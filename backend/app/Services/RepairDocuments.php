<?php

namespace App\Services;

use App\Models\RepairRequest;
use App\Support\SimplePdf;
use Carbon\CarbonImmutable;

class RepairDocuments
{
    public function __construct(private SimplePdf $pdf) {}

    public function render(RepairRequest $repair, string $kind): string
    {
        $repair->loadMissing(['customer', 'technician.user', 'deviceType', 'deviceBrand', 'category', 'estimates', 'appointments', 'history']);
        $quote = $repair->estimates->first();
        abort_if($kind === 'quotation' && ! $quote, 422, 'Add a repair estimate before downloading a quotation.');
        $date = fn ($value) => CarbonImmutable::parse($value)->setTimezone('Asia/Manila')->format('M d, Y h:i A');
        $lines = [
            'Reference: '.$repair->reference,
            'Generated: '.$date(now()).' (Philippine time)',
            'Technician: '.$repair->technician->user->name,
            'Customer: '.$repair->customer->name,
            'Device: '.$repair->deviceBrand->name.' '.$repair->model.' | '.$repair->deviceType->name,
            'Repair category: '.$repair->category->name,
            'Current status: '.str_replace('_', ' ', $repair->status),
            '',
            'REPORTED PROBLEM', $repair->problem_description, '',
        ];
        if ($quote) {
            array_push($lines, 'LATEST ESTIMATE', 'Amount: PHP '.$quote->amount, 'Approval status: '.$quote->status, $quote->description, '');
        }
        if ($kind === 'job-sheet') {
            $appointment = $repair->appointments->first();
            if ($appointment) {
                array_push($lines, 'APPOINTMENT', $date($appointment->starts_at).' to '.$date($appointment->ends_at), $appointment->service_location, 'Appointment status: '.$appointment->status, '');
            }
            $history = $repair->history->take(-100);
            $lines[] = 'RECENT REPAIR HISTORY';
            foreach ($history as $entry) {
                $lines[] = $date($entry->created_at).' | '.str_replace('_', ' ', $entry->new_status);
                if ($entry->remarks) {
                    $lines[] = $entry->remarks;
                }
            }
            array_push($lines, '', 'Customer acknowledgement: __________________    Date: ______________', 'Technician acknowledgement: ________________    Date: ______________');
        }
        array_push($lines, '', 'This document records a repair request and estimate. It is not a receipt or proof of payment.');

        return $this->pdf->render($kind === 'quotation' ? 'Repair quotation' : 'Repair job sheet', $lines);
    }
}
