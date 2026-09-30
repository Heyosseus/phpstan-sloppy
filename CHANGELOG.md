# Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[Unreleased]: https://github.com/heyosseus/phpstan-sloppy/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/heyosseus/phpstan-sloppy/releases/tag/v1.0.0
