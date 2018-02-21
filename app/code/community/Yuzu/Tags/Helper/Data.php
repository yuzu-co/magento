<?php

/**
 * Data Helper
 *
 * @category    Yuzu
 * @package     Yuzu_Tags
 * @copyright   Copyright (c) 2015 Yuzu (http://www.yuzu.co)
 * @author      Jonathan Martin <jonathan@yuzu.co>
 */
class Yuzu_Tags_Helper_Data extends Mage_Core_Helper_Abstract
{
	public function getConfig($key, $store = false)
    {
        if (!$store) {
            $store = Mage::app()->getStore();
        }
        return Mage::getStoreConfig($key, $store);
    }

    public function setConfig($key, $value)
    {
        Mage::getModel('core/config')->saveConfig($key, $value);
    }
}