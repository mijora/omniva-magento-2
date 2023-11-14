<?php

namespace Omnivalt\Shipping\Block\Adminhtml\Buttons;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class CallCourier extends Generic implements ButtonProviderInterface
{
    protected $context;

    public function __construct(
        Context $context
    ) {
        $this->context = $context;
    }

    public function getButtonData()
    {
        return [
                'label' => __('Request omniva pickup'),
                'on_click' => 'deleteConfirm(\'' . __(
                    'Important! Latest request for courier is until 15:00. If requested later, where are no guarantees that the courier will come.'
                ) . '\', \'' . $this->getUrl('omnivalt/order/CallOmniva') . '\')',
                'class' => 'action-primary',
                'style' => 'background-color:#b3aaaa;',
                'sort_order' => '100'
            ];
    }
}