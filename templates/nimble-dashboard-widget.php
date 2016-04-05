<?php

$totalavailable = $summary['totalAvailable']['amount'] / 100;
$total_str = number_format($totalavailable, 2, ',', '.');

$balance = $summary['balance']['amount'] / 100;
$balance_str = number_format($balance, 2, ',', '.');

?>
<div class="nimble-payments-dashboard">
    <div class="col-1 col">
        <span class="title"><?php _e('Account Balance', 'woocommerce-nimble-payments'); //LANG: BALANCE_DASHBOARD ?></span>
        <span class="result"><?php echo $balance_str; ?> €</span>
    </div>
    <div class="col">
        <span class="title"><?php _e('Available Balance', 'woocommerce-nimble-payments'); //LANG: TOTAL_AVAILABLE_DASHBOARD ?></span>
        <span class="result"><?php echo $total_str; ?> €</span>
    </div>
</div>