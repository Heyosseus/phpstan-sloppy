<?php

declare(strict_types=1);

namespace Heyosseus\PhpstanSloppy;

/**
 * The files PHPStan analysed, as PHPStan spells them.
 *
 * Errors must carry PHPStan's spelling of a path for its ignore comments and
 * baseline to match, and on Windows `C:\app\A.php` and `c:/app/A.php` are the
 * same file.
 */
final readonly class AnalysedPaths
{
    /**
     * @var array<string, string> PHPStan's paths, keyed by their comparable spelling.
     */
    private array $paths;

    /**
     * @param  list<string>  $files  Absolute paths, as PHPStan spells them.
     */
    public function __construct(public array $files)
    {
        $paths = [];

        foreach ($files as $file) {
            $paths[self::comparable($file)] = $file;
        }

        $this->paths = $paths;
    }

    /**
     * PHPStan's spelling of a file, or null when PHPStan did not analyse it.
     */
    public function find(string $absolute): ?string
    {
        return $this->paths[self::comparable($absolute)] ?? null;
    }

    /**
     * PHPStan's spelling of a project file, or Sloppy's own when PHPStan did
     * not analyse it.
     */
    public function spell(string $basePath, string $relative): string
    {
        $absolute = $basePath.'/'.$relative;

        return $this->find($absolute) ?? $absolute;
    }

    public static function comparable(string $path): string
    {
        $real = realpath($path);
        $resolved = str_replace('\\', '/', $real === false ? $path : $real);

        return PHP_OS_FAMILY === 'Windows' ? mb_strtolower($resolved) : $resolved;
    }
}
