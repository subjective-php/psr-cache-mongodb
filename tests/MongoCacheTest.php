<?php
namespace SubjectivePHPTest\Psr\SimpleCache;

use DateTime;
use DateTimeZone;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;
use MongoDB\Collection;
use SubjectivePHP\Psr\SimpleCache\InvalidArgumentException;
use SubjectivePHP\Psr\SimpleCache\MongoCache;
use SubjectivePHP\Psr\SimpleCache\Serializer\SerializerInterface;

/**
 * Defines unit tests for the MongoCache class.
 *
 * @coversDefaultClass \SubjectivePHP\Psr\SimpleCache\MongoCache
 * @covers ::__construct
 * @covers ::<private>
 * @covers ::<protected>
 */
final class MongoCacheTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Mongo Collection to use in tests.
     *
     * @var Collection
     */
    private $collection;

    /**
     * Cache instance to us in tests.
     *
     * @var MongoCache
     */
    private $cache;

    /**
     * set up each test.
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->collection = (new Client())->selectDatabase('testing')->selectCollection('cache');
        $this->collection->drop();
        $this->cache = new MongoCache($this->collection, $this->getSerializer());
    }

    /**
     * @test
     *
     * @return void
     */
    public function useWithoutSerializer()
    {
        $cache = new MongoCache($this->collection);
        $data = ['a', 'b', 'c'];
        $cache->set('foo', $data);
        $this->assertSame($data, $cache->get('foo'));
    }

    /**
     * Verify behavior of get() when the key is not found.
     *
     * @test
     * @covers ::get
     *
     * @return void
     */
    public function getNotFound()
    {
        $default = new \StdClass();
        $this->assertSame($default, $this->cache->get('key', $default));
    }

    /**
     * Verify basic behavior of get().
     *
     * @test
     * @covers ::get
     *
     * @return void
     */
    public function get()
    {
        $this->collection->insertOne(
            [
                '_id' => 'key',
                'data' => [
                    'timestamp' => 1491782286,
                    'timezone' => 'America/New_York',
                ],
            ]
        );

        $dateTime = new DateTime('@1491782286', new DateTimeZone('America/New_York'));
        $this->assertEquals($dateTime, $this->cache->get('key'));
    }

    /**
     * Verify basic behavior of set().
     *
     * @test
     * @covers ::set
     *
     * @return void
     */
    public function set()
    {
        $ttl = \DateInterval::createFromDateString('1 day');
        $dateTime = new DateTime('2017-04-09 20:54:04', new DateTimeZone('Pacific/Honolulu'));
        $this->cache->set('key', $dateTime, $ttl);
        $expires = new UTCDateTime((new \DateTime('now'))->add($ttl)->getTimestamp() * 1000);
        $this->assertDateTimeDocument('key', $expires, $dateTime);
    }

    /**
     * Verify behavior of set() with invalid $ttl value.
     *
     * @test
     * @covers ::set
     *
     * @return void
     */
    public function setInvalidTTL()
    {
        $this->expectException(\Psr\SimpleCache\InvalidArgumentException::class);
        $this->expectExceptionMessage('$ttl must be null, an integer or \DateInterval instance');
        $this->cache->set('key', new DateTime(), new DateTime());
    }

    /**
     * Verify behavior of set() with empty $key.
     *
     * @test
     * @covers ::set
     *
     * @return void
     */
    public function setEmptyKey()
    {
        $this->expectException(\Psr\SimpleCache\InvalidArgumentException::class);
        $this->expectExceptionMessage('$key must be a valid non empty string');
        $this->cache->set('', new DateTime());
    }

    /**
     * Verify behavior of set() with non string $key.
     *
     * @test
     * @covers ::set
     *
     * @return void
     */
    public function setNonStringKey()
    {
        $this->expectException(\Psr\SimpleCache\InvalidArgumentException::class);
        $this->expectExceptionMessage('$key must be a valid non empty string');
        $this->cache->set(new \StdClass(), new DateTime());
    }

    /**
     * Verify behavior of set() with string $key containing reserved characters.
     *
     * @test
     * @covers ::set
     *
     * @return void
     */
    public function setKeyContainsReservedCharacters()
    {
        $this->expectException(\Psr\SimpleCache\InvalidArgumentException::class);
        $this->expectExceptionMessage('Key \'key with {, ) & @\' contains unsupported characters');
        $this->cache->set('key with {, ) & @', new DateTime());
    }

    /**
     * Verify basic behavior of delete().
     *
     * @test
     * @covers ::delete
     *
     * @return void
     */
    public function delete()
    {
        $this->collection->insertOne(['_id' => 'key1']);
        $this->collection->insertOne(['_id' => 'key2']);

        $this->assertTrue($this->cache->delete('key1'));

        $actual = $this->collection->find(
            [],
            ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]
        )->toArray();

        $this->assertEquals([['_id' => 'key2']], $actual);
    }

    /**
     * Verify behavior of delete() when mongo exception is thrown.
     *
     * @test
     * @covers ::delete
     *
     * @return void
     */
    public function deleteMongoException()
    {
        $mockCollection = $this->getFailingCollectionMock('deleteOne');
        $cache = new MongoCache($mockCollection, $this->getSerializer());
        $this->assertFalse($cache->delete('key'));
    }

    /**
     * Verify basic behavior of clear().
     *
     * @test
     * @covers ::clear
     *
     * @return void
     */
    public function clear()
    {
        $this->collection->insertOne(['_id' => 'key1']);
        $this->collection->insertOne(['_id' => 'key2']);

        $this->assertTrue($this->cache->clear());

        $actual = $this->collection->find(
            [],
            ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]
        )->toArray();

        $this->assertSame([], $actual);
    }

    /**
     * Verify behavior of clear() when mongo exception is thrown.
     *
     * @test
     * @covers ::clear
     *
     * @return void
     */
    public function clearMongoException()
    {
        $mockCollection = $this->getFailingCollectionMock('deleteMany');
        $cache = new MongoCache($mockCollection, $this->getSerializer());
        $this->assertFalse($cache->clear());
    }

    /**
     * Verify basic behavior of getMultiple
     *
     * @test
     * @covers ::getMultiple
     *
     * @return void
     */
    public function getMultiple()
    {
        $this->collection->insertOne(
            [
                '_id' => 'key1',
                'data' => [
                    'timestamp' => 1491782286,
                    'timezone' => 'America/New_York',
                ],
                'expires' => new UTCDateTime(strtotime('+1 day') * 1000),
            ]
        );
        $this->collection->insertOne(
            [
                '_id' => 'key3',
                'data' => [
                    'timestamp' => 1491807244,
                    'timezone' => 'Pacific/Honolulu',
                ],
                'expires' => new UTCDateTime(strtotime('+1 day') * 1000),
            ]
        );

        $default = new \StdClass();

        $dates = $this->cache->getMultiple(['key1', 'key2', 'key3', 'key4'], $default);

        $this->assertCount(4, $dates);

        $this->assertSame('2017-04-09 23:58:06', $dates['key1']->format('Y-m-d H:i:s'));
        $this->assertSame($default, $dates['key2']);
        $this->assertSame('2017-04-10 06:54:04', $dates['key3']->format('Y-m-d H:i:s'));
        $this->assertSame($default, $dates['key4']);
    }

    /**
     * Verify basic behavior of setMultiple().
     *
     * @test
     * @covers ::setMultiple
     *
     * @return void
     */
    public function setMultple()
    {
        $dates = [
            'key1' => new DateTime(),
            'key2' => new DateTime(),
        ];

        $this->assertTrue($this->cache->setMultiple($dates, 86400));
        $expires = new UTCDateTime((time() + 86400) * 1000);
        $this->assertDateTimeDocument('key1', $expires, $dates['key1']);
        $this->assertDateTimeDocument('key2', $expires, $dates['key2']);
    }

    /**
     * Verify behavior of setMultiple() when mongo throws an exception.
     *
     * @test
     * @covers ::setMultiple
     *
     * @return void
     */
    public function setMultpleMongoException()
    {
        $mockCollection = $this->getFailingCollectionMock('updateOne');
        $cache = new MongoCache($mockCollection, $this->getSerializer());
        $responses = ['key1' => new DateTime(), 'key2' => new DateTime()];
        $this->assertFalse($cache->setMultiple($responses, 86400));
    }

    /**
     * Verify basic behavior of deleteMultiple().
     *
     * @test
     * @covers ::deleteMultiple
     *
     * @return void
     */
    public function deleteMultiple()
    {
        $this->collection->insertOne(['_id' => 'key1']);
        $this->collection->insertOne(['_id' => 'key2']);
        $this->collection->insertOne(['_id' => 'key3']);

        $this->assertTrue($this->cache->deleteMultiple(['key1', 'key3']));

        $actual = $this->collection->find(
            [],
            ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]
        )->toArray();

        $this->assertEquals([['_id' => 'key2']], $actual);
    }

    /**
     * Verify behavior of deleteMultiple() when mongo throws an exception.
     *
     * @test
     * @covers ::deleteMultiple
     *
     * @return void
     */
    public function deleteMultipleMongoException()
    {
        $mockCollection = $this->getFailingCollectionMock('deleteMany');
        $cache = new MongoCache($mockCollection, $this->getSerializer());
        $this->assertFalse($cache->deleteMultiple(['key1', 'key3']));
    }

    /**
     * Verify basic behavior of has().
     *
     * @test
     * @covers ::has
     *
     * @return void
     */
    public function has()
    {
        $this->collection->insertOne(['_id' => 'key1']);
        $this->assertTrue($this->cache->has('key1'));
        $this->assertFalse($this->cache->has('key2'));
    }

    /**
     * Helper method to assert the contents of a mongo document.
     *
     * @param string      $key      The _id value to assert.
     * @param UTCDateTime $expires  The expected expires value.
     * @param DateTime    $expected The expected DateTime value.
     *
     * @return void
     */
    private function assertDateTimeDocument(string $key, UTCDateTime $expires, DateTime $expected)
    {
        $actual = $this->collection->findOne(
            ['_id' => $key],
            ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]
        );

        $this->assertSame($expires->toDateTime()->getTimestamp(), $actual['expires']->toDateTime()->getTimestamp());

        $this->assertSame(
            [
                '_id' => $key,
                'data' => [
                    'timestamp' => $expected->getTimestamp(),
                    'timezone' => $expected->getTimeZone()->getName(),
                ],
                'expires' => $actual['expires'],
            ],
            $actual
        );
    }

    private function getFailingCollectionMock(string $methodToFail) : Collection
    {
        $mock = $this->getMockBuilder(Collection::class)->disableOriginalConstructor()->getMock();
        $mock->method($methodToFail)->will($this->throwException(new \Exception()));
        return $mock;
    }

    /**
     * Helper method to get a SerializerInterface instance.
     *
     * @return SerializerInterface
     */
    private function getSerializer() : SerializerInterface
    {
        return new class implements SerializerInterface
        {
            /**
             * @see SerializerInterface::unserialize().
             *
             * @param mixed $data The serialized data.
             *
             * @return DateTime
             */
            public function unserialize($data)
            {
                return new DateTime("@{$data['timestamp']}", timezone_open($data['timezone']));
            }

            /**
             * @see SerializerInterface::serialize().
             *
             * @param mixed $value The data to serialize.
             *
             * @return array
             */
            public function serialize($value)
            {
                return [
                    'timestamp' => $value->getTimestamp(),
                    'timezone' => $value->getTimezone()->getName(),
                ];
            }
        };
    }
}
