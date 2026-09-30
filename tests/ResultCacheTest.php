<?php

declare(strict_types=1);

namespace Heyosseus\PhpstanSloppy\Tests;

use Heyosseus\PhpstanSloppy\Options;
use Heyosseus\PhpstanSloppy\Report;
use Heyosseus\PhpstanSloppy\ResultCache;
use Heyosseus\PhpstanSloppy\SloppyRunner;
use PHPUnit\Framework\TestCase;

final class ResultCacheTest extends TestCase
{
    private TemporaryProject $project;

    private string $cache;

    protected function setUp(): void
    {
        $this->project = TemporaryProject::copy('project');
        $this->cache = $this->project->root.'/.phpstan-cache';
    }

    protected function tearDown(): void
    {
        $this->project->remove();
    }

    public function test_a_second_run_with_nothing_changed_is_answered_from_the_cache(): void
    {
        self::assertSame(['sloppy.SL107'], self::identifiers($this->analyse()));

        // Rewrite the stored answer: if the next run reads it, the cache hit.
        $this->tamper(static fn (Report $report): Report => new Report('sloppy.fromCache', $report->message, $report->file, $report->line));

        self::assertSame(['sloppy.fromCache'], self::identifiers($this->analyse()));
    }

    public function test_editing_a_file_sloppy_reads_runs_it_again(): void
    {
        $this->analyse();
        $this->tamper(static fn (Report $report): Report => new Report('sloppy.fromCache', $report->message));

        // QuoteTotals is not reported on, but the duplicate rule reads it.
        file_put_contents($this->project->path('src/Billing/QuoteTotals.php'), "<?php\n\nnamespace App\\Billing;\n\nfinal class QuoteTotals {}\n");

        self::assertSame(['sloppy.SL107'], self::identifiers($this->analyse()));
    }

    public function test_changing_the_configuration_or_a_parameter_runs_it_again(): void
    {
        $this->analyse();
        $this->tamper(static fn (Report $report): Report => new Report('sloppy.fromCache', $report->message));

        self::assertSame(['sloppy.SL104', 'sloppy.SL107'], self::identifiers($this->analyse(failOn: 'medium')));

        $this->tamper(static fn (Report $report): Report => new Report('sloppy.fromCache', $report->message));
        file_put_contents($this->project->path('sloppy.php'), "<?php\n\nreturn ['paths' => ['src'], 'fail_on' => 'critical'];\n");

        self::assertSame([], $this->analyse());
    }

    public function test_a_new_baseline_runs_it_again(): void
    {
        $this->analyse();
        $this->tamper(static fn (Report $report): Report => new Report('sloppy.fromCache', $report->message));

        copy(__DIR__.'/Fixtures/baselined/.sloppy-baseline.json', $this->project->path('.sloppy-baseline.json'));

        self::assertSame([], $this->analyse());
    }

    public function test_a_configuration_holding_a_closure_is_still_cached_and_its_edits_seen(): void
    {
        $configuration = "<?php\n\nreturn ['paths' => ['src'], 'fail_on' => 'high', 'x' => static fn (): int => %d];\n";
        file_put_contents($this->project->path('sloppy.php'), sprintf($configuration, 1));

        self::assertSame(['sloppy.SL107'], self::identifiers($this->analyse()));
        $this->tamper(static fn (Report $report): Report => new Report('sloppy.fromCache', $report->message));
        self::assertSame(['sloppy.fromCache'], self::identifiers($this->analyse()));

        file_put_contents($this->project->path('sloppy.php'), sprintf($configuration, 2));

        self::assertSame(['sloppy.SL107'], self::identifiers($this->analyse()));
    }

    public function test_a_cache_that_cannot_be_read_is_no_cache(): void
    {
        $this->analyse();

        foreach ((array) glob($this->cache.'/*.cache') as $file) {
            file_put_contents((string) $file, 'not a cache entry');
        }

        self::assertSame(['sloppy.SL107'], self::identifiers($this->analyse()));
    }

    public function test_without_a_cache_directory_nothing_is_written(): void
    {
        (new SloppyRunner(new Options(projectRoot: $this->project->root, workingDirectory: $this->project->root)))->run($this->files());

        self::assertDirectoryDoesNotExist($this->cache);
    }

    public function test_the_parameters_put_the_cache_in_phpstans_tmp_dir(): void
    {
        $options = Options::fromParameters(null, '/app', null, null, true, null, [], [], ' ', false, null, true, '/tmp/phpstan/');

        self::assertSame('/tmp/phpstan/sloppy', $options->cacheDirectory);
        self::assertNull($options->diffBase);
        self::assertNull(Options::fromParameters(null, '/app', null, null, true, null, [], [], null, false, null, false, '/tmp/phpstan')->cacheDirectory);
    }

    public function test_the_key_changes_with_anything_in_it(): void
    {
        self::assertSame(ResultCache::key(['a' => 1]), ResultCache::key(['a' => 1]));
        self::assertNotSame(ResultCache::key(['a' => 1]), ResultCache::key(['a' => 2]));
    }

    /**
     * @return list<Report>
     */
    private function analyse(?string $failOn = null): array
    {
        $runner = new SloppyRunner(new Options(
            projectRoot: $this->project->root,
            workingDirectory: $this->project->root,
            failOn: $failOn,
            cacheDirectory: $this->cache,
        ));

        $reports = $runner->run($this->files());
        usort($reports, static fn (Report $a, Report $b): int => $a->identifier <=> $b->identifier);

        return $reports;
    }

    /**
     * @return list<string>
     */
    private function files(): array
    {
        return [
            $this->project->path('src/Importer.php'),
            $this->project->path('src/Billing/InvoiceTotals.php'),
        ];
    }

    /**
     * @param  callable(Report): Report  $change
     */
    private function tamper(callable $change): void
    {
        $files = (array) glob($this->cache.'/*.cache');
        self::assertCount(1, $files);
        $file = (string) $files[0];

        /** @var array{key: string, reports: list<Report>} $entry */
        $entry = unserialize((string) file_get_contents($file));
        $entry['reports'] = array_map($change, $entry['reports']);
        file_put_contents($file, serialize($entry));
    }

    /**
     * @param  list<Report>  $reports
     * @return list<string>
     */
    private static function identifiers(array $reports): array
    {
        return array_map(static fn (Report $report): string => $report->identifier, $reports);
    }
}
