# Changelog

All notable changes to this project will be documented in this file. This project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.2.1] - 2021-10-31

### Changed

- Handle replacements in plugin command better.

## [1.2.0] - 2021-07-31

### Changed

- Supports PHP 8.

## [1.1.2] - 2021-07-26

### Fixed

- Issue where the composer namespace wasn't properly generated.

## [1.1.1] - 2021-07-26

### Fixed

- Issue where all the make commands hadn't been properly removed.

## [1.1.0] - 2021-07-26

### Changed

- Added "prefix" option and question to make:plugin, to support the latest plugin base version with vendor prefixing.

### Deprecated

- Remove the plugin specific make commands, as they are now handled in the Forge CLI included with the plugin base.

## [1.0.0] - 2021-03-13

First Version.
