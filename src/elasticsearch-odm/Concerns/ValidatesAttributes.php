<?php

namespace Galexth\ElasticsearchOdm\Concerns;


trait ValidatesAttributes
{
    /**
     * @var \Illuminate\Support\MessageBag
     */
    protected $validationErrors;

    /**
     * @return array
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * @param array $attributes
     */
    public function validate(array $attributes)
    {
        //
    }

    /**
     * @return \Illuminate\Support\MessageBag
     */
    public function getValidationErrors(): \Illuminate\Support\MessageBag
    {
        return $this->validationErrors;
    }
}
