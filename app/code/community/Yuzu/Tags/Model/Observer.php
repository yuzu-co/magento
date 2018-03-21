<?php

/**
 * Observer Model
 *
 * @category    Yuzu
 * @package     Yuzu_Tags
 * @copyright   Copyright (c) 2015 Yuzu (http://www.yuzu.co)
 * @author      Jonathan Martin <jonathan@yuzu.co>
 */
class Yuzu_Tags_Model_Observer
{
    /**
     * @param $observer
     */
    public function handleSaveConfig($observer)
    {
        $apiKey = Mage::helper('yuzu_tags')->getConfig('yuzu_tags/general/api_key');

        if ($apiKey) {
            $roleId = $this->getOrCreateRole();
            $this->getOrCreateUser($roleId, $apiKey);
        }

        $this->sendPing();
    }

    private function sendPing()
    {
        try {
            $client = new Varien_Http_Client('https://connector.yuzu-together.com/webhooks/plugin');
            $client->setMethod(Varien_Http_Client::POST);
            $client->setParameterPost('host', Mage::getBaseUrl());
            $client->setParameterPost('status', 'installed');

            $client->request();
        } catch (Exception $e) {
        }
    }

    /**
     * Retrieve or create Role Api Yuzu
     *
     * @return int RoleId
     */
    private function getOrCreateRole()
    {
        $yuzuRole = Mage::getModel('api/role')->getCollection()
                  ->addfieldToSelect('*')
                  ->addFieldToFilter('role_name', array('like' => 'yuzu'))
                  ->getFirstItem();

        if (!$yuzuRole->getRoleId()) {

            $resource = array(
                "giftmessage",
                "core",
                "core/magento",
                "core/magento/info",
                "core/store",
                "core/store/list",
                "catalog",
                "catalog/product",
                "catalog/product/info",
                "catalog/product/attributes",
                "catalog/product/attribute",
                "catalog/product/attribute/info",
                "catalog/product/attribute/option",
                "catalog/product/attribute/types",
                "catalog/product/attribute/read",
                "catalog/product/media",
                "catalog/category",
                "catalog/category/info",
                "catalog/category/attributes",
                "catalog/category/tree",
                "sales",
                "sales/order",
                "sales/order/creditmemo",
                "sales/order/creditmemo/list",
                "sales/order/creditmemo/info",
                "sales/order/invoice",
                "sales/order/invoice/info",
                "sales/order/invoice/void",
                "sales/order/invoice/comment",
                "sales/order/shipment",
                "sales/order/shipment/info",
                "sales/order/shipment/track",
                "sales/order/info",
                "customer",
                "customer/info",
                "customer/address",
                "customer/address/info",
                "cataloginventory",
                "cataloginventory/info",
                "directory",
                "directory/region",
                "directory/country",
            );

            $role = Mage::getModel('api/roles');

            $role = $role
                    ->setName("yuzu")
                    ->setPid(false)
                    ->setRoleType('G')
                    ->save();

            Mage::getModel("api/rules")
                ->setRoleId($role->getId())
                ->setResources($resource)
                ->saveRel();

            return $role->getId();
        }

        return $yuzuRole->getRoleId();
    }

    /**
     * create api user if not exist
     *
     * @param $roleId
     * @param $apiKey
     */
    public function getOrCreateUser($roleId, $apiKey)
    {
        $yuzuUser = Mage::getModel('api/user')->getCollection()
            ->addfieldToSelect('*')
            ->addFieldToFilter('username', array('like' => 'yuzu'))
            ->getFirstItem();

        if (!$yuzuUser->getUserId()) {
            $user = Mage::getModel('api/user')->setData(array(
                        'username' => 'yuzu',
                        'firstname' => 'yuzu',
                        'lastname' => 'api',
                        'email' => 'hello@yuzu.co',
                        'api_key' => $apiKey,
                        'api_key_confirmation' => $apiKey,
                        'is_active' => 1,
                        'user_roles' => '',
                        'assigned_user_role' => '',
                        'role_name' => 'yuzu',
                        'roles' => array($roleId)
                    ));
            $user->save();
            $user->setRoleIds(array($roleId))->setRoleUserId($user->getUserId())->saveRelations();
        }
    }
}