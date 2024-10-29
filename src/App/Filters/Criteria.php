<?php
/**
 *  Criteria
 *
 * @author roberto
 */

namespace Square1\Laravel\Connect\App\Filters;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;

class Criteria
{
    public const CONTAINS = 'contains';

    public const EQUAL = 'equal';

    public const NOTEQUAL = 'notequal';

    public const GREATERTHAN = 'greaterthan';

    public const LOWERTHAN = 'lowerthan';

    public const GREATERTHANOREQUAL = 'greaterthanorequal';

    public const LOWERTHANOREQUAL = 'lowerthanorequal';

    private string $relation;

    private string $param;

    private string $verb;

    public function __construct(private readonly string $name, private readonly mixed $value, $verb)
    {
        $exploded = explode('.', $name);

        if (count($exploded) === 2) {
            $this->relation = $exploded[0];
            $this->param = $exploded[1];
        } else {
            $this->relation = '';
            $this->param = $exploded[0];
        }

        $this->verb = Str::lower($verb);
    }

    public function onRelation(): bool
    {
        return $this->relation !== '';
    }

    public function relation(): string
    {
        return $this->relation;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function param(): string
    {
        return $this->param;
    }

    public function verb(): string
    {
        return $this->verb;
    }

    public function value()
    {
        return $this->value;
    }

    public function apply(Builder|\Illuminate\Database\Eloquent\Builder $query, string $table = ''): Builder|\Illuminate\Database\Eloquent\Builder
    {
        if ($table !== '') {
            $name = $table.'.'.$this->param;
        } else {
            $name = $this->param;
        }

        if ($this->verb === self::CONTAINS) {
            $query->where($name, 'like', '%'.$this->value.'%');
        } elseif ($this->verb === self::EQUAL) {
            $query->where($name, $this->value);
        } elseif ($this->verb === self::NOTEQUAL) {
            $query->where($name, '!=', $this->value);
        } elseif ($this->verb === self::GREATERTHAN) {
            $query->where($name, '>', $this->value);
        } elseif ($this->verb === self::LOWERTHAN) {
            $query->where($name, '<', $this->value);
        } elseif ($this->verb === self::GREATERTHANOREQUAL) {
            $query->where($name, '>=', $this->value);
        } elseif ($this->verb === self::LOWERTHANOREQUAL) {
            $query->where($name, '<=', $this->value);
        }

        return $query;
    }
}
