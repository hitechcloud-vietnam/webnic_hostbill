<?php

class WebnicDnsApiClient
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

class webnic_dns extends DNSModule
{
    protected $version = '1.0.0';
    protected $modname = 'WebNIC DNS';
    protected $description = 'Clean-room WebNIC DNS module for HostBill';
    protected $_repository = 'hosting_webnic_dns';
    protected $options = [
        'maxdomain' => ['name' => 'maxdomain', 'value' => false, 'type' => 'input'],
        'ns1' => ['name' => 'ns1', 'value' => false, 'type' => 'input'],
        'ns2' => ['name' => 'ns2', 'value' => false, 'type' => 'input'],
        'ns3' => ['name' => 'ns3', 'value' => false, 'type' => 'input'],
        'ns4' => ['name' => 'ns4', 'value' => false, 'type' => 'input'],
        'dns_template' => ['name' => 'dns_template', 'value' => false, 'type' => 'input'],
        'hide_billing' => ['name' => 'hide_billing', 'value' => false, 'type' => 'check', 'default' => false],
        'hide_zone_management' => ['name' => 'hide_zone_management', 'value' => false, 'type' => 'check', 'default' => false],
    ];

    protected $serverFields = [
        self::CONNECTION_FIELD_HOSTNAME => false,
        self::CONNECTION_FIELD_IPADDRESS => false,
        self::CONNECTION_FIELD_USERNAME => true,
        self::CONNECTION_FIELD_PASSWORD => true,
        self::CONNECTION_FIELD_INPUT1 => false,
        self::CONNECTION_FIELD_INPUT2 => false,
        self::CONNECTION_FIELD_TEXTAREA => false,
        self::CONNECTION_FIELD_CHECKBOX => true,
        self::CONNECTION_FIELD_NAMESERVERS => false,
        self::CONNECTION_FIELD_MAXACCOUNTS => false,
        self::CONNECTION_FIELD_STATUSURL => false,
    ];

    protected $serverFieldsDescription = [
        self::CONNECTION_FIELD_USERNAME => 'WebNIC API Username',
        self::CONNECTION_FIELD_PASSWORD => 'WebNIC API Password',
        self::CONNECTION_FIELD_CHECKBOX => 'OTE Environment',
    ];

    protected $supported_records = ['A', 'AAAA', 'CNAME', 'MX', 'SRV', 'TXT'];
    protected $connect_data = [];
    protected $client;

    public function connect($server)
    {
        $this->connect_data = $server;
        $this->client = null;
    }

    public function addZone($domain, $ip = false)
    {
        $response = $this->api()->post('dns/v2/zone/' . rawurlencode($domain) . '/nameserver-subscription');
        if (!$this->success($response)) {
            return $this->failResponse($response);
        }

        return true;
    }

    public function deleteZone($domain)
    {
        $response = $this->api()->delete('dns/v2/zone/' . rawurlencode($domain));
        if (!$this->success($response)) {
            return $this->failResponse($response);
        }

        return true;
    }

    public function listZones()
    {
        $response = $this->api()->get('dns/v2/zones');
        if (!$this->success($response)) {
            $this->failResponse($response);
            return [];
        }

        $zones = [];
        foreach ((array) $response['data'] as $zone) {
            $zones[] = [
                'id' => isset($zone['subscriptionId']) ? $zone['subscriptionId'] : $zone['zone'],
                'domain' => $zone['zone'],
                'type' => $zone['zoneType'] ?? '',
                'subscription' => $zone['subscription'] ?? false,
            ];
        }

        return $zones;
    }

    public function getZone($domain)
    {
        $response = $this->api()->get('dns/v2/zone/' . rawurlencode($domain) . '/records');
        if (!$this->success($response)) {
            $this->failResponse($response);
            return [];
        }

        $records = [];
        foreach ((array) ($response['data']['records'] ?? []) as $record) {
            $records[] = $this->normalizeRecord($record);
        }

        return $records;
    }

    public function addRecord($domain, $data = [])
    {
        return $this->saveRecord($domain, $data);
    }

    public function deleteRecord($record, $domain = false)
    {
        $zone = $domain ?: (is_array($record) && !empty($record['zone']) ? $record['zone'] : false);
        if (!$zone) {
            $this->addError('Zone is required to delete DNS record.');
            return false;
        }

        $name = is_array($record) ? ($record['name'] ?? '') : '';
        $type = is_array($record) ? ($record['type'] ?? '') : '';
        if ($type === '' || $name === null) {
            $this->addError('Record name and type are required to delete DNS record.');
            return false;
        }

        $response = $this->api()->delete('dns/v2/zone/' . rawurlencode($zone) . '/record', [
            'type' => $type,
            'name' => $name,
        ]);
        if (!$this->success($response)) {
            return $this->failResponse($response);
        }

        return true;
    }

