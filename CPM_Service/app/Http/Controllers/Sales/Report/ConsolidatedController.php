<?php

namespace App\Http\Controllers\Sales\Report;

use App\Http\Controllers\AppController;
use App\Models\Investor\EmailBlast;
use Illuminate\Http\Request;
use Storage;

class ConsolidatedController extends AppController
{
    public function send_mail(Request $request, $id)
    {
        try
        {
            $request->request->add(['get_data' => 'first', 'internal' => 'yes', 'investor_id' => $id]);
            $ctrl   = new \App\Http\Controllers\Sales\InvestorController;
            $inv    = $ctrl->investor_by_roles($request);
            if (isset($inv->investor_id))
            {
                $email = [];
                if ((isset($request->send_me) && $request->send_me == 'Yes') && !empty($this->auth_user()->email))
                    $email[] = $this->auth_user()->email;
                if ((isset($request->send_investor) && $request->send_investor == 'Yes') && !empty($inv->email))
                    $email[] = $inv->email;
                if (!empty($request->more_email))
                    $email = array_merge($email, $request->more_email);

                if (!empty($email))
                {
                    $file   = $request->file('attachment');
                    $path   = $file->storeAs('consolidated', $file->getClientOriginalName());
                    
                    foreach ($email as $m)
                    {
                        $mail = [
                            'to' => $m, 'content' => 'Consolidated Statement Portfolio Investor', 'attach' => storage_path('app/' . $path), 
                            'new' => [$inv->fullname, date('d/m')]
                        ];
                        $this->app_sendmail($mail);
                    }
                    unlink(storage_path('app/' . $path));
                }
                $response = $email;
                $response[] = $path;
            }
            else
            {
                $response = (object) ['errors' => ['error_code' => 403, 'error_msg' => ['Investor ID not found / You don\'t have permission']]];
            }
        }
        catch(\Exception $e)
        {
            $response = (object) ['errors' => ['error_code' => 500, 'error_msg' => $e->getMessage()]];
        }
        return $this->app_response('Send Mail', $response);
    }

    public function sendMail(Request $request, $id)
    {
        try
        {
            $mailQueue = $mailattachment = [];
            $request->request->add(['get_data' => 'first', 'internal' => 'yes', 'investor_id' => $id]);
            $ctrl   = new \App\Http\Controllers\Sales\InvestorController;
            $inv    = $ctrl->investor_by_roles($request);
            if (isset($inv->investor_id))
            {
                $email = [];
                if ((isset($request->send_me) && $request->send_me == 'Yes') && !empty($this->auth_user()->email))
                    $email[] = $this->auth_user()->email;
                if ((isset($request->send_investor) && $request->send_investor == 'Yes') && (!empty($inv->email) || !empty($inv->email_personal)))
                    $email[] = $inv->email ?? $inv->email_personal;
                if (!empty($request->more_email))
                    $email = array_merge($email, $request->more_email);
                
                if (!empty($email))
                {
                    $file = $request->file('attachment');
                    Storage::disk('ftp')->put($file->getClientOriginalName(), file_get_contents($file));
                    // Storage::disk('sftp')->put($file->getClientOriginalName(), file_get_contents($file));
                    // $layoutMail = EmailContent::join('c_email_layouts as b', 'c_email_contents.layout_id', '=', 'b.layout_id')
                    //             ->where([['c_email_contents.email_content_name', 'Consolidated Statement'], 
                    //                     ['c_email_contents.is_active', 'Yes'], ['b.is_active', 'Yes']])
                    //             ->first();
                    // $mail       = (object) ['data' => $layoutMail, 'mail' => ['new' => [$inv->fullname, date('F Y')]]];
                    
                    // $mailQueue = [
                    //     'queuestatus'   => '10', //data siap dikirim 
                    //     'mailto'        => implode(',', $email),
                    //     'subject'       => $layoutMail->email_subject,
                    //     // 'body'          => $message,
                    //     'body'          => view('emails.layouts', ['mail' => $mail])->render(),
                    //     'isbodyhtml'    => '1', 
                    //     'source'        => 'CPM'
                    // ];
                    // $send_mailqueque = MailQueue::create($mailQueue);

                    // $mailattachment = [
                    //     'tmailqueueid'          => $send_mailqueque->tmailqueueid, //ambil dari id mailqueue
                    //     'attachmenttype'        => '1',
                    //     'attachmentfilename'    => $file->getClientOriginalName(),
                    //     'fromssrsreportpath'    => env('FTP_ROOT').$file->getClientOriginalName(),
                    //     'password'              => date('dmY', strtotime($inv->date_of_birth)),
                    //     'syscreatedby'          => 'CPM'
                    // ];
                    // $save_mailattachement = MailAttachment::create($mailattachment);

                    EmailBlast::create(['investor_id' => $inv->investor_id, 'send_date' => date('Y-m-d'), 'send_type' => 'cs']);
                }
                $response = $email;
            }
            else
            {
                $response = (object) ['errors' => ['error_code' => 403, 'error_msg' => ['Investor ID not found / You don\'t have permission']]];
            }
        }
        catch(\Exception $e)
        {
            $response = (object) ['errors' => ['error_code' => 500, 'error_msg' => $e->getMessage()]];
        }
        return $this->responseJson('Send Mail', $response);
    }
}