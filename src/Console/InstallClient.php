<?php

namespace Square1\Laravel\Connect\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class InstallClient extends Command
{
    /**
     * The filesystem instance.
     */
    protected Filesystem $files;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'connect:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run pending migrations, sets up Laravel Passport and generates client keys and secrets for login ';

    /**
     * Create a new migrator instance.
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();

        $this->files = $files;
        // this will erase all previous config files

        //__DIR__.'/../config/connect.php'
    }

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->info('Initialising LaravelConnect, the command will also setup Laravel Passport for users authentication');

        $settings = config('connect');

        if (! isset($settings)) {
            $this->error(' Missing connect configuration, have you run connect init ?');

            return;
        }

        //migrate
        $continue = $this->confirm('About to check if any migration before setting up Laravel Passport, proceed ? ');

        if ($continue) {
            $this->call('migrate');
            //migrate
            $this->call('passport:install');
            $this->call('passport:keys');

            $continue = $this->confirm('Do you want to create a new  Laravel Passport Client ?');

            if ($continue) {
                $this->call('passport:client');
            }
            $this->info('LaravelConnect  completed');
        } else {
            $this->info('LaravelConnect setup not completed');
        }
    }
}
