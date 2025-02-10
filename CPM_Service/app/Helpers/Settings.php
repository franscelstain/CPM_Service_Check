<?php

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