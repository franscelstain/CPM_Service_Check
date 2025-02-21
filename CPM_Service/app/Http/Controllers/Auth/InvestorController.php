<?php

namespace App\Http\Controllers\Auth;

use App\Interfaces\Auth\InvestorAuthRepositoryInterface;
use App\Validators\Auth\RegisterValidator;
use App\Http\Controllers\AppController;
use App\Http\Controllers\Administrative\Notify\UsersNotificationController;
use App\Http\Controllers\Notify\NotificationController;
use App\Http\Controllers\Administrative\Config\GeneralController;
use App\Models\Administrative\Config\Config;
use App\Models\Administrative\Mobile\MobileContent;
use App\Models\Auth\Investor as AuthInvestor;
use App\Models\Users\Investor\Account;
use App\Models\Users\Investor\Address;
use App\Models\Users\Investor\Investor;
use App\Models\Users\Investor\Question;
use App\Models\Users\Investor\ResetPassword;
use App\Models\Users\Investor\CardPriority;
use App\Models\Users\Investor\InvestorPasswordHistories;
use App\Models\Users\Investor\InvestorPasswordRenewal;
use App\Models\Users\Investor\InvestorPasswordAttemp;
use App\Models\Users\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades;
use Schema;
use Session;
use Log;

class InvestorController extends AppController
{
    protected $table = 'Users\Investor\Investor';
    protected $investorRepository;

    public function __construct(InvestorAuthRepositoryInterface $investorRepository)
    {
        $this->investorRepository = $investorRepository;
    }

    public function change_password(Request $request)
    {
        try {
            $error = ['error_code' => 422, 'error_msg' => ['Unauthorized']];
            $data = [];
            if (Auth::id()) {
                $validate = ['old_password' => 'required|min:8', 'password' => 'required|confirmed|min:8'];
                if (!empty($this->app_validate($request, $validate)))
                    exit;

                if (Auth::user()->email == $request->input('password')) {
                    $error = ['error_code' => 422, 'error_msg' => ['Password cannot be the same as username']];
                } else {
                    if (Hash::check($request->input('old_password'), Auth::user()->password)) {
                        $request->request->add(['token' => null, 'password' => app('hash')->make($request->input('password'))]);
                        $this->db_save($request, Auth::id(), $this->form_ele());
                        $data = ['id' => Auth::id()];
                        $error = [];
                    } else {
                        $error = ['error_code' => 422, 'error_msg' => ['Invalid password']];
                    }
                }
            }
            return $this->app_response('Change Password', $data, $error);
        } catch (\Exception $e) {
            return $this->app_catch($e);
        }
    }

    public function check_identity(Request $request)
    {
        try {
            $info = '';
            $msg = 'not registered';
            $idNo = $request->input('identity_no');
            $email = $request->input('email');
            if (!empty($idNo)) {
                $ws = Investor::where([['is_active', 'Yes'], ['identity_no', $idNo]])->first();
                if (!empty($ws)) {
                    $msg = '';
                    $inv = Investor::where('is_active', 'Yes')->where(function ($qry) use ($request) {
                        return empty($request->email) ? $qry->where([[DB::raw('LOWER(identity_no)'), $request->identity_no], ['valid_account', 'Yes']])->orWhere([[DB::raw('LOWER(identity_no)'), $request->identity_no], [DB::raw('LENGTH(email)'), '>', 3]]) :
                            $qry->where([[DB::raw('LOWER(identity_no)'), $request->identity_no], ['valid_account', 'Yes']])->orWhere(DB::raw('LOWER(email)'), $request->email);
                    })->first();
                    if (!empty($inv->investor_id)) {
                        $msg = 'registered';
                        if (($idNo && $inv->identity_no == $idNo) && ($email && $inv->email == $email)) {
                            $info = 'Identity Number (' . $inv->identity_no . ') & Email (' . $inv->email . ')';
                        } else {
                            $info = $inv->identity_no == $idNo ? 'Identity Number (' . $inv->identity_no . ')' : 'Email (' . $inv->email . ')';
                        }
                    } else {
                        //check priority or pre approved
                        $cards = $this->investorRepository->checkCif($ws->cif);
                        if (empty($cards->investor_card_id) || (!$cards->is_priority && !$cards->pre_approve)) {
                            return $this->app_response('Investor', ['msg' => 'non priority or pre approve', 'info' => $info]);
                        }
                    }
                }
            }
            return $this->app_response('Investor', ['msg' => $msg, 'info' => $info]);
        } catch (\Exception $e) {
            return $this->app_catch($e);
        }
    }

    public function check_reset(Request $request)
    {
        try {
            $res = ResetPassword::where([['investor_id', Auth::id()], ['token', $request->token], ['is_active', 'Yes']])->count() > 0 ? true : false;
            return $this->app_response('Check Reset Password', $res);
        } catch (\Exception $e) {
            return $this->app_catch($e);
        }
    }

