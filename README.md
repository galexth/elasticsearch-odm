Elastic ODM

```php
$query = Person::query()->with([
    'relation1' => function (Query $query) {
        $query->setSource(['field1', 'field2', 'field3']);
    }
]);
```
Get documents collection with filtered _source

```php
$document = $query->get(['field1']);
```

Get single document 

```php
$document = $query->first();
```

```php
$document->setHidden(['field1', 'field2']);
```

```php
$document->toArray();
```

