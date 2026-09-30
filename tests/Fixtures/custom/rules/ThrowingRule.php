<?php

declare(strict_types=1);

namespace Fixture\Rules;

use Heyosseus\Sloppy\Analysis\AnalysisContext;
use Heyosseus\Sloppy\Analysis\Category;
use Heyosseus\Sloppy\Analysis\Severity;
use Heyosseus\Sloppy\Rules\BaseRule;
use LogicException;

/**
 * Fails on every file, as a rule with a bug would.
 */
final class ThrowingRule extends BaseRule
{
    public function id(): string
    {
        return 'CRASH1';
    }

    public function name(): string
    {
        return 'Crash';
    }

    public function description(): string
    {
        return 'Throws.';
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
        throw new LogicException('The rule broke.');
    }
}
