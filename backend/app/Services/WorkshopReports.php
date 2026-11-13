<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\InventoryPart;
use App\Models\RepairRequest;
use App\Models\TechnicianProfile;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class WorkshopReports
{
    public function summary(TechnicianProfile $profile, CarbonImmutable $from, CarbonImmutable $until): array
    {
        $repairs = $profile->repairs()->where('created_at', '>=', $from)->where('created_at', '<', $until);
        $completed = $profile->repairs()->where('status', 'completed')->where('completed_at', '>=', $from)->where('completed_at', '<', $until);
        $quoteTotal = DB::table('repair_requests as r')
            ->join('repair_estimates as e', 'e.repair_request_id', '=', 'r.id')
            ->where('r.technician_profile_id', $profile->id)->where('r.status', 'completed')
            ->where('r.completed_at', '>=', $from)->where('r.completed_at', '<', $until)
            ->where('e.status', 'approved')
            ->whereRaw("e.id = (select max(latest.id) from repair_estimates latest where latest.repair_request_id = r.id and latest.status = 'approved')")
            ->sum('e.amount');
        $usage = InventoryMovement::where('technician_id', $profile->id)->where('kind', 'usage')
            ->where('created_at', '>=', $from)->where('created_at', '<', $until);
        $partsCost = (clone $usage)->selectRaw('COALESCE(SUM(quantity * unit_cost), 0) AS total')->first()->total;

        return [
            'from' => $from->setTimezone('Asia/Manila')->toDateString(),
            'through' => $until->setTimezone('Asia/Manila')->subDay()->toDateString(),
            'requests_received' => (clone $repairs)->count(), 'completed_repairs' => (clone $completed)->count(),
            'status_counts' => (clone $repairs)->select('status')->selectRaw('count(*) AS total')->groupBy('status')->pluck('total', 'status'),
            'completed_approved_estimates' => $this->decimal((string) $quoteTotal),
            'parts_used' => (int) (clone $usage)->sum('quantity'),
            'parts_cost' => $this->decimal((string) $partsCost),
            'low_stock_items' => InventoryPart::where('technician_id', $profile->id)->where('active', true)->whereColumn('quantity', '<=', 'reorder_level')->count(),
            'scope' => 'Request counts use submission dates; completed jobs and their latest approved estimate use completion dates; parts usage uses movement dates. Philippine time. Estimate values are not collected revenue; parts costs exclude labor and other expenses.',
        ];
    }

    public function repairRows(TechnicianProfile $profile, CarbonImmutable $from, CarbonImmutable $until): array
    {
        $repairs = RepairRequest::where('technician_profile_id', $profile->id)
            ->where('created_at', '>=', $from)->where('created_at', '<', $until)
            ->with(['estimates' => fn ($q) => $q->where('status', 'approved')])
            ->oldest('id')->limit(2001)->get();
        abort_if($repairs->count() > 2000, 422, 'Choose a shorter date range to export up to 2,000 requests.');

        return $repairs->map(fn ($repair) => [
            $repair->reference, $repair->model, $repair->status,
            $repair->created_at->setTimezone('Asia/Manila')->format('Y-m-d H:i'),
            $repair->completed_at?->setTimezone('Asia/Manila')->format('Y-m-d H:i') ?? '',
            $repair->estimates->first()?->amount ?? '',
        ])->all();
    }

    public function csvCell(mixed $value): string
    {
        $text = (string) $value;

        return preg_match('/^[=+\-@\s]/u', $text) ? "'".$text : $text;
    }

    private function decimal(string $amount): string
    {
        // PostgreSQL numeric aggregates can exceed the size of a single quote.
        // Keep their exact decimal representation without floats or integer overflow.
        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/D', $amount, $parts)) {
            throw new \LogicException('Unexpected monetary aggregate.');
        }

        return $parts[1].'.'.str_pad($parts[2] ?? '', 2, '0');
    }
}
