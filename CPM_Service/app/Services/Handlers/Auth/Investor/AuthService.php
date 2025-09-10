<?php

namespace App\Services\Handlers\Auth\Investor;

use App\Services\Http\MetadataService;
use App\Repositories\Auth\InvestorPasswordAttemptRepository;
use App\Repositories\Users\InvestorRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthService
{
    protected $attemptRepo;
    protected $invRepo;
    protected $metaService;

    public function __construct (
        InvestorPasswordAttemptRepository $attemptRepo, 
        InvestorRepository $invRepo, 
        MetadataService $metaService
    ) {
        $this->attemptRepo = $attemptRepo;
        $this->invRepo = $invRepo;
        $this->metaService = $metaService;
    }

    public function login(array $credentials, int $maxAttempts = 5) {
        try {
            $token = Auth::attempt($credentials);
            if (!$token) {
                return $this->handleFailedLogin($credentials['email'], $maxAttempts);
            } else {
                $user = Auth::user();

                if ($user->email_verified_at === null) {
                    $code = 403;
                    $message = 'Email Not Verified';
                    $errors = ['Email verification is required.'];
                    $data = [
                        'token' => $token,
                        'user' => [
                            'fullname' => $user->fullname,
                            'email' => $user->email,
                            'identity_no' => $user->identity_no,
                        ],
                    ];
                } else if ($user->valid_account !== 'Yes') {
                    $code = 403;
                    $message = 'Account Not Valid';
                    $errors = ['Account is not valid.'];
                    $data = [
                        'token' => $token,
                        'user' => [
                            'mobile_phone' => $user->mobile_phone,
                        ],
                    ];
                } elseif ($user->is_enabled === 'No') {
                    $code = 403;
                    $message = 'Account Disabled';
                    $errors = ['Your account has been disabled.'];
                } else {
                    $last_activity_at = $user->last_activity_at ?? null;
                    $code = 200;
                    $message = 'Login Successful';
                    $data = [
                        'token' => $token,
                        'expires_in' => Auth::factory()->getTTL() * 60 * 3,
                        'user' => $user,
                    ];
                    
                    // if (!empty($user->token) && $this->isTokenValid($user->token)) {
                    //     JWTAuth::invalidate($token); 
                    //     $token = null;

                    //     $code = 403;
                    //     $message = 'Login Denied';
                    //     $errors = ['You are already logged in on another device or browser.'];
                    // }
                }
            }

            if (isset($last_activity_at) && !empty($last_activity_at)) {
                $timediff = round((time() - strtotime($last_activity_at)) / 60);
                if ($timediff > env('IDLE_TIMEOUT', 15)) {
                    $user->last_activity_at = Carbon::now();
                    $user->token = $token ?? null;
                    $user->save();

                    $data = [
                        'token' => $token,
                        'user' => $user,
                    ];
                }
            }
            
            $response =  [
                'status' => $code,
                'message' => $message,
                'data' => $data
            ];

            if (isset($errors) && !empty($errors)) {
                $response['errors'] = $errors;
            }

            return $response;
        } catch (\Exception $e) {
            return [
                'status' => 500,
                'message' => 'Internal Server Error',
                'data' => [],
                'errors' => [$e->getMessage()]
            ];
        }
    }

    private function handleFailedLogin($email, $maxAttempts)
    {
        $code = 401;
        $message = 'Login Failed';
        $errors = ['Email or Password is wrong'];
        $data = [];
        $ch = $this->invRepo->findByEmail($email);
        if (!empty($ch) && !empty($ch->investor_id)) {
            $last_activity_at = $ch->last_activity_at ?? null;
            if ($ch->is_enable == 'No') {
                $errors = ['Sorry, your account has been disabled'];
            } else {
                if ($maxAttempts) {
                    $attemptCount = $this->attemptRepo->incrementAttempt($ch->investor_id);
                    if ($attemptCount >= $maxAttempts) {
                        $this->invRepo->deactivateInvestorById($ch->investor_id);
                        $code = 403;
                        $message = 'Account Blocked';
                        $errors = ["Account has been blocked due to $maxAttempts failed login attempts."];
                    } else {
                        $errors = ["Incorrect password. Attempt $attemptCount of $maxAttempts."];
                    }
                }
            }
        } else {
            $code = 404;
            $message = 'Account Not Found';
            $errors = ['Email not registered or account not found.'];
        }

        return [
            'status' => $code,
            'message' => $message,
            'errors' => $errors
        ];
    }

    protected function isTokenValid($token): bool
    {
        try {
            JWTAuth::setToken($token)->authenticate();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function updateInfoInvestor($user) {
        $metaRisk = $this->metaService->callServiceApi('InvestorRiskProfile', [$user->cif]);
        $riskProfile = $metaRisk['data'] ?? null;
        if (!empty($riskProfile)) {
            $profile_id = null;
            if (!empty($riskProfile->profile)) {
                $riskDB = DB::table('m_risk_profiles')
                    ->where('profile_name', $riskProfile->profile)
                    ->first();
                $profile_id = !empty($riskDB) ? $riskDB->profile_id : null;
            }
            $effective_date = !empty($riskProfile->effectiveDate) ? $riskProfile->effectiveDate : null;
            $expired_date = !empty($riskProfile->expiredDate) ? $riskProfile->expiredDate : null;

            $metaInvestor = $this->metaService->callServiceApi('Investor', [$user->cif]);
            $investor = $metaInvestor['data'] ?? null;
            $profile_id = !empty($profile_id) ? $profile_id : $user->profile_id;
            $sid = !empty($investor['sidMf']) ? $investor['sidMf'] : $user->sid;
            $ifua = !empty($investor['ifua']) ? $investor['ifua'] : $user->ifua;

            $this->invRepo->updateProfileById($user->investor_id, [
                'profile_id' => $profile_id,
                'sid' => $sid,
                'ifua' => $ifua,
                'effective_date' => $effective_date,
                'expired_date' => $expired_date,
            ]);
        }
    }

    public function verifyLoginOtp(string $investorId, string $otp): array
    {
        try {
            $now    = Carbon::now();
            $cutoff = $now->copy()->subMinutes(2);

            // Ambil record OTP yang match (investor + otp) dan masih fresh
            $row = $this->invRepo->findByIdWithOtp($investorId, $otp);

            if (!$row) {
                return [
                    'status' => 422, 
                    'message' => 'Verification failed.',
                    'errors' => ['The OTP you entered is incorrect.']
                ];
            }

            if (empty($row->otp_created)) {
                return [
                    'status' => 422,
                    'message' => 'Verification not available.',
                    'errors' => ['No active OTP found for this account. Request a new code.']
                ];
            }

            $created = Carbon::parse($row->otp_created);
            if ($created->lt($cutoff)) {
                $this->invRepo->clearOtpById($investorId);

                return [
                    'status' => 422,
                    'message' => 'Verification expired.',
                    'errors' => ['The OTP has expired (older than 2 minutes). Request a new code.']
                ];
            }

            $this->invRepo->clearOtpById($investorId);

            return [
                'status' => 200, 
                'message' => 'OTP verified.',
                'data' => [
                    'investor' => $row,
                ]
            ];
        } catch (\Throwable $e) {
            Log::error('[verifyLoginOtp] Error: '.$e->getMessage(), ['investor_id' => $investorId]);
            return [
                'status' => 500, 
                'message' => 'Server error.',
                'errors' => ['An unexpected error occurred during OTP verification. Please try again.']
            ];
        }
    }
}