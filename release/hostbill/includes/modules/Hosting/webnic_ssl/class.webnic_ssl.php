<?php

class WebnicSslApiClient
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

class webnic_ssl extends SSLModule
{
    protected $version = '1.0.0';
    protected $modname = 'WebNIC SSL';
    protected $description = 'Clean-room WebNIC SSL provisioning module for HostBill';
    protected $_repository = 'hosting_webnic_ssl';

    protected $options = [
        'product_key' => [
            'name' => 'WebNIC Product Key',
            'value' => false,
            'type' => 'input',
        ],
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

    protected $details = [
        \SSL\CN => [
            'name' => 'Common Name',
            'type' => 'input',
        ],
        \SSL\ORDERID => [
            'name' => 'Order ID',
            'type' => 'input',
        ],
        \SSL\STATUS => [
            'name' => 'Certificate Status',
            'type' => 'input',
        ],
        \SSL\CSR => [
            'type' => 'hidden',
        ],
        \SSL\CSR_SERVER => [
            'type' => 'hidden',
        ],
        \SSL\DCV => [
            'type' => 'hidden',
        ],
        \SSL\DCV_DETAILS => [
            'type' => 'hidden',
        ],
        \SSL\CONTACTS => [
            'type' => 'hidden',
        ],
        \SSL\SAN => [
            'type' => 'hidden',
        ],
        'productKey' => [
            'type' => 'hidden',
        ],
        'adminContactId' => [
            'type' => 'hidden',
        ],
        'techContactId' => [
            'type' => 'hidden',
        ],
        'shipmentContactId' => [
            'type' => 'hidden',
        ],
        'vendor_order_status' => [
            'type' => 'hidden',
        ],
        'vendor_cert_status' => [
            'type' => 'hidden',
        ],
    ];

    protected $commands = ['Reissue', 'Terminate'];
    protected $connect_data = [];
    protected $client;

    public function connect($server)
    {
        $this->connect_data = $server;
        $this->client = null;
    }

    public function PrepareDetails($details)
    {
        if (!is_array($details)) {
            return;
        }

        foreach ($details as $key => $value) {
            if (isset($this->details[$key])) {
                $this->details[$key]['value'] = $value;
            }
        }
    }

    public function Create()
    {
        $contactIds = $this->ensureContacts();
        if ($contactIds === false) {
            return false;
        }

        $payload = [
            'productKey' => $this->resolveProductKey(),
            'term' => max(1, (int) $this->periodYears),
            'csr' => $this->details[\SSL\CSR]['value'],
            'administratorContactId' => $contactIds['administratorContactId'],
            'technicalContactId' => $contactIds['technicalContactId'],
            'shipmentContactId' => $contactIds['shipmentContactId'],
            'authType' => $this->resolveAuthType(),
        ];

        $sans = $this->getSanList();
        if (!empty($sans)) {
            $payload['sanfield'] = $sans;
        }

        if (!empty($this->details[\SSL\DCV_DETAILS]['value']) && $payload['authType'] === 'email') {
            $payload['approverEmail'] = $this->buildApproverEmails($this->details[\SSL\DCV_DETAILS]['value']);
        }

        if (!empty($this->details['organizationId']['value'])) {
            $payload['organizationId'] = $this->details['organizationId']['value'];
        }

        $response = $this->api()->post('ssl/v2/orders/new', $payload);
        if (!$this->success($response)) {
            return $this->failResponse($response);
        }

        $this->details[\SSL\ORDERID]['value'] = $response['data']['orderId'];
        $this->details[\SSL\STATUS]['value'] = 'PROCESSING';
        $this->details['productKey']['value'] = $payload['productKey'];
        $this->storeDcvDetails($response['data']);

        return true;
    }

    public function Renewal()
    {
        $orderId = $this->details[\SSL\ORDERID]['value'];
        $payload = [
            'term' => max(1, (int) $this->periodYears),
        ];
        if (!empty($this->details[\SSL\CSR]['value'])) {
            $payload['csr'] = $this->details[\SSL\CSR]['value'];
        }

        $response = $this->api()->post('ssl/v2/orders/' . rawurlencode($orderId) . '/renew', $payload);
        if (!$this->success($response)) {
            return $this->failResponse($response);
        }

        if (!empty($response['data']['renewOrder']['orderId'])) {
            $this->details[\SSL\ORDERID]['value'] = $response['data']['renewOrder']['orderId'];
        }
        $this->details[\SSL\STATUS]['value'] = 'PROCESSING';

        return true;
    }

    public function Reissue()
    {
        $orderId = $this->details[\SSL\ORDERID]['value'];
        $payload = [
            'csr' => $this->details[\SSL\CSR]['value'],
        ];
        $response = $this->api()->post('ssl/v2/orders/' . rawurlencode($orderId) . '/reissue', $payload);
        if (!$this->success($response)) {
            return $this->failResponse($response);
        }

        $this->details[\SSL\STATUS]['value'] = 'PROCESSING';
        $this->storeDcvDetails($response['data']);

        return true;
    }

    public function Terminate()
    {
        $orderId = $this->details[\SSL\ORDERID]['value'];
        $response = $this->api()->post('ssl/v2/orders/' . rawurlencode($orderId) . '/cancel');
        if (!$this->success($response)) {
            return $this->failResponse($response);
        }

        $this->details[\SSL\STATUS]['value'] = 'CANCELLED';
        return true;
    }

    public function testConnection()
    {
        return $this->api()->testConnection();
    }

    public function CertOptions($product)
    {
        $productKey = $this->extractProductValue($product, 'product_key', $this->options['product_key']['value']);
        $catalog = $this->findProductCatalogItem($productKey);

        $supportsWildcard = !empty($catalog['wildcard']);
        $supportsSan = !empty($catalog['allowSan']) || !empty($catalog['allowWsan']);
        $sanLimit = 0;
        if (!empty($catalog['bundle']['san'])) {
            $sanLimit = (int) $catalog['bundle']['san'];
        }
        if (!empty($catalog['bundle']['wSan'])) {
            $sanLimit = max($sanLimit, (int) $catalog['bundle']['wSan']);
        }

        return [
            \SSL\OPT\CSR => true,
            \SSL\OPT\DCV => [\SSL\DCV\EMAIL, \SSL\DCV\DNS, \SSL\DCV\HTTP],
            \SSL\OPT\WILDCARD => $supportsWildcard,
            \SSL\OPT\SAN_LIMIT => $supportsSan ? $sanLimit : 0,
            \SSL\OPT\SAN_DOMAIN => $supportsSan,
            \SSL\OPT\SAN_PUBLICIPS => false,
        ];
    }

    public function CertContacts($params)
    {
        return SSLType::contacts_init('OV', $params);
    }

    public function CertDCVEmail()
    {
        $domains = array_merge([$this->details[\SSL\CN]['value']], $this->getSanList());
        $list = [];
        foreach (array_unique(array_filter($domains)) as $domain) {
            $response = $this->api()->get('ssl/v2/domainValidations/approver-list', ['commonName' => str_replace('*.', '', $domain)]);
            if ($this->success($response) && !empty($response['data'])) {
                $list[$domain] = $response['data'];
            }
        }

        return $list;
    }

    public function CertSynchronize()
    {
        $info = $this->getSynchInfo();
        return $info ? $this->CertDetails() : [];
    }

    public function changeDCV($dcv, $email = '')
    {
        $orderId = $this->details[\SSL\ORDERID]['value'];
        $method = strtolower((string) $dcv);
        if ($method === 'http') {
            $method = 'file';
        }

        $payload = ['authType' => $method];
        if ($method === 'email' && $email !== '') {
            $payload['approverEmail'] = $email;
        }

        $response = $this->api()->post('ssl/v2/orders/' . rawurlencode($orderId) . '/auth', $payload);
        if (!$this->success($response)) {
            return $this->failResponse($response);
        }

        $this->details[\SSL\DCV]['value'] = $method === 'file' ? 'http' : $method;
        $this->storeDcvDetails($response['data']);

        return true;
    }

    public function ResendDCVEmail()
    {
        $current = $this->details[\SSL\DCV_DETAILS]['value'] ?? [];
        $email = '';
        foreach ((array) $current as $entry) {
            if (is_array($entry) && !empty($entry['value'])) {
                $email = $entry['value'];
                break;
            }
        }

        return $this->changeDCV('email', $email);
    }

    public function CertDcvDns()
    {
        $records = [];
        foreach ((array) ($this->details[\SSL\DCV_DETAILS]['value'] ?? []) as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            if (($entry['type'] ?? '') === 'dns' || !empty($entry['dnsName']) || !empty($entry['dnsValue'])) {
                $records[] = [
                    'name' => $entry['dnsName'] ?? ($entry['name'] ?? ''),
                    'type' => $entry['dnsType'] ?? 'TXT',
                    'content' => $entry['dnsValue'] ?? ($entry['value'] ?? ''),
                ];
            }
        }

        return $records;
    }

    public function CertDcvHttp()
    {
        $files = [];
        foreach ((array) ($this->details[\SSL\DCV_DETAILS]['value'] ?? []) as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            if (($entry['type'] ?? '') === 'file' || !empty($entry['fileName']) || !empty($entry['fileContent']) || !empty($entry['url'])) {
                $files[] = [
                    'url' => $entry['url'] ?? ($entry['fileName'] ?? ''),
                    'data' => $entry['fileContent'] ?? ($entry['value'] ?? ''),
                ];
            }
        }

        return $files;
    }

    public function getApproverEmailChoices()
    {
        $emails = [];
        foreach ($this->CertDCVEmail() as $domain => $items) {
            foreach ((array) $items as $item) {
                $emails[] = $item;
            }
        }

        return array_values(array_unique(array_filter($emails)));
    }

    public function getUiCertificateDetails()
    {
        $this->getSynchInfo();

        return [
            'cn' => $this->details[\SSL\CN]['value'] ?? '',
            'order_id' => $this->details[\SSL\ORDERID]['value'] ?? '',
            'status' => $this->details[\SSL\STATUS]['value'] ?? '',
            'csr' => $this->details[\SSL\CSR]['value'] ?? '',
            'san' => $this->getSanList(),
            'dcv' => $this->details[\SSL\DCV]['value'] ?? '',
            'dcv_details' => $this->details[\SSL\DCV_DETAILS]['value'] ?? [],
            'dcv_status' => $this->details['vendor_cert_status']['value'] ?? '',
        ];
    }

    public function getProductCatalogOptions()
    {
        return $this->getProductCatalog();
    }

    public function getSynchInfo()
    {
        $orderId = $this->details[\SSL\ORDERID]['value'];
        $response = $this->api()->get('ssl/v2/orders/info', ['orderId' => $orderId]);
        if (!$this->success($response)) {
            return $this->failResponse($response);
        }

        $dcvResponse = $this->api()->get('ssl/v2/orders/' . rawurlencode($orderId) . '/auth/info');
        if ($this->success($dcvResponse)) {
            $this->storeDcvDetails($dcvResponse['data']);
        }

        $this->details[\SSL\CN]['value'] = $response['data']['commonName'];
        $this->details[\SSL\SAN]['value'] = !empty($response['data']['sanfield']) ? $response['data']['sanfield'] : [];
        $this->details['adminContactId']['value'] = $response['data']['admid'];
        $this->details['techContactId']['value'] = $response['data']['tecid'];
        $this->details['vendor_order_status']['value'] = $response['data']['orderStatus'];
        $this->details['vendor_cert_status']['value'] = $response['data']['certStatus'];
        $this->details[\SSL\STATUS]['value'] = $this->mapVendorStatus($response['data']['orderStatus'], $response['data']['certStatus']);

        return [
            'cert_status' => $this->details[\SSL\STATUS]['value'],
            'cert_expires' => !empty($response['data']['dtcertexpire']) ? substr($response['data']['dtcertexpire'], 0, 10) : $this->account_details['next_due'],
        ];
    }

    public function downloadCertificate($format = 'PEM', $privateKey = '', $password = '', $bundle = false)
    {
        $multipart = [
            'bundle' => $bundle ? 'true' : 'false',
        ];
        if ($password !== '') {
            $multipart['pfxPassword'] = $password;
        }
        if ($privateKey !== '') {
            $tmp = tempnam(sys_get_temp_dir(), 'wnssl');
            file_put_contents($tmp, $privateKey);
            $multipart['privateKey'] = curl_file_create($tmp);
        }

        $response = $this->api()->download('ssl/v2/orders/' . rawurlencode($this->details[\SSL\ORDERID]['value']) . '/download/format/' . rawurlencode($format), [
            'multipart' => $multipart,
        ]);

        if (isset($tmp) && file_exists($tmp)) {
            @unlink($tmp);
        }

        if (!$this->success($response)) {
            return $this->failResponse($response);
        }

        return $response['data'];
    }

    protected function api()
    {
        if ($this->client === null) {
            $this->client = new WebnicSslApiClient(
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

    protected function ensureContacts()
    {
        if (!empty($this->details['adminContactId']['value']) && !empty($this->details['techContactId']['value'])) {
            return [
                'administratorContactId' => $this->details['adminContactId']['value'],
                'technicalContactId' => $this->details['techContactId']['value'],
                'shipmentContactId' => !empty($this->details['shipmentContactId']['value']) ? $this->details['shipmentContactId']['value'] : $this->details['adminContactId']['value'],
            ];
        }

        $contacts = isset($this->details[\SSL\CONTACTS]['value']) && is_array($this->details[\SSL\CONTACTS]['value']) ? $this->details[\SSL\CONTACTS]['value'] : [];
        $admin = !empty($contacts['admin']) ? $contacts['admin'] : $this->account_details;
        $tech = !empty($contacts['tech']) ? $contacts['tech'] : $admin;

        $payload = [
            'administrator' => $this->mapContact($admin),
            'technical' => $this->mapContact($tech),
        ];

        $response = $this->api()->post('ssl/v2/contact/create', $payload);
        if (!$this->success($response)) {
            return $this->failResponse($response);
        }

        $adminId = '';
        $techId = '';
        foreach ((array) $response['data'] as $item) {
            if (($item['contactType'] ?? '') === 'administrator') {
                $adminId = $item['contactId'];
            }
            if (($item['contactType'] ?? '') === 'technical') {
                $techId = $item['contactId'];
            }
        }

        if ($adminId === '') {
            $adminId = !empty($response['data']['administrator']['contactId']) ? $response['data']['administrator']['contactId'] : '';
        }
        if ($techId === '') {
            $techId = !empty($response['data']['technical']['contactId']) ? $response['data']['technical']['contactId'] : $adminId;
        }

        $this->details['adminContactId']['value'] = $adminId;
        $this->details['techContactId']['value'] = $techId;
        $this->details['shipmentContactId']['value'] = $adminId;

        return [
            'administratorContactId' => $adminId,
            'technicalContactId' => $techId,
            'shipmentContactId' => $adminId,
        ];
    }

    protected function mapContact(array $contact)
    {
        return [
            'company' => $contact['companyname'] ?? $contact['organization'] ?? trim(($contact['firstname'] ?? '') . ' ' . ($contact['lastname'] ?? '')),
            'firstName' => $contact['firstname'] ?? '',
            'lastName' => $contact['lastname'] ?? '',
            'address1' => $contact['address1'] ?? '',
            'address2' => $contact['address2'] ?? '',
            'city' => $contact['city'] ?? '',
            'state' => $contact['state'] ?? '',
            'countryCode' => strtoupper($contact['country'] ?? 'MY'),
            'zip' => $contact['postcode'] ?? '',
            'phoneNumber' => $this->normalizePhone($contact['phonenumber'] ?? $contact['phone'] ?? ''),
            'faxNumber' => $this->normalizePhone($contact['faxnumber'] ?? $contact['fax'] ?? ''),
            'email' => $contact['email'] ?? '',
        ];
    }

    protected function normalizePhone($phone)
    {
        $phone = trim((string) $phone);
        if ($phone === '') {
            return null;
        }
        if (strpos($phone, '+') === 0 && strpos($phone, '.') !== false) {
            return $phone;
        }
        $digits = preg_replace('/[^0-9]/', '', $phone);
        if ($digits === '') {
            return $phone;
        }
        if (strlen($digits) > 2) {
            return '+' . substr($digits, 0, 2) . '.' . substr($digits, 2);
        }
        return '+' . $digits;
    }

    protected function resolveProductKey()
    {
        if (!empty($this->details['productKey']['value'])) {
            return $this->details['productKey']['value'];
        }
        if (!empty($this->options['product_key']['value'])) {
            return $this->options['product_key']['value'];
        }
        if (!empty($this->product_details['options']['product_key'])) {
            return $this->product_details['options']['product_key'];
        }

        $this->addError('WebNIC SSL product key is not configured.');
        return '';
    }

    protected function resolveAuthType()
    {
        $dcv = strtolower((string) ($this->details[\SSL\DCV]['value'] ?? ''));
        if ($dcv === 'http') {
            return 'file';
        }
        if (in_array($dcv, ['dns', 'email', 'file'], true)) {
            return $dcv;
        }
        return 'email';
    }

    protected function buildApproverEmails($details)
    {
        $emails = [];
        if (is_array($details) && isset($details['commonName'])) {
            foreach ([$details] as $entry) {
                if (!empty($entry['email'])) {
                    $emails[] = $entry['email'];
                }
            }
        } elseif (is_array($details)) {
            foreach ($details as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                if (!empty($entry['value'])) {
                    $emails[] = $entry['value'];
                } elseif (!empty($entry['email'])) {
                    $emails[] = $entry['email'];
                }
            }
        }

        return array_values(array_unique(array_filter($emails)));
    }

    protected function storeDcvDetails(array $data)
    {
        $dcv = [];
        if (!empty($data['commonName'])) {
            $dcv[] = $data['commonName'];
        }
        if (!empty($data['san']) && is_array($data['san'])) {
            foreach ($data['san'] as $san) {
                $dcv[] = $san;
            }
        }
        if (!empty($data['authType'])) {
            $this->details[\SSL\DCV]['value'] = $data['authType'] === 'file' ? 'http' : $data['authType'];
        }
        $this->details[\SSL\DCV_DETAILS]['value'] = $dcv;
    }

    protected function getSanList()
    {
        $san = isset($this->details[\SSL\SAN]['value']) ? $this->details[\SSL\SAN]['value'] : [];
        if (is_string($san) && $san !== '') {
            $san = preg_split('/\s*,\s*/', $san);
        }
        return array_values(array_filter((array) $san));
    }

    protected function mapVendorStatus($orderStatus, $certStatus)
    {
        $orderStatus = strtoupper((string) $orderStatus);
        $certStatus = strtoupper((string) $certStatus);

        if (in_array($orderStatus, ['CANCELLED', 'REJECTED', 'REFUNDED'], true) || in_array($certStatus, ['CANCELLED', 'REVOKED'], true)) {
            return 'CANCELLED';
        }
        if ($certStatus === 'ACTIVE' || $orderStatus === 'COMPLETED') {
            return 'ISSUED';
        }
        if ($orderStatus === 'EXPIRED' || $certStatus === 'EXPIRED') {
            return 'EXPIRED';
        }
        if (in_array($orderStatus, ['IN_PROCESS', 'PROCESSED', 'PENDING_REISSUE'], true)) {
            return 'VALIDATION';
        }
        return 'PROCESSING';
    }

    protected function getProductCatalog()
    {
        $response = $this->api()->get('ssl/v2/products/list');
        if (!$this->success($response)) {
            return [];
        }
        return !empty($response['data']) ? $response['data'] : [];
    }

    protected function findProductCatalogItem($productKey)
    {
        foreach ($this->getProductCatalog() as $item) {
            if (!empty($item['productKey']) && $item['productKey'] === $productKey) {
                return $item;
            }
        }
        return [];
    }

    protected function extractProductValue($product, $key, $default = null)
    {
        if (is_array($product)) {
            if (isset($product[$key])) {
                return $product[$key];
            }
            if (isset($product['options'][$key])) {
                return $product['options'][$key];
            }
        }

        return $default;
    }
}
