<?php

namespace App\Services\Http;

use App\Services\Http\HttpService;
use App\Repositories\Auth\MetadataRepository;

class MetadataService
{
    protected $metadataRepository;
    protected $client;

    public function __construct(MetadataRepository $metadataRepository, HttpService $client)
    {
        $this->metadataRepository = $metadataRepository;
        $this->client = $client;
    }

    public function callServiceApi(string $serviceName, array $input = [], $path = null): ?array
    {
        try {
            $service = $this->metadataRepository->getServiceByName($serviceName);
            if (!$service) {
                return $this->errorResponse('Service not found', 404);
            }

            $host = $this->metadataRepository->getHostById($service->api_id);
            if (!$host) {
                return $this->errorResponse('Host not found', 404);
            }
            
            $token = $host->token ?? null;
            if ($host->get_token === 'Yes' && empty($token)) {
                $token = $this->getTokenFromHost($host);
            }

            $method = strtoupper($service->service_method ?? 'GET');
            $options = $this->getServiceParam($service->service_id, $method, $input, $token);
            $url = rtrim($host->slug, '/') . '/' . ltrim($service->service_path, '/');
            if (!empty($path)) {
                $url .= '/' . ltrim($path, '/');
            }

            $response = $this->callRequest($url, $method, $options, $host, $token);

            if (
                !$response['success'] &&
                ($response['status'] === 401) &&
                ($host->get_token ?? 'No') === 'Yes'
            ) {
                $token = $this->getTokenFromHost($host);
                if (isset($options['query']['token'])) {
                    $options['query']['token'] = $token;
                } elseif (isset($options['json']['token'])) {
                    $options['json']['token'] = $token;
                }

                $response = $this->callRequest($url, $method, $options, $host, $token);
            }

            return $response;
        } catch (\Exception $e) {
            return $this->errorResponse(
                $e->getMessage(),
                method_exists($e, 'getCode') ? $e->getCode() : 500
            );
        }
    }

    private function callRequest($url, $method, $options, $host, $token = null) {
        if ($host->get_token === 'Yes' && $token && strtolower($host->authorization) === 'bearer') {
            $options['headers']['Authorization'] = "Bearer $token";
        }

        return $this->client->request($method, $url, $options);
    }

    private function errorResponse($message, $code = 500): array
    {
        return [
            'success' => false,
            'status' => $code ?: 500,
            'errors' => $message
        ];
    }

    private function getTokenFromHost($host) {
        $authLink = $host->slug . $host->auth_link;

        if ($host->authorization === 'auth') {
            $credentials = [
                'auth' => [$host->username, $host->password]
            ];
        } else {
            $credentials = [
                'json' => [
                    $host->user_label => $host->username,
                    $host->pass_label => $host->password
                ]
            ];
        }

        $method = strtoupper($host->auth_method ?? 'GET');
        $dataKey = $host->data_key ?? 'data';
        $response = $this->client->request($method, $authLink, $credentials);

        $token = $response[$dataKey] ?? null;

        if ($token) {
            $this->metadataRepository->updateHostToken($host->api_id, $token);
        }

        return $token;
    }


    protected function getServiceParam($serviceId, $method, array $input = [], $token = null): array
    {
        $result = [];
        $params = $this->metadataRepository->getParamByServiceId($serviceId);

        if ($params->isEmpty()) {
            return $result;
        }

        $isGet = strtoupper($method) === 'GET';
        $n = 0;

        foreach ($params as $param) {
            $key = $param->param_key;

            if ($key === 'token') {
                $value = $token;
            } elseif (!empty($param->param_value) || is_numeric($param->param_value)) {
                $value = $param->param_value;
            } else {
                $value = $input[$n++] ?? '';
            }

            if (
                $key === 'token' ||
                !empty($value) ||
                is_numeric($value) ||
                is_null($value) ||
                is_bool($value)
            ) {
                if (!empty($param->param_type)) {
                    $value = $param->param_type === 'number' ? floatval($value) : $value;
                } else {
                    $value = is_numeric($value) ? floatval($value) : $value;
                }

                if ($isGet) {
                    $result['query'][$key] = $value;
                } else {
                    $result['json'][$key] = $value;
                }
            }
        }

        return $result;
    }
}