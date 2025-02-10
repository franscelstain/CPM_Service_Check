<?php

namespace App\Repositories\Auth;

use App\Http\Controllers\Administrative\Config\GeneralController;
use App\Interfaces\Auth\UserAuthRepositoryInterface;
use App\Models\Users\User;
use App\Models\Users\UserPasswordAttemp;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserAuthRepository implements UserAuthRepositoryInterface
{    
    /**
     * Cek apakah password lama cocok dengan yang ada di database.
     *
     * @param int $userId
     * @param string $oldPassword
     * @return bool
     */
    public function checkOldPassword($userId, $oldPassword)
    {
        $user = User::find($userId);
        
        if ($user && Hash::check($oldPassword, $user->password)) {
            return true;
        }

        return false;
    }
    
    /**
     * Update token pengguna menjadi null (digunakan saat logout)
     */
    public function clearUserToken($userId)
    {
        return User::where('user_id', $userId)->update(['token' => null]);
    }

    public function login(array $credentials)
    {
        // Sanitasi input email untuk mencegah XSS
        $email = htmlspecialchars($credentials['email'], ENT_QUOTES, 'UTF-8');

        // Cek apakah email valid (optional: pengecekan DNS)
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['error_code' => 422, 'error_msg' => ['Invalid email address']];
        }

        // Mencoba autentikasi dengan JWT
        if (!$token = Auth::guard('admin')->attempt([
            'email' => $email,  // Pastikan menggunakan email yang sudah disanitasi
            'password' => $credentials['password']
        ])) {
            return $this->handleFailedLogin($email);
        }

        // Jika berhasil login, ambil user yang terautentikasi
        $user = Auth::guard('admin')->user();
        
        // Jika akun sudah tidak aktif
        if ($user->is_active === 'No') {
            return ['error_code' => 422, 'error_msg' => ['Sorry, your account has been deleted']];
        }
        
        // Jika akun diblokir
        if ($user->is_enable === 'No') {
            return ['error_code' => 422, 'error_msg' => ['Sorry, your account has been disabled']];
        }

        // Reset jumlah percobaan login yang gagal setelah login berhasil
        UserPasswordAttemp::where('user_id', $user->user_id)->update([
            'is_active' => 'No', 
            'attempt_count' => 0
        ]);

        // Kembalikan data sukses login
        return [
            'token' => $token,
            'token_type' => 'Bearer', 
            'expires_in' => auth('admin')->factory()->getTTL() * 60, 
            'user' => $user,
        ];
    }

    /**
     * Menangani percobaan login yang gagal
     * Menghitung jumlah percobaan dan memblokir akun jika percobaan sudah terlalu banyak
     */
    private function handleFailedLogin($email)
    {
        // Cari user berdasarkan email dan status aktif
        $user = User::where([['email', $email], ['is_active', 'Yes']])->first();

        // Jika user ditemukan, periksa jumlah percobaan login yang gagal
        if ($user) {
            $attempt = UserPasswordAttemp::where('user_id', $user->user_id)->orderBy('created_at', 'desc')->first();
            $attemptCount = $attempt ? $attempt->attempt_count + 1 : 1;

            // Perbarui atau buat entri percobaan login
            UserPasswordAttemp::updateOrCreate(
                ['user_id' => $user->user_id],
                ['attempt_count' => $attemptCount, 'created_host' => $_SERVER['SERVER_ADDR']]
            );

            // Ambil konfigurasi dari GeneralController (misal: batas jumlah percobaan salah)
            $generalCtrl = new GeneralController();
            $configPassword = $generalCtrl->password()->original['data'];

            // Jika jumlah percobaan salah melebihi batas, blokir akun
            if ($attemptCount >= $configPassword['PasswordInvalid']) {
                User::where('email', $email)->update(['is_enable' => 'No']);
                return [
                    'error_code' => 422,
                    'error_msg' => ['Sorry, your account password has been blocked due to multiple wrong attempts.']
                ];
            } else {
                // Jika percobaan belum melebihi batas, beri tahu berapa percobaan yang tersisa
                return [
                    'error_code' => 422,
                    'error_msg' => ['Password incorrect. Attempt ' . $attemptCount . ' of ' . $configPassword['PasswordInvalid']]
                ];
            }
        }

        // Jika user tidak ditemukan, kembalikan pesan error standar
        return ['error_code' => 422, 'error_msg' => ['Email or Password is wrong']];
    }

    /**
     * Update password user di database.
     *
     * @param int $userId
     * @param string $newPassword
     * @return bool
     */
    public function updatePassword($userId, $newPassword)
    {
        return User::where('user_id', $userId)->update([
            'password' => app('hash')->make($newPassword)
        ]);
    }
}
