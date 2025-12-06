<?php

namespace App\Jobs;

use App\Models\Appointment;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendAppointmentReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public array $backoff = [60, 300, 900];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Appointment $appointment
    ) {}

    /**
     * Execute the job.
     */
    public function handle(NotificationService $notificationService): void
    {
        try {
            // Send reminder notification to client
            $notificationService->create(
                $this->appointment->client_id,
                'appointment_reminder',
                'Podsjetnik: Imate termin sutra',
                "Vaš termin za {$this->appointment->service->name} u salonu {$this->appointment->salon->name} je zakazan za sutra u {$this->appointment->time}.",
                [
                    'appointment_id' => $this->appointment->id,
                    'salon_id' => $this->appointment->salon_id,
                    'date' => $this->appointment->date->format('d.m.Y'),
                    'time' => $this->appointment->time,
                ]
            );

            Log::info('Appointment reminder sent', [
                'appointment_id' => $this->appointment->id,
                'client_id' => $this->appointment->client_id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send appointment reminder', [
                'appointment_id' => $this->appointment->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Appointment reminder job failed permanently', [
            'appointment_id' => $this->appointment->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
