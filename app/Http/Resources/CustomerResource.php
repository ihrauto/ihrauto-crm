<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'city' => $this->city,
            'postal_code' => $this->postal_code,
            'country' => $this->country,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),

            // Computed attributes
            'full_name' => $this->full_name,

            // Relationships (only when loaded)
            'vehicles' => VehicleResource::collection($this->whenLoaded('vehicles')),
            'active_checkins' => CheckinResource::collection($this->whenLoaded('checkins', function () {
                return $this->checkins->where('status', '!=', 'completed')->where('status', '!=', 'cancelled');
            })),

            // Counts
            'vehicles_count' => $this->when(isset($this->vehicles_count), $this->vehicles_count),
            'checkins_count' => $this->when(isset($this->checkins_count), $this->checkins_count),
            'active_checkins_count' => $this->when(isset($this->active_checkins_count), $this->active_checkins_count),
        ];
    }
}
