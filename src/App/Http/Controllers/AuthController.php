<?php

namespace Square1\Laravel\Connect\App\Http\Controllers;

use ErrorException;
use Exception;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use JsonException;
use Laravel\Passport\Http\Controllers\AccessTokenController;
use League\OAuth2\Server\AuthorizationServer;
use Psr\Http\Message\ServerRequestInterface;
use Square1\Laravel\Connect\ConnectUtils;

class AuthController extends ConnectBaseController
{
    private mixed $authModel;

    private mixed $authServer;

    /**
     * @throws BindingResolutionException
     */
    public function __construct(private readonly AccessTokenController $accessTokenController, Request $request)
    {
        parent::__construct($request);

        //TODO Should have this depending on client? As in separate for iOS, Android
        $clientId = env('CONNECT_API_AUTH_CLIENT_ID', '');
        $grantType = env('CONNECT_API_AUTH_GRANT_TYPE', 'password');
        $client_secret = env('CONNECT_API_AUTH_CLIENT_SECRET', '');

        //Never add those to the request from the client but hide this inside the code
        $request->request->add([
            'grant_type' => $grantType,
            'client_id' => $clientId,
            'client_secret' => $client_secret,
        ]);

        $authClass = config('connect.api.auth.model');
        $this->authModel = new $authClass;
        //if there is any restriction on accessing this model, we need to remove it ( for example, if a user model is accessible only to log in users)
        $this->authModel::disableModelAccessRestrictions();

        $this->authServer = app()->make(AuthorizationServer::class);
    }

    /**
     * Display a listing of the resource.
     */
    public function show(Request $request): JsonResponse
    {
        $data = Auth::user();

        return response()->connect($data);
    }

    /**
     * Login a user with username and password
     *
     * @return Response , with user details and both refresh and auth token
     */
    public function login(ServerRequestInterface $request): JsonResponse
    {
        $reference = $this->authModel->endpointReference();
        $statusCode = 500;
        try {
            $token = $this->accessTokenController->issueToken($request);
            $statusCode = $token->getStatusCode();

            $responseBody = json_decode($token->content(), true, 512, JSON_THROW_ON_ERROR);
            if ($statusCode === 200) {
                $user = ConnectUtils::getUserForTokenString($responseBody['access_token']);
                $data = array_merge(['reference' => $reference, 'user' => $user], $responseBody);
            } else {
                //parse error here
                $error = [];
                if (isset($responseBody['error'])) {
                    $error['code'] = 403;
                    $error['type'] = $responseBody['error'];
                }
                if (isset($responseBody['message'])) {
                    $error['message'] = $responseBody['message'];
                }
                if (isset($responseBody['hint'])) {
                    $error['hint'] = $responseBody['hint'];
                }
                $data['error'] = $error;
            }
        } catch (ErrorException $error) {
            $statusCode = 500;
            $data = ['error' => ['message' => 'something went wrong']];
        } catch (Exception $e) {
            $statusCode = 500;
            $data = ['error' => ['message' => 'something went wrong']];
            dd($e);
        }

        return response()->connect($data, $statusCode);
    }

    /**
     *  Refresh an auth token
     */
    public function refresh(ServerRequestInterface $request): JsonResponse
    {

        $currentBody = $request->getParsedBody();
        $currentBody['grant_type'] = 'refresh_token';
        $request = $request->withParsedBody($currentBody);

        $reference = $this->authModel->endpointReference();
        $statusCode = 500;
        try {
            $token = $this->accessTokenController->issueToken($request);
            $statusCode = $token->getStatusCode();

            $responseBody = json_decode($token->content(), true, 512, JSON_THROW_ON_ERROR);
            if ($statusCode === 200) {
                $user = ConnectUtils::getUserForTokenString($responseBody['access_token']);
                $data = array_merge(['reference' => $reference, 'user' => $user], $responseBody);
            } else {
                //parse error here
                $error = [];
                if (isset($responseBody['error'])) {
                    $error['code'] = 403;
                    $error['type'] = $responseBody['error'];
                }
                if (isset($responseBody['message'])) {
                    $error['message'] = $responseBody['message'];
                }
                if (isset($responseBody['hint'])) {
                    $error['hint'] = $responseBody['hint'];
                }
                $data['error'] = $error;
            }
        } catch (ErrorException $error) {
            $statusCode = 500;
            $data = ['error' => ['message' => 'something went wrong']];
        } catch (Exception $e) {
            $statusCode = 500;
            $data = ['error' => ['message' => 'something went wrong']];
            dd($e);
        }

        return response()->connect($data, $statusCode);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @throws JsonException
     */
    public function register(ServerRequestInterface $request): JsonResponse
    {
        $params = $request->getParsedBody();
        $params['password'] = bcrypt($params['password']);
        $user = $this->authModel->create($params);
        $token = $this->accessTokenController->issueToken($request);
        $token = json_decode($token->content(), true, 512, JSON_THROW_ON_ERROR);
        $reference = $this->authModel->endpointReference();
        $data = array_merge(['reference' => $reference, 'user' => $user], $token);

        return response()->connect($data);
    }

    //TODO connect account to facebook, google, linkeding ecc... ecc...
    public function connect(Request $request): void {}
}
