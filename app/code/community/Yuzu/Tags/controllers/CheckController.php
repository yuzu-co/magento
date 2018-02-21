<?php

/**
 * Check controller
 *
 * @category    Yuzu
 * @package     Yuzu_Tags
 * @copyright   Copyright (c) 2015 Yuzu (http://www.yuzu.co)
 * @author      Jonathan Martin <jonathan@yuzu.co>
 */
class Yuzu_Tags_CheckController extends Mage_Core_Controller_Front_Action
{
    public function statusAction()
    {
        $config = (array) Mage::getConfig()->getModuleConfig("Yuzu_Tags");
        $enabled = Mage::helper('yuzu_tags')->getConfig('yuzu_tags/general/enable');

        $response = array(
            'version' => $config['version'],
            'date' => time(),
            'timezone' => date_default_timezone_get(),
            'mage_version' => Mage::getVersion(),
            'php_version' => phpversion(),
            'enabled' => ($enabled === '1') ? true : false
        );

        $this->getResponse()->setHeader('Content-type', 'application/json');
        $this->getResponse()->setBody(json_encode($response));
    }
}