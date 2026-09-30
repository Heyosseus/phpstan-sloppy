<?php

declare(strict_types=1);

namespace Heyosseus\PhpstanSloppy;

use Heyosseus\Sloppy\Analysis\Finding;
use Heyosseus\Sloppy\Analysis\Severity;
use Heyosseus\Sloppy\Evidence\EvidenceCollector;
use Heyosseus\Sloppy\Git\ChangedFile;
use Heyosseus\Sloppy\Sloppy;
use InvalidArgumentException;
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
    public const string INTERNAL_ERROR = RunReport::INTERNAL_ERROR;

    public const string SCORE = RunReport::SCORE;

    public function __construct(private Options $options) {}

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
            return $this->reports(new AnalysedPaths($files));
        } catch (Throwable $exception) {
            // A broken configuration must fail the run it was meant to gate,
            // visibly and without taking PHPStan's own errors down with it.
            return [RunReport::couldNotRun($exception)];
        }
    }

    /**
     * @return list<Report>
     */
    private function reports(AnalysedPaths $paths): array
    {
        $project = Project::load($this->options);
        $configuration = $project->sloppy->configuration;

        if (! $configuration->enabled()) {
            return [];
        }

        if ($this->options->minConfidence !== null) {
            $configuration = $configuration->withMinConfidence(self::percentage('minConfidence', $this->options->minConfidence));
        }

        if ($this->options->minScore !== null) {
            self::percentage('minScore', $this->options->minScore);
        }

        $filter = RuleFilter::for($this->options, $configuration);
        $sloppy = $filter->narrow($project->sloppy->withConfiguration($configuration));
        $threshold = $this->threshold($configuration->failOn());

        if (! $threshold instanceof Severity && $this->options->minScore === null) {
            return [];
        }

        $base = DiffBase::resolve($this->options->diffBase, $sloppy->git(), $configuration->basePath);

        return $base === null
            ? $this->scan($sloppy, $filter, $threshold, $paths)
            : $this->diff($sloppy, $filter, $threshold, $base, $paths);
    }

    /**
     * Everything Sloppy finds in the files PHPStan analysed.
     *
     * @return list<Report>
     */
    private function scan(Sloppy $sloppy, RuleFilter $filter, ?Severity $threshold, AnalysedPaths $paths): array
    {
        $configuration = $sloppy->configuration;
        $map = $sloppy->fileMap();
        $only = $sloppy->files()->named($map, $paths->files);

        // Nothing to report on is no reason to parse the whole project.
        if ($only === []) {
            return [];
        }

        $map = $this->withEditorBuffer($map);
        $cache = $this->options->cacheDirectory === null ? null : new ResultCache($this->options->cacheDirectory);
        $slot = $configuration->basePath.'|'.$this->options->configPath().'|'.($this->options->editorFile === null ? 'files' : 'editor');
        $key = $cache instanceof ResultCache ? CacheKey::for($sloppy, $this->options, $map, $only, $paths->files) : '';
        $cached = $cache?->get($slot, $key);

        if ($cached !== null) {
            return $cached;
        }

        $result = $sloppy->analyzer()->analyze($map, $only);

        if ($this->options->useBaseline) {
            $result = $sloppy->baselines()->apply($result, $configuration->baselinePath(), $sloppy->scores());
        }

        $reports = [];

        foreach ($result->findings as $finding) {
            if ($this->reportable($finding, $filter, $threshold)) {
                $reports[] = FindingReport::from($finding, $paths->spell($configuration->basePath, $finding->location->relativePath), $this->options->explain);
            }
        }

        $reports = [
            ...$reports,
            ...RunReport::ruleFailures($result->errors, $result->analyzedFiles, $configuration->basePath, $paths),
            ...RunReport::scoreBelow($this->options->minScore, $result->score),
        ];

        $cache?->put($slot, $key, $reports);

        return $reports;
    }

    /**
     * Only what the branch introduced since `$base`.
     *
     * The comparisons that need two revisions -- `SL502` baseline growth and
     * `SL503` weakened tests -- come with it. Their findings sit in a baseline
     * or a test PHPStan may not analyse, and are reported there anyway: they
     * are exactly what the base revision is for.
     *
     * @return list<Report>
     */
    private function diff(Sloppy $sloppy, RuleFilter $filter, ?Severity $threshold, string $base, AnalysedPaths $paths): array
    {
        $configuration = $sloppy->configuration;
        $report = $sloppy->diff($base);
        $evidence = EvidenceCollector::fromConfiguration($configuration)->ids();
        $reports = [];

        foreach ($report->new as $finding) {
            $absolute = $configuration->basePath.'/'.$finding->location->relativePath;
            $analysed = $paths->find($absolute);

            if ($this->reportable($finding, $filter, $threshold) && ($analysed !== null || in_array($finding->ruleId, $evidence, true))) {
                $reports[] = FindingReport::from($finding, $analysed ?? $absolute, $this->options->explain);
            }
        }

        $changed = array_map(static fn (ChangedFile $file): string => $file->relativePath, $report->changedFiles);

        return [
            ...$reports,
            ...RunReport::ruleFailures($report->errors, $changed, $configuration->basePath, $paths),
            ...RunReport::scoreBelow($this->options->minScore, $report->currentScore),
        ];
    }

    private function reportable(Finding $finding, RuleFilter $filter, ?Severity $threshold): bool
    {
        return $threshold instanceof Severity
            && $finding->severity->isAtLeast($threshold)
            && $filter->accepts($finding->ruleId);
    }

    /**
     * The file map with the editor's unsaved copy standing in for the file
     * on disk, the same substitution PHPStan's `--tmp-file` makes.
     *
     * @param  array<string, string>  $map
     * @return array<string, string>
     */
    private function withEditorBuffer(array $map): array
    {
        if ($this->options->editorFile === null || $this->options->editorInsteadOf === null) {
            return $map;
        }

        $insteadOf = AnalysedPaths::comparable($this->options->editorInsteadOf);

        foreach ($map as $relative => $absolute) {
            if (AnalysedPaths::comparable($absolute) === $insteadOf) {
                $map[$relative] = $this->options->editorFile;
            }
        }

        return $map;
    }

    private function threshold(?Severity $configured): ?Severity
    {
        if ($this->options->failOn === null) {
            return $configured;
        }

        return $this->options->failOn === Options::NEVER ? null : Severity::parse($this->options->failOn);
    }

    private static function percentage(string $parameter, int $value): int
    {
        if ($value < 0 || $value > 100) {
            throw new InvalidArgumentException(sprintf('The sloppy.%s parameter must be between 0 and 100; it is %d.', $parameter, $value));
        }

        return $value;
    }
}
