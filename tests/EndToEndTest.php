<?php

declare(strict_types=1);

namespace Heyosseus\PhpstanSloppy\Tests;

use PHPUnit\Framework\TestCase;

/**
 * The real `phpstan analyse`, in a process of its own, with extension.neon
 * included the way extension-installer includes it.
 *
 * Everything a unit test stands in for is real here: PHPStan's own copy of
 * php-parser beside Sloppy's, the parameter schema, parallel workers and the
 * result cache.
 */
final class EndToEndTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        $this->tmp = sys_get_temp_dir().'/phpstan-sloppy-e2e-'.bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        self::remove($this->tmp);
    }

    public function test_phpstan_reports_sloppys_findings_where_they_are(): void
    {
        [$exit, $output, $errors] = $this->phpstan('phpstan.neon');

        self::assertSame(1, $exit, $errors);
        self::assertSame([
            'src/Billing/InvoiceTotals.php:12 sloppy.SL104',
            'src/Billing/QuoteTotals.php:12 sloppy.SL104',
            'src/Importer.php:13 sloppy.SL107',
        ], self::errors($output));
    }

    public function test_a_second_run_from_the_result_cache_reports_the_same(): void
    {
        [, $first] = $this->phpstan('phpstan.neon');
        [$exit, $second, $errors] = $this->phpstan('phpstan.neon');

        self::assertSame(1, $exit, $errors);
        self::assertSame(self::errors($first), self::errors($second));
    }

    public function test_an_invalid_parameter_is_refused_by_the_schema(): void
    {
        [$exit, $stdout, $stderr] = $this->phpstan('invalid-fail-on.neon');
        $output = $stdout.$stderr;

        self::assertNotSame(0, $exit);
        self::assertStringContainsString('sloppy', $output);
        self::assertStringContainsString('failOn', $output);
    }

    /**
     * @return array{int, string, string} The exit code, standard output and standard error.
     */
    private function phpstan(string $config): array
    {
        $command = [
            PHP_BINARY,
            'vendor/phpstan/phpstan/phpstan',
            'analyse',
            '--configuration=tests/e2e/'.$config,
            '--error-format=json',
            '--no-progress',
            '--memory-limit=512M',
        ];

        // Standard error goes to a file, so a child that writes a lot of it
        // can never block on a pipe nobody is reading yet.
        $stderrFile = $this->tmp.'-stderr.txt';

        $pipes = [];
        $process = proc_open(
            $command,
            [1 => ['pipe', 'w'], 2 => ['file', $stderrFile, 'w']],
            $pipes,
            dirname(__DIR__),
            [...getenv(), 'PHPSTAN_SLOPPY_TMP' => $this->tmp],
        );

        self::assertIsResource($process);

        $stdout = $pipes[1];
        self::assertIsResource($stdout);

        $output = (string) stream_get_contents($stdout);
        fclose($stdout);
        $exit = proc_close($process);

        $errors = (string) file_get_contents($stderrFile);
        @unlink($stderrFile);

        return [$exit, $output, $errors];
    }

    /**
     * `path:line identifier` for every error, relative to the fixture project.
     *
     * @return list<string>
     */
    private static function errors(string $output): array
    {
        /** @var array{files: array<string, array{messages: list<array{line: int, identifier?: string}>}>} $report */
        $report = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        $root = str_replace('\\', '/', (string) realpath(__DIR__.'/Fixtures/project')).'/';
        $errors = [];

        foreach ($report['files'] as $file => $result) {
            foreach ($result['messages'] as $message) {
                $errors[] = sprintf(
                    '%s:%d %s',
                    str_replace($root, '', str_replace('\\', '/', $file)),
                    $message['line'],
                    $message['identifier'] ?? '(none)',
                );
            }
        }

        sort($errors);

        return $errors;
    }

    private static function remove(string $path): void
    {
        if (is_file($path) || is_link($path)) {
            @unlink($path);

            return;
        }

        if (! is_dir($path)) {
            return;
        }

        foreach ((array) scandir($path) as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                self::remove($path.'/'.$entry);
            }
        }

        @rmdir($path);
    }
}
