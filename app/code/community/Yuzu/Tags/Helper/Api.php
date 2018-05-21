<?php

/**
 * Api Helper
 *
 * @category    Yuzu
 * @package     Yuzu_Tags
 * @copyright   Copyright (c) 2015 Yuzu (http://www.yuzu.co)
 * @author      Olivier Mouren <olivier@yuzu.co>
 */
class Yuzu_Tags_Helper_Api extends Mage_Core_Helper_Abstract
{
    private $request;

    private $message;

    private $res = array();

    public function setRequest(Mage_Core_Controller_Request_Http $request)
    {
        $this->request = $request;
        $this->message = json_decode($this->decodeBase64($this->getPostData()['message']), true);
    }

    public function getPostData()
    {
        return $this->request->getPost();
    }

    /**
     * @return array
     */
    public function getResponse()
    {
        return $this->res;
    }

    public function encodeBase64($data)
    {
        return base64_encode($data);
    }

    public function decodeBase64($data)
    {
        return base64_decode($data);
    }

    public function getCustomers()
    {
        $query = json_decode($this->getPostData()['query'], true);

        $page = (int) $query['page'];
        $limit = (int) $query['limit'];

        /** @var Mage_Sales_Model_Resource_Order_Collection $q */
        $q = Mage::getModel('customer/customer')->getCollection()
            ->addAttributeToSort('created_at', 'asc')
            ->setCurPage($page)
            ->setPageSize($limit);

        if (!empty($query['date_from']) && !empty($query['date_to'])) {
            $q = $q->addAttributeToFilter('created_at', ['from' => $query['date_from'], 'to' => $query['date_to']]);
        } elseif (!empty($query['date_from'])) {
            $q = $q->addAttributeToFilter('created_at', ['from' => $query['date_from']]);
        } elseif (!empty($query['date_to'])) {
            $q = $q->addAttributeToFilter('created_at', ['to' => $query['date_from']]);
        }

        if (!empty($this->message['id_shop'])) {
            $q = $q->addAttributeToFilter('store_id', $this->message['id_shop']);
        }

        $this->signResponse();

        if ($page > $q->getLastPageNumber()) {
            return null;
        }

        return $q;
    }

    public function getOrders()
    {
        $query = json_decode($this->getPostData()['query'], true);

        $page = (int) $query['page'];
        $limit = (int) $query['limit'];

        /** @var Mage_Sales_Model_Resource_Order_Collection $q */
        $q = Mage::getModel('sales/order')->getCollection()
            ->addAttributeToSort('created_at', 'asc')
            ->setCurPage($page)
            ->setPageSize($limit);

        if (!empty($query['date_from']) && !empty($query['date_to'])) {
            $q = $q->addAttributeToFilter('updated_at', ['from' => $query['date_from'], 'to' => $query['date_to']]);
        } elseif (!empty($query['date_from'])) {
            $q = $q->addAttributeToFilter('updated_at', ['from' => $query['date_from']]);
        } elseif (!empty($query['date_to'])) {
            $q = $q->addAttributeToFilter('updated_at', ['to' => $query['date_from']]);
        }

        if (!empty($this->message['id_shop'])) {
            $q = $q->addAttributeToFilter('store_id', $this->message['id_shop']);
        }

        $this->signResponse();

        if ($page > $q->getLastPageNumber()) {
            return null;
        }

        return $q;
    }

    public function getOrder()
    {
        $query = json_decode($this->getPostData()['query'], true);

        /** @var Mage_Sales_Model_Resource_Order_Collection $q */
        $q = Mage::getModel('sales/order')->getCollection()
            ->addAttributeToFilter('increment_id', $query['id_order']);

        if (!empty($this->message['id_shop'])) {
            $q = $q->addAttributeToFilter('store_id', $this->message['id_shop']);
        }

        $this->signResponse();

        return $q->getFirstItem()->getId() ? $q->getFirstItem() : null;
    }

