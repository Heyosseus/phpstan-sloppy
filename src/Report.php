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
    public function __construct(
        public string $identifier,
        public string $message,
        public ?string $file = null,
        public ?int $line = null,
        public ?string $tip = null,
    ) {}
}
