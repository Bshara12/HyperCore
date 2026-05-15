<?php

namespace App\Console\Commands;

use App\Models\Domains\Notifications\Models\Notification;
use App\Models\Domains\Notifications\Models\NotificationBatch;
use App\Models\Domains\Notifications\Models\NotificationDelivery;
use Illuminate\Console\Command;

class PruneNotificationDataCommand extends Command
{
    protected $signature = 'notifications:prune';

    protected $description = 'Prune old notification data';

    public function handle(): int
    {
        $notifications = (new Notification)->pruneAll();
        $deliveries = (new NotificationDelivery)->pruneAll();
        $batches = (new NotificationBatch)->pruneAll();

        $this->info("Pruned notifications: {$notifications}");
        $this->info("Pruned deliveries: {$deliveries}");
        $this->info("Pruned batches: {$batches}");

        return self::SUCCESS;
    }
}