    /**
     * Request an email verification email to be sent.
     *
     * @param  Request  $request
     * @return Response
     */
    public function emailRequestVerification(Request $request)
    {
        try {
            $user = $request->user();
    
            if ($user->hasVerifiedEmail()) {
                return $this->app_response('Verify Email', 'Email address is already verified.');
            }
    
            $user->sendEmailVerificationNotification();
    
            return $this->app_response('Verify Email', 'Email request verification sent to ' . $user->email);
        } catch (\Exception $e) {
            return $this->app_catch($e);
        }
    }

    /**
     * Verify an email using email and token from email.
     *
     * @param  Request  $request
     * @return Response
     */
    public function emailVerify(Request $request)
    {
        try {
            if (!empty($this->app_validate($request, ['token' => 'required|string']))) {
                exit();
            }

            \Tymon\JWTAuth\Facades\JWTAuth::getToken();
            \Tymon\JWTAuth\Facades\JWTAuth::parseToken()->authenticate();
            $token = $user = '';
            if (!$request->user()) {
                $status = 'invalid';
            } else {
                $user = $request->user();
                if ($request->user()->hasVerifiedEmail()) {
                    $status = 'already verified';
                } else {
                    $status = 'verified';
                    $token = $request->input('token');
                    $code = rand(1000, 9999);

                    $request->user()->markEmailAsVerified();

                    if (!empty($request->user()->mobile_phone)) {
                        $request->request->add(['otp' => $code, 'otp_created' => $this->app_date('', 'Y-m-d H:i:s'), 'token' => null]);

                        //$conf   = Config::where([['config_name', 'OTPMessage'], ['is_active', 'Yes']])->first();
                        //$msg    = !empty($conf->config_value) ? str_replace('{otp_code}', $code, $conf->config_value) : '';
                        $conf = MobileContent::where([['mobile_content_name', 'Registration'], ['is_active', 'Yes']])->first();
                        $msg = !empty($conf->mobile_content_text) ? str_replace('{otp_code}', $code, $conf->mobile_content_text) : '';

                        $this->api_ws(['sn' => 'SmsGateway', 'val' => [$request->user()->mobile_phone, $msg]]);

                        $this->db_save($request, Auth::id(), ['validate' => true]);
                    }
                }
            }

            return $this->app_response('Verify Email', ['status' => $status, 'token' => $token, 'user' => $user]);
        } catch (\Exception $e) {
            return $this->app_catch($e);
        }
    }

    protected function form_ele()
    {
        return [
            'validate' => true
        ];
    }

    public function getInvestor($request)
    {
        try {
            return $this->api_ws(['sn' => 'InvestorCRM', 'val' => [$request->input('identity_no')]])->original['data'];
        } catch (\Exception $e) {
            return $this->app_catch($e);
        }
    }

    public function getInvestorRiskProfile($cif)
    {
        try {
            return $this->api_ws(['sn' => 'InvestorRiskProfile', 'val' => [$cif]])->original['data'];
        } catch (\Exception $e) {
            return $this->app_catch($e);
        }
    }

    private function investor_data()
    {
        $client['address'] = $this->api_ws(['sn' => 'InvestorAddress', 'val' => [Auth::user()->identity_no]])->original['data'];
        $client['account'] = $this->api_ws(['sn' => 'InvestorAccount', 'val' => [Auth::user()->cif]])->original['data']; //pakai yg ini
        $client['sales'] = $this->api_ws(['sn' => 'InvestorSales', 'val' => [Auth::user()->identity_no]])->original['data'];
        $client['question'] = $this->api_ws(['sn' => 'InvestorRiskProfileDetail', 'val' => [Auth::user()->identity_no]])->original['data'];
        return (object) $client;
    }

    /**
     * Get a JWT via given credentials.
     *
     * @param  Request  $request
     * @return Response
     */

