<?php

namespace App\Models\Investor;

use Illuminate\Database\Eloquent\Model;

class EmailBlast extends Model
{
    protected $table 	= 'u_investors_email_blast';
    protected $guarded 	= ['id', 'created_at', 'updated_at'];
}
