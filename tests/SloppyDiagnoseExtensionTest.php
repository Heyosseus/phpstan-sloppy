<?php

declare(strict_types=1);

namespace Heyosseus\PhpstanSloppy\Tests;

use Heyosseus\PhpstanSloppy\Options;
use Heyosseus\PhpstanSloppy\SloppyDiagnoseExtension;
use PHPStan\Command\Output;
use PHPUnit\Framework\TestCase;

final class SloppyDiagnoseExtensionTest extends TestCase
{
    public function test_it_says_where_every_setting_came_from(): void
    {
        $lines = $this->diagnose(new Options(
            projectRoot: __DIR__.'/Fixtures/baselined',
            workingDirectory: __DIR__,
            excludeRules: ['SL104'],
            diffBase: 'main',
            minScore: 80,
            cacheDirectory: '/tmp/phpstan/sloppy',
        ));

        self::assertContains('Sloppy reports: high and above (the project\'s fail_on)', $lines);
        self::assertContains('Sloppy diff base: main', $lines);
        self::assertContains('Sloppy minimum score: 80/100', $lines);
        self::assertContains('Sloppy result cache: /tmp/phpstan/sloppy', $lines);
        self::assertMatchesRegularExpression('/^Sloppy baseline: .+\.sloppy-baseline\.json, 3 entries$/', self::line($lines, 'Sloppy baseline'));
        self::assertStringNotContainsString('SL104', self::line($lines, 'Sloppy rules'));
    }

    public function test_it_says_when_nothing_will_be_reported(): void
    {
        $never = $this->diagnose(new Options(__DIR__.'/Fixtures/project', __DIR__, failOn: 'never', useBaseline: false));
        $disabled = $this->diagnose(new Options(__DIR__.'/Fixtures/disabled', __DIR__));

        self::assertContains('Sloppy reports: nothing (failOn: never)', $never);
        self::assertContains('Sloppy baseline: ignored (useBaseline: false)', $never);
        self::assertContains('Sloppy status: disabled by the configuration; nothing is reported', $disabled);
    }

    public function test_a_configuration_that_cannot_be_read_is_said_so_without_failing(): void
    {
        $lines = $this->diagnose(new Options(__DIR__.'/Fixtures/broken', __DIR__));

        self::assertStringContainsString('must return an array', self::line($lines, 'Sloppy cannot run'));
    }

    /**
     * @return list<string> The lines written, without formatting tags.
     */
    private function diagnose(Options $options): array
    {
        $lines = [];
        $output = self::createStub(Output::class);
        $output->method('writeLineFormatted')->willReturnCallback(static function (string $line) use (&$lines): void {
            $lines[] = (string) preg_replace('/<\/?[a-z]+>/', '', $line);
        });

        (new SloppyDiagnoseExtension($options))->print($output);

        return $lines;
    }

    /**
     * @param  list<string>  $lines
     */
    private static function line(array $lines, string $label): string
    {
        foreach ($lines as $line) {
            if (str_starts_with($line, $label.':')) {
                return $line;
            }
        }

        self::fail(sprintf('No [%s] line in: %s', $label, implode("\n", $lines)));
    }
}
