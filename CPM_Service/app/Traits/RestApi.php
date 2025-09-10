<?php

namespace App\Traits;

use App\Models\Administrative\Api\Host;
use App\Models\Administrative\Api\Service;
use App\Models\Administrative\Api\ServiceIndex;
use App\Models\Administrative\Api\ServiceParam;
use Illuminate\Support\Collection;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Client;

trait RestApi 
{
    protected function __api($api = [])
    {
        try 
        {
            $code   = 404;
            $result = 'Not Found';
            if (!empty($api['slug']))
            {
                $param = [];
                switch ($api['authorization'])
                {
                    case 'auth'     :
                        if (isset($api['login']))
                            $param['auth'] = $api['auth']; 
                        break;
                    case 'bearer'   : 
                        if (!isset($api['login']))
                            $param['headers'] = ['Authorization' => 'bearer '. $api['token'], 'Accept' => 'application/' . $api['content_type']]; 
                        break;
                }
                
                if (!empty($api['data']))
                    $param['body'] = json_encode($api['data']);
                
                if (!empty($api['json']))
                    $param['json'] = $api['json'];
                
                $client = new Client();
                $url    = !empty($api['path']) ? $api['slug'] . $api['path'] . $api['uri'] : $api['slug'] . $api['uri'];
                // $app    = !empty($api['method']) && strtolower($api['method']) == 'post' ? $client->post($url, $param) : $client->get($url, $param);

                //ini ditambahkan karena ada prosess put
                if(!empty($api['method']) && (strtolower($api['method']) == 'post'))
                {
                   $app    = $client->post($url, $param);
                }elseif(!empty($api['method']) && (strtolower($api['method']) == 'put'))
                {
                   $app    = $client->put($url, $param);
                }else
                {
                   $app    = $client->get($url, $param);
                }
                $result = json_decode($app->getBody());
                $result = is_array($result) ? json_decode(json_encode($result)) : $result;
                $code   = $app->getStatusCode();
            }
        }
        catch (ClientException $e)
        {
            $code   = $e->getResponse()->getStatusCode();
            $result = json_decode($e->getResponse()->getBody());
        }
        
        return isset($result->code) ? $result : (object) array_merge(['code' => $code], (array) $result);
    }
    
