<?php

namespace Omnivalt\Shipping\Model\Plugin\Pdf;

use Omnivalt\Shipping\Model\Carrier;

class InvoiceTerminal
{
    protected $carrier;

    public function __construct(Carrier $carrier)
    {
        $this->carrier = $carrier;
    }

    public function aroundGetPdf(
        \Magento\Sales\Model\Order\Pdf\Invoice $subject,
        callable $proceed,
        $invoices = []
    ) {
        $modified = [];

        foreach ($invoices as $invoice) {
            $order = $invoice->getOrder();
            if (strtoupper($order->getData('shipping_method') ?? '') === strtoupper('Omnivalt_PARCEL_TERMINAL')) {
                $shippingAddress = $order->getShippingAddress();
                if ($shippingAddress) {
                    $terminalId = $shippingAddress->getOmnivaltParcelTerminal();
                    $terminalName = $this->carrier->getTerminalAddress($terminalId);
                    if ($terminalName) {
                        $original = $order->getShippingDescription();
                        $modified[$order->getId()] = $original;
                        $order->setShippingDescription($original . ": " . $terminalName);
                    }
                }
            }
        }

        $result = $proceed($invoices);

        foreach ($invoices as $invoice) {
            $order = $invoice->getOrder();
            if (isset($modified[$order->getId()])) {
                $order->setShippingDescription($modified[$order->getId()]);
            }
        }

        return $result;
    }
}