    /* 
    public function login(Request $request)
    {
        try 
        {
            if (!empty($this->app_validate($request, ['email' => 'required|string|email', 'password' => 'required|string|min:1'])))
            {
                exit();
            }

            $data   = $error = [];
            $msg    = 'Login Failed';
            $err    = 'Email or Password is wrong';            
            $config = new GeneralController;

            if (! $token = Auth::attempt($request->only(['email', 'password'])))
            {
                //check whether email password ok, but not active
                $ch = Investor::where([['email', $request->email], ['is_active', 'Yes']])->first();
                if (!empty($ch->investor_id))
                {
                    if ($ch->is_enable == 'No')
                    {
                        $err = 'Sorry, your account has been disabled';
                    }
                    else
                    {
                        $configPassword = $config->password()->original['data'];
                        $chAtempt       = InvestorPasswordAttemp::where([['investor_id', $ch->investor_id], ['is_active', 'Yes']])->first();
                        $attempCount    = 1;
                        if (!isset($chAtempt->attempt_count))
                        {
                            InvestorPasswordAttemp::create([
                                'investor_id'    => $ch->investor_id,
                                'attempt_count'  => $attempCount,
                                'is_active'      => 'Yes',
                                'created_by'     => 'System',
                                'created_host'   => $_SERVER['SERVER_ADDR']
                            ]);
                        }
                        else
                        {
                            $attempCount = ($chAtempt->attempt_count + 1);
                            InvestorPasswordAttemp::where('investor_id', $ch->investor_id)->update(['attempt_count' => $attempCount, 'updated_host' => $_SERVER['SERVER_ADDR']]);                            
                        }

                        if ($attempCount >= $configPassword['PasswordInvalid'])
                        {
                            Investor::where([['investor_id', $ch->investor_id], ['is_active', 'Yes']])->update(['is_enable' => 'No']);
                            $err = 'Sorry, your account password has been block, because '. $configPassword['PasswordInvalid'] .' time, wrong password'; 
                        }
                        else
                        {
                            $err = 'Sorry, your password wrong, you was '. $attempCount .' time (maximum '. $configPassword['PasswordInvalid'] .' attempt)'; 
                        }
                    }
                }
                else
                {
                    $ch = Investor::where([['email', $request->email], ['is_active', 'No']])->orderBy('valid_account', 'desc')->first();
                    if (!empty($ch->investor_id) && app('hash')->check($request->password, $ch->password) && $ch->valid_account == 'Yes') 
                        $err = 'Sorry, your account has been delete';
                    else
                        $err = 'Sorry, your account is not registered';
                }
                $error  = ['error_code' => 422, 'error_msg' => $err];
            }
            else
            {
                $msg    = 'Login Success';
                $result = (array) $this->respondWithToken($token);
                $data   = ['expires_in'     => $result['original']['expires_in'],
                           'token'          => $result['original']['token'],
                           'token_type'     => $result['original']['token_type'],
                           'user'           => Auth::user()
                          ];
                if (Auth::user()->valid_account == 'Yes')
                {
                    if (empty(Auth::user()->token))
                        Investor::where('investor_id', Auth::id())->update(['token' => $result['original']['token'],'last_activity_at'=>$this->app_date('', 'Y-m-d H:i:s')]);                    
                    //$this->update_info();
                }
                
                InvestorPasswordAttemp::where('investor_id', Auth::id())->update(['is_active' => 'No']);
                
                if (!empty(Auth::user()->last_activity_at))
                {
                    $timediff = round((time()- strtotime(Auth::user()->last_activity_at))/60);
                    if ($timediff > env('IDLE_TIMEOUT',15))
                    {
                        Investor::where([['email', $request->email], ['is_active', 'Yes']])->update(['last_activity_at' => date('Y-m-d H:i:s'), 'token' => null]);
                    }
                }
                
                /* ditutup sementara (sedang fokus di Feedback Pentest BSI, setelah itu fitur nya di lanjutkan)
                $configPassword = $config->password()->original['data'];
                $chkPassTime    = InvestorPasswordHistories::where('investor_id', Auth::id())->orderBy('created_at', 'desc')->first();
                $your_date      = !empty($chkPassTime->created_at) ? strtotime($chkPassTime->created_at) : 0;
                $datediffDay    = round((time() - $your_date) / (60 * 60 * 24));
                $dayPassExp     = $configPassword['PasswordExpiredDate'] != 'year' ? $configPassword['PasswordExpiredDate'] == 'day' ? $configPassword['PasswordExpired'] : $configPassword['PasswordExpired'] * 30 : $configPassword['PasswordExpired'] * 365;

                if ($datediffDay > $dayPassExp) 
                {                 
                    $token  = base64_encode(uniqid().'~'.($request->input('email').'~'.Auth::id()));
                    $error  = ['error_code' => 422, 'error_msg' => 'Sorry, your account password has been expire, please renewal'];
                    $link   = env('MAIN_URL') . 'renewal-investor/' . $token;
                    
                    InvestorPasswordRenewal::where('investor_id', Auth::id())->update(['is_active' => 'No']); 
                    InvestorPasswordRenewal::create([
                        'investor_id'    => Auth::id(),
                        'link_uniq_code' => $token, 
                        'created_time'   => $this->app_date('', 'Y-m-d H:i:s'),  
                        'expired_time'   => date('Y-m-d H:i:s', strtotime('+24 hour')),  
                        'is_active'      => 'Yes',
                        'created_by'     => 'System',
                        'created_host'   => $_SERVER['SERVER_ADDR'], 
                        'created_at'     => date('Y-m-d H:i:s')
                    ]);

                    //$this->app_sendmail(['to' => $request->input('email'), 'content' => 'Renewal Password']);
                } 
                
            }

            return $this->app_response($msg, $data, $error);
        } 
        catch (\Exception $e) 
        {
            return $this->app_catch($e);
        }
    }
    */

