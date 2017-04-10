# Chadicus\Psr\SimpleCache\MongoCache

[![Build Status](https://travis-ci.org/chadicus/psr-cache-mongodb.svg?branch=master)](https://travis-ci.org/chadicus/psr-cache-mongodb)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/chadicus/psr-cache-mongodb/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/chadicus/psr-cache-mongodb/?branch=master)
[![Coverage Status](https://coveralls.io/repos/github/chadicus/psr-cache-mongodb/badge.svg?branch=master)](https://coveralls.io/github/chadicus/psr-cache-mongodb?branch=master)

[![Latest Stable Version](https://poser.pugx.org/chadicus/psr-cache-mongodb/v/stable)](https://packagist.org/packages/chadicus/psr-cache-mongodb)
[![Latest Unstable Version](https://poser.pugx.org/chadicus/psr-cache-mongodb/v/unstable)](https://packagist.org/packages/chadicus/psr-cache-mongodb)
[![License](https://poser.pugx.org/chadicus/psr-cache-mongodb/license)](https://packagist.org/packages/chadicus/psr-cache-mongodb)

[![Total Downloads](https://poser.pugx.org/chadicus/psr-cache-mongodb/downloads)](https://packagist.org/packages/chadicus/psr-cache-mongodb)
[![Daily Downloads](https://poser.pugx.org/chadicus/psr-cache-mongodb/d/daily)](https://packagist.org/packages/chadicus/psr-cache-mongodb)
[![Monthly Downloads](https://poser.pugx.org/chadicus/psr-cache-mongodb/d/monthly)](https://packagist.org/packages/chadicus/psr-cache-mongodb)

[PSR-16 SimpleCache](http://www.php-fig.org/psr/psr-16/) Implementation using [MongoDB](https://docs.mongodb.com/php-library/master/)

## Requirements

Chadicus\Util\Exception requires PHP 7.0 (or later).

## Composer
To add the library as a local, per-project dependency use [Composer](http://getcomposer.org)! Simply add a dependency on `chadicus/psr-cache-mongodb` to your project's `composer.json` file such as:

```sh
composer require chadicus/psr-cache-mongodb
```

## Contact
Developers may be contacted at:

 * [Pull Requests](https://github.com/chadicus/psr-cache-mongodb/pulls)
 * [Issues](https://github.com/chadicus/psr-cache-mongodb/issues)

## Project Build
With a checkout of the code get [Composer](http://getcomposer.org) in your PATH and run:

```sh
composer install
./vendor/bin/phpunit
```
