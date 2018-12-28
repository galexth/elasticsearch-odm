<?php

namespace Galexth\ElasticsearchOdm;

use ArrayAccess;
use DateTimeInterface;
use Elastica\Result;
use Elastica\ResultSet;
use Galexth\ElasticsearchOdm\Concerns\HasTimestamps;
use Galexth\ElasticsearchOdm\Concerns\ValidatesAttributes;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as BaseCollection;
use JsonSerializable;
use Galexth\ElasticsearchOdm\Concerns\GuardsAttributes;
use Galexth\ElasticsearchOdm\Concerns\HasEvents;
use Galexth\ElasticsearchOdm\Exceptions\ClientEmptyException;
use Galexth\ElasticsearchOdm\Exceptions\InvalidException;
use Galexth\ElasticsearchOdm\Exceptions\MassAssignmentException;

/**
 * @mixin \Galexth\ElasticsearchOdm\Builder
 */
abstract class Model implements ArrayAccess, Arrayable, Jsonable, JsonSerializable
{
    use HasEvents;
    use HasTimestamps;
    use GuardsAttributes;
    use ValidatesAttributes;

    /**
     * @var \Elastica\Client
     */
    protected static $client;

    /**
     * @var string
     */
    protected $type;

    /**
     * @var array
     */
    protected $attributes = [];

    /**
     * @var array
     */
    protected $original = [];

    /**
     * @var bool
     */
    public $exists = false;

    /**
     * @var bool
     */
    public $wasRecentlyCreated = false;

    /**
     * @var bool
     */
    protected $mergeRelations = false;

    /**
     * The name of the "created at" column.
     *
     * @var string
     */
    const CREATED_AT = 'created_at';

    /**
     * The name of the "updated at" column.
     *
     * @var string
     */
    const UPDATED_AT = 'updated_at';

    /**
     * @var string
     */
    protected $_id;

    /**
     * @var string
     */
    protected $_parent;

    /**
     * @var string
     */
    protected $_routing;

    /**
     * @var float
     */
    protected $_score;

    /**
     * @var int|null
     */
    protected $_version = null;

    /**
     * @var \Illuminate\Support\Collection
     */
    protected $_innerHits;

    /**
     * @var array
     */
    protected $hidden = [];

    /**
     * @var array
     */
    protected $casts = [];

    /**
     * @var array
     */
    protected $relations = [];

    /**
     * @var array
     */
    protected $appends = [];

    /**
     * @var bool
     */
    protected $readOnly = false;

    /**
     * The array of booted models.
     *
     * @var array
     */
    protected static $booted = [];

    /**
     * Model constructor.
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->bootIfNotBooted();
        $this->syncOriginal();
        $this->fill($attributes);
    }

    /**
     * The "booting" method of the model.
     *
     * @return void
     */
    protected static function boot()
    {
        //
    }

    /**
     * Check if the model needs to be booted and if so, do it.
     *
     * @return void
     */
    protected function bootIfNotBooted()
    {
        if (! isset(static::$booted[static::class])) {
            static::$booted[static::class] = true;

            $this->fireModelEvent('booting');

            static::boot();

            $this->fireModelEvent('booted');
        }
    }

    /**
     * @return string
     */
    public function getId(): ?string
    {
        return $this->_id;
    }

    /**
     * @return string
     */
    public function getParent(): ?string
    {
        return $this->_parent;
    }

    /**
     * @return float
     */
    public function getScore(): float
    {
        return $this->_score;
    }

    /**
     * @param array $hidden
     */
    public function setHidden(array $hidden)
    {
        $this->hidden = $hidden;
    }

    /**
     * @param string $relation
     * @param mixed  $models
     *
     * @return \Galexth\ElasticsearchOdm\Model
     */
    public function addRelation(string $relation, $models): Model
    {
        $this->relations[$relation] = $models;
        return $this;
    }

    /**
     * @return array
     */
    public function getRelations(): array
    {
        return $this->relations;
    }

    /**
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    public function getRelation(string $key, $default = null)
    {
        return $this->relations[$key] ?? $default;
    }

    /**
     * @return array
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * Get the model's original attribute values.
     *
     * @param  string|null  $key
     * @param  mixed  $default
     * @return mixed|array
     */
    public function getOriginal($key = null, $default = null)
    {
        return Arr::get($this->original, $key, $default);
    }