    public function login(Request $request)
    {
        try
        {
            if (!empty($this->app_validate($request, ['email' => 'required|string|email', 'password' => 'required|string|min:1'])))
            {
                exit();
            }

            $data = $error = [];
            $updToken = null;
            $msg = 'Login Failed';
            $err = 'Email or Password is wrong';
            $preCheckSign = true;
            $preCheckSendEmailRenewalPassword = false;

            /* ditutup sementara (sedang fokus di Feedback Pentest BSI, setelah itu fitur nya di lanjutkan)             
            if(isset($preCheck[0]->investor_id)) {
                $checkPasswordTimeCreated = InvestorPasswordHistories::where('investor_id',$preCheck[0]->investor_id)->orderBy('created_at', 'desc')->first();
                $now = time(); 
                $your_date   = !empty($checkPasswordTimeCreated->created_at) ? strtotime($checkPasswordTimeCreated->created_at) : 0;
                $datediff    = ($now - $your_date);
                $datediffDay = round($datediff / (60 * 60 * 24));
                $dayPasswordExpired = 0;

                if($configPassword['PasswordExpiredDate'] == 'month') 
                {
                   $dayPasswordExpired = ($configPassword['PasswordExpired'] * 30);  
                }

                if($configPassword['PasswordExpiredDate'] == 'year') 
                {
                   $dayPasswordExpired = ($configPassword['PasswordExpired'] * 365);  
                }

                if($configPassword['PasswordExpiredDate'] == 'day') 
                {
                   $dayPasswordExpired = $configPassword['PasswordExpired'];  
                }

                if($datediffDay > $dayPasswordExpired) 
                {
                    $err = 'Sorry, your account password has been expire, please renewal'; 
                    $preCheckSign = false; 
                    $preCheckSendEmailRenewalPassword = true;
                }
            }  
            */

            if (!$token = Auth::attempt($request->only(['email', 'password'])))
            {
                //check whether email password ok, but not active
                $ch = Investor::where([['email', $request->email], ['is_active', 'Yes']])->first();
                if (!empty($ch) && !empty($ch->investor_id))
                {
                    $last_activity_at = $ch->last_activity_at;
                    if ($ch->is_enable == 'No')
                    {
                        $err = 'Sorry, your account has been disabled';
                    }
                    else
                    {
                        $attempCount = 1;
                        $chAtempt = InvestorPasswordAttemp::where('investor_id', $ch->investor_id)->first();
                        if (!isset($chAtempt->attempt_count))
                        {
                            InvestorPasswordAttemp::where('investor_id', $ch->investor_id)->update(['is_active' => 'No']);
                            InvestorPasswordAttemp::create([
                                'investor_id' => $ch->investor_id,
                                'attempt_count' => $attempCount,
                                'is_active' => 'Yes',
                                'created_by' => 'System',
                                'created_host' => '127.0.0.1',
                                'created_at' => $this->app_date('', 'Y-m-d H:i:s')
                            ]);
                        }
                        else
                        {
                            $attempCount = ($chAtempt->attempt_count + 1);
                            InvestorPasswordAttemp::where('investor_id', $ch->investor_id)->update(['attempt_count' => $attempCount, 'created_host' => '127.0.0.1']);
                        }                                 

                        $generalControllerClass = new GeneralController;
                        $configPassword = $generalControllerClass->password();
                        $configPassword = isset($configPassword->original) && isset($configPassword->original['data']) ? $configPassword->original['data'] : [];
                        $maxPassInvalid = isset($configPassword['PasswordInvalid']) ? $configPassword['PasswordInvalid'] : 0;

                        if ($attempCount >= $maxPassInvalid)
                        {
                            $err = 'Sorry, your account password has been block, because '. $maxPassInvalid .' time, wrong password'; 
                            $preCheckSign = false;   
                            Investor::where([['email', $request->email], ['is_active', 'Yes']])->update(['is_enable' => 'No']);
                        }
                        else
                        {
                            $err = 'Sorry, your password wrong, you was '. $attempCount .' time (maximum '. $maxPassInvalid .' attempt)'; 
                        }
                    }
                }
                $error = ['error_code' => 422, 'error_msg' => $err];
            }
            else
            {
                $user = Auth::user();
                $last_activity_at = $user->last_activity_at;
                if ($user->is_enable == 'Yes')
                {
                    $msg = 'Login Success';
                    $result = (array) $this->respondWithToken($token);
                    $data = [
                        'expires_in' => $result['original']['expires_in'],
                        'token' => $result['original']['token'],
                        'token_type' => $result['original']['token_type'],
                        'user' => $user
                    ];
                    if ($user->valid_account == 'Yes')
                    {
                        if (empty($user->token))
                        {
                            $updToken = $result['original']['token'];
                        }
                        $this->update_info();
                    }
                    InvestorPasswordAttemp::where('investor_id', $user->investor_id)->update(['is_active' => 'No', 'attempt_count' => 0]);
                }
                else
                {
                    $error = ['error_code' => 422, 'error_msg' => 'Sorry, your account has been disabled'];
                }
            }

            if (isset($last_activity_at) && !empty($last_activity_at))
            {
                $timediff = round((time() - strtotime($last_activity_at)) / 60);
                if ($timediff > env('IDLE_TIMEOUT', 15))
                {
                    Investor::where([['email', $request->email], ['is_active', 'Yes']])->update(['last_activity_at' => $this->app_date('', 'Y-m-d H:i:s'), 'token' => $updToken]);
                }
            }

            //ditutup sementara (sedang fokus di Feedback Pentest BSI, setelah itu fitur nya di lanjutkan)  
            /*} else {
                if($preCheckSendEmailRenewalPassword) {
                    $ch = Investor::where('email', $request->email)->get();                
                    InvestorPasswordRenewal::where('investor_id', $ch[0]->investor_id)->update(['is_active' => 'No']);                    
                    $token = base64_encode(uniqid().'~'.($request->input('email').'~'.$ch[0]->investor_id));
                    InvestorPasswordRenewal::create([
                        'investor_id'    => $ch[0]->investor_id,
                        'link_uniq_code' => $token, 
                        'created_time'   => $this->app_date('', 'Y-m-d H:i:s'),  
                        'expired_time'   => date('Y-m-d H:i:s', strtotime('+24 hour')),  
                        'is_active'      => 'Yes',
                        'created_by'     => 'System',
                        'created_host'   => $_SERVER["SERVER_ADDR"], 
                        'created_at'     => $this->app_date('', 'Y-m-d H:i:s')
                    ]);

                    $link = env('MAIN_URL') . 'renewal-investor/' . $token;
                    //$this->app_sendmail(['to' => $request->input('email'), 'content' => 'Renewal Password']);        
                }
                $error  = ['error_code' => 422, 'error_msg' => $err];                
            }*/

            return $this->app_response($msg, $data, $error);
        }
        catch (\Exception $e)
        {
            \Log::error('Exception', ['exception' => $e]);
            return response()->json(['error' => 'gagal'], 500);
            return $this->app_catch($e);
        }
    }

