<?php

namespace App\Http\Controllers;

date_default_timezone_set('Asia/Jakarta');

use App\Mail\Email;
use App\Traits\DBQuery;
use App\Traits\RestApi;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Auth;
use DB;
use Mail;
use Schema;
use Log;

class AppController extends Controller
{
    use DBQuery, RestApi;
    
    protected function app_catch($e, $res=true)
    {
        Log::error('Error terjadi: ' . $e->getMessage(), [
            'exception' => $e,
            'stack_trace' => $e->getTraceAsString(),
        ]);
        return $res ? $this->app_response('Response Failed', [], ['error_code' => 500, 'error_msg' => [$e->getMessage()]]) : $e->getMessage();
    }

    public function app_date($date='', $fmt='Y-m-d')
    {
        if ($fmt == 'dotnet')
        {
            if (!empty($date))
            {
                preg_match('/([\d]{9})/', $date, $dt);
                $date = date('Y-m-d', $dt[0]);
            }
            return $date;
        }
        else
        {
            return !empty($date) ? date($fmt, strtotime($date)) : date($fmt);
        }
    }

    protected function app_partials($success, $fail, $details)
    {
        $data   = ['success' => $success, 'fail' => $fail, 'details' => $details];
        $msg    = $success > 0 || $fail > 0 ? $success == 0 ? 'All data failed' : 'Success partial' : 'No data is saved ';
        return $this->app_response($msg, $data);
    }
    
    protected function app_response($msg, $data = [], $errors = [], $customStatusCode = null)
    {
        // Tentukan apakah response sukses berdasarkan ada tidaknya error
        $success = empty($errors) || (isset($errors['error_code']) && $errors['error_code'] === 200);

        // Jika tidak sukses, kosongkan data dan masukkan error
        $data = $success ? $data : [];

        // Buat struktur response
        $response = ['success' => $success, 'message' => $msg, 'data' => $data];

        // Tambahkan error jika ada dan response tidak sukses
        if (!$success) {
            $response['errors'] = $errors;
        }

        // Tentukan status code: gunakan custom status code jika ada, atau default berdasarkan sukses/gagal
        $statusCode = $customStatusCode ?? (!$success ? (isset($errors['error_code']) ? $errors['error_code'] : 500) : 200);

        // Kembalikan response JSON dengan status code yang sesuai
        return response()->json($response, $statusCode);
    }

    public function app_sendmail($mail)
    {
        try
        {
            $model      = new \App\Models\Administrative\Email\EmailContent;
            $fn_mail    = !empty($mail['content_id']) ? 'email_content_id' : 'email_content_name';
            $key_mail   = !empty($mail['content_id']) ? $mail['content_id'] : $mail['content'];
            $data       = $model::join('c_email_layouts as b', 'c_email_contents.layout_id', '=', 'b.layout_id')->where([['c_email_contents.is_active', 'Yes'], ['b.is_active', 'Yes'], [$fn_mail, $key_mail]])->first();
            if (!empty($data->email_content_id))
            {
                $send = (object) array('from' => env('MAIL_FROM'), 'subject' => $data->email_subject, 'data' => $data, 'mail' => $mail);
                if (isset($mail['attach']) && file_exists($mail['attach']))
                {
                    $send->attach = $mail['attach'];
                }
                return Mail::to($mail['to'])->send(new Email($send));
                //return (new Email($send))->render();                
            }
        }
        catch (\Exception $e)
        {
            return $this->app_catch($e);
        }
    }

    protected function app_validate($request, $rules = [], $partials = false)
    {
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails())
        {
            $errors     = $validator->errors();
            $response   = ['error_code' => 422, 'error_msg' => $errors->all()];
            return $partials ? (object) $response : $this->app_response('Validation Failed', [], $response)->send();
        }
    }

    public function responseJson($msg, $res)
    {
        $success    = true;
        $code       = 200;
        if (isset($res->errors) && !empty($res->errors))
        {
            $success = false;
            $errors  = $res->errors;
            if (isset($errors['error_code']))
                $code = $errors['error_code'];
            unset($res->errors);
        }

        $response['success']    = $success;
        $response['message']    = $msg;
        $response['data']       = !empty($res) ? $res : [];

        if (isset($errors))
            $response['errors'] = $errors;

        return response()->json($response, $code);
    }
    
    protected function auth_user()
    {
        return Auth::id() ? Auth::user() : Auth::guard('admin')->user();
    }

    /**
     * @param $instance
     * @param $arr
     * @return void
     */
    protected function serviceBroker($instance,$arr) {
        
        $connection = new AMQPStreamConnection(env('RABBITMQ_HOST'), env('RABBITMQ_PORT'), env('RABBITMQ_USER'), env('RABBITMQ_PWD'));
        $channel    = $connection->channel();
        $channel->queue_declare($instance, false, false, false, false);
        
        $msg        = new AMQPMessage(json_encode($arr));
        $channel->basic_publish($msg, '',$instance);

        $channel->close();
        $connection->close();
    }

    /**
     * Validasi request dan kembalikan pesan error jika ada.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  array  $rules
     * @return array|null
     */
    public function validateRequest($request, $rules = [], $messages = [], $customStatusCode = 422)
    {
        // Jalankan validasi dengan pesan kustom
        $validator = Validator::make($request->all(), $rules, $messages);

        // Jika validasi gagal, kembalikan error
        if ($validator->fails()) {
            $errorMessages = $validator->errors()->all();
            // Kembalikan array dengan pesan error
            return [
                'error_code' => $customStatusCode,
                'error_msg' => $errorMessages,
            ];
        }
        
        // Jika validasi berhasil, kembalikan null
        return null;
    }
}