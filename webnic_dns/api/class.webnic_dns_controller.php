<?php

class webnic_dns_controller extends HBController
{
    /**
     * @var webnic_dns
     */
    public $module;

    public function summary($params)
    {
        $this->loadServer($params);
        return [true, ['summary' => $this->module->getAppConfigSummary()]];
    }

    public function zones($params)
    {
        $this->loadServer($params);
        return [true, ['zones' => $this->module->listZones()]];
    }

    protected function loadServer($params)
    {
        $serverId = isset($params['server_id']) ? (int) $params['server_id'] : 0;
        if (!$serverId) {
            throw new Exception('Missing server_id');
        }

        $servers = HBLoader::LoadModel('Servers');
        $server = $servers->getServerDetails($serverId);
        $this->module->connect($server);
    }
}