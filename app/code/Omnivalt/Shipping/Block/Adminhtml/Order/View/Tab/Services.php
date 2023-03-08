<?php

namespace Omnivalt\Shipping\Block\Adminhtml\Order\View\Tab;


class Services extends \Magento\Backend\Block\Template implements \Magento\Backend\Block\Widget\Tab\TabInterface
{
    /**
     * Template
     *
     * @var string
     */
    protected $_template = 'order/view/tab/services.phtml';

    protected $omniva_carrier;
    
    protected $shipping_helper;
   
    /**
     * Core registry
     *
     * @var \Magento\Framework\Registry
     */
    protected $coreRegistry = null;

    /**
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Omnivalt\Shipping\Model\Carrier $omniva_carrier,
        \Omnivalt\Shipping\Model\Helper\ShippingMethod $shipping_helper,
        array $data = []
    ) {
        $this->coreRegistry = $registry;
        $this->omniva_carrier = $omniva_carrier;
        $this->shipping_helper = $shipping_helper;
        parent::__construct($context, $data);
    }

    /**
     * Retrieve order model instance
     *
     * @return \Magento\Sales\Model\Order
     */
    public function getOrder()
    {
        $order = $this->coreRegistry->registry('current_order');
        $default_fragile = $this->omniva_carrier->getConfigData('fragile');
        if ($default_fragile && $order->getOmnivaltServices() == null) {
            $order->setOmnivaltServices(json_encode(array('services'=>['BC'])));
            $order->save();
        }

        return $order;
    }   

    public function getServices(){
        return $this->shipping_helper->getAdditionalServices($this->getOrder(), $this->omniva_carrier);
    }
    
    public function isOmnivaMethod($order)
      {
        $_methods      = array(
          'omnivalt_parcel_terminal',
          'omnivalt_courier',
          'omnivalt_courier_plus'
        );
        $order_shipping_method = strtolower($order->getData('shipping_method') ?? '');
        return in_array($order_shipping_method, $_methods);
      }

    /**
     * {@inheritdoc}
     */
    public function getTabLabel()
    {
        return __('Omniva services');
    }

    /**
     * {@inheritdoc}
     */
    public function getTabTitle()
    {
        return __('Omniva services');
    }

    /**
     * {@inheritdoc}
     */
    public function canShowTab()
    {
        // For me, I wanted this tab to always show
        // You can play around with the ACL settings 
        // to selectively show later if you want
        //return true;
        return $this->isOmnivaMethod($this->getOrder());
    }

    /**
     * {@inheritdoc}
     */
    public function isHidden()
    {
        // For me, I wanted this tab to always show
        // You can play around with conditions to
        // show the tab later
        return false;
    }

    /**
     * Get Tab Class
     *
     * @return string
     */
    public function getTabClass()
    {
        // I wanted mine to load via AJAX when it's selected
        // That's what this does
        //return 'ajax only';
        return '';
    }

    /**
     * Get Class
     *
     * @return string
     */
    public function getClass()
    {
        return $this->getTabClass();
    }

    /**
     * View URL getter
     *
     * @param int $orderId
     * @return string
     */
    public function getViewUrl($orderId)
    {
        return $this->getUrl('omnivaservices/*/*', ['order_id' => $orderId]);
    }
}