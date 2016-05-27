<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
//$url = $this->getOauth3Url();
$totalavailable = $summary['data']['available'] / 100;
$total_str = number_format($totalavailable, 2, ',', '.');

$balance = $summary['data']['accountBalance'] / 100;
$balance_str = number_format($balance, 2, ',', '.');

$holdback = $summary['data']['hold'] / 100;
$holdback_str = number_format($holdback, 2, ',', '.');
?>
<div class="wrap">
    <h1><?php _e('Nimble Payments Summary', 'woocommerce-nimble-payments'); //LANG: SUMMARY_TITLE_2 ?></h1>
</div>

<div class="nimble-payments-summary">
    <div class="col-1 col">
        <h2><?php bloginfo( 'name' ); ?></h2>
        <div class="padding">
            <p class="resume-info">
                <span class="item">
                    <span class="link">
                        <span class="padding-item">
                            <span class="title"><?php _e('Balance account', 'woocommerce-nimble-payments'); //LANG: BALANCE_TITLE ?></span>
                            <span class="result"><?php echo $balance_str; ?> €</span>
                            <span class="tooltip">
                                <span class="tooltip-link" title="<?php _e('Balance account includes total amount available plus hold back.', 'woocommerce-nimble-payments'); //LANG: BALANCE_TOOLTIP ?>">?</span>
                            </span>
                        </span>
                    </span>
                </span>
            </p>
        </div>
        <div class="padding">
            <p class="resume-info">
                <span class="item">
                    <span class="link">
                        <span class="padding-item">
                            <span class="title"><?php _e('Hold back', 'woocommerce-nimble-payments'); //LANG: HOLD_BACK_TITLE ?></span>
                            <span class="result"><?php echo $holdback_str; ?> €</span>
                            <span class="tooltip">
                                <span class="tooltip-link" title="<?php _e('Total hold back due to refunds or disputes.', 'woocommerce-nimble-payments'); //LANG: HOLD_BACK_TOOLTIP ?>">?</span>
                            </span>
                        </span>
                    </span>
                </span>
            </p>
        </div>
    </div>
    <div class="col">
        <h2><?php _e('Total available', 'woocommerce-nimble-payments'); //LANG: TOTAL_AVAILABLE_HEADER ?></h2>
        <div class="padding">
            <p class="resume-info primary">
                <span class="item">
                    <span class="link">
                        <span class="padding-item">
                            <span class="title"><?php _e('Total available', 'woocommerce-nimble-payments'); //LANG: TOTAL_AVAILABLE_TITLE ?></span>
                            <span class="result"><?php echo $total_str; ?> €</span>
                            <span class="tooltip">
                                <span class="tooltip-link" title="<?php _e('Total available is the amount available for you to operate.', 'woocommerce-nimble-payments'); //LANG: TOTAL_AVAILABLE_TOOLTIP ?>">?</span>
                            </span>
                        </span>
                    </span>
                </span>
            </p>
        </div>
    </div>
</div>