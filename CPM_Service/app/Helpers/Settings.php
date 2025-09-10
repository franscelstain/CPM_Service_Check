<?php

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

if (! function_exists('__api'))
{
    function __api($api)
    {
        $_api   = !empty($api['api']) ? $api['api'] : 'PYTHON';
        $ct     = !empty($api['ct']) ? $api['ct'] : 'json';
        $ch     = !empty($api['ch']) ? curl_init($api['ch'].$api['url']) : curl_init(env('API_'.$_api) . $api['url']);
        $method = !empty($api['method']) ? $api['method'] : 'GET';
        $header = [];

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);

        if(!empty($api['header']) && is_array($api['header']))
        {
            foreach($api['header'] as $value)
            {
                $header[] = $value;
            }
        }

        switch ($ct)
        {
            case 'form' :
                $header[] = 'Content-Type: application/x-www-form-urlencoded';
                break;
            default :
                $header[] = 'Content-Type: application/json';
        }

        if (!empty($api['data']))
        {
            $method    = !empty($api['req']) ? $api['req'] : 'POST';
            switch ($ct)
            {
                case 'form' :
                    $data = http_build_query($api['data']);
                    break;
                default :
                    $data = json_encode($api['data']);
            }
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }


        // Set HTTP Header for POST request
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

        if (curl_errno($ch))
        {
           return curl_error($ch);
        }

        $result     = json_decode(curl_exec($ch), true);
        //$result = curl_exec($ch);
        $httpcode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // Close cURL session handle
        curl_close($ch);

        return $result;
    }
}

if (! function_exists('__router'))
{
    function __router($router, $ctrl, $prefix='')
    {
        $router->group(['prefix' => $prefix], function() use ($router, $ctrl) {
            $router->get('/detail/{id}', $ctrl . 'Controller@detail');
            $router->delete('/save/{id}', $ctrl . 'Controller@save');
            $router->put('/save/{id}', $ctrl . 'Controller@save');
            $router->post('/save', $ctrl . 'Controller@save');
            $router->get('/', $ctrl . 'Controller@index');
        });
    }
}

if (! function_exists('cpm_img'))
{
    function cpm_img($img='')
    {
        return env('API_ASSETS') . 'img/' . $img;
    }
}

if (! function_exists('email_template'))
{
    function email_template()
    {
        $email = new App\Http\Controllers\Administrative\Config\Email\LayoutEmailController;
        return (object) $email->index()->original['data'];
    }
}

if (! function_exists('logo_active'))
{
    function logo_active()
    {
        $logo = new App\Http\Controllers\Administrative\Config\LogoController;
        return (object) $logo->logo_active()->original['data'];
    }
}

if (! function_exists('socmed_email'))
{
    function socmed_email()
    {
        $socmed = new App\Http\Controllers\SA\UI\SocialMediaController;
        return (object) $socmed->index()->original['data']['list'];
    }
}

if (!function_exists('apiResponse')) {
    /**
     * Mengembalikan respon JSON standar.
     *
     * @param bool   $success  True jika operasi sukses, false jika ada error
     * @param string $message  Pesan yang ingin ditampilkan
     * @param mixed  $data     Data yang dikembalikan (opsional)
     * @param mixed  $errors   Error yang dikembalikan (opsional)
     * @param int|null $status Kode status HTTP (jika null, akan diset default: 200 untuk sukses, 400 untuk error)
     * @return JsonResponse
     */
    function apiResponse(
        string $message,
        $data = [],
        $errors = [],
        int $status = null
    ): JsonResponse {
        $success = empty($errors) ? true : false;
        if ($status === null) {
            $status = $success ? 200 : 400;
        }

        return response()->json([
            'success' => $success,
            'message' => $message,
            'data'    => $data,
            'errors'  => !$success ? (array) $errors : [],
        ], $status);
    }
}

if (! function_exists('validateRequest'))
{
    /**
     * Membuat instance validator dari data dan aturan yang diberikan.
     *
     * @param array $data            Data yang akan divalidasi (mis. $request->all())
     * @param array $rules           Aturan validasi (mis. ['email' => 'required|email', 'password' => 'required|min:6'])
     * @param array $messages        Pesan kustom (opsional)
     * @param array $customAttributes Atribut kustom (opsional)
     * @return \Illuminate\Contracts\Validation\Validator
     */
    function validateRequest(
        array $data,
        array $rules,
        array $messages = [],
        array $customAttributes = []
    ) {
        return Validator::make($data, $rules, $messages, $customAttributes);
    }
}