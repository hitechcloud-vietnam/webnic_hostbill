<?php

require_once dirname(__DIR__) . DS . 'webnic_common' . DS . 'lib' . DS . 'class.webnic_api_client.php';

class webnic_domains extends DomainModule implements DomainLookupInterface, DomainPremiumInterface, DomainWhoisInterface, DomainSuggestionsInterface, DomainModuleNameservers, DomainModulePrivacy, DomainModuleContacts, DomainModuleAuth, DomainModuleLock
{
    protected $version = '1.0.0';
    protected $modname = 'WebNIC Domains';
    protected $description = 'Clean-room WebNIC domain registrar module for HostBill';
    protected $_repository = 'domain_webnic_domains';

    protected $configuration = [
        'Username' => [
            'value' => '',
            'type' => 'input',
            'default' => false,
        ],
        'Password' => [
            'value' => '',
            'type' => 'password',
            'default' => false,
        ],
        'OTE Environment' => [
            'value' => '',
            'type' => 'check',
            'default' => false,
        ],
        'Registrant User ID' => [
            'value' => '',
            'type' => 'input',
            'default' => false,
        ],
        'Default WHOIS Privacy' => [
            'value' => '',
            'type' => 'check',
            'default' => false,
        ],
        'Default Proxy' => [
            'value' => '',
            'type' => 'check',
            'default' => false,
        ],
    ];

    protected $commands = [
        'Register',
        'Transfer',
        'Renew',
        'Delete',
        'EppCode',
        'ContactInfo',
        'Lock',
        'Unlock',
        'SendVerify',
        'DownloadCertificate',
    ];

    protected $clientCommands = [
        'EppCode',
        'ContactInfo',
        'Lock',
        'Unlock',
        'SendVerify',
        'DownloadCertificate',
    ];

    protected $proxyTlds = ['my', 'com.my', 'net.my', 'org.my', 'sg', 'com.sg', 'asia', 'kr', 'co.kr', 'it', 'de', 'jp', 'id', 'co.id', 'web.id'];
    protected $client;

    public function connect($server = [])
    {
        parent::connect($server);
        $this->client = null;
    }

    public function Register()
    {
        $contactIds = $this->ensureDomainContacts();
        if ($contactIds === false) {
            return false;
        }

        $payload = [
            'domainName' => $this->name,
            'term' => (int) $this->period,
            'nameservers' => $this->getRequestedNameservers(),
            'registrantContactId' => $contactIds['registrant'],
            'administratorContactId' => $contactIds['administrator'],
            'technicalContactId' => $contactIds['technical'],
            'billingContactId' => $contactIds['billing'],
            'registrantUserId' => $this->getRegistrantUserId(),
            'addons' => [
                'proxy' => $this->shouldEnableProxy(),
                'whoisPrivacy' => $this->shouldEnableWhoisPrivacy(),
            ],
        ];

        if (!empty($this->options['premium'])) {
            $payload['domainType'] = 'premium';
        }

        $response = $this->api()->post('domain/v2/register', $payload);
        if (!$this->success($response)) {
            return $this->failResponse($response);
        }

        $this->storeContactIds($contactIds);
        $this->logAction([
            'action' => 'Register Domain',
            'result' => true,
            'change' => $payload,
            'error' => false,
        ]);
        $this->addDomain(empty($response['data']['pendingOrder']) ? 'Active' : 'Pending Registration');
        if (!empty($response['data']['dtexpire'])) {
            $this->expires = substr($response['data']['dtexpire'], 0, 10);
        }

        return true;
    }

    public function Renew()
    {
        $payload = [
            'domainName' => $this->name,
            'term' => (int) $this->period,
        ];
        if (!empty($this->options['premium'])) {
            $payload['domainType'] = 'premium';
        }
        if (!empty($this->expires)) {
            $payload['domainExpiryDate'] = $this->expires;
        }

        $response = $this->api()->post('domain/v2/renew', $payload);
        if (!$this->success($response)) {
            return $this->failResponse($response);
        }

        $this->addPeriod();
        $this->logAction([
            'action' => 'Renew Domain',
            'result' => true,
            'change' => $payload,
            'error' => false,
        ]);

        return true;
    }

