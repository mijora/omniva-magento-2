<?php

namespace Omnivalt\Shipping\Controller\Adminhtml\CourierRequest;

class Index extends \Magento\Backend\App\Action {

    protected $resultPageFactory;
    protected $coreRegistry;

    public function __construct(
            \Magento\Backend\App\Action\Context $context,
            \Magento\Framework\View\Result\PageFactory $resultPageFactory,
            \Magento\Framework\Registry $coreRegistry
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->coreRegistry = $coreRegistry;
    }

    public function execute() {
        $this->_view->loadLayout();
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Omnivalt_Shipping::courier_request');
        $resultPage->getConfig()->getTitle()->prepend(__('Omniva pickup requests'));
        $resultPage->addBreadcrumb(__('Omniva'), __('Courier requests'));
        $this->_addContent($this->_view->getLayout()->createBlock('Omnivalt\Shipping\Block\Adminhtml\CourierRequest\Grid'));
        $this->_view->renderLayout();
    }

    protected function _isAllowed() {
        return true;
    }

}