    public function editRecord($record, $data = [], $domain = false)
    {
        $zone = $domain ?: (is_array($record) && !empty($record['zone']) ? $record['zone'] : false);
        if (!$zone) {
            $this->addError('Zone is required to edit DNS record.');
            return false;
        }

        if (empty($data['name']) && is_array($record) && isset($record['name'])) {
            $data['name'] = $record['name'];
        }
        if (empty($data['type']) && is_array($record) && isset($record['type'])) {
            $data['type'] = $record['type'];
        }

        return $this->saveRecord($zone, $data);
    }

    public function getSupportedRecords()
    {
        $response = $this->api()->get('dns/v2/zone/record-types');
        if ($this->success($response) && !empty($response['data'])) {
            return $response['data'];
        }

        return $this->supported_records;
    }

    public function getDefaultNameservers()
    {
        $subscription = $this->api()->get('dns/v2/zone/subscription/record/nameservers');
        if ($this->success($subscription) && !empty($subscription['data'])) {
            return $subscription['data'];
        }

        $basic = $this->api()->get('dns/v2/zone/basic/record/nameservers');
        if ($this->success($basic) && !empty($basic['data'])) {
            return $basic['data'];
        }

        return [];
    }

    public function getDomainLimit()
    {
        try {
            return $this->resource('maxdomain');
        } catch (Exception $e) {
        }

        return 0;
    }

    public function testConnection()
    {
        return $this->api()->testConnection();
    }

    public function getAppConfigSummary()
    {
        return [
            'nameservers' => $this->getDefaultNameservers(),
            'supported_records' => $this->getSupportedRecords(),
            'zone_limit' => $this->getDomainLimit(),
        ];
    }

    protected function api()
    {
        if ($this->client === null) {
            $this->client = new WebnicDnsApiClient(
                $this->connect_data['username'],
                $this->connect_data['password'],
                !empty($this->connect_data['ssl'])
            );
        }

        return $this->client;
    }

    protected function success($response)
    {
        return $this->api()->isSuccessful($response);
    }

    protected function failResponse($response)
    {
        $this->addError($this->api()->extractError($response));
        return false;
    }

    protected function saveRecord($zone, array $data)
    {
        $payload = [
            'name' => isset($data['name']) ? $data['name'] : '@',
            'type' => strtoupper($data['type'] ?? 'A'),
            'ttl' => !empty($data['ttl']) ? (int) $data['ttl'] : 3600,
            'rdatas' => $this->normalizeRdatas($data),
        ];

        $response = $this->api()->post('dns/v2/zone/' . rawurlencode($zone) . '/record', $payload);
        if (!$this->success($response)) {
            return $this->failResponse($response);
        }

        return true;
    }

    protected function normalizeRdatas(array $data)
    {
        if (!empty($data['rdatas']) && is_array($data['rdatas'])) {
            return $data['rdatas'];
        }
        if (!empty($data['value']) && is_array($data['value'])) {
            $items = [];
            foreach ($data['value'] as $value) {
                $items[] = ['value' => $value];
            }
            return $items;
        }
        if (!empty($data['content']) && is_array($data['content'])) {
            $items = [];
            foreach ($data['content'] as $value) {
                $items[] = ['value' => $value];
            }
            return $items;
        }

        $value = '';
        if (isset($data['value'])) {
            $value = $data['value'];
        } elseif (isset($data['content'])) {
            $value = $data['content'];
        }

        return [['value' => $value]];
    }

    protected function normalizeRecord(array $record)
    {
        $name = array_key_exists('name', $record) ? $record['name'] : '';
        return [
            'id' => md5(($record['type'] ?? '') . '|' . $name),
            'zone' => $record['zone'] ?? null,
            'name' => $name,
            'type' => $record['type'] ?? '',
            'ttl' => $record['ttl'] ?? 3600,
            'rdatas' => $record['rdatas'] ?? [],
            'value' => !empty($record['rdatas'][0]['value']) ? $record['rdatas'][0]['value'] : '',
            'remarks' => $record['remarks'] ?? '',
        ];
    }
}
