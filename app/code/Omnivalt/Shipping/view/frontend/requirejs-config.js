var config = {
    /*
    "map": {
        "*": {
            "Magento_Checkout/js/model/shipping-save-processor/default" : "Omnivalt_Shipping/js/shipping-save-processor-default-override",
        }
    },
     *
     */
    config: {
        mixins: {
            'Magento_Checkout/js/action/set-shipping-information': {
                'Omnivalt_Shipping/js/action/mixin/set-shipping-information-mixin': true
            },
            'Magento_Checkout/js/action/place-order': {
                'Omnivalt_Shipping/js/action/mixin/place-order-mixin': true
            }
        }
    },
    paths: {
        leaflet: 'Omnivalt_Shipping/js/leaflet',
        'leaflet.markercluster': 'Omnivalt_Shipping/js/leaflet.markercluster',
        omnivaTerminalMapping: 'Omnivalt_Shipping/terminal-mapping/terminal-mapping.omniva-fullwidth'
    },
    shim: {
        leaflet: {
            exports: 'L'
        },
        'leaflet.markercluster': {
            deps: ['leaflet']
        },
        // Terminal mapping library checks for global L and L.markerClusterGroup
        // and would inject them from CDN as plain <script> tags otherwise,
        // which breaks RequireJS (anonymous define). Load them first.
        omnivaTerminalMapping: {
            deps: ['leaflet', 'leaflet.markercluster'],
            exports: 'OmnivaTerminalMapping'
        }
    }
};
