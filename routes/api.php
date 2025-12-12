<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\JobAdController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PublicController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\SalonController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// API v1 routes
Route::prefix('v1')->group(function () {
    // Public auth routes - more reasonable rate limiting for login
    // Using web middleware to ensure session is available for Sanctum
    Route::middleware(['web'])->group(function () {
        Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:60,1');
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:60,1');
    });

    // Email verification routes (public - user clicks link from email)
    // Using higher rate limit as browsers may prefetch links
    Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware(['signed', 'throttle:60,1'])
        ->name('verification.verify');

    // Resend verification email (public - for users who can't login)
    Route::post('/email/resend', [AuthController::class, 'resendVerificationEmail'])
        ->middleware('throttle:6,1');

    // Password reset routes (public)
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
        ->middleware('throttle:6,1');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])
        ->middleware('throttle:6,1');

    // =============================================
    // PUBLIC ROUTES - No authentication required
    // =============================================
    Route::middleware('throttle:120,1')->group(function () {
        // Existing salon public routes
        Route::get('/salons', [SalonController::class, 'index']);
        Route::get('/salons/nearest', [SalonController::class, 'nearest']);
        Route::get('/salons/{salon}', [SalonController::class, 'show']);
        Route::get('/salons/{salon}/services', [ServiceController::class, 'index']);
        Route::get('/salons/{salon}/services/by-category', [ServiceController::class, 'byCategory']);
        Route::get('/salons/{salon}/staff', [StaffController::class, 'index']);
        Route::get('/salons/{salon}/reviews', [ReviewController::class, 'index']);

        // NEW: SEO-friendly public routes
        Route::prefix('public')->group(function () {
            // City pages for SEO (e.g., /frizer-doboj)
            Route::get('/cities', [PublicController::class, 'cities']);
            Route::get('/cities/{citySlug}', [PublicController::class, 'salonsByCity']);

            // Salon by slug (e.g., /salon/studio-ana-doboj)
            Route::get('/salon/{slug}', [PublicController::class, 'salonBySlug']);

            // Public search
            Route::get('/search', [PublicController::class, 'search']);

            // Popular services for search suggestions
            Route::get('/services', [PublicController::class, 'popularServices']);

            // Available time slots (public)
            Route::get('/available-slots', [PublicController::class, 'availableSlots']);

            // Guest booking (no auth required)
            Route::post('/book', [PublicController::class, 'storeGuestAppointment']);

            // Sitemap for SEO
            Route::get('/sitemap', [PublicController::class, 'sitemap']);

            // Public analytics settings (for GA injection)
            Route::get('/analytics-settings', [SettingsController::class, 'getAnalytics']);

            // Locations (for salon registration dropdown)
            Route::get('/locations', [LocationController::class, 'index']);
            Route::get('/locations/grouped', [LocationController::class, 'grouped']);
            Route::get('/locations/cantons', [LocationController::class, 'cantons']);

            // Job ads (public)
            Route::get('/job-ads', [JobAdController::class, 'index']);
            Route::get('/job-ads/{id}', [JobAdController::class, 'show']);
        });
    });

    // XML Sitemap (no throttling)
    Route::get('/sitemap.xml', [PublicController::class, 'sitemapXml']);

    // Protected routes with standard rate limiting (120 per minute)
    Route::middleware(['auth:sanctum', 'throttle:120,1'])->group(function () {
        // Auth routes
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'user']);
        Route::put('/user/profile', [AuthController::class, 'updateProfile']);
        Route::put('/user/password', [AuthController::class, 'changePassword']);
        Route::post('/user/avatar', [AuthController::class, 'uploadAvatar']);
        Route::post('/email/resend', [AuthController::class, 'resendVerificationEmail'])
            ->middleware('throttle:6,1');
        Route::get('/user/favorites', [FavoriteController::class, 'index']);
        Route::post('/user/favorites/{salon}', [FavoriteController::class, 'store']);
        Route::delete('/user/favorites/{salon}', [FavoriteController::class, 'destroy']);
        Route::get('/user/favorites/{salon}/check', [FavoriteController::class, 'check']);
        Route::get('/user/appointments', [AppointmentController::class, 'index']);
        Route::post('/user/appointments', [AppointmentController::class, 'store']);
        Route::get('/user/appointments/{appointment}', [AppointmentController::class, 'show']);
        Route::put('/user/appointments/{appointment}', [AppointmentController::class, 'update']);
        Route::delete('/user/appointments/{appointment}', [AppointmentController::class, 'destroy']);
        Route::put('/user/appointments/{appointment}/cancel', [AppointmentController::class, 'cancel']);
        Route::put('/salon/profile', [SalonController::class, 'updateProfile']);

        // Salon routes
        Route::post('/salons', [SalonController::class, 'store']);
        Route::put('/salons/{salon}', [SalonController::class, 'update']);
        Route::delete('/salons/{salon}', [SalonController::class, 'destroy']);
        Route::post('/salons/{salon}/images', [SalonController::class, 'uploadImages']);
        Route::delete('/salons/{salon}/images/{image}', [SalonController::class, 'deleteImage']);
        Route::put('/salons/{salon}/images/{image}/primary', [SalonController::class, 'setPrimaryImage']);
        Route::get('/salons/{salon}/available-slots', [SalonController::class, 'availableSlots']);

        // Staff routes
        Route::post('/salons/{salon}/staff', [StaffController::class, 'store']);
        Route::get('/salons/{salon}/staff/{staff}', [StaffController::class, 'show']);
        Route::put('/salons/{salon}/staff/{staff}', [StaffController::class, 'update']);
        Route::delete('/salons/{salon}/staff/{staff}', [StaffController::class, 'destroy']);
        Route::post('/salons/{salon}/staff/{staff}/avatar', [StaffController::class, 'uploadAvatar']);
        Route::get('/salons/{salon}/staff/{staff}/schedule', [StaffController::class, 'schedule']);
        Route::get('/salons/{salon}/staff/{staff}/appointments', [StaffController::class, 'appointments']);

        // Staff self-update route
        Route::put('/staff/me/settings', [StaffController::class, 'updateOwnSettings']);

        // Service routes
        Route::post('/salons/{salon}/services', [ServiceController::class, 'store']);
        Route::get('/salons/{salon}/services/{service}', [ServiceController::class, 'show']);
        Route::put('/salons/{salon}/services/{service}', [ServiceController::class, 'update']);
        Route::delete('/salons/{salon}/services/{service}', [ServiceController::class, 'destroy']);

        // Appointment routes
        Route::get('/appointments', [AppointmentController::class, 'index']);
        Route::post('/appointments', [AppointmentController::class, 'store']);
        Route::get('/appointments/{appointment}', [AppointmentController::class, 'show']);
        Route::put('/appointments/{appointment}', [AppointmentController::class, 'update']);
        Route::delete('/appointments/{appointment}', [AppointmentController::class, 'destroy']);
        Route::put('/appointments/{appointment}/cancel', [AppointmentController::class, 'cancel']);
        Route::put('/appointments/{appointment}/no-show', [AppointmentController::class, 'markNoShow']);
        Route::put('/appointments/{appointment}/complete', [AppointmentController::class, 'markCompleted']);

        // Review routes
        Route::post('/reviews', [ReviewController::class, 'store']);
        Route::get('/reviews/{review}', [ReviewController::class, 'show']);
        Route::put('/reviews/{review}', [ReviewController::class, 'update']);
        Route::delete('/reviews/{review}', [ReviewController::class, 'destroy']);
        Route::post('/reviews/{review}/response', [ReviewController::class, 'addResponse']);

        // Schedule routes
        Route::get('/salons/{salon}/breaks', [ScheduleController::class, 'getSalonBreaks']);
        Route::post('/salons/{salon}/breaks', [ScheduleController::class, 'storeSalonBreak']);
        Route::put('/salons/{salon}/breaks/{break}', [ScheduleController::class, 'updateSalonBreak']);
        Route::delete('/salons/{salon}/breaks/{break}', [ScheduleController::class, 'deleteSalonBreak']);

        Route::get('/salons/{salon}/vacations', [ScheduleController::class, 'getSalonVacations']);
        Route::post('/salons/{salon}/vacations', [ScheduleController::class, 'storeSalonVacation']);
        Route::put('/salons/{salon}/vacations/{vacation}', [ScheduleController::class, 'updateSalonVacation']);
        Route::delete('/salons/{salon}/vacations/{vacation}', [ScheduleController::class, 'deleteSalonVacation']);

        Route::get('/staff/{staff}/breaks', [ScheduleController::class, 'getStaffBreaks']);
        Route::post('/staff/{staff}/breaks', [ScheduleController::class, 'storeStaffBreak']);
        Route::put('/staff/{staff}/breaks/{break}', [ScheduleController::class, 'updateStaffBreak']);
        Route::delete('/staff/{staff}/breaks/{break}', [ScheduleController::class, 'deleteStaffBreak']);

        Route::get('/staff/{staff}/vacations', [ScheduleController::class, 'getStaffVacations']);
        Route::post('/staff/{staff}/vacations', [ScheduleController::class, 'storeStaffVacation']);
        Route::put('/staff/{staff}/vacations/{vacation}', [ScheduleController::class, 'updateStaffVacation']);
        Route::delete('/staff/{staff}/vacations/{vacation}', [ScheduleController::class, 'deleteStaffVacation']);

        // Favorite routes
        Route::get('/favorites', [FavoriteController::class, 'index']);
        Route::post('/favorites/{salon}', [FavoriteController::class, 'store']);
        Route::delete('/favorites/{salon}', [FavoriteController::class, 'destroy']);
        Route::get('/favorites/{salon}/check', [FavoriteController::class, 'check']);

        // Notification routes
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::put('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
        Route::put('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy']);

        // Job ads for salon owners
        Route::prefix('owner/job-ads')->group(function () {
            Route::get('/', [JobAdController::class, 'ownerIndex']);
            Route::post('/', [JobAdController::class, 'ownerStore']);
            Route::put('/{id}', [JobAdController::class, 'ownerUpdate']);
            Route::delete('/{id}', [JobAdController::class, 'ownerDestroy']);
        });

        // Clients management for salon owners and staff
        Route::get('/clients', [ClientController::class, 'index']);
        Route::post('/clients/send-email', [ClientController::class, 'sendEmail']);
        Route::get('/clients/{clientId}', [ClientController::class, 'show']);

        // Admin routes with extra security
        Route::middleware('admin')->prefix('admin')->group(function () {
            Route::get('/dashboard', [AdminController::class, 'dashboardStats']);
            Route::get('/users', [AdminController::class, 'users']);
            Route::post('/users', [AdminController::class, 'createUser']);
            Route::put('/users/{user}', [AdminController::class, 'updateUser']);
            Route::delete('/users/{user}', [AdminController::class, 'deleteUser']);
            Route::post('/users/{user}/reset-password', [AdminController::class, 'resetUserPassword']);
            Route::post('/users/{user}/message', [AdminController::class, 'sendMessageToUser']);
            Route::get('/salons', [AdminController::class, 'salons']);
            Route::put('/salons/{salon}', [AdminController::class, 'updateSalon']);
            Route::put('/salons/{salon}/approve', [AdminController::class, 'approveSalon']);
            Route::put('/salons/{salon}/suspend', [AdminController::class, 'suspendSalon']);
            Route::get('/analytics', [AdminController::class, 'analytics']);

            // GDPR Consents management
            Route::get('/consents', [AdminController::class, 'consents']);
            Route::get('/consents/export', [AdminController::class, 'exportConsents']);
            Route::get('/users/{user}/consents', [AdminController::class, 'userConsents']);

            // System settings
            Route::get('/settings', [SettingsController::class, 'index']);
            Route::get('/settings/{group}', [SettingsController::class, 'getByGroup']);
            Route::put('/settings', [SettingsController::class, 'update']);

            // Gradient/Appearance settings
            Route::get('/gradient-presets', [SettingsController::class, 'getGradientPresets']);
            Route::put('/gradient', [SettingsController::class, 'updateGradient']);
            Route::put('/navbar-gradient', [SettingsController::class, 'updateNavbarGradient']);
            Route::put('/sticky-navbar', [SettingsController::class, 'updateStickyNavbar']);

            // Salon profile layout settings
            Route::get('/salon-profile-layout', [SettingsController::class, 'getSalonProfileLayout']);
            Route::get('/salon-profile-layouts', [SettingsController::class, 'getSalonProfileLayoutOptions']);
            Route::put('/salon-profile-layout', [SettingsController::class, 'updateSalonProfileLayout']);

            // Featured salon settings
            Route::get('/featured-salon', [SettingsController::class, 'getFeaturedSalonAdmin']);
            Route::put('/featured-salon', [SettingsController::class, 'updateFeaturedSalon']);

            // Locations management
            Route::get('/locations', [LocationController::class, 'adminIndex']);
            Route::post('/locations', [LocationController::class, 'store']);
            Route::get('/locations/{location}', [LocationController::class, 'show']);
            Route::put('/locations/{location}', [LocationController::class, 'update']);
            Route::delete('/locations/{location}', [LocationController::class, 'destroy']);

            // Job ads management
            Route::get('/job-ads', [JobAdController::class, 'adminIndex']);
            Route::post('/job-ads', [JobAdController::class, 'store']);
            Route::put('/job-ads/owner-posting-setting', [JobAdController::class, 'updateOwnerPostingSetting']);
            Route::put('/job-ads/{id}', [JobAdController::class, 'update']);
            Route::delete('/job-ads/{id}', [JobAdController::class, 'destroy']);
            Route::put('/job-ads/{id}/toggle-active', [JobAdController::class, 'toggleActive']);
        });
    });
});

