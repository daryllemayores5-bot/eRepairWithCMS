<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RepairRequest;
use App\Services\RepairDocuments;
use App\Services\WorkshopAccess;
use App\Services\WorkshopReports;
use App\Support\SimplePdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class WorkshopController extends Controller
{
    public function __construct(private WorkshopAccess $access) {}

    public function index(Request $request)
    {
        return response()->json(['data' => $this->access->capabilities($request->user())]);
    }

    public function repairs(Request $request)
    {
        $profile = $this->access->requirePlan($request->user());
        $filters = $request->validate(['page' => ['sometimes', 'integer', 'min:1', 'max:100000'], 'search' => ['nullable', 'string', 'max:100'], 'stockable' => ['sometimes', 'boolean']]);
        $repairs = RepairRequest::where('technician_profile_id', $profile->id);
        if ($request->boolean('stockable')) {
            $repairs->where('status', 'in_repair');
        }
        if ($search = $filters['search'] ?? null) {
            $repairs->where(fn ($q) => $q->where('reference', 'ilike', '%'.$search.'%')->orWhere('model', 'ilike', '%'.$search.'%'));
        }

        return response()->json(['data' => $repairs->with('customer:id,name')->withCount('estimates')->latest('id')->paginate(20, ['id', 'customer_id', 'reference', 'model', 'status', 'created_at'])]);
    }

    public function document(Request $request, int $repair, string $kind, RepairDocuments $documents)
    {
        $profile = $this->access->requirePlan($request->user());
        abort_unless(in_array($kind, ['quotation', 'job-sheet'], true), 404);
        $record = RepairRequest::where('technician_profile_id', $profile->id)->findOrFail($repair);
        $file = preg_replace('/[^A-Za-z0-9_-]/', '_', $record->reference).'-'.$kind.'.pdf';

        return response($documents->render($record, $kind))->withHeaders([
            'Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$file.'"',
            'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function report(Request $request, WorkshopReports $reports)
    {
        $profile = $this->access->requirePlan($request->user(), 'premium');
        [$from, $until] = $this->period($request);

        return response()->json(['data' => $reports->summary($profile, $from, $until)]);
    }

    public function export(Request $request, WorkshopReports $reports, SimplePdf $pdf)
    {
        $profile = $this->access->requirePlan($request->user(), 'premium');
        $options = $request->validate(['format' => ['required', 'in:csv,pdf']]);
        [$from, $until] = $this->period($request);
        $summary = $reports->summary($profile, $from, $until);
        $rows = $reports->repairRows($profile, $from, $until);
        $filename = 'eRepair-business-'.$summary['from'].'-'.$summary['through'];
        if ($options['format'] === 'pdf') {
            $lines = [
                'Technician: '.$request->user()->name,
                'Period: '.$summary['from'].' to '.$summary['through'].' (Philippine time)',
                'Requests received: '.$summary['requests_received'], 'Repairs completed: '.$summary['completed_repairs'],
                'Approved estimates on completed repairs: PHP '.$summary['completed_approved_estimates'],
                'Parts used: '.$summary['parts_used'].' | Recorded parts cost: PHP '.$summary['parts_cost'],
                'Current low-stock items: '.$summary['low_stock_items'], '', $summary['scope'], '',
                'REQUESTS RECEIVED IN THIS PERIOD',
            ];
            foreach ($rows as $row) {
                $lines[] = $row[0].' | '.$row[1].' | '.str_replace('_', ' ', $row[2]).' | Approved estimate: '.($row[5] === '' ? 'none' : 'PHP '.$row[5]);
            }

            return response($pdf->render('Technician business report', $lines))->withHeaders(['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$filename.'.pdf"', 'Cache-Control' => 'no-store, private']);
        }

        return response()->streamDownload(function () use ($reports, $summary, $rows) {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['eRepair technician business report', $summary['from'], $summary['through']], ',', '"', '');
            foreach (['requests_received', 'completed_repairs', 'completed_approved_estimates', 'parts_used', 'parts_cost', 'low_stock_items', 'scope'] as $metric) {
                fputcsv($stream, [$metric, $reports->csvCell($summary[$metric])], ',', '"', '');
            }
            fputcsv($stream, [], ',', '"', '');
            fputcsv($stream, ['Reference', 'Device model', 'Status', 'Submitted (Philippine time)', 'Completed (Philippine time)', 'Latest approved estimate (PHP; not payment received)'], ',', '"', '');
            foreach ($rows as $row) {
                fputcsv($stream, array_map($reports->csvCell(...), $row), ',', '"', '');
            }
            fclose($stream);
        }, $filename.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store, private']);
    }

    private function period(Request $request): array
    {
        $data = $request->validate(['from' => ['sometimes', 'date_format:Y-m-d'], 'through' => ['sometimes', 'date_format:Y-m-d']]);
        $today = CarbonImmutable::now('Asia/Manila')->startOfDay();
        $from = isset($data['from']) ? CarbonImmutable::createFromFormat('!Y-m-d', $data['from'], 'Asia/Manila') : $today->startOfMonth();
        $until = (isset($data['through']) ? CarbonImmutable::createFromFormat('!Y-m-d', $data['through'], 'Asia/Manila') : $today)->addDay();
        abort_if($from->greaterThanOrEqualTo($until) || $from->diffInDays($until) > 366, 422, 'Choose an ordered reporting period of at most 366 days.');

        return [$from->utc(), $until->utc()];
    }
}