    public function getOrderByCart()
    {
        $query = json_decode($this->getPostData()['query'], true);

        $q = Mage::getModel('sales/order')->getCollection()
            ->addAttributeToFilter('quote_id', $query['id_cart']);

        if (!empty($this->message['id_shop'])) {
            $q = $q->addAttributeToFilter('store_id', $this->message['id_shop']);
        }

        $this->signResponse();

        return $q->getFirstItem()->getId() ? $q->getFirstItem() : null;
    }

    public function formatCustomer(Mage_Customer_Model_Customer $customer)
    {
        $customerData = Mage::getModel('customer/customer')->load($customer->getId())->getData();
        $address = Mage::getModel('customer/address')->load($customerData['default_billing'])->getData();
        $subscriber = Mage::getModel('newsletter/subscriber')->loadByEmail($customerData['email']);
        $optin = $subscriber->getId() ? true : false;

        $res = array(
            'id_customer' => $customer->getId(),
            'id_gender' => $customerData['gender'],
            'group_id' => $customerData['group_id'],
            'lastname' => $customerData['lastname'],
            'firstname' => $customerData['firstname'],
            'dob' => $customerData['dob'],
            'email' => $customerData['email'],
            'taxvat' => $customerData['taxvat'],
//            'newsletter' => $customer->newsletter,
            'optin' => $optin,
            'active' => $customerData['is_active'],
//            'is_guest' => $customer->is_guest,
            'created_at' => $customerData['created_at'],
            'updated_at' => $customerData['updated_at'],
            'id_store' => $customerData['store_id'],
            'phone' => $address ? $address['telephone'] : null,
        );

        if ($address) {
            $res['address'] = array(
                'id' => $address['entity_id'],
                'firstname' => $address['firstname'],
                'lastname' => $address['lastname'],
                'company' => $address['company'],
                'address' => $address['street'],
                'city' => $address['city'],
                'postcode' => $address['postcode'],
                'country' => $address['country_id'],
                'phone' => $address['telephone'],
//                'phone_mobile' => $address->phone_mobile,
            );
        }

        return $res;
    }

