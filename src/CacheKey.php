<?php

declare(strict_types=1);

namespace Heyosseus\PhpstanSloppy;

use Heyosseus\Sloppy\Sloppy;
use ReflectionClass;

/**
 * Everything a run's reports depend on, hashed into one {@see ResultCache} key.
 *
 * Every source file, not only the analysed ones, because cross-file rules
 * read them all; every rule's own file, so upgrading Sloppy or editing a
 * custom rule is a new key; the configuration as loaded and as written, the
 * baseline, and composer's files, which decide the framework rules.
 */
final class CacheKey
{
    /**
     * Where Sloppy looks for a configuration file, as `bin/sloppy` does.
     */
    private const array CONFIGURATION_FILES = ['config/sloppy.php', 'sloppy.php', '.sloppy.php'];

    /**
     * @param  array<string, string>  $map  Relative path => the file Sloppy reads.
     * @param  list<string>  $only  The relative paths reported on.
     * @param  list<string>  $files  PHPStan's paths, which the reports carry.
     */
    public static function for(Sloppy $sloppy, Options $options, array $map, array $only, array $files): string
    {
        $configuration = $sloppy->configuration;
        $root = $configuration->basePath;
        sort($files);

        $configurationFiles = array_map(static fn (string $file): string => $root.'/'.$file, self::CONFIGURATION_FILES);
        $configurationFiles[] = $options->configPath() ?? '';

        return ResultCache::key([
            'sloppy' => Sloppy::VERSION,
            'extension' => self::hashes(self::ownFiles()),
            'options' => $options->fingerprint(),
            // var_export rather than serialize: a configuration holding a
            // closure exports without complaint, and the file it is written
            // in is hashed below, so an edit to the closure is still seen.
            'configuration' => var_export($configuration, true),
            'configurationFiles' => self::hashes($configurationFiles),
            'rules' => self::hashes(array_map(
                static fn (object $rule): string|false => (new ReflectionClass($rule))->getFileName(),
                $sloppy->rules()->rules(),
            )),
            'baseline' => $options->useBaseline ? self::hash($configuration->baselinePath()) : null,
            'composer' => self::hashes([$root.'/composer.json', $root.'/composer.lock']),
            'sources' => self::hashes($map),
            'only' => $only,
            'phpstan' => $files,
        ]);
    }

    /**
     * @param  array<array-key, string|false>  $files
     * @return array<array-key, string|null>
     */
    private static function hashes(array $files): array
    {
        return array_map(static fn (string|false $file): ?string => $file === false ? null : self::hash($file), $files);
    }

    private static function hash(string $path): ?string
    {
        $hash = is_file($path) ? hash_file('xxh128', $path) : false;

        return $hash === false ? null : $hash;
    }

    /**
     * @return array<string, string>
     */
    private static function ownFiles(): array
    {
        $files = glob(__DIR__.'/*.php');

        return $files === false ? [] : array_combine(array_map(basename(...), $files), $files);
    }
}
