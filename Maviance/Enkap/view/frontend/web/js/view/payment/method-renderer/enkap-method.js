/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define(
    [	 
        'Magento_Checkout/js/view/payment/default',
		'jquery'
    ],
        function (Component, $) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Maviance_Enkap/payment/enkap',
				redirectAfterPlaceOrder: false
            },

            /** Returns send check to info */
            getMailingAddress: function() {
                return window.checkoutConfig.payment.checkmo.mailingAddress;
            },
			 afterPlaceOrder: function () {
				 var enkapafterPlaceOrder = window.checkout.baseUrl+"enkap/payment/redirect";
                $.mage.redirect(enkapafterPlaceOrder);
            },
			 getInstructions: function () {
                return window.checkoutConfig.payment.instructions[this.item.method];
            },

           
        });
    }
);
