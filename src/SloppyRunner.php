<?php

declare(strict_types=1);

namespace Heyosseus\PhpstanSloppy;

use Heyosseus\Sloppy\Analysis\AnalysisResult;
use Heyosseus\Sloppy\Analysis\Finding;
use Heyosseus\Sloppy\Analysis\Severity;
use Heyosseus\Sloppy\Sloppy;
use RuntimeException;
use Throwable;

/**
 * Runs Sloppy over the files PHPStan analysed and says what to report.
 *
 * Everything that decides *what* is found belongs to Sloppy: its rules, its
 * configuration, its baseline. This decides only what PHPStan sees of it --
 * which findings clear the threshold, and which of PHPStan's paths each one
 * belongs to -- so PHPStan and `sloppy ci` cannot disagree about a finding.
 */
final readonly class SloppyRunner
{
    public const string INTERNAL_ERROR = 'sloppy.internalError';

    private const string NEVER = 'never';

    /**
     * @param  string|null  $projectRoot  Where the project's composer.json and Sloppy configuration are; null for the working directory.
     * @param  string|null  $configPath  An explicit configuration file, relative to the project root or absolute.
     * @param  string|null  $failOn  The lowest severity reported, or `never`; null for the project's own `fail_on`.
     */
    public function __construct(
        private ?string $projectRoot,
        private string $workingDirectory,
        private ?string $configPath = null,
        private ?string $failOn = null,
        private bool $useBaseline = true,
    ) {}

    /**
     * @param  list<string>  $files  Absolute paths, as PHPStan spells them.
     * @return list<Report>
     */
    public function run(array $files): array
    {
        if ($files === []) {
            return [];
        }

        try {
            return $this->reports($files);
        } catch (Throwable $exception) {
            // A broken configuration must fail the run it was meant to gate,
            // visibly and without taking PHPStan's own errors down with it.
            return [new Report(
                identifier: self::INTERNAL_ERROR,
                message: 'Sloppy could not run: '.$exception->getMessage(),
                tip: 'Check the Sloppy configuration, or the sloppy parameters in your PHPStan configuration.',
            )];
        }
    }

    /**
     * @param  list<string>  $files
     * @return list<Report>
     */
    private function reports(array $files): array
    {
        $root = self::resolve($this->projectRoot ?? $this->workingDirectory, $this->workingDirectory);

        if (! is_dir($root)) {
            throw new RuntimeException(sprintf('Project root [%s] does not exist.', $root));
        }

        $sloppy = Sloppy::forProject($root, $this->configPath === null ? null : self::resolve($this->configPath, $root));
        $configuration = $sloppy->configuration;
        $threshold = $this->threshold($configuration->failOn());

        if (! $configuration->enabled() || ! $threshold instanceof Severity) {
            return [];
        }

        $result = $sloppy->analyzePaths($files, $this->useBaseline);
        $paths = [];

        foreach ($files as $file) {
            $paths[self::comparable($file)] = $file;
        }

        $reports = [];

        foreach ($result->findings as $finding) {
            if ($finding->severity->isAtLeast($threshold)) {
                $reports[] = $this->finding($finding, $configuration->basePath, $paths);
            }
        }

        return [...$reports, ...$this->ruleFailures($result, $configuration->basePath, $paths)];
    }

    /**
     * @param  array<string, string>  $paths  PHPStan's paths, keyed by their comparable spelling.
     */
    private function finding(Finding $finding, string $basePath, array $paths): Report
    {
        return new Report(
            identifier: self::identifier($finding->ruleId),
            message: sprintf('%s: %s', $finding->ruleName, $finding->message),
            file: self::pathFor($basePath, $finding->location->relativePath, $paths),
            line: $finding->location->line,
            tip: $finding->suggestion,
        );
    }

    /**
     * A rule that threw on a file.
     *
     * Sloppy keeps going when one rule fails on one file, and records the
     * failure as `<rule> in <path>`. Parse failures are recorded too, under
     * the bare path, and are left out: PHPStan reports those itself.
     *
     * @param  array<string, string>  $paths
     * @return list<Report>
     */
    private function ruleFailures(AnalysisResult $result, string $basePath, array $paths): array
    {
        $reports = [];

        foreach ($result->errors as $where => $message) {
            foreach ($result->analyzedFiles as $relative) {
                $suffix = ' in '.$relative;

                if (! str_ends_with($where, $suffix)) {
                    continue;
                }

                $reports[] = new Report(
                    identifier: self::INTERNAL_ERROR,
                    message: sprintf('Sloppy rule %s failed on this file: %s', substr($where, 0, -strlen($suffix)), $message),
                    file: self::pathFor($basePath, $relative, $paths),
                    tip: 'This is a bug in Sloppy; please report it at https://github.com/heyosseus/sloppy/issues.',
                );

                break;
            }
        }

        return $reports;
    }

    private function threshold(?Severity $configured): ?Severity
    {
        if ($this->failOn === null) {
            return $configured;
        }

        return $this->failOn === self::NEVER ? null : Severity::parse($this->failOn);
    }

    /**
     * `sloppy.SL107`. A custom rule's ID is whatever its author chose, and
     * PHPStan accepts only letters, digits and inner dots.
     */
    public static function identifier(string $ruleId): string
    {
        $sanitised = trim((string) preg_replace('/[^A-Za-z0-9.]+/', '', $ruleId), '.');

        return 'sloppy.'.($sanitised === '' ? 'custom' : $sanitised);
    }

    /**
     * The path PHPStan knows a file by, so its ignore comments and baseline
     * match; Sloppy's own spelling when PHPStan did not analyse the file.
     *
     * @param  array<string, string>  $paths
     */
    private static function pathFor(string $basePath, string $relative, array $paths): string
    {
        $absolute = $basePath.'/'.$relative;

        return $paths[self::comparable($absolute)] ?? $absolute;
    }

    private static function resolve(string $path, string $base): string
    {
        $isAbsolute = str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;

        return $isAbsolute ? $path : rtrim($base, '/\\').'/'.$path;
    }

    private static function comparable(string $path): string
    {
        $real = realpath($path);
        $resolved = str_replace('\\', '/', $real === false ? $path : $real);

        return PHP_OS_FAMILY === 'Windows' ? mb_strtolower($resolved) : $resolved;
    }
}
