#   i-doit CLI Tool

Access your CMDB on the command line interface


##  About

[i-doit](https://i-doit.com) is a software application for IT documentation and a CMDB (Configuration Management Database). This application is very useful to collect all your knowledge about the IT infrastructure you are dealing with. i-doit is a Web application and [has an exhausting API](https://kb.i-doit.com/pages/viewpage.action?pageId=37355644) which is very useful to automate your infrastructure.

This client provides a simple, but powerful command line interface to access your CMDB data stored in i-doit.


##  Features

*   Read information about objects their types and even their attributes
*   Do you need a free IP address in a particular subnet? This script suggests one for you.
*   Stress your system: auto-generate thousands of objects


##  Install and Update

You have several options to download (and kinda install) this script:

*   Download any stable release (**recommended**)
*   Clone the Git repository to fetch the (maybe unstable) development branch


### Download Release

Download the latest version of the binary `idoit` [from the release site](https://github.com/bheisig/i-doit-cli/releases). Then install it system-wide:

~~~ {.bash}
chmod 755 idoit
sudo mv idoit /usr/local/bin/
~~~

To be up-to-date just repeat the steps above.


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

Just run the script to show the basic usage:

~~~ {.bash}
idoit
~~~


##  First Steps

This script caches a lot locally to give you the best user experience. Run the `init` command:

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
~~~

List all servers:

~~~ {.bash}
idoit read server
~~~

Show basic information about server "host123":

~~~ {.bash}
idoit read server/host123
~~~

List the attributes from category "model" for this server:

~~~ {.bash}
idoit read server/host123/model
~~~

Or just show the serial number:

~~~ {.bash}
idoit read server/host123/model/serial
~~~


##  Show the Next Free IP Address

Get the next free IPv4 address for a particular subnet:

~~~ {.bash}
idoit nextip SUBNET
~~~

`SUBNET` may be the object title or its identifier.


##  Auto-generate Objects

For testing purposes stress your i-doit installation and let the script create thousands of objects, attributes and relations between objects:

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


##  Configuration

There are some ways to set your configurations settings:

1.  System-wide settings are stored under `/etc/idoitcli/config.json`.
2.  User-defined settings are stored under `~/.idoitcli/config.json`.
3.  Pass your run-time settings by using the options `-c` or `--config`.

Keep in mind this order matters. Each file is optional. Any combination is possible. Furthermore, to include even more configuration files you can pass the options `-c` and `--config` as often as you like.

The configuration files are JSON-formatted.


##  Contribute

Please, report any issues to [our issue tracker](https://github.com/bheisig/i-doit-cli/issues). Pull requests and distribution packages are very welcomed.


##  Copyright & License

Copyright (C) 2017 [Benjamin Heisig](https://benjamin.heisig.name/)

Licensed under the [GNU Affero GPL version 3 or later (AGPLv3+)](https://gnu.org/licenses/agpl.html). This is free software: you are free to change and redistribute it. There is NO WARRANTY, to the extent permitted by law.
