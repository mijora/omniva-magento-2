define([
    'jquery',
    'mage/utils/wrapper',
    'Magento_Checkout/js/model/quote',
    'Magento_Ui/js/model/messageList',
    'mage/translate',
    'Omnivalt_Shipping/js/omniva-data'
], function($, wrapper, quote, globalMessageList, $t, $omnivaData) {
    'use strict';

    return function(shippingInformationAction) {

        return wrapper.wrap(
            shippingInformationAction,
            function(originalAction) {
                let selectedShippingMethod = quote.shippingMethod();
                let shippingAddress = quote.shippingAddress();
                
                
                if (selectedShippingMethod.carrier_code !== 'omnivalt') {
                    return originalAction();
                }
                
                let terminal = $omnivaData.getPickupPoint();
                
                if (selectedShippingMethod.method_code === 'PARCEL_TERMINAL' &&
                    !terminal) {
                    // Amasty OSC calls this on method select; terminal is still checked on place order by shipping-validation
                    if ($('#checkout[data-amcheckout-js="checkout"]').length === 0) {
                        globalMessageList.addErrorMessage(
                            {message: $t('Select Omniva parcel terminal!')});
                        jQuery(window).scrollTop(0);
                    }
                    return originalAction();
                }
                
                if (shippingAddress.extensionAttributes === undefined) {
                    shippingAddress.extensionAttributes = {};
                }
                
                if (shippingAddress.extension_attributes === undefined) {
                    shippingAddress.extension_attributes = {};
                }
                
                shippingAddress.extensionAttributes.omnivalt_parcel_terminal = terminal;
                shippingAddress.extension_attributes.omnivalt_parcel_terminal = terminal;

                return originalAction();
            });
    };
});
