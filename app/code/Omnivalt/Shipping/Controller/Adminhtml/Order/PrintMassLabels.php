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
use Magento\Sales\Model\Order;

/**
 * Class MassDelete
 */
class PrintMassLabels extends \Magento\Sales\Controller\Adminhtml\Order\AbstractMassAction
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
            'omnivalt_COURIER_PLUS',
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
        $model = $this->_objectManager->create('Magento\Sales\Model\Order');
        $unique = array();
        foreach ($collection->getItems() as $order) {
            if (!$order->getEntityId()) {
                continue;
            }
            if (in_array($order->getEntityId(), $unique))
                continue;
            $unique[] = $order->getEntityId();
            $pack_no = array();

            if (!$this->isOmnivaltMethod($order)) {
                $text = 'Warning: Order ' . $order->getData('increment_id') . ' not Omnivalt shipping method.';
                $this->messageManager->addError($text);
                continue;
            }
            if (!$order->getShippingAddress()) { //Is set Shipping adress?
                $items = $order->getAllVisibleItems();
                foreach ($items as $item) {
                    $ordered_items['sku'][] = $item->getSku();
                    $ordered_items['type'][] = $item->getProductType();
                }
                $text = 'Warning: Order ' . $order->getData('increment_id') . ' not have Shipping Address.';
                $this->messageManager->addError($text);
                continue;
            }
            $pack_data[] = $order;
        }

        return $pack_data;
    }

    /**
     * Generate ShipmentXML
     *
     * Test Data if all correct, @return Omniva Lables
     */
    public function massAction(AbstractCollection $collection) {
        $order_ids = $this->_collectPostData('order_ids');
        $pack_data = $this->_fillDataBase($collection); //Send data to server and get packs number's
        try {
            if (!count($pack_data) || $pack_data === false) { //If nothing to print
                $this->_redirect($this->_redirect->getRefererUrl());
                return;
            } else { //If found Order who can get Label so Do it
                $order_ids = array();
                foreach ($pack_data as $order) {
                    $this->_createShipment($order);
                }
            }
            if (!empty($this->labelsContent)) {
                $outputPdf = $this->_combineLabelsPdf($this->labelsContent);
                $outputPdf->Output('Omnivalt_labels.pdf', 'D');
                return;
                //return $fileFactory->create('OmnivaltShippingLabels.pdf', $outputPdf->Output('S'), DirectoryList::VAR_DIR, 'application/pdf');
            }
        } catch (\Exception $e) {
            $this->messageManager->addError($e->getMessage());
            $this->_redirect($this->_redirect->getRefererUrl());
            return;
        }
        $this->messageManager->addError(__('There are no shipping labels related to selected orders.'));
        $this->_redirect($this->_redirect->getRefererUrl());
        return;
    }

    public function _createShipment($order) {
        $shipmentItems = array();
        foreach ($order->getAllItems() as $item) {
            $shipmentItems[$item->getId()] = $item->getQtyToShip();
        }
        // Prepare shipment and save ....
        if ($order->getId() && !empty($shipmentItems)) {
            $shipment = false;
            if ($order->hasShipments()) {
                foreach ($order->getShipmentsCollection() as $_shipment) {
                    $shipment = $_shipment; //get last shipment            
                }
            }

            $label = $this->_createShippingLabel($shipment, $order);
            if (!$label) {
                $this->messageManager->addWarning('Warning: Shipment label not generated for order ' . $order->getData('increment_id'));
            } else {
                $this->messageManager->addSuccess('Success: Order ' . $order->getData('increment_id') . ' shipment generated');
                $order->setIsInProcess(true);
                $order->save();
                $order->addStatusHistoryComment('Automatically SHIPPED by Omnivalt mass action.', false);
                //set status to complete
                $order->setStatus(Order::STATE_COMPLETE);
                $order->save();
            }
        } else {
            $this->messageManager->addWarning('Warning: Order ' . $order->getData('increment_id') . ' is empty or cannot be shipped or has been shipped already');
        }
    }

    protected function _createShippingLabel($shipment, $order) {
        $new_shipment = false;
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $convertOrder = $objectManager->create('Magento\Sales\Model\Convert\Order');
        if (!$shipment) {
            $shipment = $convertOrder->toShipment($order);
            $shipment->register();
            $shipment->getOrder()->setIsInProcess(true);
            $new_shipment = true;
        }
        $subtotal = 0;
        $packaging = array(
            'items' => array()
        );
        foreach ($order->getAllItems() AS $orderItem) {
            $shipmentItem = $convertOrder->itemToShipmentItem($orderItem);
            //maybe tried to ship before unseccessfully
            if ($new_shipment && !$orderItem->getQtyToShip()) {
                $orderItem->setQtyToShip($shipmentItem->getQty());
                $orderItem->setQtyShipped(0);
            }
            if (!$orderItem->getQtyToShip() || $orderItem->getIsVirtual()) {
                continue;
            }
            $qtyShipped = $orderItem->getQtyToShip();
            $packaging['items'][$shipmentItem->getOrderItemId()] = array(
                'qty' => $shipmentItem->getQty(),
                'custom_value' => $shipmentItem->getPrice(),
                'price' => $shipmentItem->getPrice(),
                'name' => $shipmentItem->getName(),
                'weight' => $shipmentItem->getWeight(),
                'product_id' => $shipmentItem->getProductId(),
                'order_item_id' => $shipmentItem->getOrderItemId()
            );
            $subtotal += $shipmentItem->getRowTotal();
            $orderItem->setQtyShipped($orderItem->getQtyShipped() + $qtyShipped);
            $orderItem->save();
            $shipment->addItem($shipmentItem);
        }
        //for sample, not used in label
        $package = array();
        $packaging['params'] = array(
            'container' => '',
            'weight' => 1,
            'custom_value' => $subtotal,
            'length' => 0,
            'width' => 0,
            'height' => 0,
            'weight_units' => 'KILOGRAM',
            'dimension_units' => 'CENTIMETER',
            'content_type' => '',
            'content_type_other' => ''
        );
        $package[] = $packaging;

        $shipment->setPackages($package);
        $labelFactory = $objectManager->create('\Magento\Shipping\Model\Shipping\Labels');
        try {
            $response = $labelFactory->requestToShipment($shipment);
        } catch (\Exception $e) {
            $this->messageManager->addWarning('Warning: Order ' . $shipment->getOrder()->getData('increment_id') . ': ' . $e->getMessage());
            return false;
        }
        if ($response->hasErrors()) {
            $this->messageManager->addWarning('Warning: Order ' . $shipment->getOrder()->getData('increment_id') . ': ' . $response->getErrors());
            return false;
        }
        if (!$response->hasInfo()) {
            return false;
        }
        $labelsContent = array();
        $trackingNumbers = array();
        $info = $response->getInfo();
        foreach ($info as $inf) {
            if (!empty($inf['tracking_number']) && !empty($inf['label_content'])) {
                $labelsContent[] = $inf['label_content'];
                $trackingNumbers[] = $inf['tracking_number'];
            }
        }
        $outputPdf = $this->_combineLabelsPdfZend($labelsContent);
        $shipment->setShippingLabel($outputPdf->render());
        $shipment->save();
        if ($trackingNumbers) {
            foreach ($shipment->getAllTracks() as $track) {
                $track->delete();
            }
            foreach ($trackingNumbers as $trackingNumber) {
                $track = $objectManager->create('Magento\Sales\Model\Order\Shipment\Track')->setShipment($shipment)->setTitle('Omnivalt')->setNumber($trackingNumber)->setCarrierCode('omnivalt')->setOrderId($shipment->getData('order_id'))->save();
            }
        } else {
            $text = 'Warning: Order ' . $shipment->getOrder()->getData('increment_id') . ' has not received tracking numbers.';
            $this->messageManager->addWarning($text);
        }
        if ($shipment->getShippingLabel()) {
            $this->labelsContent[] = $shipment->getShippingLabel();
        }
        return true;
    }

    private function _combineLabelsPdf(array $labelsContent) {
        $pdf = new \setasign\Fpdi\TcpdfFpdi('P');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $tmp_files = array();
        $count = 1;
        $labels = 4;
        $combine = $this->omnivaltCarrier->getConfigData('combine_labels');
        $print_type = $combine ? 4 : 1;
        foreach ($labelsContent as $content) {
            if (!$content)
                continue;
            $prefix = rand(100, 999) . time();
            $label_url = realpath(dirname(__FILE__)) . '/' . $prefix . '.pdf';
            file_put_contents($label_url, $content);
            $pagecount = $pdf->setSourceFile($label_url);
            for ($i = 1; $i <= $pagecount; $i++) {
                $tplidx = $pdf->ImportPage($i);
                if ($print_type == '1') {
                    $s = $pdf->getTemplatesize($tplidx);
                    $pdf->AddPage('P', array($s['width'], $s['height']));
                    $pdf->useTemplate($tplidx);
                } else if ($print_type == '4') {
                    if ($labels >= 4) {
                        $pdf->AddPage();
                        $labels = 0;
                    }
                    if ($labels == 0) {
                        $pdf->useTemplate($tplidx, 5, 15, 94.5, 108, false);
                    } else if ($labels == 1) {
                        $pdf->useTemplate($tplidx, 110, 15, 94.5, 108, false);
                    } else if ($labels == 2) {
                        $pdf->useTemplate($tplidx, 5, 140, 94.5, 108, false);
                    } else if ($labels == 3) {
                        $pdf->useTemplate($tplidx, 110, 140, 94.5, 108, false);
                    }
                    $labels++;
                }
            }
            unlink($label_url);
        }
        return $pdf;
    }

    private function _combineLabelsPdfZend(array $labelsContent) {
        $outputPdf = new \Zend_Pdf();
        foreach ($labelsContent as $content) {
            if (stripos($content, '%PDF-') !== false) {
                $pdfLabel = \Zend_Pdf::parse($content);
                foreach ($pdfLabel->pages as $page) {
                    $outputPdf->pages[] = clone $page;
                }
            } else {
                $page = $this->createPdfPageFromImageString($content);
                if ($page) {
                    $outputPdf->pages[] = $page;
                }
            }
        }
        return $outputPdf;
    }

}
