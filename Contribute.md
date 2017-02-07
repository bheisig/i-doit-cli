#   Contribution

Thank you very much for your interest in this project! There are plenty of ways you can support us. :-)


##  Use it!

The best and (probably) easiest way is to use the script in your daily work. It would be very nice to share your thoughts with us. We love to hear from you.

If you have questions how to use it properly read the [documentation](Readme.md) carefully.


##  Report bugs!

If you find something strange please report it to [our issue tracker](https://github.com/bheisig/i-doit-cli/issues).


##  Make a wish!

Of course, there are some features in the pipeline. But if you have good ideas how to improve the script please let us know! Write a feature request [in our issue tracker](https://github.com/bheisig/i-doit-cli/issues).


##  Translation/Localization

_tbd_


##  Setup a development environment

If you like to contribute source code, documentation snippets, self-explaining examples or other useful bits, fork this repository, setup the environment and make a pull request.

~~~ {.bash}
git clone https://github.com/bheisig/i-doit-cli.git
~~~

If you have a GitHub account, create a fork first and then clone the repository.

After that, setup the environment with Composer:

~~~ {.bash}
composer install
~~~

Now it is the time to do your stuff. Do not forget to commit your changes. When you are done consider to make a pull requests.


##  Requirements

This projects has some dependencies:

*   [PHP](https://php.net/), version 5.6+
*   [Composer](https://getcomposer.org/)
*   Composer package [`bheisig\idoitapi`](https://github.com/bheisig/i-doit-api-client-php)
*   A working copy of [i-doit](https://i-doit.com/).

Developers must meet some more requirements:

*   [Git](https://git-scm.com/) (obviously)
*   [Pandoc](https://pandoc.org/)
*   make
*   Composer package [`clue/phar-composer`](https://github.com/clue/phar-composer)


##  Create a binary

Just call `make` or `make build`. This creates a file `idoit`.


##  Create a distribution tarball

*   Bump version in file `project.json`
*   Call `make`
*   Call `make dist`

The last call creates a file `i-doit-cli-<VERSION>.tar.gz`.

