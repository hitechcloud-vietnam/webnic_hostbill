<?php

class WebnicApiClient
{
    const LIVE_BASE = 'https://api.webnic.cc/';
    const TEST_BASE = 'https://oteapi.webnic.cc/';
    const TOKEN_PATH = 'reseller/v2/api-user/token';

    protected $username;
    protected $password;
    protected $testMode = false;
    protected $timeout = 90;
    protected $accessToken;
    protected $tokenExpiresAt = 0;
    protected $lastResponse = [];
    protected $lastError = '';

    public function __construct($username, $password, $testMode = false, $timeout = 90)
    {
        $this->username = (string) $username;
        $this->password = (string) $password;
        $this->testMode = (bool) $testMode;
        $this->timeout = (int) $timeout;
    }

    public function get($path, array $query = [])
    {
        return $this->request('GET', $path, [
            'query' => $query,
        ]);
    }

    public function post($path, array $body = [], array $options = [])
    {
        $options['body'] = $body;

        return $this->request('POST', $path, $options);
    }

    public function put($path, array $body = [], array $options = [])
    {
        $options['body'] = $body;

        return $this->request('PUT', $path, $options);
    }

    public function delete($path, array $query = [], array $options = [])
    {
        $options['query'] = $query;

        return $this->request('DELETE', $path, $options);
    }

    public function download($path, array $options = [])
    {
        $options['raw'] = true;

        return $this->request('POST', $path, $options);
    }

    public function getLastResponse()
    {
        return $this->lastResponse;
    }

    public function getLastError()
    {
        return $this->lastError;
    }

    public function isSuccessful($response)
    {
        return is_array($response) && isset($response['code']) && (string) $response['code'] === '1000';
    }

    public function testConnection()
    {
        return $this->authenticate();
    }

    public function extractError($response)
    {
        if (!is_array($response)) {
            return 'Empty response from WebNIC API';
        }

        $messages = [];

        if (!empty($response['message'])) {
            $messages[] = $response['message'];
        }
        if (!empty($response['error']['subCode'])) {
            $messages[] = $response['error']['subCode'];
        }
        if (!empty($response['error']['message'])) {
            $messages[] = $response['error']['message'];
        }
        if (!empty($response['validationErrors']) && is_array($response['validationErrors'])) {
            foreach ($response['validationErrors'] as $validationError) {
                if (!empty($validationError['field']) || !empty($validationError['message'])) {
                    $messages[] = trim(($validationError['field'] ?? '') . ': ' . ($validationError['message'] ?? ''), ': ');
                }
            }
        }

        $messages = array_values(array_unique(array_filter($messages)));

        return $messages ? implode(' | ', $messages) : 'WebNIC API request failed';
    }

    protected function request($method, $path, array $options = [])
    {
        if (!$this->authenticate()) {
            return [
                'code' => '0',
                'message' => 'Authentication failed',
                'error' => [
                    'message' => $this->lastError,
                ],
            ];
        }

        $url = rtrim($this->getBaseUrl(), '/') . '/' . ltrim($path, '/');
        if (!empty($options['query']) && is_array($options['query'])) {
            $query = http_build_query($options['query']);
            if ($query !== '') {
                $url .= (strpos($url, '?') === false ? '?' : '&') . $query;
            }
        }

        $headers = [
            'Authorization: Bearer ' . $this->accessToken,
            'Accept: application/json',
        ];

        $curl = curl_init();
        $responseHeaders = [];

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($curl, CURLOPT_HEADERFUNCTION, function ($curlHandle, $headerLine) use (&$responseHeaders) {
            $length = strlen($headerLine);
            $parts = explode(':', $headerLine, 2);
            if (count($parts) === 2) {
                $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
            }

            return $length;
        });

        if (!empty($options['multipart'])) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $options['multipart']);
        } elseif (array_key_exists('body', $options)) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($options['body']));
        }

        if (!empty($options['headers']) && is_array($options['headers'])) {
            $headers = array_merge($headers, $options['headers']);
        }

        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $rawResponse = curl_exec($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        if ($rawResponse === false) {
            $this->lastError = $curlError ?: 'Unknown cURL error';

            return [
                'code' => '0',
                'message' => 'Transport error',
                'error' => [
                    'message' => $this->lastError,
                ],
            ];
        }

        if (!empty($options['raw'])) {
            $result = [
                'code' => $httpCode >= 200 && $httpCode < 300 ? '1000' : (string) $httpCode,
                'message' => $httpCode >= 200 && $httpCode < 300 ? 'Command completed successfully.' : 'Binary request failed',
                'data' => $rawResponse,
                'headers' => $responseHeaders,
                'httpCode' => $httpCode,
            ];
            $this->lastResponse = $result;
            $this->lastError = $httpCode >= 200 && $httpCode < 300 ? '' : 'Binary request failed';

            return $result;
        }

        $decoded = json_decode($rawResponse, true);
        if (!is_array($decoded)) {
            $decoded = [
                'code' => (string) $httpCode,
                'message' => 'Unexpected response from WebNIC API',
                'error' => [
                    'message' => $rawResponse,
                ],
            ];
        }

        $decoded['httpCode'] = $httpCode;
        $decoded['headers'] = $responseHeaders;
        $this->lastResponse = $decoded;
        $this->lastError = $this->isSuccessful($decoded) ? '' : $this->extractError($decoded);

        return $decoded;
    }

    protected function authenticate()
    {
        if ($this->accessToken && $this->tokenExpiresAt > time() + 30) {
            return true;
        }

        $tokenUrl = rtrim($this->getBaseUrl(), '/') . '/' . self::TOKEN_PATH;
        $payload = json_encode([
            'username' => $this->username,
            'password' => $this->password,
        ]);

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $tokenUrl);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
        ]);

        $rawResponse = curl_exec($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        if ($rawResponse === false) {
            $this->lastError = $curlError ?: 'Unable to retrieve WebNIC token';
            return false;
        }

        $decoded = json_decode($rawResponse, true);
        if (!is_array($decoded) || (string) ($decoded['code'] ?? '') !== '1000') {
            $decoded = is_array($decoded) ? $decoded : [
                'message' => 'Unable to decode WebNIC token response',
                'error' => [
                    'message' => $rawResponse,
                ],
            ];
            $this->lastError = $this->extractError($decoded);
            return false;
        }

        $this->accessToken = (string) $decoded['data']['access_token'];
        $expiresIn = (int) ($decoded['data']['expires_in'] ?? 3600);
        $this->tokenExpiresAt = time() + $expiresIn;
        $this->lastError = '';
        $this->lastResponse = $decoded;

        return $httpCode >= 200 && $httpCode < 300;
    }

    protected function getBaseUrl()
    {
        return $this->testMode ? self::TEST_BASE : self::LIVE_BASE;
    }
}
