<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CheckinResource extends JsonResource
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
            'service_type' => $this->service_type,
            'service_description' => $this->service_description,
            'priority' => $this->priority,
            'status' => $this->status,
            'estimated_duration' => $this->estimated_duration,
            'estimated_cost' => $this->estimated_cost,
            'actual_cost' => $this->actual_cost,
            'checkin_time' => $this->checkin_time?->format('Y-m-d H:i:s'),
            'checkout_time' => $this->checkout_time?->format('Y-m-d H:i:s'),
            'assigned_technician' => $this->assigned_technician,
            'service_bay' => $this->service_bay,
            'technician_notes' => $this->technician_notes,
            'customer_notes' => $this->customer_notes,
            'customer_notified' => $this->customer_notified,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),

            // Computed attributes
            'status_badge_color' => $this->status_badge_color,
            'priority_badge_color' => $this->priority_badge_color,
            'duration' => $this->duration,
            'time_ago' => $this->time_ago,

            // Relationships (only when loaded)
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'vehicle' => new VehicleResource($this->whenLoaded('vehicle')),
        ];
    }
}