    public function Transfer()
    {
        $contactIds = $this->ensureDomainContacts();
        if ($contactIds === false) {
            return false;
        }

        $payload = [
            'domainName' => $this->name,
            'authInfo' => isset($this->options['epp_code']) ? $this->options['epp_code'] : $this->details['epp_code'],
            'registrantUserId' => $this->getRegistrantUserId(),
            'registrantContactId' => $contactIds['registrant'],
            'administratorContactId' => $contactIds['administrator'],
            'technicalContactId' => $contactIds['technical'],
            'billingContactId' => $contactIds['billing'],
            'subscribeProxy' => $this->shouldEnableProxy(),
        ];
        if (!empty($this->options['premium'])) {
            $payload['domainType'] = 'premium';
        }

        $response = $this->api()->post('domain/v2/transfer-in', $payload);
        if (!$this->success($response)) {
            return $this->failResponse($response);
        }

        $extended = $this->details['extended'];
        $extended['webnic_transfer'] = $response['data'];
        $this->storeContactIds($contactIds, $extended);
        $this->logAction([
            'action' => 'Transfer Domain',
            'result' => true,
            'change' => $payload,
            'error' => false,
        ]);

        return true;
    }

    public function Delete()
    {
        $response = $this->api()->delete('domain/v2/delete', ['domainName' => $this->name]);
        if (!$this->success($response)) {
            return $this->failResponse($response);
        }

        $this->logAction([
            'action' => 'Delete Domain',
            'result' => true,
            'change' => $this->name,
            'error' => false,
        ]);

        return true;
    }

    public function lookupDomain($sld, $tld, $settings = [])
    {
        $name = rtrim($sld, '.') . '.' . ltrim($tld, '.');
        $response = $this->api()->get('domain/v2/query', ['domainName' => $name]);
        $result = ['available' => false];

        if (!$this->success($response) || empty($response['data'])) {
            return $result;
        }

        $result['available'] = !empty($response['data']['available']);
        if (!empty($response['data']['premium']) && !empty($response['data']['premiumInfo'])) {
            $result['premium'] = [
                'register' => $response['data']['premiumInfo']['registerPrice'],
                'renew' => $response['data']['premiumInfo']['renewPrice'],
                'transfer' => $response['data']['premiumInfo']['transferPrice'],
                'currency' => $response['data']['premiumInfo']['currency'],
            ];
        }

        return $result;
    }

    public function whoisDomain($sld, $tld, $settings = [])
    {
        $name = rtrim($sld, '.') . '.' . ltrim($tld, '.');
        $response = $this->api()->get('domain/v2/whois', ['domainName' => $name]);

        if (!$this->success($response)) {
            return false;
        }

        return $response['data'];
    }

    public function suggestDomains($sld, $tld, $settings = [])
    {
        $name = rtrim($sld, '.') . '.' . ltrim($tld, '.');
        $response = $this->api()->get('domain/v2/top-domain-available-list', ['domainName' => $name]);

        if (!$this->success($response)) {
            return [];
        }

        return $response['data'];
    }

    public function getNameServers()
    {
        $info = $this->domainInfo();
        if ($info === false) {
            return false;
        }

        return isset($info['nameservers']) ? $info['nameservers'] : [];
    }

    public function updateNameServers()
    {
        $payload = [
            'nameservers' => $this->getRequestedNameservers(),
        ];
        $response = $this->api()->put('domain/v2/dns?domainName=' . rawurlencode($this->name), $payload);

        if (!$this->success($response)) {
            return $this->failResponse($response);
        }

        $this->logAction([
            'action' => 'Update Nameservers',
            'result' => true,
            'change' => $payload['nameservers'],
            'error' => false,
        ]);

        return true;
    }

    public function getIDProtection()
    {
        $info = $this->domainInfo();
        if ($info === false) {
            return false;
        }

        return !empty($info['whoisPrivacy']);
    }

    public function updateIDProtection()
    {
        $active = !$this->getIDProtection();
        $response = $this->api()->put('domain/v2/whois-privacy/toggle', [
            'domainName' => $this->name,
            'active' => (bool) $active,
        ]);

        if (!$this->success($response)) {
            return $this->failResponse($response);
        }

        $this->logAction([
            'action' => 'Update Whois Privacy',
            'result' => true,
            'change' => $active ? 'enabled' : 'disabled',
            'error' => false,
        ]);

        return true;
    }

    public function getRegistrarLock()
    {
        $info = $this->domainInfo();
        if ($info === false) {
            return false;
        }

        return isset($info['status']) && $info['status'] === 'transfer_protected';
    }

    public function updateRegistrarLock()
    {
        return $this->getRegistrarLock() ? $this->Unlock() : $this->Lock();
    }

    public function Lock()
    {
        return $this->setDomainStatus('transfer_protected', 'Lock Domain');
    }

    public function Unlock()
    {
        return $this->setDomainStatus('active', 'Unlock Domain');
    }

