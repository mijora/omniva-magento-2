<?php

namespace Omnivalt\Shipping\Controller\Adminhtml\Order;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Backend\App\Action\Context;
use Magento\Ui\Component\MassAction\Filter;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Shipping\Model\Shipping\LabelGenerator;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\Filesystem\DirectoryList;

/**
 * Class MassDelete
 */
class ReprintMassLabels extends \Magento\Sales\Controller\Adminhtml\Order\AbstractMassAction
{

    /**
     * @var OrderManagementInterface
     */
    protected $orderManagement;
    
    private $omnivaltCarrier;
    public $labelsContent = array();

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     * @param OrderManagementInterface $orderManagement
     */
    public function __construct(
            Context $context, 
            Filter $filter, 
            CollectionFactory $collectionFactory, 
            OrderManagementInterface $orderManagement,
            \Omnivalt\Shipping\Model\Carrier $omnivaltCarrier
            ) {
        $this->collectionFactory = $collectionFactory;
        $this->orderManagement = $orderManagement;
        $this->omnivaltCarrier = $omnivaltCarrier;
        parent::__construct($context, $filter);
    }

    public function isOmnivaltMethod($order) {
        $_omnivaltMethods = array(
            'omnivalt_PARCEL_TERMINAL',
            'omnivalt_COURIER',
            'omnivalt_INTERNATIONAL_ECONOMY',
            'omnivalt_INTERNATIONAL_STANDARD',
            'omnivalt_INTERNATIONAL_PREMIUM',
        );
        $order_shipping_method = $order->getData('shipping_method');
        return in_array($order_shipping_method, $_omnivaltMethods);
    }

    private function _collectPostData($post_key = null) {
        return $this->getRequest()->getPost($post_key);
    }
    
    private function _fillDataBase(AbstractCollection $collection) {
        $pack_data = array();
        $unique = array();
        foreach ($collection->getItems() as $order) {
            if (!$order->getEntityId()) {
                continue;
            }
            if (in_array($order->getEntityId(), $unique)) {
                continue;
            }
            $unique[] = $order->getEntityId();

            if (!$this->isOmnivaltMethod($order)) {
                continue;
            }
            if (!$order->getShippingAddress()) { 
                continue;
            }
            $pack_data[] = $order;
        }

        return $pack_data;
    }
    
    public function massAction(AbstractCollection $collection) {
        $barcodes = [];
        $orders = $this->_fillDataBase($collection);
        if (!empty($orders)) {
            foreach ($orders as $order) {
                foreach ($order->getShipmentsCollection() as $shipment) {
                    foreach ($shipment->getAllTracks() as $tracknum) {
                        $barcodes[] = (string)$tracknum->getNumber();
                    }
                }
            }
        }
        if ($this->getRequest()->getParam('barcode')) {
            $barcodes[] = $this->getRequest()->getParam('barcode');
        }
        if (!empty($barcodes)) {
            $this->omnivaltCarrier->getLabels($barcodes);
        } else {
            $this->messageManager->addError(__('There are no shipping labels related to selected orders.'));
            $this->_redirect($this->_redirect->getRefererUrl());
            return;
        }
        
    }

}
