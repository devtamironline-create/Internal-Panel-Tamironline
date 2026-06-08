<?php

namespace Modules\Site\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\Site\Models\Forum\Answer;
use Modules\Site\Models\Forum\Question;

class ForumQuestionAnsweredMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Question $question,
        public Answer $answer,
        public string $publicUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'به سوال شما در انجمن تعمیرآنلاین پاسخ داده شد',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'site::mail.forum-question-answered',
            with: [
                'question' => $this->question,
                'answer' => $this->answer,
                'publicUrl' => $this->publicUrl,
            ],
        );
    }
}
