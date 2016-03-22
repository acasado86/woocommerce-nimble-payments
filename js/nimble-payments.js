/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */


jQuery(document).ready(function () {
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
});