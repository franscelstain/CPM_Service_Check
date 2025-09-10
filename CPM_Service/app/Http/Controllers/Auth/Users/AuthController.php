<?php

namespace App\Http\Controllers\Auth\Users;

use App\Http\Controllers\Controller;
use App\Services\Handlers\Auth\PasswordRuleService;
use App\Services\Handlers\Auth\Users\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller 
{
    protected $authService;
    protected $passwordRuleService;

    public function __construct(AuthService $authService, PasswordRuleService $passwordRuleService) {
        $this->authService = $authService;
        $this->passwordRuleService = $passwordRuleService;
    }
    
    public function login(Request $request)
    {
        try {
            if (!config('ldap.login')) {
                $passwordRules = $this->passwordRuleService->loginRule([
                    'PasswordLength' => 6,
                ]);
                $passwordLength = $passwordRules['PasswordLength'] ?? 6;
                $maxAttempts = $passwordRules['PasswordInvalid'] ?? 5;                

                $rules = [
                    'email' => 'required|string|email',
                    'password' => 'required|string|min:' . $passwordLength,
                ];
            } else {
                $rules = [
                    'username' => 'required|string',
                    'password' => 'required|string',
                ];
            }

            $validation = validateRequest($request->all(), $rules);
            if ($validation->fails()) {
                $errors = $validation->errors();
                return apiResponse('Validation Failed', [], $errors->all(), 422);
            }

            if (config('ldap.login')) {
                $result = $this->authService->ldapLogin($request->only(['username', 'password']));
            } else {
                $result = $this->authService->loginUser($request->only(['email', 'password']), $maxAttempts);
            }

            return apiResponse(
                $result['message'],
                $result['data'] ?? [],
                $result['errors'] ?? [],
                $result['status']
            );
        } catch (\Exception $e) {
            // Handle exceptions and return an appropriate response
            Log::error('Login Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return apiResponse('Login Error', [], [$e->getMessage()], 500);
        }
    }
}