<?php

declare(strict_types=1);

namespace Heyosseus\PhpstanSloppy\Tests;

use Heyosseus\PhpstanSloppy\FindingReport;
use Heyosseus\PhpstanSloppy\Options;
use Heyosseus\PhpstanSloppy\Report;
use Heyosseus\PhpstanSloppy\SloppyRunner;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SloppyRunnerTest extends TestCase
{
    public function test_nothing_analysed_is_nothing_reported(): void
    {
        self::assertSame([], (new SloppyRunner(new Options(null, __DIR__.'/Fixtures/broken')))->run([]));
    }

    public function test_the_project_root_defaults_to_the_working_directory(): void
    {
        $runner = new SloppyRunner(new Options(null, __DIR__.'/Fixtures/project'));

        self::assertSame(['sloppy.SL107'], self::identifiers($runner->run([self::importer()])));
    }

    public function test_a_relative_project_root_is_read_from_the_working_directory(): void
    {
        $runner = new SloppyRunner(new Options('Fixtures/project', __DIR__));

        self::assertSame(['sloppy.SL107'], self::identifiers($runner->run([self::importer()])));
    }

    public function test_a_relative_configuration_file_is_read_from_the_project_root(): void
    {
        // disabled/sloppy.php turns Sloppy off; project/sloppy.php does not.
        $runner = new SloppyRunner(new Options(__DIR__.'/Fixtures/disabled', __DIR__, configPath: '../project/sloppy.php'));

        self::assertSame(['sloppy.SL107'], self::identifiers($runner->run([(string) realpath(__DIR__.'/Fixtures/disabled/src/Importer.php')])));
    }

    public function test_a_project_root_that_does_not_exist_is_an_internal_error(): void
    {
        $reports = (new SloppyRunner(new Options('Fixtures/nowhere', __DIR__)))->run([self::importer()]);

        self::assertCount(1, $reports);
        self::assertSame('sloppy.internalError', $reports[0]->identifier);
        self::assertStringContainsString('Fixtures/nowhere] does not exist', $reports[0]->message);
        self::assertNull($reports[0]->file);
    }

    public function test_an_unknown_severity_is_an_internal_error(): void
    {
        $reports = (new SloppyRunner(new Options(null, __DIR__.'/Fixtures/project', failOn: 'sometimes')))->run([self::importer()]);

        self::assertSame(['sloppy.internalError'], self::identifiers($reports));
    }

    public function test_only_rules_reports_those_rules_and_no_others(): void
    {
        $reports = self::project(failOn: 'medium', onlyRules: ['sloppy.SL104'])->run(self::everything());

        self::assertSame(['sloppy.SL104', 'sloppy.SL104'], self::identifiers($reports));
    }

    public function test_exclude_rules_leaves_those_rules_out_whatever_their_case(): void
    {
        $reports = self::project(failOn: 'medium', excludeRules: ['sl104'])->run(self::everything());

        self::assertSame(['sloppy.SL107'], self::identifiers($reports));
    }

    public function test_a_rule_that_does_not_exist_is_refused_with_the_closest_match(): void
    {
        $reports = self::project(excludeRules: ['SL17'])->run(self::everything());

        self::assertSame(['sloppy.internalError'], self::identifiers($reports));
        self::assertStringContainsString('No Sloppy rule has the ID [SL17]. Did you mean [SL107]?', $reports[0]->message);
    }

    public function test_a_diff_only_rule_can_be_named(): void
    {
        $reports = self::project(excludeRules: ['SL503'])->run(self::everything());

        self::assertSame(['sloppy.SL107'], self::identifiers($reports));
    }

    public function test_min_confidence_leaves_out_findings_sloppy_is_less_sure_of(): void
    {
        // The swallowed exception is reported at 96% confidence.
        self::assertSame(['sloppy.SL107'], self::identifiers(self::project(minConfidence: 96)->run(self::everything())));
        self::assertSame([], self::project(minConfidence: 97)->run(self::everything()));
    }

    public function test_a_percentage_out_of_range_is_an_internal_error(): void
    {
        $reports = self::project(minScore: 101)->run(self::everything());

        self::assertSame(['sloppy.internalError'], self::identifiers($reports));
        self::assertStringContainsString('sloppy.minScore parameter must be between 0 and 100', $reports[0]->message);
    }

    public function test_a_score_below_the_minimum_fails_the_run_once(): void
    {
        $reports = self::project(failOn: 'never', minScore: 100)->run(self::everything());

        self::assertSame(['sloppy.score'], self::identifiers($reports));
        self::assertMatchesRegularExpression('#^Sloppy scores the analysed code \d+/100 \(.+\), below the minimum of 100\.$#', $reports[0]->message);
        self::assertNull($reports[0]->file);
    }

    public function test_a_score_at_or_above_the_minimum_says_nothing(): void
    {
        self::assertSame([], self::project(failOn: 'never', minScore: 0)->run(self::everything()));
    }

    public function test_explain_adds_why_the_pattern_costs_you(): void
    {
        $plain = self::project()->run([self::importer()]);
        $explained = self::project(explain: true)->run([self::importer()]);

        self::assertCount(2, $plain[0]->tips);
        self::assertCount(3, $explained[0]->tips);
        self::assertStringStartsWith('Why it matters: ', $explained[0]->tips[1]);
    }

    public function test_a_custom_rule_gets_no_documentation_link(): void
    {
        self::assertNull(FindingReport::documentation('ACME-001'));
        self::assertNull(FindingReport::documentation('SL999'));
        self::assertStringEndsWith('rules.md#laravel', (string) FindingReport::documentation('SL203'));
        self::assertStringEndsWith('rules.md#suppression', (string) FindingReport::documentation('SL503'));
    }

    #[DataProvider('ruleIds')]
    public function test_a_rule_id_becomes_an_identifier_phpstan_accepts(string $ruleId, string $identifier): void
    {
        self::assertSame($identifier, FindingReport::identifier($ruleId));
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

    /**
     * @param  list<string>  $onlyRules
     * @param  list<string>  $excludeRules
     */
    private static function project(
        ?string $failOn = null,
        array $onlyRules = [],
        array $excludeRules = [],
        ?int $minConfidence = null,
        ?int $minScore = null,
        bool $explain = false,
    ): SloppyRunner {
        return new SloppyRunner(new Options(
            projectRoot: __DIR__.'/Fixtures/project',
            workingDirectory: __DIR__,
            failOn: $failOn,
            minConfidence: $minConfidence,
            onlyRules: $onlyRules,
            excludeRules: $excludeRules,
            explain: $explain,
            minScore: $minScore,
        ));
    }

    /**
     * @return list<string>
     */
    private static function everything(): array
    {
        return [
            self::importer(),
            (string) realpath(__DIR__.'/Fixtures/project/src/Billing/InvoiceTotals.php'),
            (string) realpath(__DIR__.'/Fixtures/project/src/Billing/QuoteTotals.php'),
        ];
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
