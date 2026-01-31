<?php
// ============================================
// app/Mail/GenericNotificationMail.php
// ============================================

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class GenericNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $subjectLine;
    public string $viewName;
    public array $data;

    public function __construct(string $subject, string $view, array $data = [])
    {
        $this->subjectLine = $subject;
        $this->viewName = $view;
        $this->data = $data;
    }

    public function build()
    {
        return $this->subject($this->subjectLine)
            ->view($this->viewName)
            ->with($this->data);
    }
}
