<?php

require_once APPDIR_LIBS . 'dnsfunctions' . DS . 'class.dnstemplates_controller.php';

class webnic_dns_controller extends DNSTemplates_Controller
{
    /**
     * @var webnic_dns
     */
    public $module;

    public function beforeCall($params)
    {
        if (isset($params['account']['server_id'])) {
            $servers = HBLoader::LoadModel('Servers');
            $this->module->connect($servers->getServerDetails($params['account']['server_id']));
        }

        parent::beforeCall($params);
    }

    public function productdetails($params)
    {
        $servers = HBLoader::LoadModel('Servers');
        if (isset($params['server_id']) && is_numeric($params['server_id'])) {
            $this->module->connect($servers->getServerDetails($params['server_id']));
        }

        parent::productdetails($params);
        $this->template->assign('customconfig', APPDIR_MODULES . $this->module->getModuleType() . DS . strtolower($this->module->getModuleName()) . DS . 'templates' . DS . 'productconfig.tpl');
        $this->template->assign('extra_tabs', ['DNS Templates' => MAINDIR . 'includes/types/_common/dns_templates.tpl']);
        $this->template->assign('app_summary', $this->module->getAppConfigSummary());

        if (!empty($params['make']) && $params['make'] === 'loadoptions') {
            return $this->load_options($params);
        }

        return true;
    }

    public function appdetails($params)
    {
        parent::productdetails($params);
        $this->template->assign('custom_template', APPDIR_MODULES . $this->module->getModuleType() . DS . strtolower($this->module->getModuleName()) . DS . 'templates' . DS . 'appconfig.tpl');
        $this->template->assign('app_summary', $this->module->getAppConfigSummary());
        return true;
    }

    protected function load_options($params)
    {
        if (empty($params['opt']) || empty($params['server_id'])) {
            return false;
        }

        $servers = HBLoader::LoadModel('Servers');
        $appDetails = $servers->getServerDetails($params['server_id']);
        if (empty($appDetails)) {
            return false;
        }

        $this->module->connect($appDetails);
        $summary = $this->module->getAppConfigSummary();
        $this->template = HBLoader::LoadComponent('template/ApiResponse');

        $requested = is_array($params['opt']) ? $params['opt'] : [$params['opt']];
        foreach ($requested as $opt) {
            if ($opt === 'nameservers') {
                $this->template->assign('nameservers', $summary['nameservers']);
            }
            if ($opt === 'supported_records') {
                $this->template->assign('supported_records', $summary['supported_records']);
            }
            if ($opt === 'zone_limit') {
                $this->template->assign('zone_limit', $summary['zone_limit']);
            }
        }

        $this->template->show();
        return true;
    }
}