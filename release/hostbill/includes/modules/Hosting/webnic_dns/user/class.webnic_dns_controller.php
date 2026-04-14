<?php

require_once APPDIR_LIBS . 'dnsfunctions' . DS . 'class.dnscontroller.php';

class webnic_dns_controller extends DNSClient_Controller
{
    /**
     * @var webnic_dns
     */
    public $module;

    public function beforeCall($params)
    {
        $this->domain_indent = DnsManage::COLUMN_RELATED_ID;
        parent::beforeCall($params);
    }

    public function accountdetails($params)
    {
        parent::accountdetails($params);
    }

    protected function action_add_domain($params)
    {
        $this->template->assign('require_ip', false);
        parent::action_add_domain($params);
    }
}