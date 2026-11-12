<?php

namespace App\Http\Resources;

use App\Services\TechnicianFinder;
use Illuminate\Http\Resources\Json\JsonResource;

class TechnicianResource extends JsonResource
{
    public function toArray($request): array
    {
        $coordinates = $this->resource->publicCoordinates();
        $paid = $this->resource->getAttribute('is_pro') !== null ? (bool) $this->resource->getAttribute('is_pro') : $this->resource->hasProSubscription();
        $premium = $this->resource->getAttribute('is_premium') !== null ? (bool) $this->resource->getAttribute('is_premium') : $this->resource->hasPremiumSubscription();

        return [
            'id' => $this->id, 'name' => $this->user->name, 'biography' => $this->biography,
            'photo_url' => $this->photo_path ? url($request->is('api/v1/technician/*') ? '/api/v1/technician/photo' : '/api/v1/technicians/'.$this->id.'/photo') : null,
            'years_experience' => $this->years_experience, 'city' => $this->city, 'province' => $this->province,
            'latitude' => $coordinates[0] ?? null,
            'longitude' => $coordinates[1] ?? null,
            'availability' => $this->availability, 'verified' => $this->verification_status === 'approved',
            'verification_status' => $this->verification_status,
            'is_pro' => $paid, 'is_premium' => $premium,
            'subscription_tier' => $premium ? 'premium' : ($paid ? 'pro' : 'free'),
            'average_rating' => round((float) $this->average_rating, 1), 'reviews_count' => $this->reviews_count ?? 0,
            'specializations' => $this->specializations->map->only(['id', 'name']), 'device_types' => $this->deviceTypes->map->only(['id', 'name']),
            'service_areas' => $this->serviceAreas->map->only(['city', 'province']),
            'matching' => $this->resource->getAttribute('matching') ?? app(TechnicianFinder::class)->score($this->resource, []),
        ];
    }
}
