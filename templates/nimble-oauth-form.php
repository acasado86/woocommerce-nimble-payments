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
    <p><?php _e('Desde WooCommerce podrás gestionar todas tus ventas, ver los movimientos de tu cuenta , hacer devoluciones, etc.', self::$domain); //LANG: AUTHORIZE_DESC_1 ?></p>
    <p><?php _e('Para acceder a toda la potencia de Nimble Payments desde WooCommerce, es necesario que te identifiques y des permiso a WooCommerce a acceder a estos datos.', self::$domain); //LANG: AUTHORIZE_DESC_2 ?></p>
    <p class="submit">
        <?php if ( false == $this->gateway_enabled ): ?>
        <span class="button button-disabled" ><?php _e('Authorize WooCommerce', self::$domain); //LANG: AUTHORIZE_BUTTON_2 ?></span>
        <?php else: ?>
        <a class="button button-primary" href='<?php echo $this->oauth3_url; ?>'><?php _e('Authorize WooCommerce', self::$domain); //LANG: AUTHORIZE_BUTTON_2 ?></a>
        <?php endif; ?>
    </p>
</div>
