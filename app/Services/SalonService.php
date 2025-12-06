<?php

namespace App\Services;

use App\Models\Salon;
use App\Models\SalonImage;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SalonService
{
    /**
     * Create a new salon.
     */
    public function createSalon(array $data, User $owner): Salon
    {
        return DB::transaction(function () use ($data, $owner) {
            // Create salon
            $salon = Salon::create([
                'name' => $data['name'],
                'description' => $data['description'],
                'address' => $data['address'],
                'city' => $data['city'],
                'postal_code' => $data['postal_code'] ?? null,
                'country' => $data['country'] ?? 'Bosna i Hercegovina',
                'phone' => $data['phone'],
                'email' => $data['email'],
                'website' => $data['website'] ?? null,
                'working_hours' => $data['working_hours'],
                'location' => $data['location'],
                'target_audience' => $data['target_audience'] ?? [
                    'women' => true,
                    'men' => true,
                    'children' => true,
                ],
                'amenities' => $data['amenities'] ?? [],
                'social_media' => $data['social_media'] ?? null,
                'owner_id' => $owner->id,
                'status' => 'pending',
            ]);

            // Update owner's salon_id
            $owner->update(['salon_id' => $salon->id]);

            return $salon;
        });
    }

    /**
     * Update an existing salon.
     */
    public function updateSalon(Salon $salon, array $data): Salon
    {
        return DB::transaction(function () use ($salon, $data) {
            $salon->update($data);
            return $salon;
        });
    }

    /**
     * Get the nearest salons based on location.
     */
    public function getNearestSalons(float $latitude, float $longitude, float $radius = 10): array
    {
        // Using Haversine formula to calculate distance
        $salons = Salon::approved()
            ->selectRaw("*,
                (6371 * acos(cos(radians(?)) * cos(radians(JSON_EXTRACT(location, '$.lat'))) *
                cos(radians(JSON_EXTRACT(location, '$.lng')) - radians(?)) +
                sin(radians(?)) * sin(radians(JSON_EXTRACT(location, '$.lat'))))) AS distance",
                [$latitude, $longitude, $latitude])
            ->having('distance', '<=', $radius)
            ->orderBy('distance')
            ->with(['images', 'services'])
            ->get();

        return $salons->toArray();
    }

    /**
     * Get available time slots for a salon, staff, and date.
     */
    public function getAvailableTimeSlots(Salon $salon, string $staffId, string $date, string $serviceId): array
    {
        $staff = Staff::with(['breaks', 'vacations', 'salon.salonBreaks', 'salon.salonVacations', 'services'])
            ->findOrFail($staffId);
        $service = Service::findOrFail($serviceId);

        // Check if staff belongs to salon
        if ($staff->salon_id !== $salon->id) {
            return [];
        }

        // Check if staff can perform this service
        if (!$staff->services->contains($service->id)) {
            return [];
        }

        // Convert date format if needed (from European DD.MM.YYYY to ISO YYYY-MM-DD)
        $isoDate = $date;
        if (preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $date)) {
            $isoDate = \Carbon\Carbon::createFromFormat('d.m.Y', $date)->format('Y-m-d');
        }

        // Get day of week
        $dayOfWeek = strtolower(date('l', strtotime($isoDate)));

        // Check salon working hours
        $salonHours = $salon->working_hours[$dayOfWeek] ?? null;
        if (!$salonHours || !$salonHours['is_open']) {
            return [];
        }

        // Check staff working hours
        $staffHours = $staff->working_hours[$dayOfWeek] ?? null;
        if (!$staffHours || !$staffHours['is_working']) {
            return [];
        }

        // Determine start and end times (use the later start time and earlier end time)
        $startTime = max($salonHours['open'], $staffHours['start']);
        $endTime = min($salonHours['close'], $staffHours['end']);

        // Generate all possible time slots (30-minute intervals)
        $slots = $this->generateTimeSlots($startTime, $endTime);

        // Filter out slots that are not available
        $availableSlots = [];
        foreach ($slots as $slot) {
            if ($staff->isAvailable($date, $slot, $service->duration)) {
                $availableSlots[] = $slot;
            }
        }

        return $availableSlots;
    }

    /**
     * Generate time slots between start and end time.
     */
    private function generateTimeSlots(string $startTime, string $endTime, int $interval = 30): array
    {
        $slots = [];
        $start = strtotime($startTime);
        $end = strtotime($endTime);

        for ($time = $start; $time < $end; $time += $interval * 60) {
            $slots[] = date('H:i', $time);
        }

        return $slots;
    }
}