    public function formatOrder(Mage_Sales_Model_Order $order)
    {
        $customer = Mage::getModel('customer/customer')->load($order->getCustomerId())->getData();

        $subscriber = Mage::getModel('newsletter/subscriber')->loadByEmail($order->getCustomerEmail());
        $optin = $subscriber->getId() ? true : false;

        $products = array();
        /** @var Mage_Sales_Model_Order_Item $product */
        foreach ($order->getItemsCollection() as $product) {
            $products[] = array(
                'id_product' => $product->getId(),
                'sku' => $product->getSku(),
                'name' => $product->getName(),
                'quantity' => $product->getQtyOrdered(),
                'price' => $product->getPrice(),
            );
        }

        $billingAddress = $order->getBillingAddress();
        $shippingAddress = $order->getShippingAddress();

        $formatedOrder = array(
            'order' => array(
                'id_order' => $order->getIncrementId(),
                'cart_id' => $order->getQuoteId(),
                'created_at' => $order->getCreatedAt(),
                'updated_at' => $order->getUpdatedAt(),
                'status' => $order->getStatusLabel(),
                'status_code' => $order->getStatus(),
                'coupon_code' => $order->getCouponCode(),
//                    'coupon_rule_name' => $order->getCouponCode(),
                'shipping_description' => $order->getShippingDescription(),
                'grand_total' => $order->getGrandTotal(),
                'shipping_amount' => $order->getShippingAmount(),
                'discount_amount' => $order->getDiscountAmount(),
                'discount_description' => $order->getDiscountDescription(),
                'tax_amount' => $order->getTaxAmount(),
                'subtotal' => $order->getSubtotal(),
                'weight' => $order->getWeight(),
                'currency_code' => $order->getOrderCurrencyCode(),
                'is_virtual' => $order->getIsVirtual(),
                'ip' => $order->getRemoteIp(),
                'gift_message_id' => $order->getGiftMessageId(),
                'store_id' => $order->getStoreId(),
            ),
            'customer' => array(
                'id_customer' => $order->getCustomerId(),
                'firstname' => $order->getCustomerFirstname(),
                'lastname' => $order->getCustomerLastname(),
                'email' => $order->getCustomerEmail(),
                'dob' => $customer['dob'],
                'optin' =>  $optin,
                'group_id' => $order->getCustomerGroupId(),
                'gender' => $customer['gender'],
                'taxvat' => $customer['taxvat'],
                'is_guest' => $order->getCustomerIsGuest(),
                'phone' => $billingAddress->getTelephone(),
            ),
            'products' => $products,
        );

        if ($billingAddress) {
            $formatedOrder['billing_address'] = array(
                'id' => $order->getBillingAddressId(),
                'region' => $billingAddress->getRegion(),
                'postcode' => $billingAddress->getPostcode(),
                'prefix' => $billingAddress->getPrefix(),
                'company' => $billingAddress->getCompany(),
                'firstname' => $billingAddress->getFirstname(),
                'lastname' => $billingAddress->getLastname(),
                'street' => $billingAddress->getStreet(),
                'city' => $billingAddress->getCity(),
                'fax' => $billingAddress->getFax(),
                'telephone' => $billingAddress->getTelephone(),
                'country_id' => $billingAddress->getCountryId(),
            );
        }

        if ($shippingAddress) {
            $formatedOrder['shipping_address'] = array(
                'id' => $order->getShippingAddressId(),
                'region' => $shippingAddress->getRegion(),
                'postcode' => $shippingAddress->getPostcode(),
                'prefix' => $shippingAddress->getPrefix(),
                'company' => $shippingAddress->getCompany(),
                'firstname' => $shippingAddress->getFirstname(),
                'lastname' => $shippingAddress->getLastname(),
                'street' => $shippingAddress->getStreet(),
                'city' => $shippingAddress->getCity(),
                'fax' => $shippingAddress->getFax(),
                'telephone' => $shippingAddress->getTelephone(),
                'country_id' => $shippingAddress->getCountryId(),
            );
        }

        return $formatedOrder;
    }

    public function getCartRules()
    {
        /** @var Mage_SalesRule_Model_Resource_Rule_Collection $q */
        $q = Mage::getModel('salesrule/rule')->getCollection();

        $this->signResponse();

        return $q;
    }

    public function formatCartRule(Mage_SalesRule_Model_Rule $rule)
    {
        return array(
            'id_quote' => $rule->getId(),
            'name' => $rule->getName(),
            'description' => $rule->getDescription(),
            'code' => $rule->getPrimaryCoupon()->getCode(),
            'date_from' => $rule->getFromDate(),
            'date_to' => $rule->getToDate(),
            'active' => $rule->getIsActive(),
            'action' => $rule->getSimpleAction(),
            'amount' => $rule->getDiscountAmount(),
            'quantity' => $rule->getUsesPerCoupon(),
            'quantity_per_user' => $rule->getUsesPerCustomer(),
        );
    }

    public function getCategories()
    {
        /** @var Mage_Catalog_Model_Resource_Category_Collection $q */
        $q = Mage::getModel('catalog/category')->getCollection()
            ->addAttributeToSelect('name')
            ->addAttributeToSelect('description');

        if (!empty($this->message['id_shop'])) {
            $rootid = Mage::app()->getStore($this->message['id_shop'])->getRootCategoryId();
            $q = $q->addFieldToFilter('path', ['like' => "1/$rootid/%"]);
        }

        $this->signResponse();

        return $q;
    }

    public function formatCategory(Mage_Catalog_Model_Category $category)
    {
        return array(
            'id_category' => $category->getId(),
            'id_parent' => $category->getParentId(),
            'name' => $category->getName(),
            'description' => $category->getDescription(),
        );
    }

