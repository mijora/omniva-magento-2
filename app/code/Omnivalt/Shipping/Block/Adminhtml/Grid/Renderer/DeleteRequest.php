<?php

namespace Omnivalt\Shipping\Block\Adminhtml\Grid\Renderer;

class DeleteRequest extends \Magento\Backend\Block\Widget\Grid\Column\Renderer\AbstractRenderer {

    /**
     * Renders grid column
     * @param \Magento\Framework\DataObject $row
     * @return string
     */
    public function render(\Magento\Framework\DataObject $row) {
        $class = '';
        $label = __("Cancel and delete");
        $class = ' delete request';
        
        return '<a href="' . $this->getUrl('omnivalt/CourierRequest/Delete', ['id' => $row->getCourierRequestId()]) . '" class="action-default scalable action-save action-primary '.$class.'">' . $label . '</a>';
     
    }

}
