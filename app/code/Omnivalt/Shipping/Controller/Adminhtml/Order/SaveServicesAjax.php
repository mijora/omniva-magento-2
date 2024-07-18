<?php

namespace Omnivalt\Shipping\Controller\Adminhtml\Order;

use Magento\Backend\App\Action;

class SaveServicesAjax extends \Magento\Sales\Controller\Adminhtml\Order
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */

    public function execute()
    {
        $order = $this->_initOrder();
        if ($order) {
            $params = $this->getRequest()->getParams();
            $services = array();
            if (isset($params['omniva_services'])){
                $services = $params['omniva_services'];
            }
            $labels_count = 1;
            if (isset($params['omniva_labels_count'])){
                $labels_count = intval($params['omniva_labels_count']);
            }
            if (!$labels_count) {
                $labels_count = 1;
            }
            $resultJson = $this->resultJsonFactory->create();
            $order->setOmnivaltServices(json_encode(array('services'=>$services, 'labels_count'=>$labels_count)));
            $order->save();
            return $resultJson->setData([
                'messages' => 'Successfully.' ,
                'error' => false
            ]);
        }
        return false;
    }
}