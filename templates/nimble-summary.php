<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
//$url = $this->getOauth3Url();
?>
<div class="wrap">
    <h1><?php echo $title; ?></h1>
    <span>Total Available:</span> <strong><?php echo $summary['totalAvailable']['amount'] / 100; ?></strong> <br/>
    <span>Balance:</span> <strong><?php echo $summary['balance']['amount'] / 100; ?></strong> <br/>
    <span>Hold Back:</span> <strong><?php echo $summary['holdBack']['amount'] / 100; ?></strong> 
</div>
