<?php

class webnic_ssl_controller extends HBController
{
    /**
     * @var webnic_ssl
     */
    public $module;

    public function details($params)
    {
        $this->loadAccount($params);
        return [true, ['certificate' => $this->module->getUiCertificateDetails()]];
    }

    public function dcv($params)
    {
        $this->loadAccount($params);
        $dcv = isset($params['dcv']) ? $params['dcv'] : '';
        $email = isset($params['dcv_email']) ? $params['dcv_email'] : '';
        $result = $this->module->changeDCV($dcv, $email);

        return [true, ['result' => $result]];
    }

    protected function loadAccount($params)
    {
        $serviceId = isset($params['account_id']) ? (int) $params['account_id'] : (isset($params['id']) ? (int) $params['id'] : 0);
        if (!$serviceId) {
            throw new Exception('Missing account_id');
        }

        $accounts = HBLoader::LoadModel('Accounts');
        $account = $accounts->getAccount($serviceId);
        $config = $accounts->getAccountModuleConfig($serviceId);
        $servers = HBLoader::LoadModel('Servers');
        $server = $servers->getServerDetails($account['server_id']);

        $this->module->connect($server);
        $this->module->setAccountConfig($config);
        $this->module->setAccount($account);
    }
}