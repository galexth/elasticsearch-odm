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
     * @return void
     */
    public function validate()
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
