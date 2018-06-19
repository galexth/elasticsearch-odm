<?php

namespace Galexth\ElasticsearchOdm;

use Elastica\Client;
use Elastica\Query;
use Elastica\Result;
use Elasticsearch\Endpoints\UpdateByQuery;
use Galexth\ElasticsearchOdm\Exceptions\AccessDenied;
use Galexth\ElasticsearchOdm\Exceptions\DocumentNotFoundException;
use Galexth\ElasticsearchOdm\Exceptions\InvalidException;

/**
 * @mixin \Elastica\Query
 */
class Builder
{
    /**
     * @var Client
     */
    protected $connection;

    /**
     * @var Query
     */
    protected $query;

    /**
     * @var Model
     */
    protected $model;

    /**
     * @var array
     */
    protected $with = [];

    /**
     * @var array
     */
    protected $options = [];

    /**
     * Builder constructor.
     *
     * @param \Elastica\Client     $connection
     * @param \Galexth\ElasticsearchOdm\Model $model
     */
    public function __construct(Client $connection, Model $model)
    {
        $this->connection = $connection;
        $this->query = new Query();
        $this->model = $model;
    }

    /**
     * @return $this
     */
    public function withoutSource()
    {
        return $this->withSource(false);
    }

    /**
     * @param bool $with
     * @return $this
     */
    public function withSource($with = true)
    {
        $this->query->setSource($with);
        return $this;
    }

    /**
     * @param array|bool $fields
     * @return $this
     */
    public function fields($fields)
    {
        $this->query->setSource($fields);
        return $this;
    }

    /**
     * @param bool $value
     *
     * @return $this
     */
    public function setRefresh(bool $value = true)
    {
        $this->addOption('refresh', $value);
        return $this;
    }

    /**
     * @return $this
     */
    public function setWaitFor()
    {
        $this->addOption('refresh', 'wait_for');
        return $this;
    }

    /**
     * @param int $value
     *
     * @return $this
     */
    public function retryOnConflict(int $value = 1)
    {
        $this->addOption('retry_on_conflict', $value);
        return $this;
    }

    /**
     * @param string $value
     *
     * @return $this
     */
    public function conflicts(string $value = 'proceed')
    {
        $this->addOption('conflicts', $value);
        return $this;
    }

    /**
     * @param array $with
     * @return $this
     */
    public function with(array $with)
    {
        $this->with = $this->parseWithRelations($with);
        return $this;
    }

    /**
     * Parse a list of relations into individuals.
     *
     * @param  array  $relations
     * @return array
     */
    protected function parseWithRelations(array $relations)
    {
        $results = [];

        foreach ($relations as $name => $constraints) {

            if (is_numeric($name)) {
                $f = function () {};

                list($name, $constraints) = [$constraints, $f];
            }

            $results[$name] = $constraints;
        }

        return $results;
    }

    /**
     * @param array $fields
     * @return Collection
     */
    public function get(array $fields = [])
    {
        if ($fields) {
            $this->fields($fields);
        }

        $set = $this->getSearchInstance()->search($this->getQuery());

        $models = $this->model::hydrate($set->getResults(), true);

        if ($this->with) {
            $models = $this->loadRelations($models);
        }

        return $this->model->newCollection($models, $set);
    }

    /**
     * @return \Elastica\Search
     */
    protected function getSearchInstance()
    {
        return $this->connection->getIndex($this->model->getIndex())
            ->getType($this->model->getType())->createSearch();
    }

    /**
     * @return \Elastica\Type
     */
    protected function getTypeInstance()
    {
        return $this->connection->getIndex($this->model->getIndex())
            ->getType($this->model->getType());
    }

    /**
     * @param string $expiryTime
     *
     * @return Scroll
     */
    public function scroll(string $expiryTime = '30s')
    {
        return new Scroll(
            $this->getTypeInstance()->createSearch($this->getQuery()), $this->model, $expiryTime
        );
    }

    /**
     * @return int
     */
    public function count()
    {
        return (int) $this->getTypeInstance()->count($this->getQuery());
    }

    /**
     * @return int
     */
    public function exists()
    {
        return (bool) $this->count();
    }

    /**
     * @return Collection
     */
    public function ids()
    {
        return $this->setSource(false)->get()->map(function (Model $item) {
            return $item->getId();
        });
    }

    /**
     * @param int $from
     * @param int $size
     * @return OffsetPaginator
     */
    public function paginate(int $from = 0, int $size = 10)
    {
        $this->setFrom($from);
        $this->setSize($size);

        $models = $this->get();

        return new OffsetPaginator($models, $from, $size, $models->getTotal());
    }

    /**
     * Perform a model insert operation.
     *
     * @param array  $attributes
     * @param string $id
     *
     * @return \Elastica\Response
     */
    public function create(array $attributes = [], string $id)
    {
        $this->checkAccess();

        $endpoint = new \Elasticsearch\Endpoints\Create();

        $endpoint->setID($id);
        $endpoint->setBody($attributes);
        $endpoint->setParams($this->options);

        return $this->getTypeInstance()->requestEndpoint($endpoint);
    }

    /**
     * Perform a model insert operation.
     *
     * @param array $attributes
     *
     * @return \Elastica\Response
     */
    public function index(array $attributes = [])
    {
        $this->checkAccess();

        $endpoint = new \Elasticsearch\Endpoints\Index();

        $endpoint->setBody($attributes);
        $endpoint->setParams($this->options);

        return $this->getTypeInstance()->requestEndpoint($endpoint);
    }

    /**
     * @param array  $attributes
     * @param string $id
     *
     * @return \Elastica\Response
     */
    public function updateById(array $attributes = [], string $id)
    {
        $this->checkAccess();

        $response = $this->connection->updateDocument($id, $attributes,
            $this->model->getIndex(),
            $this->model->getType(),
            $this->options
        );

        return $response;
    }