    public function logout()
    {
        if (Auth::user()) {
            Investor::where('investor_id', Auth::id())->update(['token' => null]);
            JWTAuth::invalidate(JWTAuth::getToken());
        }
        return $this->app_response('Logout', 1);
    }

    public function password_email(Request $request)
    {
        try {
            $user = AuthInvestor::where([['email', $request->email], ['is_active', 'Yes'], ['valid_account', 'Yes']])->first();
            if (!empty($user->investor_id)) {
                $token = JWTAuth::fromUser($user);
                $request->request->add(['investor_id' => $user->investor_id, 'password' => $user->password, 'email' => $user->email, 'token' => $token]);
                $this->db_save($request, null, ['table' => 'Users\Investor\ResetPassword', 'res' => 'id']);

                $link = env('MAIN_URL') . 'password/verify/' . $token;
                $this->app_sendmail(['to' => $user->email, 'content' => 'Reset Password', 'new' => [$user->fullname, $user->email, $link]]);
            }
            return $this->app_response('Password', ['email' => $request->email]);
        } catch (\Exception $e) {
            return $this->app_catch($e);
        }
    }

    public function password_reset(Request $request)
    {
        try {
            $error = ['error_code' => 422, 'error_msg' => ['Unauthorized']];
            $data = [];
            if (Auth::id()) {
                if (!empty($this->app_validate($request, ['password' => 'required|confirmed|min:8'])))
                    exit;

                ResetPassword::where('investor_id', Auth::id())->update(['is_active' => 'No']);

                $request->request->add(['password' => app('hash')->make($request->input('password'))]);
                return $this->db_save($request, Auth::id(), $this->form_ele());
            }
            return $this->app_response('Reset Password', '');
        } catch (\Exception $e) {
            return $this->app_catch($e);
        }
    }

    public function password_verify(Request $request)
    {
        try {
            $status = 'invalid';

            if (!empty($this->app_validate($request, ['token' => 'required|string']))) {
                exit();
            }

            if ($request->user()) {
                $status = 'verified';
            }

            return $this->app_response('Verify Password', ['status' => $status]);
        } catch (\Exception $e) {
            return $this->app_catch($e);
        }
    }

