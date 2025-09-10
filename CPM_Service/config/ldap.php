<?php

// bool helper (tanpa arrow function)
$bool = static function ($v, $default = false) {
    if ($v === null) return $default;
    $s = strtolower(trim((string) $v));
    return in_array($s, ['1', 'true', 'on', 'yes'], true);
};

// Ambil host tunggal atau multi-host (dipisah koma di LDAP_HOSTS)
$hostsEnv = env('LDAP_HOSTS', env('LDAP_HOST', 'localhost'));
$parts = explode(',', (string) $hostsEnv);
$trimmed = array_map(function ($h) {
    return trim((string) $h);
}, $parts);
$hosts = array_values(array_filter($trimmed, function ($h) {
    return $h !== '';
}));

return [
    'default' => env('LDAP_CONNECTION', 'default'),

    'connections' => [
        'default' => [
            'hosts'            => $hosts ?: ['localhost'],
            'port'             => (int) env('LDAP_PORT', 389),
            'base_dn'          => env('LDAP_BASE_DN', null),
            'username'         => env('LDAP_USERNAME', null), // optional service account
            'password'         => env('LDAP_PASSWORD', null),
            'timeout'          => (int) env('LDAP_TIMEOUT', 5),
            'use_ssl'          => $bool(env('LDAP_SSL'), false),
            'use_tls'          => $bool(env('LDAP_TLS'), false),
            'version'          => 3,
            'follow_referrals' => false,
            'options'          => [
                // Tambah opsi LDAP_* jika perlu, contoh:
                // LDAP_OPT_PROTOCOL_VERSION => 3,
                // LDAP_OPT_REFERRALS        => 0,
            ],
        ],
    ],

    // Extra keys untuk helper build identity (tidak mengganggu LdapRecord)
    'login' => $bool(env('LDAP_LOGIN'), false),
    'is_upn_login' => $bool(env('LDAP_IS_UPN_LOGIN'), true),
    'domain'       => env('LDAP_DOMAIN', null),
    'user_dn_attr' => env('LDAP_USER_DN_ATTR', 'uid'),
];
