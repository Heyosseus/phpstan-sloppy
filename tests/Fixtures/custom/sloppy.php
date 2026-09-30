<?php

require_once __DIR__.'/rules/EveryClassRule.php';
require_once __DIR__.'/rules/ThrowingRule.php';

return [
    'paths' => ['src'],
    'fail_on' => 'low',
    'custom_rules' => [
        Fixture\Rules\EveryClassRule::class,
        Fixture\Rules\ThrowingRule::class,
    ],
];