    /**
     * Store a new user.
     *
     * @param  Request  $request
     * @return Response
     */
    public function register(Request $request)
    {
        try {
            // Ambil aturan password dari repository
            $passwordRules = $this->investorRepository->getPasswordRules();

            // Validasi request menggunakan RegisterValidator, sambil meneruskan instance controller
            $validationErrors = RegisterValidator::validate($this, $request, $passwordRules);

            if ($validationErrors) {
                return $this->app_response('Validation Failed', [], $validationErrors);
            }

            $investor = $this->investorRepository->findByIdentity($request->identity_no);

            if (!empty($investor)) {
                //check investor priority or pre approve
                $cards = $this->investorRepository->checkCif($investor->cif);
                if (empty($cards->investor_card_id) || (!$cards->is_priority && !$cards->pre_approve)) {
                    return $this->app_response('Registration Failed', [], ['error_code' => 403, 'error_msg' => ['Non-priority or pre-approve investor']]);
                }

                $num_inv = $this->investorRepository->findByIdentityAndEmail($request->identity_no, $request->email);
                if ($num_inv == 0) {
                    // Persiapkan data yang akan dikirim ke repository
                    $data = [
                        'identity_no' => $request->identity_no,
                        'email' => $request->email,
                        'password' => $request->password,
                        'ip' => $request->ip() ?? '::1',
                    ];

                    $this->investorRepository->updateInvestor($investor, $data);

                    Auth::attempt($request->only(['email', 'password']));

                    $this->emailRequestVerification($request);
                    
                    return $this->app_response('Registration Success', ['id' => Auth::id()]);
                } else {
                    return $this->app_response('Registration Failed', [], ['error_code' => 409, 'error_msg' => ['Investor already registered']]);
                }
            } else {
                return $this->app_response('Registration Failed', [], ['error_code' => 404, 'error_msg' => ['Identity number not registered']]);
            }
            
        } catch (\Exception $e) {
            return $this->app_catch($e);
        }
    }

    public function resend_otp(Request $request)
    {
        $code = rand(1000, 9999);
        if (!empty(Auth::user()->mobile_phone)) {
            //$conf   = Config::where([['config_name', 'OTPMessage'], ['is_active', 'Yes']])->first();
            //$msg    = !empty($conf->config_value) ? str_replace('{otp_code}', $code, $conf->config_value) : '';
            $conf = MobileContent::where([['mobile_content_name', 'Resend_otp'], ['is_active', 'Yes']])->first();
            $msg = !empty($conf->mobile_content_text) ? str_replace('{otp_code}', $code, $conf->mobile_content_text) : '';
            $this->api_ws(['sn' => 'SmsGateway', 'val' => [Auth::user()->mobile_phone, $msg]]);
        }
        $request->request->add(['otp' => $code, 'otp_created' => $this->app_date('', 'Y-m-d H:i:s')]);
        $this->db_save($request, Auth::id(), $this->form_ele());
        return $this->app_response('Resend OTP', ['user' => Auth::user(), 'otp' => $code]);
    }

    public function resetpassword(Request $request)
    {
        try {
            $user = Investor::select('fullname')->where([['is_active', 'Yes'], ['email', $request->input('email')]])->first();
            return $this->app_sendmail(['to' => $request->input('email'), 'content' => 'Forget Password', 'new' => [$user->fullname]]);
        } catch (\Exception $e) {
            return $this->app_catch($e);
        }
    }

    public function sso(Request $request)
    {
        if (Auth::user()) {
            $user = [];
        } else {
            $user = Investor::find($request->id);
            if ($user->email_verified_at && $user->valid_account == 'Yes')
                Investor::where('investor_id', $request->id)->update(['token' => $request->token]);
        }
        return $this->app_response('SSO', $user);
    }

    public function sync_bankaccount_data()
    {
        try {
            $investor = $this->api_ws(['sn' => 'InvestorAccount', 'val' => [Auth::user()->cif]])->original['data']; //pakai yg ini
            $updated = [];
            if (!empty($investor)) {
                foreach ($investor as $dtv) {
                    $new_acc_no = $dtv->accountNo; //update Acc No
                    $new_acc_name = $dtv->accountName; //update Acc Name
                    $inv = Investor::where([['cif', Auth::user()->cif], ['is_active', 'Yes']])->get();
                    foreach ($inv as $dt) {
                        // $account = Account::where([['investor_id', $dt->investor_id],['account_no', $new_acc_no], ['is_active', 'Yes']]);
                        $account = Account::where([['investor_id', $dt->investor_id], ['account_no', $new_acc_no]]); //coba
                        if (!$account->get()->isEmpty()) {
                            //update name only
                            $account->update(['account_no' => $new_acc_no, 'account_name' => $new_acc_name]);
                        } else {
                            //rubah semua menjadi tidak aktif
                            Account::where([['investor_id', $dt->investor_id]])->update(['is_active' => 'No']);
                            //insert new baris
                            Account::create([
                                'investor_account_id' => Auth::id(),
                                'investor_id' => $dt->investor_id,
                                'currency_id' => 1,
                                'bank_branch_id' => !empty($dtv->bankBranchId) ? $this->db_row('bank_branch_id', ['where' => [['branch_name', $dtv->bankBranch]]], 'SA\Reference\Bank\Branch')->original['data'] : null,
                                'account_type_id' => !empty($dtv->accountType) ? $this->db_row('account_type_id', ['where' => [['account_type_name', $dtv->accountType]]], 'SA\Reference\Bank\AccountType')->original['data'] : null,
                                'account_name' => $new_acc_name, //$ac->accountName,
                                'account_no' => $new_acc_no, //$dtv->->accountNo,
                                'ext_code' => !empty($dtv->id) ? $dtv->id : 0,
                                'is_data' => 'WS',
                                'is_active' => 'Yes',
                                'created_by' => Auth::user()->usercategory_name . ':' . Auth::id() . ':' . Auth::user()->fullname,
                                'created_host' => $_SERVER["SERVER_ADDR"] //ip
                            ]);
                        }
                        $updated[] = $dt;
                    }
                }
                return $this->app_response('Account', ['success' => true, 'data' => $updated], []); //berhasil
            } else {
                return $this->app_response('Account', ['success' => false, 'error_msg' => 'Data Empty']);
            }
        } catch (\Exception $e) {
            return $this->app_catch($e);
        }
    }

