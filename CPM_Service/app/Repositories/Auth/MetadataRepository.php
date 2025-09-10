<?php

namespace App\Repositories\Auth;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MetadataRepository
{
    public function getHostById($hostId)
    {
        return DB::table('c_api as ca')
            ->where('ca.api_id', $hostId)
            ->where('ca.is_active', 'Yes')
            ->first();
    }

    public function getServiceByName($serviceName)
    {
        return DB::table('c_api_services as cas')
            ->where('service_name', $serviceName)
            ->where('is_active', 'Yes')
            ->first();
    }

    public function getParamByServiceId($serviceId)
    {
        return DB::table('c_api_services_param as casp')
            ->where('service_id', $serviceId)
            ->where('is_active', 'Yes')
            ->orderBy('sequence_to')
            ->get();
    }

    public function updateHostToken($hostId, $token)
    {
        return DB::table('c_api')
            ->where('api_id', $hostId)
            ->update(['token' => $token, 'updated_at' => Carbon::now()]);
    }
}