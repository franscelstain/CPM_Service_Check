<?php

namespace App\Services\Handlers\Auth;

use App\Repositories\Config\GeneralConfigRepository;
use Illuminate\Support\Facades\Cache;

class PasswordRuleService
{
    protected $configRepository;

    public function __construct(GeneralConfigRepository $configRepository) {
        $this->configRepository = $configRepository;
    }

    public function loginRule(array $defaults = []): array
    {
        $cacheKey = 'passwd_login_config';
        $ttl = 60 * 60; // 1 jam

        // Coba ambil dari cache
        $configs = Cache::get($cacheKey);

        // Kalau belum ada di cache
        if (empty($configs)) {
            $configs = $this->configRepository->passwordRule();

            // Simpan ke cache hanya kalau hasilnya tidak kosong
            if (!empty($configs)) {
                Cache::put($cacheKey, $configs, $ttl);
            }
        }

        // Gabungkan dengan default
        return array_merge($defaults, $configs);
    }
}