<?php

namespace Omnivalt\Shipping\Model\ResourceModel\CourierRequest;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection {

    protected $_idFieldName = 'courier_request_id';
    protected $_eventPrefix = 'omnivalt';
    protected $_eventObject = 'courier_request_collection';

    /**
     * Define resource model
     *
     * @return void
     */
    protected function _construct() {
        $this->_init('Omnivalt\Shipping\Model\CourierRequest', 'Omnivalt\Shipping\Model\ResourceModel\CourierRequest');
    }

}