    public function getEppCode()
    {
        $response = $this->api()->post('domain/v2/auth-info/send?domainName=' . rawurlencode($this->name), [
            'domainName' => $this->name,
        ]);

        if (!$this->success($response)) {
            return $this->failResponse($response);
        }

        $recipient = isset($response['data']['recipient']) ? $response['data']['recipient'] : '';
        $this->logAction([
            'action' => 'Send EPP Code',
            'result' => true,
            'change' => $recipient,
            'error' => false,
        ]);

        return $recipient ? 'Authorization code sent to ' . $recipient : true;
    }

    public function ChangeEpp()
    {
        $response = $this->api()->post('domain/v2/auth-info/reset?domainName=' . rawurlencode($this->name), [
            'domainName' => $this->name,
        ]);

        if (!$this->success($response)) {
            return $this->failResponse($response);
        }

        $this->logAction([
            'action' => 'Reset EPP Code',
            'result' => true,
            'change' => $this->name,
            'error' => false,
        ]);

        return true;
    }

    public function SendVerify()
    {
        $response = $this->api()->post('domain/v2/resend-verification-email?domainName=' . rawurlencode($this->name), [
            'domainName' => $this->name,
        ]);

        if (!$this->success($response)) {
            return $this->failResponse($response);
        }

        return isset($response['data']['recipient']) ? $response['data']['recipient'] : true;
    }

    public function DownloadCertificate($lang = 'eng')
    {
        $response = $this->api()->get('domain/v2/download/certificate', [
            'domainName' => $this->name,
            'lang' => $lang,
        ]);

        if (!$this->success($response)) {
            return $this->failResponse($response);
        }

        return $response['data'];
    }

    public function testConnection()
    {
        if (!$this->getRegistrantUserId()) {
            return false;
        }

        return $this->api()->testConnection();
    }

    public function getDomainInfo()
    {
        return $this->domainInfo();
    }

    public function getDomainContacts()
    {
        return $this->getContactInfo();
    }

    public function syncContacts()
    {
        return $this->updateContactInfo();
    }

    public function getTransferStatus()
    {
        $extended = isset($this->details['extended']) && is_array($this->details['extended']) ? $this->details['extended'] : [];
        $transfer = !empty($extended['webnic_transfer']) ? $extended['webnic_transfer'] : [];

        if (!empty($transfer['id'])) {
            $response = $this->api()->get('domain/v2/transfer-in/status/' . rawurlencode($transfer['id']));
            if ($this->success($response) && !empty($response['data'])) {
                return $response['data'];
            }
        }

        $response = $this->api()->get('domain/v2/transfer-in/status');
        if (!$this->success($response) || empty($response['data']) || !is_array($response['data'])) {
            return $transfer;
        }

        foreach ($response['data'] as $item) {
            if (!empty($item['domainName']) && strcasecmp($item['domainName'], $this->name) === 0) {
                return $item;
            }
        }

        return $transfer;
    }

    public function getAdminSnapshot()
    {
        $info = $this->getDomainInfo();

        return [
            'domain' => $this->name,
            'info' => $info,
            'contacts' => $this->getDomainContacts(),
            'transfer' => $this->getTransferStatus(),
            'status' => $this->synchInfo(),
        ];
    }

    public function getContactInfo()
    {
        $contactIds = $this->getStoredContactIds();
        if (!$contactIds) {
            $info = $this->domainInfo();
            if (!empty($info['contactId']) && is_array($info['contactId'])) {
                $contactIds = [
                    'registrant' => $info['contactId']['registrantContactId'],
                    'administrator' => $info['contactId']['administratorContactId'],
                    'technical' => $info['contactId']['technicalContactId'],
                    'billing' => $info['contactId']['billingContactId'],
                ];
            }
        }

        if (!$contactIds) {
            return false;
        }

        $contacts = [];
        foreach ($contactIds as $role => $contactId) {
            if (!$contactId) {
                continue;
            }
            $response = $this->api()->get('domain/v2/contact/query', ['contactId' => $contactId]);
            if ($this->success($response) && !empty($response['data']['details'])) {
                $contacts[$role] = $this->mapDomainContactFromApi($response['data']['details']);
                $contacts[$role]['contact_id'] = $contactId;
            }
        }

        return $contacts;
    }

    public function updateContactInfo()
    {
        $contactIds = $this->ensureDomainContacts(true);
        if ($contactIds === false) {
            return false;
        }

        $this->logAction([
            'action' => 'Update Contact Info',
            'result' => true,
            'change' => $contactIds,
            'error' => false,
        ]);

        return true;
    }

