<?php

namespace Omnivalt\Shipping\Model;

class CourierRequest extends \Magento\Framework\Model\AbstractModel implements \Magento\Framework\DataObject\IdentityInterface
{

    const CACHE_TAG = 'omnivalt_courier_requests';

    protected function _construct() {
        $this->_init('Omnivalt\Shipping\Model\ResourceModel\CourierRequest');
    }

    public function getIdentities() {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

}
