<?php

declare(strict_types=1);

namespace Heyosseus\PhpstanSloppy\Tests;

use Heyosseus\PhpstanSloppy\Options;
use Heyosseus\PhpstanSloppy\Report;
use Heyosseus\PhpstanSloppy\SloppyRunner;
use PHPUnit\Framework\TestCase;

/**
 * `diffBase`: only what a branch introduced, the way `sloppy diff` reports it.
 */
final class DiffModeTest extends TestCase
{
    private TemporaryProject $project;

    protected function setUp(): void
    {
        if (! TemporaryProject::gitIsAvailable()) {
            self::markTestSkipped('git is not installed.');
        }

        $this->project = TemporaryProject::copy('project');

        // The temporary directory may itself sit inside a repository, a home
        // directory under version control; git must not look above the project.
        // Symfony Process passes on $_ENV, not what putenv() changed.
        $_ENV['GIT_CEILING_DIRECTORIES'] = strtr(dirname($this->project->root), '\\', '/');
    }

    protected function tearDown(): void
    {
        unset($_ENV['GIT_CEILING_DIRECTORIES']);

        if (isset($this->project)) {
            $this->project->remove();
        }
    }

    public function test_only_what_the_branch_introduced_is_reported(): void
    {
        $this->branchWithNewDebt();

        $reports = $this->analyse('main', [$this->project->path('src/Importer.php'), $this->project->path('src/Exporter.php')]);

        // Importer's swallowed exception was already on main.
        self::assertSame([
            'sloppy.SL107 src/Exporter.php',
            'sloppy.SL503 tests/ExporterTest.php',
        ], $this->described($reports));
    }

    public function test_a_weakened_test_is_reported_although_phpstan_did_not_analyse_it(): void
    {
        $this->branchWithNewDebt();

        $reports = $this->analyse('main', [$this->project->path('src/Importer.php')]);

        self::assertSame(['sloppy.SL503 tests/ExporterTest.php'], $this->described($reports));
        self::assertStringContainsString('ExporterTest::test_it_exports() is now skipped', $reports[0]->message);
    }

    public function test_a_branch_with_nothing_new_reports_nothing(): void
    {
        $this->project->git(['init', '--quiet']);
        $this->project->commitAll('Base');

        self::assertSame([], $this->analyse('main', [$this->project->path('src/Importer.php')]));
    }

    public function test_a_revision_that_does_not_exist_is_an_internal_error(): void
    {
        $this->project->git(['init', '--quiet']);
        $this->project->commitAll('Base');

        $reports = $this->analyse('develop', [$this->project->path('src/Importer.php')]);

        self::assertSame(['sloppy.internalError'], array_map(static fn (Report $report): string => $report->identifier, $reports));
        self::assertStringContainsString('diffBase [develop] is not a revision here; tried develop, origin/develop', $reports[0]->message);
    }

    public function test_an_explicit_revision_outside_git_is_an_internal_error(): void
    {
        $reports = $this->analyse('main', [$this->project->path('src/Importer.php')]);

        self::assertSame(['sloppy.internalError'], array_map(static fn (Report $report): string => $report->identifier, $reports));
        self::assertStringContainsString('is not a git checkout', $reports[0]->message);
    }

    public function test_auto_outside_git_reports_everything(): void
    {
        $reports = $this->analyse('auto', [$this->project->path('src/Importer.php')]);

        self::assertSame(['sloppy.SL107 src/Importer.php'], $this->described($reports));
    }

    private function branchWithNewDebt(): void
    {
        $this->project->git(['init', '--quiet']);
        $this->project->write('tests/ExporterTest.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace App\Tests;

            use PHPUnit\Framework\TestCase;

            final class ExporterTest extends TestCase
            {
                public function test_it_exports(): void
                {
                    $this->assertSame('csv', (new \App\Exporter)->format());
                }
            }

            PHP);
        $this->project->commitAll('Base');
        $this->project->git(['checkout', '--quiet', '-b', 'feature']);

        $this->project->write('src/Exporter.php', <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace App;

            final class Exporter
            {
                public function format(): string
                {
                    try {
                        return $this->detect();
                    } catch (\Throwable $exception) {
                    }

                    return 'csv';
                }

                private function detect(): string
                {
                    throw new \RuntimeException('Unknown format.');
                }
            }

            PHP);
        $this->project->write('tests/ExporterTest.php', str_replace(
            '        $this->assertSame',
            "        \$this->markTestSkipped('Flaky.');\n\n        \$this->assertSame",
            (string) file_get_contents($this->project->path('tests/ExporterTest.php')),
        ));
        $this->project->commitAll('Feature');
    }

    /**
     * @param  list<string>  $files
     * @return list<Report>
     */
    private function analyse(string $diffBase, array $files): array
    {
        return (new SloppyRunner(new Options(
            projectRoot: $this->project->root,
            workingDirectory: $this->project->root,
            diffBase: $diffBase,
        )))->run($files);
    }

    /**
     * @param  list<Report>  $reports
     * @return list<string>
     */
    private function described(array $reports): array
    {
        $root = str_replace('\\', '/', $this->project->root).'/';
        $described = array_map(
            static fn (Report $report): string => $report->identifier.' '.str_replace($root, '', str_replace('\\', '/', (string) $report->file)),
            $reports,
        );
        sort($described);

        return $described;
    }
}
