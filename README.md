# phpstan-sloppy

**[Sloppy](https://github.com/heyosseus/sloppy)'s rules inside the PHPStan run you already have.**

Swallowed exceptions, god methods, N+1 queries, placeholder bodies an agent
left behind, copy-paste drift, tests quietly skipped to make a build pass: the
debt AI coding agents leave in PHP, reported as ordinary PHPStan errors. No new
pipeline step, no new report to read, nothing new to learn.

<p>
  <a href="https://github.com/heyosseus/phpstan-sloppy/actions/workflows/tests.yml"><img alt="tests" src="https://github.com/heyosseus/phpstan-sloppy/actions/workflows/tests.yml/badge.svg"></a>
  <a href="https://packagist.org/packages/heyosseus/phpstan-sloppy"><img alt="packagist" src="https://img.shields.io/packagist/v/heyosseus/phpstan-sloppy.svg"></a>
  <a href="https://packagist.org/packages/heyosseus/phpstan-sloppy"><img alt="downloads" src="https://img.shields.io/packagist/dt/heyosseus/phpstan-sloppy.svg"></a>
  <img alt="php" src="https://img.shields.io/packagist/dependency-v/heyosseus/phpstan-sloppy/php.svg">
  <img alt="phpstan" src="https://img.shields.io/badge/PHPStan-2.1%2B-brightgreen.svg">
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
         💡  High severity, 96% confidence. Rule SL107:
         https://github.com/heyosseus/sloppy/blob/main/docs/rules.md#php-and-general
 ------ ---------------------------------------------------------------------------------
```

## Install

```bash
composer require --dev heyosseus/phpstan-sloppy
```

With [phpstan/extension-installer](https://github.com/phpstan/extension-installer),
that's all: run `vendor/bin/phpstan` as you always do. Without it, include the
extension yourself:

```neon
includes:
    - vendor/heyosseus/phpstan-sloppy/extension.neon
```

It needs PHP 8.3+ and PHPStan 2.1+, and works on any PHP project. On Laravel 12
or 13 it adds Sloppy's ten Eloquent-aware rules, the same as the CLI does.

## Why use it

| | |
| --- | --- |
| **One run, one report** | Sloppy's findings arrive in PHPStan's table, JSON, GitHub, GitLab, Checkstyle or JUnit output, wherever PHPStan already reports. |
| **One way to suppress** | Every finding has its own identifier (`sloppy.SL107`), so `@phpstan-ignore`, `ignoreErrors` and `--generate-baseline` all work. |
| **Only what is new** | `diffBase: main` reports only what your branch introduced, including tests somebody weakened to make the build pass. |
| **In your editor** | PHPStan's editor mode is supported: an editor that uses it sees findings for the buffer you are typing in, not the file on disk. |
| **Fast when nothing changed** | Results are cached. A run where no file Sloppy reads has changed skips Sloppy altogether. |
| **A quality gate** | `minScore: 80` fails the run when the code's Sloppy score drops, not only when one finding does. |
| **Never silent** | A broken configuration, a mistyped rule ID or a revision git cannot find fails the run with the reason. It never passes quietly. |

## What it reports

Exactly what `vendor/bin/sloppy ci` would fail on, and nothing else:

- **Only the files both tools cover.** A file PHPStan analyses but Sloppy's
  `paths` or `exclude` leave out (your `tests/`, usually) is not reported.
  The whole Sloppy project is still indexed, so duplication across files is
  found even when PHPStan was handed one file.
- **At or above `fail_on`** (`high` by default), from your Sloppy
  configuration.
- **Not what `.sloppy-baseline.json` accepts.**

Each finding's tips say what to do instead, how severe it is, how sure Sloppy
is, and where the rule is documented.

## Configure

Sloppy reads its usual configuration: `config/sloppy.php`, `sloppy.php` or
`.sloppy.php` in the project root. See [Sloppy's configuration
docs](https://github.com/heyosseus/sloppy/blob/main/docs/configuration.md).

Everything else is optional, and every value is checked by PHPStan's
configuration schema:

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

        # The lowest confidence reported, 0 to 100. Default: your Sloppy
        # min_confidence.
        minConfidence: null

        # Report only these rules, or never report these. SL107 and
        # sloppy.SL107 both work; an ID that does not exist is an error.
        onlyRules: []
        excludeRules: []

        # Leave out what .sloppy-baseline.json accepts.
        useBaseline: true

        # Report only what changed since this git revision, like sloppy diff:
        # a branch, tag or commit, or auto for the pull request's base branch
        # in CI (and everything, outside one).
        diffBase: null

        # Fail when Sloppy's score for the analysed code is below this, 0-100.
        minScore: null

        # Add each rule's explanation of why the pattern costs you to the tips.
        explain: false

        # Keep results in PHPStan's tmpDir between runs.
        cache: true
```

## Recipes

### Adopt it on a codebase with history

Two ways to start without a wall of findings. Keep a baseline:

```bash
vendor/bin/phpstan --generate-baseline   # PHPStan's baseline, or
vendor/bin/sloppy baseline               # Sloppy's, respected by default
```

Or report only what each branch introduces, and never look back:

```neon
parameters:
    sloppy:
        diffBase: main
```

### Gate pull requests in CI

`diffBase: auto` compares with the pull request's base branch on GitHub
Actions and the merge request's target branch on GitLab CI, and reports
everything where there is neither. It needs the history to compare with:

```yaml
- uses: actions/checkout@v5
  with:
    fetch-depth: 0

- run: vendor/bin/phpstan analyse --error-format=github
```

```neon
parameters:
    sloppy:
        diffBase: auto
```

In diff mode two more checks come along, which a scan cannot do because they
compare revisions:

- `sloppy.SL502`: entries the branch added to `phpstan-baseline.neon` or
  `psalm-baseline.xml`.
- `sloppy.SL503`: tests the branch skipped, stripped of assertions or gave
  `assertTrue(true)` to make them pass, reported on the test even though
  Sloppy does not otherwise analyse `tests/`.

### Fail on defects, not on style

```neon
parameters:
    sloppy:
        failOn: medium
        onlyRules:
            - SL107  # swallowed exception
            - SL111  # copy-paste drift
            - SL112  # placeholder implementation
            - SL203  # possible N+1
            - SL204  # query inside a loop
```

### Keep the score up

```neon
parameters:
    sloppy:
        minScore: 80
```

The run fails with `sloppy.score` when the analysed code scores below 80,
whatever `failOn` says: set `failOn: never` to gate on the score alone.

### Teach the team (or the agent) why

```neon
parameters:
    sloppy:
        explain: true
```

Every finding then carries the rule's reasoning as well as its fix. It is a
good setting for a coding agent that reads PHPStan's output: it learns why a
pattern is a problem, not only that it is one.

## In your editor

An editor that runs PHPStan in editor mode (`--tmp-file` and `--instead-of`)
hands it the buffer you are typing in. This extension reads that buffer too,
so findings appear and disappear as you type, without saving, on the file you
have open. Editor mode arrived during PHPStan 2.1; on a release before it,
findings are for the file as saved.

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

## Identifiers

| Identifier | Means |
| --- | --- |
| `sloppy.SL101` ... `sloppy.SL503` | A finding from that rule. See [Sloppy's rules](https://github.com/heyosseus/sloppy/blob/main/docs/rules.md). |
| `sloppy.ACME001` | A finding from a custom rule; its ID keeps only letters, digits and dots, so `ACME-001` becomes `ACME001`. |
| `sloppy.score` | The analysed code scored below `minScore`. |
| `sloppy.internalError` | Sloppy could not run, or a rule broke on one file. |

Each finding also carries metadata for a custom error formatter: `rule`,
`name`, `category`, `severity`, `confidence`, `endLine` and `identity`, under
the `sloppy` key of `Error::getMetadata()`.

## When something looks wrong

```bash
vendor/bin/phpstan diagnose
```

prints what the extension read: Sloppy's version, the project root and
configuration file, the threshold and where it came from, the active and
skipped rules, the baseline, the diff base and the cache directory.

A broken Sloppy configuration is reported as one error, `sloppy.internalError`,
with the reason. It fails the run instead of passing silently, and PHPStan's own
errors are still reported. A Sloppy rule that throws on one file is reported
the same way, on that file, and the rest of the run carries on.

## Performance

Sloppy's cross-file rules read the whole configured project, so PHPStan's own
result cache cannot shorten them: one edited file can change a finding in
another. What the extension can know cheaply is whether anything Sloppy reads
changed at all: every source file, the configuration, the baseline, the rules
themselves. When nothing did, the last run's answer is reused and Sloppy does
not run. On Sloppy's own source, a repeat `phpstan analyse` goes from about
6s to about 3s, which is PHPStan's own warm run.

The cache lives in `sloppy/` inside PHPStan's `tmpDir`, holding one entry per
project; `cache: false` turns it off. Diff mode does not use it, since
what a branch introduced depends on the repository as well as the files.

## FAQ

**Does it replace the Sloppy CLI?** No. PHPStan is one way in. The CLI also
triages a first scan, reviews a branch by risk, fixes the mechanical findings
with Rector and Pint, and keeps a coding agent honest through Claude Code
hooks. The two agree on every finding, because this extension decides only
which findings PHPStan sees, never what is found.

**Is it an AI detector?** No. Sloppy looks for patterns that turn into
maintenance cost, whoever wrote them. A 200-line action that swallows a
`Throwable` is a problem whether a person or an agent wrote it.

**Does it need a network, a model or an API key?** No. Everything is local and
deterministic: the same code always produces the same findings.

**Why is a finding reported on a test file PHPStan did not analyse?** It is
`SL503`, a weakened test, which exists only in diff mode. It is the point of
comparing with the base revision.

## Everything else Sloppy does

This extension is one way in. [Sloppy](https://github.com/heyosseus/sloppy) is
also a CLI that triages a long first scan, `sloppy diff main` for what a branch
introduced, a Rector and Pint fix pass, Claude Code hooks that make an agent
clean up after itself, an MCP server, Pest expectations and CI annotations.

## License

MIT. See [LICENSE.md](LICENSE.md).
