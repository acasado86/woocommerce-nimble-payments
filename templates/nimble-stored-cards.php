<span>Usuario: <?php echo $user->ID;?></span>
<select name="<?php echo $this->id; ?>_storedcard">
    <?php foreach ($cards as $card) : ?>
    <option <?php if ($card['default']) echo 'selected="selected"';  ?>value="<?php echo base64_encode(json_encode($card)); ?>"><?php echo $card['maskedPan'] . ', ' . $card['cardBrand']; ?></option>
    <?php endforeach;?>
    <option value="">Nueva tarjeta</option>
</select>