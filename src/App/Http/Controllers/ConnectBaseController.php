<?php

namespace Square1\Laravel\Connect\App\Http\Controllers;

use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ConnectBaseController extends Controller
{
    public function __construct(protected Request $request) {}

    public function options(): string
    {
        return '';
    }

    /**
     * @throws \Throwable
     * @throws BindingResolutionException
     */
    public function withErrorHandling($closure): JsonResponse
    {
        try {
            return $closure();
        } catch (\Exception $e) {
            $this->exceptionHandler()->report($e);
            $payload = [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ];

            return response()->connect(['error' => $payload]);
        }
    }

    /**
     * Get the exception handler instance.
     *
     * @throws BindingResolutionException
     */
    protected function exceptionHandler(): ExceptionHandler
    {
        return Container::getInstance()->make(ExceptionHandler::class);
    }
}
