define([
    'jquery',
    'ko',
    'uiComponent',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/shipping-service',
    'Omnivalt_Shipping/js/view/checkout/shipping/parcel-terminal-service',
    'mage/translate',
    'Omnivalt_Shipping/js/omniva-data',
    'leaflet',
    'Omnivalt_Shipping/js/omniva'
], function ($, ko, Component, quote, shippingService, parcelTerminalService, t, omnivaData) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Omnivalt_Shipping/checkout/shipping/form'
        },

        initialize: function (config) {
            this.parcelTerminals = ko.observableArray();
            this.selectedParcelTerminal = ko.observable();
            this._super();
            
            
            
        },
        hideSelect: function () {
            var method = quote.shippingMethod();
            var selectedMethod = method != null ? method.carrier_code + '_' + method.method_code : null;
            
            if (selectedMethod == 'omnivalt_PARCEL_TERMINAL') {
                $('#terminal-select-location').first().show();
            } else {
                $('#terminal-select-location').first().hide();
            }
        },
        moveSelect: function () {
            var omniva_last_selected_terminal = '';
            if ($('#terminal-select-location select').length > 0){
                omniva_last_selected_terminal = $('#terminal-select-location select').val();
            }
            if ($('#onepage-checkout-shipping-method-additional-load .parcel-terminal-list').length > 0){
                $('#checkout-shipping-method-load input:radio:not(.bound)').addClass('bound').bind('click', this.hideSelect());
                if ($('#checkout-shipping-method-load .parcel-terminal-list').html() !=  $('#onepage-checkout-shipping-method-additional-load .parcel-terminal-list').html()){
                    $('#terminal-select-location').remove();
                }
                
                if ($('#checkout-shipping-method-load .parcel-terminal-list').length == 0){
                    var terminal_list = $('#onepage-checkout-shipping-method-additional-load .omnivalt-parcel-terminal-list-wrapper div');
                    var row = $.parseHTML('<tr><td colspan = "4" style = "border-top: none; padding-top: 0px"></td></tr>');
                    if ($('#s_method_omnivalt_PARCEL_TERMINAL').length > 0){
                        var move_after = $('#s_method_omnivalt_PARCEL_TERMINAL').parents('tr'); 
                    } else if ($('#label_method_PARCEL_TERMINAL_omnivalt').length > 0){
                        var move_after = $('#label_method_PARCEL_TERMINAL_omnivalt').parents('tr'); 
                    }
                    var cloned =  terminal_list.clone(true);
                    if ($('#terminal-select-location').length == 0){
                        $('<tr id = "terminal-select-location" ><td colspan = "4" style = "border-top: none; padding-top: 0px"></td></tr>').insertAfter(move_after);
                    }
                    cloned.appendTo($('#terminal-select-location td'));
                }
            }

            
            if (omnivaData.getPickupPoint()){
                $('#terminal-select-location select').val(omnivaData.getPickupPoint());
            }
            
            if($('#omnivaLtModal').length > 0 && $('.omniva-terminals-list').length == 0){
                if ($('#terminal-select-location select option').length>0){
                    var omnivadata = [];
                    omnivadata.omniva_plugin_url = require.toUrl('Omnivalt_Shipping/css/');
                    omnivadata.omniva_current_country = quote.shippingAddress().countryId;
                    omnivadata.text_title = $.mage.__('Omniva parcel terminals');
                    omnivadata.text_select_terminal = $.mage.__('Select terminal');
                    omnivadata.text_search_placeholder = $.mage.__('Enter postcode');
                    omnivadata.not_found = $.mage.__('Place not found');
                    omnivadata.text_enter_address = $.mage.__('Enter postcode / address');
                    omnivadata.text_show_in_map = $.mage.__('Show in map');
                    omnivadata.text_show_more = $.mage.__('Show more');
                    omnivadata.postcode = quote.shippingAddress().postcode;

                    if (this.isMatkahuolto()) {
                        omnivadata.text_title = $.mage.__('Matkahuolto parcel terminals');
                    }

                    $('#terminal-select-location select').omniva({omnivadata:omnivadata});
                    $('.omniva-modal-header > h5').text(omnivadata.text_title);
                }
            }
            if (typeof omniva_last_selected_terminal === 'undefined') {
                var omniva_last_selected_terminal = '';
            }

            $('#checkout-step-shipping_method').off('change', '#terminal-select-location select').on('change', '#terminal-select-location select', function () {
                omnivaData.setPickupPoint($(this).val());
                
            });
        },
        initObservable: function () {
            this._super();
            this.showParcelTerminalSelection = ko.computed(function() {
                this.moveSelect();
                return this.parcelTerminals().length != 0
            }, this);

            this.selectedMethod = ko.computed(function() {
                this.moveSelect();
                var method = quote.shippingMethod();
                var selectedMethod = method != null ? method.carrier_code + '_' + method.method_code : null;
                return selectedMethod;
            }, this);

            quote.shippingMethod.subscribe(function(method) {
                this.moveSelect();
                var selectedMethod = method != null ? method.carrier_code + '_' + method.method_code : null;
                if (selectedMethod == 'omnivalt_PARCEL_TERMINAL') {
                    this.reloadParcelTerminals();
                }
            }, this);

            this.selectedParcelTerminal.subscribe(function(terminal) {
                /*
                //not needed on one step checkout, is done from overide
                if (quote.shippingAddress().extensionAttributes == undefined) {
                    quote.shippingAddress().extensionAttributes = {};
                }
                quote.shippingAddress().extensionAttributes.omnivalt_parcel_terminal = terminal;
                */
            });

            this.btnIconUrl = ko.computed(function() {
                var dir = 'Omnivalt_Shipping/css/';
                var icon = 'sasi.png';
                if (this.isMatkahuolto()) {
                    icon = 'sasi_mh.svg';
                }

                return dir + icon;
            }, this);

            return this;
        },

        setParcelTerminalList: function(list) {
            this.parcelTerminals(list);
            this.moveSelect();
        },
        
        reloadParcelTerminals: function() {
            parcelTerminalService.getParcelTerminalList(quote.shippingAddress(), this, 1);
            this.moveSelect();
        },

        getParcelTerminal: function() {
            var parcelTerminal;
            if (this.selectedParcelTerminal()) {
                for (var i in this.parcelTerminals()) {
                    var m = this.parcelTerminals()[i];
                    if (m.name == this.selectedParcelTerminal()) {
                        parcelTerminal = m;
                    }
                }
            }
            else {
                parcelTerminal = this.parcelTerminals()[0];
            }

            return parcelTerminal;
        },

        isMatkahuolto: function() {
            if (typeof quote === 'undefined') {
                return false;
            }
            if (quote.shippingAddress() == null) {
                return false;
            }

            if (quote.shippingAddress().countryId == 'FI') {
                return true;
            }

            return false;
        },

        initSelector: function() {
            var startParcelTerminal = this.getParcelTerminal();
        }
    });
});