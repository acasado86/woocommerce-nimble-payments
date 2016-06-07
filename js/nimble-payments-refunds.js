/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

jQuery( function ( $ ) {
    jQuery('button.do-api-refund').addClass('do-nimble-refund').removeClass('do-api-refund');
    
    /*global woocommerce_admin_meta_boxes, woocommerce_admin, accounting, woocommerce_admin_meta_boxes_order */
    $('button.do-nimble-refund').on( 'click', function () {
        var refund_amount = $( 'input#refund_amount' ).val();
        var refund_reason = $( 'input#refund_reason' ).val();

        // Get line item refunds
        var line_item_qtys       = {};
        var line_item_totals     = {};
        var line_item_tax_totals = {};

        $( '.refund input.refund_order_item_qty' ).each(function( index, item ) {
                if ( $( item ).closest( 'tr' ).data( 'order_item_id' ) ) {
                        if ( item.value ) {
                                line_item_qtys[ $( item ).closest( 'tr' ).data( 'order_item_id' ) ] = item.value;
                        }
                }
        });

        $( '.refund input.refund_line_total' ).each(function( index, item ) {
                if ( $( item ).closest( 'tr' ).data( 'order_item_id' ) ) {
                        line_item_totals[ $( item ).closest( 'tr' ).data( 'order_item_id' ) ] = accounting.unformat( item.value, woocommerce_admin.mon_decimal_point );
                }
        });

        $( '.refund input.refund_line_tax' ).each(function( index, item ) {
                if ( $( item ).closest( 'tr' ).data( 'order_item_id' ) ) {
                        var tax_id = $( item ).data( 'tax_id' );

                        if ( ! line_item_tax_totals[ $( item ).closest( 'tr' ).data( 'order_item_id' ) ] ) {
                                line_item_tax_totals[ $( item ).closest( 'tr' ).data( 'order_item_id' ) ] = {};
                        }

                        line_item_tax_totals[ $( item ).closest( 'tr' ).data( 'order_item_id' ) ][ tax_id ] = accounting.unformat( item.value, woocommerce_admin.mon_decimal_point );
                }
        });

        var data = {
                action:                 'woocommerce_refund_line_items_nimble_payments',
                order_id:               woocommerce_admin_meta_boxes.post_id,
                refund_amount:          refund_amount,
                refund_reason:          refund_reason,
                line_item_qtys:         JSON.stringify( line_item_qtys, null, '' ),
                line_item_totals:       JSON.stringify( line_item_totals, null, '' ),
                line_item_tax_totals:   JSON.stringify( line_item_tax_totals, null, '' ),
                restock_refunded_items: jQuery( '#restock_refunded_items:checked' ).size() ? 'true' : 'false',
                security:               woocommerce_admin_meta_boxes.order_item_nonce
        };

        $.post( ajaxurl, data, function( response ) {
                if ( true === response.success ) {
                        // Redirect to OTP PAGE
                        window.location.href = response.data.otp_url;
                } else {
                        window.alert( response.data.error );
                }
        });
    });
    
    if ( typeof np_refund_info !== 'undefined') {
        if (np_refund_info.result !== 'OK'){
            alert(np_refund_info.error);
        } else {
            var messageDiv = '<div id="nimble-refund-message" class="updated woocommerce-message"><h4>' + np_refund_info.process_message + '<span class="spinner is-active"></span></h4></div>';
            $(messageDiv).insertAfter(jQuery("div[class='wrap'] > h1"));
            //CALL WOOCOMMERCE REFUND AJAX
            var data = {
                    action:                 'woocommerce_refund_line_items',
                    order_id:               np_refund_info.data.order_id,
                    refund_amount:          np_refund_info.data.refund_amount,
                    refund_reason:          np_refund_info.data.refund_reason,
                    line_item_qtys:         np_refund_info.data.line_item_qtys,
                    line_item_totals:       np_refund_info.data.line_item_totals,
                    line_item_tax_totals:   np_refund_info.data.line_item_tax_totals,
                    api_refund:             np_refund_info.data.api_refund,
                    restock_refunded_items: np_refund_info.data.restock_refunded_items,
                    security:               np_refund_info.data.security,
                    otp_token:              np_refund_info.data.token
            };

            $.post( woocommerce_admin_meta_boxes.ajax_url, data, function( response ) {
                    if ( true === response.success ) {
                            window.location.href = window.location.href;
                    } else {
                            window.alert( response.data.error );
                            $('#nimble-refund-message').hide();
                    }
            });
        }
    }
});