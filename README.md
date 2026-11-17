# Ecwid Payment Apps (v2, Laravel 13)

Payment gateways for Ecwid stores, built so more gateways can be added next to
Thawani. v1 (plain PHP) is tagged `v1.0`; its code sits in `legacy/` for
reference until the port is finished.

## How a payment works

1. At checkout Ecwid POSTs the encrypted order to the app's `paymentUrl`
   (`enc_data`, AES-128-GCM; the older `data`, AES-128-CBC, is still accepted).
2. We check the store's S4D license with WHMCS and that it covers the app's
   gateway, save a `payment_transactions` row (Ecwid token stored encrypted),
   and send the shopper to the gateway's hosted checkout.
3. The gateway sends the shopper back to `/pay/{id}/return` (or `/cancel`). We
   ask the gateway for the real status. We never trust the URL.
4. We `PUT /api/v3/{storeId}/orders/{referenceTransactionId}` with `PAID`
   (or `INCOMPLETE` if it failed or was cancelled), then redirect to Ecwid's
   `returnUrl` (with `errorMsg` on failure).
5. `payments:reconcile` (scheduled every 10 minutes) settles payments whose
   shopper never came back, and retries failed Ecwid updates.

## Layout

| Path | What |
| --- | --- |
| `app/Payments/Contracts/PaymentGateway.php` | The interface every gateway implements |
| `app/Payments/Gateways/Thawani/` | Thawani, using `phpawcom/thawani_laravel` |
| `app/Payments/PaymentService.php` | Runs the payment flow, whatever the gateway |
| `app/Ecwid/` | Ecwid decryption, payment-request DTO, REST client, settings-page context |
| `app/Licensing/` | WHMCS license check and gateway ↔ license mapping |
| `app/Http/Controllers/Ecwid/` | Payment, settings page, storefront JS, v1 URL compatibility |
| `config/payments.php` | Registered gateways |
| `config/licensing.php` | WHMCS server and which license addon/product unlocks which gateway |

## Ecwid apps

Each Ecwid app (the public `thawani-gateway` app, or a store's private
`custom-app-…`) has its own client_id/secret and one gateway, stored in the
`ecwid_apps` table:

```bash
php artisan ecwid:import-legacy          # import the v1 apps from legacy/
php artisan ecwid:app custom-app-123-1 --gateway=thawani --secret=... --name="Thawani (shop.com)"
```

`ecwid:app` prints the URLs to give Ecwid:

- paymentUrl: `POST /ecwid/{client_id}/payment`
- iframeUrl (settings page): `GET /ecwid/{client_id}/settings`
- customJsUrl (storefront logos): `GET /ecwid/{client_id}/storefront.js`

The v1 URLs (`/payment-request.php`, `/index.html`, `/clientscript.js`,
`/stores/{domain}/payment-request.php`, `/stores/{domain}/index.php`) are also
routed. The web server must send them to Laravel, not try to run them as PHP
files.

Merchant settings stay in Ecwid app storage, with the same keys as v1
(`publishable_key`, `secret_key`, `money_exchange`, `testMode`,
`s4d_license`), so existing stores keep working without re-entering anything.
Ecwid sends them back in each payment request as `merchantAppSettings`.

## Licenses (WHMCS)

The license key is checked against `LICENSE_SERVER_URL/modules/servers/licensing/verify.php`.
`LICENSE_SECRET` must match the product's MD5 hash verification secret. Active
results are cached (`LICENSE_LOCAL_KEY_DAYS`), and stay usable for
`LICENSE_CHECK_FAIL_DAYS` more days if WHMCS cannot be reached.

What each gateway needs from the license is set in `config/licensing.php`:

- an Active addon whose name contains one of the gateway's `addons` strings, or
- a product name containing one of its `products` strings, or
- a license with no addons at all, for gateways in `allow_without_addons` (v1
  behaviour; currently Thawani only).

So when a new gateway ships, create a WHMCS addon for it (for example
"NewPay Gateway") and map it here. Existing licenses get it by adding that addon.

## Adding a gateway

1. Implement `App\Payments\Contracts\PaymentGateway` in `app/Payments/Gateways/<Name>/`.
2. Register it in `config/payments.php` and map it to a license addon in `config/licensing.php`.
3. Optional: `resources/views/ecwid/settings/<key>.blade.php` (help text),
   `resources/views/ecwid/storefront/<key>.blade.php` (checkout logos),
   `public/images/gateways/<key>-512x512.png` (logo).
4. Register the Ecwid app for it with `php artisan ecwid:app`.

## Development

```bash
composer install && cp .env.example .env && php artisan key:generate
php artisan migrate
php artisan test
```

Production also needs the scheduler (`* * * * * php artisan schedule:run`).
