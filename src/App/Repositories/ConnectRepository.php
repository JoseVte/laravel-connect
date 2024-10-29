<?php

namespace Square1\Laravel\Connect\App\Repositories;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

interface ConnectRepository
{
    /**
     * Get a paginated list of all the instances of the current model.
     *
     * @param  array  $with  Eager load models
     * @param  int  $perPage  set the number of elements per page
     * @param  array  $filter  the array representation of a Filter object
     * @param  array  $sortBy  a list of sorting preferences
     */
    public function index(array $with, int $perPage, array $filter, array $sortBy): LengthAwarePaginator;

    /**
     * Get a paginated list of all the instances of the current related model(s).
     * This treats in the same toMany and toOne relations, a collection will be returned in all cases.
     *
     * @param  int  $parentId  the id of the parent model
     * @param  string  $relationName  the name of the relationship to fetch
     * @param  array  $with  Eager load models
     * @param  int  $perPage  set the number of elements per page
     * @param  array  $filter  the array representation of a Filter object
     * @param  array  $sortBy  a list of sorting preferences
     */
    public function indexRelation(int $parentId, string $relationName, array $with, int $perPage, array $filter, array $sortBy);

    /**
     * returns the model instance give the id
     */
    public function show($id, $with);

    public function showRelation($parentId, $relationName, $relId, $with);

    /**
     * Creates a model.
     *
     * @param  array  $params  The model fields
     */
    public function create(array $params): Model;

    /**
     * Updates a model. The received params are a key value dictionary containing three types of values.
     * 1) Assignable parameters, standard parameters like String or Integer that can be set directly
     * 2) UploadedFile, those are first stored calling the storeUploadedFile, and then a String pointer is saved in model
     * 3) a "relations" key value array, containing a map of the relationships to be updated. This is keyed with the name of the relationship
     * and an array named add with the list of models to add and remove with a list of models to remove.
     * relations[relation1][add]= [id1, id2, id3], relations[relation1][remove]= [id4, id5, id6]
     *
     * @param  int  $id  The model's ID
     * @param  array  $params  The model fields a key value array of parameters to be updated
     * @return Model the updated model
     */
    public function update(int $id, array $params): Model;

    public function updateRelation($parentId, $relationName, $relationData);

    /**
     * Deletes a model.
     *
     * @param  int  $id  The model's ID
     */
    public function delete(int $id): bool;

    /**
     * Restores a previously deleted model.
     *
     * @param  int  $id  The model's ID
     */
    public function restore(int $id): Model;

    /**
     * Get a new instance of model.
     */
    public function getNewModel(): Model;

    /*
    *  process uploaded files and based on the Model
    *  returs an appropriate form of the file
    */
    public function storeUploadedFile($file);
}
