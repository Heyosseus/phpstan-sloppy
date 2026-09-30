<?php

declare(strict_types=1);

namespace Heyosseus\PhpstanSloppy;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Sloppy's findings as PHPStan errors.
 *
 * Sloppy's rules need the whole project at once -- duplication, dead private
 * methods and copy-paste drift are all questions about more than one file --
 * so this runs once, after PHPStan has analysed everything, rather than per
 * file.
 *
 * @implements Rule<CollectedDataNode>
 */
final readonly class SloppyRule implements Rule
{
    public function __construct(private SloppyRunner $runner) {}

    public function getNodeType(): string
    {
        return CollectedDataNode::class;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $files = array_keys($node->get(SloppyFileCollector::class));

        $errors = [];

        foreach ($this->runner->run($files) as $report) {
            $error = RuleErrorBuilder::message($report->message)->identifier($report->identifier);

            if ($report->file !== null) {
                $error->file($report->file);
            }

            if ($report->line !== null) {
                $error->line($report->line);
            }

            if ($report->tip !== null) {
                $error->tip($report->tip);
            }

            $errors[] = $error->build();
        }

        return $errors;
    }
}
