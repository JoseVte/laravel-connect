<?php

namespace Square1\Laravel\Connect\App\Middleware;

use Closure;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Square1\Laravel\Connect\ConnectUtils;

/**
 * Description of AfterConnectMiddleware
 *
 * @author roberto
 */
class AfterConnectMiddleware
{
    public function handle($request, Closure $next)
    {
        //first check the apikey
        if (! $this->verifyApiKey($request)) {
            //TODO RETURN BETTER ERROR
            return null;
        }

        //deal with logging in a user
        try {
            $currentUser = ConnectUtils::currentAuthUser($request);

            if ($currentUser) {
                Auth::login($currentUser);
            }
        } catch (Exception $e) {
        }
        $response = $next($request);

        //deal with crossdomain requests
        if ($this->isCORSEnabled()) {

            $headers = ['Access-Control-Allow-Origin' => '*'];

            if ($this->isPreflightRequest($request)) {
                $headers['Access-Control-Allow-Headers'] = 'Content-Type, Authorization';
                $headers['Access-Control-Allow-Methods'] = 'GET, POST, PUT, PATCH, DELETE, OPTIONS';
            }

            $response->headers->add($headers);
        }

        //Handle JSONP requests
        if ($request->has('callback')) {
            return $response->withCallback($request->input('callback'));
        }

        return $response;
    }

    public function isCORSEnabled()
    {
        return config('connect.api.cors.enabled');
    }

    /**
     * Check the api key and return true if the request can continue
     *
     * @param  Request  $request  the incoming request
     * @return bool true if the request has a valid key or if no key is set for this application
     */
    public function verifyApiKey(Request $request): bool
    {
        $keys = config('connect.api.key.value');

        if (! empty($keys)) {
            $headerValue = $request->header(config('connect.api.key.header'), '');

            if (empty($headerValue)) {
                return false;
            }
            if (is_array($keys)) {
                return Str::contains($headerValue, $keys);
            }

            return $headerValue === $keys;
        }

        return true;
    }

    /**
     * Check for a Preflight request.
     */
    protected function isPreflightRequest(Request $request): bool
    {
        return $request->isMethod('OPTIONS') &&
                $request->hasHeader('Access-Control-Request-Method'); // &&
        // $request->hasHeader('Origin');
    }
}
