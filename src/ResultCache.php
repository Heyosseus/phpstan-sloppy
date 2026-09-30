<?php

declare(strict_types=1);

namespace Heyosseus\PhpstanSloppy;

/**
 * What the last run reported, kept until anything it depended on changes.
 *
 * Sloppy's cross-file rules read the whole project, so PHPStan's own result
 * cache cannot shorten them: one edited file can change a finding in another.
 * What *can* be known cheaply is whether anything changed at all, and on the
 * runs where nothing did -- a second `phpstan analyse`, an edit to a test
 * Sloppy does not cover, a CI retry -- the answer is already on disk.
 *
 * The key is a hash of everything the result depends on. A cache that cannot
 * be read or written is no cache, never an error: it only ever saves time.
 */
final readonly class ResultCache
{
    /**
     * Bumped whenever the shape of what is stored changes, so an upgrade never
     * reads an older extension's entries.
     */
    private const int SCHEMA = 2;

    public function __construct(private string $directory) {}

    /**
     * @param  array<string, mixed>  $inputs
     */
    public static function key(array $inputs): string
    {
        return hash('xxh128', serialize([self::SCHEMA, $inputs]));
    }

    /**
     * @return list<Report>|null Null when there is nothing stored for this key.
     */
    public function get(string $slot, string $key): ?array
    {
        $contents = @file_get_contents($this->path($slot));

        if ($contents === false) {
            return null;
        }

        // An entry that does not unserialise -- another PHP version wrote it,
        // somebody edited it -- is a miss, and the run writes a good one.
        $entry = @unserialize($contents, ['allowed_classes' => [Report::class]]);

        if (! is_array($entry) || ($entry['key'] ?? null) !== $key || ! is_array($entry['reports'] ?? null)) {
            return null;
        }

        $reports = [];

        foreach ($entry['reports'] as $report) {
            if (! $report instanceof Report) {
                return null;
            }

            $reports[] = $report;
        }

        return $reports;
    }

    /**
     * One entry per slot: a project's next run either matches its last one or
     * replaces it, so the directory never grows.
     *
     * @param  list<Report>  $reports
     */
    public function put(string $slot, string $key, array $reports): void
    {
        if (! is_dir($this->directory) && ! @mkdir($this->directory, 0777, true) && ! is_dir($this->directory)) {
            return;
        }

        $path = $this->path($slot);
        $temporary = $path.'.'.bin2hex(random_bytes(4)).'.tmp';

        // Written aside and renamed, so a run that reads while another writes
        // sees the old entry or the new one, never half of one.
        if (@file_put_contents($temporary, serialize(['key' => $key, 'reports' => $reports])) === false) {
            return;
        }

        if (! @rename($temporary, $path)) {
            @unlink($temporary);
        }
    }

    public function directory(): string
    {
        return $this->directory;
    }

    private function path(string $slot): string
    {
        return $this->directory.'/results-'.hash('xxh128', $slot).'.cache';
    }
}
