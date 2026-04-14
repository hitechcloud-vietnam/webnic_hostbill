<?php

require_once dirname(__DIR__) . DS . 'webnic_common' . DS . 'lib' . DS . 'class.webnic_api_client.php';

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
            $this->client = new WebnicApiClient(
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
            $details = [$details['commonName']];
        }
        foreach ((array) $details as $entry) {
            if (is_array($entry) && !empty($entry['name']) && !empty($entry['value'])) {
                $emails[] = [
                    'name' => $entry['name'],
                    'email' => $entry['value'],
                ];
            }
        }
        return $emails;
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
