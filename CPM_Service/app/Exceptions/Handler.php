<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Laravel\Lumen\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpFoundation\Response as HttpResponse; // penting utk statusTexts
use Throwable;
use PDOException;

class Handler extends ExceptionHandler
{
    /**
     * Exceptions yang tidak di-report.
     * Parent::report() akan hormati daftar ini.
     */
    protected $dontReport = [
        AuthorizationException::class,
        HttpException::class,
        ModelNotFoundException::class,
        ValidationException::class,
        QueryException::class, // opsional
    ];

    private function isDbConnectionIssue(Throwable $e): bool
    {
        // Ambil akar exception
        $root = $e;
        while ($root->getPrevious()) {
            $root = $root->getPrevious();
        }

        $msg  = strtolower((string) $root->getMessage());
        $code = (string) ($root->getCode() ?? '');

        // SQLSTATE 08xxx = Connection Exception
        $isSqlState08 = (strpos($msg, 'sqlstate[08') !== false) || (strpos($code, '08') === 0);

        // Tanda umum koneksi DB putus
        $isConnText =
            strpos($msg, 'could not connect') !== false ||
            strpos($msg, 'connection refused') !== false ||
            strpos($msg, 'server has gone away') !== false ||
            strpos($msg, 'no such host') !== false ||
            strpos($msg, 'broken pipe') !== false ||
            strpos($msg, 'timeout') !== false;

        return $isSqlState08 || $isConnText || ($root instanceof PDOException);
    }

    public function report(Throwable $e)
    {
        // Hanya log manual jika memang perlu (biar nggak dobel & hormati dontReport)
        if ($this->shouldReport($e)) {
            $status = ($e instanceof HttpException) ? $e->getStatusCode() : 500;

            if ($status >= 500) {
                \Log::error('Unhandled exception', [
                    'message' => $e->getMessage(),
                    'file'    => $e->getFile(),
                    'line'    => $e->getLine(),
                    'trace'   => $e->getTraceAsString(),
                ]);
            } else {
                \Log::warning('Client error', [
                    'status'  => $status,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        parent::report($e);
    }

    public function render($request, Throwable $e)
    {
        $status  = 500;
        $message = 'Internal Server Error';

        $errors = [app()->environment('local') ? $e->getMessage() : 'Unknown error occurred.'];

        // Mapping umum
        if ($e instanceof ValidationException) {
            $status  = 422;
            $message = 'Validation Error';
            $errors  = $this->flattenValidationErrors($e);
        } elseif ($e instanceof \Illuminate\Auth\AuthenticationException) {
            $status  = 401;
            $message = 'Unauthorized';
            $errors  = ['You are not authenticated. Please log in.'];
        } elseif ($e instanceof AuthorizationException) {
            $status  = 403;
            $message = 'Forbidden';
            $errors  = ['You are not authorized to access this resource.'];
        } elseif ($e instanceof ModelNotFoundException || $e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
            $status  = 404;
            $message = 'Not Found';
            $errors  = ['The requested resource was not found.'];
        } elseif ($e instanceof HttpException) {
            $status  = $e->getStatusCode();
            $message = $e->getMessage() ?: (isset(HttpResponse::$statusTexts[$status]) ? HttpResponse::$statusTexts[$status] : 'HTTP Error');
            if ($status === 405 && (empty($errors) || (count($errors) === 1 && trim((string)$errors[0]) === ''))) {
                $errors = ['Method not allowed.'];
            }
        }

        // Deteksi isu koneksi DB -> 503
        if ($this->isDbConnectionIssue($e)) {
            $status  = 503;
            $message = 'Service Unavailable';
            $errors  = ['Database connection issue. Please retry later.'];
        }

        // Pastikan errors tidak kosong (PHP 7.3: pakai switch, bukan match)
        if (empty($errors) || (count($errors) === 1 && trim((string)$errors[0]) === '')) {
            switch ($status) {
                case 401:
                    $errors = ['You are not authenticated. Please log in.'];
                    break;
                case 403:
                    $errors = ['You are not authorized to access this resource.'];
                    break;
                case 404:
                    $errors = ['The requested resource was not found.'];
                    break;
                case 422:
                    $errors = ['There was a validation error.'];
                    break;
                case 503:
                    $errors = ['Service temporarily unavailable.'];
                    break;
                default:
                    $errors = ['Unknown error occurred.'];
                    break;
            }
        }

        // Tambah request_id untuk korelasi log
        $requestId = $request->headers->get('X-Request-Id');
        if (!$requestId) {
            // random_bytes tersedia sejak PHP 7.0
            try {
                $requestId = bin2hex(random_bytes(8));
            } catch (\Exception $ex) {
                // fallback kalau extension random gagal
                $requestId = uniqid('', true);
            }
        }

        $payload = [
            'success'     => false,
            'code'        => $status,
            'message'     => $message,
            'errors'      => array_values($errors),
            'request_id'  => $requestId,
        ];

        $response = response()->json($payload, $status);

        if ($status === 503) {
            $response->headers->set('Retry-After', '5');
        }

        // Jika mau dukung HTML utk non-API, uncomment:
        // if (!$request->expectsJson()) return parent::render($request, $e);

        return $response;
    }

    private function flattenValidationErrors(ValidationException $e): array
    {
        $bag = $e->errors(); // ['field' => ['msg1','msg2'], ...]
        $out = [];
        foreach ($bag as $field => $messages) {
            foreach ((array) $messages as $msg) {
                $out[] = $field . ': ' . $msg;
            }
        }
        return $out ?: ['There was a validation error.'];
    }
}