    public function api_ws($arr=[], $lvl=0, $srv=[])
    {
        try
        {
            if ($lvl == 0)
            {                
                $srv    = Service::select('*', 'c_api_services.service_method as method', 'c_api_services.service_path as path')
                        ->join('c_api as b', 'c_api_services.api_id', '=', 'b.api_id')
                        ->where([['service_name', $arr['sn']], ['c_api_services.is_active', 'Yes'], ['b.is_active', 'Yes']])->first();
                
                if (!empty($arr['path']))
                {
                    $srv                = (object) $srv->attributesToArray();
                    $srv->path          = $srv->path . $arr['path'];
                    $srv->service_path  = $srv->service_path . $arr['path'];
                }
            }
            
            $data       = ['data' => [], 'uri' => ''];
            $ws         = $err = [];
            $success    = false;
            $auth       = $this->api_ws_auth($srv, $lvl);
            if ($auth->success)
            {
                if (!empty($auth->token))
                {
                    if ($lvl == 0)
                        $srv->token = $auth->token;
                    else
                        $srv['token'] = $auth->token;
                }
                
                if ($lvl == 0)
                {
                    if (!empty($arr['key']))
                    {
                        for ($i = 0; $i < count($arr['key']); $i++)
                        {
                            $key        = $i > 0 ? $i : '';
                            $key_val    = $arr['key'][$i] == 'token' ? $auth->token : $arr['key'][$i];
                            $srv->path  = str_replace('{key'. $key .'}', $key_val, $srv->path);
                        }
                    }

                    $n      = 0;
                    $dKey   = $srv->data_key;
                    $param  = ServiceParam::where([['service_id', $srv->service_id], ['is_active', 'Yes']])->orderBy('sequence_to');
                    if ($param->count() > 0)
                    {
                        $x      = 1;
                        $val    = !empty($arr['val']) ? $arr['val'] : [];
                        foreach ($param->get() as $prm)
                        {
                            if ($prm->param_key == 'token' || !empty($prm->param_value) || is_numeric($prm->param_value) || !empty($val[$n]) || is_numeric($val[$n]) || is_null($val[$n]) || is_bool($val[$n]) )
                            {
                                $value = $prm->param_key != 'token' ? !empty($prm->param_value) || is_numeric($prm->param_value) ? $prm->param_value : $val[$n++] : $auth->token;
                                if ($srv->service_method == 'GET')
                                {
                                    if ($x == 1)
                                        $data['uri'] = '?';
                                    else
                                        $data['uri'] .= '&';
                                    $data['uri'] .= $prm->param_key .'='. $value;
                                }
                                else
                                {
                                    if(!empty($prm->param_type))
                                    {
                                        $value = $prm->param_value == 'number' ?  floatval($value) : $value;
                                    }
                                    else
                                    {
                                        $value = is_numeric($value) ? floatval($value) : $value ;                                        
                                    }
                                    $data['json'][$prm->param_key] = $value; 
                                }
                                $x++;
                            }
                        }
                    }

                    $merge      = new Collection($srv);
                    $collect    = $merge->merge($data);
                }
                else
                {
                    $dKey       = $srv['data_key'];
                    $collect    = $srv;
                }
                $curl = $this->__api($collect);
                $code = $curl->code;
                
                if (isset($curl->success) && $curl->success)
                {
                    $success    = true;
                    $msg        = 'Get WS';
                    $ws         = isset($curl->$dKey) ? $curl->$dKey : $curl;
                }
                else
                {
                    if (in_array($code, [401, 403]) && $lvl == 0)
                    {
                        return $this->api_ws($arr, 1, $collect);
                    }
                    $msg = isset($curl->message) ? 'API - ' . $curl->message : $curl;
                }                
                
                /*if (isset($arr['save']) && $arr['save'] && isset($curl->$dKey))
                {
                    $this->api_ws_save($ws, $srv);
                }*/
            }
            else
            {
                $msg    = $auth->message;
                $code   = $auth->code;
            }
            
            return response()->json(['success' => $success, 'code' => $code, 'message' => $msg, 'data' => $ws]);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
    
    private function api_ws_auth($srv, $lvl=0)
    {
        $token  = '';
        $srv    = $lvl == 1 ? json_decode(json_encode($srv)) : $srv;
        if (!empty($srv->service_id) && $srv->get_token == 'Yes')
        {
            if (empty($srv->token) || $lvl == 1)
            {
                $dt_auth = [
                    'authorization' => $srv->authorization,
                    'login'         => true,
                    'method'        => $srv->auth_method,
                    'slug'          => $srv->slug . $srv->auth_link,
                    'uri'           => ''
                ];
                
                switch ($srv->authorization)
                {
                    case 'auth'     : $dt_auth = array_merge($dt_auth, ['auth' => [$srv->username, $srv->password]]); break;
                    case 'bearer'   : $dt_auth = array_merge($dt_auth, ['json' => [$srv->user_label => $srv->username, $srv->pass_label => $srv->password]]); break;
                }
                
                $api = $this->__api($dt_auth);
                if (!empty($api))
                {
                    $token = empty($api->data) ? !empty($api->token) ? $api->token : '' : $api->data;
                    Host::where('api_id', $srv->api_id)->update(['token' => $token]);
                }

                if (empty($token))
                {
                    if ($lvl > 0)
                        return (object) ['success' => false, 'code' => 401, 'message' => 'unauthorized'];
                    else
                        return $this->api_ws_auth($srv, 1);
                }
            }
            else
            {
                $token = $srv->token;
            }
        }
        return (object) ['success' => true, 'token' => $token];
    }
    
    private function api_ws_save($ws, $srv, $ip='')
    {
        try
        {
            $skey       = $srv->service_key;
            $model      = $this->qry_table();
            $col        = $model->getFillable();
            $pKey       = $model->getKeyName();
            $save_by    = !empty($this->user_auth()) ? $this->user_auth()->usercategory_name .':'. $this->user_auth()->id .':'. $this->user_auth()->fullname : 'System';
            $host       = !empty($ip) ? $ip : '::1';
            $index      = ServiceIndex::where([['service_id', $srv->service_id], ['is_active', 'Yes']])->get();
            if ($index->count() > 0)
            {
                foreach ($ws as $w)
                {
                    $arr    = [];
                    $n      = 0;
                    foreach ($index as $idx)
                    {
                        $fn             = $idx->index_name;
                        $val            = is_array($w) ? $w[$fn] : $w->$fn;
                        $arr[$col[$n]]  = !empty($val) ? $val : '-';
                        $n++;
                    }
                    $wcode  = is_array($w) ? $w[$skey] : $w->$skey;
                    $row    = $model::where('ext_code', $wcode)->first();
                    $save   = empty($row->$pKey) ? 'created' : 'updated';
                    $data   = array_merge($arr, ['is_data' => 'WS', 'is_active' => 'Yes', $save.'_by' => $save_by, $save.'_host' => $host]);
                    $qry    = $save == 'created' ? $model::create($data) : $model::where($pKey, $row->$pKey)->update($data);
                }
            }
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
}