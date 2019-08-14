<?php
/**
 * Template.php
 * @author      Gabriel Somoza <gabriel@usestrategery.com>
 * @date        10/16/2014 6:38 PM
 * @copyright   Copyright (c) 2014
 */

class Strategery_GmailInboxActions_Model_Email_Template extends Mage_Core_Model_Email_Template {

    /**
     * Post-processes the template to add the Schema.org markup before body end.
     *
     * @param array $variables
     *
     * @return string
     * @throws Exception
     */
    public function getProcessedTemplate(array $variables = array())
    {
        $template = parent::getProcessedTemplate($variables);
        return $this->_addSchemaMarkup($template, $variables);
    }

    /**
     * Adds the Schema.org markup to emails before body end.
     *
     * @param       $template
     * @param array $variables
     *
     * @return mixed
     */
    protected function _addSchemaMarkup($template, array $variables = array())
    {
        $metadata = $this->_generateMetadata($variables) . '</body>';
        return $metadata ? str_ireplace('</body>', $metadata, $template) : $template;
    }

    /**
     * Generates the Schema.org metadata based on the available variables
     *
     * @param $variables
     *
     * @return string
     */
    protected  function _generateMetadata($variables)
    {
        $result = false;
        $json = null;
        if(isset($variables['order'])) {
            /** @var Mage_Sales_Model_Order $order */
            $order = $variables['order'];
            $json = array(
                '@context' => 'http://schema.org',
                '@type' => 'Order',
                'merchant' => array(
                    '@type' => 'Organization', //TODO: Allow customizing this
                    'name'  => Mage::app()->getStore($order->getStore())->getFrontendName(),
                ),
                'acceptedOffer' => $this->_getAcceptedOffers($order),
                'orderNumber'   => $order->getIncrementId(),
                'priceCurrency' => $order->getOrderCurrency()->toString(),
                'price'         => number_format($order->getGrandTotal(),2), //TODO: make decimal points a config option?
                'url'           => Mage::app()->getStore($order->getStore())->getUrl('sales/order/view', array('order_id' => $order->getId(), '_secure' => true)),
                'orderStatus'   => $this->_getOrderStatus($order),
                //'paymentMethod' => $this->_getPaymentMethod($order),
                'orderDate'     => $order->getCreatedAtStoreDate()->toString(),
                //'priceSpecification' //TODO: add PriceSpecification (recommended for Google Now)
            );
        }
        if($json) {
            $result = '<script type="application/ld+json">'. PHP_EOL .
                      json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL .
                      '</script>';
        }
        return $result;
    }

    /**
     * @param Mage_Sales_Model_Order $order
     *
     * @return array
     */
    protected function _getAcceptedOffers(Mage_Sales_Model_Order $order)
    {
        $offers = array();
        $currencyCode = $order->getOrderCurrency()->getCurrencyCode();
        foreach($order->getAllVisibleItems() as $item) {
            /** @var Mage_Sales_Model_Order_Item $item */
            $productImage = Mage::helper('catalog/image')->init($item->getProduct(), 'image')->resize(265)->__toString();
            $offers[] = array(
                '@type' => 'Offer',
                'itemOffered' => array(
                    '@type' => 'Product', //TODO: Allow customizing this
                    'name'  => $item->getName(),
                    'sku'   => $item->getSku(),
                    'url'   => $item->getProduct()->getProductUrl(),
                    'image' => $productImage,
                ),
                'price' => number_format($item->getPrice(), 2),
                'priceCurrency' => $currencyCode,
                'eligibleQuantity' => array(
                    '@type' => 'QuantitativeValue',
                    'value' => $item->getQtyOrdered()
                ),
                'seller' => array(
                    '@type' => 'Organization', //TODO: Allow customizing this
                    'name'  => Mage::app()->getStore($order->getStore())->getFrontendName()
                ),
            );
        }
        return $offers;
    }

    /**
     * Maps the Magento order State to the proposed Schema.org order statuses
     * TODO: migth be a good idea to make this mapping configurable
     *
     * @param Mage_Sales_Model_Order $order
     *
     * @return string
     */
    protected function _getOrderStatus(Mage_Sales_Model_Order $order)
    {
        $state = $order->getState();
        $mapping = array(
            'new' => 'Processing',
            'pending_payment' => 'ProblemWithOrder',
            'processing' => 'Processing',
            'complete' => 'Delivered',
            'closed' => 'Cancelled',
            'cancelled' => 'Cancelled',
            'holded' => 'ProblemWithOrder',
        );
        return 'http://schema.org/OrderStatus/' . $mapping[$state];
    }

    /**
     * FIXME: Support this, will probably have to expose some config to users
     * @return string
     */
    protected function _getPaymentMethod(Mage_Sales_Model_Order $order)
    {
        //$order->getPayment()->getMethod();
        return 'http://schema.org/CreditCard';
    }

} 
