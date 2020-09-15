# Hiboutik module for Prestashop

This repository contains the open source module that allows you to synchronize Hiboutik POS software and Prestashop.
All your online sales are automatically integrated in your Hiboutik account.

## Requirements

* PHP 5.3.0 or newer
* PHP cURL extension

## Description

When a customer makes a purchase in your store, your inventory levels will be updated in your Prestashop store.

All Prestashop sales will be created in your Hiboutik account automatically in real-time, when the order status updates to "payment accepted".

When you complete a stock order, a transfer, or an inventory count in your Hiboutik account, you must push all your inventory levels in Prestashop, from the module settings page (SYNC NOW button).

When a sale comes through to Hiboutik from your Prestashop store, we'll add the shipping charge amount to the Shipping product setup on the module settings page.


## Installation

Download Zip archive

On your Prestashop admin interface : Modules > Modules Catalog > Upload a module

Then follow the guide :

[FR] http://www.logiciel-caisse-gratuit.com/synchronisation-prestashop-hiboutik/

[EN] http://www.pos-software-free.com/sync-prestashop-hiboutik/

## Tips

### How to sync all your warehouses

In https://github.com/hiboutik/prestashop-hiboutik/blob/master/hiboutik.php, replace :
```php
$stock_available = $hiboutik->get('/stock_available/warehouse_id/' . $\config['HIBOUTIK_STORE_ID']);
```
with this :
```php
$stock_available = $hiboutik->get('/stock_available/all_wh/');
```

In https://github.com/hiboutik/prestashop-hiboutik/blob/master/controllers/front/Sync.php, replace :
```php
          foreach ($stocks_dispo as $stock) {
            if ($stock['warehouse_id'] == $config['HIBOUTIK_STORE_ID']) {
              $quantity = $stock['stock_available'];
              break;
            }
          }
```
with this :
```php
              $quantity = 0;
          foreach ($stocks_dispo as $stock) {
              $quantity = $quantity + $stock['stock_available'];
          }
```

### How to close sale after sync

In https://github.com/hiboutik/prestashop-hiboutik/blob/master/hiboutik.php, replace :
```php
      //identifiant unique de la vente
```
with this :
```php

      //Close sale
      $hibou_close_sale = $hiboutik->post('/sales/close/', [
      'sale_id'         => $hibou_sale_id
      ]);

      //identifiant unique de la vente
```


### How to set the payment method

In https://github.com/hiboutik/prestashop-hiboutik/blob/master/hiboutik.php, replace :
```php
      //identifiant unique de la vente
```
with this :
```php

      //Set payment method
      $hibou_set_payment = $hiboutik->put("/sale/$hibou_sale_id/", [
      'sale_attribute'  => 'payment',
      'new_value'       => 'XXX'
      ]);

      //identifiant unique de la vente
```


## Credits

Many have contributed to this plugin effort, from direct contributions of code, to contributions of projects.

Contributors :
* [Murelh Ntyandi](http://www.murelh.info), _(contact@murelh.info)_
* [Hiboutik dev team](https://www.hiboutik.com)
