<?php

/**
 * Observer Model
 *
 * @category    Yuzu
 * @package     Yuzu_Tags
 * @copyright   Copyright (c) 2015 Yuzu (http://www.yuzu.co)
 * @author      Jonathan Martin <jonathan@yuzu.co>
 */
class Yuzu_Tags_Model_OrderObserver
{
    /**
     * @param $observer
     */
    public function handleOrderChanges($observer)
    {
        try {
            $order = $observer->getOrder();
            /** @var Mage_Sales_Model_Order $order */
            if ($order->getStatus() !== $order->getOrigData('status')) {
                $apiKey = Mage::helper('yuzu_tags')->getConfig('yuzu_tags/general/api_key');

                $client = new Zend_Http_Client('URL_SALES_STATUS');
                $client->setMethod(Zend_Http_Client::PUT);
                $client->setHeaders(
                    array(
                        'Authorization: Bearer '.$apiKey,
                        'Content-Type: application/x-www-form-urlencoded',
                    )
                );
                $client->setParameterPost('orderId', $order->getIncrementId());
                $client->setParameterPost('statusCode', $order->getStatus());
                $client->setParameterPost('status', $order->getStatusLabel());

                $tracks = [];
                /** @var Mage_Sales_Model_Order_Shipment_Track $track */
                foreach ($order->getTracksCollection() as $track) {
                    $tracks[] = array(
                        'name' => $track->getTitle(),
                        'number' => $track->getNumber()
                    );
                }

                $client->setParameterPost('tracks', json_encode($tracks));

                $client->request();
            }
        } catch (Exception $e) {
        }
    }
}