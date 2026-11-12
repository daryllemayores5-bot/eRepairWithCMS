<?php

namespace App\Console\Commands;

use App\Services\SubscriptionService;
use Illuminate\Console\Command;

class ExpireSubscriptions extends Command
{
    protected $signature = 'erepair:subscriptions-expire';

    protected $description = 'Expire overdue subscriptions and activate due scheduled plans without changing payment history';

    public function handle(SubscriptionService $service): int
    {
        $this->info($service->expire().' subscription(s) expired.');

        return self::SUCCESS;
    }
}
