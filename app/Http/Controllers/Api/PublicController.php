<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SalonResource;
use App\Mail\AppointmentConfirmationMail;
use App\Models\Appointment;
use App\Models\Location;
use App\Models\Salon;
use App\Models\Service;
use App\Models\Staff;
use App\Services\AppointmentService;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PublicController extends Controller
{
    protected AppointmentService $appointmentService;
    protected NotificationService $notificationService;

    public function __construct(
        AppointmentService $appointmentService,
        NotificationService $notificationService
    ) {
        $this->appointmentService = $appointmentService;
        $this->notificationService = $notificationService;
    }

    /**
     * Get list of all cities with salon counts for SEO pages
     */
    public function cities(): JsonResponse
    {
        $cities = \Illuminate\Support\Facades\Cache::remember('cities.list', 1800, function () {
            return Salon::approved()
                ->whereNotNull('city_slug')
                ->select('city', 'city_slug', DB::raw('count(*) as salon_count'))
                ->groupBy('city', 'city_slug')
                ->orderBy('salon_count', 'desc')
                ->get()
                ->map(function ($city) {
                    return [
                        'name' => $city->city,
                        'slug' => $city->city_slug,
                        'salon_count' => $city->salon_count,
                        'url' => '/frizer-' . $city->city_slug,
                        'meta' => [
                            'title' => 'Frizeri i Kozmetičari u ' . $city->city . ' | Frizersko-Kozmetički Saloni',
                            'description' => 'Pronađite najbolje frizere i kozmetičare u gradu ' . $city->city . '. Pregledajte cijene, recenzije i zakažite termin online.',
                        ],
                    ];
                });
        });

        return response()->json([
            'cities' => $cities,
            'total' => $cities->count(),
        ]);
    }

    /**
     * Get popular services across all salons for search suggestions
     */
    public function popularServices(): JsonResponse
    {
        $data = \Illuminate\Support\Facades\Cache::remember('services.popular', 1800, function () {
            // Get all unique service names with their counts across approved salons
            $services = Service::query()
                ->join('salons', 'services.salon_id', '=', 'salons.id')
                ->where('salons.status', 'approved')
                ->select(
                    'services.name',
                    'services.category',
                    DB::raw('COUNT(*) as salon_count'),
                    DB::raw('MIN(services.price) as min_price'),
                    DB::raw('MAX(services.price) as max_price')
                )
                ->groupBy('services.name', 'services.category')
                ->orderBy('salon_count', 'desc')
                ->limit(50)
                ->get()
                ->map(function ($service) {
                    return [
                        'name' => $service->name,
                        'category' => $service->category,
                        'salon_count' => $service->salon_count,
                        'min_price' => $service->min_price,
                        'max_price' => $service->max_price,
                        // Normalized name for search (remove diacritics)
                        'search_name' => $this->normalizeText($service->name),
                    ];
                });

            // Get popular categories
            $categories = Service::query()
                ->join('salons', 'services.salon_id', '=', 'salons.id')
                ->where('salons.status', 'approved')
                ->select('services.category', DB::raw('COUNT(*) as service_count'))
                ->groupBy('services.category')
                ->orderBy('service_count', 'desc')
                ->get()
                ->map(function ($category) {
                    return [
                        'name' => $category->category,
                        'count' => $category->service_count,
                        'search_name' => $this->normalizeText($category->category),
                    ];
                });

            return [
                'services' => $services,
                'categories' => $categories,
                'total' => $services->count(),
            ];
        });

        return response()->json($data);
    }

    /**
     * Normalize text for search (remove Croatian diacritics)
     */
    private function normalizeText(string $text): string
    {
        $replacements = [
            'š' => 's', 'Š' => 'S',
            'č' => 'c', 'Č' => 'C',
            'ć' => 'c', 'Ć' => 'C',
            'ž' => 'z', 'Ž' => 'Z',
            'đ' => 'd', 'Đ' => 'D',
        ];

        return strtolower(strtr($text, $replacements));
    }

    /**
     * Get salons for a specific city (SEO page)
     */
    public function salonsByCity(string $citySlug): JsonResponse
    {
        // First, try to find the city in locations table
        $location = Location::where('city_slug', $citySlug)->first();

        // If no location found, try to find salons with this city_slug
        $salons = Salon::approved()
            ->where('city_slug', $citySlug)
            ->with(['images', 'services', 'staff'])
            ->withCount(['reviews', 'staff'])
            ->orderByDesc('rating')
            ->paginate(20);

        // Determine city name
        $cityName = $location?->name ?? ($salons->first()?->city ?? $this->slugToName($citySlug));

        // Always return success with city info, even if no salons
        return response()->json([
            'city' => [
                'name' => $cityName,
                'slug' => $citySlug,
            ],
            'salons' => SalonResource::collection($salons),
            'total' => $salons->total(),
            'meta' => [
                'title' => 'Frizeri i Kozmetičari u ' . $cityName . ' - Pronađite Najboljeg | Frizerino',
                'description' => $salons->total() > 0
                    ? 'Lista svih frizersko-kozmetičkih salona u gradu ' . $cityName . '. Pogledajte cijene usluga, recenzije klijenata i zakažite svoj termin online. ' . $salons->total() . ' salona dostupno.'
                    : 'Trenutno nema registrovanih salona u gradu ' . $cityName . '. Budite prvi koji će registrovati svoj salon na Frizerino platformi.',
                'keywords' => ['frizer ' . $cityName, 'kozmetičar ' . $cityName, 'frizerski salon ' . $cityName, 'frizeri ' . $cityName, 'šišanje ' . $cityName, 'salon ljepote ' . $cityName],
                'canonical' => '/saloni/' . $citySlug,
            ],
            'schema' => $salons->total() > 0 ? $this->generateCitySchema($cityName, $salons->items()) : null,
        ]);
    }

    /**
     * Convert slug to readable city name
     */
    private function slugToName(string $slug): string
    {
        // Common city name mappings
        $cityNames = [
            'sarajevo' => 'Sarajevo',
            'banja-luka' => 'Banja Luka',
            'tuzla' => 'Tuzla',
            'zenica' => 'Zenica',
            'mostar' => 'Mostar',
            'bihac' => 'Bihać',
            'bijeljina' => 'Bijeljina',
            'brcko' => 'Brčko',
            'prijedor' => 'Prijedor',
            'doboj' => 'Doboj',
            'trebinje' => 'Trebinje',
            'cazin' => 'Cazin',
            'bugojno' => 'Bugojno',
            'livno' => 'Livno',
            'gradiska' => 'Gradiška',
            'gracanica' => 'Gračanica',
            'gorazde' => 'Goražde',
            'visoko' => 'Visoko',
            'konjic' => 'Konjic',
            'srebrenik' => 'Srebrenik',
            'kakanj' => 'Kakanj',
            'modrica' => 'Modriča',
            'sanski-most' => 'Sanski Most',
            'velika-kladusa' => 'Velika Kladuša',
            'zivinice' => 'Živinice',
            'lukavac' => 'Lukavac',
            'tesanj' => 'Tešanj',
            'travnik' => 'Travnik',
            'capljina' => 'Čapljina',
            'siroki-brijeg' => 'Široki Brijeg',
            'orasje' => 'Orašje',
            'derventa' => 'Derventa',
            'laktasi' => 'Laktaši',
            'vogosca' => 'Vogošća',
            'ilidza' => 'Ilidža',
            'hadzici' => 'Hadžići',
            'novi-grad' => 'Novi Grad',
            'stari-grad' => 'Stari Grad',
            'centar' => 'Centar',
            'novo-sarajevo' => 'Novo Sarajevo',
        ];

        return $cityNames[$slug] ?? ucwords(str_replace('-', ' ', $slug));
    }

    /**
     * Get salon by slug for SEO-friendly URL
     */
    public function salonBySlug(string $slug): JsonResponse
    {
        $cacheKey = 'salon.profile.' . $slug;

        $data = \Illuminate\Support\Facades\Cache::remember($cacheKey, 600, function () use ($slug) {
            $salon = Salon::approved()
                ->where('slug', $slug)
                ->with([
                    'images',
                    'services.staff',
                    'staff',
                    'salonBreaks',
                    'salonVacations',
                    'reviews' => function ($query) {
                        $query->latest()->limit(10);
                    },
                    'reviews.client',
                ])
                ->withCount('reviews')
                ->first();

            if (!$salon) {
                return null;
            }

            return [
                'salon' => new SalonResource($salon),
                'meta' => [
                    'title' => $salon->meta_title ?: $salon->name . ' - Frizersko-Kozmetički Salon u ' . $salon->city,
                    'description' => $salon->meta_description ?: 'Posjetite ' . $salon->name . ' u gradu ' . $salon->city . '. ' . Str::limit($salon->description, 150),
                    'keywords' => $salon->meta_keywords ?: [$salon->name, 'frizer ' . $salon->city, 'kozmetičar ' . $salon->city, 'frizersko-kozmetički salon'],
                    'canonical' => '/salon/' . $salon->slug,
                    'image' => $salon->images->where('is_primary', true)->first()?->url,
                ],
                'schema' => $this->generateSalonSchema($salon),
            ];
        });

        if (!$data) {
            return response()->json([
                'message' => 'Salon nije pronađen',
            ], 404);
        }

        return response()->json($data);
    }

    /**
     * Public search with filtering
     */
    public function search(Request $request): JsonResponse
    {
        // Create cache key from request parameters
        $cacheKey = 'search:' . md5(json_encode($request->all()));

        // Cache for 5 minutes (300 seconds)
        // Skip cache if date/time filters are present (real-time availability)
        $useCache = !$request->filled('date') && !$request->filled('time');

        if ($useCache && \Illuminate\Support\Facades\Cache::has($cacheKey)) {
            return response()->json(\Illuminate\Support\Facades\Cache::get($cacheKey));
        }

        $query = Salon::approved()
            ->with(['images', 'services', 'owner:id,name,email']) // Eager load owner to prevent N+1
            ->withCount(['reviews', 'staff']);

        // Filter by date and time availability
        if ($request->filled('date') || $request->filled('time')) {
            // Use today's date if only time is specified
            $date = $request->filled('date') ? $request->date : now()->format('d.m.Y');
            $time = $request->filled('time') ? $request->time : null;
            $duration = $request->filled('duration') ? (int) $request->duration : 60;

            // Get salon IDs that have availability
            $availableSalonIds = $this->appointmentService->getAvailableSalonIds($date, $time, $duration);

            if (empty($availableSalonIds)) {
                // No salons available, return empty result
                return response()->json([
                    'salons' => [],
                    'filters' => [
                        'applied' => $request->only(['q', 'city', 'service', 'min_rating', 'audience', 'date', 'time']),
                        'available_cities' => $this->getAvailableCities(),
                    ],
                    'meta' => [
                        'current_page' => 1,
                        'last_page' => 1,
                        'per_page' => $request->per_page ?? 12,
                        'total' => 0,
                    ],
                ]);
            }

            $query->whereIn('id', $availableSalonIds);
        }

        // Search by name with fuzzy matching for Croatian diacritics
        if ($request->filled('q')) {
            $searchTerm = $request->q;
            $normalizedSearch = $this->normalizeText($searchTerm);

            $query->where(function ($q) use ($searchTerm, $normalizedSearch) {
                // Direct match
                $q->where('name', 'ilike', "%{$searchTerm}%")
                    ->orWhere('description', 'ilike', "%{$searchTerm}%")
                    ->orWhere('city', 'ilike', "%{$searchTerm}%")
                    // Also search with normalized text for fuzzy matching
                    ->orWhereRaw("TRANSLATE(LOWER(name), 'šŠčČćĆžŽđĐ', 'sScCcCzZdD') ILIKE ?", ["%{$normalizedSearch}%"])
                    ->orWhereRaw("TRANSLATE(LOWER(description), 'šŠčČćĆžŽđĐ', 'sScCcCzZdD') ILIKE ?", ["%{$normalizedSearch}%"])
                    // Search in services
                    ->orWhereHas('services', function ($sq) use ($searchTerm, $normalizedSearch) {
                        $sq->where('name', 'ilike', "%{$searchTerm}%")
                           ->orWhere('category', 'ilike', "%{$searchTerm}%")
                           ->orWhereRaw("TRANSLATE(LOWER(name), 'šŠčČćĆžŽđĐ', 'sScCcCzZdD') ILIKE ?", ["%{$normalizedSearch}%"])
                           ->orWhereRaw("TRANSLATE(LOWER(category), 'šŠčČćĆžŽđĐ', 'sScCcCzZdD') ILIKE ?", ["%{$normalizedSearch}%"]);
                    });
            });
        }

        // Filter by city (with fuzzy matching for Croatian diacritics)
        if ($request->filled('city')) {
            $citySearch = $request->city;
            $normalizedCity = $this->normalizeText($citySearch);

            $query->where(function ($q) use ($citySearch, $normalizedCity) {
                $q->where('city', 'ilike', $citySearch)
                  ->orWhere('city_slug', 'ilike', $normalizedCity)
                  ->orWhereRaw("TRANSLATE(LOWER(city), 'šŠčČćĆžŽđĐ', 'sScCcCzZdD') ILIKE ?", [strtolower($normalizedCity)]);
            });
        }

        // Filter by service type
        if ($request->filled('service')) {
            $query->whereHas('services', function ($q) use ($request) {
                $q->where('name', 'ilike', "%{$request->service}%")
                    ->orWhere('category', 'ilike', "%{$request->service}%");
            });
        }

        // Filter by rating
        if ($request->filled('min_rating')) {
            $query->where('rating', '>=', $request->min_rating);
        }

        // Filter by target audience
        if ($request->filled('audience')) {
            $audience = is_array($request->audience) ? $request->audience : [$request->audience];
            foreach ($audience as $type) {
                $query->whereJsonContains('target_audience->' . $type, true);
            }
        }

        // Sort options - map frontend values to database columns
        $sortMapping = [
            'newest' => 'created_at',
            'oldest' => 'created_at',
            'rating' => 'rating',
            'name' => 'name',
            'review_count' => 'review_count',
        ];
        $sortField = $request->sort ?? 'rating';
        $sortDirection = $request->direction ?? 'desc';

        // Handle special cases where sort value implies direction
        if ($sortField === 'newest') {
            $sortField = 'created_at';
            $sortDirection = 'desc';
        } elseif ($sortField === 'oldest') {
            $sortField = 'created_at';
            $sortDirection = 'asc';
        } elseif (isset($sortMapping[$sortField])) {
            $sortField = $sortMapping[$sortField];
        }

        $query->orderBy($sortField, $sortDirection);

        $salons = $query->paginate($request->per_page ?? 12);

        $responseData = [
            'salons' => SalonResource::collection($salons),
            'filters' => [
                'applied' => $request->only(['q', 'city', 'service', 'min_rating', 'audience', 'date', 'time']),
                'available_cities' => $this->getAvailableCities(),
            ],
            'meta' => [
                'current_page' => $salons->currentPage(),
                'last_page' => $salons->lastPage(),
                'per_page' => $salons->perPage(),
                'total' => $salons->total(),
                'from' => $salons->firstItem(),
                'to' => $salons->lastItem(),
            ],
        ];

        // Cache the response if applicable
        if ($useCache) {
            \Illuminate\Support\Facades\Cache::put($cacheKey, $responseData, 300);
        }

        return response()->json($responseData);
    }

    /**
     * Store a guest appointment (no authentication required)
     */
    public function storeGuestAppointment(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'salon_id' => 'required|exists:salons,id',
            'staff_id' => 'required|exists:staff,id',
            'service_id' => 'required|exists:services,id',
            'date' => ['required', 'regex:/^\d{2}\.\d{2}\.\d{4}$/'],
            'time' => 'required|date_format:H:i',
            'notes' => 'nullable|string|max:500',
            // Guest information
            'guest_name' => 'required|string|max:255',
            'guest_email' => 'nullable|email|max:255',
            'guest_phone' => 'required|string|max:20',
            'guest_address' => 'required|string|max:500',
            // Optional: create account
            'create_account' => 'boolean',
            'password' => 'required_if:create_account,true|min:8|nullable',
        ], [
            'date.regex' => 'Datum mora biti u formatu DD.MM.YYYY',
            'guest_name.required' => 'Ime i prezime su obavezni',
            'guest_phone.required' => 'Broj telefona je obavezan',
            'guest_address.required' => 'Adresa je obavezna',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validacija nije uspjela',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            return DB::transaction(function () use ($request) {
                // Lock the staff row to prevent concurrent booking
                $staff = Staff::where('id', $request->staff_id)
                    ->with(['breaks', 'vacations', 'salon.salonBreaks', 'salon.salonVacations', 'services'])
                    ->lockForUpdate()
                    ->firstOrFail();

                $service = Service::findOrFail($request->service_id);
                $salon = Salon::findOrFail($request->salon_id);

                // Check if the staff can perform this service
                if (!$staff->services->contains($service->id)) {
                    return response()->json([
                        'message' => 'Odabrani frizer ne pruža ovu uslugu',
                    ], 422);
                }

                // Check if the staff is available at the requested time
                if (!$this->appointmentService->isStaffAvailable($staff, $request->date, $request->time, $service->duration)) {
                    return response()->json([
                        'message' => 'Odabrani termin nije dostupan',
                    ], 422);
                }

                // Calculate end time
                $endTime = $this->appointmentService->calculateEndTime($request->time, $service->duration);

                // Determine initial status
                $initialStatus = ($salon->auto_confirm || $staff->auto_confirm) ? 'confirmed' : 'pending';

                // Use discount price if available
                $finalPrice = $service->discount_price ?? $service->price;

                // Convert European date format to ISO format for database
                $dateForDb = Carbon::createFromFormat('d.m.Y', $request->date)->format('Y-m-d');

                $appointment = Appointment::create([
                    'client_id' => null, // Guest appointment has no user
                    'client_name' => $request->guest_name,
                    'client_email' => $request->guest_email,
                    'client_phone' => $request->guest_phone,
                    'is_guest' => true,
                    'guest_address' => $request->guest_address,
                    'salon_id' => $salon->id,
                    'staff_id' => $staff->id,
                    'service_id' => $service->id,
                    'date' => $dateForDb,
                    'time' => $request->time,
                    'end_time' => $endTime,
                    'status' => $initialStatus,
                    'notes' => $request->notes,
                    'total_price' => $finalPrice,
                    'payment_status' => 'pending',
                ]);

                // Send notifications to salon/staff
                $this->notificationService->sendNewAppointmentNotifications($appointment);

                // Send confirmation email to guest
                if ($request->guest_email) {
                    Mail::to($request->guest_email)->send(new AppointmentConfirmationMail($appointment));
                }

                return response()->json([
                    'message' => 'Termin uspješno zakazan!',
                    'appointment' => [
                        'id' => $appointment->id,
                        'date' => Carbon::parse($appointment->date)->format('d.m.Y'),
                        'time' => $appointment->time,
                        'end_time' => $appointment->end_time,
                        'status' => $appointment->status,
                        'salon' => $salon->name,
                        'staff' => $staff->name,
                        'service' => $service->name,
                        'total_price' => $appointment->total_price,
                    ],
                    'confirmation_message' => $initialStatus === 'confirmed'
                        ? 'Vaš termin je potvrđen. Vidimo se!'
                        : 'Vaš termin čeka potvrdu salona. Obavijestit ćemo vas putem SMS-a ili email-a.',
                ], 201);
            });
        } catch (QueryException $e) {
            if ($e->getCode() === '23505' || str_contains($e->getMessage(), 'appointments_no_double_booking')) {
                Log::warning('Double booking attempt prevented (guest)', [
                    'guest_name' => $request->guest_name,
                    'staff_id' => $request->staff_id,
                    'date' => $request->date,
                    'time' => $request->time,
                ]);

                return response()->json([
                    'message' => 'Ovaj termin je upravo zauzet. Molimo izaberite drugo vrijeme.',
                ], 422);
            }

            throw $e;
        }
    }

    /**
     * Get available time slots for a staff member (public)
     */
    public function availableSlots(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'staff_id' => 'required|exists:staff,id',
            'service_id' => 'required|exists:services,id',
            'date' => ['required', 'regex:/^\d{2}\.\d{2}\.\d{4}$/'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validacija nije uspjela',
                'errors' => $validator->errors(),
            ], 422);
        }

        $staff = Staff::with(['breaks', 'vacations', 'salon.salonBreaks', 'salon.salonVacations', 'services'])
            ->findOrFail($request->staff_id);
        $service = Service::findOrFail($request->service_id);

        $slots = $this->appointmentService->getAvailableSlots($staff, $request->date, $service->duration);

        return response()->json([
            'date' => $request->date,
            'staff' => $staff->name,
            'service' => $service->name,
            'duration' => $service->duration,
            'slots' => $slots,
        ]);
    }

    /**
     * Get available time slots for multiple services (public)
     * Uses the same staff member for all services
     */
    public function availableSlotsMulti(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'salon_id' => 'required|exists:salons,id',
            'date' => ['required', 'regex:/^\d{2}\.\d{2}\.\d{4}$/'],
            'services' => 'required|array|min:1',
            'services.*.serviceId' => 'required|exists:services,id',
            'services.*.staffId' => 'required|exists:staff,id',
            'services.*.duration' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validacija nije uspjela',
                'errors' => $validator->errors(),
            ], 422);
        }

        $salon = Salon::findOrFail($request->salon_id);
        $salonService = app(\App\Services\SalonService::class);

        $slots = $salonService->getAvailableTimeSlotsForMultipleServices(
            $salon,
            $request->date,
            $request->services
        );

        return response()->json([
            'slots' => $slots,
        ]);
    }

    /**
     * Generate sitemap data
     */
    public function sitemap(): JsonResponse
    {
        $salons = Salon::approved()
            ->select('slug', 'city_slug', 'updated_at')
            ->get()
            ->map(fn($s) => [
                'url' => '/salon/' . $s->slug,
                'lastmod' => $s->updated_at->toIso8601String(),
                'priority' => '0.8',
                'changefreq' => 'weekly',
            ]);

        $cities = Salon::approved()
            ->select('city_slug')
            ->distinct()
            ->get()
            ->map(fn($c) => [
                'url' => '/frizer-' . $c->city_slug,
                'priority' => '0.9',
                'changefreq' => 'daily',
            ]);

        $staticPages = [
            ['url' => '/', 'priority' => '1.0', 'changefreq' => 'daily'],
            ['url' => '/pretraga', 'priority' => '0.9', 'changefreq' => 'daily'],
        ];

        return response()->json([
            'urls' => array_merge($staticPages, $cities->toArray(), $salons->toArray()),
        ]);
    }

    /**
     * Generate XML sitemap
     */
    public function sitemapXml(): \Illuminate\Http\Response
    {
        $baseUrl = config('app.frontend_url', 'https://frizerski-saloni.ba');

        $salons = Salon::approved()
            ->select('slug', 'updated_at')
            ->get();

        $cities = Salon::approved()
            ->select('city_slug')
            ->distinct()
            ->get();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;

        // Static pages
        $xml .= $this->sitemapUrl($baseUrl . '/', '1.0', 'daily');
        $xml .= $this->sitemapUrl($baseUrl . '/pretraga', '0.9', 'daily');

        // City pages
        foreach ($cities as $city) {
            $xml .= $this->sitemapUrl($baseUrl . '/frizer-' . $city->city_slug, '0.9', 'daily');
        }

        // Salon pages
        foreach ($salons as $salon) {
            $xml .= $this->sitemapUrl(
                $baseUrl . '/salon/' . $salon->slug,
                '0.8',
                'weekly',
                $salon->updated_at->toIso8601String()
            );
        }

        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    /**
     * Helper: Generate single sitemap URL entry
     */
    private function sitemapUrl(string $url, string $priority, string $changefreq, ?string $lastmod = null): string
    {
        $entry = '  <url>' . PHP_EOL;
        $entry .= '    <loc>' . htmlspecialchars($url) . '</loc>' . PHP_EOL;
        if ($lastmod) {
            $entry .= '    <lastmod>' . $lastmod . '</lastmod>' . PHP_EOL;
        }
        $entry .= '    <changefreq>' . $changefreq . '</changefreq>' . PHP_EOL;
        $entry .= '    <priority>' . $priority . '</priority>' . PHP_EOL;
        $entry .= '  </url>' . PHP_EOL;
        return $entry;
    }

    /**
     * Helper: Get available cities for filters
     */
    private function getAvailableCities(): array
    {
        return Salon::approved()
            ->whereNotNull('city_slug')
            ->select('city', 'city_slug')
            ->distinct()
            ->orderBy('city')
            ->get()
            ->toArray();
    }

    /**
     * Helper: Generate JSON-LD schema for salon
     */
    private function generateSalonSchema(Salon $salon): array
    {
        $primaryImage = $salon->images->where('is_primary', true)->first();

        return [
            '@context' => 'https://schema.org',
            '@type' => 'HairSalon',
            'name' => $salon->name,
            'description' => $salon->description,
            'image' => $primaryImage?->url,
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $salon->address,
                'addressLocality' => $salon->city,
                'postalCode' => $salon->postal_code,
                'addressCountry' => $salon->country ?? 'BA',
            ],
            'telephone' => $salon->phone,
            'email' => $salon->email,
            'url' => config('app.frontend_url') . '/salon/' . $salon->slug,
            'aggregateRating' => $salon->review_count > 0 ? [
                '@type' => 'AggregateRating',
                'ratingValue' => round($salon->rating, 1),
                'reviewCount' => $salon->review_count,
                'bestRating' => 5,
                'worstRating' => 1,
            ] : null,
            'priceRange' => $this->getPriceRange($salon),
            'openingHoursSpecification' => $this->getOpeningHours($salon),
        ];
    }

    /**
     * Helper: Generate JSON-LD schema for city page
     */
    private function generateCitySchema(string $cityName, array $salons): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => 'Frizerski saloni u ' . $cityName,
            'description' => 'Lista frizerskih salona u gradu ' . $cityName,
            'numberOfItems' => count($salons),
            'itemListElement' => collect($salons)->map(function ($salon, $index) {
                return [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'item' => [
                        '@type' => 'HairSalon',
                        'name' => $salon->name,
                        'url' => config('app.frontend_url') . '/salon/' . $salon->slug,
                    ],
                ];
            })->toArray(),
        ];
    }

    /**
     * Helper: Get price range for schema
     */
    private function getPriceRange(Salon $salon): string
    {
        $prices = $salon->services->pluck('price')->filter();
        if ($prices->isEmpty()) {
            return '$$';
        }

        $avg = $prices->avg();
        if ($avg < 15) return '$';
        if ($avg < 30) return '$$';
        if ($avg < 50) return '$$$';
        return '$$$$';
    }

    /**
     * Helper: Get opening hours for schema
     */
    private function getOpeningHours(Salon $salon): array
    {
        if (!$salon->working_hours) {
            return [];
        }

        $dayMap = [
            'monday' => 'Monday',
            'tuesday' => 'Tuesday',
            'wednesday' => 'Wednesday',
            'thursday' => 'Thursday',
            'friday' => 'Friday',
            'saturday' => 'Saturday',
            'sunday' => 'Sunday',
        ];

        $hours = [];
        foreach ($salon->working_hours as $day => $schedule) {
            if (!empty($schedule['is_open']) && !empty($schedule['open']) && !empty($schedule['close'])) {
                $hours[] = [
                    '@type' => 'OpeningHoursSpecification',
                    'dayOfWeek' => $dayMap[$day] ?? ucfirst($day),
                    'opens' => $schedule['open'],
                    'closes' => $schedule['close'],
                ];
            }
        }

        return $hours;
    }
}