    public function sync_bankaccount_data_lama()
    {
        try {
            $investor = $this->api_ws(['sn' => 'InvestorAccount', 'val' => [Auth::user()->cif]])->original['data']; //pakai yg ini
            $updated = [];
            if (!empty($investor)) {
                foreach ($investor as $dtv) {
                    $new_acc_no = $dtv->accountNo; //update Acc No
                    $new_acc_name = $dtv->accountName; //update Acc Name
                    $inv = Investor::where([['cif', Auth::user()->cif], ['is_active', 'Yes']])->get();
                    foreach ($inv as $dt) {
                        $account = Account::where([['investor_id', $dt->investor_id], ['is_active', 'Yes']])->update(['account_no' => $new_acc_no, 'account_name' => $new_acc_name]);
                        $updated[] = $dt;
                    }
                }
                return $this->app_response('Account', ['success' => true, 'data' => $updated], []); //berhasil
            } else {
                return $this->app_response('Account', ['success' => false, 'error_msg' => 'Data Empty']);
            }
        } catch (\Exception $e) {
            return $this->app_catch($e);
        }
    }

    private function update_info()
    {
        $risk_profile = $this->getInvestorRiskProfile(Auth::user()->cif);
        if (!empty($risk_profile)) {
            $api_wms = $this->api_ws(['sn' => 'Investor', 'val' => [Auth::user()->cif]])->original['data'];
            $profile_id = !empty($risk_profile->profile) ? $this->db_row('profile_id', ['where' => [['profile_name', $risk_profile->profile]]], 'SA\Reference\KYC\RiskProfiles\Profile')->original['data'] : null;
            $effective_date = !empty($risk_profile->effectiveDate) ? $risk_profile->effectiveDate : null;
            $expired_date = !empty($risk_profile->expiredDate) ? $risk_profile->expiredDate : null;

            $inv = Investor::where('investor_id', Auth::user()->investor_id)->first();
            $profile_id = !empty($profile_id) ? $profile_id : $inv->profile_id;
            $sid = !empty($api_wms->sidMf) ? $api_wms->sidMf : $inv->sid;
            $ifua = !empty($api_wms->ifua) ? $api_wms->ifua : $inv->ifua;
            Investor::where('investor_id', Auth::user()->investor_id)->update(['profile_id' => $profile_id, 'profile_effective_date' => $effective_date, 'profile_expired_date' => $expired_date, 'sid' => $sid, 'ifua' => $ifua]);
        }
    }

    public function user_auth()
    {
        return $this->app_response('Auth', Auth::user());
    }

