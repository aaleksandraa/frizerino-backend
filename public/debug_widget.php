<?php
/**
 * Widget Debug Script - Run directly via browser
 * URL: https://api.frizerino.com/debug_widget.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$testKey = $_GET['key'] ?? 'frzn_live_fiLp9JQidKHcwo1vPsZLquw0nez2jNyh';

$result = [
    'test_key' => $testKey,
    'test_key_length' => strlen($testKey),
];

// Check if key exists
$widget = DB::table('widget_settings')->where('api_key', $testKey)->first();

if ($widget) {
    $result['found'] = true;
    $result['widget'] = [
        'id' => $widget->id,
        'salon_id' => $widget->salon_id,
        'is_active' => $widget->is_active,
        'is_active_type' => gettype($widget->is_active),
    ];
} else {
    $result['found'] = false;

    // List all widgets
    $allWidgets = DB::table('widget_settings')
        ->select('id', 'salon_id', 'api_key', 'is_active')
        ->get();

    $result['all_widgets'] = $allWidgets->map(function($w) {
        return [
            'id' => $w->id,
            'salon_id' => $w->salon_id,
            'api_key_preview' => substr($w->api_key, 0, 30) . '...',
            'api_key_full' => $w->api_key,
            'is_active' => $w->is_active,
        ];
    })->toArray();

    $result['total_widgets'] = count($result['all_widgets']);
}

// Check salon slug
$salonSlug = $_GET['salon'] ?? 'frizerski-salon-mr-barber';
$salon = DB::table('salons')->where('slug', $salonSlug)->first();

if ($salon) {
    $result['salon'] = [
        'id' => $salon->id,
        'name' => $salon->name,
        'slug' => $salon->slug,
        'status' => $salon->status,
    ];

    // Check if this salon has a widget
    $salonWidget = DB::table('widget_settings')->where('salon_id', $salon->id)->first();
    if ($salonWidget) {
        $result['salon_widget'] = [
            'id' => $salonWidget->id,
            'api_key' => $salonWidget->api_key,
            'is_active' => $salonWidget->is_active,
        ];
    } else {
        $result['salon_widget'] = null;
        $result['message'] = 'Salon nema kreiran widget! Potrebno je generirati API ključ u admin panelu.';
    }
} else {
    $result['salon'] = null;
    $result['salon_error'] = 'Salon not found with slug: ' . $salonSlug;
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
