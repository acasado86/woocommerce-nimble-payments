/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */


jQuery(document).ready(function () {
    //Button link Authorize
    jQuery("#np-oauth3.button").click(function(event) {
        event.preventDefault();
        jQuery.ajax({
            type: 'POST',
            dataType: 'json',
            url: ajaxurl,
            data: {
                'action': 'nimble_payments_oauth3'
            },
            success: function (data) {
                jQuery( location ).attr("href", data['url_oauth3']);
                //console.log(data['url_oauth3']);
            },
            error: function (data) {
                console.log(data);
            }
        });
    });
    
    //Button refund
    if (jQuery("#np-oauth3.button").length > 0 && jQuery(".refund-actions button.do-api-refund").length > 0 ){
        jQuery(".refund-actions button.do-api-refund").addClass('authorize-refund')
        jQuery(".refund-actions button.do-api-refund").removeClass('do-api-refund');
        //jQuery(".refund-actions").prepend('<button class="button button-primary" type="button">Refund <span class="wc-order-refund-amount"><span class="amount">0,00€</span></span> manually</button>');
        jQuery(".refund-actions button.authorize-refund").click(function(event) {
            jQuery("#np-authorize-message").addClass('error');
            jQuery(document).scrollTop(jQuery("html").offset().top);
        });
    }
});