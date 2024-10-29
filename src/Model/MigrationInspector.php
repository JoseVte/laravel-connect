<?php

namespace Square1\Laravel\Connect\Model;

use Closure;
use Exception;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Fluent;
use ReflectionClass;
use ReflectionException;
use Square1\Laravel\Connect\Console\MakeClient;

class MigrationInspector
{
    private mixed $model;

    private ReflectionClass $modelInfo;

    private string $baseTmpPath;

    private array $attributes;

    private array $relations;

    private array $bluePrints;

    private array $commands;

    /**
     * Create a new  instance.
     *
     * @return void
     *
     * @throws ReflectionException
     */
    public function __construct($className, protected Filesystem $files, private readonly MakeClient $client)
    {
        $this->baseTmpPath = $client->baseTmpPath.'/migration';
        $this->bluePrints = [];
        $this->commands = [];
        $this->modelInfo = new ReflectionClass(new $className);
        $this->model = $this->prepareForInspection();

        $this->attributes = [];
        $this->relations = [];
    }

    public function classShortName(): string
    {
        return $this->modelInfo->getShortName();
    }

    public function className(): string
    {
        return $this->modelInfo->getName();
    }

    private function prepareForInspection()
    {
        $injectedClassName = $this->classShortName();
        $baseCode = $this->files->get(__DIR__.'/templates/Injected.migration.template.php');
        $baseCode = str_replace('_INJECTED_MIGRATION_NAME_Template', $injectedClassName, $baseCode);
        $baseCode = str_replace('_INJECTED_EXTENDED_MIGRATION_NAME_', $this->className(), $baseCode);

        $injectedClassName = 'Square1\Laravel\Connect\Console\Injected\Injected'.$this->classShortName();
        //prepare tmp folder
        if (! $this->files->isDirectory($this->baseTmpPath)) {
            $this->files->makeDirectory($this->baseTmpPath, 0755, true);
        }

        //get the file name to store this new class in
        $fileName = $this->injectedClassFileName();
        if ($this->files->isFile($fileName)) {
            $this->files->delete($fileName);
        }

        $this->files->put($fileName, $baseCode);
        include_once $fileName;

        return new $injectedClassName($this);
    }

    public function inspect(): void
    {
        $this->client->info('starting inspection of '.$this->injectedClassFileName());

        try {
            $this->model->inspect();
        } catch (Exception $e) {
            // dd($e->getTraceAsString());
        }
    }

    private function injectedClassFileName(): string
    {
        return strtolower($this->baseTmpPath.'/'.str_replace('\\', '_', $this->className()).'.php');
    }

    /**
     * Create a new command set with a Closure.
     */
    public function createBlueprint(string $table, ?Closure $callback = null): Blueprint|ApiClientBlueprint
    {
        if (! array_key_exists($table, $this->bluePrints)) {
            $this->bluePrints[$table] = new ApiClientBlueprint($this, $table, $callback);
        }

        return $this->bluePrints[$table];
    }

    public function inspectionCompleted(): void
    {
        foreach ($this->bluePrints as $bluePrint) {
            $this->commands[$bluePrint->getTable()] = $bluePrint->getCommands();

            $currentColumns = $bluePrint->getColumns();
            //dd($currentColumns);
            $this->client->info('found in '.$bluePrint->getTable(), 'vvv');

            //we have a list of Fluent instances lets make them into attributes

            foreach ($currentColumns as $column) {
                $attribute = new ModelAttribute($column);

                $attribute->fluent = $column;
                $attribute->allowed = $column->allowed;

                $this->attributeFound($bluePrint->getTable(), $attribute);
            }
        }
    }

    public function attributeFound($table, ModelAttribute $attribute): void
    {
        $this->client->info($attribute, 'vvv');
        $this->attributes[$table][$attribute->name][] = $attribute;
    }

    public function relationFound($table, ModelAttribute $attribute): void
    {
        $this->client->info("relation found in table $table :".$attribute, 'vvv');
        $this->relations[$table][$attribute->name][] = $attribute;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getCommands(): array
    {
        return $this->commands;
    }
}
