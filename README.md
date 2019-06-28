# Magento 2 Better Order Incrementing

Increment order ids manually, instead of relying on auto increment.

When MySQL rollbacks happen, auto increment IDs do not rollback to their previous value. In many cases this isn't a bad thing, but it is for Order IDs. Order IDs need to be sequential for better organization and bookkeeping. 

## Installation
``` bash
composer require marissen/magento2-module-better-order-incrementing
bin/magento setup:upgrade
```

## Uninstallation
The auto_increment for the following tables probably are not in sync with the content of said tables:
- `sequence_creditmemo_X` 
- `sequence_invoice_X` 
- `sequence_order_X` 
- `sequence_shipment_X` 

Please verify this and make sure it is in sync, because if they are not and you disable/uninstall this module, you'll run into MySQL constraints.