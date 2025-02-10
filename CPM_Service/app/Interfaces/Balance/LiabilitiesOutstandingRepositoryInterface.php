<?php

namespace App\Interfaces\Balance;
use Illuminate\Http\Request;

interface LiabilitiesOutstandingRepositoryInterface
{
    public function getIntegration();
    public function getLiabilities(Request $request, $id);
}