<?php

namespace App\Http\Controllers\Administrative\Config;

use App\Http\Controllers\AppController;
use App\Models\Administrative\Config\Logo;
use Illuminate\Http\Request;

class LogoController extends AppController
{
    public $table = 'Administrative\Config\Logo';
    
    public function index()
    {
       return $this->db_result();
    }
    
    public function logo_active()
    {
        try 
        {
            $logo = Logo::where('is_active', 'Yes')->first();
            return $this->app_response('Logo Active', [
                'logo_color'        => !empty($logo->logo_color) ? $logo->logo_color : '',
                'logo_color_white'  => !empty($logo->logo_color_white) ? $logo->logo_color_white : '',
                'logo_white'        => !empty($logo->logo_white) ? $logo->logo_white : '',
                'logo_only'         => !empty($logo->logo_only) ? $logo->logo_only : '',
                'favicon'           => !empty($logo->favicon) ? $logo->favicon : ''
            ]);
        } 
        catch (\Exception $e) 
        {
            return $this->app_catch($e);
        }
    }

    public function logo_type($type)
    {
        try 
        {
            
            $logo = Logo::where('is_active', 'Yes')->first();
            return $this->app_response('Logo', $logo->$type ?? '');
        } 
        catch (\Exception $e) 
        {
            return $this->app_catch($e);
        }
    }

    public function save(Request $request)
    {
        $data = Logo::where('is_active', 'Yes')->first();
        if (!empty($data->logo_id))
        {
            $logo = ['favicon', 'logo_color', 'logo_color_white', 'logo_only', 'logo_white'];
            foreach ($logo as $lg)
            {
                if (!$request->hasFile($lg))
                {
                    $request->request->add([$lg => $data->$lg]);
                }
            }
        }
        return $this->db_save($request, null, ['path' => 'logo/img']);
    }
}