    public function getProducts()
    {
        $query = json_decode($this->getPostData()['query'], true);

        $page = (int) $query['page'];
        $limit = (int) $query['limit'];

        /** @var Mage_Catalog_Model_Resource_Product_Collection $q */
        $q = Mage::getModel('catalog/product')->getCollection()
            ->addAttributeToSelect('name')
            ->addAttributeToSelect('description')
            ->addAttributeToSelect('price')
            ->setCurPage($page)
            ->setPageSize($limit);


        if (!empty($this->message['id_shop'])) {
            $q = $q->addStoreFilter($this->message['id_shop']);
        }

        $this->signResponse();

        if ($page > $q->getLastPageNumber()) {
            return null;
        }

        return $q;
    }

    public function formatProduct(Mage_Catalog_Model_Product $product)
    {
        $fullProduct = Mage::getModel('catalog/product')->load($product->getId());

        $images = array();
        foreach ($fullProduct->getMediaGalleryImages() as $image) {
            $images[] = $image->getUrl();
        }

        return array(
            'id_product' => $product->getId(),
            'sku' => $product->getSku(),
            'name' => $product->getName(),
            'description' => $product->getDescription(),
            'price' => $product->getPrice(),
            'categories' => $product->getCategoryIds(),
            'url' => $product->getProductUrl(),
            'images' => $images,
            'active' => $product->getStatus(),
        );
    }

    private function signResponse()
    {
        if (!empty($this->message['id_shop'])) {
            $this->res['sign'] = sha1(
                $this->getPostData()['query']
                .Mage::helper('yuzu_tags')->getConfig('yuzu_tags/general/api_key', $this->message['id_shop'])
            );
        } else {
            $this->res['sign'] = sha1(
                $this->getPostData()['query']
                .Mage::helper('yuzu_tags')->getConfig('yuzu_tags/general/api_key')
            );
        }
    }

    public function sendEmail()
    {
        $query = json_decode($this->getPostData()['query'], true);

        if (!empty($this->message['id_shop'])) {
            $senderName = Mage::getStoreConfig('trans_email/ident_sales/name', $this->message['id_shop']);
            $senderEmail = Mage::getStoreConfig('trans_email/ident_sales/email', $this->message['id_shop']);
            $smtp = Mage::getStoreConfig('system/smtp/host', $this->message['id_shop']);
            $smtpPort = Mage::getStoreConfig('system/smtp/port', $this->message['id_shop']);
            $setReturnPath = Mage::getStoreConfig('system/smtp/set_return_path', $this->message['id_shop']);
        } else {
            $senderName = Mage::getStoreConfig('trans_email/ident_sales/name');
            $senderEmail = Mage::getStoreConfig('trans_email/ident_sales/email');
            $smtp = Mage::getStoreConfig('system/smtp/host');
            $smtpPort = Mage::getStoreConfig('system/smtp/port');
            $setReturnPath = Mage::getStoreConfig('system/smtp/set_return_path');
        }

        ini_set('SMTP', $smtp);
        ini_set('smtp_port', $smtpPort);

        $mail = new Zend_Mail('utf-8');

        switch ($setReturnPath) {
            case 1:
                $returnPathEmail = $senderEmail;
                break;
            case 2:
                if (!empty($this->message['id_shop'])) {
                    $returnPathEmail = Mage::getStoreConfig('system/smtp/return_path_email', $this->message['id_shop']);
                } else {
                    $returnPathEmail = Mage::getStoreConfig('system/smtp/return_path_email');
                }
                break;
            default:
                $returnPathEmail = null;
                break;
        }

        if ($returnPathEmail !== null) {
            $mailTransport = new Zend_Mail_Transport_Sendmail("-f".$returnPathEmail);
            Zend_Mail::setDefaultTransport($mailTransport);
        } else {
            $mailTransport = new Zend_Mail_Transport_Smtp($smtp, ['port' => $smtpPort]);
            Zend_Mail::setDefaultTransport($mailTransport);
        }

        $mail->addTo($query['to']);
        $mail->setBodyHTML($query['body']);
        $mail->setSubject($query['subject']);
        $mail->setFrom($senderEmail, $senderName);

        $mail->send();

        $this->signResponse();
    }

