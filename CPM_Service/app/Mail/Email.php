<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class Email extends Mailable
{
    use Queueable, SerializesModels;
	
	public $mail;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($cons=[])
    {
        $this->mail = $cons;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $layout = !empty($this->mail->layout) ? $this->mail->layout : 'layouts';
        $email =  $this->from('noreplay@bankbsi.co.id', 'CPM')->subject($this->mail->subject)->view('emails.'.$layout);
        if (!empty($this->mail->attach))
        {
            $email = $email->attach($this->mail->attach);
        }
        return $email;
    }
}
