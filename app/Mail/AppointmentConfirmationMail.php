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

class AppointmentConfirmationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Appointment $appointment;
    public string $googleCalendarUrl;
    public string $icsContent;
    public string $formattedDate;
    public string $formattedTime;
    public string $endTime;

    /**
     * Create a new message instance.
     */
    public function __construct(Appointment $appointment)
    {
        $this->appointment = $appointment->load(['salon', 'service', 'staff']);

        // Parse date and time - date is already Carbon instance from model cast
        $dateString = $appointment->date instanceof Carbon
            ? $appointment->date->format('Y-m-d')
            : $appointment->date;
        $startDateTime = Carbon::parse($dateString . ' ' . $appointment->time);
        $duration = $appointment->service->duration ?? 60;
        $endDateTime = $startDateTime->copy()->addMinutes($duration);

        $this->formattedDate = $startDateTime->locale('bs')->isoFormat('dddd, D. MMMM YYYY.');
        $this->formattedTime = $startDateTime->format('H:i');
        $this->endTime = $endDateTime->format('H:i');

        // Generate Google Calendar URL
        $this->googleCalendarUrl = $this->generateGoogleCalendarUrl($startDateTime, $endDateTime);

        // Generate ICS content for iOS/Outlook
        $this->icsContent = $this->generateIcsContent($startDateTime, $endDateTime);
    }    /**
     * Generate Google Calendar URL
     */
    private function generateGoogleCalendarUrl(Carbon $start, Carbon $end): string
    {
        $salon = $this->appointment->salon;
        $service = $this->appointment->service;
        $staff = $this->appointment->staff;

        $title = urlencode("Termin: {$service->name} - {$salon->name}");
        $details = urlencode("Usluga: {$service->name}\nSalon: {$salon->name}" .
            ($staff ? "\nFrizer: {$staff->name}" : "") .
            "\n\nRezervisano preko frizerino.com");
        $location = urlencode($salon->address . ', ' . $salon->city);

        $startFormatted = $start->format('Ymd\THis');
        $endFormatted = $end->format('Ymd\THis');

        return "https://calendar.google.com/calendar/render?action=TEMPLATE" .
            "&text={$title}" .
            "&dates={$startFormatted}/{$endFormatted}" .
            "&details={$details}" .
            "&location={$location}" .
            "&sf=true&output=xml";
    }

    /**
     * Generate ICS file content for iOS/Outlook
     */
    private function generateIcsContent(Carbon $start, Carbon $end): string
    {
        $salon = $this->appointment->salon;
        $service = $this->appointment->service;
        $staff = $this->appointment->staff;

        $uid = uniqid('frizerino-') . '@frizerino.com';
        $now = Carbon::now()->format('Ymd\THis\Z');
        $startFormatted = $start->format('Ymd\THis');
        $endFormatted = $end->format('Ymd\THis');

        $description = "Usluga: {$service->name}\\nSalon: {$salon->name}" .
            ($staff ? "\\nFrizer: {$staff->name}" : "") .
            "\\n\\nRezervisano preko frizerino.com";

        $location = $salon->address . ', ' . $salon->city;

        return "BEGIN:VCALENDAR\r\n" .
            "VERSION:2.0\r\n" .
            "PRODID:-//Frizerino//Appointment//BS\r\n" .
            "CALSCALE:GREGORIAN\r\n" .
            "METHOD:PUBLISH\r\n" .
            "BEGIN:VEVENT\r\n" .
            "UID:{$uid}\r\n" .
            "DTSTAMP:{$now}\r\n" .
            "DTSTART:{$startFormatted}\r\n" .
            "DTEND:{$endFormatted}\r\n" .
            "SUMMARY:Termin: {$service->name} - {$salon->name}\r\n" .
            "DESCRIPTION:{$description}\r\n" .
            "LOCATION:{$location}\r\n" .
            "STATUS:CONFIRMED\r\n" .
            "END:VEVENT\r\n" .
            "END:VCALENDAR";
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Potvrda termina - ' . $this->appointment->salon->name,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.appointment-confirmation',
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [
            \Illuminate\Mail\Mailables\Attachment::fromData(fn () => $this->icsContent, 'termin.ics')
                ->withMime('text/calendar'),
        ];
    }
}
