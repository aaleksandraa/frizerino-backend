<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Notification;
use App\Models\Review;
use App\Models\Salon;
use App\Models\User;
use App\Mail\AppointmentCancelledMail;
use App\Mail\ReviewRequestMail;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Format date for display in European format (DD.MM.YYYY)
     */
    private function formatDate(string $date): string
    {
        return Carbon::parse($date)->format('d.m.Y');
    }

    /**
     * Format time for display (HH:MM)
     */
    private function formatTime(string $time): string
    {
        return Carbon::parse($time)->format('H:i');
    }

    /**
     * Send notifications for a new appointment.
     */
    public function sendNewAppointmentNotifications(Appointment $appointment): void
    {
        // Load relationships
        $appointment->load(['salon', 'staff', 'service', 'client']);

        $formattedDate = $this->formatDate($appointment->date);
        $formattedTime = $this->formatTime($appointment->time);
        $salon = $appointment->salon;
        $service = $appointment->service;
        $client = $appointment->client;
        $staff = $appointment->staff;

        // Get client name - use registered client name or guest name from appointment
        $clientName = $client ? $client->name : ($appointment->client_name ?? 'Gost');
        $isGuest = $appointment->is_guest || !$client;

        // Notify salon owner
        $owner = $salon->owner;
        $guestLabel = $isGuest ? ' (ručno dodano)' : '';
        Notification::create([
            'type' => 'new_appointment',
            'title' => 'Novi termin',
            'message' => "{$clientName}{$guestLabel} je zakazao/la termin za uslugu '{$service->name}' kod {$staff->name} za {$formattedDate} u {$formattedTime}h",
            'recipient_id' => $owner->id,
            'related_id' => $appointment->id,
        ]);

        // Notify staff member
        if ($staff->user_id) {
            Notification::create([
                'type' => 'new_appointment',
                'title' => 'Novi termin',
                'message' => "{$clientName}{$guestLabel} je zakazao/la termin za uslugu '{$service->name}' za {$formattedDate} u {$formattedTime}h",
                'recipient_id' => $staff->user_id,
                'related_id' => $appointment->id,
            ]);
        }

        // Only notify client if they are a registered user (not a guest)
        if ($client && $appointment->client_id) {
            $statusMessage = $appointment->status === 'confirmed'
                ? 'je automatski potvrđen'
                : 'čeka potvrdu';

            Notification::create([
                'type' => $appointment->status === 'confirmed' ? 'appointment_confirmed' : 'new_appointment',
                'title' => $appointment->status === 'confirmed' ? 'Termin potvrđen' : 'Termin zakazan',
                'message' => "Vaš termin za '{$service->name}' kod {$staff->name} u salonu '{$salon->name}' za {$formattedDate} u {$formattedTime}h {$statusMessage}",
                'recipient_id' => $appointment->client_id,
                'related_id' => $appointment->id,
            ]);
        }
    }

    /**
     * Send notifications for appointment status change.
     */
    public function sendAppointmentStatusChangeNotifications(Appointment $appointment, string $oldStatus): void
    {
        // Load relationships
        $appointment->load(['salon', 'staff', 'service', 'client']);

        $formattedDate = $this->formatDate($appointment->date);
        $formattedTime = $this->formatTime($appointment->time);
        $salon = $appointment->salon;
        $service = $appointment->service;
        $staff = $appointment->staff;
        $client = $appointment->client;

        // Get client name - use registered client name or guest name from appointment
        $clientName = $client ? $client->name : ($appointment->client_name ?? 'Gost');

        // Only notify client if they are a registered user (not a guest)
        if ($client && $appointment->client_id) {
            switch ($appointment->status) {
                case 'confirmed':
                    Notification::create([
                        'type' => 'appointment_confirmed',
                        'title' => 'Termin potvrđen',
                        'message' => "Vaš termin za '{$service->name}' u salonu '{$salon->name}' za {$formattedDate} u {$formattedTime}h je potvrđen",
                        'recipient_id' => $appointment->client_id,
                        'related_id' => $appointment->id,
                    ]);
                    break;

                case 'cancelled':
                    Notification::create([
                        'type' => 'appointment_cancelled',
                        'title' => 'Termin otkazan',
                        'message' => "Vaš termin za '{$service->name}' u salonu '{$salon->name}' za {$formattedDate} u {$formattedTime}h je otkazan",
                        'recipient_id' => $appointment->client_id,
                        'related_id' => $appointment->id,
                    ]);

                    // Send email to client when salon cancels their appointment
                    $client = $appointment->client;
                    if ($client && $client->email) {
                        Mail::to($client->email)->send(new AppointmentCancelledMail($appointment, 'client', 'salon'));
                    }
                    break;

                case 'completed':
                    Notification::create([
                        'type' => 'appointment_completed',
                        'title' => 'Termin završen',
                        'message' => "Vaš termin za '{$service->name}' kod {$staff->name} u salonu '{$salon->name}' je uspješno završen. Ostavite recenziju!",
                        'recipient_id' => $appointment->client_id,
                        'related_id' => $appointment->id,
                        'data' => [
                            'salon_id' => $salon->id,
                            'salon_slug' => $salon->slug,
                            'salon_name' => $salon->name,
                            'service_name' => $service->name,
                            'staff_name' => $staff->name,
                            'can_review' => true,
                        ],
                    ]);

                    // Send email to client with review request
                    $this->sendReviewRequestEmail($appointment);
                    break;
            }
        }

        // Notify salon owner if client cancelled
        if ($appointment->status === 'cancelled' && $oldStatus !== 'cancelled') {
            $owner = $salon->owner;

            Notification::create([
                'type' => 'appointment_cancelled',
                'title' => 'Termin otkazan',
                'message' => "{$clientName} je otkazao/la termin za '{$service->name}' kod {$staff->name} za {$formattedDate} u {$formattedTime}h",
                'recipient_id' => $owner->id,
                'related_id' => $appointment->id,
            ]);

            // Also notify staff member
            if ($staff->user_id) {
                Notification::create([
                    'type' => 'appointment_cancelled',
                    'title' => 'Termin otkazan',
                    'message' => "{$clientName} je otkazao/la termin za '{$service->name}' za {$formattedDate} u {$formattedTime}h",
                    'recipient_id' => $staff->user_id,
                    'related_id' => $appointment->id,
                ]);
            }

            // Send cancellation email to salon owner
            if ($owner->email) {
                Mail::to($owner->email)->send(new AppointmentCancelledMail($appointment, 'salon', 'client'));
            }
        }

        // Notify staff member about confirmation
        if ($staff->user_id && $appointment->status === 'confirmed' && $oldStatus !== 'confirmed') {
            Notification::create([
                'type' => 'appointment_confirmed',
                'title' => 'Termin potvrđen',
                'message' => "Termin za '{$service->name}' sa klijentom {$clientName} za {$formattedDate} u {$formattedTime}h je potvrđen",
                'recipient_id' => $staff->user_id,
                'related_id' => $appointment->id,
            ]);
        }
    }

    /**
     * Send notifications for a new review.
     */
    public function sendNewReviewNotifications(Review $review): void
    {
        // Load relationships
        $review->load(['salon', 'staff', 'client']);

        $salon = $review->salon;
        $owner = $salon->owner;
        $client = $review->client;
        $stars = str_repeat('★', $review->rating) . str_repeat('☆', 5 - $review->rating);

        // Notify salon owner
        Notification::create([
            'type' => 'new_review',
            'title' => 'Nova recenzija',
            'message' => "{$client->name} je ostavio/la recenziju {$stars} ({$review->rating}/5) za vaš salon",
            'recipient_id' => $owner->id,
            'related_id' => $review->id,
        ]);

        // Notify staff member
        if ($review->staff_id) {
            $staff = $review->staff;
            if ($staff->user_id) {
                Notification::create([
                    'type' => 'new_review',
                    'title' => 'Nova recenzija',
                    'message' => "{$client->name} je ostavio/la recenziju {$stars} ({$review->rating}/5) za vas",
                    'recipient_id' => $staff->user_id,
                    'related_id' => $review->id,
                ]);
            }
        }
    }

    /**
     * Send notification for a review response.
     */
    public function sendReviewResponseNotification(Review $review): void
    {
        $review->load(['salon', 'client']);

        $responseText = $review->response['text'] ?? 'Pogledajte odgovor';
        $shortResponse = strlen($responseText) > 50 ? substr($responseText, 0, 50) . '...' : $responseText;

        Notification::create([
            'type' => 'review_response',
            'title' => 'Odgovor na recenziju',
            'message' => "Salon '{$review->salon->name}' je odgovorio na vašu recenziju: \"{$shortResponse}\"",
            'recipient_id' => $review->client_id,
            'related_id' => $review->id,
        ]);
    }    /**
     * Send notification for adding a salon to favorites.
     */
    public function sendFavoriteAddedNotification(User $user, Salon $salon): void
    {
        Notification::create([
            'type' => 'favorite_added',
            'title' => 'Salon dodan u omiljene',
            'message' => "Dodali ste '{$salon->name}' u omiljene salone. Pratite specijalne ponude!",
            'recipient_id' => $user->id,
            'related_id' => $salon->id,
        ]);

        // Also notify salon owner
        Notification::create([
            'type' => 'new_favorite',
            'title' => 'Novi pratilac',
            'message' => "{$user->name} je dodao/la vaš salon u omiljene",
            'recipient_id' => $salon->owner_id,
            'related_id' => $salon->id,
        ]);
    }

    /**
     * Send notification for removing a salon from favorites.
     */
    public function sendFavoriteRemovedNotification(User $user, Salon $salon): void
    {
        // Optional: We might not want to notify about removals
        // Notification::create([
        //     'type' => 'favorite_removed',
        //     'title' => 'Salon uklonjen iz omiljenih',
        //     'message' => "Salon '{$salon->name}' je uklonjen iz vaših omiljenih",
        //     'recipient_id' => $user->id,
        //     'related_id' => $salon->id,
        // ]);
    }

    /**
     * Send notification for salon status change.
     */
    public function sendSalonStatusChangeNotification(Salon $salon, string $status): void
    {
        $statusText = match($status) {
            'approved' => 'odobren i sada je vidljiv klijentima',
            'suspended' => 'suspendovan i više nije vidljiv klijentima',
            'pending' => 'stavljen na čekanje za pregled',
            default => $status
        };

        Notification::create([
            'type' => 'salon_status_change',
            'title' => 'Status salona promijenjen',
            'message' => "Vaš salon '{$salon->name}' je {$statusText}",
            'recipient_id' => $salon->owner_id,
            'related_id' => $salon->id,
        ]);
    }

    /**
     * Send notification for password reset.
     */
    public function sendPasswordResetNotification(User $user, string $newPassword): void
    {
        Notification::create([
            'type' => 'password_reset',
            'title' => 'Lozinka resetovana',
            'message' => "Administrator je resetovao vašu lozinku. Nova privremena lozinka: {$newPassword}. Molimo promijenite je nakon prijave.",
            'recipient_id' => $user->id,
        ]);
    }

    /**
     * Send notification for admin message.
     */
    public function sendAdminMessageNotification(User $user, string $subject, string $message): void
    {
        Notification::create([
            'type' => 'admin_message',
            'title' => $subject,
            'message' => $message,
            'recipient_id' => $user->id,
        ]);
    }

    /**
     * Send reminder notification for upcoming appointment.
     */
    public function sendAppointmentReminderNotification(Appointment $appointment): void
    {
        $appointment->load(['salon', 'staff', 'service']);

        $formattedDate = $this->formatDate($appointment->date);
        $formattedTime = $this->formatTime($appointment->time);
        $salon = $appointment->salon;
        $service = $appointment->service;
        $staff = $appointment->staff;

        Notification::create([
            'type' => 'appointment_reminder',
            'title' => 'Podsjetnik za termin',
            'message' => "Podsjetnik: Imate zakazan termin za '{$service->name}' kod {$staff->name} u salonu '{$salon->name}' za {$formattedDate} u {$formattedTime}h",
            'recipient_id' => $appointment->client_id,
            'related_id' => $appointment->id,
        ]);
    }

    /**
     * Send review request email to client after appointment is completed.
     */
    public function sendReviewRequestEmail(Appointment $appointment): void
    {
        try {
            $appointment->load(['salon', 'staff', 'service', 'client']);

            // Only send email if client is a registered user with email
            $client = $appointment->client;
            if (!$client || !$client->email) {
                Log::info("Skipping review request email - no registered client for appointment {$appointment->id}");
                return;
            }

            Mail::to($client->email)->send(new ReviewRequestMail($appointment));

            Log::info("Review request email sent to {$client->email} for appointment {$appointment->id}");
        } catch (\Exception $e) {
            Log::error("Failed to send review request email for appointment {$appointment->id}: " . $e->getMessage());
        }
    }
}
