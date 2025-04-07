# Changelog

### Unreleased
Fixed loading of the parcel terminal selection if there is a delay in loading the shipping methods list
Fixed usage of max weight settings parameter
Added the ability to separately specify max weight for the parcel terminal
Added a parameter to the settings that allows to activate the service of sending a shipment return code to the recipient

### Version 1.3.4
Fixed syntax error in statistic send.
Added platform and version identification to all requests to the Omniva server.

### Version 1.3.3
Use OMX for shipment tracking

### Version 1.3.2
Added international shipping support

### Version 1.3.1
Fixed courier call time.
Added sending of shipments statistic to Omniva PowerBi

### Version 1.3.0
The module is adapted to work with Omniva OMX

### Version 1.2.20
Added support to send to Finland from Latvia.

### Version 1.2.19
Fixed getting API URL from settings.

### Version 1.2.18
Created an error message that COD payment is not available when sending to Matkahuolto parcel terminal in Finland.
Fixed reorder when Omniva module is active.
Changed that when selecting the delivery country Finland, the parcel terminal map would show the name and logo of Matkahuolto.

### Version 1.2.17
Added support to send to FI parcel terminals.

### Version 1.2.16
Added 'PC' service, delivery to 18+ age receivers.
To use it, you need to create custom attribute for product, **Yes/No** type.
Then in module configuration's field **Add 18+ service by product attribute code** enter earlier created attribute code.
After order, module will check if ordered products have defined attribute enabled and will add additional **PC** service to Omniva shipment.

### Version 1.2.15
Added module settings, to select fragile option to all orders by default.

### Version 1.2.14
Updated: get country id from $request, to fix infinite loop on some payment modules.

### Version 1.2.13
Updated to fix compatibility with php 8.1.

### Version 1.2.12
Fixed error on checkout if buying virtual product.

### Version 1.2.11
Json decode fix of null value.

### Version 1.2.10
Updated JavaScript to not select first terminal if no postal code provided.

### Version 1.2.9
Fixed class parameters ordering.

### Version 1.2.8
Fixed terminal selection in admin, if no terminal selected in checkout.
Added place order mixin to save selected terminal in OPC.

### Version 1.2.7
Removed not existed functions.
Fixed terminal savings OPC.

### Version 1.2.6
Annotation bugfix.
