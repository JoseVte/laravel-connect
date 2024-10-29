<?php
/**
 *  Filter
 *
 * @author roberto
 */

namespace Square1\Laravel\Connect\App\Filters;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Schema;

class CriteriaCollection implements Arrayable
{
    private array $criteria;

    private array $relationCriteria;

    public function __construct()
    {
        $this->criteria = [];
        $this->relationCriteria = [];
    }

    public function addCriteria(Criteria $criteria): void
    {
        if ($criteria->onRelation()) {
            if (! isset($this->relationCriteria[$criteria->relation()])) {
                $this->relationCriteria[$criteria->relation()] = [];
            }

            $this->relationCriteria[$criteria->relation()][] = $criteria;
        } else {
            $this->criteria[] = $criteria;
        }
    }

    public function apply($query, $model)
    {
        $table = $model->getTable();

        foreach ($this->criteria as $criteria) {
            if (Schema::hasColumn($table, $criteria->param())) {
                $query = $criteria->apply($query, $table);
            }
        }

        //now loop over the relations

        foreach ($this->relationCriteria as $relation => $criteria) {
            //is this a legitimate relation?
            // To prevent calling any random method on the model,
            // this method ensures that filters are applied only to the model
            // or to an actual relations.
            $relatedModelTable = $model->getRelationTableWithName($relation);

            if (! $relatedModelTable) {
                continue; // ignore this is not a relation on the model.
            }

            // we found a relation on this model
            $query->whereHas(
                $relation,
                function ($q) use ($criteria, $relatedModelTable) {
                    //add all the criteria for this relation
                    foreach ($criteria as $c) {
                        $c->apply($q, $relatedModelTable);
                    }
                }
            );
        }

        return $query;
    }

    public function toArray(): array
    {
        $result = [];

        foreach ($this->criteria as $criteria) {
            if (! isset($result[$criteria->name()])) {
                $result[$criteria->name()] = [];
            }

            if (! isset($result[$criteria->name()][$criteria->verb()])) {
                $result[$criteria->name()][$criteria->verb()] = [];
            }

            $result[$criteria->name()][$criteria->verb()][] = $criteria->value();
        }

        return $result;
    }
}
