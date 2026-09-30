<?php

declare(strict_types=1);

namespace Heyosseus\PhpstanSloppy\Tests;

use RuntimeException;

/**
 * A fixture project copied somewhere a test may change it: edit a file,
 * commit to git, fill a cache.
 */
final readonly class TemporaryProject
{
    private function __construct(public string $root) {}

    public static function copy(string $fixture): self
    {
        $root = str_replace('\\', '/', sys_get_temp_dir()).'/phpstan-sloppy-project-'.bin2hex(random_bytes(6));
        self::copyDirectory(__DIR__.'/Fixtures/'.$fixture, $root);

        return new self((string) realpath($root));
    }

    public function path(string $relative): string
    {
        return $this->root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }

    public function write(string $relative, string $contents): void
    {
        $path = $this->path($relative);

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, $contents);
    }

    /**
     * @param  list<string>  $args
     */
    public function git(array $args): string
    {
        $pipes = [];
        $process = proc_open(
            ['git', '-c', 'user.name=Test', '-c', 'user.email=test@example.com', '-c', 'commit.gpgsign=false', '-c', 'init.defaultBranch=main', ...$args],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->root,
        );

        if (! is_resource($process)) {
            throw new RuntimeException('git could not start.');
        }

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        if (proc_close($process) !== 0) {
            throw new RuntimeException(sprintf('git %s failed: %s', implode(' ', $args), $stderr));
        }

        return $stdout;
    }

    public function commitAll(string $message): void
    {
        $this->git(['add', '--all']);
        $this->git(['commit', '--quiet', '--message', $message]);
    }

    public function remove(): void
    {
        self::removePath($this->root);
    }

    public static function gitIsAvailable(): bool
    {
        $output = [];
        $exit = 1;
        @exec('git --version', $output, $exit);

        return $exit === 0;
    }

    private static function copyDirectory(string $from, string $to): void
    {
        mkdir($to, 0777, true);

        foreach ((array) scandir($from) as $entry) {
            if ($entry === '.' || $entry === '..' || ! is_string($entry)) {
                continue;
            }

            is_dir($from.'/'.$entry)
                ? self::copyDirectory($from.'/'.$entry, $to.'/'.$entry)
                : copy($from.'/'.$entry, $to.'/'.$entry);
        }
    }

    private static function removePath(string $path): void
    {
        if (is_file($path) || is_link($path)) {
            @chmod($path, 0777);
            @unlink($path);

            return;
        }

        if (! is_dir($path)) {
            return;
        }

        foreach ((array) scandir($path) as $entry) {
            if ($entry !== '.' && $entry !== '..' && is_string($entry)) {
                self::removePath($path.'/'.$entry);
            }
        }

        @rmdir($path);
    }
}
