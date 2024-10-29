<?php

namespace Square1\Laravel\Connect\Model;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Database\SQLiteConnection;

class MockConnection extends SQLiteConnection {}

class InjectedBuilder extends Builder
{
    private MigrationInspector $inspector;

    private Blueprint $blueprint;

    /**
     * Create a new database Schema manager.
     */
    public function __construct(MigrationInspector $inspector)
    {
        $this->inspector = $inspector;
    }

    /**
     * Create a new command set with a Closure.
     *
     * @param  string  $table
     */
    protected function createBlueprint($table, ?Closure $callback = null): Blueprint
    {
        return $this->inspector->createBlueprint($table, $callback);
    }

    public function getBluePrint(): Blueprint
    {
        return $this->blueprint;
    }

    /**
     * Execute the blueprint to build / modify the table.
     */
    protected function build(Blueprint $blueprint): void
    {
        $blueprint->build($this->connection, $this->grammar);
    }

    /**
     * Determine if the given table exists.
     *
     * @param  string  $table
     */
    public function hasTable($table): bool
    {
        return false;
    }

    /**
     * Determine if the given table has a given column.
     *
     * @param  string  $table
     * @param  string  $column
     */
    public function hasColumn($table, $column): bool
    {
        $column = strtolower($column);

        return in_array($column, array_map('strtolower', $this->getColumnListing($table)), true);
    }

    /**
     * Determine if the given table has given columns.
     *
     * @param  string  $table
     */
    public function hasColumns($table, array $columns): bool
    {
        $tableColumns = array_map('strtolower', $this->getColumnListing($table));

        foreach ($columns as $column) {
            if (! in_array(strtolower($column), $tableColumns, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the data type for the given column name.
     *
     * @param  string  $table
     * @param  string  $column
     * @param  false  $fullDefinition
     */
    public function getColumnType($table, $column, $fullDefinition = false): string
    {
        $table = $this->connection->getTablePrefix().$table;

        return $this->connection->getDoctrineColumn($table, $column)->getType()->getName();
    }

    /**
     * Get the column listing for a given table.
     *
     * @param  string  $table
     */
    public function getColumnListing($table): array
    {
        $table = $this->connection->getTablePrefix().$table;

        $results = $this->connection->select($this->grammar->compileColumnExists($table));

        return $this->connection->getPostProcessor()->processColumnListing($results);
    }

    /**
     * Modify a table on the schema.
     *
     * @param  string  $table
     */
    public function table($table, Closure $callback): void
    {
        $this->build($this->createBlueprint($table, $callback));
    }

    /**
     * Create a new table on the schema.
     *
     * @param  string  $table
     */
    public function create($table, Closure $callback): void
    {
        $blueprint = $this->createBlueprint($table);

        $blueprint->create();

        $callback($blueprint);

        $this->build($blueprint);
    }

    /**
     * Drop a table from the schema.
     *
     * @param  string  $table
     */
    public function drop($table): void
    {
        $blueprint = $this->createBlueprint($table);

        $blueprint->drop();

        $this->build($blueprint);
    }

    /**
     * Drop a table from the schema if it exists.
     *
     * @param  string  $table
     */
    public function dropIfExists($table): void
    {
        $blueprint = $this->createBlueprint($table);

        $blueprint->dropIfExists();

        $this->build($blueprint);
    }

    /**
     * Rename a table on the schema.
     *
     * @param  string  $from
     * @param  string  $to
     */
    public function rename($from, $to): Blueprint
    {
        $blueprint = $this->createBlueprint($from);

        $blueprint->rename($to);

        $this->build($blueprint);
    }

    /**
     * Enable foreign key constraints.
     */
    public function enableForeignKeyConstraints(): bool
    {
        return true;
    }

    /**
     * Disable foreign key constraints.
     */
    public function disableForeignKeyConstraints(): bool
    {
        return true;
    }

    /**
     * Get the database connection instance.
     */
    public function getConnection(): ?Connection
    {
        return null;
    }

    /**
     * Set the Schema Blueprint resolver callback.
     */
    public function blueprintResolver(Closure $resolver): void
    {
        $this->resolver = $resolver;
    }
}
