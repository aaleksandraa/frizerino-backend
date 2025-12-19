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
use App\Services\NotificationService;
use App\Mail\AppointmentConfirmationMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class WidgetController extends Controller
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Get widget data (salon, services, staff)
     */
    public function show(Request $request, string $salonSlug): JsonResponse
    {
        $apiKey = $request->query('key');

        if (!$apiKey) {
            return response()->json(['error' => 'API key is required'], 401);
        }

        // PostgreSQL requires DB::raw('true') for boolean columns
        $widgetSetting = WidgetSetting::where('api_key', $apiKey)
            ->where('is_active', DB::raw('true'))
            ->first();

        if (!$widgetSetting) {
            return response()->json(['error' => 'Invalid or inactive API key'], 401);
        }

        $referer = $request->headers->get('referer');
        $domain = $referer ? parse_url($referer, PHP_URL_HOST) : null;

        if (!$widgetSetting->isDomainAllowed($domain)) {
            $this->logAnalytics($widgetSetting->salon_id, WidgetAnalytics::EVENT_ERROR, $request, [
                'error' => 'Domain not allowed',
                'domain' => $domain,
            ], $widgetSetting->id);
            return response()->json(['error' => 'Domain not allowed'], 403);
        }

        // PostgreSQL requires DB::raw('true') for boolean columns
        // Sort services by display_order, staff by display_order
        $salon = Salon::with(['services' => function($query) {
            $query->where('is_active', DB::raw('true'))
                  ->orderBy('display_order')
                  ->orderBy('id');
        }, 'staff' => function($query) {
            $query->where('is_active', DB::raw('true'))
                  ->orderBy('display_order')
                  ->orderBy('name');
        }])
            ->where('slug', $salonSlug)
            ->where('id', $widgetSetting->salon_id)
            ->where('status', 'approved')
            ->first();

        if (!$salon) {
            $this->logAnalytics($widgetSetting->salon_id, WidgetAnalytics::EVENT_ERROR, $request, [
                'error' => 'Salon not found',
                'slug' => $salonSlug,
            ], $widgetSetting->id);
            return response()->json(['error' => 'Salon not found or inactive'], 404);
        }

        $this->logAnalytics($widgetSetting->salon_id, WidgetAnalytics::EVENT_VIEW, $request, [], $widgetSetting->id);
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
                'category_order' => $salon->category_order,
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
                    'display_order' => $service->display_order,
                    'staff_ids' => $service->staff_ids,
                ];
            }),
            'staff' => $salon->staff->map(function($staff) {
                $avatarUrl = null;
                if ($staff->avatar) {
                    $avatarUrl = str_starts_with($staff->avatar, 'http')
                        ? $staff->avatar
                        : config('app.url') . '/storage/' . $staff->avatar;
                }
                return [
                    'id' => $staff->id,
                    'name' => $staff->name,
                    'role' => $staff->role,
                    'avatar' => $avatarUrl,
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
     */
    public function availableSlots(Request $request): JsonResponse
    {
        $apiKey = $request->input('key');

        $widgetSetting = WidgetSetting::where('api_key', $apiKey)
            ->where('is_active', DB::raw('true'))
            ->first();

        if (!$widgetSetting) {
            return response()->json(['error' => 'Invalid API key'], 401);
        }

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

        if ($staff->salon_id != $widgetSetting->salon_id) {
            return response()->json(['error' => 'Invalid staff for this salon'], 403);
        }

        $servicesData = array_map(function($service) use ($staffId) {
            return [
                'serviceId' => $service['serviceId'],
                'staffId' => $staffId,
                'duration' => $service['duration'],
            ];
        }, $request->input('services'));

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
     */
    public function book(Request $request): JsonResponse
    {
        $apiKey = $request->input('api_key');

        $widgetSetting = WidgetSetting::where('api_key', $apiKey)
            ->where('is_active', DB::raw('true'))
            ->first();

        if (!$widgetSetting) {
            return response()->json(['error' => 'Invalid API key'], 401);
        }

        $referer = $request->headers->get('referer');
        $domain = $referer ? parse_url($referer, PHP_URL_HOST) : null;

        if (!$widgetSetting->isDomainAllowed($domain)) {
            $this->logAnalytics($widgetSetting->salon_id, WidgetAnalytics::EVENT_ERROR, $request, [
                'error' => 'Domain not allowed',
                'domain' => $domain,
            ], $widgetSetting->id);
            return response()->json(['error' => 'Domain not allowed'], 403);
        }

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

        if ($request->input('salon_id') != $widgetSetting->salon_id) {
            $this->logAnalytics($widgetSetting->salon_id, WidgetAnalytics::EVENT_ERROR, $request, [
                'error' => 'Invalid salon',
            ], $widgetSetting->id);
            return response()->json(['error' => 'Invalid salon'], 403);
        }

        $staff = Staff::findOrFail($request->input('staff_id'));
        if ($staff->salon_id != $widgetSetting->salon_id) {
            return response()->json(['error' => 'Invalid staff for this salon'], 403);
        }

        try {
            $salon = Salon::findOrFail($request->input('salon_id'));
            $dateForDb = Carbon::createFromFormat('d.m.Y', $request->input('date'))->format('Y-m-d');
            $startTime = $request->input('time');

            $serviceIds = $request->has('services')
                ? array_column($request->input('services'), 'id')
                : [$request->input('service_id')];

            // Calculate total duration for all services
            $totalDuration = 0;
            foreach ($serviceIds as $serviceId) {
                $service = Service::findOrFail($serviceId);
                $totalDuration += $service->duration;
            }

            // Calculate end time for all services combined
            $startParts = explode(':', $startTime);
            $startMinutes = (int)$startParts[0] * 60 + (int)$startParts[1];
            $totalEndMinutes = $startMinutes + $totalDuration;
            $totalEndTime = sprintf('%02d:%02d', floor($totalEndMinutes / 60), $totalEndMinutes % 60);

            // CRITICAL: Check for overlapping appointments BEFORE creating
            $overlappingAppointment = Appointment::where('staff_id', $request->input('staff_id'))
                ->where('date', $dateForDb)
                ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
                ->where(function($query) use ($startTime, $totalEndTime) {
                    // Check if new appointment overlaps with any existing appointment
                    // Overlap occurs when: new_start < existing_end AND new_end > existing_start
                    $query->whereRaw("? < end_time AND ? > time", [$startTime, $totalEndTime]);
                })
                ->first();

            if ($overlappingAppointment) {
                return response()->json([
                    'error' => 'Žao nam je, neko se u međuvremenu zakazao u to vrijeme. Molimo odaberite drugo vrijeme.',
                    'code' => 'TIME_SLOT_TAKEN',
                    'redirect_to_time' => true
                ], 409); // 409 Conflict
            }

            $appointments = [];
            $currentTime = $startTime;
            $totalPrice = 0;
            $initialStatus = ($salon->auto_confirm || $staff->auto_confirm) ? 'confirmed' : 'pending';

            foreach ($serviceIds as $serviceId) {
                $service = Service::findOrFail($serviceId);

                $timeParts = explode(':', $currentTime);
                $currentMinutes = (int)$timeParts[0] * 60 + (int)$timeParts[1];
                $endMinutes = $currentMinutes + $service->duration;
                $endTime = sprintf('%02d:%02d', floor($endMinutes / 60), $endMinutes % 60);

                $appointment = Appointment::create([
                    'client_id' => null,
                    'salon_id' => $request->input('salon_id'),
                    'service_id' => $serviceId,
                    'staff_id' => $request->input('staff_id'),
                    'date' => $dateForDb,
                    'time' => $currentTime,
                    'end_time' => $endTime,
                    'status' => $initialStatus,
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

                $this->notificationService->sendNewAppointmentNotifications($appointment);
                $currentTime = $endTime;
            }

            $guestEmail = $request->input('guest_email');
            if ($guestEmail && count($appointments) > 0) {
                try {
                    Mail::to($guestEmail)->send(new AppointmentConfirmationMail($appointments[0]));
                } catch (\Exception $e) {
                    Log::warning('Widget: Failed to send confirmation email: ' . $e->getMessage());
                }
            }

            $this->logAnalytics($widgetSetting->salon_id, WidgetAnalytics::EVENT_BOOKING, $request, [
                'appointment_ids' => array_map(fn($a) => $a->id, $appointments),
                'service_ids' => $serviceIds,
                'staff_id' => $request->input('staff_id'),
                'total_price' => $totalPrice,
            ], $widgetSetting->id);

            $widgetSetting->increment('total_bookings', count($appointments));

            return response()->json([
                'success' => true,
                'message' => 'Rezervacija uspješno kreirana',
                'appointments' => array_map(function($apt) {
                    return [
                        'id' => $apt->id,
                        'date' => Carbon::parse($apt->date)->format('d.m.Y'),
                        'time' => $apt->time,
                        'status' => $apt->status,
                    ];
                }, $appointments),
                'total_price' => $totalPrice,
                'status' => $initialStatus,
            ], 201);

        } catch (\Exception $e) {
            Log::error('Widget booking error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            try {
                $this->logAnalytics($widgetSetting->salon_id, WidgetAnalytics::EVENT_ERROR, $request, [
                    'error' => 'Booking failed',
                    'message' => $e->getMessage(),
                ], $widgetSetting->id);
            } catch (\Exception $analyticsError) {
                Log::warning('Widget analytics log failed: ' . $analyticsError->getMessage());
            }

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
            Log::warning('Widget analytics log failed: ' . $e->getMessage());
        }
    }
}
