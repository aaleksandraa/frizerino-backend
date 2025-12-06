<?php

namespace App\Console\Commands;

use App\Jobs\SendAppointmentReminder;
use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendAppointmentReminders extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'appointments:send-reminders';

    /**
     * The console command description.
     */
    protected $description = 'Send reminder notifications for appointments scheduled for tomorrow';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tomorrow = Carbon::tomorrow()->format('Y-m-d');

        $appointments = Appointment::where('date', $tomorrow)
            ->where('status', 'confirmed')
            ->with(['client', 'salon', 'service'])
            ->get();

        $count = 0;
        foreach ($appointments as $appointment) {
            SendAppointmentReminder::dispatch($appointment);
            $count++;
        }

        $this->info("Dispatched {$count} reminder notifications for tomorrow's appointments.");

        return Command::SUCCESS;
    }
}