    /**
     * Get a subset of the model's attributes.
     *
     * @param  array|mixed  $attributes
     * @return array
     */
    public function only(array $attributes)
    {
        $results = [];

        foreach ($attributes as $attribute) {
            $results[$attribute] = $this->getAttribute($attribute);
        }

        return $results;
    }

    /**
     * @param int $version
     */
    public function setVersion(int $version)
    {
        $this->_version = $version;
    }

    /**
     * @param \Elastica\Client $client
     */
    public static function setClient(\Elastica\Client $client)
    {
        static::$client = $client;
    }

    /**
     * @return Builder
     */
    public static function query()
    {
        return (new static())->newBuilder();
    }

    /**
     * @throws ClientEmptyException
     * @return Builder
     */
    public function newBuilder()
    {
        $client = $this->getClient();

        if (! static::$client) {
            throw new ClientEmptyException('Client is not set.');
        }

        return new Builder($this, $client);
    }

    /**
     * @return \Elastica\Client
     */
    public function getClient()
    {
        return static::$client;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return string
     */
    abstract public function getIndex(): string;

    /**
     * Create a collection of models from plain arrays.
     *
     * @param  array $items
     * @param bool $collect
     * @return Collection
     * @internal param null|string $connection
     */
    public static function hydrate(array $items, bool $collect = false)
    {
        $instance = new static;

        $items = array_map(function ($item) use ($instance) {
            return $instance->newFromBuilder($item);
        }, $items);

        if ($collect) {
            return $items;
        }

        return $instance->newCollection($items);
    }

    /**
     * @param array                             $models
     * @param \Elastica\ResultSet|null $set
     *
     * @return \Galexth\ElasticsearchOdm\Collection
     */
    public function newCollection(array $models = [], ResultSet $set = null)
    {
        return new Collection($models, $set);
    }

    /**
     * Fill the model with an array of attributes.
     *
     * @param  array  $attributes
     * @throws \Exception
     * @return $this
     */
    public function fill(array $attributes)
    {
        $totallyGuarded = $this->totallyGuarded();

        foreach ($this->fillableFromArray($attributes) as $key => $value) {

            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            } elseif ($totallyGuarded) {
                throw new MassAssignmentException($key . ' is not fillable attribute.');
            }
        }

        return $this;
    }

    /**
     * Set the array of model attributes. No checking is done.
     *
     * @param  array  $attributes
     * @param  bool  $sync
     * @return $this
     */
    public function setRawAttributes(array $attributes, $sync = false)
    {
        $this->attributes = $attributes;

        if ($sync) {
            $this->syncOriginal();
        }

        return $this;
    }

    /**
     * Fill the model with an array of attributes. Force mass assignment.
     *
     * @param  array  $attributes
     * @return $this
     */
    public function forceFill(array $attributes)
    {
        return static::unguarded(function () use ($attributes) {
            return $this->fill($attributes);
        });
    }

    /**
     * Save a new model and return the instance.
     *
     * @param  array $attributes
     * @param array  $options
     *
     * @return static
     */
    public static function create(array $attributes = [], array $options = [])
    {
        $model = new static($attributes);

        $model->save(true, $options);

        return $model;
    }

    /**
     * @param array $options
     *
     * @return bool
     * @throws \Galexth\ElasticsearchOdm\Exceptions\AccessDenied
     * @throws \Galexth\ElasticsearchOdm\Exceptions\ClientEmptyException
     * @throws \Galexth\ElasticsearchOdm\Exceptions\InvalidException
     */
    public function delete(array $options = [])
    {
        if (! $this->getId()) {
            throw new InvalidException('No _id defined on this model.');
        }

        if ($this->fireModelEvent('deleting') === false) {
            return false;
        }

        $this->newBuilder()->delete($this->getId(), $options);

        $this->exists = false;

        $this->fireModelEvent('deleted');

        return true;
    }

