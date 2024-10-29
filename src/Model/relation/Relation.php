<?php

namespace Square1\Laravel\Connect\Model\Relation;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Database\Eloquent\Model;

class Relation implements Arrayable, Jsonable
{
    /**
     * Create a new relation instance.
     */
    public function __construct(protected Model $related, protected Model $parent, protected ?string $relationName) {}

    /**
     * indicates if this relation points to one or more related model instances
     */
    public function relatesToMany(): bool
    {
        return false;
    }

    /**
     * @throws \JsonException
     */
    public function toJson($options = 0): false|string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR | $options);
    }

    /**
     * Get the instance as an array.
     */
    public function toArray(): array
    {
        $array = [];
        $array['name'] = $this->relationName;
        $array['type'] = $this->get_class_name($this);
        $array['parent'] = get_class($this->parent);
        $array['related'] = get_class($this->related);
        $array['many'] = $this->relatesToMany();

        return $array;
    }

    public function __call($method, $parameters)
    {
        return $this;
    }

    public function get_class_name($object = null): false|string
    {
        if (! is_object($object) && ! is_string($object)) {
            return false;
        }

        $class = explode('\\', (is_string($object) ? $object : get_class($object)));

        return $class[count($class) - 1];
    }
}
