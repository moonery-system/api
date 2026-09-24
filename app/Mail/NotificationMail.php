<?php

namespace App\Mail;

use App\Models\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        private Notification $notification
    ) {}

    public function build()
    {
        return $this->subject($this->notification->title)
            ->view('emails.notification', [
                'title' => $this->notification->title,
                'description' => $this->notification->description,
            ]);
    }
}
