<?php

namespace Square1\Laravel\Connect\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class InitClient extends Command
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
    protected $signature = 'connect:init';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates the default config for LaravelConnect';

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

        //migrate
        $continue = $this->confirm('This will override your current connect config, proceed ? ');

        if ($continue) {
            $this->files->copy(__DIR__.'/../config/connect.php', config_path().'/connect.php');
        }
    }
}
