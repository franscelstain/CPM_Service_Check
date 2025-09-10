<?php

namespace App\Services\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class HttpService
{
    public function get(string $uri, array $options = []): array
    {
        return $this->request('GET', $uri, $options);
    }

    public function post(string $uri, array $options = []): array
    {
        return $this->request('POST', $uri, $options);
    }

    public function request(string $method, string $url, array $options = []): array
    {
        try {
            // Default headers
            $headers = ['Accept' => 'application/json'];
            if (!empty($options['headers']) && is_array($options['headers'])) {
                $headers = array_merge($headers, $options['headers']);
            }
            $options['headers'] = $headers;

            // Parse base URI & endpoint
            $parsed = parse_url($url);
            $baseUri = $parsed['scheme'] . '://' . $parsed['host'];
            if (isset($parsed['port'])) {
                $baseUri .= ':' . $parsed['port'];
            }
            $endpoint = $parsed['path'] ?? '/';

            // Build Guzzle client
            $client = new \GuzzleHttp\Client([
                'base_uri' => $baseUri,
                'timeout' => 10.0,
                'headers' => $headers,
            ]);

            $response = $client->request($method, $endpoint, $options);
            $body = json_decode($response->getBody()->getContents(), true);

            if (is_array($body) && isset($body['success'])) {
                return $body;
            }

            return [
                'success' => true,
                'status' => $response->getStatusCode(),
                'data' => $body
            ];

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $response = $e->getResponse();
            $status = $response ? $response->getStatusCode() : 500;
            $body = $response ? json_decode($response->getBody()->getContents(), true) : null;

            return [
                'success' => false,
                'status' => $status,
                'error' => $body ?? $e->getMessage()
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'status' => 500,
                'error' => $e->getMessage()
            ];
        }
    }
}
