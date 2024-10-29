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
class RelationBelongsToMany extends Relation
{
    /**
     * Create a new belongs to many relationship instance.
     */
    public function __construct(Model $related, Model $parent, protected string $table, protected string $foreignKey, protected string $relatedKey, ?string $relationName = null)
    {

        parent::__construct($related, $parent, $relationName);
    }

    public function toArray(): array
    {
        $array = parent::toArray();

        $array['table'] = $this->table;
        $array['relatedKey'] = $this->relatedKey;
        $array['foreignKey'] = $this->foreignKey;

        return $array;
    }

    public function relatesToMany(): bool
    {
        return true;
    }
}
