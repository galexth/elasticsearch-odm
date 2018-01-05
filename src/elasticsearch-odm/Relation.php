<?php

namespace Galexth\ElasticsearchOdm;


use Elastica\Query;
use Elastica\Query\Ids;

abstract class Relation
{
    /**
     * @var Model
     */
    protected $related;

    /**
     * @var Model
     */
    protected $parent;

    /**
     * @var Query
     */
    protected $query;

    /**
     * Nested constructor.
     * @param string $className
     * @param Model $parent
     * @param string $foreignKey
     * @param string $localKey
     */
    public function __construct($className, Model $parent)
    {
        $this->parent = $parent;
        $this->related = new $className;
        $this->query = $this->related->newBuilder();
    }

    /**
     * @param array $models
     * @param string $relation
     * @return Collection
     */
    abstract public function getEager(array $models, string $relation): Collection;

    /**
     * @param array $models
     * @param Collection $results
     * @return array
     */
    abstract public function match(array $models, Collection $results, string $relation): array;

    /**
     * @return Query|Ids
     */
    public function getQuery()
    {
        return $this->query;
    }
}