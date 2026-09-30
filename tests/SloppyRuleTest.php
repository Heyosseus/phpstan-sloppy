<?php

declare(strict_types=1);

namespace Heyosseus\PhpstanSloppy\Tests;

use Heyosseus\PhpstanSloppy\Options;
use Heyosseus\PhpstanSloppy\SloppyFileCollector;
use Heyosseus\PhpstanSloppy\SloppyRule;
use Heyosseus\PhpstanSloppy\SloppyRunner;
use PHPStan\Analyser\Error;
use PHPStan\Collectors\Collector;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * The rule end to end, through PHPStan's own analyser: collectors, the
 * collected-data pass, ignore comments and all.
 *
 * @extends RuleTestCase<SloppyRule>
 */
final class SloppyRuleTest extends RuleTestCase
{
    private string $project = 'project';

    private ?string $failOn = null;

    private bool $useBaseline = true;

    protected function getRule(): Rule
    {
        return new SloppyRule(new SloppyRunner(new Options(
            projectRoot: self::fixture($this->project),
            workingDirectory: __DIR__,
            failOn: $this->failOn,
            useBaseline: $this->useBaseline,
        )));
    }

    /**
     * @return list<Collector<\PhpParser\Node, mixed>>
     */
    protected function getCollectors(): array
    {
        return [new SloppyFileCollector];
    }

    public function test_it_reports_a_finding_where_it_is_with_its_identifier_and_suggestion(): void
    {
        $errors = $this->errors(['src/Importer.php']);

        self::assertCount(1, $errors);
        self::assertSame('Swallowed Exception: Importer::run() catches Throwable and does nothing at all.', $errors[0]->getMessage());
        self::assertSame('sloppy.SL107', $errors[0]->getIdentifier());
        self::assertSame(13, $errors[0]->getLine());
        self::assertSame(self::path('project', 'src/Importer.php'), $errors[0]->getFilePath());
        self::assertStringContainsString('Do at least one of: log or report the exception', (string) $errors[0]->getTip());
        self::assertStringContainsString('High severity, 96% confidence. Rule SL107: https://github.com/heyosseus/sloppy/blob/main/docs/rules.md#php-and-general', (string) $errors[0]->getTip());
        self::assertSame(
            ['rule' => 'SL107', 'name' => 'Swallowed Exception', 'category' => 'error-handling', 'severity' => 'high', 'confidence' => 96],
            array_slice((array) ($errors[0]->getMetadata()['sloppy'] ?? []), 0, 5),
        );
    }

    public function test_it_follows_the_projects_fail_on_so_medium_findings_stay_out(): void
    {
        // fail_on is high, and both SL104 findings are medium.
        self::assertSame([], $this->errors(['src/Billing/InvoiceTotals.php']));
    }

    public function test_a_failon_parameter_overrides_the_project(): void
    {
        $this->failOn = 'medium';

        $errors = $this->errors(['src/Billing/InvoiceTotals.php']);

        self::assertSame(['sloppy.SL104'], self::identifiers($errors));
        self::assertSame(self::path('project', 'src/Billing/InvoiceTotals.php'), $errors[0]->getFilePath());
        self::assertSame(12, $errors[0]->getLine());
    }

    public function test_a_cross_file_finding_sees_the_whole_project_when_phpstan_analysed_one_file(): void
    {
        $this->failOn = 'medium';

        // QuoteTotals was not handed to PHPStan, yet the duplicate is found:
        // Sloppy indexed it. Only the analysed file is reported on.
        $errors = $this->errors(['src/Billing/InvoiceTotals.php']);

        self::assertStringContainsString('structurally identical to QuoteTotals::total()', $errors[0]->getMessage());
        self::assertCount(1, $errors);
    }

    public function test_failon_never_reports_nothing(): void
    {
        $this->failOn = 'never';

        self::assertSame([], $this->errors(['src/Importer.php']));
    }

    public function test_a_file_sloppy_does_not_cover_is_never_reported(): void
    {
        // tests/ swallows an exception too, but sloppy.php covers only src/.
        self::assertSame([], $this->errors(['tests/ImporterTest.php']));
    }

    public function test_it_reports_every_analysed_file_in_one_pass(): void
    {
        $this->failOn = 'medium';

        $errors = $this->errors(['src/Importer.php', 'src/Billing/InvoiceTotals.php', 'src/Billing/QuoteTotals.php', 'tests/ImporterTest.php']);

        self::assertSame(['sloppy.SL104', 'sloppy.SL104', 'sloppy.SL107'], self::identifiers($errors));
    }

    public function test_the_baseline_hides_what_it_accepts(): void
    {
        $this->project = 'baselined';
        $this->failOn = 'info';

        self::assertSame([], $this->errors(['src/Importer.php', 'src/Billing/InvoiceTotals.php']));
    }

    public function test_the_baseline_can_be_ignored(): void
    {
        $this->project = 'baselined';
        $this->failOn = 'info';
        $this->useBaseline = false;

        self::assertSame(['sloppy.SL104', 'sloppy.SL107'], self::identifiers($this->errors(['src/Importer.php', 'src/Billing/InvoiceTotals.php'])));
    }

    public function test_a_disabled_sloppy_reports_nothing(): void
    {
        $this->project = 'disabled';

        self::assertSame([], $this->errors(['src/Importer.php']));
    }

    public function test_a_broken_configuration_is_one_internal_error(): void
    {
        $this->project = 'broken';

        $errors = $this->errors(['src/Importer.php']);

        self::assertCount(1, $errors);
        self::assertSame('sloppy.internalError', $errors[0]->getIdentifier());
        self::assertStringContainsString('must return an array', $errors[0]->getMessage());
    }

    public function test_a_custom_rule_id_becomes_a_valid_identifier_and_a_failing_rule_is_reported(): void
    {
        $this->project = 'custom';

        $errors = $this->errors(['src/Clean.php']);

        self::assertSame(['sloppy.ACME001', 'sloppy.internalError'], self::identifiers($errors));
        self::assertSame('Every Class: A class was found.', $errors[0]->getMessage());
        self::assertSame('Sloppy rule CRASH1 failed on this file: The rule broke.', $errors[1]->getMessage());
        self::assertSame(self::path('custom', 'src/Clean.php'), $errors[1]->getFilePath());
    }

    public function test_an_ignore_comment_with_the_identifier_silences_a_finding(): void
    {
        $this->project = 'ignored';

        self::assertSame([], $this->errors(['src/Importer.php']));
    }

    /**
     * @param  list<string>  $files  Relative to the fixture project.
     * @return list<Error>
     */
    private function errors(array $files): array
    {
        $errors = $this->gatherAnalyserErrors(array_map(fn (string $file): string => self::path($this->project, $file), $files));

        usort($errors, static fn (Error $a, Error $b): int => [$a->getIdentifier(), $a->getFilePath()] <=> [$b->getIdentifier(), $b->getFilePath()]);

        return $errors;
    }

    /**
     * @param  list<Error>  $errors
     * @return list<string|null>
     */
    private static function identifiers(array $errors): array
    {
        return array_map(static fn (Error $error): ?string => $error->getIdentifier(), $errors);
    }

    private static function fixture(string $project): string
    {
        return __DIR__.'/Fixtures/'.$project;
    }

    private static function path(string $project, string $file): string
    {
        return (string) realpath(self::fixture($project).'/'.$file);
    }
}
