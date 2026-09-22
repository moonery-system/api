<?php

namespace App\Mail;

use App\Models\Invite;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        private Invite $invite
    ) {}

    public function build()
    {
        $url = rtrim(config('app.frontend_url'), '/') . '/invite?token=' . $this->invite->token;

        return $this->subject('Reset your Moonery password')
            ->view('emails.password-reset', [
                'name' => $this->invite->user->name,
                'url' => $url,
                'expiresAt' => $this->invite->expires_at,
            ]);
    }
}
