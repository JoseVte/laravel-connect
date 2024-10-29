<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Square1\Laravel\Connect\Model;

use Illuminate\Support\Fluent;

class RelationAttribute
{
    public const TYPE_HAS_MANY = 'hasMany';

    public const TYPE_MORPH_MANY = 'morphMany';

    public const TYPE_BELONGS_TO = 'belongsTo';

    public const TYPE_HAS_ONE = 'hasOne';

    public const TYPE_BELONGS_TO_MANY = 'belongsToMany';

    public string $foreignKey;

    public string $localKey;

    private ?Fluent $fluent = null;

    public function __construct(public string $name, public string $type, public string $model)
    {
        $this->localKey = 'unset';
        $this->foreignKey = 'unset';
    }

    public function __toString()
    {
        return "rel:$this->name:$this->type:$this->model:$this->foreignKey-$this->localKey";
    }
}
