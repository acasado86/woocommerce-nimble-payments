<span>Identificador: <?php echo $user->ID;?></span><br>
    <?php foreach ($cards as $card) : ?>
    <input  type="radio" name="<?php echo $this->id; ?>_storedcard" <?php if ($card['default']) echo "checked";  ?> value="<?php echo base64_encode(json_encode($card)); ?>">
        <?php echo $card['maskedPan']; 
            if( $card['cardBrand'] == 'VISA') { ?><img src="<?php echo plugins_url('assets/images/visa-logo.png', plugin_dir_path(__FILE__));?>"/>&nbsp;&nbsp;&nbsp;&nbsp;<img style="cursor: pointer;" class="delete-card" src="<?php echo plugins_url('assets/images/delete-button.png', plugin_dir_path(__FILE__));?>"/>
            <?php } else if( $card['cardBrand'] == 'MASTERCARD') { ?><img src="<?php echo plugins_url('assets/images/mastercard-logo.png', plugin_dir_path(__FILE__));?>"/>&nbsp;&nbsp;&nbsp;&nbsp;<img  style="cursor: pointer;" class="delete-card" src="<?php echo plugins_url('assets/images/delete-button.png', plugin_dir_path(__FILE__));?>"/>
            <?php } else { ?><img src="<?php echo plugins_url('assets/images/credit-card.png', plugin_dir_path(__FILE__));?>"/> 
            <?php } ?>
    </input><br>
                
    <?php endforeach;?>
    <input type="radio" name="<?php echo $this->id; ?>_storedcard" value="">Nueva tarjeta <img src="<?php echo plugins_url('assets/images/credit-card.png', plugin_dir_path(__FILE__));?>"/></input>