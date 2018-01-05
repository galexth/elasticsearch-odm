<?php

namespace Galexth\ElasticsearchOdm\Tests;

use Elastica\Client;
use Galexth\ElasticsearchOdm\Model;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    public static function setUpBeforeClass()
    {
        Model::setClient(new Client());

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
