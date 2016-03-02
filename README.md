# Woocommerce Nimble Payments
##Description
Woocommerce Nimble Payments is a Woocommerce Addons to add payment services to your e-commerce.
##Installation
###Using Composer
If you have installed Composer, you can use it to install.
```
cd PATH_TO_WORDPRESS/wp-content/plugins/
git clone git@github.com:nimblepayments/woocommerce-nimble-payments.git
cd woocommerce-nimble-payments
php composer install
```
Replace PATH_TO_WORDPRESS with Wordpress folder path. Example: /var/www/wordpress
###Manual
If you don't use Composer, you must install this requeriments manually:
####nimblepayments/sdk-php (release 1.0.0.1)
You must copy sdk-php/lib inside root path of the addon.
```
git clone https://github.com/nimblepayments/sdk-php.git
cd sdk-php
git checkout tags/1.0.0.1
cp -R lib PATH_TO/woocommerce-nimble-payments/
```
##Test Environment
The test environment is disabled by default. To activate need to modify the code.
Open 'includes/class-wc-gateway-nimble.php' and search line
```
var $mode = 'real';
```
Change 'real' value to 'demo'. 
