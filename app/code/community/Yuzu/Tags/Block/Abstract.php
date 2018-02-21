<?php

/**
 * Abstract block
 *
 * @category    Yuzu
 * @package     Yuzu_Tags
 * @copyright   Copyright (c) 2015 Yuzu (http://www.yuzu.co)
 * @author      Jonathan Martin <jonathan@yuzu.co>
 */
abstract class Yuzu_Tags_Block_Abstract extends Mage_Core_Block_Template
{
    public function isEnabled()
    {
        return Mage::helper('yuzu_tags')->getConfig('yuzu_tags/general/enable');
    }
}