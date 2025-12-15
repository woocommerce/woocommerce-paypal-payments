# Change log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [[*next-version*]] - YYYY-MM-DD

## [0.3.0-alpha3] - 2021-01-14
### Removed
- `ValidationExceptionInterface` was redundant, and burdened implementations.
- `ValidatorInterface#validate()` must now throw `RuntimeException` instead of `ValidationExceptionInterface`.

## [0.3.0-alpha2] - 2021-01-14
### Added
- `ValidatorInterface#validate()` now has explicit `void` return type.

### Changed
- Updated and added missing configs.
- Allowing PHP 8.
- Now _updating_ composer deps in CI, proving that this package is in fact installable on all target versions.
- Using newer Psalm.

### Removed
- No longer depends on Dhii `Stringable` interface. Instead, using Symfony Polyfill for testing.
This is recommended for use by consuming projects in cases when working PHP lower than 8.

## [0.3.0-alpha1] - 2020-05-14
### Removed
- `SpecAwareInterface`.
- `ValidatorFactoryInterface`.
- `SubjectAwareInterface`.
- `ValidatorAwareInterface`.
- `@since` tags everywhere.
- Support for PHP < 7.1.

### Changed
- `ValidationFailedExceptionInterface::getSubject()` is now `getValidationSubject().

## [0.2] - 2018-08-29
Stable release. No code changed.

## [0.2-alpha2] - 2018-03-07
### Added
- `ValidatorFactoryInterface`

### Changed
- Using newer version of `dhii/exception-interface`

### Removed
- `SpecValidatorInterface`

### Fixed
- Added missing import for `Traversable` in `SpecAwareInterface`

## [0.2-alpha1] - 2018-03-06
### Added
- `SpecValidatorInterface`.
- `SpecAwareInterface`.
- `SubjectAwareInterface`.
- `ValidatorAwareInterface`.

## [0.1] - 2017-03-09
Initial release, containing validator and exception interfaces.
