<!DOCTYPE html>
<html lang="bs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Potvrda termina - Frizerino</title>
    <!--[if mso]>
    <style type="text/css">
        table {border-collapse: collapse;}
        .button-link {padding: 12px 24px !important;}
    </style>
    <![endif]-->
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f5f5f5;">
    <table role="presentation" cellpadding="0" cellspacing="0" style="width: 100%; background-color: #f5f5f5;">
        <tr>
            <td style="padding: 40px 20px;">
                <table role="presentation" cellpadding="0" cellspacing="0" style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);">

                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #f97316 0%, #dc2626 100%); padding: 32px 40px; text-align: center;">
                            <h1 style="color: #ffffff; font-size: 24px; font-weight: 600; margin: 0;">
                                Termin je zakazan
                            </h1>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px;">
                            <!-- Greeting -->
                            <p style="color: #374151; font-size: 16px; line-height: 1.6; margin: 0 0 24px;">
                                Pozdrav{{ $appointment->client_name ? ' ' . explode(' ', $appointment->client_name)[0] : '' }},
                            </p>
                            <p style="color: #374151; font-size: 16px; line-height: 1.6; margin: 0 0 32px;">
                                Vaš termin je uspješno zakazan. Evo detalja:
                            </p>

                            <!-- Details Box -->
                            <table role="presentation" cellpadding="0" cellspacing="0" style="width: 100%; background-color: #f9fafb; border-radius: 8px; border: 1px solid #e5e7eb; margin-bottom: 32px;">
                                <tr>
                                    <td style="padding: 24px;">
                                        <!-- Salon -->
                                        <table role="presentation" cellpadding="0" cellspacing="0" style="width: 100%; margin-bottom: 16px; border-bottom: 1px solid #e5e7eb; padding-bottom: 16px;">
                                            <tr>
                                                <td style="width: 100px; color: #6b7280; font-size: 14px; vertical-align: top;">Salon</td>
                                                <td style="color: #111827; font-size: 15px; font-weight: 600;">
                                                    {{ $appointment->salon->name }}<br>
                                                    <span style="font-weight: 400; color: #6b7280; font-size: 14px;">{{ $appointment->salon->address }}, {{ $appointment->salon->city }}</span>
                                                </td>
                                            </tr>
                                        </table>

                                        <!-- Service -->
                                        <table role="presentation" cellpadding="0" cellspacing="0" style="width: 100%; margin-bottom: 16px; border-bottom: 1px solid #e5e7eb; padding-bottom: 16px;">
                                            <tr>
                                                <td style="width: 100px; color: #6b7280; font-size: 14px; vertical-align: top;">Usluga</td>
                                                <td style="color: #111827; font-size: 15px; font-weight: 600;">
                                                    {{ $appointment->service->name }}
                                                    @if($appointment->service->price)
                                                    <span style="color: #f97316; margin-left: 8px;">{{ number_format($appointment->service->price, 2) }} KM</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        </table>

                                        <!-- Date & Time -->
                                        <table role="presentation" cellpadding="0" cellspacing="0" style="width: 100%; margin-bottom: 16px; border-bottom: 1px solid #e5e7eb; padding-bottom: 16px;">
                                            <tr>
                                                <td style="width: 100px; color: #6b7280; font-size: 14px; vertical-align: top;">Datum</td>
                                                <td style="color: #111827; font-size: 15px; font-weight: 600;">{{ $formattedDate }}</td>
                                            </tr>
                                        </table>

                                        <table role="presentation" cellpadding="0" cellspacing="0" style="width: 100%; @if($appointment->staff) margin-bottom: 16px; border-bottom: 1px solid #e5e7eb; padding-bottom: 16px; @endif">
                                            <tr>
                                                <td style="width: 100px; color: #6b7280; font-size: 14px; vertical-align: top;">Vrijeme</td>
                                                <td style="color: #111827; font-size: 15px; font-weight: 600;">{{ $formattedTime }} - {{ $endTime }} ({{ $appointment->service->duration ?? 60 }} min)</td>
                                            </tr>
                                        </table>

                                        <!-- Staff (if assigned) -->
                                        @if($appointment->staff)
                                        <table role="presentation" cellpadding="0" cellspacing="0" style="width: 100%;">
                                            <tr>
                                                <td style="width: 100px; color: #6b7280; font-size: 14px; vertical-align: top;">Frizer</td>
                                                <td style="color: #111827; font-size: 15px; font-weight: 600;">{{ $appointment->staff->name }}</td>
                                            </tr>
                                        </table>
                                        @endif
                                    </td>
                                </tr>
                            </table>

                            <!-- Add to Calendar Section -->
                            <table role="presentation" cellpadding="0" cellspacing="0" style="width: 100%; margin-bottom: 32px;">
                                <tr>
                                    <td style="text-align: center;">
                                        <p style="color: #6b7280; font-size: 14px; margin: 0 0 16px;">Dodajte termin u kalendar:</p>
                                        <table role="presentation" cellpadding="0" cellspacing="0" style="margin: 0 auto;">
                                            <tr>
                                                <td style="padding: 0 6px;">
                                                    <a href="{{ $googleCalendarUrl }}" target="_blank" style="display: inline-block; background-color: #4285f4; color: #ffffff; text-decoration: none; padding: 10px 16px; border-radius: 6px; font-size: 13px; font-weight: 500;">
                                                        Google Calendar
                                                    </a>
                                                </td>
                                                <td style="padding: 0 6px;">
                                                    <a href="https://outlook.live.com/calendar/0/deeplink/compose?subject={{ urlencode('Termin: ' . $appointment->service->name . ' - ' . $appointment->salon->name) }}&location={{ urlencode($appointment->salon->address . ', ' . $appointment->salon->city) }}&body={{ urlencode('Rezervisano preko frizerino.com') }}&startdt={{ \Carbon\Carbon::parse($appointment->date)->format('Y-m-d') }}T{{ $appointment->time }}:00&enddt={{ \Carbon\Carbon::parse($appointment->date)->format('Y-m-d') }}T{{ $endTime }}:00" target="_blank" style="display: inline-block; background-color: #0078d4; color: #ffffff; text-decoration: none; padding: 10px 16px; border-radius: 6px; font-size: 13px; font-weight: 500;">
                                                        Outlook
                                                    </a>
                                                </td>
                                                <td style="padding: 0 6px;">
                                                    <span style="display: inline-block; background-color: #333333; color: #ffffff; padding: 10px 16px; border-radius: 6px; font-size: 13px; font-weight: 500;">
                                                        Apple (prilog)
                                                    </span>
                                                </td>
                                            </tr>
                                        </table>
                                        <p style="color: #9ca3af; font-size: 12px; margin: 12px 0 0;">Za Apple Calendar otvorite priloženi .ics fajl</p>
                                    </td>
                                </tr>
                            </table>

                            <!-- Important Note -->
                            <table role="presentation" cellpadding="0" cellspacing="0" style="width: 100%; background-color: #fffbeb; border-radius: 6px; border: 1px solid #fcd34d; margin-bottom: 32px;">
                                <tr>
                                    <td style="padding: 16px 20px;">
                                        <p style="color: #92400e; font-size: 14px; margin: 0; line-height: 1.5;">
                                            <strong>Napomena:</strong> Ako ne možete doći na termin, molimo vas da otkažete rezervaciju najmanje 24 sata unaprijed.
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <!-- CTA Button -->
                            <table role="presentation" cellpadding="0" cellspacing="0" style="width: 100%; margin-bottom: 24px;">
                                <tr>
                                    <td style="text-align: center;">
                                        <a href="{{ config('app.frontend_url', 'https://frizerino.com') }}/dashboard?section=appointments" style="display: inline-block; background-color: #f97316; color: #ffffff; text-decoration: none; padding: 14px 28px; border-radius: 8px; font-size: 15px; font-weight: 600;">
                                            Pregledaj moje termine
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <!-- Contact Info -->
                            @if($appointment->salon->phone)
                            <p style="color: #6b7280; font-size: 14px; margin: 0; text-align: center;">
                                Kontakt salona: <a href="tel:{{ $appointment->salon->phone }}" style="color: #374151; text-decoration: none; font-weight: 500;">{{ $appointment->salon->phone }}</a>
                            </p>
                            @endif
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f9fafb; padding: 24px 40px; text-align: center; border-top: 1px solid #e5e7eb;">
                            <p style="color: #374151; font-size: 16px; font-weight: 600; margin: 0 0 8px;">Frizerino</p>
                            <p style="color: #9ca3af; font-size: 13px; margin: 0 0 12px;">
                                Pronađite i zakažite termine u najboljim salonima
                            </p>
                            <p style="margin: 0;">
                                <a href="https://frizerino.com" style="color: #f97316; text-decoration: none; font-size: 13px;">frizerino.com</a>
                            </p>
                            <p style="color: #9ca3af; font-size: 11px; margin: 16px 0 0;">
                                © {{ date('Y') }} Frizerino
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
