<?php

namespace App\Http\Controllers\Administrative\Config;

use App\Http\Controllers\AppController;
use App\Models\Administrative\Config\Config;
use Illuminate\Http\Request;
use App\Models\Users\User; 

class ConfigController extends AppController
{
    public $table = 'Administrative\Config\Config';
    
    public function index()
    {
        return $this->db_result();
    }
    
    public function detail($cfg)
    {
        try
        {
            return $this->app_response('Config Detail', Config::where([['is_active', 'Yes'], ['config_name', $cfg]])->first());
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
    
    public function speed_marquee()
    {
        try
        {
            $data   = Config::select('config_value as speed')->where([['is_active', 'Yes'], ['config_name', 'SpeedMarquee']])->first();
            $speed  = !empty($data->speed) ? $data->speed : 1;
            return $this->app_response('Speed Marquee', $speed);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }    

    public function is_email_exist($id, $email)  
    {
        $dt = User::where('email', $email)
                ->where('is_active', 'Yes')
                ->where('user_id', '<>', $id)
                ->first();
        $exist = (is_null($dt)) ? false: true;
        return $this->app_response('email', $exist);
    }
}