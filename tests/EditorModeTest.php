<?php

declare(strict_types=1);

namespace Heyosseus\PhpstanSloppy\Tests;

use Heyosseus\PhpstanSloppy\Options;
use Heyosseus\PhpstanSloppy\Report;
use Heyosseus\PhpstanSloppy\SloppyRunner;
use PHPUnit\Framework\TestCase;

/**
 * PHPStan's editor mode: `--tmp-file` is the editor's unsaved buffer and
 * `--instead-of` the file it stands in for. Sloppy reads the buffer, and the
 * findings stay on the file the editor has open.
 */
final class EditorModeTest extends TestCase
{
    private TemporaryProject $project;

    protected function setUp(): void
    {
        $this->project = TemporaryProject::copy('project');
    }

    protected function tearDown(): void
    {
        $this->project->remove();
    }

    public function test_the_unsaved_buffer_is_analysed_instead_of_the_file_on_disk(): void
    {
        // On disk the exception is swallowed; in the buffer it is rethrown.
        $buffer = $this->buffer(str_replace(
            "} catch (\\Throwable \$exception) {\n        }",
            "} catch (\\Throwable \$exception) {\n            throw new \\LogicException('Import failed.', 0, \$exception);\n        }",
            (string) file_get_contents($this->project->path('src/Importer.php')),
        ));

        self::assertSame([], $this->analyse($buffer));
    }

    public function test_a_finding_in_the_buffer_is_reported_on_the_file_the_editor_has_open(): void
    {
        $clean = "<?php\n\nnamespace App;\n\nfinal class Importer\n{\n    public function run(): void {}\n}\n";
        file_put_contents($this->project->path('src/Importer.php'), $clean);
        $buffer = $this->buffer((string) file_get_contents(__DIR__.'/Fixtures/project/src/Importer.php'));

        $reports = $this->analyse($buffer);

        self::assertSame(['sloppy.SL107'], array_map(static fn (Report $report): string => $report->identifier, $reports));
        self::assertSame($this->project->path('src/Importer.php'), $reports[0]->file);
        self::assertSame(13, $reports[0]->line);
    }

    /**
     * @return list<Report>
     */
    private function analyse(string $buffer): array
    {
        $runner = new SloppyRunner(new Options(
            projectRoot: $this->project->root,
            workingDirectory: $this->project->root,
            editorFile: $buffer,
            editorInsteadOf: $this->project->path('src/Importer.php'),
        ));

        return $runner->run([$this->project->path('src/Importer.php')]);
    }

    private function buffer(string $contents): string
    {
        $path = $this->project->root.'/buffer.tmp';
        file_put_contents($path, $contents);

        return $path;
    }
}
