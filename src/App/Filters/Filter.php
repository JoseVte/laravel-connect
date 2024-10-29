<?php

/**
 *  Filter
 *
    "filter[0][medias.event_id][equal][0]": eventId,
    "filter[0][medias.event_id][equal][1]": 5,
    "filter[0][id][equal][1]": 52323,

    "filter[1][medias.event_id][equal][0]": 666,
    "filter[1][medias.event_id][equal][1]": 666,
    "filter[1][id][equal][1]": 52323
 *
 * @author roberto
 */

namespace Square1\Laravel\Connect\App\Filters;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;

class Filter implements Arrayable
{
    private array $filters;

    private array $request;

    public function __construct(private readonly Model $model)
    {
        $this->filters = [];
    }

    public function addFilter(CriteriaCollection $filter): void
    {
        $this->filters[] = $filter;
    }

    public function apply($query, $model)
    {
        $first = true;

        foreach ($this->filters as $filter) {
            if ($first) {
                $query->where(
                    function ($q) use ($filter, $model) {
                        $filter->apply($q, $model);
                    }
                );
            } else {// apply the OR clause
                $query->orWhere(
                    function ($q) use ($filter, $model) {
                        $filter->apply($q, $model);
                    }
                );
            }

            $first = false;
        }

        return $query;
    }

    public static function buildFromArray(Model $model, $array = []): Filter
    {
        $filter = new Filter($model);
        $filter->request = $array;

        foreach ($array as $filterData) {//array containing a filter
            $filterCollection = static::buildFilterFromArray($filterData);

            if (isset($filterCollection)) {
                $filter->addFilter($filterCollection);
            }
        }

        return $filter;
    }

    public static function buildFilterFromArray($filterData): ?CriteriaCollection
    {
        if (! is_array($filterData)) {
            return null;
        }

        $filter = new CriteriaCollection;

        foreach ($filterData as $paramName => $criteria) {
            foreach ($criteria as $verb => $value) {
                if (is_array($value)) {
                    foreach ($value as $v) {
                        $newCriteria = new Criteria($paramName, $v, $verb);
                        $filter->addCriteria($newCriteria);
                    }
                } else {
                    $newCriteria = new Criteria($paramName, $value, $verb);
                    $filter->addCriteria($newCriteria);
                }
            }
        }

        return $filter;
    }

    public function toArray(): array
    {
        $result = [];

        foreach ($this->filters as $filter) {
            $result[] = $filter->toArray();
        }

        return ['orig' => $this->request, 'parsed' => $result];
    }
}
