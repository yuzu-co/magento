<?php

/**
 * Cart link controller
 *
 * @category    Yuzu
 * @package     Yuzu_Tags
 * @copyright   Copyright (c) 2018 Yuzu (http://www.yuzu.co)
 * @author      Jonathan Martin <jonathan@yuzu.co>
 */
class Yuzu_Tags_CartlinkController extends Mage_Core_Controller_Front_Action
{
    private function redirectToCart()
    {
        $url = Mage::helper('checkout/cart')->getCartUrl();

        //Keep params except cart_id & cart_token
        $queryParams = array();
        $params = $this->getRequest()->getParams();
        foreach ($params as $key => $value) {
            if ($key === 'cart_token' || $key === 'cart_id') {
                continue;
            }
            $queryParams[] = $key . '=' . $value;
        }

        //Concats query
        if (!empty($queryParams)) {
            $url .= strpos($url, '?') !== false ? '&' : '?';
            $url .= implode('&', $queryParams);
        }

        $this->getResponse()->setRedirect($url)->sendResponse();
    }

    public function indexAction()
    {
        // Get request params
        $params = $this->getRequest()->getParams();

        // Stop if no enoguth params
        if (!isset($params['cart_id']) || !isset($params['cart_token'])) {
            return $this->redirectToCart();
        }

        // Load quote by id
        $quote = Mage::getModel('sales/quote')->load($params['cart_id']);

        // Stop if quote does not exist
        if (!$quote->getId()) {
            return $this->redirectToCart();
        }

        $token = md5(Mage::helper('yuzu_tags')->getEncryptionKey().'recover_cart_'. $quote->getId());

        // Check quote token
        if (!$token || $token != $params['cart_token']) {
            return $this->redirectToCart();
        }

        // Auto log customer if we can
        if ($quote->getCustomerId()) {
            //Gest customer
            $customer = Mage::getModel('customer/customer')->load($quote->getCustomerId());

            Mage::getSingleton('customer/session')->setCustomerAsLoggedIn($customer);
        } else {

            // Get current cart
            $cart = Mage::getSingleton('checkout/cart');

            foreach ($cart->getQuote()->getAllVisibleItems() as $item) {
                $found = false;
                foreach ($quote->getAllItems() as $quoteItem) {
                    if ($quoteItem->compare($item)) {
                        $found = true;
                        break;
                    }
                }

                if (!$found) {
                    $newItem = clone $item;
                    $quote->addItem($newItem);
                    if ($quote->getHasChildren()) {
                        foreach ($item->getChildren() as $child) {
                            $newChild = clone $child;
                            $newChild->setParentItem($newItem);
                            $quote->addItem($newChild);
                        }
                    }
                }
            }

            $quote->save();
            $cart->setQuote($quote);
            $cart->init();
            $cart->save();
        }

        // Redirect to checkout
        return $this->redirectToCart();
    }
}