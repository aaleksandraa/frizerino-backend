<?php
/**
 * Test Widget Slots API directly
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    require __DIR__ . '/../vendor/autoload.php';

    $app = require_once __DIR__ . '/../bootstrap/app.php';
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

    $apiKey = $_GET['key'] ?? 'frzn_live_04Nni7cWjZBs77PmMJpyd98F91pmQSY7';
    $staffId = $_GET['staff_id'] ?? 3;
    $date = $_GET['date'] ?? '24.12.2025';
    $serviceId = $_GET['service_id'] ?? 4;
    $duration = $_GET['duration'] ?? 30;

    // Find widget - use whereRaw for PostgreSQL
    $widget = \App\Models\WidgetSetting::where('api_key', $apiKey)
        ->whereRaw('is_active = true')
        ->first();

    if (!$widget) {
        echo json_encode(['error' => 'Widget not found or inactive']);
        exit;
    }

    echo json_encode(['step' => 'widget_found', 'widget_id' => $widget->id]) . "\n";

    // Find staff
    $staff = \App\Models\Staff::find($staffId);
    if (!$staff) {
        echo json_encode(['error' => 'Staff not found']);
        exit;
    }

    echo json_encode(['step' => 'staff_found', 'staff_name' => $staff->name]) . "\n";

    // Find salon
    $salon = \App\Models\Salon::find($widget->salon_id);
    if (!$salon) {
        echo json_encode(['error' => 'Salon not found']);
        exit;
    }

    echo json_encode(['step' => 'salon_found', 'salon_name' => $salon->name]) . "\n";

    // Prepare services data
    $servicesData = [
        [
            'serviceId' => $serviceId,
            'staffId' => $staffId,
            'duration' => (int)$duration,
        ]
    ];

    echo json_encode(['step' => 'services_prepared', 'services' => $servicesData]) . "\n";

    // Get slots
    $salonService = app(\App\Services\SalonService::class);

    $slots = $salonService->getAvailableTimeSlotsForMultipleServices(
        $salon,
        $date,
        $servicesData
    );

    echo json_encode([
        'success' => true,
        'slots_count' => count($slots),
        'slots' => $slots,
    ], JSON_PRETTY_PRINT);

} catch (\Throwable $e) {
    echo json_encode([
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => array_slice(explode("\n", $e->getTraceAsString()), 0, 10),
    ], JSON_PRETTY_PRINT);
}
