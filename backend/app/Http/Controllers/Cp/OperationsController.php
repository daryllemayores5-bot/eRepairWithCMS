<?php

namespace App\Http\Controllers\Cp;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\CmsUser;
use App\Services\CmsAccount;
use App\Models\DeviceBrand;
use App\Models\DeviceType;
use App\Models\RepairCategory;
use App\Models\RepairRequest;
use App\Models\Review;
use App\Models\Specialization;
use App\Models\TechnicianProfile;
use App\Models\User;
use App\Services\TechnicianVerification;
use App\Support\CsvCell;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OperationsController extends Controller
{
    private const CATALOG = ['device-types' => DeviceType::class, 'device-brands' => DeviceBrand::class, 'specializations' => Specialization::class, 'repair-categories' => RepairCategory::class];

    private function actor(Request $request): User
    {
        $identity = CmsAccount::identity($request->user('cp'));
        abort_unless($identity instanceof CmsUser, 403);

        return $identity->operator()->firstOrFail();
    }

    public function photo(TechnicianProfile $technician)
    {
        abort_unless($technician->photo_path && Storage::disk('local')->exists($technician->photo_path), 404);

        return Storage::disk('local')->response($technician->photo_path, null, [
            'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function index(Request $request)
    {
        $filter = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'string', 'max:40']]);

        return view('cp.erepair', [
            'stats' => ['Customers' => User::where('role', 'customer')->count(), 'Technicians' => TechnicianProfile::count(), 'Pending approvals' => TechnicianProfile::where('verification_status', 'pending')->count(), 'Verified' => TechnicianProfile::where('verification_status', 'approved')->count(), 'Repair requests' => RepairRequest::count(), 'Ongoing' => RepairRequest::whereIn('status', ['scheduled', 'diagnosing', 'awaiting_customer_approval', 'in_repair', 'ready_for_pickup'])->count(), 'Completed' => RepairRequest::where('status', 'completed')->count()],
            'technicians' => TechnicianProfile::with(['user', 'deviceTypes', 'specializations'])->whereHas('user', fn ($q) => $q->where('name', 'ilike', '%'.($filter['search'] ?? '').'%'))->paginate(15, ['*'], 'technicians_page'),
            'customers' => User::where('role', 'customer')->where(fn ($q) => $q->where('name', 'ilike', '%'.($filter['search'] ?? '').'%')->orWhere('email', 'ilike', '%'.($filter['search'] ?? '').'%'))->paginate(15, ['*'], 'customers_page'),
            'repairs' => RepairRequest::with(['customer', 'technician.user', 'history.actor', 'estimates', 'appointments'])->when($filter['status'] ?? null, fn ($q, $s) => $q->where('status', $s))->latest()->paginate(15, ['*'], 'repairs_page'),
            'reviews' => Review::with('customer:id,name')->latest()->paginate(15, ['*'], 'reviews_page'),
            'activity' => ActivityLog::latest()->limit(30)->get(),
            'catalog' => collect(self::CATALOG)->map(fn ($class) => $class::orderBy('name')->get()),
        ]);
    }

    public function verify(Request $request, TechnicianProfile $technician, TechnicianVerification $service)
    {
        $data = $request->validate(['decision' => ['required', 'in:approved,rejected,pending'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $service->decide($this->actor($request), $technician, $data['decision'], $data['notes'] ?? null);

        return back()->with('success', 'Technician verification updated.');
    }

    public function location(Request $request, TechnicianProfile $technician)
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);
        DB::transaction(function () use ($request, $technician, $data) {
            $profile = TechnicianProfile::lockForUpdate()->findOrFail($technician->id);
            // Store only an approximate public reference, never a residential GPS pin.
            $profile->update(['latitude' => round((float) $data['latitude'], 2), 'longitude' => round((float) $data['longitude'], 2)]);
            ActivityLog::create(['actor_id' => $this->actor($request)->id, 'action' => 'technician.location_updated', 'subject_type' => 'technician_profile', 'subject_id' => $profile->id]);
        });

        return back()->with('success', 'Approximate public service location updated.');
    }

    public function user(Request $request, User $user)
    {
        abort_if($user->role === 'admin', 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'account_status' => ['required', 'in:active,suspended']]);
        DB::transaction(function () use ($user, $data, $request) {
            $user->name = $data['name'];
            $user->account_status = $data['account_status'];
            $user->save();
            if ($user->account_status === 'suspended') {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }
            ActivityLog::create(['actor_id' => $this->actor($request)->id, 'action' => 'user.updated', 'subject_type' => 'user', 'subject_id' => $user->id, 'metadata' => $data]);
        });

        return back()->with('success', 'Account updated.');
    }

    public function review(Request $request, Review $review)
    {
        $data = $request->validate(['hidden' => ['required', 'boolean']]);
        $review->update($data);
        ActivityLog::create(['actor_id' => $this->actor($request)->id, 'action' => 'review.moderated', 'subject_type' => 'review', 'subject_id' => $review->id, 'metadata' => $data]);

        return back()->with('success', 'Review visibility updated.');
    }

    public function catalog(Request $request, string $kind)
    {
        abort_unless(isset(self::CATALOG[$kind]), 404);
        $data = $request->validate(['id' => ['nullable', 'integer'], 'name' => ['required', 'string', 'max:100'], 'active' => ['required', 'boolean'], 'device_type_id' => ['nullable', 'exists:device_types,id'], 'specialization_id' => ['nullable', 'exists:specializations,id']]);
        $class = self::CATALOG[$kind];
        $record = isset($data['id']) ? $class::findOrFail($data['id']) : new $class;
        $record->name = $data['name'];
        $record->active = $data['active'];
        if (! $record->exists) {
            $record->slug = Str::slug($data['name']).'-'.Str::lower(Str::random(5));
        }
        if ($kind === 'repair-categories') {
            $record->device_type_id = $data['device_type_id'] ?? null;
            $record->specialization_id = $data['specialization_id'] ?? null;
        }
        $record->save();
        ActivityLog::create(['actor_id' => $this->actor($request)->id, 'action' => 'catalog.saved', 'subject_type' => $kind, 'subject_id' => $record->id]);

        return back()->with('success', 'Catalog saved.');
    }

    public function report()
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Technician', 'City', 'Verification', 'Requests', 'Completed', 'Average rating'], ',', '"', '');
            TechnicianProfile::with('user')->withCount('repairs')->withCount(['repairs as completed_count' => fn ($q) => $q->where('status', 'completed')])->withAvg('reviews', 'rating')->each(function ($p) use ($out) {
                fputcsv($out, array_map([CsvCell::class, 'forSpreadsheet'], [$p->user->name, $p->city, $p->verification_status, $p->repairs_count, $p->completed_count, round((float) $p->reviews_avg_rating, 2)]), ',', '"', '');
            });
            fclose($out);
        }, 'erepair-report-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv', 'Cache-Control' => 'no-store, private']);
    }
}
