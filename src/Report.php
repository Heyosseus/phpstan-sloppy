<?php

declare(strict_types=1);

namespace Heyosseus\PhpstanSloppy;

/**
 * One thing to tell PHPStan, already in PHPStan's terms.
 *
 * A null file or line is a problem with the run rather than with any one
 * place in the code, such as a configuration Sloppy could not read.
 */
final readonly class Report
{
    /**
     * @param  list<string>  $tips  Shown under the message, in order.
     * @param  array<string, mixed>  $metadata  What a custom error formatter can read: the finding's rule, severity, confidence and so on.
     */
    public function __construct(
        public string $identifier,
        public string $message,
        public ?string $file = null,
        public ?int $line = null,
        public array $tips = [],
        public array $metadata = [],
    ) {}
}
