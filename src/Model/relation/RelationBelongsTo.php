<?php

namespace Square1\Laravel\Connect\Model\Relation;

use Illuminate\Database\Eloquent\Model;

/**
 * Description of RelationBelongsTo
 *
 * @author roberto
 */
class RelationBelongsTo extends Relation
{
    /**
     * The local key of the parent model.
     */
    protected string $localKey;

    /**
     * Create a new belongs to relationship instance.
     */
    public function __construct(Model $related, Model $child, protected string $foreignKey, protected string $ownerKey, ?string $relationName)
    {

        parent::__construct($related, $child, $relationName);
    }

    public function toArray(): array
    {
        $array = parent::toArray();

        $array['ownerKey'] = $this->ownerKey;
        $array['foreignKey'] = $this->foreignKey;

        return $array;
    }
}
