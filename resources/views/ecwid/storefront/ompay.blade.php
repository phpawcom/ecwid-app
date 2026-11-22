/* Ecwid storefront script for {{ $app->name }}: card logos under the OmPay payment option. */
(function () {
    var appId = @json($app->client_id);
    var logos = [
        [@json(asset('images/gateways/ompay-54x34.png')), 'OmPay'],
        [@json(asset('images/gateways/mastercard.svg')), 'Mastercard'],
        [@json(asset('images/gateways/visa.svg')), 'Visa']
    ];

    function addLogos() {
        var labels = document.querySelectorAll('label[class*="' + appId + '"]');
        Array.prototype.forEach.call(labels, function (label) {
            if (label.querySelector('.s4d-gateway-logos')) return;
            var body = label.querySelector('.ec-radiogroup__body');
            if (!body) return;
            var wrap = document.createElement('div');
            wrap.className = 'ec-radiogroup__info s4d-gateway-logos';
            var inner = '<div class="ec-cart__accept ec-cart-accept"><div class="ec-cart-accept__icons"><div><div>';
            logos.forEach(function (logo) {
                inner += '<img class="ecwid-PaymentMethodsBlockSvg" src="' + logo[0] + '" alt="' + logo[1] + '" width="54" height="34">';
            });
            wrap.innerHTML = inner + '</div></div></div></div>';
            body.parentNode.insertBefore(wrap, body.nextSibling);
        });
    }

    function init() {
        Ecwid.OnPageLoaded.add(function (page) {
            if (page.type === 'CHECKOUT_PAYMENT_DETAILS') addLogos();
        });
    }

    if (window.Ecwid && Ecwid.OnAPILoaded) {
        Ecwid.OnAPILoaded.add(init);
    } else {
        window.addEventListener('load', function () { if (window.Ecwid) init(); });
    }
})();
