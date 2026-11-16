<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DailyDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<string, Collection<int, \App\Models\Entry>>  $groupedEntries
     */
    public function __construct(
        public User $user,
        public Collection $groupedEntries,
        public int $totalCount,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Signal-to-Noise Digest ({$this->totalCount} new)",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.daily-digest',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
