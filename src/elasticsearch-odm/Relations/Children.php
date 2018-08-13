<?php

namespace Galexth\ElasticsearchOdm\Relations;


use Elastica\Query\HasParent;
use Elastica\Query\Terms;
use Galexth\ElasticsearchOdm\Collection;
use Galexth\ElasticsearchOdm\Model;
use Galexth\ElasticsearchOdm\Relation;

class Children extends Relation
{
    /**
     * @param array $models
     * @param string $relation
     * @return Collection
     */
    public function getEager(array $models, string $relation): Collection
    {
        $ids = array_unique(array_map(function (Model $model) {
            return $model->getId();
        }, $models));

        if (! $ids) {
            return new Collection();
        }

        return $this->query->setQuery(new HasParent(new Terms('_id', $ids), $this->related->getType()))
            ->setSize(count($ids))->get();
    }

    /**
     * @param array $models
     * @param Collection $results
     * @return array
     */
    public function match(array $models, Collection $results, string $relation): array
    {
        /** @var Model $model */
        foreach ($models as $model) {

            $relatedModels = $results->filter(function (Model $result) use ($model) {
                return $model->getId() == $result->getParent();
            });

            $model->addRelation($relation, $relatedModels);
        }

        return $models;
    }
}