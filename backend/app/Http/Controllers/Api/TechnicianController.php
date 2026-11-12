<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AvailabilityRequest;
use App\Http\Requests\FindTechniciansRequest;
use App\Http\Requests\TechnicianProfileRequest;
use App\Http\Resources\ReviewResource;
use App\Http\Resources\TechnicianResource;
use App\Models\TechnicianProfile;
use App\Services\ServiceLocation;
use App\Services\TechnicianFinder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class TechnicianController extends Controller
{
    public function availability(AvailabilityRequest $request)
    {
        $request->user()->technicianProfile()->firstOrFail()->update($request->validated());

        return $this->mine($request);
    }

    public function photo(int $technician)
    {
        $profile = TechnicianProfile::visible()->findOrFail($technician);
        abort_unless($profile->photo_path && Storage::disk('local')->exists($profile->photo_path), 404);

        return Storage::disk('local')->response($profile->photo_path);
    }

    public function uploadPhoto(Request $request)
    {
        abort_unless($request->user()->role === 'technician', 403);
        $request->validate(['photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048']]);
        $path = $request->file('photo')->store('technician-photos', 'local');
        try {
            $old = DB::transaction(function () use ($request, $path) {
                $profile = $request->user()->technicianProfile()->lockForUpdate()->firstOrFail();
                $old = $profile->photo_path;
                $profile->update(['photo_path' => $path]);

                return $old;
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }
        if ($old) {
            Storage::disk('local')->delete($old);
        }

        return $this->mine($request);
    }

    public function myPhoto(Request $request)
    {
        $profile = $request->user()->technicianProfile()->firstOrFail();
        abort_unless($profile->photo_path && Storage::disk('local')->exists($profile->photo_path), 404);

        return Storage::disk('local')->response($profile->photo_path, null, [
            'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function index(FindTechniciansRequest $request, TechnicianFinder $finder)
    {
        return TechnicianResource::collection($finder->search($request->validated()));
    }

    public function show(FindTechniciansRequest $request, int $technician, TechnicianFinder $finder)
    {
        $filters = $finder->criteria($request->validated());
        $profile = TechnicianProfile::visible()->with(['user', 'deviceTypes', 'specializations', 'serviceAreas'])->withAvg('reviews as average_rating', 'rating')->withCount('reviews')->findOrFail($technician);
        $profile->setAttribute('matching', $finder->score($profile, $filters));

        return new TechnicianResource($profile);
    }

    public function reviews(int $technician)
    {
        $profile = TechnicianProfile::visible()->findOrFail($technician);

        return ReviewResource::collection($profile->reviews()->with('customer:id,name')->latest()->paginate(10));
    }

    public function mine(Request $request)
    {
        abort_unless($request->user()->role === 'technician', 403);

        return new TechnicianResource($request->user()->technicianProfile()->with(['user', 'deviceTypes', 'specializations', 'serviceAreas'])->withAvg('reviews as average_rating', 'rating')->withCount('reviews')->firstOrFail());
    }

    public function myReviews(Request $request)
    {
        abort_unless($request->user()->role === 'technician', 403);

        return ReviewResource::collection($request->user()->technicianProfile->reviews()->with('customer:id,name')->latest()->paginate(20));
    }

    public function update(TechnicianProfileRequest $request)
    {
        DB::transaction(function () use ($request) {
            $profile = $request->user()->technicianProfile()->lockForUpdate()->firstOrFail();
            if ($profile->city !== $request->validated('city') || $profile->province !== $request->validated('province')) {
                $coordinates = $profile->verification_status === 'approved'
                    ? app(ServiceLocation::class)->resolve($request->validated('city'), $request->validated('province'))
                    : null;
                if ($profile->verification_status === 'approved' && $coordinates === null) {
                    throw ValidationException::withMessages(['city' => 'This city and province could not be located. Check their spelling or contact the administrator to update your map location.']);
                }
                [$profile->latitude, $profile->longitude] = $coordinates ?? [null, null];
                $profile->save();
            }
            $profile->update($request->safe()->only(['biography', 'years_experience', 'city', 'province', 'availability']));
            $profile->specializations()->sync($request->validated('specialization_ids'));
            $profile->deviceTypes()->sync($request->validated('device_type_ids'));
            $profile->serviceAreas()->delete();
            $profile->serviceAreas()->createMany(collect($request->validated('service_areas', []))->unique(fn ($a) => $a['city'].'|'.$a['province'])->values()->all());
        });

        return $this->mine($request);
    }
}
