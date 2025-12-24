<?php
/**
 * Test Widget API directly
 * URL: https://api.frizerino.com/test_widget_api.php?key=xxx&salon=xxx
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    require __DIR__ . '/../vendor/autoload.php';

    $app = require_once __DIR__ . '/../bootstrap/app.php';
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

    $apiKey = $_GET['key'] ?? '';
    $salonSlug = $_GET['salon'] ?? '';

    if (!$apiKey || !$salonSlug) {
        echo json_encode(['error' => 'Missing key or salon parameter']);
        exit;
    }

    // Find widget
    $widget = \App\Models\WidgetSetting::where('api_key', $apiKey)->first();

    if (!$widget) {
        echo json_encode(['error' => 'Widget not found', 'api_key' => substr($apiKey, 0, 20) . '...']);
        exit;
    }

    echo json_encode([
        'widget_found' => true,
        'widget_id' => $widget->id,
        'salon_id' => $widget->salon_id,
        'is_active' => $widget->is_active,
        'is_active_type' => gettype($widget->is_active),
    ]);

    // Find salon - use whereRaw for PostgreSQL boolean compatibility
    $salon = \App\Models\Salon::with(['services' => function($query) {
        $query->whereRaw('is_active = true')
              ->orderBy('display_order')
              ->orderBy('id');
    }, 'staff' => function($query) {
        $query->whereRaw('is_active = true')
              ->orderBy('display_order')
              ->orderBy('name');
    }])
        ->where('slug', $salonSlug)
        ->where('id', $widget->salon_id)
        ->where('status', 'approved')
        ->first();

    if (!$salon) {
        echo json_encode([
            'error' => 'Salon not found',
            'salon_slug' => $salonSlug,
            'widget_salon_id' => $widget->salon_id,
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'salon' => [
            'id' => $salon->id,
            'name' => $salon->name,
            'slug' => $salon->slug,
        ],
        'services_count' => $salon->services->count(),
        'staff_count' => $salon->staff->count(),
    ], JSON_PRETTY_PRINT);

} catch (\Throwable $e) {
    echo json_encode([
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => explode("\n", $e->getTraceAsString()),
    ], JSON_PRETTY_PRINT);
}
