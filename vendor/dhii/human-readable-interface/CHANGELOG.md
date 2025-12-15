# Change log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [[*next-version*]] - YYYY-MM-DD

## [0.2.0-alpha1] - 2021-05-03
### Removed
- Support for older PHP versions. Now requires at least PHP 7.1 (#2).

### Added
- Support for PHP 8.

### Changed
- Now using native `Stringable` rather than proprietary standard.
  This requires pre-PHP-8 projects to use a polyfill.

## [0.1.0] - 2020-05-14
Initial version.