    /**
     * Create a new instance of the given model.
     *
     * @param  array      $attributes
     * @param  bool       $exists
     * @param string|null $id
     * @throws \Exception
     *
     * @return static
     */
    public function newInstance(array $attributes = [], $exists = false, string $id = null)
    {
        $model = new static($attributes);

        if ($exists && ! $id) {
            throw new InvalidException('_id must be set.');
        }

        $model->exists = $exists;
        $model->_id = $id;

        return $model;
    }

    /**
     * Sync the original attributes with the current.
     *
     * @return $this
     */
    public function syncOriginal()
    {
        $this->original = $this->attributes;

        return $this;
    }

    /**
     * Update the model in the database.
     *
     * @param  array $attributes
     * @param array  $options
     *
     * @return bool
     */
    public function update(array $attributes, array $options = [])
    {
        if (! $this->exists) {
            return false;
        }

        return $this->fill($attributes)->save(true, $options);
    }

    /**
     * @param       $id
     * @param array $attributes
     * @param array $options
     *
     * @return bool
     */
    public static function updateOrCreate($id, array $attributes, array $options = [])
    {
        $instance = new static();

        if ($model = $instance->find($id)) {
            return $model->update($attributes, $options);
        }

        return $instance->newInstance($attributes, false, $id)
            ->save(true, $options);
    }

    /**
     * Save the model to the database.
     *
     * @param bool  $validate
     * @param array $options
     *
     * @return bool
     */
    public function save(bool $validate = true, array $options = [])
    {
        if ($this->fireModelEvent('saving') === false) {
            return false;
        }

        if ($validate) {
            $this->validate();
        }

        $builder = $this->newBuilder();

        if ($this->exists) {
            $saved = $this->performUpdate($builder, $options);
        } else {
            $saved = $this->performInsert($builder, $options);
        }

        if ($saved) {
            $this->fireModelEvent('saved');
            $this->syncOriginal();
        }

        return $saved;
    }

    /**
     * Perform a model update operation.
     *
     * @param \Galexth\ElasticsearchOdm\Builder $query
     * @param array                             $options
     *
     * @return bool
     * @throws \Galexth\ElasticsearchOdm\Exceptions\AccessDenied
     */
    protected function performUpdate(Builder $query, array $options = [])
    {
        if ($this->fireModelEvent('updating') === false) {
            return false;
        }

        $dirty = $this->getDirty();

        if (count($dirty) > 0) {

            if ($this->usesTimestamps()) {
                $this->updateTimestamps();

                $dirty[static::UPDATED_AT] = $this->{static::UPDATED_AT};
            }

            $response = $query->updateById($this->getId(), ['doc' => $dirty], $options);

            if ($response->isOk()) {
                $this->_version = $response->getData()['_version'] ?: null;

                $this->fireModelEvent('updated');

                return true;
            }

            return false;

        }

        return true;
    }

    /**
     * Perform a model insert operation.
     *
     * @param \Galexth\ElasticsearchOdm\Builder $query
     * @param array                             $options
     *
     * @return bool
     * @throws \Galexth\ElasticsearchOdm\Exceptions\AccessDenied
     */
    protected function performInsert(Builder $query, array $options = [])
    {
        if ($this->fireModelEvent('creating') === false) {
            return false;
        }

        if ($this->usesTimestamps()) {
            $this->updateTimestamps();
        }

        $response = $this->_id
            ? $query->create($this->_id, $this->attributes, $options)
            : $query->index($this->attributes, $options);

        if (! $response->isOk()) {
            return false;
        }

        $this->exists = true;
        $this->wasRecentlyCreated = true;

        $this->_id = $response->getData()['_id'];
        $this->_version = $response->getData()['_version'] ?: null;

        $this->fireModelEvent('created');

        return true;
    }

