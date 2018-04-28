<?php

namespace Galexth\ElasticsearchOdm\Tests\Models;


use Galexth\ElasticsearchOdm\Model;

class BaseModel extends Model
{
    public function getIndex(): string
    {
        return 'test_12';
    }
}