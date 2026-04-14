<?php

require_once dirname(__DIR__) . DS . 'webnic_common' . DS . 'lib' . DS . 'class.webnic_api_client.php';

class webnic_dns extends DNSModule
{
    protected $version = '1.0.0';
    protected $modname = 'WebNIC DNS';
    protected $description = 'Clean-room WebNIC DNS module for HostBill';
    protected $_repository = 'hosting_webnic_dns';

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
