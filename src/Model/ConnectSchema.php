<?php

namespace Square1\Laravel\Connect\Model;

use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Facade;

/**
 * @see \Illuminate\Database\Schema\Builder
 */
class ConnectSchema extends Facade
{
    public static function inspecting(MigrationInspector $inspector): void
    {
        static::$resolvedInstance['connect'] = new InjectedBuilder($inspector);
    }

    /**
     * Get a schema builder instance for a connection.
     */
    public static function connection(string $name): Builder
    {
        return static::$resolvedInstance['connect'] ?? static::$app['db']->connection($name)->getSchemaBuilder();
    }

    /**
     * Get a schema builder instance for the default connection.
     */
    protected static function getFacadeAccessor(): Builder
    {
        return static::$resolvedInstance['connect'] ?? static::$app['db']->connection()->getSchemaBuilder();
    }
}
