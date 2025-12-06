<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Calculate rating dynamically from reviews
        $reviews = $this->reviews()->get();
        $reviewCount = $reviews->count();
        $averageRating = $reviewCount > 0 ? round($reviews->avg('rating'), 1) : 0;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'role' => $this->role,
            'bio' => $this->bio,
            'avatar' => $this->avatar ? asset('storage/' . $this->avatar) : null,
            'working_hours' => $this->working_hours,
            'specialties' => $this->specialties,
            'rating' => $averageRating,
            'review_count' => $reviewCount,
            'is_active' => $this->is_active,
            'auto_confirm' => $this->auto_confirm,
            'salon_id' => $this->salon_id,
            'services' => $this->when($this->relationLoaded('services'), function () {
                return ServiceResource::collection($this->services);
            }),
            'breaks' => $this->when($this->relationLoaded('breaks'), function () {
                return $this->breaks;
            }),
            'vacations' => $this->when($this->relationLoaded('vacations'), function () {
                return $this->vacations;
            }),
            'created_at' => $this->created_at->format('d.m.Y'),
            'updated_at' => $this->updated_at->format('d.m.Y'),
        ];
    }
}
