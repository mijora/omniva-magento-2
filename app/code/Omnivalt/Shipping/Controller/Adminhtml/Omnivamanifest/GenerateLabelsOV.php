<?php
namespace Omnivalt\Shipping\Controller\Adminhtml\Omnivamanifest;

/*
 * For magento below 2.3
 */

class GenerateLabelsOV extends  \Magento\Framework\App\Action\Action
{

  protected $resultPageFactory;
  protected $massLabels;
  protected $_orderCollectionFactory;

  public function __construct(
              \Magento\Backend\App\Action\Context $context,
              \Magento\Framework\View\Result\PageFactory $resultPageFactory,
              \Omnivalt\Shipping\Controller\Adminhtml\Order\PrintMassLabels $massLabels,
              \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory
              
  ){
    
      $this->resultPageFactory = $resultPageFactory;
      $this->massLabels = $massLabels;
      $this->_orderCollectionFactory = $orderCollectionFactory;
       parent::__construct($context);
  }

  public function execute()
  {
      $order_ids = $this->getRequest()->getPost('order_ids');
      $collection = $this->_orderCollectionFactory->create()->addFieldToFilter('entity_id',array('in' => $order_ids))->load();
      
      return $this->massLabels->massAction($collection);
  }
}