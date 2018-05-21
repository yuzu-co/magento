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

    public function getEncryptionKey()
    {
        return (string) Mage::getConfig()->getNode('global/crypt/key');
    }

    public function setConfig($key, $value)
    {
        Mage::getModel('core/config')->saveConfig($key, $value);
    }

    public function getBrowserLanguage()
    {
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            foreach (explode(",", strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE'])) as $accept) {
                if (preg_match("!([a-z-]+)(;q=([0-9\\.]+))?!", trim($accept), $found)) {
                    $langs[] = $found[1];
                    $quality[] = (isset($found[3]) ? (float) $found[3] : 1.0);
                }
            }
            array_multisort($quality, SORT_NUMERIC, SORT_DESC, $langs);
            $stores = Mage::app()->getStores(false, true);
            foreach ($langs as $lang) {
                $lang = substr($lang, 0, 2);
                return $lang;
            }
        }
        return null;
    }
}