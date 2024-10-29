<?php

namespace Square1\Laravel\Connect\App\Http\Controllers;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Square1\Laravel\Connect\ConnectUtils;

class ApiModelController extends ConnectBaseController
{
    private mixed $repository;

    public function __construct(Request $request)
    {
        parent::__construct($request);

        $route = $request->route();

        if (isset($route)) {
            $modelReference = $route->parameter('model');
            $this->repository = ConnectUtils::repositoryInstanceForModelPath($modelReference);
            if (empty($this->repository)) {
                abort(404);
            }
        }
    }

    /**
     * Display a listing of the resource.
     *
     * @throws BindingResolutionException
     * @throws \Throwable
     */
    public function index(Request $request): JsonResponse
    {
        return $this->withErrorHandling(function () use ($request) {
            $params = $request->all();

            $perPage = Arr::get($params, 'per_page', 15);
            $filter = Arr::get($params, 'filter', []);
            $sortBy = Arr::get($params, 'sort_by', []);

            $with = Arr::get($params, 'include', '');

            if (! empty($with)) {
                $with = explode(',', $with);
            } else {
                $with = [];
            }

            $data = $this->repository->index($with, $perPage, $filter, $sortBy);

            return response()->connect($data);
        });
    }

    /**
     * Display a listing of the resource.
     *
     * @throws BindingResolutionException
     * @throws \Throwable
     */
    public function indexRelation(Request $request): JsonResponse
    {
        return $this->withErrorHandling(function () use ($request) {
            $params = $request->all();

            $perPage = Arr::get($params, 'per_page', 15);
            $filter = Arr::get($params, 'filter', []);
            $sort_by = Arr::get($params, 'sort_by', []);

            $with = Arr::get($params, 'include', '');

            if (! empty($with)) {
                $with = explode(',', $with);
            } else {
                $with = [];
            }

            $parentId = $request->route()?->parameter('id');
            $relationName = $request->route()?->parameter('relation');

            $data = $this->repository->indexRelation($parentId, $relationName, $with, $perPage, $filter, $sort_by);

            return response()->connect($data);
        });
    }

    /**
     * Store a newly created resource in storage.
     */
    public function create(Request $request): JsonResponse
    {
        $params = $request->all();
        $data = $this->repository->create($params);

        return response()->connect($data);
    }

    /**
     * Display the specified resource.
     */
    public function show($model, int $id, Request $request): JsonResponse
    {
        $params = $request->all();

        $with = Arr::get($params, 'include', '');

        if (! empty($with)) {
            $with = explode(',', $with);
        } else {
            $with = [];
        }

        $data = $this->repository->show($id, $with);

        return response()->connect($data);
    }

    /**
     * Display the specified resource.
     *
     * @throws BindingResolutionException
     * @throws \Throwable
     */
    public function showRelation(Request $request): JsonResponse
    {
        return $this->withErrorHandling(function () use ($request) {
            $parentId = $request->route()?->parameter('id');
            $relId = $request->route()?->parameter('relId');
            $relationName = $request->route()?->parameter('relation');

            $params = $request->all();

            $with = Arr::get($params, 'include', '');

            if (! empty($with)) {
                $with = explode(',', $with);
            } else {
                $with = [];
            }

            $data = $this->repository->showRelation($parentId, $relationName, $relId, $with);

            return response()->connect($data);
        });
    }

    /**
     * Update the specified resource in storage.
     *
     * @throws BindingResolutionException
     * @throws \Throwable
     */
    public function update(Request $request): JsonResponse
    {
        return $this->withErrorHandling(function () use ($request) {
            $id = $request->route()?->parameter('id');
            $params = $request->all();
            $data = $this->repository->update($id, $params);

            return response()->connect($data);
        });
    }

    /**
     * Update the specified relation.
     *
     * @throws BindingResolutionException
     * @throws \Throwable
     */
    public function updateRelation(Request $request): JsonResponse
    {
        return $this->withErrorHandling(function () use ($request) {
            $parentId = $request->route()?->parameter('id');
            $relationName = $request->route()?->parameter('relation');

            $relationData = $request->input('relationId');
            if (! isset($relationData)) {
                $relationData = $request->all();
            }
            $data = $this->repository->updateRelation($parentId, $relationName, $relationData);

            return response()->connect($data);
        });
    }

    /**
     * Update the specified relation.
     *
     * @throws BindingResolutionException
     * @throws \Throwable
     */
    public function deleteRelation(Request $request): JsonResponse
    {
        return $this->withErrorHandling(function () use ($request) {
            $parentId = $request->route()?->parameter('id');
            $relId = $request->route()?->parameter('relationId');
            $relationName = $request->route()?->parameter('relation');

            $data = $this->repository->deleteRelation($parentId, $relationName, $relId);

            return response()->connect($data);
        });
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): ?Response {}
}
