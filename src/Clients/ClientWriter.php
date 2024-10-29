<?php

namespace Square1\Laravel\Connect\Clients;

use Square1\Laravel\Connect\Console\MakeClient;

abstract class ClientWriter
{
    public function __construct(private readonly MakeClient $client) {}

    public function client(): MakeClient
    {
        return $this->client;
    }

    public function info($string, $verbosity = null): void
    {
        $this->client->info($string, $verbosity);
    }

    /**
     * get the app version
     */
    public function appVersion(): string
    {
        return $this->client->appVersion;
    }

    /**
     * get the app name
     */
    public function appName(): string
    {
        return $this->client->appName;
    }

    public function pathComponentsAsArrayString(): string
    {

        $str = config('connect.api.prefix');
        $result = '';

        foreach (explode('/', $str) as $part) {

            if (! empty($part)) {

                if (empty($result)) {
                    $result = '[';
                } else {
                    $result .= ',';
                }

                $result .= "\"$part\"";

            }

        }

        if (! empty($result)) {
            $result .= ']';
        }

        return $result;

    }

    /**
     * @param  mixed  $attribute,  string or ModelAttribute
     */
    abstract public function resolveType(mixed $attribute): ?string;

    abstract public function outputClient();
}
