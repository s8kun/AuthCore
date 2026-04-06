<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ProjectTemplatedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        private readonly string $subjectLine,
        private readonly string $htmlBody,
        private readonly ?string $textBody,
        private readonly string $fromEmail,
        private readonly string $fromName,
        private readonly ?string $replyToEmail,
    ) {}

    /**
     * Build the message.
     */
    public function build(): static
    {
        $mail = $this->subject($this->subjectLine)
            ->from($this->fromEmail, $this->fromName)
            ->view('mail.project-email-html', [
                'htmlBody' => $this->htmlBody,
            ]);

        if (filled($this->replyToEmail)) {
            $mail->replyTo((string) $this->replyToEmail);
        }

        if (filled($this->textBody)) {
            $mail->text('mail.project-email-text', [
                'textBody' => (string) $this->textBody,
            ]);
        }

        return $mail;
    }
}
