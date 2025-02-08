<?php

namespace App\Models\Administrative\Notify;
use Illuminate\Database\Eloquent\Model;

class SalesSetup extends Model
{

    protected $table = 'm_notification_sales';
    //Primary key
    public $primaryKey = 'id';
    //Timestamp
    public $timestamp = true;
    protected $fillable = [
        'title', 'body', 'reminder', 'count_reminder', 'category','redirect',
        ];
    // const CREATED_AT = 'created_at';

    public static function rules($id = null)
    {
        return [
            'title'   => 'required',
            'body'     => 'required',
            'reminder'     => 'required',
            'count_reminder'     => 'required',
            'category'     => 'required',
            'redirect'     => 'required'
        ];
    }
}
