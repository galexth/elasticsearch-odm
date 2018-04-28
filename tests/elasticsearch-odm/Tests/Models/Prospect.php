<?php

namespace Galexth\ElasticsearchOdm\Tests\Models;

use Galexth\ElasticsearchOdm\Builder;

class Prospect extends BaseModel
{
    /**
     * @var string
     */
    protected $type = 'prospect';
    protected $appends = ['full_name'];

    protected $fillable = ['first_name', 'name', 'emails', 'industry'];
    protected $guarded = ['industry'];

    /**
     * @var array
     */
    protected $casts = [
        'companies' => 'collection',
        'sequences' => 'collection',
        'emails' => 'collection',
        'phones' => 'collection',
        'social_profiles' => 'collection',
        'tags' => 'collection',
        'locations' => 'collection',
        'search_emails' => 'collection',
        'integration_exports' => 'collection',
    ];

    /**
     * @param Builder $query
     */
    public function scopeAllowed($query)
    {
        $query->setSize(1);
    }

    /**
     * @return string
     */
    public function getFullNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }

}