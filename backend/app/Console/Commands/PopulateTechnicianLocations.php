<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\TechnicianProfile;
use App\Services\ServiceLocation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PopulateTechnicianLocations extends Command
{
    protected $signature = 'erepair:technician-locations {--online : Look up locations absent from the bundled catalog}';

    protected $description = 'Fill missing public coordinates for approved technicians, preserving existing map locations';

    public function handle(ServiceLocation $locations): int
    {
        $updated = 0;
        $unresolved = 0;
        TechnicianProfile::visible()->where(fn ($query) => $query->whereNull('latitude')->orWhereNull('longitude'))
            ->chunkById(100, function ($profiles) use ($locations, &$updated, &$unresolved) {
                foreach ($profiles as $profile) {
                    $point = $locations->resolve($profile->city, $profile->province, (bool) $this->option('online'));
                    if ($point === null) {
                        $unresolved++;

                        continue;
                    }
                    DB::transaction(function () use ($profile, $point, &$updated) {
                        $current = TechnicianProfile::visible()->lockForUpdate()->find($profile->id);
                        if (! $current || $current->publicCoordinates() !== null || $current->city !== $profile->city || $current->province !== $profile->province) {
                            return;
                        }
                        $current->update(['latitude' => $point[0], 'longitude' => $point[1]]);
                        ActivityLog::create(['action' => 'technician.location_populated', 'subject_type' => 'technician_profile', 'subject_id' => $current->id]);
                        $updated++;
                    });
                }
            });
        $this->info("Updated {$updated} approved technician location(s); {$unresolved} require administrator review.");

        return self::SUCCESS;
    }
}
