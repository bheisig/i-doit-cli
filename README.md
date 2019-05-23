#   i-doit CLI tool

Access your CMDB on the command line interface

[![Latest Stable Version](https://img.shields.io/packagist/v/bheisig/idoitcli.svg)](https://packagist.org/packages/bheisig/idoitcli)
[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%207.0-8892BF.svg)](https://php.net/)
[![Build Status](https://travis-ci.org/bheisig/i-doit-cli.svg?branch=master)](https://travis-ci.org/bheisig/i-doit-cli)


##  About

[i-doit](https://i-doit.com) is a software application for IT documentation and a CMDB (Configuration Management Database). This application is very useful to collect all your knowledge about the IT infrastructure you are dealing with. i-doit is a Web application and [has an exhausting API](https://kb.i-doit.com/pages/viewpage.action?pageId=37355644) which is very useful to automate your infrastructure.

This application provides a simple, but powerful command line interface to access your CMDB data stored in i-doit.


##  Features

*   Read information about objects their types and even their attributes
*   Find your needle in the haystack called CMDB
*   Do you need a free IP address in a particular subnet? This app suggests one for you.
*   Stress your system: auto-generate thousands of objects


##  Requirements

Before using this app your system must meet the following requirements:

*   Of course, i-doit pro/open, version 1.12 or higher
*   i-doit API add-on, version 1.10 or higher
*   Any POSIX operating system (GNU/Linux, *BSD, MacOS) or Windows
*   PHP >= 7.0 (7.2 is recommended)
*   PHP extensions `calendar`, `cli`, `cURL`, `json`, `phar` and `zlib`
*   PHP extension `pcntl` is optional, but highly recommended (non-Windows only)


##  Install and update

You have two good options to download and install this application:

*   Download any stable release (**recommended**)
*   Use [PHIVE](https://phar.io/)


### Download release

Download the latest stable version of the binary `idoitcli` [from the release site](https://github.com/bheisig/i-doit-cli/releases). Then install it system-wide:

~~~ {.bash}
curl -OL https://github.com/bheisig/i-doit-cli/releases/download/0.7/idoitcli
chmod 755 idoitcli
sudo mv idoitcli /usr/local/bin/
~~~

To be up-to-date just repeat the steps above.


### Use PHIVE

With [PHIVE](https://phar.io/) you are able to download and install PHAR files on your system. Additionally, it will verify the SHA1 and GPG signatures which is highly recommended. If you have PHIVE already installed you can fetch the latest version of this application:

~~~ {.bash}
sudo phive install --global bheisig/i-doit-cli
~~~

This will install an executable binary to `/usr/bin/idoitcli`.

If a new release is available you can perform an update:

~~~ {.bash}
sudo phive update --global
~~~


##  Usage

Just run the application to show the basic usage:

~~~ {.bash}
idoitcli
~~~


##  First steps

This application caches a lot locally to give you the best user experience. Run the `init` command:

~~~ {.bash}
idoitcli init
~~~

Some simple questions will be asked how to access your i-doit installation. Next step is to create cache files:

~~~ {.bash}
idoitcli cache
~~~

After that some files will be created in your home directory under `~/.idoitcli/`: Each i-doit installation has its own cache files under `data/`. Your user-defined configuration file is called `config.json`.

You may check your current status by running:

~~~ {.bash}
idoitcli status
~~~

This gives you some basic information about your i-doit installation, your settings and your user.


##  Access your CMDB data

This is probably the best part: Read information about objects, their types and even attributes.

List all object types:

~~~ {.bash}
idoitcli read
idoitcli read /
~~~

List server objects:

~~~ {.bash}
idoitcli read server/
idoitcli read server/host.example.net
idoitcli read server/*.example.net
idoitcli read server/host.*.net
idoitcli read server/*.*.net
idoitcli read server/host*
idoitcli read server/*
~~~

Show common information about server "host.example.net":

~~~ {.bash}
idoitcli read server/host.example.net
~~~

Show common information about object identifier "42":

~~~ {.bash}
idoitcli read 42
~~~

Show common information about one or more objects by their titles:

~~~ {.bash}
idoitcli read *.example.net
idoitcli read host.*.net
idoitcli read *.*.net
idoitcli read host*
~~~

List assigned categories:

~~~ {.bash}
idoitcli read server/host.example.net/
~~~

Show values from category "model" for this server:

~~~ {.bash}
idoitcli read server/host.example.net/model
~~~

Show values from category "model" for one or more servers:

~~~ {.bash}
idoitcli read server/*.example.net/model
idoitcli read server/host.*.net/model
idoitcli read server/*.*.net/model
idoitcli read server/host*/model
idoitcli read server/*/model
~~~

Or just show the name of the model:

~~~ {.bash}
idoitcli read server/host.example.net/model/model
~~~

List available attributes for category "model":

~~~ {.bash}
idoitcli read server/host.example.net/model/
~~~

You may leave the object type empty for specific objects, for example:

~~~ {.bash}
idoitcli read host.example.net/model
~~~

**Notice:** These examples work great with unique names. That is why it is common practice to give objects unique titles that are not in conflict with object types and categories.


##  Show everything about an object

~~~ {.bash}
idoitcli show myserver
idoitcli show "My Server"
idoitcli show 42
~~~


##  Find your data

Find your needle in the haystack called CMDB:

~~~ {.bash}
idoitcli search myserver
idoitcli search "My Server"
~~~


##  Show the next free IPv4 address

Get the next free IPv4 address for a particular subnet:

~~~ {.bash}
idoitcli nextip SUBNET
~~~

`SUBNET` may be the object title or its identifier.


##  Auto-generate objects

For testing purposes stress your i-doit installation and let the app create thousands of objects, attributes and relations between objects:

~~~ {.bash}
idoitcli -c FILE random
~~~

There are some examples located under `docs/`.


##  Update the caches

If your CMDB configuration has changed you need to re-create the cache files by running the `cache` command:

~~~ {.bash}
idoitcli cache
~~~


##  Playground

Perform self-defined API requests – pass request as argument:

~~~ {.bash}
idoitcli call '{"version": "2.0","method": "idoit.version","params": {"apikey": "c1ia5q","language": "en"},"id": 1}'
~~~

Pipe request:

~~~ {.bash}
echo '{"version": "2.0","method": "idoit.version","params": {"apikey": "c1ia5q","language": "en"},"id": 1}' | idoitcli call
~~~

Read request from file:

~~~ {.bash}
cat request.txt | idoitcli call
~~~

Read request from standard input (double-enter to execute):

~~~ {.bash}
idoitcli call
~~~


##  Configuration

There are some ways to set your configurations settings:

1.  System-wide settings are stored under `/etc/idoitcli/config.json`.
2.  User-defined settings are stored under `~/.idoitcli/config.json`.
3.  Pass your run-time settings by using the options `-c` or `--config`.

Keep in mind this order matters. Each file is optional. Any combination is possible. Furthermore, to include even more configuration files you can pass the options `-c` and `--config` as often as you like.

The configuration files are JSON-formatted.


##  Contribute

Please, report any issues to [our issue tracker](https://github.com/bheisig/i-doit-cli/issues). Pull requests and OS distribution packages are very welcomed. For further information, see file [`CONTRIBUTING.md`](CONTRIBUTING.md).


##  Copyright & License

Copyright (C) 2016-19 [Benjamin Heisig](https://benjamin.heisig.name/)

Licensed under the [GNU Affero GPL version 3 or later (AGPLv3+)](https://gnu.org/licenses/agpl.html). This is free software: you are free to change and redistribute it. There is NO WARRANTY, to the extent permitted by law.

[List of first names and gender:](https://www.heise.de/ct/ftp/07/17/182/) Copyright (C) 2007-2008 Jörg Michael, licensed under [GNU Free Documentation License](https://www.gnu.org/licenses/fdl.html)

[List of surnames:](https://github.com/HBehrens/phonet4n/blob/master/src/Tests/data/nachnamen.txt) Copyright (C) Heiko Behrens, lisenced under [GNU Lesser General Public License](https://www.gnu.org/licenses/lgpl-3.0)
