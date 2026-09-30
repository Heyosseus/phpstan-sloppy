<?php

declare(strict_types=1);

namespace App\Tests;

final class ImporterTest
{
    public function run(): void
    {
        try {
            $this->load();
        } catch (\Throwable $exception) {
        }
    }

    private function load(): void
    {
        throw new \RuntimeException('Nothing to load.');
    }
}
