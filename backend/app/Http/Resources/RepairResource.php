<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RepairResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id, 'reference' => $this->reference, 'status' => $this->status, 'model' => $this->model,
            'serial_number' => $this->serial_number, 'problem_description' => $this->problem_description,
            'device_type' => $this->deviceType?->name, 'device_brand' => $this->deviceBrand?->name, 'category' => $this->category?->name,
            'customer' => ['id' => $this->customer->id, 'name' => $this->customer->name],
            'technician' => ['id' => $this->technician->id, 'name' => $this->technician->user->name],
            'created_at' => $this->created_at, 'completed_at' => $this->completed_at,
            'history' => $this->whenLoaded('history'), 'estimates' => $this->whenLoaded('estimates'),
            'appointments' => $this->whenLoaded('appointments'), 'attachments' => $this->whenLoaded('attachments'), 'review' => $this->whenLoaded('review'),
        ];
    }
}
