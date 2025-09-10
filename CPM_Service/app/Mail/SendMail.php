<?php

namespace App\Mail;

use App\Mail\Email;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class sendMail
{
    public function sendMail(array $mail, $data = null): array {
        $from = env('MAIL_FROM');
        $subject = isset($data->email_subject) ? (string) $data->email_subject : 'No Subject';

        $errors = [];
        if (!array_key_exists('to', $mail) || !is_string($mail['to'])) {
            $errors = ['Recipient (mail["to"]) is required.'];
        } else {
            $to = trim($mail['to']);
            if ($to === '') {
                $errors = ['Recipient (mail["to"]) is required.'];
            } elseif (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                $errors = ['Recipient email is invalid.'];
            }
        }
        
        $hasAttach = false; 
        $attachPath = null;
        if (array_key_exists('attach', $mail)) {
            if (is_string($mail['attach']) && is_file($mail['attach']) && is_readable($mail['attach'])) {
                $hasAttach = true; 
                $attachPath = $mail['attach'];
            } else {
                $errors = ['Attachment not found or unreadable.'];
                Log::notice('sendMail: invalid attachment', ['attach' => $mail['attach']]);
            }
        }

        if ($errors) {
            Log::warning('sendMail: validation failed', [
                'errors' => $errors, 
                'to' => $mail['to'] ?? null
            ]);

            return [
                'status' => 422,
                'errors' => $errors
            ];
        }

        $payload = (object) [
            'from'    => $from,
            'subject' => $subject,
            'data'    => $data,
            'mail'    => $mail,
        ];

        if ($hasAttach) {
            $payload->attach = $attachPath;
        }

        try {
            Mail::to($to)->send(new Email($payload));
            return [
                'status' => 200,
                'data' => [
                    'to'         => $to,
                    'from'       => $from,
                    'subject'    => $subject,
                    'has_attach' => $hasAttach,
                ]
            ];
        } catch (\Exception $e) {
            Log::error('sendMail: send failed', [
                'to' => $to ?? null,
                'subject' => $subject,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            $isTransport = (strpos(get_class($e), 'Transport') !== false);

            return [
                'status' => $isTransport ? 502 : 500,
                'errors' => [
                    'to' => $to,
                    'from' => $from,
                    'subject' => $subject,
                    'has_attach' => $hasAttach,
                    'should_retry' => $isTransport,
                    'exception' => $e->getMessage()
                ]
            ];
        }
    }

    public function sendMailContent($mail) {
        if (empty($mail['content_id']) && empty($mail['content'])) {
            Log::warning('sendMailContent: missing content key', ['mail' => ['to' => $mail['to'] ?? null]]);
            return [
                'status' => 422,
                'errors' => ['Provide either mail["content_id"] or mail["content"].'],
            ];
        }

        $useId   = !empty($mail['content_id']);
        $field   = $useId ? 'email_content_id' : 'email_content_name';
        $lookup  = $useId ? $mail['content_id'] : $mail['content'];
        $lookup  = is_string($lookup) ? trim($lookup) : $lookup;
        
        try {
            $data = DB::table('c_email_contents as cmc')
                ->join('c_email_layouts as cml', function($join) {
                    $join->on('cmc.layout_id', '=', 'cml.layout_id')
                        ->where('cml.is_active', 'Yes');
                })
                ->where('cmc.is_active', 'Yes')
                ->where("cmc.$field", $lookup)
                ->first();            
        } catch (\Exception $e) {
            Log::error('sendMailContent: query failed', [
                'lookup_by' => $field,
                'lookup'    => $lookup,
                'exception' => get_class($e),
                'message'   => $e->getMessage(),
            ]);

            return [
                'status' => 500,
                'errors' => [
                    'lookup_by' => $field,
                    'lookup'    => $lookup,
                    'exception' => get_class($e),
                    'message'   => $e->getMessage(),
                ]
            ];
        }

        if (!$data || empty($data->email_content_id)) {
            Log::warning('sendMailContent: content not found', ['lookup_by' => $field, 'lookup' => $lookup]);
            return [
                'status' => 404,
                'errors' => [
                    'lookup_by' => $field,
                    'lookup'    => $lookup,
                    'message'   => 'Content not found',
                ]
            ];
        }

        return $this->sendMail($mail, $data);
    }
}