    public function getCoupons()
    {
        $query = json_decode($this->getPostData()['query'], true);

        $page = (int) $query['page'];
        $limit = (int) $query['limit'];

        $q = Mage::getModel('salesrule/rule')->getCollection()
            ->setCurPage($page)
            ->setPageSize($limit);

        if (!empty($this->message['id_shop'])) {
            $q = $q->addWebsiteFilter($this->message['id_shop']);
        }

        $this->signResponse();

        if ($page > $q->getLastPageNumber()) {
            return null;
        }

        return $q;
    }

    public function getCoupon()
    {
        $query = json_decode($this->getPostData()['query'], true);

        $q = Mage::getModel('salesrule/rule')->getCollection()
            ->addFieldToFilter('rule_id', $query['id_coupon']);

        if (!empty($this->message['id_shop'])) {
            $q = $q->addWebsiteFilter($this->message['id_shop']);
        }

        $this->signResponse();

        return $q->getFirstItem()->getId() ? $q->getFirstItem() : null;
    }

    public function formatCoupon($coupon)
    {
        /** @var Mage_SalesRule_Model_Rule $rule */
        $rule = Mage::getModel('salesrule/rule')->load($coupon->getId());

        $type = null;

        if ($rule->getSimpleAction() == Mage_SalesRule_Model_Rule::BY_PERCENT_ACTION) {
            $type = 'percent';
        } elseif ($rule->getSimpleAction() == Mage_SalesRule_Model_Rule::CART_FIXED_ACTION) {
            $type = 'fixed';
        } elseif ($rule->getSimpleFreeShipping() == Mage_SalesRule_Model_Rule::FREE_SHIPPING_ITEM) {
            $type = 'free_shipping';
        }

        return array(
            'id_coupon' => $rule->getId(),
            'code' => $rule->getCouponCode(),
            'type' => $type,
            'name' => $rule->getName(),
            'description' => $rule->getDescription(),
            'active' => $rule->getIsActive(),
            'storeLabel' => $rule->getStoreLabels()[0],
            'amount' => $rule->getDiscountAmount(),
            'date_from' => $rule->getFromDate(),
            'date_to' => $rule->getToDate(),
            'quantity' => $rule->getUsesPerCoupon(),
            'quantity_per_user' => $rule->getUsesPerCustomer()
        );
    }

    public function createCoupon()
    {
        $query = json_decode($this->getPostData()['query'], true);
        $coupon = $query['coupon'];

        if (isset($coupon['duplicateCode']) && !empty($coupon['duplicateCode'])) {
            $q = Mage::getModel("salesrule/rule")->getCollection()
            ->addFieldToFilter('code', $coupon['duplicateCode']);

            if (!empty($this->message['id_shop'])) {
                $q = $q->addWebsiteFilter($this->message['id_shop']);
            }

            $toDuplicate = $q->getFirstItem()->getId() ? $q->getFirstItem() : null;

            $toDuplicate->getStoreLabels();

            $rule = clone $toDuplicate;
            $oldCoupon = $rule->acquireCoupon();
            if ($oldCoupon){
                $oldCoupon->setId(0);
            }

            $rule->setWebsiteIds($toDuplicate->getWebsiteIds());
            $rule->setCustomerGroupIds($toDuplicate->getCustomerGroupIds());

            $rule->setId(null)
                ->setIsActive($coupon['active'])
                ->setFromDate($coupon['date_from'])
                ->setToDate($coupon['date_to']);

            $rule->save();

            $rule = Mage::getModel("salesrule/rule")->load($rule->getId());
            $rule->setUsesPerCoupon($toDuplicate->getUsesPerCoupon())
                ->setUsesPerCustomer($toDuplicate->getUsesPerCustomer())
                ->setCouponCode($coupon['code']);
        } else {
            $rule = Mage::getModel('salesrule/rule');
            $customerGroupIds = Mage::getModel('customer/group')->getCollection()->getAllIds();

            $rule->setName($coupon['name'])
                ->setDescription($coupon['description'])
                ->setCouponType(Mage_SalesRule_Model_Rule::COUPON_TYPE_SPECIFIC)
                ->setCouponCode($coupon['code'])
                ->setCustomerGroupIds($customerGroupIds)
                ->setIsActive($coupon['active'])
                ->setStoreLabels(array($coupon['storeLabel']))
                ->setFromDate($coupon['date_from'])
                ->setToDate($coupon['date_to'])
                ->setUsesPerCoupon($coupon['quantity'])
                ->setUsesPerCustomer($coupon['quantity_per_user']);

            if ($coupon['type'] == 'percent') {
                $rule->setSimpleAction(Mage_SalesRule_Model_Rule::BY_PERCENT_ACTION)
                    ->setDiscountAmount($coupon['amount']);
                $rule->setSimpleFreeShipping(0);
            } elseif ($coupon['type'] == 'fixed') {
                $rule->setSimpleAction(Mage_SalesRule_Model_Rule::CART_FIXED_ACTION)
                    ->setDiscountAmount($coupon['amount']);
                $rule->setSimpleFreeShipping(0);
            } elseif ($coupon['type'] == 'free_shipping') {
                $rule->setSimpleFreeShipping(Mage_SalesRule_Model_Rule::FREE_SHIPPING_ITEM)
                    ->setDiscountAmount($coupon['amount']);
            }

            if (!empty($this->message['id_shop'])) {
                $rule->setWebsiteIds(array($this->message['id_shop']));
            } else {
                $rule->setWebsiteIds(array(1));
            }
        }

        $rule->save();

        $this->signResponse();

        return $rule->getId();
    }