// Legacy routes (without v1 prefix) - for backward compatibility
// These will be deprecated in future versions
// Using web middleware to ensure session is available for Sanctum
Route::middleware(['web'])->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:60,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:60,1');
});

Route::middleware('throttle:120,1')->group(function () {
    Route::get('/salons', [SalonController::class, 'index']);
    Route::get('/salons/nearest', [SalonController::class, 'nearest']);
    Route::get('/salons/{salon}', [SalonController::class, 'show']);
    Route::get('/salons/{salon}/services', [ServiceController::class, 'index']);
    Route::get('/salons/{salon}/services/by-category', [ServiceController::class, 'byCategory']);
    Route::get('/salons/{salon}/staff', [StaffController::class, 'index']);
    Route::get('/salons/{salon}/reviews', [ReviewController::class, 'index']);

    // Public settings (analytics, appearance, etc.)
    Route::get('/public/analytics-settings', [SettingsController::class, 'getAnalytics']);
    Route::get('/public/appearance-settings', [SettingsController::class, 'getAppearance']);
    Route::get('/public/featured-salon', [SettingsController::class, 'getFeaturedSalon']);

    // Job ads (public)
    Route::get('/public/job-ads', [JobAdController::class, 'index']);
    Route::get('/public/job-ads/{id}', [JobAdController::class, 'show']);
});

