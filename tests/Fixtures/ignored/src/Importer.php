<?php

declare(strict_types=1);

namespace App;

final class Importer
{
    public function run(): void
    {
        try {
            $this->load();
        } catch (\Throwable $exception) { // @phpstan-ignore sloppy.SL107 (Loading is best effort here.)
        }
    }

    private function load(): void
    {
        throw new \RuntimeException('Nothing to load.');
    }
}
