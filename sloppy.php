<?php

declare(strict_types=1);

// Sloppy over this package's own source, run by its PHPStan configuration
// through the extension itself.
return [
    'paths' => ['src'],
    'fail_on' => 'low',
];
