# Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.1.0] — 2026-09-30

### Added

- `diffBase` reports only what a branch introduced since a revision, as
  `sloppy diff` does, and `auto` finds the pull request's base branch in CI.
  Diff mode brings `SL502` (baseline growth) and `SL503` (weakened test) into
  PHPStan, reported on the baseline or test they are about.
- A result cache in PHPStan's `tmpDir`: a run in which nothing Sloppy reads
  has changed reuses the last answer instead of running Sloppy. On for
  everyone; `cache: false` turns it off.
- PHPStan's editor mode: with `--tmp-file` and `--instead-of`, Sloppy reads
  the unsaved buffer and reports on the file the editor has open.
- `onlyRules` and `excludeRules`, by `SL107` or `sloppy.SL107`. A rule ID that
  does not exist is an error that suggests the closest one.
- `minConfidence`, overriding the project's `min_confidence`.
- `minScore`, a quality gate reported as `sloppy.score` when the analysed code
  scores below it.
- `explain` adds each rule's reasoning to its tips.
- Each finding's tips now include its severity, its confidence and a link to
  the rule's documentation, and each finding carries its rule, severity,
  confidence and category as error metadata for custom error formatters.
- `vendor/bin/phpstan diagnose` prints what the extension read: versions,
  project root, configuration file, threshold, active rules, baseline, diff
  base and cache.

### Changed

- The extension's own classes were reorganised around an `Options` service.
  None of them is meant for use outside the extension, and `extension.neon`'s
  parameters are unchanged apart from the additions above.

## [1.0.0]

The first release: [Sloppy](https://github.com/heyosseus/sloppy)'s findings as
PHPStan errors.

### Added

- `SloppyRule` runs Sloppy once per PHPStan run, over the files PHPStan
  analysed, and reports each finding as an error with the identifier
  `sloppy.<rule>`, the finding's file and line, and its suggestion as the tip.
- It reports what `sloppy ci` would fail on: files both tools cover, at or
  above the project's `fail_on`, less what `.sloppy-baseline.json` accepts.
  The whole Sloppy project is indexed, so cross-file rules see every file even
  when PHPStan analysed one.
- Parameters: `sloppy.projectRoot`, `sloppy.config`, `sloppy.failOn` and
  `sloppy.useBaseline`, validated by PHPStan's configuration schema.
- A configuration Sloppy cannot read, or a rule that throws, is reported as
  `sloppy.internalError` instead of failing silently or crashing PHPStan.
- Registered through `extra.phpstan`, so `phpstan/extension-installer`
  enables it on install.

[Unreleased]: https://github.com/heyosseus/phpstan-sloppy/compare/v1.1.0...HEAD
[1.1.0]: https://github.com/heyosseus/phpstan-sloppy/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/heyosseus/phpstan-sloppy/releases/tag/v1.0.0
