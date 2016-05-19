<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
//$url = $this->getOauth3Url();
$button_class = 'button-primary';
?>
<div class="wrap">
    <h1><?php _e('Nimble Payments Summary', self::$domain); //LANG: SUMMARY_TITLE_1 ?></h1>
    <p><?php _e('From WooCommerce you can manage all your sales, see the movements of your account, make refunds, etc.', self::$domain); //LANG: AUTHORIZE_DESC_1 ?></p>
    <p><?php _e('To release all features of Nimble Payments from WooCommerce, you need to login in Nimble Payments and grant access to WooCommerce in order to access to this operative.', self::$domain); //LANG: AUTHORIZE_DESC_2 ?></p>
    <p class="submit">
        <?php if ( false == $this->gateway_enabled ): ?>
        <span class="button button-disabled" ><?php _e('Authorize Woocommerce', self::$domain); //LANG: AUTHORIZE_BUTTON_2 ?></span>
        <?php else: ?>
        <a class="button button-primary" href='<?php echo $this->oauth3_url; ?>'><?php _e('Authorize Woocommerce', self::$domain); //LANG: AUTHORIZE_BUTTON_2 ?></a>
        <?php endif; ?>
    </p>
</div>
