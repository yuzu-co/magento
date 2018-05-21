<?php

/**
 * Webservice Model
 *
 * @category    Yuzu
 * @package     Yuzu_Tags
 * @copyright   Copyright (c) 2018 Yuzu (http://www.yuzu.co)
 * @author      Jonathan Martin <jonathan@yuzu.co>
 */
class Yuzu_Tags_Model_Webservice
{
	private $apiBaseUrl = 'https://connector.yuzu-together.com';

	const CACHE_TAG = 'yuzu';
	const CACHE_TTL = 1800;
    const PRODUCT_CACHE_TAG = 'yuzu_products';
    const PRODUCT_CACHE_TTL = 7200;

	private function isEnabled($store)
    {
        return Mage::helper('yuzu_tags')->getConfig('yuzu_tags/general/enable', $store);
    }

    private function getEncryptionKey()
    {
        return (string) Mage::getConfig()->getNode('global/crypt/key');
    }

	public function sendCart($quote)
	{
		$store = Mage::app()->getStore($quote->getStoreId());

		if ($this->isEnabled($store)) {
			$cart = $this->getCartRepresentation($quote, $store);
	        if (!$cart) {
	            return;
	        }

	        //Check not already sent
	        $cache = Mage::app()->getCache();
	        $cacheId = 'yuzu-cart-' . $quote->getId();
	        $cachedMd5 = $cache->load($cacheId);
	        $dataMd5 = md5(json_encode($cart));

	        if ($dataMd5 == $cachedMd5) {
	            return;
	        }
	        
	        $tags = array(Yuzu_Tags_Model_Webservice::CACHE_TAG, Yuzu_Tags_Model_Webservice::CACHE_TTL);
	        $cache->save($dataMd5, $cacheId, $tags, Yuzu_Tags_Model_Webservice::CACHE_TTL);

	        $this->doPostRequest('/carts/', $cart, $store);
		}
	}

	private function doPostRequest($apiPath, $fields, $store=null, $isSync=false)
    {
        $response = null;
        $helper = Mage::helper('yuzu_tags');

        try {
            $url = $this->apiBaseUrl . $apiPath;
            $options = array(
                'storeresponse' => false,           //We don't need store the response for later
                'timeout'      => $isSync ? 10 : 1  //We need only wait if is sync, seconds as integer
            );
            $client = new Zend_Http_Client($url, $options);
            $client->setHeaders('Authorization', 'bearer '.$helper->getConfig('yuzu_tags/general/api_key', $store));
            $client->setUri($url);
            $client->setRawData(json_encode($fields), 'application/json');
            $response = $client->request(Zend_Http_Client::POST);
        } catch (Exception $e) {
        }

        return $response;
    }

	private function getCartRepresentation($quote, $store)
	{		
		$cart = array(
			'id' => $quote->getId(),
			'ip' => $quote->getRemoteIp(),
			'shopId' => $quote->getStoreId(),
			'createdAt' => $this->formatDate($quote->getCreatedAt()),
			'currency' => $quote->getQuoteCurrencyCode(),  
			'subtotal' => (float) $quote->getSubtotal(),
			'total' => (float) $quote->getGrandTotal(),
			'cartLink' => $this->getCartRecoverUrl($quote),
			'items' => $this->getCartItems($quote),
			'customerId' => $this->getCustomerId(),
			'email' => $this->getEmail($quote),
			'customer' => $this->getCustomer($quote)
        );
        return $cart;
	}

	private function getEmail($quote, $customer = false)
	{
		$email = '';
		$email = $quote->getCustomerEmail();

		if (!$email && Mage::getSingleton('customer/session')->isLoggedIn()) {
		    $email = Mage::getSingleton('customer/session')->getCustomer()->getEmail();
		}

		if (!$email && $customer) {
			$email = $customer->getEmail();
		}

		return $email;
	}

	private function getCartItems($quote)
	{
		$cache = Mage::app()->getCache();
        $items = array();
        foreach ($quote->getAllVisibleItems() as $item) {

            //Check not already sent
            $cacheId = 'yuzu-product-' . $item->getProductId();
            $productData = $cache->load($cacheId);
            if (!$productData) {
                $product = Mage::getModel('catalog/product')->load($item->getProductId());

                $categoryNames = $this->getCategoriesNames($product);

                $productData = array(
                    'url'       => $product->getProductUrl(),          
                    'shortDescription'       => $product->getShortDescription(),
                    'imageUrl'  => $this->getProductImageUrl($product), 
                    'universe'  => $this->notEmpty($categoryNames[1]),  
                    'category'  => $this->notEmpty(end($categoryNames)) 
                );

                $tags = array(Yuzu_Tags_Model_Webservice::CACHE_TAG, Yuzu_Tags_Model_Webservice::PRODUCT_CACHE_TAG);
                $cache->save(json_encode($productData), $cacheId, $tags, Yuzu_Tags_Model_Webservice::PRODUCT_CACHE_TTL);
            } else {
                $productData = json_decode($productData, true);
            }

            $quantity = (int)$item->getQtyOrdered() > 0 ?  (int)$item->getQtyOrdered() : (int)$item->getQty();

            // ignore configurable empty product
            if (!$item->getPrice()) {
            	continue;
            }

            $items[] = array(
                'id'        => $item->getProductId(),                         
                'name'     	=> $item->getName(),                        
                'shortDescription'  => $productData['shortDescription'], 
                'url'       => $productData['url'],
                'imageUrl'  => $productData['imageUrl'],
                'category'  => $productData['category'],
                'quantity'  => $quantity,                             
                'subtotal'   => (float)$item->getPrice()*$quantity,
                'total'  => (float)$item->getPriceInclTax()*$quantity
            );
        }
        return $items;
	}

