<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

class NewAppointmentNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Appointment $appointment;
    public ?array $appointments; // For multi-service bookings
    public string $recipientType; // 'salon' or 'staff'
    public string $formattedDate;
    public string $formattedTime;
    public string $endTime;
    public float $totalPrice;
    public int $totalDuration;

    /**
     * Create a new message instance.
     */
    public function __construct(Appointment $appointment, string $recipientType = 'salon', ?array $appointments = null)
    {
        $this->appointment = $appointment->load(['salon', 'service', 'staff', 'client']);
        $this->recipientType = $recipientType;
        $this->appointments = $appointments;

        // If multiple appointments provided, calculate total duration and price
        if ($appointments && count($appointments) > 1) {
            $this->totalDuration = array_sum(array_map(fn($apt) => $apt->service->duration, $appointments));
            $this->totalPrice = array_sum(array_map(fn($apt) => $apt->total_price, $appointments));
        } else {
            $this->totalDuration = $appointment->service->duration ?? 60;
            $this->totalPrice = $appointment->total_price;
        }

        // Parse date and time
        $dateString = $appointment->date instanceof Carbon
            ? $appointment->date->format('Y-m-d')
            : $appointment->date;
        $startDateTime = Carbon::parse($dateString . ' ' . $appointment->time);
        $endDateTime = $startDateTime->copy()->addMinutes($this->totalDuration);

        $this->formattedDate = $startDateTime->locale('bs')->isoFormat('dddd, D. MMMM YYYY.');
        $this->formattedTime = $startDateTime->format('H:i');
        $this->endTime = $endDateTime->format('H:i');
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $salon = $this->appointment->salon;
        $subject = $this->recipientType === 'staff'
            ? 'Novi termin zakazan'
            : 'Novi termin u salonu - ' . $salon->name;

        return new Envelope(
            from: new \Illuminate\Mail\Mailables\Address('info@frizerino.com', 'Frizerino'),
            replyTo: [new \Illuminate\Mail\Mailables\Address('info@frizerino.com', 'Frizerino Podrška')],
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.new-appointment-notification',
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}
