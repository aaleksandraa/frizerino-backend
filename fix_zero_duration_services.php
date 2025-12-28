<?php

/**
 * Fix zero duration services and appointments
 *
 * This script:
 * 1. Finds services with duration = 0
 * 2. Updates them to have minimum duration (5 minutes)
 * 3. Fixes existing appointments with zero duration
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "\n";
echo "========================================\n";
echo "Fix Zero Duration Services\n";
echo "========================================\n\n";

// Ask for confirmation
echo "This script will:\n";
echo "1. Update services with duration = 0 to duration = 5 minutes\n";
echo "2. Fix existing appointments with zero duration\n";
echo "3. Update end_time for affected appointments\n";
echo "\n";
echo "Do you want to continue? (yes/no): ";
$handle = fopen("php://stdin", "r");
$line = fgets($handle);
if (trim($line) != 'yes') {
    echo "Aborted.\n";
    exit;
}
fclose($handle);

echo "\n";

// 1. Find and update services
echo "Step 1: Update services with duration = 0\n";
echo "----------------------------\n";

$zeroServices = DB::select("
    SELECT id, name, duration, category
    FROM services
    WHERE duration = 0
");

if (empty($zeroServices)) {
    echo "✅ No services with duration = 0\n";
} else {
    echo "Found " . count($zeroServices) . " services:\n";
    foreach ($zeroServices as $service) {
        echo "  - {$service->name} (ID: {$service->id})\n";
    }

    echo "\nUpdating to 5 minutes...\n";

    $updated = DB::update("
        UPDATE services
        SET duration = 5
        WHERE duration = 0
    ");

    echo "✅ Updated {$updated} services\n";
}

echo "\n";

// 2. Find and fix appointments
echo "Step 2: Fix appointments with zero duration\n";
echo "----------------------------\n";

$zeroAppointments = DB::select("
    SELECT
        a.id,
        a.date,
        a.time,
        a.end_time,
        a.status,
        s.name as service_name,
        s.duration as new_duration
    FROM appointments a
    JOIN services s ON a.service_id = s.id
    WHERE a.time = a.end_time
    AND a.status IN ('pending', 'confirmed', 'in_progress')
");

if (empty($zeroAppointments)) {
    echo "✅ No appointments with zero duration\n";
} else {
    echo "Found " . count($zeroAppointments) . " appointments to fix:\n\n";

    foreach ($zeroAppointments as $apt) {
        // Calculate new end time
        $timeParts = explode(':', $apt->time);
        $startMinutes = (int)$timeParts[0] * 60 + (int)$timeParts[1];
        $endMinutes = $startMinutes + $apt->new_duration;
        $newEndTime = sprintf('%02d:%02d', floor($endMinutes / 60), $endMinutes % 60);

        echo "  ID {$apt->id}: {$apt->date} {$apt->time} → {$newEndTime} ({$apt->service_name})\n";

        // Update appointment
        DB::update("
            UPDATE appointments
            SET end_time = ?
            WHERE id = ?
        ", [$newEndTime, $apt->id]);
    }

    echo "\n✅ Fixed " . count($zeroAppointments) . " appointments\n";
}

echo "\n";

// 3. Verify fix
echo "Step 3: Verify fix\n";
echo "----------------------------\n";

$remainingZero = DB::select("
    SELECT COUNT(*) as count
    FROM services
    WHERE duration = 0
");

$remainingZeroApts = DB::select("
    SELECT COUNT(*) as count
    FROM appointments
    WHERE time = end_time
    AND status IN ('pending', 'confirmed', 'in_progress')
");

echo "Services with duration = 0: {$remainingZero[0]->count}\n";
echo "Appointments with zero duration: {$remainingZeroApts[0]->count}\n";

if ($remainingZero[0]->count == 0 && $remainingZeroApts[0]->count == 0) {
    echo "\n✅ All zero duration issues fixed!\n";
} else {
    echo "\n⚠️  Some issues remain, please check manually\n";
}

echo "\n========================================\n";
echo "FIX COMPLETE\n";
echo "========================================\n\n";

echo "Next steps:\n";
echo "1. Test booking on website and widget\n";
echo "2. Verify slots are now available\n";
echo "3. Monitor for any new zero duration bookings\n";
echo "\n";