    public function updateCoupon()
    {
        $query = json_decode($this->getPostData()['query'], true);
        $coupon = $query['coupon'];

        $q = Mage::getModel('salesrule/rule')->getCollection()
            ->addFieldToFilter('rule_id', $coupon['id_coupon']);

        if (!empty($this->message['id_shop'])) {
            $q = $q->addWebsiteFilter($this->message['id_shop']);
        }

        $rule = $q->getFirstItem()->getId() ? $q->getFirstItem() : null;

        $this->signResponse();

        if ($rule) {
            if (isset($coupon['duplicateCode']) && !empty($coupon['duplicateCode'])) {
                $rule->setIsActive($coupon['active'])
                    ->setCouponCode($coupon['code'])
                    ->setFromDate($coupon['date_from'])
                    ->setToDate($coupon['date_to']);
            } else {
                $rule->setName($coupon['name'])
                    ->setDescription($coupon['description'])
                    ->setCouponType(Mage_SalesRule_Model_Rule::COUPON_TYPE_SPECIFIC)
                    ->setCouponCode($coupon['code'])
                    ->setIsActive($coupon['active'])
                    ->setStoreLabels(array($coupon['storeLabel']))
                    ->setFromDate($coupon['date_from'])
                    ->setToDate($coupon['date_to'])
                    ->setUsesPerCoupon($coupon['quantity'])
                    ->setUsesPerCustomer($coupon['quantity_per_user']);

                if ($coupon['type'] == 'percent') {
                    $rule->setSimpleAction(Mage_SalesRule_Model_Rule::BY_PERCENT_ACTION)
                    ->setDiscountAmount($coupon['amount']);
                    $rule->setSimpleFreeShipping(0);
                } elseif ($coupon['type'] == 'fixed') {
                    $rule->setSimpleAction(Mage_SalesRule_Model_Rule::CART_FIXED_ACTION)
                        ->setDiscountAmount($coupon['amount']);
                    $rule->setSimpleFreeShipping(0);
                } elseif ($coupon['type'] == 'free_shipping') {
                    $rule->setSimpleFreeShipping(Mage_SalesRule_Model_Rule::FREE_SHIPPING_ITEM)
                        ->setDiscountAmount($coupon['amount']);
                }

                if (!empty($this->message['id_shop'])) {
                    $rule->setWebsiteIds(array($this->message['id_shop']));
                } else {
                    $rule->setWebsiteIds(array(1));
                }
            }

            $rule->save();

            return $rule->getId();
        }

        return null;
    }

