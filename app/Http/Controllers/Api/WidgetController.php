<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Salon;
use App\Models\WidgetSetting;
use App\Models\WidgetAnalytics;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\Staff;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class WidgetController extends Controller
{
    /**
     * Get widget data (salon, services, staff)
     */
    public function show(Request $request, string $salonSlug): JsonResponse
    {
        $apiKey = $request->query('key');

        // Validate API key
        if (!$apiKey) {
            return response()->json(['error' => 'API key is required'], 401);
        }

        $widgetSetting = WidgetSetting::where('api_key', $apiKey)
            ->whereRaw('is_active = true')
            ->first();

        if (!$widgetSetting) {
            return response()->json(['error' => 'Invalid or inactive API key'], 401);
        }

        // Validate domain (if whitelist is set)
        $referer = $request->headers->get('referer');
        $domain = $referer ? parse_url($referer, PHP_URL_HOST) : null;

        if (!$widgetSetting->isDomainAllowed($domain)) {
            $this->logAnalytics($widgetSetting->salon_id, WidgetAnalytics::EVENT_ERROR, $request, [
                'error' => 'Domain not allowed',
                'domain' => $domain,
            ], $widgetSetting->id);
            return response()->json(['error' => 'Domain not allowed'], 403);
        }

        // Get salon data
        $salon = Salon::with(['services' => function($query) {
            $query->whereRaw('is_active = true')->orderBy('name');
        }, 'staff' => function($query) {
            $query->whereRaw('is_active = true')->orderBy('name');
        }])
            ->where('slug', $salonSlug)
            ->where('id', $widgetSetting->salon_id)
            ->whereRaw('is_active = true')
            ->first();

        if (!$salon) {
            $this->logAnalytics($widgetSetting->salon_id, WidgetAnalytics::EVENT_ERROR, $request, [
                'error' => 'Salon not found',
                'slug' => $salonSlug,
            ], $widgetSetting->id);
            return response()->json(['error' => 'Salon not found or inactive'], 404);
        }

        // Log view analytics
        $this->logAnalytics($widgetSetting->salon_id, WidgetAnalytics::EVENT_VIEW, $request, [], $widgetSetting->id);

        // Update last used timestamp
        $widgetSetting->update(['last_used_at' => now()]);

        return response()->json([
            'salon' => [
                'id' => $salon->id,
                'name' => $salon->name,
                'slug' => $salon->slug,
                'description' => $salon->description,
                'address' => $salon->address,
                'city' => $salon->city,
                'phone' => $salon->phone,
                'email' => $salon->email,
                'working_hours' => $salon->working_hours,
                'images' => $salon->images,
            ],
            'services' => $salon->services->map(function($service) {
                return [
                    'id' => $service->id,
                    'name' => $service->name,
                    'description' => $service->description,
                    'price' => $service->price,
                    'discount_price' => $service->discount_price,
                    'duration' => $service->duration,
                    'category' => $service->category,
                    'staff_ids' => $service->staff_ids,
                ];
            }),
            'staff' => $salon->staff->map(function($staff) {
                return [
                    'id' => $staff->id,
                    'name' => $staff->name,
                    'role' => $staff->role,
                    'avatar' => $staff->avatar,
                    'bio' => $staff->bio,
                    'rating' => $staff->rating,
                    'review_count' => $staff->review_count,
                ];
            }),
            'theme' => $widgetSetting->getMergedTheme(),
            'settings' => $widgetSetting->settings ?? [],
        ]);
    }

    /**
     * Get available time slots for multiple services
     * Uses the same professional logic as the main booking system
     */
    public function availableSlots(Request $request): JsonResponse
    {
        $apiKey = $request->input('key');

        // Validate API key
        $widgetSetting = WidgetSetting::where('api_key', $apiKey)
            ->whereRaw('is_active = true')
            ->first();

        if (!$widgetSetting) {
            return response()->json(['error' => 'Invalid API key'], 401);
        }

        // Validate domain
        $referer = $request->headers->get('referer');
        $domain = $referer ? parse_url($referer, PHP_URL_HOST) : null;

        if (!$widgetSetting->isDomainAllowed($domain)) {
            return response()->json(['error' => 'Domain not allowed'], 403);
        }

        $validator = Validator::make($request->all(), [
            'staff_id' => 'required|integer|exists:staff,id',
            'date' => ['required', 'regex:/^\d{2}\.\d{2}\.\d{4}$/'],
            'services' => 'required|array|min:1',
            'services.*.serviceId' => 'required|exists:services,id',
            'services.*.duration' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        $staffId = $request->input('staff_id');
        $staff = Staff::findOrFail($staffId);

        // Verify staff belongs to widget's salon
        if ($staff->salon_id != $widgetSetting->salon_id) {
            return response()->json(['error' => 'Invalid staff for this salon'], 403);
        }

        // Build services array for SalonService
        $servicesData = array_map(function($service) use ($staffId) {
            return [
                'serviceId' => $service['serviceId'],
                'staffId' => $staffId,
                'duration' => $service['duration'],
            ];
        }, $request->input('services'));

        // Use the professional SalonService for slot calculation
        $salon = Salon::findOrFail($widgetSetting->salon_id);
        $salonService = app(\App\Services\SalonService::class);

        $slots = $salonService->getAvailableTimeSlotsForMultipleServices(
            $salon,
            $request->input('date'),
            $servicesData
        );

        return response()->json(['slots' => $slots]);
    }

    /**
     * Book appointment(s) via widget
     * Supports multiple services with the same staff member
     */
    public function book(Request $request): JsonResponse
    {
        $apiKey = $request->input('api_key');

        // Validate API key
        $widgetSetting = WidgetSetting::where('api_key', $apiKey)
            ->whereRaw('is_active = true')
            ->first();

        if (!$widgetSetting) {
            return response()->json(['error' => 'Invalid API key'], 401);
        }

        // Validate domain
        $referer = $request->headers->get('referer');
        $domain = $referer ? parse_url($referer, PHP_URL_HOST) : null;

        if (!$widgetSetting->isDomainAllowed($domain)) {
            $this->logAnalytics($widgetSetting->salon_id, WidgetAnalytics::EVENT_ERROR, $request, [
                'error' => 'Domain not allowed',
                'domain' => $domain,
            ], $widgetSetting->id);
            return response()->json(['error' => 'Domain not allowed'], 403);
        }

        // Validate request - supports both single service and multiple services
        $validator = Validator::make($request->all(), [
            'salon_id' => 'required|integer|exists:salons,id',
            'staff_id' => 'required|integer|exists:staff,id',
            'date' => ['required', 'regex:/^\d{2}\.\d{2}\.\d{4}$/'],
            'time' => 'required|date_format:H:i',
            'guest_name' => 'required|string|max:255|min:3',
            'guest_email' => 'nullable|email|max:255',
            'guest_phone' => 'required|string|min:8|max:20',
            'guest_address' => 'required|string|max:255|min:5',
            'notes' => 'nullable|string|max:1000',
            // Support both single service_id and services array
            'service_id' => 'required_without:services|integer|exists:services,id',
            'services' => 'required_without:service_id|array|min:1',
            'services.*.id' => 'required_with:services|exists:services,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Verify salon_id matches widget
        if ($request->input('salon_id') != $widgetSetting->salon_id) {
            $this->logAnalytics($widgetSetting->salon_id, WidgetAnalytics::EVENT_ERROR, $request, [
                'error' => 'Invalid salon',
            ], $widgetSetting->id);
            return response()->json(['error' => 'Invalid salon'], 403);
        }

        // Verify staff belongs to widget's salon
        $staff = Staff::findOrFail($request->input('staff_id'));
        if ($staff->salon_id != $widgetSetting->salon_id) {
            return response()->json(['error' => 'Invalid staff for this salon'], 403);
        }

        try {
            $appointments = [];
            $currentTime = $request->input('time');
            $totalPrice = 0;

            // Get service IDs - support both formats
            $serviceIds = $request->has('services')
                ? array_column($request->input('services'), 'id')
                : [$request->input('service_id')];

            foreach ($serviceIds as $serviceId) {
                $service = Service::findOrFail($serviceId);

                // Calculate end time for this appointment
                $timeParts = explode(':', $currentTime);
                $currentMinutes = (int)$timeParts[0] * 60 + (int)$timeParts[1];
                $endMinutes = $currentMinutes + $service->duration;
                $endTime = sprintf('%02d:%02d', floor($endMinutes / 60), $endMinutes % 60);

                // Create appointment
                $appointment = Appointment::create([
                    'client_id' => null,
                    'salon_id' => $request->input('salon_id'),
                    'service_id' => $serviceId,
                    'staff_id' => $request->input('staff_id'),
                    'date' => $request->input('date'),
                    'time' => $currentTime,
                    'end_time' => $endTime,
                    'status' => 'pending',
                    'client_name' => $request->input('guest_name'),
                    'client_email' => $request->input('guest_email'),
                    'client_phone' => $request->input('guest_phone'),
                    'is_guest' => true,
                    'guest_address' => $request->input('guest_address'),
                    'notes' => $request->input('notes'),
                    'booking_source' => 'widget',
                    'total_price' => $service->discount_price ?? $service->price,
                    'payment_status' => 'pending',
                ]);

                $appointments[] = $appointment;
                $totalPrice += $service->discount_price ?? $service->price;

                // Calculate next service start time
                $currentTime = $endTime;
            }

            // Log booking analytics
            $this->logAnalytics($widgetSetting->salon_id, WidgetAnalytics::EVENT_BOOKING, $request, [
                'appointment_ids' => array_map(fn($a) => $a->id, $appointments),
                'service_ids' => $serviceIds,
                'staff_id' => $request->input('staff_id'),
                'total_price' => $totalPrice,
            ], $widgetSetting->id);

            // Increment booking counter
            $widgetSetting->increment('total_bookings', count($appointments));

            return response()->json([
                'success' => true,
                'message' => 'Rezervacija uspješno kreirana',
                'appointments' => array_map(function($apt) {
                    return [
                        'id' => $apt->id,
                        'date' => $apt->date,
                        'time' => $apt->time,
                        'status' => $apt->status,
                    ];
                }, $appointments),
                'total_price' => $totalPrice,
            ], 201);

        } catch (\Exception $e) {
            Log::error('Widget booking error: ' . $e->getMessage());

            $this->logAnalytics($widgetSetting->salon_id, WidgetAnalytics::EVENT_ERROR, $request, [
                'error' => 'Booking failed',
                'message' => $e->getMessage(),
            ], $widgetSetting->id);

            return response()->json([
                'error' => 'Greška pri kreiranju rezervacije. Molimo pokušajte ponovo.'
            ], 500);
        }
    }

    /**
     * Log analytics event
     */
    private function logAnalytics(?int $salonId, string $eventType, Request $request, array $metadata = [], ?int $widgetSettingId = null): void
    {
        try {
            $referer = $request->headers->get('referer');
            $domain = $referer ? parse_url($referer, PHP_URL_HOST) : null;

            WidgetAnalytics::create([
                'salon_id' => $salonId,
                'widget_setting_id' => $widgetSettingId,
                'event_type' => $eventType,
                'referrer_domain' => $domain,
                'ip_address' => $request->ip(),
                'user_agent' => substr($request->userAgent() ?? '', 0, 500),
                'metadata' => $metadata,
            ]);
        } catch (\Exception $e) {
            // Silently fail - don't break widget for analytics errors
            Log::warning('Widget analytics log failed: ' . $e->getMessage());
        }
    }

}
