<?php

declare(strict_types=1);

namespace Heyosseus\PhpstanSloppy;

use Heyosseus\Sloppy\Ci\BaseRevision;
use Heyosseus\Sloppy\Ci\CiEnvironment;
use Heyosseus\Sloppy\Git\Git;
use RuntimeException;

/**
 * The revision the `diffBase` parameter names.
 *
 * `auto` is what `sloppy ci` does: the pull request's base branch where the
 * CI says there is one, and the whole project everywhere else. An explicit
 * revision that cannot be found is a mistake worth failing on, never a quiet
 * fall back to reporting everything.
 */
final class DiffBase
{
    /**
     * @return string|null The revision, or null to report on everything.
     *
     * @throws RuntimeException When an explicit revision cannot be used.
     */
    public static function resolve(?string $requested, Git $git, string $basePath): ?string
    {
        if ($requested === null) {
            return null;
        }

        $auto = mb_strtolower($requested) === Options::AUTO;

        if (! $git->isAvailable() || ! $git->isRepository()) {
            if ($auto) {
                return null;
            }

            throw new RuntimeException(sprintf('diffBase is [%s], but [%s] is not a git checkout.', $requested, $basePath));
        }

        $environment = CiEnvironment::fromGlobals();
        $base = BaseRevision::resolve($git, $auto ? null : $requested, $environment);

        if ($base === null && ! $auto) {
            throw new RuntimeException(sprintf(
                'diffBase [%s] is not a revision here; tried %s. In CI, fetch enough history for it (actions/checkout: fetch-depth: 0).',
                $requested,
                implode(', ', BaseRevision::candidates($requested, $environment)),
            ));
        }

        return $base;
    }
}