    /**
     * @param array $body
     * @param array $params
     *
     * @return int
     */
    public function updateByQuery(array $body, array $params = [])
    {
        $this->checkAccess();

        $endpoint = new UpdateByQuery();
        $endpoint->setBody($body);
        $endpoint->setParams(array_merge($this->options, $params));

        $response = $this->getTypeInstance()->requestEndpoint($endpoint);

        return $response->getData()['updated'];
    }

    /**
     * @return int
     * @throws \Exception
     */
    public function delete()
    {
        $this->checkAccess();

        if ($id = $this->model->getId()) {

            $response = $this->getTypeInstance()->deleteById($id, $this->options);

            return $response->getData()['result'] == 'deleted';

        } elseif ($this->getQuery()) {

            $response = $this->getTypeInstance()->deleteByQuery($this->getQuery(), $this->options);

            return $response->getData()['deleted'];

        } else {
            throw new InvalidException('Invalid query.');
        }
    }

    /**
     * @param array $fields
     * @return \Galexth\ElasticsearchOdm\Model|static
     */
    public function first(array $fields = [])
    {
        return $this->setSize(1)->get($fields)->first();
    }

    /**
     * @param array $fields
     *
     * @return \Galexth\ElasticsearchOdm\Builder|\Galexth\ElasticsearchOdm\Model
     * @throws \Galexth\ElasticsearchOdm\Exceptions\DocumentNotFoundException
     */
    public function firstOrFail(array $fields = [])
    {
        if (! $model = $this->first($fields)) {
            throw new DocumentNotFoundException('Document not found.');
        }

        return $model;
    }

    /**
     * @param array $fields
     *
     * @return \Galexth\ElasticsearchOdm\Model|static
     */
    public function firstOrNew(array $fields = [])
    {
        if (! $model = $this->first($fields)) {
            $model = $this->model->newInstance();
        }

        return $model;
    }

    /**
     * @param int|string $id
     * @param array      $options
     *
     * @return \Galexth\ElasticsearchOdm\Model|null
     */
    public function find($id, array $options = [])
    {
        $endpoint = new \Elasticsearch\Endpoints\Get();
        $endpoint->setID($id);
        $endpoint->setParams(array_merge($this->options, $options));

        $response = $this->getTypeInstance()->requestEndpoint($endpoint);

        if (! $response->isOk()) {
            return null;
        }

        $model = $this->model->newFromBuilder(new Result($response->getData()));

        if ($this->with) {
            $model = $model->newCollection($this->loadRelations([$model]))->first();
        }

        return $model;
    }

    /**
     * @param $id
     *
     * @return \Galexth\ElasticsearchOdm\Model|null
     * @throws \Galexth\ElasticsearchOdm\Exceptions\DocumentNotFoundException
     */
    public function findOrFail($id)
    {
        if (! $model = $this->find($id)) {
            throw new DocumentNotFoundException('Document not found.');
        }

        return $model;
    }

    /**
     * @param mixed $id
     * @return \Galexth\ElasticsearchOdm\Model|static
     */
    public function findOrNew($id)
    {
        if (! $model = $this->find($id)) {
            $model = $this->model->newInstance();
        }

        return $model;
    }

    /**
     * @param Collection $models
     * @return array
     */
    protected function loadRelations(array $models)
    {
        foreach ($this->with as $name => $constraints) {
            $models = $this->loadRelation($models, $name, $constraints);
        }

        return $models;
    }

    /**
     * @param array $models
     * @param string $name
     * @return array
     */
    protected function loadRelation(array $models, string $name, \Closure $constraints)
    {
        /** @var \Galexth\ElasticsearchOdm\Relation $relation */
        $relation = $this->getRelation($name);

        call_user_func($constraints, $relation->getQuery());

        $results = $relation->getEager($models, $name);

        return $relation->match($models, $results, $name);
    }

    /**
     * @param string $name
     * @return \Galexth\ElasticsearchOdm\Relation
     */
    protected function getRelation(string $name)
    {
        $method = 'get'. ucfirst($name) . 'Relation';

        return $this->model->{$method}();
    }

    /**
     * Apply the given scope on the current builder instance.
     *
     * @param  callable  $scope
     * @param  array  $parameters
     * @return mixed
     */
    protected function callScope(callable $scope, $parameters = [])
    {
        array_unshift($parameters, $this);

        return $scope(...array_values($parameters)) ?: $this;
    }

    /**
     * @throws AccessDenied
     */
    protected function checkAccess(): void
    {
        if ($this->model->isReadOnly()) {
            throw new AccessDenied('Read only access.');
        }
    }

    /**
     * Handle dynamic method calls into the model.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        if (method_exists($this->model, $scope = 'scope'.ucfirst($method))) {
            return $this->callScope([$this->model, $scope], $parameters);
        }

        call_user_func_array([$this->query, $method], $parameters);
        return $this;
    }

    /**
     * Get the model instance being queried.
     *
     * @return \Galexth\ElasticsearchOdm\Model
     */
    public function getModel(): Model
    {
        return $this->model;
    }

    /**
     * @return \Elastica\Query
     */
    public function getQuery(): \Elastica\Query
    {
        return $this->query;
    }

    /**
     * @param string $key
     * @param        $param
     *
     * @return \Galexth\ElasticsearchOdm\Builder
     */
    public function addOption(string $key, $param): self
    {
        $this->options[$key] = $param;
        return $this;
    }

    /**
     * @param array $options
     *
     * @return Builder
     */
    public function setOptions(array $options): Builder
    {
        if ($options) {
            $this->options = $options;
        }

        return $this;
    }

}