#   Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).


##  [Unreleased]


### Added

-   Support for Windows operating systems
-   `random`: Create persons with random names, e-mail addresses and desks
-   `random`: Install applications on laptops with license keys


### Fixed

-   `init`: Validation error occurs even before initialization could start
-   `init`: Ask for hostname if HTTP proxy will be used


##  [0.5] – 2018-04-24

**Important notes:**

1.  PHP 5.6 support is dropped. You need PHP 7.0 or higher.
2.  Re-load cache with `idoitcli init`


### Added

-   `create`: Create new object or category entry


### Changed

-   Renamed binary file to `idoitcli`
-   Separate CLI-related code from application code
-   Drop PHP 5.6 support and require PHP 7.0 or higher


### Fixed

-   `show`: Not all category entries are printed
-   `init`: Misleading cache file names for categories


##  [0.4] – 2017-09-21


### Added

-   `read`: Allow wildcards (`*`) in object titles
-   `categories`: Print a list of available categories
-   `types`: Print a list of available object types and group them
-   `fixip`: Assign IPs to subnets
-   Display error when user asks for specific category entries but category is not assigned to object type(s)


##  [0.3] – 2017-07-25


### Added

-   `show`: Show everything about an object
-   `read`: Read object by its identifier
-   `call`: Perform self-defined API requests
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


[Unreleased]: https://github.com/bheisig/i-doit-cli/compare/0.5...HEAD
[0.5]: https://github.com/bheisig/i-doit-cli/compare/0.4...0.5
[0.4]: https://github.com/bheisig/i-doit-cli/compare/0.3...0.4
[0.3]: https://github.com/bheisig/i-doit-cli/compare/0.2...0.3
[0.2]: https://github.com/bheisig/i-doit-cli/compare/0.1...0.2
