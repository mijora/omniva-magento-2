define([
    'jquery',
    'ko',
    'uiComponent',
    'require',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/shipping-service',
    'Omnivalt_Shipping/js/view/checkout/shipping/parcel-terminal-service',
    'Omnivalt_Shipping/js/omniva-data',
    'mage/translate'
], function ($, ko, Component, require, quote, shippingService, parcelTerminalService, omnivaData, $t) {
    'use strict';

    var PARCEL_TERMINAL_METHOD = 'omnivalt_PARCEL_TERMINAL';
    var ROW_ID = 'terminal-select-location';
    var MAPPING_PATH = 'Omnivalt_Shipping/terminal-mapping/';
    var TILE_SERVER_URL = 'https://maps.omnivasiunta.lt/tile/{z}/{x}/{y}.png';
    var TILE_ATTRIBUTION = '&copy; <a href="https://www.omniva.lt">Omniva</a>'
        + ' | Map data &copy; <a href="https://www.openstreetmap.org/">OpenStreetMap</a> contributors,'
        + ' <a href="https://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>';
    var POSTCODE_MIN_LENGTH = 4;

    return Component.extend({
        defaults: {
            template: 'Omnivalt_Shipping/checkout/shipping/form'
        },

        initialize: function () {
            this.parcelTerminals = ko.observableArray();

            // Element holding the terminal-mapping container. It is created once
            // and moved into the shipping methods table every time the table is
            // re-rendered, so the map instance survives shipping rates reloads.
            this.mapHost = null;
            this.mapLib = null;
            this.mapSource = null;
            this.mapCountry = null;
            this.searchedPostcode = null;
            this.autoSelectPending = false;
            this.moveSelectTimer = null;

            this._super();

            return this;
        },

        initObservable: function () {
            this._super();

            // Restore terminal row after rates re-render (e.g. Amasty OSC keeps the same shippingMethod)
            shippingService.getShippingRates().subscribe(function () {
                this.scheduleMoveSelect(0);
            }, this);

            quote.shippingMethod.subscribe(function () {
                if (this.isParcelTerminalSelected()) {
                    this.reloadParcelTerminals();
                }
                this.moveSelect();
            }, this);

            quote.shippingAddress.subscribe(function (address) {
                if (!address) {
                    return;
                }
                if (this.mapCountry !== null && address.countryId !== this.mapCountry && this.isParcelTerminalSelected()) {
                    this.reloadParcelTerminals();
                    return;
                }
                this.searchPostcode();
            }, this);

            if (this.isParcelTerminalSelected()) {
                this.reloadParcelTerminals();
            }
            this.scheduleMoveSelect(0);

            return this;
        },

        getSelectedMethodCode: function () {
            var method = quote.shippingMethod();

            return method ? method.carrier_code + '_' + method.method_code : null;
        },

        isParcelTerminalSelected: function () {
            return this.getSelectedMethodCode() === PARCEL_TERMINAL_METHOD;
        },

        getMethodRow: function () {
            var input = $('#s_method_omnivalt_PARCEL_TERMINAL');

            if (!input.length) {
                input = $('#label_method_PARCEL_TERMINAL_omnivalt');
            }

            return input.length ? input.first().closest('tr') : $();
        },

        getMapHost: function () {
            if (!this.mapHost) {
                this.mapHost = document.createElement('div');
                this.mapHost.className = 'omnivalt-terminal-map';
            }

            return this.mapHost;
        },

        scheduleMoveSelect: function (delay) {
            clearTimeout(this.moveSelectTimer);
            this.moveSelectTimer = setTimeout(this.moveSelect.bind(this), delay);
        },

        /**
         * Places the terminal selection row right after the Omniva parcel terminal
         * shipping method and (re)initializes the map when terminals are available.
         */
        moveSelect: function () {
            var methodRow, row, cell, host;

            clearTimeout(this.moveSelectTimer);

            if ($('#checkout-shipping-method-load .table-checkout-shipping-method tbody tr').length === 0) {
                console.log('OMNIVA: Shipping methods not loaded yet.');
                this.scheduleMoveSelect(500);
                return;
            }

            methodRow = this.getMethodRow();
            if (!methodRow.length) {
                return;
            }

            row = $('#' + ROW_ID);
            if (!row.length) {
                row = $('<tr id="' + ROW_ID + '"><td colspan="4"></td></tr>');
            }
            if (!row.prev().is(methodRow)) {
                row.insertAfter(methodRow);
            }

            cell = row.children('td').first();
            host = this.getMapHost();
            if (host.parentNode !== cell[0]) {
                cell.append(host);
            }

            row.toggle(this.isParcelTerminalSelected());

            if (this.parcelTerminals().length === 0) {
                // Terminal list is requested on method select, setParcelTerminalList() will call back here
                return;
            }

            this.initMap();
        },

        setParcelTerminalList: function (list) {
            this.parcelTerminals(list || []);
            this.moveSelect();
        },

        reloadParcelTerminals: function () {
            if (!quote.shippingAddress()) {
                return;
            }
            parcelTerminalService.getParcelTerminalList(quote.shippingAddress(), this, 1);
        },

        /**
         * Converts grouped terminals list from REST API into terminal-mapping format
         * { id, name, city, address, coords: { lat, lng }, comment }
         */
        getMapTerminals: function (source) {
            var terminals = [];
            var toArray = function (value) {
                if (Array.isArray(value)) {
                    return value;
                }

                return value && typeof value === 'object' ? Object.keys(value).map(function (key) {
                    return value[key];
                }) : [];
            };

            toArray(source).forEach(function (group) {
                var items = group && group.terminals ? toArray(group.terminals) : [group];

                items.forEach(function (terminal) {
                    var lat, lng;

                    if (!terminal || !terminal.zip) {
                        return;
                    }

                    lat = parseFloat(terminal.y);
                    lng = parseFloat(terminal.x);
                    if (isNaN(lat) || isNaN(lng)) {
                        return;
                    }

                    terminals.push({
                        id: String(terminal.zip),
                        name: terminal.name || '',
                        city: [terminal.location, terminal.city].filter(Boolean).join(' '),
                        address: terminal.address || terminal.location || '',
                        coords: {
                            lat: lat,
                            lng: lng
                        },
                        comment: terminal.comment || ''
                    });
                });
            });

            return terminals;
        },

        getCountry: function () {
            var address = quote.shippingAddress();

            return address && address.countryId ? address.countryId : '';
        },

        getPostcode: function () {
            var address = quote.shippingAddress();

            return address && address.postcode ? String(address.postcode).trim() : '';
        },

        initMap: function () {
            var self = this;
            var source = this.parcelTerminals();
            var terminals;

            if (this.mapSource === source) {
                // Same terminal list, map is already created (or being created)
                this.searchPostcode();
                return;
            }

            terminals = this.getMapTerminals(source);
            this.mapSource = source;

            if (!terminals.length) {
                this.destroyMap();
                this.getMapHost().innerHTML = '<p class="omnivalt-no-terminals">'
                    + $('<span>').text($t('No parcel terminals found')).html() + '</p>';
                return;
            }

            require(['omnivaTerminalMapping'], function (TerminalMapping) {
                if (self.mapSource !== source) {
                    // Terminal list changed while library was loading
                    return;
                }
                self.createMap(TerminalMapping || window.OmnivaTerminalMapping, terminals);
            });
        },

        createMap: function (TerminalMapping, terminals) {
            var self = this;
            var host = this.getMapHost();
            var imagesUrl = require.toUrl(MAPPING_PATH + 'images/');
            var lib, themeOptions;

            this.destroyMap();
            host.innerHTML = '';

            lib = new TerminalMapping();
            this.mapLib = lib;
            this.mapCountry = this.getCountry();
            this.searchedPostcode = null;
            this.autoSelectPending = false;

            lib.setImagesPath(imagesUrl);
            lib.setTranslation(this.getCoreStrings());
            lib.dom.setContainerParent(host);

            lib.sub('tmjs-ready', function () {
                var savedTerminal, location;

                if (self.mapLib !== lib) {
                    return;
                }

                savedTerminal = omnivaData.getPickupPoint();
                location = savedTerminal ? lib.map.getLocationById(savedTerminal) : null;
                if (location) {
                    lib.dom.setActiveTerminal(location.id);
                    lib.publish('terminal-selected', location);
                } else if (savedTerminal) {
                    // Saved terminal is not in the loaded list (e.g. country changed), so it must not be sent with the order
                    omnivaData.setPickupPoint(null);
                }

                self.searchPostcode();
            });

            // Must be subscribed before the theme, so the modal is closed before theme renders selection
            lib.sub('terminal-selected', function (location) {
                if (self.mapLib !== lib || !location || location.id === null || typeof location.id === 'undefined') {
                    return;
                }

                self.autoSelectPending = false;
                lib.dom.setActiveTerminal(location.id);
                lib.publish('close-map-modal');

                if (omnivaData.getPickupPoint() !== String(location.id)) {
                    omnivaData.setPickupPoint(String(location.id));
                    console.log('OMNIVA: Terminal changed to ' + location.id);
                }
            });

            lib.sub('search-result', function () {
                var nearest;

                if (self.mapLib !== lib || !self.autoSelectPending) {
                    return;
                }
                self.autoSelectPending = false;

                if (omnivaData.getPickupPoint()) {
                    return;
                }

                // Locations are sorted by distance from the customer postcode
                nearest = lib.map.locations[0];
                if (nearest) {
                    lib.dom.setActiveTerminal(nearest.id);
                    lib.publish('terminal-selected', nearest);
                }
            });

            lib.sub('search-error', function () {
                if (self.mapLib === lib) {
                    self.autoSelectPending = false;
                }
            });

            themeOptions = window.OmnivaFullwidthTheme.apply(lib, {
                container: host,
                strings: this.getThemeStrings(),
                icons: {
                    search: imagesUrl + 'input_search.svg',
                    clear: imagesUrl + 'input_x.svg',
                    geolocation: imagesUrl + 'geolocation.svg',
                    location: imagesUrl + 'location.svg',
                    terminal: imagesUrl + 'terminal.svg'
                },
                onClear: function () {
                    if (self.mapLib === lib) {
                        omnivaData.setPickupPoint(null);
                    }
                }
            });

            lib.init($.extend({
                country_code: this.mapCountry,
                identifier: 'omnivalt',
                isModal: true,
                // Modal is fixed full-screen, keep it out of checkout containers (transforms, overflow, re-renders)
                modalParent: document.body,
                hideContainer: true,
                hideSelectBtn: true,
                customTileServerUrl: TILE_SERVER_URL,
                customTileAttribution: TILE_ATTRIBUTION,
                terminalList: terminals
            }, themeOptions));
        },

        destroyMap: function () {
            var lib = this.mapLib;
            var leafletMap;

            if (!lib) {
                return;
            }
            this.mapLib = null;

            try {
                leafletMap = lib.map && typeof lib.map.getLeafletMap === 'function' ? lib.map.getLeafletMap() : null;
                if (leafletMap) {
                    leafletMap.remove();
                }
            } catch (e) {
                console.log('OMNIVA: Failed to remove map.', e);
            }

            ['container', 'modal', 'overlay'].forEach(function (key) {
                var el = lib.dom && lib.dom.UI ? lib.dom.UI[key] : null;

                if (el && el.parentNode) {
                    el.parentNode.removeChild(el);
                }
            });
            document.body.classList.remove('tmjs-hide-overflow');
        },

        /**
         * Sorts terminals by distance from the shipping address postcode and,
         * when no terminal is selected yet, selects the nearest one.
         */
        searchPostcode: function () {
            var postcode = this.getPostcode();

            if (!this.mapLib || !this.mapLib.map || postcode.length < POSTCODE_MIN_LENGTH || postcode === this.searchedPostcode) {
                return;
            }

            this.searchedPostcode = postcode;
            this.autoSelectPending = !omnivaData.getPickupPoint();
            this.mapLib.dom.searchNearest(postcode);
        },

        getCoreStrings: function () {
            var title = this.getCountry() === 'FI' ? $t('Matkahuolto parcel terminals') : $t('Omniva parcel terminals');

            return {
                modal_header: title,
                terminal_list_header: $t('Parcel terminals addresses'),
                select_pickup_point: $t('Select parcel machine'),
                modal_open_btn: $t('Select in map'),
                select_btn: $t('Select'),
                geolocation_btn: $t('Use my location'),
                your_position: $t('Distance calculated from this point'),
                nothing_found: $t('Place not found'),
                no_cities_found: $t('There were no cities found for your search term'),
                geolocation_not_supported: $t('Geolocation is not supported')
            };
        },

        getThemeStrings: function () {
            return {
                delivery_location: $t('Delivery location'),
                close_button: $t('Close map'),
                search_label: $t('Search delivery locations'),
                search_placeholder: $t('Start typing parcel machine name or address'),
                clear_search: $t('Clear search'),
                clear_selection: $t('Clear selected delivery location'),
                search_results_label: $t('Delivery location search results'),
                no_search_results: $t('No results'),
                search_error: $t('Unable to find a location'),
                show_on_map: $t('Show on map'),
                sorted_by_zip: $t('Sorted by distance from your postcode:'),
                sort_by_zip: $t('Sort by distance from your postcode'),
                sorted_by_location: $t('Sorted by distance from your location:'),
                postcode_input_label: $t('Postcode'),
                postcode_placeholder: $t('Enter postcode'),
                geolocation_button: $t('Use my location'),
                geolocation_loading: $t('Locating...'),
                geolocation_error: $t('Location unavailable'),
                use_zip: $t('Use postcode'),
                enter_zip: $t('Enter postcode'),
                your_position: $t('Distance calculated from this point'),
                close_popup: $t('Close popup'),
                select_pickup_point: $t('Select parcel machine'),
                modal_open_button: $t('Select in map'),
                select_btn: $t('Select'),
                selected_btn: $t('Selected'),
                change_button: $t('Change'),
                clear_button: $t('Clear')
            };
        }
    });
});
