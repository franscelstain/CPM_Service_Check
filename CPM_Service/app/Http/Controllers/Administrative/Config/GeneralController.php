<?php

namespace App\Http\Controllers\Administrative\Config;

use App\Http\Controllers\AppController;
use App\Models\Administrative\Config\Config;
use Illuminate\Http\Request;

class GeneralController extends AppController
{
    public $table = 'Administrative\Config\Config';
    
    public function index()
    {
        try
        {
        	$general 	= []; 
        	$data 		= Config::where([['is_active', 'Yes'], ['config_type', 'General']])->get();
        	
        	foreach ($data as $dt ) 
        	{
        		$general[$dt->config_name] = $dt->config_value;  
        	}
        	return $this->app_response('Config General', $general);
        }
        catch (\Exception $e)
	    {
	    	return $this->app_catch($e);
	    }
    }

    public function password()
    {
        try
        {
            $general    = []; 
            $data       = Config::where([['is_active', 'Yes'], ['config_type', 'Password']])->whereIn('config_name', ['PasswordLength', 'PasswordCycles', 'PasswordInvalid', 'PasswordReminder', 'PasswordComplexityNumeric', 'PasswordComplexityAlphabet', 'PasswordComplexityUppercase', 'PasswordComplexitySymbol', 'PasswordExpiredDate', 'PasswordExpired', 'PasswordReminder'])->get();
            
            foreach ($data as $dt ) 
            {
                $general[$dt->config_name] = $dt->config_value;  
            }
            return $this->app_response('Config General', $general);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
    
    public function save(Request $request, $id=null)
    {
    	try
    	{
    		//$arr = ['SpeedMarquee', 'OTPMessage', 'Currency', 'RangePriceMin', 'RangePriceMax', 'TaxFee', 'ReminderRiskProfileExpired'];
		$arr = ['SpeedMarquee', 'OTPMessage', 'Currency', 'RangePriceMin', 'RangePriceMax', 'TaxFee', 'ReminderRiskProfileExpired', 'ReminderRiskProfileExpired', 'ValidAccountExpired'];
	    	foreach ($arr as $a) 
	    	{
	    		if (!empty($request->input($a)))
	    		{

		    		$data   = Config::where([['config_name', $a], ['is_active', 'Yes']])->first();                    
	    			$id 	= !empty($data->config_id) ? $data->config_id : null;
                    
		    		$request->request->add(['config_name' => $a, 'config_value' =>  str_replace('-', '',$request->input($a)), 'config_type' => 'General']);
	    			$this->db_save($request, $id);
	    		}
	    	}
	    	 return $this->app_response('Data successfully saved !', []);
	    }
	    catch (\Exception $e)
	    {
	    	return $this->app_catch($e);
	    }
	}

    public function save_password(Request $request, $id=null)
    {
        try
        {
            $arr    = ['PasswordLength', 'PasswordCycles', 'PasswordInvalid', 'PasswordComplexityNumeric', 'PasswordComplexityAlphabet', 'PasswordComplexityUppercase', 'PasswordComplexitySymbol', 'PasswordExpiredDate', 'PasswordExpired', 'PasswordReminder'];
            $pass   = ['PasswordLength', 'PasswordCycles', 'PasswordInvalid', 'PasswordReminder'];
            foreach ($arr as $a) 
            {
                if ((in_array($a, $pass) && !empty($request->input($a))) || !in_array($a, $pass))
                {                
                    $config_value = !empty($request->input($a)) ?  $request->input($a) : 'No';
                    $data         = Config::where([['config_name', $a], ['is_active', 'Yes']])->first();                    
                    $id           = !empty($data->config_id) ? $data->config_id : null;
                    
                    $request->request->add(['config_name' => $a, 'config_value' => str_replace('-', '',$config_value), 'config_type' => 'Password']);
                    $this->db_save($request, $id);
                }
            }
             return $this->app_response('Data successfully saved !', []);
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }
}