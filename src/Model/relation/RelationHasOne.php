<?php

namespace Square1\Laravel\Connect\Model\Relation;

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

use Illuminate\Database\Eloquent\Model;

/**
 * Description of RelationHasOne
 *
 * @author roberto
 */
class RelationHasOne extends Relation
{
    public function __construct(Model $related, Model $parent, protected string $foreignKey, protected string $localKey, ?string $relationName)
    {
        parent::__construct($related, $parent, $relationName);
    }

    public function toArray(): array
    {
        $array = parent::toArray();

        $array['localKey'] = $this->localKey;
        $array['foreignKey'] = $this->foreignKey;

        return $array;
    }
}
