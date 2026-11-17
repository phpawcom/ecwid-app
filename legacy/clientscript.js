Ecwid.OnPageLoaded.add(function(page){
    if(page.type == 'CHECKOUT_PAYMENT_DETAILS' && $('label[class*="thawani-gateway"]').length && !$('label[class*="thawani-gateway"] .ec-cart__accept').length){
        $('label[class*="thawani-gateway"] .ec-radiogroup__body').after('<div class="ec-radiogroup__info">\n' +
            '         <div class="ec-cart__accept ec-cart-accept">\n' +
            '            <div class="ec-cart-accept__icons">\n' +
            '               <div>\n' +
            '                  <div class="ecwid-paymentPaypal"><img class="ecwid-PaymentMethodsBlockSvg" src="https://apps.s4d.om/ecwid-thawani/images/thawani-54x34.png" alt="Thawani" width="54" height="34"><img class="ecwid-PaymentMethodsBlockSvg" src="https://apps.s4d.om/ecwid-thawani/images/mastercard.svg" alt="master card" width="54" height="34"><img class="ecwid-PaymentMethodsBlockSvg" src="https://apps.s4d.om/ecwid-thawani/images/visa.svg" alt="visa" width="54" height="34"></div>\n' +
            '               </div>\n' +
            '            </div>\n' +
            '         </div>\n' +
            '      </div>');
    }
});