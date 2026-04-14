<?php

class webnic_domains_controller extends HBController
{
    /**
     * @var webnic_domains
     */
    public $module = null;

    public function _default($params)
    {
        $modDir = strtolower($this->module->getModuleDirName());
        $this->template->pageTitle = Language::parse($this->module->getModName());
        $this->template->module_template_dir = APPDIR_MODULES . 'Domain' . DS . $modDir . DS . 'admin';
        $this->template->showtpl = 'default';
        $this->template->assign('moduleurl', Utilities::checkSecureURL(HBConfig::getConfig('InstallURL') . 'includes/modules/Domain/' . $modDir . '/admin/'));
        $this->template->assign('modulename', $this->module->getModuleName());
        $this->template->assign('modname', $this->module->getModName());
        $this->template->assign('moduleid', $this->module->getModuleId());
    }

    public function domaindetails($params)
    {
        $this->assignLayout($params);

        if (Controller::isAjax() && !empty($params['token_valid']) && !empty($params['load'])) {
            $this->loadDomain($params['id']);
            $this->template->showtpl = $this->adminTpl('ajax.domaindetails.tpl');
            $snapshot = $this->module->getAdminSnapshot();
            $this->template->assign('info', $snapshot['info']);
            $this->template->assign('status_sync', $snapshot['status']);
        }

        $this->template->assign('domainid', (int) $params['id']);
        $this->template->assign('domaininfo', isset($params['domain']) ? $params['domain'] : []);
    }

    public function domaincontacts($params)
    {
        $this->assignLayout($params);

        if (Controller::isAjax() && !empty($params['token_valid']) && !empty($params['load'])) {
            $this->loadDomain($params['id']);
            $this->template->showtpl = $this->adminTpl('ajax.domaincontacts.tpl');
            $this->template->assign('contacts', $this->module->getDomainContacts());
        }

        $this->template->assign('domainid', (int) $params['id']);
    }

    public function domaintransfer($params)
    {
        $this->assignLayout($params);

        if (Controller::isAjax() && !empty($params['token_valid']) && !empty($params['load'])) {
            $this->loadDomain($params['id']);
            $this->template->showtpl = $this->adminTpl('ajax.domaintransfer.tpl');
            $this->template->assign('transferinfo', $this->module->getTransferStatus());
        }

        $this->template->assign('domainid', (int) $params['id']);
    }

    public function domainstatus($params)
    {
        $this->assignLayout($params);

        if (Controller::isAjax() && !empty($params['token_valid']) && !empty($params['load'])) {
            $this->loadDomain($params['id']);
            $this->template->showtpl = $this->adminTpl('ajax.domainstatus.tpl');
            $snapshot = $this->module->getAdminSnapshot();
            $this->template->assign('info', $snapshot['info']);
            $this->template->assign('status_sync', $snapshot['status']);
        }

        $this->template->assign('domainid', (int) $params['id']);
    }

    public function domainaction($params)
    {
        $this->assignLayout($params);

        if (!(Controller::isAjax() && !empty($params['token_valid']) && !empty($params['load']))) {
            $this->template->assign('domainid', (int) $params['id']);
            return true;
        }

        $this->loadDomain($params['id']);
        $action = isset($params['ac']) ? (string) $params['ac'] : '';

        switch ($action) {
            case 'SendVerify':
                $recipient = $this->module->SendVerify();
                $this->template->showtpl = $this->adminTpl('ajax.sendverify.tpl');
                $this->template->assign('recipient', $recipient);
                break;

            case 'Certificate':
                $this->template->showtpl = $this->adminTpl('ajax.certificate.tpl');
                break;

            default:
                $this->template->showtpl = $this->adminTpl('ajax.domainactions.tpl');
                $message = $this->executeDomainAction($action);
                $this->template->assign('message', $message);
                break;
        }

        $this->template->assign('domainid', (int) $params['id']);

        return true;
    }

    public function downloadcertificate($params)
    {
        $this->loadDomain($params['id']);
        $lang = !empty($params['lang']) ? $params['lang'] : 'eng';
        $pdf = $this->module->DownloadCertificate($lang);
        if ($pdf === false) {
            Utilities::redirect('?cmd=domains&action=edit&id=' . (int) $params['id']);
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="webnic-domain-certificate-' . (int) $params['id'] . '-' . $lang . '.pdf"');
        echo $pdf;
        exit;
    }

    protected function executeDomainAction($action)
    {
        switch ($action) {
            case 'SyncContact':
                return $this->module->syncContacts() ? 'ok' : 'error';
            case 'ChangeEpp':
                return $this->module->ChangeEpp() ? 'ok' : 'error';
            case 'Lock':
                return $this->module->Lock() ? 'ok' : 'error';
            case 'Unlock':
                return $this->module->Unlock() ? 'ok' : 'error';
            case 'GetEpp':
                $result = $this->module->getEppCode();
                return $result ?: 'error';
            default:
                return 'Unsupported action';
        }
    }

    protected function loadDomain($domainId)
    {
        $this->module->getFromDB((int) $domainId);
        $this->module->setDomainConfig(HBLoader::LoadModel('Domains')->getDomainModuleConfig((int) $domainId));
    }

    protected function assignLayout($params)
    {
        $path = APPDIR_MODULES . 'Domain' . DS . strtolower($this->module->getModuleName()) . DS . 'admin' . DS . 'domaindetails.tpl';
        $this->template->assign('custom_template', $path);
        $this->template->assign('modulename', $this->module->getModuleName());
        $this->template->assign('modname', $this->module->getModName());
        $this->template->assign('moduleid', $this->module->getModuleId());
        if (isset($params['id'])) {
            $this->template->assign('domainid', (int) $params['id']);
        }
    }

    protected function adminTpl($tpl)
    {
        return APPDIR_MODULES . 'Domain' . DS . strtolower($this->module->getModuleName()) . DS . 'admin' . DS . $tpl;
    }
}