    public function synchInfo()
    {
        $info = $this->domainInfo();
        if ($info === false) {
            return false;
        }

        $status = 'Active';
        if (!empty($info['status'])) {
            switch ($info['status']) {
                case 'pendingCreate':
                    $status = 'Pending Registration';
                    break;
                case 'pendingDelete':
                    $status = 'Cancelled';
                    break;
                case 'clientHold':
                    $status = 'Suspended';
                    break;
                case 'inactive':
                case 'Inactive':
                    $status = 'Expired';
                    break;
            }
        }

        return [
            'status' => $status,
            'ns' => isset($info['nameservers']) ? $info['nameservers'] : [],
            'expires' => !empty($info['dtexpire']) ? substr($info['dtexpire'], 0, 10) : $this->expires,
            'idprotection' => !empty($info['whoisPrivacy']),
        ];
    }

    protected function api()
    {
        if ($this->client === null) {
            $this->client = new WebnicApiClient(
                $this->configuration['Username']['value'],
                $this->configuration['Password']['value'],
                !empty($this->configuration['OTE Environment']['value'])
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

    protected function domainInfo()
    {
        $response = $this->api()->get('domain/v2/info', ['domainName' => $this->name]);
        if (!$this->success($response)) {
            $this->failResponse($response);
            return false;
        }

        return $response['data'];
    }

    protected function ensureDomainContacts($updateExisting = false)
    {
        $stored = $this->getStoredContactIds();
        $result = [];
        $createPayload = [];
        $roleMap = [
            'registrant' => 'registrant',
            'admin' => 'administrator',
            'tech' => 'technical',
            'billing' => 'billing',
        ];

        foreach ($roleMap as $sourceRole => $apiRole) {
            $contact = isset($this->domain_contacts[$sourceRole]) ? $this->domain_contacts[$sourceRole] : $this->client_data;
            $payload = $this->mapDomainContactToApi($contact);
            $existingId = isset($stored[$apiRole]) ? $stored[$apiRole] : null;

            if ($existingId) {
                if ($updateExisting) {
                    $response = $this->api()->post('domain/v2/contact/modify', [
                        'contactId' => $existingId,
                        'details' => $payload,
                    ]);
                    if (!$this->success($response)) {
                        return $this->failResponse($response);
                    }
                }
                $result[$apiRole] = $existingId;
                continue;
            }

            $createPayload[$apiRole] = $payload;
        }

        if (!empty($createPayload)) {
            $response = $this->api()->post('domain/v2/contact/create', $createPayload);
            if (!$this->success($response)) {
                return $this->failResponse($response);
            }
            foreach ((array) $response['data'] as $createdContact) {
                if (!empty($createdContact['contactType']) && !empty($createdContact['contactId'])) {
                    $result[$createdContact['contactType']] = $createdContact['contactId'];
                }
            }
        }

        foreach ($roleMap as $unused => $apiRole) {
            if (empty($result[$apiRole]) && !empty($stored[$apiRole])) {
                $result[$apiRole] = $stored[$apiRole];
            }
        }

        if (count(array_filter($result)) < 4) {
            $this->addError('Unable to prepare all required WebNIC domain contacts.');
            return false;
        }

        return $result;
    }

    protected function mapDomainContactToApi(array $contact)
    {
        $isOrganization = !empty($contact['companyname']) || !empty($contact['organization']) || !empty($contact['orgname']);
        $company = '';
        if (!empty($contact['companyname'])) {
            $company = $contact['companyname'];
        } elseif (!empty($contact['organization'])) {
            $company = $contact['organization'];
        } elseif (!empty($contact['orgname'])) {
            $company = $contact['orgname'];
        } else {
            $company = trim(($contact['firstname'] ?? '') . ' ' . ($contact['lastname'] ?? ''));
        }

        $customFields = [];
        foreach (['organizationType', 'organizationRegistrationNumber', 'nexus', 'individualType', 'identificationNumber', 'dateOfBirth', 'gender', 'faxNumber'] as $field) {
            if (!empty($contact[$field])) {
                $customFields[$field] = $contact[$field];
            }
        }
        if (!empty($contact['customFields']) && is_array($contact['customFields'])) {
            $customFields = array_merge($customFields, array_filter($contact['customFields']));
        }

        return [
            'category' => $isOrganization ? 'organization' : 'individual',
            'company' => $company,
            'firstName' => $contact['firstname'] ?? '',
            'lastName' => $contact['lastname'] ?? '',
            'address1' => $contact['address1'] ?? '',
            'address2' => $contact['address2'] ?? '',
            'city' => $contact['city'] ?? '',
            'state' => $contact['state'] ?? '',
            'countryCode' => strtoupper($contact['country'] ?? $contact['countrycode'] ?? 'MY'),
            'zip' => $contact['postcode'] ?? $contact['zip'] ?? '',
            'phoneNumber' => $this->normalizePhone($contact['phonenumber'] ?? $contact['phone'] ?? ''),
            'faxNumber' => $this->normalizePhone($contact['faxnumber'] ?? $contact['fax'] ?? ''),
            'email' => $contact['email'] ?? '',
            'customFields' => !empty($customFields) ? $customFields : (object) [],
        ];
    }

    protected function mapDomainContactFromApi(array $details)
    {
        return [
            'firstname' => $details['firstName'] ?? '',
            'lastname' => $details['lastName'] ?? '',
            'companyname' => $details['company'] ?? '',
            'address1' => $details['address1'] ?? '',
            'address2' => $details['address2'] ?? '',
            'city' => $details['city'] ?? '',
            'state' => $details['state'] ?? '',
            'postcode' => $details['zip'] ?? '',
            'country' => $details['countryCode'] ?? '',
            'phonenumber' => $details['phoneNumber'] ?? '',
            'faxnumber' => $details['faxNumber'] ?? '',
            'email' => $details['email'] ?? '',
            'customFields' => isset($details['customFields']) ? $details['customFields'] : [],
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

        if (strpos($phone, '+') === 0) {
            if (strlen($digits) > 2) {
                return '+' . substr($digits, 0, 2) . '.' . substr($digits, 2);
            }
            return '+' . $digits;
        }

        if (strlen($digits) > 2) {
            return '+' . substr($digits, 0, 2) . '.' . substr($digits, 2);
        }

        return '+' . $digits;
    }

    protected function getRequestedNameservers()
    {
        $nameservers = [];
        for ($i = 1; $i <= 13; $i++) {
            if (!empty($this->options['ns' . $i])) {
                $nameservers[] = trim($this->options['ns' . $i]);
            }
        }

        if (count($nameservers) >= 2) {
            return array_values(array_unique($nameservers));
        }

        $response = $this->api()->get('domain/v2/dns/default', ['domainName' => $this->name]);
        if ($this->success($response) && !empty($response['data'])) {
            return $response['data'];
        }

        return $nameservers;
    }

    protected function getRegistrantUserId()
    {
        $value = trim((string) $this->configuration['Registrant User ID']['value']);
        if ($value !== '') {
            return $value;
        }
        $this->addError('Registrant User ID is required in module configuration.');
        return '';
    }

    protected function shouldEnableWhoisPrivacy()
    {
        if (isset($this->details['idprotection'])) {
            return (bool) $this->details['idprotection'];
        }

        return !empty($this->configuration['Default WHOIS Privacy']['value']);
    }

    protected function shouldEnableProxy()
    {
        $tld = isset($this->options['tld']) ? ltrim($this->options['tld'], '.') : '';
        if (!in_array($tld, $this->proxyTlds, true)) {
            return false;
        }

        if (!empty($this->details['extended']['webnicproxy'])) {
            return true;
        }

        return !empty($this->configuration['Default Proxy']['value']);
    }

    protected function getStoredContactIds()
    {
        $extended = isset($this->details['extended']) && is_array($this->details['extended']) ? $this->details['extended'] : [];
        if (!empty($extended['webnic_contact_ids']) && is_array($extended['webnic_contact_ids'])) {
            return $extended['webnic_contact_ids'];
        }

        return false;
    }

    protected function storeContactIds(array $contactIds, array $extended = null)
    {
        $extendedData = $extended === null ? (isset($this->details['extended']) && is_array($this->details['extended']) ? $this->details['extended'] : []) : $extended;
        $extendedData['webnic_contact_ids'] = $contactIds;
        $this->details['extended'] = $extendedData;

        if ($this->domain_id) {
            $this->updateExtended($extendedData, 'Store WebNIC Contact IDs');
        }
    }

    protected function setDomainStatus($status, $action)
    {
        $response = $this->api()->put('domain/v2/status?domainName=' . rawurlencode($this->name) . '&status=' . rawurlencode($status), [
            'domainName' => $this->name,
            'status' => $status,
        ]);
        if (!$this->success($response)) {
            return $this->failResponse($response);
        }

        $this->logAction([
            'action' => $action,
            'result' => true,
            'change' => $status,
            'error' => false,
        ]);

        return true;
    }
}
