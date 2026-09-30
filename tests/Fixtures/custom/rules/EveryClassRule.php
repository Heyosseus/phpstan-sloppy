<?php

declare(strict_types=1);

namespace Fixture\Rules;

use Heyosseus\Sloppy\Analysis\AnalysisContext;
use Heyosseus\Sloppy\Analysis\Category;
use Heyosseus\Sloppy\Analysis\Severity;
use Heyosseus\Sloppy\Rules\BaseRule;

/**
 * Reports every class, under an ID PHPStan would refuse as an identifier.
 */
final class EveryClassRule extends BaseRule
{
    public function id(): string
    {
        return 'ACME-001';
    }

    public function name(): string
    {
        return 'Every Class';
    }

    public function description(): string
    {
        return 'Reports every class.';
    }

    public function category(): Category
    {
        return Category::Architecture;
    }

    protected function defaultSeverity(): Severity
    {
        return Severity::Low;
    }

    public function analyze(AnalysisContext $context): iterable
    {
        foreach ($context->classLikes() as $class) {
            yield $this->report(
                context: $context,
                at: $class,
                message: 'A class was found.',
                suggestion: 'Keep it.',
                confidence: 90,
                fingerprint: (string) $class->name,
            );
        }
    }
}
