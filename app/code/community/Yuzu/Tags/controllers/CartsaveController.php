<?php

/**
 * Cart save controller
 *
 * @category    Yuzu
 * @package     Yuzu_Tags
 * @copyright   Copyright (c) 2018 Yuzu (http://www.yuzu.co)
 * @author      Jonathan Martin <jonathan@yuzu.co>
 */
class Yuzu_Tags_CartsaveController extends Mage_Core_Controller_Front_Action
{
    public function indexAction()
    {
        $params = $this->getRequest()->getParams();

        // Stop if no email
        if (!isset($params['email'])) {
            return;
        }

        $quote = Mage::getModel('checkout/cart')->getQuote();
        $quote->setCustomerEmail($params['email']);
        if (isset($params['firstname'])) {
            $quote->setCustomerFirstname($params['firstname']);
        }
        if (isset($params['lastname'])) {
            $quote->setCustomerLastname($params['lastname']);
        }
        $address = $quote->getBillingAddress();
        if ($address) {
            if (isset($params['telephone'])) {
                $address->setTelephone($params['telephone']);
            }
            if (isset($params['country'])) {
                $address->setCountryId($params['country']);
            }
            $quote->setBillingAddress($address);
        }
        Mage::getModel('yuzu_tags/webservice')->sendCart($quote);
    }
}