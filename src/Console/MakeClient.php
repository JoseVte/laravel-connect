<?php

namespace Square1\Laravel\Connect\Console;

use DateTime;
use ErrorException;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Square1\Laravel\Connect\App\Routes\RoutesInspector;
use Square1\Laravel\Connect\Clients\Android\AndroidClientWriter;
use Square1\Laravel\Connect\Clients\iOS\iOSClientWriter;
use Square1\Laravel\Connect\Model\MigrationsHandler;
use Square1\Laravel\Connect\Model\ModelInspector;

class MakeClient extends Command
{
    /**
     * The filesystem instance.
     */
    public Filesystem $files;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'connect:build {platform?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Build the LaravelConnect Client ';

    /**
     * Map Database table list of parameters to the table name
     */
    public array $tableMap;

    /**
     * Map of modelInspectors and class name
     */
    public array $classMap;

    /**
     * Map of modelInspectors and the table name used to store this model in the database
     */
    public array $tableInspectorMap;

    /**
     * the path to the folder where all the model files are stored
     */
    private string $modelFolder;

    private MigrationsHandler $migrationsHandler;

    public string $baseTmpPath;

    public string $baseBuildPath;

    public string $baseRepositoriesPath;

    public string $appName;

    /**
     * The hash code of the last commit on the application, plus the date of the commit ,
     * e8c4cad (2018-03-16 14:03:08), It is passed to the client generated code to ensure
     * consistency between client and server code
     */
    public string $appVersion;

    /**
     * The target platform for this build ( android, iOS) or empty as passed when invoking the command.
     */
    public ?string $platform;

    /**
     * Create a new migrator instance.
     *
     * @throws ErrorException
     * @throws \DateMalformedStringException
     * @throws \JsonException
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();

        $this->files = $files;
        $this->classMap = [];
        $this->tableMap = [];
        $this->tableInspectorMap = [];
        $this->modelFolder = config('connect.model_classes_folder');

        $this->appVersion = $this->getAppVersion();
        $this->appName = $this->getAppName();

        $this->baseTmpPath = base_path('tmp');
        $this->baseBuildPath = config('connect.clients.build_path');
        $this->baseRepositoriesPath = app_path().'/Repositories/Connect';

        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
        });
    }

    /**
     * Execute the console command.
     *
     * @throws FileNotFoundException
     * @throws \DOMException
     * @throws \JsonException
     * @throws \Throwable
     */
    public function handle(): void
    {
        $this->platform = $this->argument('platform');

        if ($this->platform !== 'android' && $this->platform !== 'iOS') {
            $this->info("Building the Laravel Client code version $this->appVersion");
            $this->info("$this->platform unknown building all...");
            $this->platform = null;
        } else {
            $this->info("Building the Laravel Client for $this->platform, code version $this->appVersion");
        }

        $settings = config('connect');

        if (! isset($settings)) {
            $this->error(' Missing connect configuration, have you run connect init ?');

            return;
        }

        $this->prepareStorage();

        //loop over the migrations to see what parameters to add to each model object
        $this->migrationsHandler = new MigrationsHandler($this->files, $this);
        $this->tableMap = $this->migrationsHandler->process();

        //dd($this->tableMap);
        //list of classes extending Model
        $this->processModelSubclasses();

        //removing hidden fields from tables
        $this->removeHiddenFields();

        //check for routes and requests that need to be exposed
        $routesInspector = new RoutesInspector($this->files, $this);
        $routesInspector->inspect();

        foreach ($routesInspector->routes as $route) {
            $modelClass = $route['model'];

            if (isset($this->classMap[$modelClass])) {
                if (! isset($this->classMap[$modelClass]['routes'])) {
                    $this->classMap[$modelClass]['routes'] = [];
                }
                $this->classMap[$modelClass]['routes'][] = $route;
            }
        }

        // just for debug purposes
        $this->dumpObject('classMap', $this->classMap);
        $this->dumpObject('tableMap', $this->tableMap);

        //rewrite the connect_auto_generated config
        $this->generateConfig();

        //build and push the client code
        $this->outputClient();
    }

    private function processModelSubclasses(): void
    {
        $files = $this->files->allFiles($this->modelFolder);
        foreach ($files as $file) {
            require_once $file;
        }

        $classes = get_declared_classes();

        foreach ($classes as $class) {
            //discard framework classes
            if (is_subclass_of($class, Model::class)) {
                //
                $this->info('-----------------------------------------', 'vvv');
                $this->info('-                                       ', 'vvv');
                $this->info("-                 $class                ", 'vvv');
                $this->info('-                                       ', 'vvv');
                $this->info('-----------------------------------------', 'vvv');

                $inspector = new ModelInspector($class, $this->files, $this);

                if ($inspector->hasTrait()) {
                    $inspector->init();
                    $this->classMap[$class]['inspector'] = $inspector;
                    $this->tableInspectorMap[$inspector->tableName()] = $inspector;
                    $inspector->inspect();
                }
            }
        }
    }

    /**
     * @throws FileNotFoundException
     */
    private function makeRepository(ModelInspector $modelInspector): string
    {
        $injectedClassName = $modelInspector->classShortName();
        $baseCode = $this->files->get(__DIR__.'/../model/templates/Injected.repository.template.php');

        $baseCode = str_replace(['_INJECTED_APP_NAMESPACE_', '_INJECTED_CLASS_NAME_Template', '_INJECTED_EXTENDED_CLASS_NAME_'], [app()->getNamespace(), $injectedClassName, $modelInspector->className()], $baseCode);

        $this->files->put($this->baseRepositoriesPath.'/'.$modelInspector->classShortName().'ConnectRepository.php', $baseCode);

        return app()->getNamespace()."Repositories\Connect\\".$modelInspector->classShortName().'ConnectRepository';
    }

