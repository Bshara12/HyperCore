<?php

namespace App\Domains\Notifications\Mail;

use App\Models\Domains\Notifications\Models\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Notification $notification
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->notification->title ?: 'Notification'
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.notifications.generic',
            with: [
                'notification' => $this->notification,
                'title' => $this->notification->title,
                'body' => $this->notification->body,
                'data' => $this->notification->data ?? [],
            ]
        );
    }
}