	private function getCustomerId()
	{
		if(Mage::getSingleton('customer/session')->isLoggedIn()) {
		    return Mage::getSingleton('customer/session')->getCustomer()->getId();
		}

		return;
	}

	private function getCustomer($quote)
	{
		$customer = Mage::getModel("customer/customer");
		$helper = Mage::helper('yuzu_tags');
		if(Mage::getSingleton('customer/session')->isLoggedIn()) {
		    $customer = Mage::getSingleton('customer/session')->getCustomer();
		}

		return [
			'accountId' => $this->getCustomerId(),
			'civility' => $this->getGender($customer->getGender()), 
			'lastname' => $customer->getLastname(), 
	        'firstname' => $customer->getFirstname(), 	
	        'email' => $this->getEmail($quote, $customer),
	        'homePhoneNumber' => '',
	        'mobilePhoneNumber' => $this->getPhone($quote, $customer),
	        'countryCode'   => $this->getCountry($quote, $customer),
	        'language' => $helper->getBrowserLanguage(),
	        'id_default_group' => Mage::getSingleton('customer/session')->getCustomerGroupId(),
            'id_groups' => [Mage::getSingleton('customer/session')->getCustomerGroupId()],
		];
	}

	private function getPhone($quote, $customer)
	{
		$phone = '';
		$address = $quote->getBillingAddress();
        $request = Mage::app()->getRequest()->getParams();

        if (isset($request['billing'])) {
            if (isset($request['billing']['telephone'])) {
                $phone = $request['billing']['telephone'];
            }
        }

        if ($address) {
            if (!$phone) {
                $phone = $address->getTelephone();
            }
        }

        if ($customer) {
            $customerAddress = $customer->getDefaultBillingAddress();

            if ($customerAddress && !$phone) {
                $phone = $customerAddress->getTelephone();
            }
        }

        return $phone;
	}

	private function getCountry($quote, $customer)
	{
		$country = '';
		$address = $quote->getBillingAddress();
        $request = Mage::app()->getRequest()->getParams();

        if (isset($request['billing'])) {
            if (isset($request['billing']['country_id'])) {
                $country = $request['billing']['country_id'];
            }
        }

        if ($address) {
            if (!$country) {
                $country = $address->getCountryId();
            }
        }

        if ($customer) {
            $customerAddress = $customer->getDefaultBillingAddress();

            if ($customerAddress && !$country) {
                $country = $customerAddress->getCountryId();
            }
        }

        return $country;
	}

	private function getGender($gender)
	{
		switch ((int)$gender) {
            case 1:
                return 'm';
            case 2:
                return 'f';
            default:
                return '';
        }
	}

	public function getCategoriesNames($product)
    {
        $categoryNames = array();
        $categoryIds = $product->getCategoryIds();
        foreach ($categoryIds as $categoryId) {
            $category = Mage::getModel('catalog/category')->load($categoryId);
            $ids = explode('/', $category->getPath());
            foreach ($ids as $id) {
                $category = Mage::getModel('catalog/category')->load($id);
                $categoryNames[] = $category->getName();
            }
        }

        if (empty($categoryNames)) {
            $categoryNames = array(
                0 => $this->notEmpty(null),
                1 => $this->notEmpty(null)
            );
        }

        return $categoryNames;
    }

	private function getProductImageUrl($product)
    {
        if ($product->getImage() == 'no_selection' || !$product->getImage()) {
            return $imageUrl = $this->notEmpty(null);
        }

        $image = null;

        //Handle 1.9.1 feature
        if (version_compare(Mage::getVersion(), '1.9.1', '>=')) {
            //Check if need resize or not
            if (Mage::getStoreConfig(Mage_Catalog_Helper_Image::XML_NODE_PRODUCT_SMALL_IMAGE_WIDTH) < 120) {
                $image = Mage::helper('catalog/image')->init($product, 'image')->resize(120, 120);
            } else {
                $image = Mage::helper('catalog/image')->init($product, 'small_image');
            }
        } else {
            $image = Mage::helper('catalog/image')->init($product, 'small_image');
        }

        //Get the url
        $image = (string)$image;

        //Work with the normal image if no small image available
        if (empty($image)) {
            $image = Mage::helper('catalog/image')->init($product, 'image');
            $image = (string)$image;
        }

        return $image;
    }

	private function formatDate($date)
    {
        return date('Y-m-d\TH:i:sP', strtotime($date));
    }

    private function notEmpty($value)
    {
        return ($value)? $value : '';
    }

    private function getCartRecoverUrl($quote)
    {
    	$token = md5($this->getEncryptionKey().'recover_cart_'. $quote->getId());
    	return Mage::getBaseUrl() . 'yuzu/cartlink?cart_id=' . $quote->getId() . '&cart_token=' . $token;
    }
}