    /**
     * @throws FileNotFoundException
     */
    private function generateConfig(): void
    {
        $endpoints = config('connect_auto_generated.endpoints');

        foreach ($this->classMap as $inspector) {
            $inspector = $inspector['inspector'];
            $className = $inspector->className();
            $classPath = $inspector->endpointReference();
            //do not override in case developer wants to use a different class
            if (! isset($endpoints[$classPath])) {
                $repositoryClass = $this->makeRepository($inspector);
                $endpoints[$classPath] = $repositoryClass;
            }
        }
        $settings = [];
        $settings['endpoints'] = $endpoints;

        $config_file = config_path().'/connect_auto_generated.php';
        $this->files->delete($config_file);
        $this->files->put($config_file, "<?php\n\nreturn ".var_export($settings, true).';');
    }

    private function prepareStorage(): void
    {
        $this->initAndClearFolder($this->baseTmpPath);
        $this->initAndClearFolder($this->baseBuildPath);

        //do not clear the repository folder is already there
        $this->initAndClearFolder($this->baseRepositoriesPath, false);
    }

    public function removeHiddenFields(): void
    {

        ///looping over the ModelInspectors and removing hidden fields
        foreach ($this->tableInspectorMap as $tableName => $model) {
            foreach ($model->getHidden() as $hidden) {
                $this->info("$tableName hiding ".$hidden, 'vvv');
                unset($this->tableMap[$tableName]['attributes'][$hidden]);
            }
        }
    }

    /**
     * @throws \JsonException
     */
    public function dumpObject($fileName, $object): void
    {
        $fileName = $this->baseTmpPath.'/'.$fileName.'.json';

        $this->files->delete($fileName);
        $this->files->put($fileName, json_encode($object, JSON_THROW_ON_ERROR));
    }

    /**
     * @throws \DOMException
     * @throws FileNotFoundException
     * @throws \Throwable
     */
    private function outputClient(): void
    {
        if ($this->platform === null || $this->platform === 'android') {
            $android = new AndroidClientWriter($this);
            $android->outputClient();
        }

        if ($this->platform === null || $this->platform === 'iOS') {
            $ios = new iOSClientWriter($this);
            $ios->outputClient();
        }
    }

    /**
     * Given a table name returns the associated model class name.
     * The model class should use the ConnectModelTrait or null will be returned.
     *
     * @param  string  $table  the name of a table
     * @return string|null the name of a Model subclass is one is available.
     */
    public function getModelClassFromTableName(string $table): ?string
    {
        $modelInspector = $this->tableInspectorMap[$table] ?? null;

        return $modelInspector ? $modelInspector->className() : null;
    }

    /**
     * Creates a folder if it doesn't exists or clear an existing folder if force = true
     *
     * @param  string  $folder  the path to a folder to create.
     * @param  bool  $force,  clear the folder if it exists already
     */
    public function initAndClearFolder(string $folder, bool $force = true): void
    {
        $shouldCreate = true;

        if ($this->files->isDirectory($folder)) {
            if ($force) {
                $this->files->deleteDirectory($folder);
            } else {
                $shouldCreate = false;
            }
        }

        if ($shouldCreate) {
            $this->files->makeDirectory($folder, 0755, true);
        }
    }

    /**
     * Get the name of the migration.
     */
    public function getMigrationName(string $path): string
    {
        return str_replace('.php', '', basename($path));
    }

    /**
     * Get all the migration files in a given path.
     */
    public function getMigrationFiles(array|string $paths): array
    {
        return Collection::make($paths)->flatMap(function ($path) {
            return $this->files->glob($path.'/*_*.php');
        })->filter()->sortBy(function ($file) {
            return $this->getMigrationName($file);
        })->values()->keyBy(function ($file) {
            return $this->getMigrationName($file);
        })->all();
    }

    public static function getProtectedValue($obj, $name)
    {
        $array = (array) $obj;
        $prefix = chr(0).'*'.chr(0);

        return $array[$prefix.$name];
    }

    public function get_this_class_methods($class): array
    {
        $array1 = get_class_methods($class);
        if ($parent_class = get_parent_class($class)) {
            $array2 = get_class_methods($parent_class);
            $array3 = array_diff($array1, $array2);
        } else {
            $array3 = $array1;
        }

        return $array3;
    }

    /**
     * @throws \DateMalformedStringException
     */
    public function getAppVersion(): string
    {
        $commitHash = trim(exec('git log --pretty="%h" -n1 HEAD'));

        $commitDate = new DateTime(trim(exec('git log -n1 --pretty=%ci HEAD')));
        //$commitDate->setTimezone(new \DateTimeZone('UTC'));

        return sprintf('%s %s', $commitHash, $commitDate->format('Y-m-d H:m:s'));
    }

    /**
     * Get the application name and namespace.
     *
     * @throws \JsonException
     */
    protected function getAppName(): string
    {
        $appNameSpace = '';

        $composer = json_decode(file_get_contents(base_path().'/composer.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach ((array) data_get($composer, 'autoload.psr-4') as $namespace => $path) {
            foreach ((array) $path as $pathChoice) {
                if (realpath(app_path()) === realpath(base_path().'/'.$pathChoice)) {
                    $appNameSpace = $namespace;
                    break;
                }
            }
        }

        return $appNameSpace.config('app.name');

    }
}
