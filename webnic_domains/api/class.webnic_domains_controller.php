<?php

class webnic_domains_controller extends HBController
{
    /**
     * @var webnic_domains
     */
    public $module;

    public function snapshot($params)
    {
        $this->loadDomain($params);
        $snapshot = $this->module->getAdminSnapshot();

        return [true, [
            'snapshot' => $snapshot,
        ]];
    }

    public function actions($params)
    {
        $this->loadDomain($params);
        $action = isset($params['ac']) ? $params['ac'] : '';
        $result = null;

        switch ($action) {
            case 'lock':
                $result = $this->module->Lock();
                break;
            case 'unlock':
                $result = $this->module->Unlock();
                break;
            case 'syncContacts':
                $result = $this->module->syncContacts();
                break;
            case 'sendVerify':
                $result = $this->module->SendVerify();
                break;
            case 'resetEpp':
                $result = $this->module->ChangeEpp();
                break;
            default:
                return [false, ['error' => 'Unsupported action']];
        }

        return [true, ['result' => $result]];
    }

    protected function loadDomain($params)
    {
        $domainId = isset($params['domain_id']) ? (int) $params['domain_id'] : (isset($params['id']) ? (int) $params['id'] : 0);
        if (!$domainId) {
            throw new Exception('Missing domain_id');
        }

        $this->module->getFromDB($domainId);
        $this->module->setDomainConfig(HBLoader::LoadModel('Domains')->getDomainModuleConfig($domainId));
    }
}