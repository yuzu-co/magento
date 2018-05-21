<?php

/**
 * Observer Model
 *
 * @category    Yuzu
 * @package     Yuzu_Tags
 * @copyright   Copyright (c) 2018 Yuzu (http://www.yuzu.co)
 * @author      Jonathan Martin <jonathan@yuzu.co>
 */
class Yuzu_Tags_Model_QuoteObserver
{
	public function handleQuoteChanges($observer)
	{
		$quote = $observer->getEvent()->getQuote();
		Mage::getModel('yuzu_tags/webservice')->sendCart($quote);
	}
}