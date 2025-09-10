<?php

namespace App\Http\Controllers\Auth\Investor;

use App\Http\Controllers\Controller;
use App\Mail\SendMail as SendMailService;
use App\Repositories\Users\InvestorRepository;
use App\Services\Handlers\Auth\PasswordRuleService;
use App\Services\Handlers\Auth\Investor\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    protected $authService;
    protected $passwordRuleService;
    protected $invRepo;
    protected $sendMailService;

    public function __construct(
        AuthService $authService, 
        PasswordRuleService $passwordRuleService, 
        InvestorRepository $invRepo,
        SendMailService $sendMailService
    ) {
        $this->authService = $authService;
        $this->passwordRuleService = $passwordRuleService;
        $this->invRepo = $invRepo;
        $this->sendMailService = $sendMailService;
    }

    public function login(Request $request) {
        try {
            $passwordRules = $this->passwordRuleService->loginRule([
                'PasswordLength' => 6, // Default length if not found in DB
            ]);

            $passwordLength = $passwordRules['PasswordLength'] ?? 6;
            $maxAttempts = $passwordRules['PasswordInvalid'] ?? 5;

            $rules = [
                'email'    => 'required|email',
                'password' => 'required|string|min:' . $passwordLength,
            ];

            $validation = validateRequest($request->all(), $rules);
            if ($validation->fails()) {
                $errors = $validation->errors();
                return apiResponse('Validation Failed', [], $errors->all(), 422);
            }

            $result = $this->authService->login($request->only(['email', 'password']), $maxAttempts);

            if ($result['status'] === 200) {
                $this->sendOtp($result['data']['user']);
            }
            
            return apiResponse(
                $result['message'],
                $result['data'] ?? [],
                $result['errors'] ?? [],
                $result['status']
            );
        } catch (\Exception $e) {
            \Log::error('Login Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return apiResponse('Internal Server Error', [], ['An unexpected error occurred.'], 500);
        }
    }

    private function sendOtp($user) {
        $otpCode = rand(1000, 9999);
        $mail = [
            'to' => $user->email,
            'content' => 'LoginOTP',
            'new' => [$user->fullname, $otpCode]
        ];
        $this->sendMailService->sendMailContent($mail);
        $this->invRepo->updateOtpById($user['investor_id'], $otpCode);
    }

    public function otpLogin(Request $request) {
        $rules = [
            'otp'   => ['required', 'array', 'size:4'],
            'otp.*' => ['required', 'digits:1']            
        ];

        $validation = validateRequest($request->all(), $rules);
        if ($validation->fails()) {
            $errors = $validation->errors();
            return apiResponse('Validation Failed', [], $errors->all(), 422);
        }

        $investor = Auth::user();
        if (!$investor || empty($investor->investor_id)) {
            return apiResponse('Unauthorized', [], ['Unauthorized. Invalid token.'], 401);
        }

        $digits = array_map(static function ($d) {
            $s = trim((string)$d);
            return (strlen($s) === 1 && ctype_digit($s)) ? $s : '';
        }, $request->input('otp', []));

        $otp = implode('', $digits);
        if (strlen($otp) !== 4) {
            return apiResponse('Invalid format', [], ['Invalid OTP format. Expected 4 digits.'], 422);
        }

        $result = $this->authService->verifyLoginOtp((string)$investor->investor_id, $otp);

        return apiResponse(
            $result['message'],
            $result['data'] ?? [],
            $result['errors'] ?? [],
            $result['status']
        );
    }
}