Route::middleware(['auth:sanctum', 'throttle:120,1'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::put('/user/profile', [AuthController::class, 'updateProfile']);
    Route::put('/user/password', [AuthController::class, 'changePassword']);
    Route::post('/user/avatar', [AuthController::class, 'uploadAvatar']);
    Route::get('/user/favorites', [FavoriteController::class, 'index']);
    Route::post('/user/favorites/{salon}', [FavoriteController::class, 'store']);
    Route::delete('/user/favorites/{salon}', [FavoriteController::class, 'destroy']);
    Route::get('/user/favorites/{salon}/check', [FavoriteController::class, 'check']);
    Route::get('/user/appointments', [AppointmentController::class, 'index']);
    Route::post('/user/appointments', [AppointmentController::class, 'store']);
    Route::get('/user/appointments/{appointment}', [AppointmentController::class, 'show']);
    Route::put('/user/appointments/{appointment}', [AppointmentController::class, 'update']);
    Route::delete('/user/appointments/{appointment}', [AppointmentController::class, 'destroy']);
    Route::put('/user/appointments/{appointment}/cancel', [AppointmentController::class, 'cancel']);
    Route::put('/salon/profile', [SalonController::class, 'updateProfile']);

    Route::post('/salons', [SalonController::class, 'store']);
    Route::put('/salons/{salon}', [SalonController::class, 'update']);
    Route::delete('/salons/{salon}', [SalonController::class, 'destroy']);
    Route::post('/salons/{salon}/images', [SalonController::class, 'uploadImages']);
    Route::delete('/salons/{salon}/images/{image}', [SalonController::class, 'deleteImage']);
    Route::put('/salons/{salon}/images/{image}/primary', [SalonController::class, 'setPrimaryImage']);
    Route::get('/salons/{salon}/available-slots', [SalonController::class, 'availableSlots']);

    Route::post('/salons/{salon}/staff', [StaffController::class, 'store']);
    Route::get('/salons/{salon}/staff/{staff}', [StaffController::class, 'show']);
    Route::put('/salons/{salon}/staff/{staff}', [StaffController::class, 'update']);
    Route::delete('/salons/{salon}/staff/{staff}', [StaffController::class, 'destroy']);
    Route::post('/salons/{salon}/staff/{staff}/avatar', [StaffController::class, 'uploadAvatar']);
    Route::get('/salons/{salon}/staff/{staff}/schedule', [StaffController::class, 'schedule']);
    Route::get('/salons/{salon}/staff/{staff}/appointments', [StaffController::class, 'appointments']);

    Route::put('/staff/me/settings', [StaffController::class, 'updateOwnSettings']);

    Route::post('/salons/{salon}/services', [ServiceController::class, 'store']);
    Route::get('/salons/{salon}/services/{service}', [ServiceController::class, 'show']);
    Route::put('/salons/{salon}/services/{service}', [ServiceController::class, 'update']);
    Route::delete('/salons/{salon}/services/{service}', [ServiceController::class, 'destroy']);

    Route::get('/appointments', [AppointmentController::class, 'index']);
    Route::post('/appointments', [AppointmentController::class, 'store']);
    Route::get('/appointments/{appointment}', [AppointmentController::class, 'show']);
    Route::put('/appointments/{appointment}', [AppointmentController::class, 'update']);
    Route::delete('/appointments/{appointment}', [AppointmentController::class, 'destroy']);
    Route::put('/appointments/{appointment}/cancel', [AppointmentController::class, 'cancel']);
    Route::put('/appointments/{appointment}/no-show', [AppointmentController::class, 'markNoShow']);
    Route::put('/appointments/{appointment}/complete', [AppointmentController::class, 'markCompleted']);

    Route::post('/reviews', [ReviewController::class, 'store']);
    Route::get('/reviews/{review}', [ReviewController::class, 'show']);
    Route::put('/reviews/{review}', [ReviewController::class, 'update']);
    Route::delete('/reviews/{review}', [ReviewController::class, 'destroy']);
    Route::post('/reviews/{review}/response', [ReviewController::class, 'addResponse']);

    Route::get('/salons/{salon}/breaks', [ScheduleController::class, 'getSalonBreaks']);
    Route::post('/salons/{salon}/breaks', [ScheduleController::class, 'storeSalonBreak']);
    Route::put('/salons/{salon}/breaks/{break}', [ScheduleController::class, 'updateSalonBreak']);
    Route::delete('/salons/{salon}/breaks/{break}', [ScheduleController::class, 'deleteSalonBreak']);

    Route::get('/salons/{salon}/vacations', [ScheduleController::class, 'getSalonVacations']);
    Route::post('/salons/{salon}/vacations', [ScheduleController::class, 'storeSalonVacation']);
    Route::put('/salons/{salon}/vacations/{vacation}', [ScheduleController::class, 'updateSalonVacation']);
    Route::delete('/salons/{salon}/vacations/{vacation}', [ScheduleController::class, 'deleteSalonVacation']);

    Route::get('/staff/{staff}/breaks', [ScheduleController::class, 'getStaffBreaks']);
    Route::post('/staff/{staff}/breaks', [ScheduleController::class, 'storeStaffBreak']);
    Route::put('/staff/{staff}/breaks/{break}', [ScheduleController::class, 'updateStaffBreak']);
    Route::delete('/staff/{staff}/breaks/{break}', [ScheduleController::class, 'deleteStaffBreak']);

    Route::get('/staff/{staff}/vacations', [ScheduleController::class, 'getStaffVacations']);
    Route::post('/staff/{staff}/vacations', [ScheduleController::class, 'storeStaffVacation']);
    Route::put('/staff/{staff}/vacations/{vacation}', [ScheduleController::class, 'updateStaffVacation']);
    Route::delete('/staff/{staff}/vacations/{vacation}', [ScheduleController::class, 'deleteStaffVacation']);

    Route::get('/favorites', [FavoriteController::class, 'index']);
    Route::post('/favorites/{salon}', [FavoriteController::class, 'store']);
    Route::delete('/favorites/{salon}', [FavoriteController::class, 'destroy']);
    Route::get('/favorites/{salon}/check', [FavoriteController::class, 'check']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::put('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::put('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy']);

    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminController::class, 'dashboardStats']);
        Route::get('/users', [AdminController::class, 'users']);
        Route::post('/users', [AdminController::class, 'createUser']);
        Route::put('/users/{user}', [AdminController::class, 'updateUser']);
        Route::delete('/users/{user}', [AdminController::class, 'deleteUser']);
        Route::post('/users/{user}/reset-password', [AdminController::class, 'resetUserPassword']);
        Route::post('/users/{user}/message', [AdminController::class, 'sendMessageToUser']);
        Route::get('/salons', [AdminController::class, 'salons']);
        Route::put('/salons/{salon}', [AdminController::class, 'updateSalon']);
        Route::put('/salons/{salon}/approve', [AdminController::class, 'approveSalon']);
        Route::put('/salons/{salon}/suspend', [AdminController::class, 'suspendSalon']);
        Route::get('/analytics', [AdminController::class, 'analytics']);

        // System settings
        Route::get('/settings', [SettingsController::class, 'index']);
        Route::get('/settings/{group}', [SettingsController::class, 'getByGroup']);
        Route::put('/settings', [SettingsController::class, 'update']);

        // Gradient/Appearance settings
        Route::get('/gradient-presets', [SettingsController::class, 'getGradientPresets']);
        Route::put('/gradient', [SettingsController::class, 'updateGradient']);
        Route::put('/navbar-gradient', [SettingsController::class, 'updateNavbarGradient']);
        Route::put('/sticky-navbar', [SettingsController::class, 'updateStickyNavbar']);

        // Salon profile layout settings
        Route::get('/salon-profile-layout', [SettingsController::class, 'getSalonProfileLayout']);
        Route::get('/salon-profile-layouts', [SettingsController::class, 'getSalonProfileLayoutOptions']);
        Route::put('/salon-profile-layout', [SettingsController::class, 'updateSalonProfileLayout']);

        // Featured salon settings
        Route::get('/featured-salon', [SettingsController::class, 'getFeaturedSalonAdmin']);
        Route::put('/featured-salon', [SettingsController::class, 'updateFeaturedSalon']);

        // Locations management
        Route::get('/locations', [LocationController::class, 'adminIndex']);
        Route::post('/locations', [LocationController::class, 'store']);
        Route::get('/locations/{location}', [LocationController::class, 'show']);
        Route::put('/locations/{location}', [LocationController::class, 'update']);
        Route::delete('/locations/{location}', [LocationController::class, 'destroy']);
    });
});
