# Changelog

All notable changes to this fork are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

This fork tracks upstream [Filament 4.x](https://github.com/filamentphp/filament). Upstream releases are listed in the [upstream release notes](https://github.com/filamentphp/filament/releases).

## [Unreleased]

### Changed

- `composer.json`: require PHP patch releases with the latest upstream security fixes (`>=8.2.35 <8.3 || >=8.3.36 <8.4 || >=8.4.27 <8.5 || >=8.5.12 <8.6`)
- `composer.json`: pin `filament/support` to `^4.0`
- `SECURITY.md`: restored the upstream policy and added PHP requirement and deployment recommendations
