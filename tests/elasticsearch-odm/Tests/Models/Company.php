<?php

namespace Galexth\ElasticsearchOdm\Tests\Models;


class Company extends BaseModel
{
    /**
     * @var string
     */
    protected $type = 'company';

    protected $readOnly = true;

}