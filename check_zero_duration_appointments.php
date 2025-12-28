<?php

/**
 * Check for appointments with zero duration services
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "\n";
echo "========================================\n";
echo "Zero Duration Appointments Check\n";
echo "========================================\n\n";

// 1. Find services with duration = 0
echo "1. Services with duration = 0:\n";
echo "----------------------------\n";

$zeroServices = DB::select("
    SELECT id, name, duration, category, price
    FROM services
    WHERE duration = 0
    ORDER BY name
");

if (empty($zeroServices)) {
    echo "✅ No services with duration = 0\n";
} else {
    foreach ($zeroServices as $service) {
        echo "⚠️  ID {$service->id}: {$service->name} (category: {$service->category}, price: {$service->price})\n";
    }
}

echo "\n";

// 2. Find appointments with these services
echo "2. Appointments with zero duration services:\n";
echo "----------------------------\n";

$zeroAppointments = DB::select("
    SELECT
        a.id,
        a.date,
        a.time,
        a.end_time,
        a.status,
        a.client_name,
        s.name as service_name,
        s.duration,
        st.name as staff_name
    FROM appointments a
    JOIN services s ON a.service_id = s.id
    JOIN staff st ON a.staff_id = st.id
    WHERE s.duration = 0
    AND a.status IN ('pending', 'confirmed', 'in_progress')
    ORDER BY a.date DESC, a.time DESC
    LIMIT 20
");

if (empty($zeroAppointments)) {
    echo "✅ No active appointments with zero duration services\n";
} else {
    echo "Found " . count($zeroAppointments) . " active appointments:\n\n";
    foreach ($zeroAppointments as $apt) {
        echo "ID {$apt->id}: {$apt->date} {$apt->time}-{$apt->end_time} | {$apt->client_name} | {$apt->service_name} ({$apt->duration}min) | Staff: {$apt->staff_name} | Status: {$apt->status}\n";
    }
}

echo "\n";

// 3. Check if these appointments are blocking slots
echo "3. Checking slot blocking:\n";
echo "----------------------------\n";

if (!empty($zeroAppointments)) {
    $example = $zeroAppointments[0];
    echo "Example appointment:\n";
    echo "  Date: {$example->date}\n";
    echo "  Time: {$example->time}\n";
    echo "  End time: {$example->end_time}\n";
    echo "  Duration: {$example->duration} minutes\n";
    echo "\n";

    if ($example->time === $example->end_time) {
        echo "⚠️  PROBLEM: Start time = End time (no duration)\n";
        echo "   This appointment blocks the slot but doesn't reserve any time!\n";
    } else {
        echo "✅ Start time ≠ End time (has duration)\n";
    }
}

echo "\n";

// 4. Count total zero duration appointments (all statuses)
echo "4. Total zero duration appointments (all statuses):\n";
echo "----------------------------\n";

$totalZero = DB::select("
    SELECT
        a.status,
        COUNT(*) as count
    FROM appointments a
    JOIN services s ON a.service_id = s.id
    WHERE s.duration = 0
    GROUP BY a.status
    ORDER BY count DESC
");

if (empty($totalZero)) {
    echo "✅ No appointments with zero duration services\n";
} else {
    foreach ($totalZero as $stat) {
        echo "{$stat->status}: {$stat->count} appointments\n";
    }
}

echo "\n";

// 5. Recommendations
echo "========================================\n";
echo "RECOMMENDATIONS\n";
echo "========================================\n\n";

if (!empty($zeroServices)) {
    echo "❌ PROBLEM: Found " . count($zeroServices) . " services with duration = 0\n\n";

    echo "Solutions:\n";
    echo "1. PREVENT booking zero-duration services alone:\n";
    echo "   - Add validation in booking endpoints\n";
    echo "   - Only allow as part of multi-service booking\n\n";

    echo "2. FIX existing appointments:\n";
    echo "   - Option A: Cancel them (if they're mistakes)\n";
    echo "   - Option B: Set minimum duration (e.g., 5 minutes)\n";
    echo "   - Option C: Mark as completed if they're old\n\n";

    echo "3. UPDATE services:\n";
    echo "   - Set minimum duration (e.g., 5 minutes)\n";
    echo "   - Or mark them as 'addon_only' (can't be booked alone)\n\n";
}

echo "Next steps:\n";
echo "1. Review zero-duration services\n";
echo "2. Decide: Cancel, fix duration, or mark as addon-only\n";
echo "3. Add validation to prevent future bookings\n";
echo "4. Fix existing appointments\n";

echo "\n========================================\n";
echo "CHECK COMPLETE\n";
echo "========================================\n\n";
