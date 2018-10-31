<?php

namespace Galexth\ElasticsearchOdm;

use Elastica\Client;
use Elastica\Exception\NotFoundException;
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
    protected $client;

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
     * Builder constructor.
     *
     * @param \Galexth\ElasticsearchOdm\Model $model
     * @param \Elastica\Client     $client
     */
    public function __construct(Model $model, Client $client)
    {
        $this->client = $client;
        $this->query = new Query();
        $this->model = $model;
    }

    /**
     * @return $this
     */
    public function withoutSource()
    {
        return $this->setSource(false);
    }

    /**
     * @param array|bool $fields
     * @deprecated
     * @return $this
     */
    public function fields($fields)
    {
        $this->query->setSource($fields);
        return $this;
    }

    /**
     * @param array $fields
     *
     * @return $this
     */
    public function include(array $fields)
    {
        $source = $this->getSourceParam();

        $source['includes'] = $fields;

        $this->query->setSource($source);
        return $this;
    }

    /**
     * @param array $fields
     *
     * @return $this
     */
    public function exclude(array $fields)
    {
        $source = $this->getSourceParam();

        $source['excludes'] = $fields;

        $this->query->setSource($source);
        return $this;
    }

    /**
     * @return mixed|null
     */
    protected function getSourceParam()
    {
        if ($this->query->hasParam('_source')) {
            return $this->query->getParam('_source');
        }

        return null;
    }

    /**
     * @param int $size
     *
     * @return \Galexth\ElasticsearchOdm\Builder
     */
    public function take(int $size)
    {
        return $this->setSize($size);
    }

    /**
     * @param int $offset
     *
     * @return \Galexth\ElasticsearchOdm\Builder
     */
    public function from(int $offset = 0)
    {
        return $this->setFrom($offset);
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
            $this->setSource($fields);
        }

        $set = $this->getSearchInstance()->search($this->getQuery());

        $models = $this->model::hydrate($set->getResults(), true);

        if ($this->with) {
            $models = $this->loadRelations($models);
        }

        return $this->model->newCollection($models, $set);
    }

    /**
     * @return \Elastica\Type
     */
    protected function getTypeInstance()
    {
        return $this->client->getIndex($this->model->getIndex())
            ->getType($this->model->getType());
    }

    /**
     * @return \Elastica\Search
     */
    protected function getSearchInstance()
    {
        return $this->getTypeInstance()->createSearch();
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
     * @return bool
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
     * @param string $id
     * @param array  $attributes
     * @param array  $options
     *
     * @return \Elastica\Response
     * @throws \Galexth\ElasticsearchOdm\Exceptions\AccessDenied
     */
    public function create(string $id, array $attributes, array $options = [])
    {
        $this->checkAccess();

        $endpoint = new \Elasticsearch\Endpoints\Create();

        $endpoint->setID($id);
        $endpoint->setBody($attributes);
        $endpoint->setParams($options);

        return $this->getTypeInstance()->requestEndpoint($endpoint);
    }

    /**
     * Perform a model insert operation.
     *
     * @param array $attributes
     * @param array $options
     *
     * @return \Elastica\Response
     * @throws \Galexth\ElasticsearchOdm\Exceptions\AccessDenied
     */
    public function index(array $attributes, array $options = [])
    {
        $this->checkAccess();

        $endpoint = new \Elasticsearch\Endpoints\Index();

        $endpoint->setBody($attributes);
        $endpoint->setParams($options);

        return $this->getTypeInstance()->requestEndpoint($endpoint);
    }

    /**
     * @param string $id
     * @param array  $body
     * @param array  $options
     *
     * @return \Elastica\Response
     * @throws \Galexth\ElasticsearchOdm\Exceptions\AccessDenied
     */
    public function updateById(string $id, array $body, array $options = [])
    {
        $this->checkAccess();

        $response = $this->client->updateDocument($id, $body,
            $this->model->getIndex(), $this->model->getType(), $options
        );

        return $response;
    }

    /**
     * @param array $body
     * @param array $params
     * @param array $slice
     *
     * @return mixed
     * @throws \Galexth\ElasticsearchOdm\Exceptions\AccessDenied
     * @throws \Galexth\ElasticsearchOdm\Exceptions\InvalidException
     */
    public function update(array $body, array $params = [])
    {
        $this->checkAccess();

        if (empty($body['query'])) {
            if (! $this->getQuery()->count()) {
                throw new InvalidException('Query is required.');
            }

            $body['query'] = $this->getQuery()->getQuery()->toArray();
        }

        $endpoint = new UpdateByQuery();
        $endpoint->setBody($body);
        $endpoint->setParams($params);

        $response = $this->getTypeInstance()->requestEndpoint($endpoint);

        return $response->getData()['updated'];
    }

    /**
     * @param string|null $id
     * @param array       $params
     *
     * @return bool
     * @throws \Galexth\ElasticsearchOdm\Exceptions\AccessDenied
     * @throws \Galexth\ElasticsearchOdm\Exceptions\InvalidException
     */
    public function delete(string $id = null, array $params = [])
    {
        $this->checkAccess();

        if ($id) {
            try {
                $response = $this->getTypeInstance()->deleteById($id, $params);
                return (int) ($response->getData()['result'] == 'deleted');
            } catch (NotFoundException $e) {
                return 0;
            }
        } elseif ($this->getQuery()->count()) {
            $response = $this->getTypeInstance()->deleteByQuery($this->getQuery(), $params);
            return $response->getData()['deleted'];
        }

        throw new InvalidException('Query or id are required.');
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
        $endpoint->setParams($options);

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

}