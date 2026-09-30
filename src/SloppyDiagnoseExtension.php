<?php

declare(strict_types=1);

namespace Heyosseus\PhpstanSloppy;

use Composer\InstalledVersions;
use Heyosseus\Sloppy\Analysis\Severity;
use Heyosseus\Sloppy\Sloppy;
use PHPStan\Command\Output;
use PHPStan\Diagnose\DiagnoseExtension;
use Throwable;

/**
 * What the extension read, for `vendor/bin/phpstan diagnose` (and
 * `phpstan analyse -vvv`).
 *
 * The first question about a finding that should or should not be there is
 * always "which configuration, which threshold, which rules" -- answered here
 * without running an analysis.
 */
final readonly class SloppyDiagnoseExtension implements DiagnoseExtension
{
    public function __construct(private Options $options) {}

    public function print(Output $output): void
    {
        $output->writeLineFormatted(sprintf('<info>Sloppy:</info> %s, through heyosseus/phpstan-sloppy %s', self::version('heyosseus/sloppy', Sloppy::VERSION), self::version('heyosseus/phpstan-sloppy', 'dev')));

        try {
            foreach ($this->lines() as $label => $value) {
                $output->writeLineFormatted(sprintf('<info>Sloppy %s:</info> %s', $label, $value));
            }
        } catch (Throwable $exception) {
            $output->writeLineFormatted(sprintf('<error>Sloppy cannot run:</error> %s', $exception->getMessage()));
        }

        $output->writeLineFormatted('');
    }

    /**
     * @return array<string, string>
     */
    private function lines(): array
    {
        $project = Project::load($this->options);
        $configuration = $project->sloppy->configuration;
        $registry = $project->sloppy->rules();

        if (! $configuration->enabled()) {
            return [
                'project root' => $project->root,
                'configuration' => $project->configSource,
                'status' => 'disabled by the configuration; nothing is reported',
            ];
        }

        $filter = RuleFilter::for($this->options, $configuration);
        $active = array_values(array_filter($registry->ids(), $filter->accepts(...)));
        $skipped = $registry->skipped();

        return [
            'project root' => $project->root,
            'configuration' => $project->configSource,
            'paths' => implode(', ', $configuration->paths()),
            'reports' => $this->threshold($configuration->failOn()),
            'confidence' => sprintf('%d%% and above', $this->options->minConfidence ?? $configuration->minConfidence()),
            'rules' => sprintf('%d active: %s', count($active), implode(' ', $active))
                .($skipped === [] ? '' : sprintf('; %d skipped for a framework the project does not use', count($skipped))),
            'baseline' => $this->baseline($project),
            'diff base' => $this->options->diffBase ?? 'none, every finding is reported',
            'minimum score' => $this->options->minScore === null ? 'none' : $this->options->minScore.'/100',
            'result cache' => $this->options->cacheDirectory ?? 'off',
        ];
    }

    private function threshold(?Severity $configured): string
    {
        if ($this->options->failOn === Options::NEVER) {
            return 'nothing (failOn: never)';
        }

        if ($this->options->failOn !== null) {
            return sprintf('%s and above (the failOn parameter)', Severity::parse($this->options->failOn)->value);
        }

        return $configured instanceof Severity
            ? sprintf('%s and above (the project\'s fail_on)', $configured->value)
            : 'nothing (the project\'s fail_on is null)';
    }

    private function baseline(Project $project): string
    {
        $path = $project->sloppy->configuration->baselinePath();

        if (! $this->options->useBaseline) {
            return 'ignored (useBaseline: false)';
        }

        $baseline = $project->sloppy->baselines()->load($path);

        return $baseline === null
            ? sprintf('none at %s', $path)
            : sprintf('%s, %d entries', $path, $baseline->count());
    }

    /**
     * What Composer installed, which is what a bug report needs; the package's
     * own constant is the fallback where Composer cannot say, as in a phar.
     */
    private static function version(string $package, string $fallback): string
    {
        if (! class_exists(InstalledVersions::class) || ! InstalledVersions::isInstalled($package)) {
            return $fallback;
        }

        return InstalledVersions::getPrettyVersion($package) ?? $fallback;
    }
}
