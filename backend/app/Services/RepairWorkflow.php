<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class RepairWorkflow
{
    public const TECHNICIAN = [
        'pending' => ['accepted', 'rejected'], 'accepted' => ['scheduled'], 'scheduled' => ['diagnosing'],
        'diagnosing' => ['awaiting_customer_approval'], 'in_repair' => ['ready_for_pickup'],
    ];

    public const CUSTOMER = [
        'pending' => ['canceled'], 'accepted' => ['canceled'], 'scheduled' => ['canceled'],
        'awaiting_customer_approval' => ['in_repair', 'canceled'], 'ready_for_pickup' => ['completed'],
    ];

    public function ensure(string $from, string $to, string $role): void
    {
        $map = $role === 'technician' ? self::TECHNICIAN : ($role === 'customer' ? self::CUSTOMER : []);
        if (! in_array($to, $map[$from] ?? [], true)) {
            throw ValidationException::withMessages(['status' => "A {$role} cannot change {$from} to {$to}."]);
        }
    }
}
