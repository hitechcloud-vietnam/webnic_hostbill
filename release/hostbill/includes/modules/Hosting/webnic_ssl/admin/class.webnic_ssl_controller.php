<?php

class webnic_ssl_controller extends HBController
{
    /**
     * @var webnic_ssl
     */
    public $module;

    public function productdetails($params)
    {
        if (isset($params['server_id']) && $params['server_id']) {
            $servers = HBLoader::LoadModel('Servers');
            $this->module->connect($servers->getServerDetails($params['server_id']));
        }

        $this->template->assign('customconfig', APPDIR_MODULES . 'Hosting' . DS . strtolower($this->module->getModuleName()) . DS . 'admin' . DS . 'myproductconfig.tpl');
        $this->template->assign('catalog', $this->module->getProductCatalogOptions());
        $this->template->assign('test_connection_result', $this->buildConnectionResult());
    }

    public function accountdetails($params)
    {
        $this->setupModule($params['id']);

        if (!empty($params['edo']) && $params['edo'] === 'changedcv') {
            $dcv = isset($params['dcv']) ? $params['dcv'] : '';
            $email = isset($params['dcv_email']) ? $params['dcv_email'] : '';
            if ($this->module->changeDCV($dcv, $email)) {
                Engine::addInfo('DCV method updated successfully');
            }
            return Utilities::redirect('?cmd=accounts&action=edit&id=' . (int) $params['id']);
        }

        if (!empty($params['resetdcv'])) {
            if ($this->module->ResendDCVEmail()) {
                Engine::addInfo('DCV email resent successfully');
            }
            return Utilities::redirect('?cmd=accounts&action=edit&id=' . (int) $params['id']);
        }

        $this->template->assign('custom_template', APPDIR_MODULES . 'Hosting' . DS . strtolower($this->module->getModuleName()) . DS . 'admin' . DS . 'details.tpl');

        $cert = $this->module->getUiCertificateDetails();
        $dcvType = strtolower((string) ($cert['dcv'] ?? ''));
        $dcv = [
            'type' => $dcvType,
            'details' => [],
        ];

        if ($dcvType === 'email') {
            $dcv['details'] = [];
            foreach ((array) $cert['dcv_details'] as $index => $email) {
                if (is_array($email) && isset($email['name'], $email['value'])) {
                    $dcv['details'][$email['name']] = ['name' => $email['value']];
                } else {
                    $dcv['details']['Email ' . ($index + 1)] = ['name' => is_scalar($email) ? $email : json_encode($email)];
                }
            }
        } elseif ($dcvType === 'dns') {
            $dcv['details'] = $this->module->CertDcvDns();
        } elseif ($dcvType === 'http' || $dcvType === 'https') {
            $dcv['details'] = $this->module->CertDcvHttp();
        }

        $this->template->assign('cert', $cert);
        $this->template->assign('dcv', $dcv);
        $this->template->assign('approveremails', $this->module->getApproverEmailChoices());
        $this->template->assign('dcv_method', $dcvType);
    }

    protected function setupModule($serviceId)
    {
        $accounts = HBLoader::LoadModel('Accounts');
        $account = $accounts->getAccount($serviceId);
        $accountConfig = $accounts->getAccountModuleConfig($serviceId);
        $servers = HBLoader::LoadModel('Servers');
        $server = $servers->getServerDetails($account['server_id']);

        $this->module->connect($server);
        $this->module->setAccountConfig($accountConfig);
        $this->module->setAccount($account);

        return true;
    }

    protected function buildConnectionResult()
    {
        $result = ['result' => 'Unknown', 'error' => ''];
        if ($this->module->testConnection()) {
            $result['result'] = 'Success';
        } else {
            $result['result'] = 'Failure';
            $result['error'] = 'Unable to authenticate against WebNIC API';
        }

        return $result;
    }
}