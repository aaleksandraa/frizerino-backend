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
            // Load relationships
            $this->appointment->load(['salon', 'service', 'staff', 'client']);

            // Send in-app notification
            $notificationService->sendAppointmentReminderNotification($this->appointment);

            // Send email reminder if client has email
            if ($this->appointment->client && $this->appointment->client->email) {
                \Illuminate\Support\Facades\Mail::to($this->appointment->client->email)
                    ->send(new \App\Mail\AppointmentReminderMail($this->appointment));

                Log::info('Appointment reminder email sent', [
                    'appointment_id' => $this->appointment->id,
                    'client_id' => $this->appointment->client_id,
                    'client_email' => $this->appointment->client->email,
                ]);
            } else {
                Log::warning('Appointment reminder email not sent - no client email', [
                    'appointment_id' => $this->appointment->id,
                    'client_id' => $this->appointment->client_id,
                ]);
            }

            Log::info('Appointment reminder sent', [
                'appointment_id' => $this->appointment->id,
                'client_id' => $this->appointment->client_id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send appointment reminder', [
                'appointment_id' => $this->appointment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
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