    /**
     * Determine if the model or given attribute(s) have been modified.
     *
     * @param  array|string|null  $attributes
     * @return bool
     */
    public function isDirty($attributes = null)
    {
        $dirty = $this->getDirty();

        if (is_null($attributes)) {
            return count($dirty) > 0;
        }

        if (! is_array($attributes)) {
            $attributes = func_get_args();
        }

        foreach ($attributes as $attribute) {
            if (array_key_exists($attribute, $dirty)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the attributes that have been changed since last sync.
     *
     * @return array
     */
    public function getDirty()
    {
        $dirty = [];

        foreach ($this->attributes as $key => $value) {
            if (! array_key_exists($key, $this->original)) {
                $dirty[$key] = $value;
            } elseif ($value !== $this->original[$key] &&
                ! $this->originalIsNumericallyEquivalent($key)) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    /**
     * Determine if the new and old values for a given key are numerically equivalent.
     *
     * @param  string  $key
     * @return bool
     */
    protected function originalIsNumericallyEquivalent($key)
    {
        $current = $this->attributes[$key];

        $original = $this->original[$key];

        return is_numeric($current) && is_numeric($original) && strcmp((string) $current, (string) $original) === 0;
    }

    /**
     * Convert a DateTime to a storable string.
     *
     * @param  \DateTime|int  $value
     * @return string
     */
    public function fromDateTime($value)
    {
        return empty($value) ? $value : $this->asDateTime($value)->format(
            $this->getDateFormat()
        );
    }

    /**
     * Return a timestamp as DateTime object.
     *
     * @param  mixed  $value
     * @return \Illuminate\Support\Carbon
     */
    protected function asDateTime($value)
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return new Carbon(
                $value->format('Y-m-d H:i:s.u'), $value->getTimezone()
            );
        }

        if (is_numeric($value)) {
            return Carbon::createFromTimestamp($value);
        }

        if ($this->isStandardDateFormat($value)) {
            return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        }

        return Carbon::createFromFormat(
            str_replace('.v', '.u', $this->getDateFormat()), $value
        );
    }

    /**
     * Get the format for database stored dates.
     *
     * @return string
     */
    public function getDateFormat()
    {
        return $this->dateFormat ?: Carbon::DEFAULT_TO_STRING_FORMAT;
    }

    /**
     * Determine if the given value is a standard date format.
     *
     * @param  string  $value
     * @return bool
     */
    protected function isStandardDateFormat($value)
    {
        return preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value);
    }

    /**
     * Create a new model instance that is existing.
     *
     * @param  Result $result
     * @return static
     */
    public function newFromBuilder(Result $result)
    {
        $model = $this->newInstance([], true, $result->getId());

        $model->setRawAttributes($result->getSource(), true);

        $model->_score = $result->getScore();
        $model->_version = $result->getVersion() ?: null;
        $model->_innerHits = $this->buildInnerHits($result);

        return $model;
    }

    /**
     * @param Result $result
     *
     * @return \Illuminate\Support\Collection|static
     */
    protected function buildInnerHits(Result $result)
    {
        if (! $result->hasInnerHits()) {
            return collect([]);
        }

        return collect($result->getInnerHits())->map(function ($hit) {
            return collect(array_map(function ($item) {
                return $item['_source'];
            }, $hit['hits']['hits']));
        });
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
        $query = $this->newBuilder();

        return call_user_func_array([$query, $method], $parameters);
    }

    /**
     * Handle dynamic static method calls into the method.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public static function __callStatic($method, $parameters)
    {
        $instance = new static;

        return call_user_func_array([$instance, $method], $parameters);
    }

    /**
     * Dynamically retrieve attributes on the model.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->getAttribute($key);
    }

    /**
     * Dynamically set attributes on the model.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return void
     */
    public function __set($key, $value)
    {
        $this->setAttribute($key, $value);
    }

    /**
     * Determine if the given relation is loaded.
     *
     * @param  string  $key
     * @return bool
     */
    public function relationLoaded($key)
    {
        return array_key_exists($key, $this->relations);
    }

    /**
     * Get an attribute from the model.
     *
     * @param  string  $key
     * @return mixed
     */
    public function getAttribute($key)
    {
        if (! $key) {
            return null;
        }

        if (array_key_exists($key, $this->attributes) || $this->hasGetMutator($key)) {
            return $this->getAttributeValue($key);
        }

        return $this->getRelationValue($key);
    }

    /**
     * Get a relationship value from a method.
     *
     * @param  string  $relation
     * @return mixed
     *
     * @throws \Exception
     */
    protected function getRelationshipFromMethod(string $relation)
    {
        $method = 'get'. ucfirst($relation) . 'Relation';

        $relations = $this->$method();

        if (! $relations instanceof Relation) {
            throw new \Exception('Relationship method must return an object of type ' . Relation::class);
        }

        $this->addRelation($relation, $results = $relations->getEager([$this], $relation));

        return $results;
    }

    /**
     * Get a relationship.
     *
     * @param  string  $key
     * @return mixed
     */
    public function getRelationValue($key)
    {
        if ($this->relationLoaded($key)) {
            return $this->relations[$key];
        }

        if (method_exists($this, 'get'. ucfirst($key) . 'Relation')) {
            return $this->getRelationshipFromMethod($key);
        }

        return null;
    }

    /**
     * @param string $value
     *
     * @return string
     */
    private function studly(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $value)));
    }

    /**
     * Determine if a get mutator exists for an attribute.
     *
     * @param  string  $key
     * @return bool
     */
    public function hasGetMutator($key)
    {
        return method_exists($this, 'get'.$this->studly($key).'Attribute');
    }

    /**
     * Determine if a set mutator exists for an attribute.
     *
     * @param  string  $key
     * @return bool
     */
    public function hasSetMutator($key)
    {
        return method_exists($this, 'set'.$this->studly($key).'Attribute');
    }

    /**
     * Get the value of an attribute using its mutator.
     *
     * @param  string  $key
     * @return mixed
     */
    protected function mutateAttribute($key)
    {
        return $this->{'get'.$this->studly($key).'Attribute'}();
    }

    /**
     * Get an attribute from the $attributes array.
     *
     * @param  string  $key
     * @return mixed
     */
    protected function getAttributeFromArray($key)
    {
        if (array_key_exists($key, $this->attributes)) {
            return $this->attributes[$key];
        }

        return null;
    }

    /**
     * Determine whether an attribute should be cast to a native type.
     *
     * @param  string  $key
     * @param  array|string|null  $types
     * @return bool
     */
    public function hasCast($key, $types = null)
    {
        if (array_key_exists($key, $this->getCasts())) {
            return $types ? in_array($this->getCastType($key), (array) $types, true) : true;
        }

        return false;
    }

    /**
     * Get the casts array.
     *
     * @return array
     */
    public function getCasts()
    {
        return $this->casts;
    }

    /**
     * Get the type of cast for a model attribute.
     *
     * @param  string  $key
     * @return string
     */
    protected function getCastType($key)
    {
        return trim(strtolower($this->getCasts()[$key]));
    }

    /**
     * Cast an attribute to a native PHP type.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return mixed
     */
    protected function castAttribute($key, $value)
    {
        if (is_null($value)) {
            return $value;
        }

        switch ($this->getCastType($key)) {
            case 'int':
            case 'integer':
                return (int) $value;
            case 'real':
            case 'float':
            case 'double':
                return (float) $value;
            case 'string':
                return (string) $value;
            case 'bool':
            case 'boolean':
                return (bool) $value;
            case 'array':
            case 'json':
                return $this->fromJson($value);
            case 'collection':
                return new BaseCollection(is_array($value) ? $value : $this->fromJson($value));
            default:
                return $value;
        }
    }

    /**
     * Decode the given JSON back into an array or object.
     *
     * @param  string  $value
     * @param  bool  $asObject
     * @return mixed
     */
    public function fromJson($value, $asObject = false)
    {
        return json_decode($value, ! $asObject);
    }

    /**
     * Get a plain attribute (not a relationship).
     *
     * @param  string  $key
     * @return mixed
     */
    public function getAttributeValue($key)
    {
        if ($this->hasGetMutator($key)) {
            return $this->mutateAttribute($key);
        } else {
            $value = $this->getAttributeFromArray($key);
        }

        if ($this->hasCast($key)) {
            return $this->castAttribute($key, $value);
        }

        return $value;
    }

    /**
     * Set a given attribute on the model.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return $this
     */
    public function setAttribute($key, $value)
    {
        if ($this->hasSetMutator($key)) {
            $method = 'set'.$this->studly($key).'Attribute';

            return $this->{$method}($value);
        }

        $this->attributes[$key] = $value;

        return $this;
    }

    /**
     * @param array $appends
     *
     * @return $this
     */
    public function append(array $appends)
    {
        $this->appends = array_merge($this->appends, $appends);

        return $this;
    }

    /**
     * Get an attribute array of all arrayable values.
     *
     * @return array
     */
    protected function getArrayableAttributes(array $values)
    {
        if (count($this->hidden) > 0) {
            $values = array_diff_key($values, array_flip($this->hidden));
        }

        return $this->castAttributes($values);
    }

    /**
     * @param array $values
     * @return array
     */
    protected function castAttributes(array $values)
    {
        foreach ($values as &$value) {

            if (is_array($value)) {
                $value = $this->castAttributes($value);
            }

            $value = $value instanceof Arrayable ? $value->toArray() : $value;
        }

        return $values;
    }

    /**
     * @return array
     */
    protected function attributesToArray()
    {
        $attributes = $this->attributes;

        if ($this->appends) {
            $attributes = array_merge($attributes, $mutatedAttributes = $this->getDynamicAttributes());
        }

        $attributes = $this->getArrayableAttributes($attributes);

        $attributes = $this->addCastAttributesToArray(
            $attributes, $mutatedAttributes ?? []
        );

        return $attributes;
    }

    /**
     * Add the casted attributes to the attributes array.
     *
     * @param  array  $attributes
     * @param  array  $mutatedAttributes
     * @return array
     */
    protected function addCastAttributesToArray(array $attributes, array $mutatedAttributes)
    {
        foreach ($this->getCasts() as $key => $value) {
            if (! array_key_exists($key, $attributes) || in_array($key, $mutatedAttributes)) {
                continue;
            }

            $attributes[$key] = $this->castAttribute($key, $attributes[$key]);
        }

        return $attributes;
    }

    /**
     * Get all of the appendable values that are arrayable.
     *
     * @return array
     */
    protected function getArrayableAppends()
    {
        if (! count($this->appends)) {
            return [];
        }

        return $this->getArrayableItems(
            array_combine($this->appends, $this->appends)
        );
    }

    /**
     * @return array
     */
    protected function getDynamicAttributes()
    {
        $attributes = [];

        foreach ($this->getArrayableAppends() as $append) {
            if ($this->hasGetMutator($append)) {
                $attributes[$append] = $this->mutateAttribute($append);
            }
        }

        return $attributes;
    }

    /**
     * @return array
     */
    public function relationsToArray()
    {
        return array_map(function ($relation) {
            /** @var Arrayable $relation */
            return $relation ? $relation->toArray() : null;
        }, $this->getArrayableRelations());
    }

    /**
     * Get an attribute array of all arrayable values.
     *
     * @param  array  $values
     * @return array
     */
    protected function getArrayableItems(array $values)
    {
        if ($this->hidden) {
            $values = array_diff_key($values, array_flip($this->hidden));
        }

        return $values;
    }

    /**
     * Get an attribute array of all arrayable relations.
     *
     * @return array
     */
    protected function getArrayableRelations()
    {
        return $this->getArrayableItems($this->relations);
    }

    /**
     * Get the instance as an array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->attributesToArray() + [
            'relations' => $this->relationsToArray()
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
     * Determine if the given attribute exists.
     *
     * @param  mixed  $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return ! is_null($this->getAttribute($offset));
    }

    /**
     * Get the value for a given offset.
     *
     * @param  mixed  $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->getAttribute($offset);
    }

    /**
     * Set the value for a given offset.
     *
     * @param  mixed  $offset
     * @param  mixed  $value
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        $this->setAttribute($offset, $value);
    }

    /**
     * Unset the value for a given offset.
     *
     * @param  mixed  $offset
     * @return void
     */
    public function offsetUnset($offset)
    {
        unset($this->attributes[$offset], $this->relations[$offset]);
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function innerHits(): BaseCollection
    {
        return $this->_innerHits;
    }

    /**
     * @return bool
     */
    public function isReadOnly(): bool
    {
        return $this->readOnly;
    }

    /**
     * @param string $id
     */
    public function setId(string $id)
    {
        $this->_id = $id;
    }

    /**
     * @return int|null
     */
    public function version()
    {
        return $this->_version;
    }

}