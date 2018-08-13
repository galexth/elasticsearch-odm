<?php

namespace Galexth\ElasticsearchOdm\Relations;


use Galexth\ElasticsearchOdm\Collection;
use Galexth\ElasticsearchOdm\Model;
use Galexth\ElasticsearchOdm\Relation;
use Elastica\Query\Ids;

class Arrayed extends Relation
{
    /**
     * @var string
     */
    protected $foreignKey;

    /**
     * @var string
     */
    protected $localKey;

    /**
     * Nested constructor.
     * @param string $className
     * @param Model $parent
     * @param string $foreignKey
     * @param string $localKey
     */
    public function __construct($className, Model $parent, string $foreignKey, string $localKey)
    {
        parent::__construct($className, $parent);

        $this->foreignKey = $foreignKey;
        $this->localKey = $localKey;
    }

    /**
     * @param array $models
     * @param string $relation
     * @return Collection
     */
    public function getEager(array $models, string $relation): Collection
    {
        $ids = array_filter(array_values(array_unique(array_collapse(array_map(function ($model) use ($relation) {
            return $model->{$relation};
        }, $models)))));

        if (! $ids) {
            return new Collection();
        }

        return $this->query->setQuery(new Ids($ids))->setSize(count($ids))->get();
    }

    /**
     * @param array $models
     * @param Collection $results
     * @return array
     */
    public function match(array $models, Collection $results, string $relation): array
    {
        $localKey = $this->localKey;

        /** @var Model $model */
        foreach ($models as $model) {
            $relatedModels = $results->filter(function (Model $item) use ($model, $localKey, $relation) {
                return in_array($localKey == 'id' ? $item->getId() : $localKey, (array) $model->{$relation});
            })->values();

            $model->addRelation($relation, $relatedModels);
        }

        return $models;
    }
}