<?php

namespace Galexth\ElasticsearchOdm\Support;

use Elastica\QueryBuilder;

/**
 * @mixin QueryBuilder
 */
class QBuilder
{
    /**
     * @param $name
     * @param $arguments
     *
     * @return mixed
     */
    public static function __callStatic($name, $arguments)
    {
        return call_user_func_array([new QueryBuilder(), $name], $arguments);
    }
}