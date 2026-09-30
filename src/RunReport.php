<?php

declare(strict_types=1);

namespace Heyosseus\PhpstanSloppy;

use Heyosseus\Sloppy\Scoring\Score;
use Throwable;

/**
 * What PHPStan is told about the run itself rather than about a finding: it
 * could not start, a rule broke on a file, or the code scored too low.
 */
final class RunReport
{
    public const string INTERNAL_ERROR = 'sloppy.internalError';

    public const string SCORE = 'sloppy.score';

    public static function couldNotRun(Throwable $exception): Report
    {
        return new Report(
            identifier: self::INTERNAL_ERROR,
            message: 'Sloppy could not run: '.$exception->getMessage(),
            tips: ['Check the Sloppy configuration, or the sloppy parameters in your PHPStan configuration. `vendor/bin/phpstan diagnose` shows what the extension read.'],
        );
    }

    /**
     * A rule that threw on a file.
     *
     * Sloppy keeps going when one rule fails on one file, and records the
     * failure as `<rule> in <path>`. Parse failures are recorded too, under
     * the bare path, and are left out: PHPStan reports those itself.
     *
     * @param  array<string, string>  $errors  Sloppy's errors, keyed by where they happened.
     * @param  list<string>  $analysed  The relative paths Sloppy ran its rules on.
     * @return list<Report>
     */
    public static function ruleFailures(array $errors, array $analysed, string $basePath, AnalysedPaths $paths): array
    {
        $reports = [];

        foreach ($errors as $where => $message) {
            foreach ($analysed as $relative) {
                $suffix = ' in '.$relative;

                if (! str_ends_with($where, $suffix)) {
                    continue;
                }

                $reports[] = new Report(
                    identifier: self::INTERNAL_ERROR,
                    message: sprintf('Sloppy rule %s failed on this file: %s', substr($where, 0, -strlen($suffix)), $message),
                    file: $paths->spell($basePath, $relative),
                    tips: ['This is a bug in the rule; please report it at https://github.com/heyosseus/sloppy/issues, or to the rule\'s author if it is a custom one.'],
                );

                break;
            }
        }

        return $reports;
    }

    /**
     * @return list<Report> One report when the score is below the minimum, none otherwise.
     */
    public static function scoreBelow(?int $minimum, Score $score): array
    {
        if ($minimum === null || $score->value >= $minimum) {
            return [];
        }

        return [new Report(
            identifier: self::SCORE,
            message: sprintf('Sloppy scores the analysed code %d/100 (%s), below the minimum of %d.', $score->value, $score->label(), $minimum),
            tips: ['Fix the findings reported here, or run `vendor/bin/sloppy` to see what costs the most points. `--explain-risk` shows the arithmetic.'],
            metadata: ['sloppy' => ['score' => $score->value, 'minimum' => $minimum]],
        )];
    }
}
