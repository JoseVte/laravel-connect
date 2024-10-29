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
class RelationHasManyThrough extends Relation
{
    public function __construct(Model $related, protected Model $farParent, protected Model $throughParent, protected string $firstKey, protected string $secondKey, protected string $localKey, ?string $relationName)
    {

        parent::__construct($related, $throughParent, $relationName);
    }

    public function relatesToMany(): bool
    {
        return true;
    }

    public function toArray(): array
    {
        $array = parent::toArray();

        $array['localKey'] = $this->localKey;
        $array['firstKey'] = $this->firstKey;
        $array['secondKey'] = $this->secondKey;
        $array['farParent'] = $this->farParent;
        $array['throughParent'] = $this->throughParent;

        return $array;
    }
}
