<?php

namespace App\Http\Controllers\Auth\Investor;

use App\Http\Controllers\Controller;
use App\Repositories\Users\InvestorRepository;
use App\Services\Http\MetadataService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;

class EmailVerifyController extends Controller
{
    protected $metaService;
    protected $investorRepository;

    public function __construct(MetadataService $metaService, InvestorRepository $investorRepository) {
        $this->metaService = $metaService;
        $this->investorRepository = $investorRepository;
    }

    public function verifyEmail(Request $request)
    {
        try {
            $validation = validateRequest($request->all(), ['token' => 'required|string']);
            if ($validation->fails()) {
                $errors = $validation->errors();
                return apiResponse('Validation Failed', [], $errors->all(), 422);
            }

            $token = $request->input('token');

            try {
                $jwtToken = JWTAuth::getToken();
                if (!$jwtToken) {
                    Log::warning('[Email Verify] Token not found in Authorization header');
                    return apiResponse('Not Provided', [], ['Verification token is missing.'], 401);
                }

                $user = JWTAuth::parseToken()->authenticate();
            } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
                return apiResponse('Expired', [], ['Verification link has expired'], 401);
            } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
                return apiResponse('Invalid', [], ['Verification link is invalid or has already been used'], 401);
            } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
                return apiResponse('Missing', [], ['Verification link is malformed or missing'], 401);
            }

            $userId = $user->investor_id ?? null;
            Log::info('[Email Verify] Authenticated user', ['user_id' => $userId]);

            $status = '';

            // Sudah terverifikasi
            if ($user->hasVerifiedEmail() && $user->valid_account === 'Yes') {
                $status = 'Already Verified';
                Log::info('[Email Verify] Email already verified and account valid', ['user_id' => $userId]);
            } else {
                // Proses verifikasi
                $status = 'Verified';                

                $user->markEmailAsVerified();
                Log::info('[Email Verify] Email marked as verified', ['user_id' => $userId]);

                $this->sendOtp($user);
            }

            return apiResponse(
                'Verify Email',
                ['status' => $status, 'token' => $token, 'user' => $user]
            );

        } catch (\Exception $e) {
            Log::error('Email Verification Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return apiResponse('Internal Server Error', [], ['An unexpected error occurred.'], 500);
        }
    }

    public function sendOtp($user) {
        if (!empty($user->mobile_phone)) {
            $userId = $user->investor_id ?? null;
            $code = rand(1000, 9999);
            $conf = DB::table('c_mobile_contents')
                    ->where('mobile_content_name', 'Registration')
                    ->where('is_active', 'Yes')
                    ->first();

            $msg = !empty($conf->mobile_content_text)
                ? str_replace('{otp_code}', $code, $conf->mobile_content_text)
                : 'Kode OTP Anda: ' . $code;

            $this->metaService->callServiceApi('SmsGateway', [$user->mobile_phone, $msg]);
            
            $this->investorRepository->updateOtpById($userId, $code);

            Log::info('[Email Verify] OTP generated and SMS sent', [
                'user_id' => $userId,
                'otp' => $code,
                'mobile' => $user->mobile_phone
            ]);
        }
    }
}