<?php

/**
 * Check controller
 *
 * @category    Yuzu
 * @package     Yuzu_Tags
 * @copyright   Copyright (c) 2015 Yuzu (http://www.yuzu.co)
 * @author      Olivier Mouren <olivier@yuzu.co>
 */
class Yuzu_Tags_ApiController extends Mage_Core_Controller_Front_Action
{
    /** @var Yuzu_Tags_Helper_Api */
    private $api;

    public function indexAction()
    {
        $request = $this->getRequest();
        $this->api = Mage::helper('yuzu_tags/Api');
        $this->api->setRequest($request);

        $this->checkRequest();

        $res = '';
        $action = json_decode($this->api->getPostData()['query'], true)['action'];
        switch ($action) {
            case 'getCustomers':
                $res = $this->getCustomers();
                break;
            case 'getOrders':
                $res = $this->getOrders();
                break;
            case 'getOrder':
                $res = $this->getOrder();
                break;
            case 'getCodes':
                $res = $this->getCodes();
                break;
            case 'getCategories':
                $res = $this->getCategories();
                break;
            case 'getProducts':
                $res = $this->getProducts();
                break;
            case 'sendEmail':
                $res = $this->sendEmail();
                break;
            case 'getCoupons':
                $res = $this->getCoupons();
                break;
            case 'getCoupon':
                $res = $this->getCoupon();
                break;
            case 'createCoupon':
                $res = $this->createCoupon();
                break;
            case 'updateCoupon':
                $res = $this->updateCoupon();
                break;
            case 'deleteCoupon':
                $res = $this->deleteCoupon();
                break;
            case 'getSalesEmailStatus':
                $res = $this->getSalesEmailStatus();
                break;
            case 'setSalesEmailStatus':
                $res = $this->setSalesEmailStatus();
                break;
            case 'setPluginStatus':
                $res = $this->setPluginStatus();
                break;
            case 'getOrderStatuses':
                $res = $this->getOrderStatuses();
                break;
            case 'getCustomerGroups':
                $res = $this->getCustomerGroups();
                break;
            default:
                break;
        }

        die($this->api->encodeBase64(json_encode(array_merge($res, $this->api->getResponse()))));
    }

    private function checkRequest()
    {
        $postData = $this->api->getPostData();
        if (!count($postData)) {
            $res = array();
            $res['debug'] = 'No POST DATA received';
            $res['return'] = 2;

            die($this->api->encodeBase64(json_encode($res)));
        }

        $checkSign = $this->checkSign();
        if ($checkSign['return'] !== 1) {
            die($this->api->encodeBase64(json_encode($checkSign)));
        }
    }

    private function checkSign()
    {
        $res = array();
        $message = json_decode($this->api->decodeBase64($this->api->getPostData()['message']), true);

        if (empty($message)) {
            $res['debug'] = 'Empty message';
            $res['return'] = 2;
            $res['query'] = 'checkSign';

            return $res;
        }

        if (!empty($message['id_shop'])) {
            $apiKey = Mage::helper('yuzu_tags')->getConfig('yuzu_tags/general/api_key', $message['id_shop']);

            $res['query'] = 'checkSign';
            if (!$apiKey) {
                $res['debug'] = 'Identifiant client non renseigné sur le module';
                $res['message'] = 'Identifiant client non renseigné sur le module';
                $res['return'] = 3;

                return $res;
            } elseif ($message['apiKey'] !== $apiKey) {
                $res['message'] = 'ApiKey incorrecte';
                $res['debug'] = 'ApiKey incorrecte';
                $res['return'] = 4;

                return $res;
            } elseif (sha1($this->api->getPostData()['query'].$apiKey) !== $message['sign']) {
                $res['message'] = 'La signature est incorrecte';
                $res['debug'] = 'La signature est incorrecte';
                $res['return'] = 5;

                return $res;
            } else {
                $res['message'] = 'Identifiants client Ok';
                $res['debug'] = 'Identifiants client Ok';
                $res['return'] = 1;
                $res['sign'] = sha1($this->api->getPostData()['query'].$apiKey);

                return $res;
            }
        } else {
            $apiKey = Mage::helper('yuzu_tags')->getConfig('yuzu_tags/general/api_key');

            if (!$apiKey) {
                $res['debug'] = 'Identifiants client non renseignés sur le module';
                $res['message'] = 'Identifiants client non renseignés sur le module';
                $res['return'] = 3;
                $res['query'] = 'checkSign';

                return $res;
            } elseif ($message['apiKey'] !== $apiKey) {
                $res['message'] = 'ApiKey incorrecte';
                $res['debug'] = 'ApiKey incorrecte';
                $res['return'] = 4;
                $res['query'] = 'checkSign';

                return $res;
            } elseif (sha1($this->api->getPostData()['query'].$apiKey) !== $message['sign']) {
                $res['message'] = 'La signature est incorrecte';
                $res['debug'] = 'La signature est incorrecte';
                $res['return'] = 5;
                $res['query'] = 'checkSign';

                return $res;
            }
            $res['message'] = 'Identifiants Client Ok';
            $res['debug'] = 'Identifiants Client Ok';
            $res['return'] = 1;
            $res['sign'] = sha1($this->api->getPostData()['query'].$apiKey);
            $res['query'] = 'checkSign';

            return $res;
        }
    }

