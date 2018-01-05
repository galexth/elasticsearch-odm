<?php

namespace Galexth\ElasticsearchOdm;


use Elastica\Response;
use Elastica\ResultSet;

class Collection extends \Illuminate\Support\Collection
{
    /**
     * @var array|null
     */
    protected $aggregations = null;

    /**
     * @var int|null
     */
    protected $total = null;

    /**
     * @var \Elastica\Response|null
     */
    private $response = null;

    /**
     * Collection constructor.
     *
     * @param array                    $items
     * @param \Elastica\ResultSet|null $set
     */
    public function __construct($items = [], ResultSet $set = null)
    {
        parent::__construct($items);

        if ($set) {
            $this->aggregations = $set->getAggregations();
            $this->total = $set->getTotalHits();
            $this->response = $set->getResponse();
        }
    }

    /**
     * @return array
     */
    public function getAggregations(): array
    {
        return $this->aggregations;
    }

    /**
     * @return array
     */
    public function getTotal(): int
    {
        return $this->total;
    }

    /**
     * @return \Elastica\Response
     */
    public function getResponse(): ?Response
    {
        return $this->response;
    }

    /**
     * @param  array  $attributes
     * @return $this
     */
    public function append(array $attributes)
    {
        return $this->each(function (Model $model) use ($attributes) {
            $model->append($attributes);
        });
    }

    /**
     * @param  array  $attributes
     * @return $this
     */
    public function makeHidden(array $attributes)
    {
        return $this->each(function (Model $model) use ($attributes) {
            $model->setHidden($attributes);
        });
    }
}