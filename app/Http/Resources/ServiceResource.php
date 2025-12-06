<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
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
            'description' => $this->description,
            'duration' => $this->duration,
            'price' => $this->price,
            'discount_price' => $this->discount_price,
            'category' => $this->category,
            'salon_id' => $this->salon_id,
            'is_active' => $this->is_active,
            'staff' => $this->when($this->relationLoaded('staff'), function () {
                return $this->staff->map(function ($staff) {
                    return [
                        'id' => $staff->id,
                        'name' => $staff->name,
                        'role' => $staff->role,
                    ];
                });
            }),
            'staff_ids' => $this->when($this->relationLoaded('staff'), function () {
                return $this->staff->pluck('id');
            }),
            'created_at' => $this->created_at->format('d.m.Y'),
            'updated_at' => $this->updated_at->format('d.m.Y'),
        ];
    }
}
