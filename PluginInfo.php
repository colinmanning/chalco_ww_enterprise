<?php

require_once BASEDIR.'/server/interfaces/plugins/EnterprisePlugin.class.php';
require_once BASEDIR.'/server/interfaces/plugins/PluginInfoData.class.php';

define('COPYRIGHT_HYPHEN', '(c) 2014 Hyphen Italia All rights reserved.');

class Chalco_EnterprisePlugin extends EnterprisePlugin {

   public function getPluginInfo() {
      $info = new PluginInfoData();
      $info->DisplayName = 'Chalco';
      $info->Version = '1.0.1.0';
      $info->Description = 'Access Chalco DAM from Woodwing Content Station';
      $info->Copyright = COPYRIGHT_HYPHEN;
      return $info;
   }

   final public function getConnectorInterfaces() {
      return array('ContentSource_EnterpriseConnector');
   }

    public function runInstallation() {
        $configFile = dirname(__FILE__) . '/config.php';
        require_once $configFile;
    }
}
