<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class BackupDatabase extends Command
{
    protected $signature = 'db:backup';
    protected $description = 'Create daily database backup';

    public function handle()
    {
        $database = config('database.connections.mysql.database');
        $username = config('database.connections.mysql.username');
        $password = config('database.connections.mysql.password');
        $host = config('database.connections.mysql.host');

        $fileName = 'backup_' . now()->format('Y_m_d_H_i_s') . '.sql';
        $path = storage_path("app/backups/$fileName");

        if (!file_exists(storage_path('app/backups'))) {
            mkdir(storage_path('app/backups'), 0755, true);
        }

        $command = "mysqldump --user={$username} --password={$password} --host={$host} {$database} > {$path}";

        exec($command);

        $this->info("Backup created: {$fileName}");
    }
}