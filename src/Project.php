<?php

declare(strict_types=1);

namespace Heyosseus\PhpstanSloppy;

use Heyosseus\Sloppy\Configuration\ConfigurationLoader;
use Heyosseus\Sloppy\Sloppy;
use RuntimeException;

/**
 * A project's Sloppy, configured exactly as `bin/sloppy` would configure it,
 * with a note of where that configuration came from.
 */
final readonly class Project
{
    public function __construct(
        public string $root,
        public Sloppy $sloppy,
        public string $configSource,
    ) {}

    public static function load(Options $options): self
    {
        $root = $options->projectRoot();

        if (! is_dir($root)) {
            throw new RuntimeException(sprintf('Project root [%s] does not exist.', $root));
        }

        $loader = new ConfigurationLoader($root);
        $sloppy = new Sloppy($loader->load($options->configPath()));

        return new self(strtr($root, '\\', '/'), $sloppy, $loader->source());
    }
}
