<?php

namespace Galexth\ElasticsearchOdm\Tests;

use Elastica\Client;
use Galexth\ElasticsearchOdm\Model;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Client
     */
    protected $client;

    public static function setUpBeforeClass()
    {
        $client = new Client();

        Model::setClient($client);

        \Kint::$max_depth = 10;
    }

    /**
     * @param array  $keys
     * @param array  $array
     * @param string $message
     */
    public function assertArrayHasKeys(array $keys, array $array, string $message = '')
    {
        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $array, $message);
        }
    }

}
