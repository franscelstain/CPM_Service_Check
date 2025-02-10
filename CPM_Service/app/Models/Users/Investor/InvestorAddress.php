<?php

namespace App\Models\Users\Investor;

use Illuminate\Database\Eloquent\Model;

class InvestorAddress extends Model
{
       protected $table        = 'u_investors_addresses';
       protected $primaryKey   = 'investor_address_id';
       protected $fillable     = [
              'address',
              'address_type',
              'province_id',
              'city_id',
              'subdistrict_id',
              'postal_code',
       ];
}
