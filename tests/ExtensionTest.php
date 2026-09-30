<?php

declare(strict_types=1);

namespace Heyosseus\PhpstanSloppy\Tests;

use Heyosseus\PhpstanSloppy\SloppyFileCollector;
use Heyosseus\PhpstanSloppy\SloppyRule;
use PHPStan\Analyser\Error;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * The rule as extension.neon builds it, so the service wiring and the
 * parameter schema are tested, not just the classes.
 *
 * @extends RuleTestCase<SloppyRule>
 */
final class ExtensionTest extends RuleTestCase
{
    public static function getAdditionalConfigFiles(): array
    {
        return [
            __DIR__.'/../extension.neon',
            self::configured(),
        ];
    }

    /**
     * The parameters, with the fixture project as an absolute path: in
     * PHPStan's test container the working directory is inside PHPStan's own
     * phar. The file name is fixed so the container, which PHPStan caches per
     * set of configuration files, is built once.
     */
    private static function configured(): string
    {
        $path = sys_get_temp_dir().'/phpstan-sloppy-extension-test.neon';
        $root = str_replace('\\', '/', (string) realpath(__DIR__.'/Fixtures/project'));

        file_put_contents($path, <<<NEON
            parameters:
              sloppy:
                projectRoot: '{$root}'
                failOn: medium
                useBaseline: false

            NEON);

        return $path;
    }

    protected function getRule(): Rule
    {
        return self::getContainer()->getByType(SloppyRule::class);
    }

    protected function getCollectors(): array
    {
        return [self::getContainer()->getByType(SloppyFileCollector::class)];
    }

    public function test_the_parameters_reach_the_rule(): void
    {
        // The parameters point at the fixture project, lower the threshold to
        // medium and turn the baseline off.
        $errors = $this->gatherAnalyserErrors([
            (string) realpath(__DIR__.'/Fixtures/project/src/Billing/InvoiceTotals.php'),
            (string) realpath(__DIR__.'/Fixtures/project/src/Importer.php'),
        ]);

        $identifiers = array_map(static fn (Error $error): ?string => $error->getIdentifier(), $errors);
        sort($identifiers);

        self::assertSame(['sloppy.SL104', 'sloppy.SL107'], $identifiers);
    }
}
