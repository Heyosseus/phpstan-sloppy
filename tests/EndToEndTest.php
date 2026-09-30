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

    public function test_an_editor_analysing_an_unsaved_buffer_gets_findings_for_the_buffer(): void
    {
        // The buffer rethrows what the file on disk swallows.
        mkdir($this->tmp, 0777, true);
        $buffer = $this->tmp.'/buffer.php';
        file_put_contents($buffer, str_replace(
            "} catch (\\Throwable \$exception) {\n        }",
            "} catch (\\Throwable \$exception) {\n            throw new \\LogicException('Import failed.', 0, \$exception);\n        }",
            (string) file_get_contents(__DIR__.'/Fixtures/project/src/Importer.php'),
        ));

        [$exit, $output, $errors] = $this->phpstan('phpstan.neon', [
            '--tmp-file='.$buffer,
            '--instead-of=tests/Fixtures/project/src/Importer.php',
            'tests/Fixtures/project/src/Importer.php',
        ]);

        if (str_contains($errors, '"--tmp-file" option does not exist')) {
            self::markTestSkipped('This PHPStan predates editor mode.');
        }

        self::assertSame(0, $exit, $output.$errors);
        self::assertSame([], self::errors($output));
    }

    public function test_diagnose_says_what_the_extension_read(): void
    {
        [$exit, $output, $errors] = $this->phpstan('phpstan.neon', [], 'diagnose');
        $output = (string) preg_replace('/\e\[[0-9;]*m/', '', $output.$errors);

        self::assertSame(0, $exit, $output);
        self::assertMatchesRegularExpression('/Sloppy: v?\d+\.\d+\.\d+, through heyosseus\/phpstan-sloppy/', $output);
        self::assertStringContainsString('Sloppy configuration: sloppy.php', $output);
        self::assertStringContainsString('Sloppy reports: medium and above (the failOn parameter)', $output);
        self::assertMatchesRegularExpression('/Sloppy rules: \d+ active: SL101 /', $output);
    }

    /**
     * @param  list<string>  $arguments
     * @return array{int, string, string} The exit code, standard output and standard error.
     */
    private function phpstan(string $config, array $arguments = [], string $subcommand = 'analyse'): array
    {
        $command = [
            PHP_BINARY,
            'vendor/phpstan/phpstan/phpstan',
            $subcommand,
            '--configuration=tests/e2e/'.$config,
            ...($subcommand === 'analyse' ? ['--error-format=json', '--no-progress'] : []),
            '--memory-limit=512M',
            ...$arguments,
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
