<?php

namespace Galexth\ElasticsearchOdm\Tests\Models;


use Galexth\ElasticsearchOdm\Model;

class Company extends Model
{
    /**
     * @var string
     */
    protected $type = 'company';

    protected $readOnly = true;

    /**
     * @return string
     */
    public function getIndex(): string
    {
        return 'test_index_2';
    }
}