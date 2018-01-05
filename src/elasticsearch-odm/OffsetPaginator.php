<?php

namespace Galexth\ElasticsearchOdm;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use JsonSerializable;

class OffsetPaginator implements Arrayable, ArrayAccess, JsonSerializable, Jsonable
{
    /**
     * All of the items being paginated.
     *
     * @var \Galexth\ElasticsearchOdm\Collection
     */
    protected $items;

    /**
     * The number of items to be skipped.
     *
     * @var int
     */
    protected $offset;

    /**
     * The number of items to be shown per page.
     *
     * @var int
     */
    protected $limit;

    /**
     * The number of items to be shown per page.
     *
     * @var int
     */
    protected $total;

    /**
     * The number of items to be shown per page.
     *
     * @var array
     */
    protected $aggs;

    /**
     * OffsetPaginator constructor.
     * @param array|\Galexth\ElasticsearchOdm\Collection $items
     * @param int $offset
     * @param int $limit
     * @param int $total
     */
    public function __construct($items, int $offset, int $limit, int $total)
    {
        $this->limit = intval($limit);
        $this->offset = intval($offset);
        $this->total = intval($total);

        $this->items = is_array($items) ? new Collection($items) : $items;

        $this->aggs = $items->getAggregations();
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'limit' => $this->limit,
            'offset' => $this->offset,
            'total' => $this->total,
            'data' => $this->items->toArray(),
            'aggs' => $this->aggs,
        ];
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * Convert the object to its JSON representation.
     *
     * @param  int  $options
     * @return string
     */
    public function toJson($options = 0)
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Determine if the given item exists.
     *
     * @param  mixed  $key
     * @return bool
     */
    public function offsetExists($key)
    {
        return $this->items->has($key);
    }

    /**
     * Get the item at the given offset.
     *
     * @param  mixed  $key
     * @return mixed
     */
    public function offsetGet($key)
    {
        return $this->items->get($key);
    }

    /**
     * Set the item at the given offset.
     *
     * @param  mixed  $key
     * @param  mixed  $value
     * @return void
     */
    public function offsetSet($key, $value)
    {
        $this->items->put($key, $value);
    }

    /**
     * Unset the item at the given key.
     *
     * @param  mixed  $key
     * @return void
     */
    public function offsetUnset($key)
    {
        $this->items->forget($key);
    }

    /**
     * Get the paginator's underlying collection.
     *
     * @return \Galexth\ElasticsearchOdm\Collection
     */
    public function getCollection()
    {
        return $this->items;
    }

    /**
     * @return int
     */
    public function getTotal(): int
    {
        return $this->total;
    }
}
