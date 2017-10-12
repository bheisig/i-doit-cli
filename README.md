#   i-doit CLI Tool

Access your CMDB on the command line interface

[![Build Status](https://travis-ci.org/bheisig/i-doit-cli.svg?branch=master)](https://travis-ci.org/bheisig/i-doit-cli)


##  About

[i-doit](https://i-doit.com) is a software application for IT documentation and a CMDB (Configuration Management Database). This application is very useful to collect all your knowledge about the IT infrastructure you are dealing with. i-doit is a Web application and [has an exhausting API](https://kb.i-doit.com/pages/viewpage.action?pageId=37355644) which is very useful to automate your infrastructure.

This client provides a simple, but powerful command line interface to access your CMDB data stored in i-doit.


##  Features

*   Read information about objects their types and even their attributes
*   Find your needle in the haystack called CMDB
*   Do you need a free IP address in a particular subnet? This app suggests one for you.
*   Stress your system: auto-generate thousands of objects


##  Dependencies

Before using this app your system must meet the following requirements:

*   Any POSIX operating system (GNU/Linux, *BSD, MacOS)
*   PHP >= 5.6 (7.x is recommended)
*   PHP extensions `cURL`, `json` and `cli`


##  Install and Update

You have several options to download (and kinda install) this app:

*   Download any stable release (**recommended**)
*   Use [PHIVE](https://phar.io/)
*   Clone the Git repository to fetch the (maybe unstable) development branch


### Download Release

Download the latest stable version of the binary `idoit` [from the release site](https://github.com/bheisig/i-doit-cli/releases). Then install it system-wide:

~~~ {.bash}
curl -O https://github.com/bheisig/i-doit-cli/releases/download/0.4/idoit
chmod 755 idoit
sudo mv idoit /usr/local/bin/
~~~

To be up-to-date just repeat the steps above.


### Use PHIVE

With [PHIVE](https://phar.io/) you are able to download and install PHAR files on your system. Additionally, it will verify the SHA1 and GPG signatures which is highly recommended. If you have PHIVE already installed you can fetch the latest version of this app:

~~~ {.bash}
sudo phive install --global bheisig/i-doit-cli
~~~

This will install an executable binary to `/usr/bin/idoit`.

If a new release is available you can perform an update:

~~~ {.bash}
sudo phive update --global
~~~


### Fetch Source Code

Fetch the current development branch (maybe unstable) from the Git repository.

~~~ {.bash}
git clone https://github.com/bheisig/i-doit-cli.git
cd i-doit-cli/
~~~

The executable is located under `bin/idoit`. If you want to use it system-wide you will need [Composer](https://getcomposer.org/):

~~~ {.bash}
composer install
make
sudo make install
~~~

After that the executable is located under `/usr/local/bin/idoit`.

Fetching updates is similar:

~~~ {.bash}
cd i-doit-cli/
git pull
composer update
make
sudo make install
~~~


##  Usage

Just run the app to show the basic usage:

~~~ {.bash}
idoit
~~~


##  First Steps

This app caches a lot locally to give you the best user experience. Run the `init` command:

~~~ {.bash}
idoit init
~~~

Some simple questions will be asked how to access your i-doit installation.

After that some files will be created in your home directory under `~/.idoitcli/`: Each i-doit installation has its own cache files under `data/`. Your user-defined configuration file is called `config.json`.

You may check your current status by running:

~~~ {.bash}
idoit status
~~~

This gives you some basic information about your i-doit installation, your settings and your user.


##  Access Your CMDB Data

This is probably the best part: Read information about objects, their types and even attributes.

List all object types:

~~~ {.bash}
idoit read
idoit read /
~~~

List server objects:

~~~ {.bash}
idoit read server/
idoit read server/host.example.net
idoit read server/*.example.net
idoit read server/host.*.net
idoit read server/*.*.net
idoit read server/host*
idoit read server/*
~~~

Show common information about server "host.example.net":

~~~ {.bash}
idoit read server/host.example.net
~~~

Show common information about object identifier "42":

~~~ {.bash}
idoit read 42
~~~

Show common information about one or more objects by their titles:

~~~ {.bash}
idoit read *.example.net
idoit read host.*.net
idoit read *.*.net
idoit read host*
~~~

List assigned categories:

~~~ {.bash}
idoit read server/host.example.net/
~~~

Show values from category "model" for this server:

~~~ {.bash}
idoit read server/host.example.net/model
~~~

Show values from category "model" for one or more servers:

~~~ {.bash}
idoit read server/*.example.net/model
idoit read server/host.*.net/model
idoit read server/*.*.net/model
idoit read server/host*/model
idoit read server/*/model
~~~

Or just show the name of the model:

~~~ {.bash}
idoit read server/host.example.net/model/model
~~~

List available attributes for category "model":

~~~ {.bash}
idoit read server/host.example.net/model/
~~~

You may leave the object type empty for specific objects, for example:

~~~ {.bash}
idoit read host.example.net/model
~~~

**Notice:** These examples work great with unique names. That is why it is common practice to give objects unique titles that are not in conflict with object types and categories.


##  Show Everything About an Object

~~~ {.bash}
idoit show myserver
idoit show "My Server"
idoit show 42
~~~


##  Find Your Data

Find your needle in the haystack called CMDB:

~~~ {.bash}
idoit search myserver
idoit search "My Server"
~~~


##  Show the Next Free IP Address

Get the next free IPv4 address for a particular subnet:

~~~ {.bash}
idoit nextip SUBNET
~~~

`SUBNET` may be the object title or its identifier.


##  Auto-generate Objects

For testing purposes stress your i-doit installation and let the app create thousands of objects, attributes and relations between objects:

~~~ {.bash}
idoit -c FILE random
~~~

There are some examples located under `docs/`.


##  Update the Caches

If your CMDB configuration has changed you need to re-create the cache files by running the `init` command:

~~~ {.bash}
idoit init
~~~

Just type `n` (no) if you want to keep your existing configuration settings.


##  Playground

Perform self-defined API requests – pass request as argument:

~~~ {.bash}
idoit call '{"version": "2.0","method": "idoit.version","params": {"apikey": "c1ia5q","language": "en"},"id": 1}'
~~~

Pipe request:

~~~ {.bash}
echo '{"version": "2.0","method": "idoit.version","params": {"apikey": "c1ia5q","language": "en"},"id": 1}' | idoit call
~~~

Read request from file:

~~~ {.bash}
cat request.txt | idoit call
~~~

Read request from standard input (double-enter to execute):

~~~ {.bash}
idoit call
~~~


##  Configuration

There are some ways to set your configurations settings:

1.  System-wide settings are stored under `/etc/idoitcli/config.json`.
2.  User-defined settings are stored under `~/.idoitcli/config.json`.
3.  Pass your run-time settings by using the options `-c` or `--config`.

Keep in mind this order matters. Each file is optional. Any combination is possible. Furthermore, to include even more configuration files you can pass the options `-c` and `--config` as often as you like.

The configuration files are JSON-formatted.


##  Contribute

Please, report any issues to [our issue tracker](https://github.com/bheisig/i-doit-cli/issues). Pull requests and OS distribution packages are very welcomed. For further information, see file [`Contribute.md`](Contribute.md).


##  Copyright & License

Copyright (C) 2017 [Benjamin Heisig](https://benjamin.heisig.name/)

Licensed under the [GNU Affero GPL version 3 or later (AGPLv3+)](https://gnu.org/licenses/agpl.html). This is free software: you are free to change and redistribute it. There is NO WARRANTY, to the extent permitted by law.
