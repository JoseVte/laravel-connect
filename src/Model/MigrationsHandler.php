<?php

namespace Square1\Laravel\Connect\Model;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Square1\Laravel\Connect\Console\MakeClient;

class MigrationsHandler
{
    /**
     * Map Database table list of parameters to the table name
     */
    private array $tableMap;

    public function __construct(private readonly Filesystem $files, private readonly MakeClient $client)
    {
        $this->tableMap = [];
    }

    /**
     * Get the name of the migration.
     */
    public function getMigrationName(string $path): string
    {
        return str_replace('.php', '', basename($path));
    }

    /**
     * @throws FileNotFoundException
     */
    public function process(): array
    {
        $this->client->info('----  PROCESSING MIGRATIONS  ------');

        $migrations = $this->getMigrationFiles(database_path().'/migrations');

        foreach ($migrations as $migration) {
            $this->files->requireOnce($migration);
        }

        $classes = get_declared_classes();

        foreach ($classes as $class) {

            //discard framework classes
            if (is_subclass_of($class, Migration::class)
                && ! str_contains((string) $class, 'Illuminate')
            ) {
                $inspector = new MigrationInspector($class, $this->files, $this->client);
                $inspector->inspect();
                $this->aggregateTableDetails($inspector);
            }
        }

        return $this->tableMap;
    }

    private function aggregateTableDetails(MigrationInspector $inspector): void
    {
        foreach ($inspector->getAttributes() as $table => $attributes) {

            //loop over the attributes for that table
            foreach ($attributes as $attribute => $attributeSettings) {
                foreach ($attributeSettings as $attributeSetting) {
                    $this->tableMap[$table]['attributes'][$attribute] = $attributeSetting;
                }
            }
        }

        foreach ($inspector->getCommands() as $table => $commands) {
            if (! isset($this->tableMap[$table]['commands'])) {
                $this->tableMap[$table]['commands'] = [];
            }
            $this->tableMap[$table]['commands'] = array_merge($commands, $this->tableMap[$table]['commands']);
        }

        //now we need to apply those commands
        foreach ($this->tableMap as $table) {
            $this->runCommandsOnTable($table);
        }
    }

    private function runCommandsOnTable(&$table): void
    {
        $attributes = $table['attributes'];
        $commands = $table['commands'];

        foreach ($commands as $command) {
            if ($command->name === 'foreign') {
                $column = $command->columns[0];
                if (isset($attributes[$column])) {
                    $attributes[$column]->on = $command->on;
                    $attributes[$column]->references = $command->references;
                }
            }
        }

        $table['attributes'] = $attributes;
    }

    /**
     * Get all the migration files in a given path.
     */
    public function getMigrationFiles(array|string $paths): array
    {
        return Collection::make($paths)->flatMap(
            function ($path) {
                return $this->files->glob($path.'/*_*.php');
            }
        )->filter()->sortBy(
            function ($file) {
                return $this->getMigrationName($file);
            }
        )->values()->keyBy(
            function ($file) {
                return $this->getMigrationName($file);
            }
        )->all();
    }
}
