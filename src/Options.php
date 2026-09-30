<?php

declare(strict_types=1);

namespace Heyosseus\PhpstanSloppy;

use PHPStan\DependencyInjection\Container;

/**
 * The `sloppy` parameters from the PHPStan configuration, and the parts of
 * PHPStan's own state that decide what Sloppy reads.
 *
 * Only the shape is checked here; PHPStan's parameter schema has already
 * refused the wrong types. Values that need Sloppy to judge -- a rule ID that
 * does not exist, a revision git cannot find -- are judged when Sloppy runs,
 * so the mistake is reported as an error in the run rather than a crash.
 */
final readonly class Options
{
    public const string NEVER = 'never';

    public const string AUTO = 'auto';

    /**
     * @param  string|null  $projectRoot  Where the project's composer.json and Sloppy configuration are; null for the working directory.
     * @param  string  $workingDirectory  The directory PHPStan runs in, which relative paths are read from.
     * @param  string|null  $configPath  An explicit configuration file, relative to the project root or absolute.
     * @param  string|null  $failOn  The lowest severity reported, or `never`; null for the project's own `fail_on`.
     * @param  bool  $useBaseline  Leave out what `.sloppy-baseline.json` accepts.
     * @param  int|null  $minConfidence  The lowest confidence reported, 0 to 100; null for the project's own `min_confidence`.
     * @param  list<string>  $onlyRules  Report these rules and no others; empty for every rule.
     * @param  list<string>  $excludeRules  Never report these rules.
     * @param  string|null  $diffBase  Report only what changed since this revision, `auto` for the CI's base branch, or null for everything.
     * @param  bool  $explain  Add each rule's explanation of why the pattern costs you to the tips.
     * @param  int|null  $minScore  Fail when Sloppy's score for the analysed files is below this, 0 to 100.
     * @param  string|null  $cacheDirectory  Where to keep results between runs; null for no cache.
     * @param  string|null  $editorFile  The unsaved copy PHPStan's editor mode analyses (`--tmp-file`).
     * @param  string|null  $editorInsteadOf  The file that copy stands in for (`--instead-of`).
     */
    public function __construct(
        public ?string $projectRoot,
        public string $workingDirectory,
        public ?string $configPath = null,
        public ?string $failOn = null,
        public bool $useBaseline = true,
        public ?int $minConfidence = null,
        public array $onlyRules = [],
        public array $excludeRules = [],
        public ?string $diffBase = null,
        public bool $explain = false,
        public ?int $minScore = null,
        public ?string $cacheDirectory = null,
        public ?string $editorFile = null,
        public ?string $editorInsteadOf = null,
    ) {}

    /**
     * Built from extension.neon, where the cache is a switch and PHPStan's
     * temporary directory says where it goes. The container is PHPStan's,
     * autowired, for the parameters of editor mode.
     *
     * @param  list<string>  $onlyRules
     * @param  list<string>  $excludeRules
     */
    public static function fromParameters(
        ?string $projectRoot,
        string $workingDirectory,
        ?string $configPath,
        ?string $failOn,
        bool $useBaseline,
        ?int $minConfidence,
        array $onlyRules,
        array $excludeRules,
        ?string $diffBase,
        bool $explain,
        ?int $minScore,
        bool $cache,
        string $tmpDir,
        ?Container $container = null,
    ): self {
        return new self(
            projectRoot: $projectRoot,
            workingDirectory: $workingDirectory,
            configPath: $configPath,
            failOn: $failOn,
            useBaseline: $useBaseline,
            minConfidence: $minConfidence,
            onlyRules: $onlyRules,
            excludeRules: $excludeRules,
            diffBase: $diffBase === null || trim($diffBase) === '' ? null : trim($diffBase),
            explain: $explain,
            minScore: $minScore,
            cacheDirectory: $cache ? rtrim($tmpDir, '/\\').'/sloppy' : null,
            editorFile: self::containerString($container, 'singleReflectionFile'),
            editorInsteadOf: self::containerString($container, 'singleReflectionInsteadOfFile'),
        );
    }

    /**
     * One of PHPStan's own parameters, where this PHPStan version has it.
     *
     * Editor mode (`--tmp-file` and `--instead-of`) arrived during PHPStan
     * 2.1, so naming its parameters in extension.neon would stop the
     * extension loading on the releases before it.
     */
    private static function containerString(?Container $container, string $parameter): ?string
    {
        if (! $container instanceof Container || ! $container->hasParameter($parameter)) {
            return null;
        }

        $value = $container->getParameter($parameter);

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function projectRoot(): string
    {
        return self::resolve($this->projectRoot ?? $this->workingDirectory, $this->workingDirectory);
    }

    public function configPath(): ?string
    {
        return $this->configPath === null ? null : self::resolve($this->configPath, $this->projectRoot());
    }

    /**
     * What decides the result, and nothing that only decides where it is
     * kept: two runs that differ only in cache location find the same things.
     *
     * @return array<string, mixed>
     */
    public function fingerprint(): array
    {
        return [
            'failOn' => $this->failOn,
            'useBaseline' => $this->useBaseline,
            'minConfidence' => $this->minConfidence,
            'onlyRules' => $this->onlyRules,
            'excludeRules' => $this->excludeRules,
            'explain' => $this->explain,
            'minScore' => $this->minScore,
        ];
    }

    public static function resolve(string $path, string $base): string
    {
        $isAbsolute = str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;

        return $isAbsolute ? $path : rtrim($base, '/\\').'/'.$path;
    }
}
