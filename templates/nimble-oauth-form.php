<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
//$url = $this->getOauth3Url();
$button_class = 'button-primary';
?>
<h1><?php _e('Nimble Payments Advanced Options', $this->domain);?></h1>
<?php if ( false == $this->oauth3_url ): ?>
    <div class="updated message">
        <div class="squeezer">
            <h4><?php _e("You must activate Nimble Payments gateway to configure advanced options.", $this->domain); //LANG: TODO ?></h4>
            <p class="submit">
                <a class="button" href="<?php echo get_admin_url() . "admin.php?page=wc-settings&tab=checkout&section=wc_gateway_nimble" ?>" ><?php _e( 'Activate now', $this->domain ); //LANG: TODO ?></a>
            </p>
        </div>
    </div>
<?php endif; ?>
<p><?php _e('Desde WooCommerce podrás gestionar todas tus ventas, ver los movimientos de tu cuenta , hacer devoluciones, etc.', $this->domain); ?></p>
<p><?php _e('Para acceder a toda la potencia de Nimble Payments desde WooCommerce, es necesario que te identifiques y des permiso a WooCommerce a acceder a estos datos.', $this->domain); ?></p>
<p class="submit">
    <?php if ( false == $this->oauth3_url ): ?>
    <span class="button button-disabled" ><?php _e('Authorize WooCommerce', $this->domain); ?></span>
    <?php else: ?>
    <a class="button button-primary" href='<?php echo $this->oauth3_url; ?>'><?php _e('Authorize WooCommerce', $this->domain); ?></a>
    <?php endif; ?>
</p>
