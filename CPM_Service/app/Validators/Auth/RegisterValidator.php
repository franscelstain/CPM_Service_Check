<?php

namespace App\Validators\Auth;

use Illuminate\Support\Facades\Validator;

class RegisterValidator
{
    public static function validate($controller, $request, $passwordRules)
    {
        $rules = [
            'identity_no' => 'required',
            'email' => [
                'required',
                'email',
                \Illuminate\Validation\Rule::unique('u_investors')->ignore(null, 'investor_id')->where('is_active', 'Yes'),
            ],
            'password' => [
                'required',
                'confirmed',
                function ($attribute, $value, $fail) use ($passwordRules) {
                    if (isset($passwordRules['min_length']) && strlen($value) < $passwordRules['min_length']) {
                        $fail('The password must be at least ' . $passwordRules['min_length'] . ' characters long.');
                    }
                },
                function ($attribute, $value, $fail) use ($passwordRules) {
                    if (isset($passwordRules['uppercase']) && !preg_match('/[A-Z]/', $value)) {
                        $fail('The password must contain at least one uppercase letter.');
                    }
                },
                function ($attribute, $value, $fail) use ($passwordRules) {
                    if (isset($passwordRules['lowercase']) && !preg_match('/[a-z]/', $value)) {
                        $fail('The password must contain at least one lowercase letter.');
                    }
                },
                function ($attribute, $value, $fail) use ($passwordRules) {
                    if (isset($passwordRules['number']) && !preg_match('/[0-9]/', $value)) {
                        $fail('The password must contain at least one number.');
                    }
                },
                function ($attribute, $value, $fail) use ($passwordRules) {
                    if (isset($passwordRules['special']) && !preg_match('/[@$!%*#?&]/', $value)) {
                        $fail('The password must contain at least one special character (@$!%*#?&).');
                    }
                }
            ],
            'password_confirmation' => 'required',
        ];
        
        // Non-bail: Jalankan semua aturan validasi meskipun ada error
        $validator = Validator::make($request->all(), $rules);

        // Jika validasi gagal, kumpulkan semua pesan error
        if ($validator->fails()) {
            return [
                'error_code' => 422,
                'error_msg' => $validator->errors()->all(), // Ambil semua error
            ];
        }
    }
}
