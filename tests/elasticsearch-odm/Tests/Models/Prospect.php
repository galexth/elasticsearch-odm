<?php

namespace Galexth\ElasticsearchOdm\Tests\Models;

use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Galexth\ElasticsearchOdm\Builder;
use Galexth\ElasticsearchOdm\Model;
use Galexth\ElasticsearchOdm\Relations\Children;

class Prospect extends Model
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
     * @return string
     */
    public function getIndex(): string
    {
        return 'salestools_prospector_test_2';
    }

    /**
     * @return Children
     */
    public function getMessagesRelation()
    {
        return new Children(Email::class, $this);
    }

    /**
     * @param Builder $query
     */
    public function scopeAllowed($query)
    {
        $query->setSize(1);
    }

    /**
     * @param $value
     *
     * @return string
     */
    public function getFullNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    public static function boot()
    {
        parent::boot();

        parent::saving(function (self $model) {

        });
    }

    public function rules(): array
    {
        return [
            'domain' => ['nullable', 'url'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'stage' => ['nullable', 'string'],

            'social_profiles' => ['array'],
            'social_profiles.*.url' => ['required_with:social_profiles','url'],
            'social_profiles.*.image_url' => ['required_with:social_profiles','url'],
            'social_profiles.*.network_type' => ['required_with:social_profiles', 'integer'],

            'tags' => ['array'],
            'tags.*.id' => ['required_with:tags','integer'],
            'tags.*.name' => ['required_with:tags','string'],
            'tags.*.color' => ['present', 'nullable', 'string'],

            'locations' => ['array'],
            'locations.*.google_place_id' => ['required_with:locations', 'string'],
            'locations.*.street' => ['required_with:locations', 'string'],
            'locations.*.street_number' => ['required_with:locations', 'string'],
            'locations.*.office_number' => ['required_with:locations', 'string'],
            'locations.*.postal_code' => ['required_with:locations', 'string'],
            'locations.*.country' => ['required_with:locations', 'string'],
            'locations.*.city' => ['required_with:locations', 'string'],
            'locations.*.state' => ['required_with:locations', 'string'],
            'locations.*.address_string' => ['required_with:locations', 'string'],

            'emails' => ['array'],
            'emails.*.email' => ['required_with:emails','email'],
            'emails.*.is_verified' => ['required_with:emails','boolean'],
            'emails.*.company_id' => ['present', 'nullable', 'integer'],

            'phones' => ['array'],
            'phones.*.phone' => ['required_with:phones','string'],
        ];
    }

//    public function validate()
//    {
//        $validator = new Validator(new Translator(new ArrayLoader(), 'en'), $this->getDirty(), $this->rules());
//        $validator->valid();
//        d($validator->errors());
//    }

}