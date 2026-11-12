<?php

namespace App\Services;

use App\Models\RepairCategory;
use App\Models\TechnicianProfile;
use Illuminate\Validation\ValidationException;

class TechnicianFinder
{
    public function criteria(array $filters): array
    {
        if (! empty($filters['repair_category'])) {
            $category = RepairCategory::where('active', true)->find($filters['repair_category']);
            if (! $category) {
                throw ValidationException::withMessages(['repair_category' => 'Choose an active repair problem.']);
            }
            foreach (['device_type' => $category->device_type_id, 'specialization' => $category->specialization_id] as $key => $required) {
                if ($required) {
                    if (! empty($filters[$key]) && (int) $filters[$key] !== $required) {
                        throw ValidationException::withMessages([$key => 'This selection does not match the chosen repair problem.']);
                    }
                    $filters[$key] = $required;
                }
            }
        }

        return $filters;
    }

    public function search(array $filters)
    {
        $filters = $this->criteria($filters);
        $query = TechnicianProfile::visible()->with(['user', 'deviceTypes', 'specializations', 'serviceAreas'])
            ->withExists(['subscriptions as is_pro' => fn ($q) => $q->validPaid(), 'subscriptions as is_premium' => fn ($q) => $q->validPremium()])
            ->withAvg('reviews as average_rating', 'rating')->withCount('reviews');
        $location = array_filter(collect($filters)->only(['city', 'province'])->all(), fn ($value) => $value !== null && $value !== '');
        if ($location) {
            $matchesArea = function ($area) use ($location) {
                foreach ($location as $field => $value) {
                    $area->where($field, 'ilike', '%'.$value.'%');
                }
            };
            $query->where(fn ($q) => $q->where($matchesArea)->orWhereHas('serviceAreas', $matchesArea));
        }
        if (! empty($filters['search'])) {
            $term = '%'.$filters['search'].'%';
            $query->where(fn ($q) => $q->where('biography', 'ilike', $term)->orWhere('city', 'ilike', $term)->orWhere('province', 'ilike', $term)
                ->orWhereHas('user', fn ($u) => $u->where('name', 'ilike', $term))
                ->orWhereHas('specializations', fn ($s) => $s->where('name', 'ilike', $term)));
        }
        if (! empty($filters['device_type'])) {
            $query->whereHas('deviceTypes', fn ($q) => $q->where('device_types.id', $filters['device_type']));
        }
        if (! empty($filters['specialization'])) {
            $query->whereHas('specializations', fn ($q) => $q->where('specializations.id', $filters['specialization']));
        }
        if (! empty($filters['availability'])) {
            $query->where('availability', $filters['availability']);
        }
        if (! empty($filters['min_rating'])) {
            $query->whereRaw('(select coalesce(avg(rating),0) from reviews where reviews.technician_profile_id = technician_profiles.id and hidden = false) >= ?', [$filters['min_rating']]);
        }
        if (($filters['sort'] ?? '') === 'distance' && isset($filters['latitude'], $filters['longitude'])) {
            // Rank the same rounded public points that customers see on the map.
            $query->orderByRaw('case when latitude between -90 and 90 and longitude between -180 and 180 then 6371 * acos(least(1.0, greatest(-1.0, cos(radians(?::double precision)) * cos(radians(round(latitude::numeric, 2)::double precision)) * cos(radians(round(longitude::numeric, 2)::double precision) - radians(?::double precision)) + sin(radians(?::double precision)) * sin(radians(round(latitude::numeric, 2)::double precision))))) end asc nulls last', [$filters['latitude'], $filters['longitude'], $filters['latitude']]);
        } elseif (($filters['sort'] ?? '') === 'rating') {
            $query->orderByRaw('average_rating desc nulls last');
        } elseif (($filters['sort'] ?? '') === 'experience') {
            $query->orderByDesc('years_experience');
        } else {
            $query->orderByRaw("case availability when 'available' then 0 when 'busy' then 1 else 2 end")->orderByRaw('average_rating desc nulls last');
        }

        // Paid priority only breaks ties after the existing relevance/sort rules.
        // Filters, availability, distance and customer-selected sorts retain precedence.
        $results = $query->orderByRaw("case availability when 'available' then 0 when 'busy' then 1 else 2 end")
            ->orderByDesc('is_premium')->orderByDesc('is_pro')->orderBy('technician_profiles.id')->paginate(12)->withQueryString();
        $results->getCollection()->each(fn ($profile) => $profile->setAttribute('matching', $this->score($profile, $filters)));

        return $results;
    }

    public function score(TechnicianProfile $profile, array $filters): array
    {
        $parts = [];
        if (! empty($filters['device_type']) && $profile->deviceTypes->contains('id', (int) $filters['device_type'])) {
            $parts['Compatible device'] = 35;
        }
        if (! empty($filters['specialization']) && $profile->specializations->contains('id', (int) $filters['specialization'])) {
            $parts['Repair specialization'] = 30;
        }
        if (! empty($filters['city']) && (str_contains(mb_strtolower($profile->city), mb_strtolower($filters['city'])) || $profile->serviceAreas->contains(fn ($area) => str_contains(mb_strtolower($area->city), mb_strtolower($filters['city']))))) {
            $parts['Selected service area'] = 20;
        }
        if ($profile->availability === 'available') {
            $parts['Available'] = 10;
        }
        $parts['Customer rating'] = round((float) $profile->average_rating, 1);

        return ['score' => array_sum($parts), 'reasons' => $parts];
    }
}
