<?php

namespace App\Traits;

use Illuminate\Support\Facades\Validator;
use Auth;

trait Responses
{
    protected function authUser()
    {
        return Auth::id() ? Auth::user() : Auth::guard('admin')->user();
    }

    public function jsonOnly($res)
    {
        return response()->json($res);
    }

    public function responseJson($msg, $res)
    {
        $success    = true;
        $code       = 200;
        if (isset($res->errors) && !empty($res->errors))
        {
            $success = false;
            $errors  = $res->errors;
            $code    = isset($errors['error_code']) ? $errors['error_code'] : 500;
            unset($res->errors);
        }

        $response['success']    = $success;
        $response['message']    = $msg;
        $response['data']       = !empty($res) ? $res : [];

        if (isset($errors))
            $response['errors'] = $errors;

        return response()->json($response, $code);
    }

    public function validation($data, $rules, $msg = [])
    {
        $validator = Validator::make($data, $rules, $msg);
        if ($validator->fails())
        {
            $errors = $validator->errors();
            return (object) ['errors' => ['error_code' => 422, 'error_msg' => $errors->all()]];
        }
    }
}
