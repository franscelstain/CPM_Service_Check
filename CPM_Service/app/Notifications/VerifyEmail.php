<?php

namespace App\Notifications;

use App\Mail\Email;
use App\Models\Administrative\Email\EmailContent;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Lang;
use Tymon\JWTAuth\Facades\JWTAuth;
use Mail;

class VerifyEmail extends Notification
{
    use Queueable;
    
    /**
     * The callback that should be used to build the mail message.
     *
     * @var \Closure|null
     */
    public static $toMailCallback;
    
    /**
     * Get the notification's channels.
     *
     * @param  mixed  $notifiable
     * @return array|string
     */
    public function via($notifiable)
    {
        return ['mail'];
    }
    
    /**
     * Build the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $verificationUrl = $this->verificationUrl($notifiable);
        
        if (static::$toMailCallback) 
        {
            return call_user_func(static::$toMailCallback, $notifiable, $verificationUrl);
        }
        $token  = explode('token=', $verificationUrl); 
        $link   = env('MAIN_URL') . 'email/verify/' . $token[1];
        $mail   = ['new' => [JWTAuth::user()->fullname, $link]];
        $data   = EmailContent::join('c_email_layouts as b', 'c_email_contents.layout_id', '=', 'b.layout_id')->where([['c_email_contents.is_active', 'Yes'], ['b.is_active', 'Yes'], ['email_content_name', 'Registration']])->first();
        $send   = (object) array('from' => 'noreplay-wms@bankbsi.co.id', 'subject' => 'Verify Email Address', 'data' => $data, 'mail' => $mail);
        return Mail::to(JWTAuth::user()->email)->send(new Email($send));
        
        /*return (new MailMessage)
            ->subject(Lang::get('Verify Email Address'))
            ->line(Lang::get('Please click the button below to verify your email address.'))
            ->action(Lang::get('Verify Email Address'), $verificationUrl)
            ->line(Lang::get('If you did not create an account, no further action is required.'));*/
    }
    
    /**
     * Get the verification URL for the given notifiable.
     *
     * @param  mixed  $notifiable
     * @return string
     */
    protected function verificationUrl($notifiable)
    {
        $token = JWTAuth::fromUser($notifiable);
        return route('email.verify', ['token' => $token], false);
    }
    
    /**
     * Set a callback that should be used when building the notification mail message.
     *
     * @param  \Closure  $callback
     * @return void
     */
    public static function toMailUsing($callback)
    {
        static::$toMailCallback = $callback;
    }
}