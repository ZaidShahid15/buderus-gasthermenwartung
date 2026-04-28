<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ContactFormController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'your-name' => ['required', 'string', 'max:255'],
            'your-email' => ['required', 'email', 'max:255'],
            'your-phone' => ['required', 'string', 'max:255'],
            'your-company' => ['required', 'string', 'max:255'],
            'your-message' => ['required', 'string', 'max:2000'],
        ]);

        $recipient = config('mail.contact_to.address', 'ast.mediainternational@gmail.com');
        $websiteName = config('mail.from.name', 'Baxi Gasthermenwartung');
        $subject = $websiteName . ' - Neue Anfrage von ' . $validated['your-name'];

        $body = implode("\n", [
            'Website: ' . $websiteName,
            'Neue Anfrage ueber das Kontaktformular:',
            '',
            'Name: ' . $validated['your-name'],
            'E-Mail: ' . $validated['your-email'],
            'Telefon: ' . $validated['your-phone'],
            'Firma: ' . $validated['your-company'],
            '',
            'Nachricht:',
            $validated['your-message'],
        ]);

        Mail::raw($body, function ($message) use ($recipient, $subject, $validated) {
            $message
                ->to($recipient)
                ->subject($subject)
                ->replyTo($validated['your-email'], $validated['your-name']);
        });

        return redirect()
            ->to(route('home') . '#wpcf7-f44-p6-o1')
            ->with('contact_success', 'Vielen Dank. Ihre Anfrage wurde erfolgreich gesendet.');
    }
}