    public function valid_account(Request $request)
    {

        try {
            $phone = '';
            $status = 'unauthorized';
            $msg = 'Permission denied';
            if (!empty(Auth::id())) {
                $phone = Auth::user()->mobile_phone;
                $status = 'success';
                $msg = 'Success';
                $data = Investor::where([['investor_id', Auth::id()], ['otp', implode($request->input('otp_num'))]])->first();

                if (!empty($data->investor_id)) {
                    if (strtotime('+1 minute', strtotime($data->otp_created)) > strtotime($this->app_date('', 'Y-m-d H:i:s'))) {
                        $investor = $this->investor_data();
                        $ip = $request->input('ip');

                        if (!empty($investor->account)) {
                            foreach ($investor->account as $ac) {
                                if (!empty($ac->accountName) && !empty($ac->accountNo)) {
                                    Account::create([
                                        'investor_id' => Auth::id(),
                                        //'currency_id'       => null,
                                        //'bank_branch_id'    => null,
                                        'currency_id' => !empty($ac->currencyCode) ? $this->db_row('currency_id', ['where' => [['currency_code', $ac->currencyCode]]], 'SA\Assets\Products\Currency')->original['data'] : null,
                                        'bank_branch_id' => !empty($ac->bankBranchId) ? $this->db_row('bank_branch_id', ['where' => [['branch_name', $ac->bankBranch]]], 'SA\Reference\Bank\Branch')->original['data'] : null,
                                        'account_type_id' => !empty($ac->accountType) ? $this->db_row('account_type_id', ['where' => [['account_type_name', $ac->accountType]]], 'SA\Reference\Bank\AccountType')->original['data'] : null,
                                        'account_name' => $ac->accountName,
                                        'account_no' => $ac->accountNo,
                                        //'ext_code'          => 13,
                                        'ext_code' => !empty($ac->accountTypeCode) ? $ac->accountTypeCode : 0,
                                        'is_data' => 'WS',
                                        'created_by' => Auth::user()->usercategory_name . ':' . Auth::id() . ':' . Auth::user()->fullname,
                                        'created_host' => $ip

                                    ]);
                                }
                            }
                        }

                        if (!empty($investor->address)) {
                            foreach ($investor->address as $a) {
                                $prv = !empty($a->provinceCode) ? $this->db_row('region_id', ['where' => [['region_code', $a->provinceCode]]], 'SA\Reference\KYC\Region')->original['data'] : null;
                                $city = !empty($a->cityCode) ? $this->db_row('region_id', ['where' => [['region_code', $a->cityCode]]], 'SA\Reference\KYC\Region')->original['data'] : null;
                                $district = !empty($a->subDistrictCode) ? $this->db_row('region_id', ['where' => [['region_code', $a->subDistrictCode]]], 'SA\Reference\KYC\Region')->original['data'] : null;
                                $address = [];

                                if (!empty($a->address1))
                                    $address[] = $a->address1;
                                if (!empty($a->address2))
                                    $address[] = $a->address2;
                                if (!empty($a->address3))
                                    $address[] = $a->address3;
                                if (!empty($a->address4))
                                    $address[] = $a->address4;
                                if (!empty($a->address5))
                                    $address[] = $a->address5;

                                if (!empty($address) && !empty($a->addressType)) {
                                    Address::create([
                                        'investor_id' => Auth::id(),
                                        'province_id' => $prv,
                                        'city_id' => $city,
                                        'subdistrict_id' => $district,
                                        'postal_code' => !empty($a->postalCode) ? $a->postalCode : null,
                                        'address' => implode(', ', $address),
                                        'address_type' => $a->addressType,
                                        'is_data' => 'WS',
                                        'created_by' => Auth::user()->usercategory_name . ':' . Auth::id() . ':' . Auth::user()->fullname,
                                        'created_host' => $ip
                                    ]);
                                }
                            }
                        }

                        if (!empty($investor->question)) {
                            foreach ($investor->question as $qst) {
                                $qst_id = !empty($qst->questionId) ? $this->db_row('question_id', ['where' => [['ext_code', $qst->questionId]]], 'SA\Reference\KYC\RiskProfiles\Question')->original['data'] : null;
                                if (!empty($qst_id)) {
                                    Question::create([
                                        'investor_id' => Auth::id(),
                                        'profile_id' => Auth::user()->profile_id,
                                        'question_id' => $qst_id,
                                        'answer_id' => !empty($qst->questionOptionId) ? $this->db_row('answer_id', ['where' => [['ext_code', $qst->questionOptionId]]], 'SA\Reference\KYC\RiskProfiles\Answer')->original['data'] : null,
                                        'answer_score' => !empty($qst->optionValue) ? $qst->optionValue : 0,
                                        'repetition' => 1,
                                        'ext_code' => !empty($qst->id) ? $qst->id : null,
                                        'is_data' => 'WS',
                                        'created_by' => Auth::user()->usercategory_name . ':' . Auth::id() . ':' . Auth::user()->fullname,
                                        'created_host' => $ip

                                    ]);
                                }
                            }
                        }

                        $request->request->add(['valid_account' => 'Yes']);
                        $this->db_save($request, Auth::id(), $this->form_ele());
                    } else {
                        $status = 'success';
                        $msg = 'The OTP Number has expired';
                    }
                } else {
                    $status = 'failed';
                    $msg = 'OTP Number does not match';
                }
            }
            return $this->app_response('Valid Account', ['status' => $status, 'msg' => $msg, 'mobile_phone' => $phone]);
        } catch (\Exception $e) {
            return $this->app_catch($e);
        }
    }

    public function check_renewal_password(Request $request)
    {
        try {
            $token = $request->token;
            $respon = InvestorPasswordRenewal::where([['link_uniq_code', $token], ['is_active', 'Yes']])->first();
            $data = array();
            $statusSuccess = true;

            if (!empty($respon)) {
                $investor = Investor::where([['investor_id', $respon->investor_id], ['is_active', 'Yes']])->first();
                $data['link_uniq_code'] = $respon->link_uniq_code;
                $data['fullname'] = $investor->fullname;
                $data['email'] = $investor->email;
                $statusSuccess = true;
            } else {
                $statusSuccess = false;
            }

            return $this->app_response('Renewal Password', ['status' => $statusSuccess, 'msg' => $data]);
        } catch (\Exception $e) {
            return $this->app_catch($e);
        }
    }
}