<?php

namespace Omnivalt\Shipping\Controller\Adminhtml\CourierRequest;

use Magento\Backend\App\Action\Context;

/**
 * Class MassManifest
 */
class Delete extends \Magento\Framework\App\Action\Action
{

    protected $omnivalt_carrier;
    protected $courierRequestFactory;

    public function __construct(
            Context $context, 
            \Omnivalt\Shipping\Model\Carrier $omnivalt_carrier,
            \Omnivalt\Shipping\Model\CourierRequestFactory $courierRequestFactory) {
        $this->omnivalt_carrier = $omnivalt_carrier;
        $this->courierRequestFactory = $courierRequestFactory;
        parent::__construct($context);
    }

    public function execute() {

        $id = $this->getRequest()->getParam('id');
        $model = $this->courierRequestFactory->create();
        $model->load($id);
        $request_id =  $model->getData('omniva_request_id');
        if ($request_id && $this->omnivalt_carrier->cancelOmnivaPickup($request_id)) {
            $model->delete();
            $this->messageManager->addSuccess(__('The courier request has been deleted.'));
        } else {
            $this->messageManager->addWarning(__('Failed to delete request. Request does not exist or has passed'));
        }
        $this->_redirect($this->_redirect->getRefererUrl());
        return;
    }

}
