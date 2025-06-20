<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class WipeMigrateSeed extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'customs:refresh-db';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command for do the wipe, migration and database seeder';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Dropping all tables...');
        $this->call('db:wipe');

        $this->info('Migrating tables...');
        $this->call('migrate');

        $this->info('Running Seeder...');
        $this->call('db:seed');

        $this->info('Done.');
        return Command::SUCCESS;
    }
}
