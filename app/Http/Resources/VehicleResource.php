<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VehicleResource extends JsonResource
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
            'license_plate' => $this->license_plate,
            'make' => $this->make,
            'model' => $this->model,
            'year' => $this->year,
            'color' => $this->color,
            'mileage' => $this->mileage,
            'fuel_type' => $this->fuel_type,
            'transmission' => $this->transmission,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),

            // Computed attributes
            'full_name' => $this->full_name,
            'display_name' => $this->display_name,

            // Relationships (only when loaded)
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'active_checkins' => CheckinResource::collection($this->whenLoaded('checkins', function () {
                return $this->checkins->where('status', '!=', 'completed')->where('status', '!=', 'cancelled');
            })),

            // Counts
            'checkins_count' => $this->when(isset($this->checkins_count), $this->checkins_count),
            'active_checkins_count' => $this->when(isset($this->active_checkins_count), $this->active_checkins_count),
        ];
    }
}
