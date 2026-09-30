<?php

declare(strict_types=1);

namespace Heyosseus\PhpstanSloppy;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Node\FileNode;

/**
 * Records which files PHPStan analysed.
 *
 * The file is the key PHPStan stores collected data under, so the value only
 * has to be something other than null. Collected data survives the result
 * cache, which is what lets {@see SloppyRule} see every analysed file even on a
 * run that re-analysed one.
 *
 * @implements Collector<FileNode, true>
 */
final class SloppyFileCollector implements Collector
{
    public function getNodeType(): string
    {
        return FileNode::class;
    }

    public function processNode(Node $node, Scope $scope): true
    {
        return true;
    }
}
