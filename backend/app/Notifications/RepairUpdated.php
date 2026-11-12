<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class RepairUpdated extends Notification
{
    public function __construct(public int $repairId, public string $message) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['repair_request_id' => $this->repairId, 'message' => $this->message];
    }
}
