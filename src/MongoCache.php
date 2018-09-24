<?php

namespace SubjectivePHP\Psr\SimpleCache;

use DateInterval;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;
use Psr\SimpleCache\CacheInterface;
use SubjectivePHP\Psr\SimpleCache\Serializer\NullSerializer;
use SubjectivePHP\Psr\SimpleCache\Serializer\SerializerInterface;

/**
 * A PSR-16 implementation which stores data in a MongoDB collection.
 */
final class MongoCache implements CacheInterface
{
    use KeyValidatorTrait;
    use TTLValidatorTrait;

    /**
     * MongoDB collection containing the cached responses.
     *
     * @var Collection
     */
    private $collection;

    /**
     * The object responsible for serializing data to and from Mongo documents.
     *
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * Array of settings to use with find commands.
     *
     * @var array
     */
    private static $findSettings = [
        'typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array'],
        'projection' => ['expires' => false],
    ];

    /**
     * Construct a new instance of MongoCache.
     *
     * @param Collection          $collection The collection containing the cached data.
     * @param SerializerInterface $serializer A concrete serializer for converting data to and from BSON serializable
     *                                        data.
     */
    public function __construct(Collection $collection, SerializerInterface $serializer = null)
    {
        $this->collection = $collection;
        $this->serializer = $serializer ?? new NullSerializer();
    }

    /**
     * Fetches a value from the cache.
     *
     * @param string $key     The unique key of this item in the cache.
     * @param mixed  $default Default value to return if the key does not exist.
     *
     * @return mixed The value of the item from the cache, or $default in case of cache miss.
     *
     * @throws InvalidArgumentException Thrown if the $key string is not a legal value.
     */
    public function get($key, $default = null)//@codingStandardsIgnoreLine Interface does not define type-hints or return
    {
        $this->validateKey($key);
        $cached = $this->collection->findOne(['_id' => $key], self::$findSettings);
        if ($cached === null) {
            return $default;
        }

        return $this->serializer->unserialize($cached['data']);
    }

    /**
     * Persists data in the cache, uniquely referenced by a key with an optional expiration TTL time.
     *
     * @param string                    $key   The key of the item to store.
     * @param mixed                     $value The value of the item to store, must be serializable.
     * @param null|integer|DateInterval $ttl   Optional. The TTL value of this item. If no value is sent and
     *                                         the driver supports TTL then the library may set a default value
     *                                         for it or let the driver take care of that.
     *
     * @return boolean True on success and false on failure.
     *
     * @throws InvalidArgumentException Thrown if the $key string is not a legal value.
     */
    public function set($key, $value, $ttl = null)//@codingStandardsIgnoreLine Interface does not define type-hints or return
    {
        $this->validateKey($key);
        return $this->updateCache($key, $this->serializer->serialize($value), $this->getExpires($ttl));
    }

    /**
     * Delete an item from the cache by its unique key.
     *
     * @param string $key The unique cache key of the item to delete.
     *
     * @return boolean True if the item was successfully removed. False if there was an error.
     *
     * @throws InvalidArgumentException Thrown if the $key string is not a legal value.
     */
    public function delete($key)//@codingStandardsIgnoreLine Interface does not define type-hints or return
    {
        $this->validateKey($key);
        try {
            $this->collection->deleteOne(['_id' => $key]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Wipes clean the entire cache's keys.
     *
     * @return boolean True on success and false on failure.
     */
    public function clear()//@codingStandardsIgnoreLine Interface does not define type-hints or return
    {
        try {
            $this->collection->deleteMany([]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Obtains multiple cache items by their unique keys.
     *
     * @param iterable $keys    A list of keys that can obtained in a single operation.
     * @param mixed    $default Default value to return for keys that do not exist.
     *
     * @return array List of key => value pairs. Cache keys that do not exist or are stale will have $default as value.
     *
     * @throws InvalidArgumentException Thrown if the $key string is not a legal value.
     */
    public function getMultiple($keys, $default = null)//@codingStandardsIgnoreLine Interface does not define type-hints or return
    {
        $this->validateKeys($keys);

        $items = array_fill_keys($keys, $default);
        $cached = $this->collection->find(['_id' => ['$in' => $keys]], self::$findSettings);
        foreach ($cached as $item) {
            $items[$item['_id']] = $this->serializer->unserialize($item['data']);
        }

        return $items;
    }

    /**
     * Persists a set of key => value pairs in the cache, with an optional TTL.
     *
     * @param iterable                  $values A list of key => value pairs for a multiple-set operation.
     * @param null|integer|DateInterval $ttl    Optional. The TTL value of this item. If no value is sent and
     *                                          the driver supports TTL then the library may set a default value
     *                                          for it or let the driver take care of that.
     *
     * @return boolean True on success and false on failure.
     *
     * @throws InvalidArgumentException Thrown if $values is neither an array nor a Traversable,
     *                                  or if any of the $values are not a legal value.
     */
    public function setMultiple($values, $ttl = null)//@codingStandardsIgnoreLine Interface does not define type-hints or return
    {
        $expires = $this->getExpires($ttl);
        foreach ($values as $key => $value) {
            $this->validateKey($key);
            if (!$this->updateCache($key, $this->serializer->serialize($value), $expires)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Deletes multiple cache items in a single operation.
     *
     * @param iterable $keys A list of string-based keys to be deleted.
     *
     * @return boolean True if the items were successfully removed. False if there was an error.
     *
     * @throws InvalidArgumentException Thrown if $keys is neither an array nor a Traversable,
     *                                  or if any of the $keys are not a legal value.
     */
    public function deleteMultiple($keys)//@codingStandardsIgnoreLine Interface does not define type-hints
    {
        $this->validateKeys($keys);

        try {
            $this->collection->deleteMany(['_id' => ['$in' => $keys]]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Determines whether an item is present in the cache.
     *
     * NOTE: It is recommended that has() is only to be used for cache warming type purposes
     * and not to be used within your live applications operations for get/set, as this method
     * is subject to a race condition where your has() will return true and immediately after,
     * another script can remove it making the state of your app out of date.
     *
     * @param string $key The cache item key.
     *
     * @return boolean
     *
     * @throws InvalidArgumentException Thrown if the $key string is not a legal value.
     */
    public function has($key) //@codingStandardsIgnoreLine  Interface does not define type-hints
    {
        $this->validateKey($key);
        return $this->collection->count(['_id' => $key]) === 1;
    }

    /**
     * Upserts a PSR-7 response in the cache.
     *
     * @param string      $key     The key of the response to store.
     * @param array       $value   The data to store.
     * @param UTCDateTime $expires The expire date of the cache item.
     *
     * @return boolean
     */
    private function updateCache(string $key, array $value, UTCDateTime $expires) : bool
    {
        $document = ['_id' => $key, 'expires' => $expires, 'data' => $value];
        try {
            $this->collection->updateOne(['_id' => $key], ['$set' => $document], ['upsert' => true]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Converts the given time to live value to a UTCDateTime instance;
     *
     * @param mixed $ttl The time-to-live value to validate.
     *
     * @return UTCDateTime
     */
    private function getExpires($ttl) : UTCDateTime
    {
        $this->validateTTL($ttl);

        $ttl = $ttl ?: 86400;

        if ($ttl instanceof \DateInterval) {
            return new UTCDateTime((new \DateTime('now'))->add($ttl)->getTimestamp() * 1000);
        }

        return new UTCDateTime((time() + $ttl) * 1000);
    }
}