    public function deleteCoupon()
    {
        $query = json_decode($this->getPostData()['query'], true);

        $q = Mage::getModel('salesrule/rule')->getCollection()
            ->addFieldToFilter('rule_id', $query['id_coupon']);

        if (!empty($this->message['id_shop'])) {
            $q = $q->addWebsiteFilter($this->message['id_shop']);
        }

        $rule = $q->getFirstItem()->getId() ? $q->getFirstItem() : null;

        $this->signResponse();

        if ($rule) {
            $rule->delete();

            return $rule->getId();
        }

        return null;
    }

    public function getSalesEmailStatus()
    {
        $query = json_decode($this->getPostData()['query'], true);
        $type = $query['type'];

        if (!empty($this->message['id_shop'])) {
            $status = Mage::getStoreConfig('sales_email/'.$type.'/enabled', $this->message['id_shop']);
        } else {
            $status = Mage::getStoreConfig('sales_email/'.$type.'/enabled');
        }

        return $status;
    }

    public function setSalesEmailStatus()
    {
        $query = json_decode($this->getPostData()['query'], true);
        $type = $query['type'];
        $enabled = (bool) $query['enabled'];

        if (!empty($this->message['id_shop'])) {
            Mage::getConfig()->saveConfig('sales_email/'.$type.'/enabled', $enabled ? '1' : '0', 'stores', $this->message['id_shop']);
        } else {
            Mage::getConfig()->saveConfig('sales_email/'.$type.'/enabled', $enabled ? '1' : '0');
        }

        Mage::app()->getCacheInstance()->cleanType('config');
        Mage::dispatchEvent('adminhtml_cache_refresh_type', array('type' => 'config'));
    }

    public function setPluginStatus()
    {
        $query = json_decode($this->getPostData()['query'], true);
        $enabled = (bool) $query['enabled'];

        if (!empty($this->message['id_shop'])) {
            Mage::getConfig()->saveConfig('yuzu_tags/general/enable', $enabled ? '1' : '0', 'stores', $this->message['id_shop']);
        } else {
            Mage::getConfig()->saveConfig('yuzu_tags/general/enable', $enabled ? '1' : '0');
        }

        Mage::app()->getCacheInstance()->cleanType('config');
        Mage::dispatchEvent('adminhtml_cache_refresh_type', array('type' => 'config'));
        Mage::app()->getCacheInstance()->cleanType('block_html');
        Mage::dispatchEvent('adminhtml_cache_refresh_type', array('type' => 'block_html'));
    }

    public function getOrderStatuses()
    {
        $query = json_decode($this->getPostData()['query'], true);

        $page = (int) $query['page'];
        $limit = (int) $query['limit'];

        $q = Mage::getModel('sales/order_status')->getCollection()
            ->setCurPage($page)
            ->setPageSize($limit);

        $this->signResponse();

        if ($page > $q->getLastPageNumber()) {
            return null;
        }

        return $q;
    }

    public function formatOrderStatus($orderStatus)
    {
        /** @var Mage_Sales_Model_Order_Status $orderStatus */
        $orderStatus = Mage::getModel('sales/order_status')->load($orderStatus->getId());

        $labels = $orderStatus->getLabel();
        if (count($orderStatus->getStoreLabels()) > 0) {
            $labels = array_merge([$labels], $orderStatus->getStoreLabels());
        }

        return array(
            'code' => $orderStatus->getStatus(),
            'labels' => $labels,
        );
    }

    public function getCustomerGroups()
    {
        $q = Mage::getModel('customer/group')->getCollection();

        $this->signResponse();

        return $q;
    }

    public function formatCustomerGroup($customerGroup)
    {
        /** @var Mage_Customer_Model_Group $customerGroup */
        $customerGroup = Mage::getModel('customer/group')->load($customerGroup->getId());

        return array(
            'id_group' => $customerGroup->getId(),
            'name' => $customerGroup->getCode(),
        );
    }
}