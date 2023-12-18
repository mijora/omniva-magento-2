# Changelog

### Unreleased
Created an error message that COD payment is not available when sending to Matkahuolto parcel terminal in Finland.
Fixed reorder when Omniva module is active.

### Version 1.2.17
Added support to send to FI parcel terminals

### Version 1.2.16
Added 'PC' service, delivery to 18+ age receivers.
To use it, you need to create custom attribute for product, **Yes/No** type.
Then in module configuration's field **Add 18+ service by product attribute code** enter earlier created attribute code.
After order, module will check if ordered products have defined attribute enabled and will add additional **PC** service to Omniva shipment.