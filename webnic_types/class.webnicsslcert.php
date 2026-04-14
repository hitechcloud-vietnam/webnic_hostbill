<?php

require_once APPDIR . 'types' . DS . 'class.ssltype.php';

class WebnicSSLCert extends SSLType
{
    protected $featured_modules = ['class.webnic_ssl.php'];
    protected $related_modules = ['class.webnic_ssl.php'];
    protected $typeTplDir;
    protected $ajaxTpl;
    protected $module = false;

    public function getCSRServers($prod)
    {
        $module = $this->getModule($prod);
        if (!$module && is_null($this->module)) {
            return parent::getCSRServers($prod);
        }

        if (is_callable([$this->module, 'getCSRServers'])) {
            return $this->module->getCSRServers($prod);
        }

        return parent::getCSRServers($prod);
    }

    public function controller($data, &$smarty, $account = [])
    {
        if (!empty($account[0]) && is_array($account[0])) {
            foreach ($account as $acc) {
                $key = array_search($acc['id'], $data);
                if ($key !== false && !empty($key)) {
                    $account = $acc;
                    break;
                }
            }
        }

        $this->getModule($account, true);
        $this->typeTplDir = MAINDIR . 'includes' . DS . 'types' . DS . 'webnicsslcert' . DS;

        return parent::controller($data, $smarty, $account);
    }
}
