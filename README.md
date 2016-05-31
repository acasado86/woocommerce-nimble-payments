# Nimble Payments Plugin for WordPress/WooCommerce
The NimblePayments Plugin for WooCommerce is an addons that makes it easy to add payment services to your e-commerce.
##Release notes

###1.0.8
- Add WordPress Multisite compatibility

###1.0.7
- Add WordPress 4.5 compatibility

###1.0.6
- Fixes a bug that did not allow update order state for new customers
- Fixes a bug that did not allow send emails

###1.0.5
- First live release
- Added the single payment service

##Requirements
- WordPress 4.0 or later
- WooCommerce 2.5.0 or later
- NimblePayments SDK for PHP https://github.com/nimblepayments/sdk-php.git

##Installation
The NimblePayments Plugin for WooCommerce can either be installed by the Composer or manually.

The plugin can be downloaded from https://wordpress.org/plugins/nimblepayments/

###Composer
To install the plugin via Composer, just run the following commands:
```
cd PATH_TO_WORDPRESS/wp-content/plugins/
git clone git@github.com:nimblepayments/woocommerce-nimble-payments.git nimblepayments
cd nimblepayments
composer.phar install
```
and replace ```PATH_TO_WORDPRESS``` with the Wordpress folder path. Example: ```/var/www/wordpress```
###Manual Installation
To install the plugin without using the Composer,  just run the following commands:
```
git clone https://github.com/nimblepayments/sdk-php.git
cd sdk-php
git checkout tags/1.0.0.1
cp -R lib PATH_TO_WORDPRESS/wp-content/plugins/nimblepayments/
```
##Environment
There are two different environment options:
- Sandbox.It is used in the demo environment to make tests.
- Real. It is used to work in the real environment.

The sandbox environment is disabled by default. To activate it, the variable mode must be manually set to “Sandbox” in the code. please, follow these steps:
- Open the file ```includes/class-wc-gateway-nimble.php```
- Search the line where ```var $mode = 'real';``` is placed
- Change the value ```real``` to ```sandbox```

##Generating nimblepayments.zip
It is possible to generate a unique plugin to be used in your code using the Composer. To that end, in a empty folder run the following commands:
```
git clone git@github.com:nimblepayments/woocommerce-nimble-payments.git nimblepayments
cd nimblepayments/
composer.phar update
composer.phar zip
```
The zip file ```nimblepayments.zip``` is generated in the current folder.
##Documentation
Please see [Apiary](http://docs.nimblepublicapi.apiary.io/#) for up-to-date documentation.
