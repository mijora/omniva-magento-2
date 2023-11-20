<?php
namespace Omnivalt\Shipping\Controller\Adminhtml\Omnivamanifest;


class Index extends  \Magento\Backend\App\Action
{

  protected $resultPageFactory;

  public function __construct(
              \Magento\Backend\App\Action\Context $context,
              \Magento\Framework\View\Result\PageFactory $resultPageFactory
  ){
       parent::__construct($context);
      $this->resultPageFactory = $resultPageFactory;
  }

  public function execute()
  {
      $resultPage = $this->resultPageFactory->create();
      $resultPage->setActiveMenu('Omnivalt_Shipping::manifest');
      $resultPage->getConfig()->getTitle()->prepend(__('Omniva manifest'));

      return $resultPage;
  }
}