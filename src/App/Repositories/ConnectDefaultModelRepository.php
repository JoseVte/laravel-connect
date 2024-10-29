<?php

namespace Square1\Laravel\Connect\App\Repositories;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Square1\Laravel\Connect\App\Filters\Filter;
use Square1\Laravel\Connect\ConnectUtils;
use Square1\Laravel\Connect\Traits\ConnectModelTrait;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ConnectDefaultModelRepository implements ConnectRepository
{
    /**
     * @var Model|ConnectModelTrait
     */
    protected Model $model;

    public function __construct(string $model)
    {
        $this->model = new $model;
    }

    /**
     * {@inheritDoc}
     */
    public function index(array $with, int $perPage, array $filter, array $sortBy): LengthAwarePaginator
    {
        $query = $this->model;
        if (in_array(ConnectModelTrait::class, class_uses_recursive($this->model), true)) {
            $query = $this->model->filter(Filter::buildFromArray($this->model, $filter));
        }

        if (!empty($sortBy)) {
            foreach ($sortBy as $field => $direction) {
                $query->orderBy($field, $direction);
            }
        }

        return $query->with($with)
            ->paginate($perPage);
    }

    /**
     * {@inheritDoc}
     */
    public function indexRelation(int $parentId, string $relationName, array $with, int $perPage, array $filter, array $sortBy)
    {
        $model = $this->model;

        $relation = ConnectUtils::validateRelation($model, $relationName);

        if (! $relation) {
            return [];
        }

        $model = $model::find($parentId);

        $relation = $model->$relationName();
        $relatedModel = $relation->getRelated();

        //that 1 relation doesn't need to be paginated
        if ($relation instanceof HasOne || $relation instanceof BelongsTo) {
            return $relation
                ->with($with)
                ->first();
        }
        if (in_array(ConnectModelTrait::class, class_uses_recursive($this->model), true)) {
            /** @var ConnectModelTrait|Model $relation */
            $relation = $relation->filter(Filter::buildFromArray($relatedModel, $filter));
        }

        if (!empty($sortBy)) {
            foreach ($sortBy as $field => $direction) {
                $relation->orderBy($field, $direction);
            }
        }

        return $relation->with($with)
            ->paginate($perPage);
    }

    public function show($id, $with = [])
    {
        return $this->model
            ->with($with)
            ->where('id', $id)
            ->first();
    }

    public function showRelation($parentId, $relationName, $relId, $with)
    {
        $model = $this->model;

        $relation = ConnectUtils::validateRelation($model, $relationName);

        if (! $relation) {
            return null;
        }

        $model = $model::find($parentId);

        return $model->$relationName()->with($with)->where('id', $relId)->first();
    }

    /**
     * {@inheritDoc}
     */
    public function create(array $params): Model
    {

        foreach ($params as $param => $value) {
            if ($value instanceof UploadedFile) {
                $params[$param] = $this->storeUploadedFile($value);
            }
        }

        return $this->model->create($params);
    }

    /**
     * {@inheritDoc}
     */
    public function update(int $id, array $params): Model
    {
        $model = $this->model->where('id', $id)->get()->first();

        foreach ($params as $param => $value) {
            if ($value instanceof UploadedFile) {
                $params[$param] = $this->storeUploadedFile($value);
            }
        }

        $relations = Arr::get($params, 'relations', []);

        $updatedRelations = [];

        foreach ($relations as $relation => $data) {
            $relationAdd = Arr::get($data, 'add', []);
            $relationRemove = Arr::get($data, 'remove', []);

            if (ConnectUtils::updateRelationOnModel($model, $relation, $relationAdd, $relationRemove)) {
                $updatedRelations[] = $relation;

            }
        }

        //remove relation values before assigning to model as those are not part of the fillable values
        unset($params['relations']);

        $model->forceFill($params);
        $model->push();

        return $this->show($id, $updatedRelations);
    }

    public function updateRelation($parentId, $relationName, $relationData)
    {
        $model = $this->model;

        $relation = ConnectUtils::validateRelation($model, $relationName);

        if (! $relation) {
            return null;
        }

        $model = $model::find($parentId);
        $relation = $model->$relationName();
        $relatedModel = $relation->getRelated();
        $related = 0;

        if (is_array($relationData)) {
            //need to create a new instance of the related
            $repository = ConnectUtils::repositoryInstanceForModelPath($relatedModel->endpointReference());
            $related = $repository->create($relationData);
        } elseif ($relationData instanceof Model) {
            $related = $relationData;
        } else {
            $related = $relatedModel::find($relationData);
        }

        if ($relation instanceof HasOne) {
            $relation->associate($related);
        } elseif ($relation instanceof BelongsToMany) {
            $relation->attach($related);
        } elseif ($relation instanceof HasMany) {
            $relation->save($related);
        }
        //        else if($relation instanceof MorphTo){
        //
        //        }
        //        else if($relation instanceof MorphOne){
        //
        //        }
        //        else if($relation instanceof MorphMany){
        //
        //        }
        //        else if($relation instanceof MorphToMany){
        //
        //        }
        //        else if($relation instanceof HasManyThrough){
        //
        //        }

        $model->save();

        return $related;
    }

    public function deleteRelation($parentId, $relationName, $relId)
    {
        $model = $this->model;

        $relation = ConnectUtils::validateRelation($model, $relationName);

        if (! $relation) {
            return null;
        }

        $model = $model::find($parentId);
        $relation = $model->$relationName();

        if ($relation instanceof HasOne) {
            $relation->dissociate();
        } elseif ($relation instanceof HasMany) {
            $relationModel = $relation->findOrNew($relId);
            $relationModel = $relationModel->find($relId);

            //cant remove this only assign to another one if strict relations
            $relationModel->setAttribute($relation->getForeignKeyName(), 0);
            $relationModel->save();
        }
        //        else if($relation instanceof MorphTo){
        //
        //        }
        //        else if($relation instanceof MorphOne){
        //
        //        }
        //        else if($relation instanceof MorphMany){
        //
        //        }
        //        else if($relation instanceof MorphToMany){
        //
        //        }
        //        else if($relation instanceof HasManyThrough){
        //
        //        }
        elseif ($relation instanceof BelongsTo) {
            $relation->dissociate();
        } elseif ($relation instanceof BelongsToMany) {
            $relation->detach($relId);
        }

        $model->touch();
        $model->save();

        return $model->withRelations()->get()->first();
    }

    /**
     * Deletes a model.
     *
     * @param  int  $id  The model's ID
     */
    public function delete(int $id): bool
    {
        return $this->model->findOrFail($id)->delete();
    }

    /**
     * Restores a previously deleted model.
     *
     * @param  int  $id  The model's ID
     */
    public function restore(int $id): Model
    {
        return $this->model->withTrashed()->findOrFail($id)->restore();
    }

    /**
     * Get a new instance of model.
     */
    public function getNewModel(): Model
    {
        return new $this->model;
    }

    /**
     *  Store uploaded files in the defined storage.
     *  Place then in a subfolder named as the endpoint reference for the model
     *
     * @param  UploadedFile  $file,  the uploaded file
     * @return string an appropriate representation of the location where the file was stored
     */
    public function storeUploadedFile($file): string
    {
        return Storage::putFile($this->model->endpointReference(), $file);
    }
}
