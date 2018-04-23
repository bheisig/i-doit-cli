#   Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).


##  [Unreleased]


### Added

-   Create new object or category entry (command `create`)


### Changed

-   Separate CLI-related code from application code


##  [0.4] – 2017-09-21


### Added

-   Allow wildcards (`*`) in object titles (command `read`)
-   Print a list of available categories (command `categories`)
-   Print a list of available object types and group them (command `types`)
-   Assign IPs to subnets (command `fixip`)
-   Display error when user asks for specific category entries but category is not assigned to object type(s)


##  [0.3] – 2017-07-25


### Added

-   Show everything about an object (command `idoit show`)
-   Read object by its identifier (command `idoit read`)
-   Perform self-defined API requests (command `idoit call`)
-   Find errors in additional configuration files


### Fixed

-   Show the right command description (`idoit help [command]` or `idoit [command] --help`)
-   Command `idoit read` was unable to fetch a list of objects or attributes for an object when configuration setting `limitBatchRequest` is disabled (`0`).


##  [0.2] – 2017-04-06


### Added

-   Find your data with new command `search`
-   List attributes and assigned categories with command `read`
-   Sort results with command `read`
-   Strip HTML code in results for command `read`
-   Limit batch requests (if configured) for command `read` with setting `limitBatchRequests`
-   Describe a lot of examples for command `read` in [documentation](README.md) und built-in help
-   Simple bash completion
-   Create racks and servers with categories "formfactor", "cpu", "model", and "location" with command `random`
-   Put servers into empty racks with command `random`
-   More examples in `docs/` for command `random`
-   Show message if cache is out-dated, see configuration setting `cacheLifetime`


### Fixed

-   Errors in built-in `help`
-   Removed out-dated command line options
-   Source code documentation


##  0.1 – 2017-02-06

Initial release


[Unreleased]: https://github.com/bheisig/i-doit-cli/compare/0.4...HEAD
[0.4]: https://github.com/bheisig/i-doit-cli/compare/0.3...0.4
[0.3]: https://github.com/bheisig/i-doit-cli/compare/0.2...0.3
[0.2]: https://github.com/bheisig/i-doit-cli/compare/0.1...0.2
