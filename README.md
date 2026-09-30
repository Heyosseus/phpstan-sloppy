# phpstan-sloppy

**[Sloppy](https://github.com/heyosseus/sloppy)'s rules inside the PHPStan run you already have.**

Swallowed exceptions, god methods, N+1 queries, placeholder bodies an agent
left behind, copy-paste drift: the debt AI coding agents leave in PHP, reported
as ordinary PHPStan errors. No new pipeline step, no new report to read.

<p>
  <a href="https://github.com/heyosseus/phpstan-sloppy/actions/workflows/tests.yml"><img alt="tests" src="https://github.com/heyosseus/phpstan-sloppy/actions/workflows/tests.yml/badge.svg"></a>
  <a href="https://packagist.org/packages/heyosseus/phpstan-sloppy"><img alt="packagist" src="https://img.shields.io/packagist/v/heyosseus/phpstan-sloppy.svg"></a>
  <a href="LICENSE.md"><img alt="license" src="https://img.shields.io/packagist/l/heyosseus/phpstan-sloppy.svg"></a>
</p>

```text
 ------ ---------------------------------------------------------------------------------
  Line   src/Importer.php
 ------ ---------------------------------------------------------------------------------
  :13    Swallowed Exception: Importer::run() catches Throwable and does nothing at all.
         🪪  sloppy.SL107
         💡  Do at least one of: log or report the exception, rethrow it wrapped in a
         domain-specific type, or return a result the caller can distinguish from
         success. If the failure genuinely is expected and unremarkable, say so in a
         comment so the next reader knows it was a decision.
 ------ ---------------------------------------------------------------------------------
```

## Install

```bash
composer require --dev heyosseus/phpstan-sloppy
```

With [phpstan/extension-installer](https://github.com/phpstan/extension-installer),
that's all. Without it, include the extension yourself:

```neon
includes:
    - vendor/heyosseus/phpstan-sloppy/extension.neon
```

It needs PHP 8.3+ and PHPStan 2.1+, and works on any PHP project. On Laravel 12
or 13 it adds Sloppy's Eloquent-aware rules, the same as the CLI does.

## What it reports

Exactly what `vendor/bin/sloppy ci` would fail on, and nothing else:

- **Only the files both tools cover.** A file PHPStan analyses but Sloppy's
  `paths` or `exclude` leave out (your `tests/`, usually) is not reported.
  The whole Sloppy project is still indexed, so duplication across files is
  found even when PHPStan was handed one file.
- **At or above `fail_on`** (`high` by default), from your Sloppy
  configuration.
- **Not what `.sloppy-baseline.json` accepts.**

Each rule has its own identifier, `sloppy.<rule>`: `sloppy.SL107`,
`sloppy.SL203`. A custom rule's ID keeps only its letters, digits and dots,
so `ACME-001` becomes `sloppy.ACME001`. The suggestion is the tip.

`SL502` (baseline growth) and `SL503` (weakened test) compare your change with
its base revision, which PHPStan does not have. They stay in `sloppy diff` and
`sloppy ci`.

## Configure

Sloppy reads its usual configuration: `config/sloppy.php`, `sloppy.php` or
`.sloppy.php` in the project root. See [Sloppy's configuration
docs](https://github.com/heyosseus/sloppy/blob/main/docs/configuration.md).

Everything else is optional:

```neon
parameters:
    sloppy:
        # Where composer.json and the Sloppy configuration are. Default: the
        # directory PHPStan runs in. Relative paths are read from there too.
        projectRoot: null
        # An explicit Sloppy configuration file, relative to projectRoot.
        config: null
        # The lowest severity reported: critical, high, medium, low, info or
        # never. Default: your Sloppy fail_on.
        failOn: null
        # Leave out what .sloppy-baseline.json accepts.
        useBaseline: true
```

## Suppressing findings

Every PHPStan mechanism works, because these are PHPStan errors:

```php
} catch (Throwable $e) { // @phpstan-ignore sloppy.SL107 (Best effort: a missing cache is rebuilt.)
```

```neon
parameters:
    ignoreErrors:
        - identifier: sloppy.SL109
          path: src/Legacy/*
```

`vendor/bin/phpstan --generate-baseline` records Sloppy's findings with the rest.
If you already keep a Sloppy baseline, it is respected, so installing the
extension never floods an existing project.

## When Sloppy cannot run

A broken Sloppy configuration is reported as one error, `sloppy.internalError`,
with the reason. It fails the run instead of passing silently, and PHPStan's own
errors are still reported. A Sloppy rule that throws on one file is reported
the same way, on that file, and the rest of the run carries on.

## Good to know

- **It adds one Sloppy run to every PHPStan run.** Sloppy's cross-file rules
  read the whole configured project, so PHPStan's result cache does not
  shorten this part: expect about what `vendor/bin/sloppy` takes on its own.
- **It reads files as saved.** An editor that runs PHPStan on an unsaved
  buffer gets Sloppy's findings for the file on disk.

## Everything else Sloppy does

This extension is one way in. [Sloppy](https://github.com/heyosseus/sloppy) is
also a CLI that triages a long first scan, `sloppy diff main` for what a branch
introduced, a Rector and Pint fix pass, Claude Code hooks that make an agent
clean up after itself, an MCP server, Pest expectations and CI annotations.

## License

MIT. See [LICENSE.md](LICENSE.md).
