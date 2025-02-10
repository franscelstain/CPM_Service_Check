<?php

namespace App\Http\Controllers\Administrative\Api;

use App\Http\Controllers\CpmController;
use Illuminate\Http\Request;

class ExternalController extends CpmController
{
	public function getInvestor($idNo = '1600000000000001')
	{
        try
        {
            $client 	= [];
            $param		= ['value' => $idNo];
            $req 		= $this->api_external('API_250', 'Inquiry.svc/Client', $param, 'getCustomer')->original['data'];
            $reqAccount = $this->api_external('API_250', 'Inquiry.svc/ClientAccount', $param, 'getCustomer')->original['data'];

            if (!empty($req['Data']))
            {
                $client['Address']	= $req['Data']['ClientAddresses'];
                $client['Info']		= $req['Data']['ClientInfo'];
            }

            if (!empty($reqAccount['Data']))
            {
                $client['Account'] 	= $reqAccount['Data'];
            }
            return $client;
        }
        catch (\Exception $e)
        {
            return $this->api_catch($e);
        }
	}
}
