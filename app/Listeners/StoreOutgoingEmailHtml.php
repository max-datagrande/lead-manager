<?php

namespace App\Listeners;

use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Address;

class StoreOutgoingEmailHtml
{
    /**
     * Handle the event.
     */
    public function handle(MessageSent $event): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $message = $event->message;
        $htmlBody = $message->getHtmlBody();

        if (! is_string($htmlBody) || $htmlBody === '') {
            $textBody = $message->getTextBody();
            $htmlBody = '<html><body><pre>'.e((string) $textBody).'</pre></body></html>';
        }

        $toAddresses = array_values($message->getTo() ?? []);
        $firstRecipient = $toAddresses[0] ?? null;

        $to = 'unknown';
        if ($firstRecipient instanceof Address) {
            $to = $firstRecipient->getAddress();
        } elseif (is_string($firstRecipient) && $firstRecipient !== '') {
            $to = $firstRecipient;
        }

        $subject = (string) $message->getSubject();
        if ($subject === '') {
            $subject = 'no-subject';
        }

        $directory = storage_path('logs/emails');
        File::ensureDirectoryExists($directory);

        $datePrefix = now()->format('Ymd');
        $safeTo = Str::slug($to, '_');
        $safeSubject = Str::slug($subject, '_');

        if ($safeTo === '') {
            $safeTo = 'unknown';
        }

        if ($safeSubject === '') {
            $safeSubject = 'no_subject';
        }

        $baseName = sprintf('%s_%s_%s', $datePrefix, $safeTo, $safeSubject);
        $filePath = $directory.DIRECTORY_SEPARATOR.$baseName.'.html';

        $counter = 1;
        while (File::exists($filePath)) {
            $filePath = $directory.DIRECTORY_SEPARATOR.sprintf('%s_%02d.html', $baseName, $counter);
            $counter++;
        }

        File::put($filePath, $htmlBody);
    }
}
