<?php

namespace Square1\Laravel\Connect\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Str;
use Square1\Laravel\Connect\App\Filters\Filter;

class InternalConnectScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $model->restrictModelAccessInternal($builder, $model);
    }
}

trait ConnectModelTrait
{
    public static bool $modelRestrictionEnabled = true;

    /**
     * Boot the global scope  trait for a model.
     */
    public static function bootConnectModelTrait(): void
    {
        static::$modelRestrictionEnabled = true;
        static::addGlobalScope(new InternalConnectScope);
    }

    /**
     * Get a type hint for the given attribute .
     *
     * @param  string  $name  the name of the attribute
     */
    public function getTypeHint(string $name): ?string
    {
        return $this->hint[$name] ?? null;
    }

    /**
     * Undocumented function
     */
    public function endpointReference(): string
    {
        return Str::snake(class_basename(get_class($this)), '_');
    }

    /**
     * Override in each model to control access to model
     */
    public function restrictModelAccessInternal(Builder $builder, Model $model): void
    {
        if (static::$modelRestrictionEnabled) {
            $this->restrictModelAccess($builder, $model);
        }
    }

    /**
     * Override in each model to control access to model
     */
    public function restrictModelAccess(Builder $builder, Model $model): void {}

    public static function disableModelAccessRestrictions(): void
    {
        static::$modelRestrictionEnabled = false;
    }

    public static function enableModelAccessRestrictions(): void
    {
        static::$modelRestrictionEnabled = true;
    }

    /**
     * Undocumented function
     *
     * @param  string  $parent
     * @return mixed
     */
    public function withRelations($parent = null)
    {
        $with_array = $this->with_relations ?? [];

        if (! empty($parent)) {
            $callback = static function ($value) use ($parent) {
                return $parent.'.'.$value;
            };

            $with_array = array_map($callback, $with_array);
        }

        return $this::with($with_array);
    }

    /**
     * Undocumented function
     */
    public function scopeOrder(Builder $query, array $sortBy): Builder
    {
        foreach ($sortBy as $paramName => $sort) {
            $query->orderBy($paramName, $sort);
        }

        return $query;
    }

    /**
     * Scope a query to filter based on the filter array received.
     */
    public function scopeFilter(Builder $query, ?Filter $filter = null): Builder
    {
        if (isset($filter)) {
            return $filter->apply($query, $this);
        }

        return $query;
    }

    /**
     * Return a Relation given a name , false if the name doesn't match any defined
     * relation
     */
    public function getRelationWithName(string &$relationName): Relation|false
    {
        //if ($this::class->snakeAttributes == true) {
        $relationName = Str::camel($relationName);
        // }

        if (! method_exists($this, $relationName)) {
            return false;
        }

        $relation = $this->$relationName();

        if ($relation instanceof Relation) {
            return $relation;
        }

        return false;
    }

    public function getRelationTableWithName(&$relationName): false|string
    {
        $relation = $this->getRelationWithName($relationName);

        if ($relation instanceof Relation) {
            return $relation->getRelated()->getTable();
        }

        return false;
    }
}