    private function getCustomers()
    {
        $res = array();
        $results = $this->api->getCustomers();

        $customers = array();
        foreach ($results as $customer) {
            $customers[] = $this->api->formatCustomer($customer);
        }

        $res['message']['customers']  = $customers;

        return $res;
    }

    private function getOrders()
    {
        $res = array();
        $results = $this->api->getOrders();

        $orders = array();
        foreach ($results as $order) {
            $orders[] = $this->api->formatOrder($order);
        }

        $res['message']['orders']  = $orders;

        return $res;
    }

    private function getOrder()
    {
        $res = array();
        $order = $this->api->getOrder();

        if ($order) {
            $res['message'] = $this->api->formatOrder($order);
        }

        return $res;
    }

    private function getCodes()
    {
        $res = array();
        $results = $this->api->getCartRules();

        $codes = array();
        foreach ($results as $quote) {
            $codes[] = $this->api->formatCartRule($quote);
        }

        $res['message']['codes']  = $codes;

        return $res;
    }

    private function getProducts()
    {
        $res = array();
        $results = $this->api->getProducts();

        $products = array();
        foreach ($results as $product) {
            $products[] = $this->api->formatProduct($product);
        }

        $res['message']['products']  = $products;

        return $res;
    }

    private function getCategories()
    {
        $res = array();
        $results = $this->api->getCategories();

        $categories = array();
        foreach ($results as $category) {
            $categories[] = $this->api->formatCategory($category);
        }

        $res['message']['categories']  = $categories;

        return $res;
    }

    private function sendEmail()
    {
        try {
            $this->api->sendEmail();

            return ['message' => 'ok'];
        } catch (Exception $e) {
            return ['message' => $e->getMessage()];
        }
    }

    private function getCoupons()
    {
        $res = array();
        $results = $this->api->getCoupons();

        $coupons = array();
        foreach ($results as $coupon) {
            $coupons[] = $this->api->formatCoupon($coupon);
        }

        $res['message']['coupons']  = $coupons;

        return $res;
    }

    private function getCoupon ()
    {
        $res = array();
        $coupon = $this->api->getCoupon();

        if ($coupon) {
            $res['message'] = $this->api->formatCoupon($coupon);
        }

        return $res;
    }

    private function createCoupon()
    {
        $res = array();
        $id = $this->api->createCoupon();

        $res['message']['id_coupon'] = $id;

        return $res;
    }

    private function updateCoupon()
    {
        $res = array();
        $id = $this->api->updateCoupon();

        $res['message']['id_coupon'] = $id;

        return $res;
    }

    private function deleteCoupon()
    {
        $res = array();
        $id = $this->api->deleteCoupon();

        $res['message']['id_coupon'] = $id;

        return $res;
    }

    private function getSalesEmailStatus()
    {
        $res = array();
        $status = $this->api->getSalesEmailStatus();

        $res['message']['status'] = $status === '1' ? '1' : '0';

        return $res;
    }

    private function setSalesEmailStatus()
    {
        $this->api->setSalesEmailStatus();

        return ['message' => 'ok'];
    }

    private function setPluginStatus()
    {
        $this->api->setPluginStatus();

        return ['message' => 'ok'];
    }

    private function getOrderStatuses()
    {
        $res = array();
        $results = $this->api->getOrderStatuses();

        $orderStatuses = array();
        foreach ($results as $orderStatus) {
            $orderStatuses[] = $this->api->formatOrderStatus($orderStatus);
        }

        $res['message']['orderStatuses']  = $orderStatuses;

        return $res;
    }

    private function getCustomerGroups()
    {
        $res = array();
        $results = $this->api->getCustomerGroups();

        $customerGroups = array();
        foreach ($results as $customerGroup) {
            $customerGroups[] = $this->api->formatCustomerGroup($customerGroup);
        }

        $res['message']['customerGroups']  = $customerGroups;

        return $res;
    }
}