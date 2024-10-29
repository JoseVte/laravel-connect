<?php

namespace Square1\Laravel\Connect\Traits;

use Exception;

trait ConnectApiRequestTrait
{
    /**
     * @throws Exception
     */
    public function getAssociatedModel(): void
    {
        throw new Exception('associatedModel is not set for this request');
    }

    /**
     * returning this to true automatically adds
     * the following parameters to the request
     * 'page' => 'integer','per_page' => 'integer'
     */
    public function getIsPaginated(): bool
    {
        return false;
    }

    /**
     * return an array with the parameters for this request
     */
    public function parameters(): array
    {
        $params = [];
        $rules = $this->rules();

        if (isset($this->params)) {
            $params = array_merge($rules, $this->params);
        } else {
            $params = array_merge($rules, $params);
        }

        return $params;
    }
}
