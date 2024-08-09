<?php

namespace Omnivalt\Shipping\Block\Adminhtml\CourierRequest;

use Magento\Backend\Block\Widget\Grid\Extended;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Helper\Data;
use Magento\Framework\Registry;
use Magento\Framework\ObjectManagerInterface;

// also you can use Magento Default CollectionFactory
class Grid extends Extended {

    protected $registry;
    protected $_objectManager = null;
    protected $courierRequestFactory;

    public function __construct(
            Context $context,
            Data $backendHelper,
            Registry $registry,
            ObjectManagerInterface $objectManager,
            \Omnivalt\Shipping\Model\CourierRequestFactory $courierRequestFactory,
            array $data = []
    ) {
        $this->_objectManager = $objectManager;
        $this->registry = $registry;
        $this->courierRequestFactory = $courierRequestFactory;
        parent::__construct($context, $backendHelper, $data);
    }

    protected function _construct() {
        parent::_construct();
        $this->setId('courier_request_id');
        $this->setDefaultSort('courier_request_id');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
		
        $this->setTitle(__('Orders'));
    }

    protected function _prepareCollection() {
        $courier_requests = $this->courierRequestFactory->create()->getCollection()
                ->addFieldToSelect('*');
        $courier_requests->addFieldToFilter('courier_request_id', array('neq' => ''));


        $this->setCollection($courier_requests);
        return parent::_prepareCollection();
    }

    protected function _prepareColumns() {
        $this->addColumn(
                'select',
                [
                    'header_css_class' => 'a-center',
                    'type' => 'checkbox',
                    'name' => 'id',
                    'align' => 'center',
                    'index' => 'courier_request_id',
                ]
        );
        $this->addColumn(
                'courier_request_id',
                [
                    'header' => __('ID'),
                    'type' => 'number',
                    'index' => 'courier_request_id',
                    'header_css_class' => 'col-id',
                    'column_css_class' => 'col-id',
                ]
        );
        $this->addColumn(
                'omniva_request_id',
                [
                    'header' => __('ID in Omniva system'),
                    'type' => 'text',
                    'index' => 'omniva_request_id',
                    'header_css_class' => 'col-id',
                    'column_css_class' => 'col-id',
                ]
        );
        $this->addColumn(
                'created_at',
                [
                    'header' => __('Called At'),
                    'index' => 'created_at',
                    'type' => 'datetime',
                ]
        );
        $this->addColumn(
                'action.delete',
                [
                    'header' => '',
                    'index' => 'courier_request_id',
                    'type' => 'text',
                    'sortable' => false,
                    'filter' => false,
                    'renderer' => 'Omnivalt\Shipping\Block\Adminhtml\Grid\Renderer\DeleteRequest',
                ]
        );
        return parent::_prepareColumns();
    }

	public function getMainButtonsHtml() {
        $html = parent::getMainButtonsHtml(); //get the parent class buttons
        $addButton = $this->getLayout()->createBlock('Magento\Backend\Block\Widget\Button')
                        ->setData(array(
                            'label' => __('Request omniva pickup'),
                            //'onclick' => "setLocation('".$this->getUrl('omnivalt/order/CallOmniva')."')",
                            'on_click' => 'deleteConfirm(\'' . __(
                                'Important! Latest request for courier is until 15:00. If requested later, where are no guarantees that the courier will come.'
                            ) . '\', \'' . $this->getUrl('omnivalt/order/CallOmniva') . '\')',
                            'class' => 'action-primary',
                            'id' => 'call_omniva',
                        ))->toHtml();
        return  $html .$addButton;
    }

    public function getGridUrl() {
        return $this->getUrl('*/*/index', ['_current' => true]);
    }

}
