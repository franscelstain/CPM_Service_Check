<?php 

namespace App\Traits;

date_default_timezone_set('Asia/Jakarta');

use App\Models\Users\Investor\Investor;
use App\Notifications\VerifyEmail;
use Auth;
use DB;

trait MustVerifyEmail
{
    /**
     * Determine if the user has verified their email address.
     *
     * @return bool
     */
    public function hasVerifiedEmail()
    {
        return ! is_null($this->email_verified_at);
    }
    
    /**
     * Mark the given user's email as verified.
     *
     * @return bool
     */
    public function markEmailAsVerified()
    {
        $datetime = $this->freshTimestamp();
        DB::table('u_investors_audit')->insert([
            'pk_id'         => Auth::id(),
            'user_id'       => Auth::id(),
            'user_name'     => Auth::user()->fullname,
            'user_type'     => 'Investor',
            'description'   => 'Update data email_verified_at from "" to "'. $datetime .'"',
            'status'        => 'Update'
        ]);
        
        return Investor::where('investor_id', Auth::id())->update(['email_verified_at' => $datetime, 'updated_by' => Auth::id()]);
        /*return $this->forceFill([
            'email_verified_at' => $this->freshTimestamp(),
        ])->save();*/
    }
    
    /**
     * Send the email verification notification.
     *
     * @return void
     */
    public function sendEmailVerificationNotification()
    {
        return $this->notify(new VerifyEmail);
    }
    
    /**
     * Get the email address that should be used for verification.
     *
     * @return string
     */
    public function getEmailForVerification()
    {
        return $this->email;
    }
}