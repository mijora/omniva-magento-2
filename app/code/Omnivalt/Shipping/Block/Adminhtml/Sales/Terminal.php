<?php

namespace Omnivalt\Shipping\Block\Adminhtml\Sales;

use Magento\Sales\Model\OrderRepository;
use Omnivalt\Shipping\Model\Carrier;
use Omnivalt\Shipping\Model\LabelHistoryFactory;

class Terminal extends \Magento\Backend\Block\Template
{

    protected $omnivaltCarrier;
    protected $data;
    private $productMetadata;
    protected $labelhistoryFactory;

    /**
     * Core registry
     *
     * @var \Magento\Framework\Registry
     */
    protected $coreRegistry = null;

    /**
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Omnivalt\Shipping\Model\Carrier $omnivaltCarrier
     * @param \Magento\Framework\App\ProductMetadataInterface $productMetadata
     * @param \Omnivalt\Shipping\Model\LabelHistoryFactory $labelhistoryFactory
     * @param array $data
     */
    public function __construct(
            \Magento\Backend\Block\Template\Context $context,
            \Magento\Framework\Registry $registry,
            Carrier $omnivaltCarrier,
            \Magento\Framework\App\ProductMetadataInterface $productMetadata,
            LabelHistoryFactory $labelhistoryFactory,
            array $data = []
    ) {
        $this->coreRegistry = $registry;
        $this->omnivaltCarrier = $omnivaltCarrier;
        $this->data = $data;
        $this->productMetadata = $productMetadata;
        $this->labelhistoryFactory = $labelhistoryFactory;
        parent::__construct($context, $data);
    }

    public function getTerminalName() {
        $order = $this->getOrder();
        if (strtoupper($order->getData('shipping_method') ?? '') == strtoupper('Omnivalt_PARCEL_TERMINAL')) {
            return $this->getTerminal($order);
        }
        return false;
    }

    public function isOmnivaTerminal() {
        $order = $this->getOrder();
        return strtoupper($order->getData('shipping_method') ?? '') == strtoupper('Omnivalt_PARCEL_TERMINAL');
    }

    public function getCurrentTerminal() {
        //$orderRepository = new \Magento\Sales\Model\OrderRepository();
        $order_id = $this->getRequest()->getParam('order_id');
        $order = $this->getOrder();
        //$order =  $orderRepository->get($order_id);
        if (strtoupper($order->getData('shipping_method') ?? '') == strtoupper('Omnivalt_PARCEL_TERMINAL')) {
            return $this->getTerminalId($order);
        }
        return false;
    }

    public function getTerminalId($order) {
        $shippingAddress = $order->getShippingAddress();
        $terminal_id = $shippingAddress->getOmnivaltParcelTerminal();
        return $terminal_id;
    }

    public function getTerminal($order) {
        $shippingAddress = $order->getShippingAddress();
        $terminal_id = $shippingAddress->getOmnivaltParcelTerminal();
        $parcel_terminal = $this->omnivaltCarrier->getTerminalAddress($terminal_id);
        return $parcel_terminal;
    }

    public function getReceiverCountry($order) {
        if (!$order) {
            return 'LT';
        }
        $shippingAddress = $order->getShippingAddress();
        $country = $shippingAddress->getCountryId();
        return $country ?? 'LT';
    }

    public function getTerminals($order = false) {
        $parcel_terminals = $this->omnivaltCarrier->getTerminals($this->getReceiverCountry($order)); //$this->getAddress()->getCountryId());
        return $parcel_terminals;
    }

    /**
     * Retrieve order model instance
     *
     * @return \Magento\Sales\Model\Order
     */
    public function getOrder() {
        return $this->coreRegistry->registry('current_order');
    }

    public function blockIsVisible() {
        if (isset($this->data['up_to_version'])) {
            if ($this->getMagentoVersion() >= $this->data['up_to_version']) {
                return false;
            }
        }
        return true;
    }

    public function getMagentoVersion() {
        return $this->productMetadata->getVersion();
    }
    
    public function getOrderHistory() {
        if ($this->getMagentoVersion() < '2.3.0') {
            $old_version = true;
        } else {
            $old_version = false;
        }
        $order = $this->getOrder();
        $history = '';
        try {
            $history_items = $this->labelhistoryFactory->create()->getCollection() ->addFieldToSelect('*');
            $history_items->addFieldToFilter('order_id', array('eq' => $order->getId()));
            if (count($history_items)) {
                $history .= '<table class = "data-table admin__table-primary edit-order-table" style = "margin-bottom:20px;"><thead><tr class = "headings"><th>'.__('Barcode').'</th><th>'.__('Service').'</th><th>'.__('Generation date').'</th></tr></thead><tbody>';
                foreach ($history_items as $item) {
                    $link = '<a href = "'.$this->getUrl('omnivalt/omnivamanifest/printlabels' . ($old_version ? 'ov' : '')).'?barcode='.$item->getLabelBarcode().'" target = "_blank">' . $item->getLabelBarcode() . '</a>';                    
                    $history .= '<tr><td>'.$link.'</td><td>'.$item->getServices().'</td><td>'.$item->getCreatedAt().'</td></tr></thead>';
                }
                $history .= '</tbody></table>';
            }
        } catch (\Exception $e) {
            return $e->getMessage();
        }
        if (!$history) {
            return '';
        }
        return $history;
    }

}
