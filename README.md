# Simple MongoDB Cache

[![Build Status](https://travis-ci.org/subjective-php/psr-cache-mongodb.svg?branch=master)](https://travis-ci.org/subjective-php/psr-cache-mongodb)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/subjective-php/psr-cache-mongodb/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/subjective-php/psr-cache-mongodb/?branch=master)
[![Coverage Status](https://coveralls.io/repos/github/subjective-php/psr-cache-mongodb/badge.svg?branch=master)](https://coveralls.io/github/subjective-php/psr-cache-mongodb?branch=master)

[![Latest Stable Version](https://poser.pugx.org/subjective-php/psr-cache-mongodb/v/stable)](https://packagist.org/packages/subjective-php/psr-cache-mongodb)
[![Latest Unstable Version](https://poser.pugx.org/subjective-php/psr-cache-mongodb/v/unstable)](https://packagist.org/packages/subjective-php/psr-cache-mongodb)
[![License](https://poser.pugx.org/subjective-php/psr-cache-mongodb/license)](https://packagist.org/packages/subjective-php/psr-cache-mongodb)

[![Total Downloads](https://poser.pugx.org/subjective-php/psr-cache-mongodb/downloads)](https://packagist.org/packages/subjective-php/psr-cache-mongodb)
[![Daily Downloads](https://poser.pugx.org/subjective-php/psr-cache-mongodb/d/daily)](https://packagist.org/packages/subjective-php/psr-cache-mongodb)
[![Monthly Downloads](https://poser.pugx.org/subjective-php/psr-cache-mongodb/d/monthly)](https://packagist.org/packages/subjective-php/psr-cache-mongodb)

[![Documentation](https://img.shields.io/badge/reference-phpdoc-blue.svg?style=flat)](http://www.pholiophp.org/subjective-php/psr-cache-mongodb)

[PSR-16 SimpleCache](http://www.php-fig.org/psr/psr-16/) Implementation using [MongoDB](https://docs.mongodb.com/php-library/master/)

## Requirements

Requires PHP 7.0 (or later).

## Composer
To add the library as a local, per-project dependency use [Composer](http://getcomposer.org)! Simply add a dependency on `subjective-php/psr-cache-mongodb` to your project's `composer.json` file such as:

```sh
composer require subjective-php/psr-cache-mongodb
```

## Contact
Developers may be contacted at:

 * [Pull Requests](https://github.com/subjective-php/psr-cache-mongodb/pulls)
 * [Issues](https://github.com/subjective-php/psr-cache-mongodb/issues)

## Project Build
With a checkout of the code get [Composer](http://getcomposer.org) in your PATH and run:

```sh
composer install
./vendor/bin/phpunit
```
## Example Caching PSR-7 Response Messages with Guzzle Client

Below is a very simplified example of caching responses to GET requests in mongo.

```php
<?php

use Chadicus\Psr\SimplCache\SerializerInterface;
use Chadicus\Psr\SimplCache\MongoCache;
use GuzzleHttp\Psr7;
use MongoDB\Client;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Provides serialization from mongo documents to PSR-7 response objects.
 */
final class Psr7Serializer implements SerializerInterface
{
    /**
     * Unserializes cached data into the original state.
     *
     * @param array $data The data to unserialize.
     *
     * @return Diactoros\Response
     */
    public function unserialize(array $data)
    {
        return new Psr7\Response(
            $data['statusCode'],
            $data['headers'],
            $data['body'],
            $data['protocolVersion'],
            $data['reasonPhrase']
        );
    }

    /**
     * Serializes the given data for storage in caching.
     *
     * @param mixed $value The data to serialize for caching.
     *
     * @return array The result of serializing the given $data.
     *
     * @throws InvalidArgumentException Thrown if the given value is not a PSR-7 Response instance.
     */
    public function serialize($value) : array
    {
        if (!is_a($value, '\\Psr\\Http\\Message\\ResponseInterface')) {
            throw new class('$value was not a PSR-7 Response') extends \Exception implements InvalidArgumentException { };
        }

        return [
            'statusCode' => $value->getStatusCode(),
            'headers' => $value->getHeaders(),
            'body' => (string)$value->getBody(),
            'protocolVersion' => $value->getProtocolVersion(),
            'reasonPhrase' => $value->getReasonPhrase(),
        ];
    }
}

//create the mongo collection
$collection = (new Client('mongodb://locathost:27017'))->selectDatabase('psr')->selectCollection('cache');
//Set a TTL index on the expires field
$collection->createIndex(['expires' => 1], ['expireAfterSeconds' => 0]);

$cache = new MongoCache($collection, new Psr7Serializer());

// Use the cache when sending guzzle requests

//Only caching GET responses
if ($request->getMethod() === 'GET') {
    $key = (string)$request->getUri();
    $response = $cache->get($key);
    if ($response === null) {
        $response = $guzzleClient->send($request);
        //Add to cache if valid Expires header
        if ($response->hasHeader('Expires')) {
            $expires = strtotime($response->getHeader('Expires')[0]);
            $cache->set($key, $response, $expires - time());
        }
    }
} else {
    $response = $guzzleClient->send($request);
}
```
