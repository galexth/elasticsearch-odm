Elastic ODM

```php
$doc = Document::setQuery(
    (new BoolQuery())->addFilter(new Term(['field' => $field]))
        ->addFilter(new Term(['field2' => $field2]))
)->firstOrFail();

$doc->delete();
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

