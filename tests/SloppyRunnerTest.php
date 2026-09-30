<?php

declare(strict_types=1);

namespace Heyosseus\PhpstanSloppy\Tests;

use Heyosseus\PhpstanSloppy\Report;
use Heyosseus\PhpstanSloppy\SloppyRunner;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SloppyRunnerTest extends TestCase
{
    public function test_nothing_analysed_is_nothing_reported(): void
    {
        self::assertSame([], (new SloppyRunner(null, __DIR__.'/Fixtures/broken'))->run([]));
    }

    public function test_the_project_root_defaults_to_the_working_directory(): void
    {
        $runner = new SloppyRunner(null, __DIR__.'/Fixtures/project');

        self::assertSame(['sloppy.SL107'], self::identifiers($runner->run([self::importer()])));
    }

    public function test_a_relative_project_root_is_read_from_the_working_directory(): void
    {
        $runner = new SloppyRunner('Fixtures/project', __DIR__);

        self::assertSame(['sloppy.SL107'], self::identifiers($runner->run([self::importer()])));
    }

    public function test_a_relative_configuration_file_is_read_from_the_project_root(): void
    {
        // disabled/sloppy.php turns Sloppy off; project/sloppy.php does not.
        $runner = new SloppyRunner(__DIR__.'/Fixtures/disabled', __DIR__, configPath: '../project/sloppy.php');

        self::assertSame(['sloppy.SL107'], self::identifiers($runner->run([(string) realpath(__DIR__.'/Fixtures/disabled/src/Importer.php')])));
    }

    public function test_a_project_root_that_does_not_exist_is_an_internal_error(): void
    {
        $reports = (new SloppyRunner('Fixtures/nowhere', __DIR__))->run([self::importer()]);

        self::assertCount(1, $reports);
        self::assertSame('sloppy.internalError', $reports[0]->identifier);
        self::assertStringContainsString('Fixtures/nowhere] does not exist', $reports[0]->message);
        self::assertNull($reports[0]->file);
    }

    public function test_an_unknown_severity_is_an_internal_error(): void
    {
        $reports = (new SloppyRunner(null, __DIR__.'/Fixtures/project', failOn: 'sometimes'))->run([self::importer()]);

        self::assertSame(['sloppy.internalError'], self::identifiers($reports));
    }

    #[DataProvider('ruleIds')]
    public function test_a_rule_id_becomes_an_identifier_phpstan_accepts(string $ruleId, string $identifier): void
    {
        self::assertSame($identifier, SloppyRunner::identifier($ruleId));
        self::assertMatchesRegularExpression('/^[a-zA-Z0-9](?:[a-zA-Z0-9\.]*[a-zA-Z0-9])?$/', $identifier);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function ruleIds(): iterable
    {
        yield 'a shipped rule' => ['SL107', 'sloppy.SL107'];
        yield 'punctuation' => ['ACME-001', 'sloppy.ACME001'];
        yield 'dots at the ends' => ['.APP.1.', 'sloppy.APP.1'];
        yield 'nothing usable' => ['--', 'sloppy.custom'];
    }

    private static function importer(): string
    {
        return (string) realpath(__DIR__.'/Fixtures/project/src/Importer.php');
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
