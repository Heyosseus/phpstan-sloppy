<?php

declare(strict_types=1);

namespace Heyosseus\PhpstanSloppy;

use Heyosseus\Sloppy\Analysis\Finding;

/**
 * How a Sloppy finding reads as a PHPStan error.
 *
 * The message is Sloppy's own, so a PHPStan baseline written today still
 * matches tomorrow. Everything that helps somebody act on it -- what to do,
 * how sure Sloppy is, where the rule is documented -- goes in the tips, which
 * baselines and ignore rules never look at.
 */
final class FindingReport
{
    private const string RULES_DOCS = 'https://github.com/heyosseus/sloppy/blob/main/docs/rules.md';

    /**
     * The section of Sloppy's rule reference for each family of shipped rules.
     */
    private const array SECTIONS = [
        '1' => 'php-and-general',
        '2' => 'laravel',
        '3' => 'architecture',
        '5' => 'suppression',
    ];

    public static function from(Finding $finding, string $file, bool $explain): Report
    {
        $tips = [];

        if (trim($finding->suggestion) !== '') {
            $tips[] = trim($finding->suggestion);
        }

        if ($explain && trim($finding->explanation) !== '') {
            $tips[] = 'Why it matters: '.trim($finding->explanation);
        }

        $tips[] = self::summary($finding);

        return new Report(
            identifier: self::identifier($finding->ruleId),
            message: sprintf('%s: %s', $finding->ruleName, $finding->message),
            file: $file,
            line: $finding->location->line,
            tips: $tips,
            metadata: ['sloppy' => [
                'rule' => $finding->ruleId,
                'name' => $finding->ruleName,
                'category' => $finding->category->value,
                'severity' => $finding->severity->value,
                'confidence' => $finding->confidence,
                'endLine' => $finding->location->endLine,
                'identity' => $finding->identity(),
            ]],
        );
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
     * Where a shipped rule is documented; null for a custom one.
     */
    public static function documentation(string $ruleId): ?string
    {
        if (preg_match('/^SL(\d)\d\d$/', $ruleId, $matches) !== 1 || ! isset(self::SECTIONS[$matches[1]])) {
            return null;
        }

        return self::RULES_DOCS.'#'.self::SECTIONS[$matches[1]];
    }

    /**
     * `High severity, 92% confidence. Docs: https://...`
     */
    private static function summary(Finding $finding): string
    {
        $summary = sprintf('%s severity, %d%% confidence.', ucfirst($finding->severity->value), $finding->confidence);
        $documentation = self::documentation($finding->ruleId);

        return $documentation === null ? $summary : $summary.' Rule '.$finding->ruleId.': '.$documentation;
    }
}
