<?php
session_start();
include_once (__DIR__.'/s4d/thawani.php');
$client_secret = $AppConfig['ecwid_secret']; // This is a dummy value. Place your client_secret key here. You received it from Ecwid team in email when registering the app
$cipher = "AES-128-CBC";
$iv = $AppConfig['iv']; // hqaj734yaclAL2Yyws4aO5LU// this can be generated random if you plan to store it for later but in this case e.g. openssl_random_pseudo_bytes($ivlen);
//$cipher = "aes-128-gcm";
$ivlen = openssl_cipher_iv_length($cipher);
$tag = 0;

// If this is a payment request
//$_POST['data'] = 'EWRYFsQIQgtIbPKGDNm9vbBe7Rzb0JJAHqa4lC9DjOxfbY1Pm8fgCbOtgjtE_X1R9G4b0ZUEVXKIZq1niBf19SR2Ck7Kr_Ivf_ec680CwcFCrIypYf36APMYjU7lqGIna0kTAPx1oUFg4qevHIemwX-s9d9Xo44comHLOi3OxM7eGZej6pDqrVYF0c1rmb015mv10HwfN4d6VqPPRfpBltwyD1dA-741isd0y9J27uihN_GKv8GxJ_55xoZAGjEu3RWQqj5inMRuVawYTHlWls7WlcaHS6uLHwVqA7258uVP5XUzo60Wq86S9ZU-_me3atbc6E2F1lZTN-1gz6LD88Koqx4EO-vTG2S09DUGkzJ7FTyBcCX3-FGDaO685fIOE7IvvhsfhkQJmKVRt0-xi4G-4P_zQKq5uHe_y-9v5nrYGfk04nptc87H18QWlcRxH8TbWR5SDOjWlrlAEjaTP3PYWT_Od0THEYq1zdVOZPA0gZT9zQ-VH825bd3ee8GAaVp73fPnqBtrplUmzNDVpzklw6CtX8YMcgSNA3VTXxUtTQBAZCQKo7G7-9LrwPHxvVEPpfwNZcOrzUVB96xlAr2n1pMsGDpQ_RwbR0pdlYzLWHQbkuXh2S7_dnrj5axMwOGhQ48A7jmHHx0EtQJkljUIw9G8KTBkpV2b46h8TtymApLsm5eSdW23N6XYRzh9gzsG_w5AM2l3W6u-rNFVIXThTjQbFs8DgUpc21wsC8020v1B0oevT4j8JbRjbBBC_d7dOc76MpJnOx71PdnvVyPXVsASvqwy37FlYEbMs5DZqQyIHGJMze0V0_J3wkwl0XGIXepo007dMLW6EYUn-Xi-JHmS6k1ajA_L9PwhVbRfYjpSlNf5aNhmLcGcpcHYVjdLCJwolf71K9cPqGSx7NPGs0PCow90rAjFaEONpk3Bo4j6jEJ8NkinMG9FEv07ZNTKmWot72L-Eb2n2kGW6s7A0S2twfzn1kqkynfI1DN9zNYUS4rqZc_ZQb8mOOXZyfZP9swoBApFCayuBLWiJmgmVNX9fFQx3KMvdLqwpWbxI-v9chmEcISoploesT3z-JDafnLzzmCfEWgEmYw6MTYzwlMpnOZgabRI-8jE5hEtFT9vbXSZeVYieRiGqhjAsZUE2xpvkeOghXjR3jnTX0ca3A7XdyiNI-169noOizfEV0SLUK2xDue0UU1KApmAtNegchrfZ1Z2S_aJtlbz_UHngHi-cfRI4SdvLqASn15GNroEIx0VY3xsxQ6ILiOJwjEHMFdJkuQJZ-mWNzy4QWp6T91Vck4IUAP_fAeZcQZVSXudQ8IYKHwIZK0leR0zRsq5gDvfUuxqhp0EPaHzj8vfygSkWZRUq9nR4vUg0iJNwg9EIPbsmEAQKg2L83SZB9g7vOdGCQOIC-GfaUgEQ6-gdqAbgnFYZKl7dhkWXscMqIWctqkOE-iAH0c8c4NZNnAC9yIKUXN6504YkWJxZ_ftN8cWHXtTsvtnoTFJ28BqDzpNq0M4pCUO7H8kUkb0VhAUZfYbWkb847FSk2ewh3NgU4-EzTuRbi7sjM4zvKS6MliDBL0r7Vt0d_j1fcnH5ft4R_LHpUD99DSSIiCrzYPBHnKPTW3VUr9c9bipNyOUSdKk4Ex_MXqSTJaWinZcsBSKabQb1J8-Q2-SKow-UYRt_ZYFObiCOPQYDDBZkztjSgWy2VLnfPm72JC52owtP0sZntkzTFTmv0Ie2dJCoh2BM1yDhe9Co0rB5OLDgP_i4rGVS-5sVV6rYwtGIvWMnRabIkDu4fXhqRKN0jRpHrxRoGOb18f2V_wquCuSKLBTMuMzVigTf0eMJsBBrwoXvZ1Ub4ZUX1PbsJ9r7nvpLeFIQQw2i0YvR6DlNvjrEe2qiKC33E8WGODtV-Xe69bcHebHEBNv2QwuR3kgCp51quJPnnTV4hVVux1o7LnA61AjIM_MPMQUfE_e5aTB2GrWHnfwRSzeJuEGZ4JxbR3wOj-OKg9PeqzkqckgXa8uSP17XPEIsW4QKfZxFwmO3zEJ3_GtWJt3wGultiRVAJfQOU-4lC3Cx2txOQ0Ftbrl1OBnQAILpvtcJaqZYUVq-4XHA4vtVoK6enZuvwapGx88HHHsDwfzmvO45i9w_XObivq3ndhP3hGPq7dL8C87yOua_gB9Q7ro3zS5CLoaZmUaz3AL7ApiltW5LX6Wy8-UVtZFK22r7-UyNVj0e7x6hdg2MV-sf4d5uHHf8UBdLbGhUFNuQPUKScfStduTZM1dz4rCCokwpEeg75ML1czBgWjkExDo-SX1TWMsiusI7PHa0RPnRhO_U0KlEu9E7EgyeCxte2dSzc4SWsjJENLFIltWER5RGayXgPXLYLob-zHPfgxh-Rmp-TsrOquHZ79y3kX9V1KiioHWIKmnhug34fCQufoIhCsT466fqHpbimAfHGVPXEhzLQJdm8X6YYOueUudffafNyFdlRYXjWKgakmVJK6pfJS8doF5ECKfVco8tHoLooRKvo-F5-AkCdsabhHHjOchqm26H2YTS4Af6HXvA_5IXA-JGRwt48KXmwAHHdpbRkTnNHP-9mifFZeBDLMiRn72yIcxfaHvdbeZxQqbm9Ps2oWf-4-PJVztG_Euku0AfcfMh4EM6cwzxc50ZbIC0kuth8Bnw04zKdLxfaphKF8xsVoA82QSLdCWQ4qXiux5BcuYNU5CfE0NKYQBMfJ3WAT-KSVFoDNskKvEeH2mYYhuS0omuC3qN2IJK8tEMvlq9lqQ9lyzEu5BgL_mAU2JdzVfUfuWGPudFHWBy8uGPuikOLjslh4pcr-wLHA1f-S__i7jLWEayRuSz7xfGJ8dWGBa12W93y5sfpvizRMFLlzap59rr4Kz_ZWKvTG6K3NDNCghxpapiREoJXMReQHawXX1myG6tbVyj3mU3vqXdBXw_Lmzewh-eLtYYF_pSQQypEXqyWwehy8U0D6Kgao8I4GPoiuVJpZ8LrQsA5cDxv7rHTH9XrGNbCQl1ghBFX-n4iybDe-gLfQiO1pbYN2AqyBABkWiHeA42tuRDVqGBDM0-lVmIyPlEdJwdrEpCWkTuHflDCLHTchG_s7VXau9I5M75RYOBVJpWIoA-6EvDkHZq_oOWAvmcpGWL7wbsPe8Zp8KsQS1FnmURXNVD1e7WEEJxO3c4l4KS9RHWJNfvhw8UoG-aT6cGKLsrhjiknjKfdE5VZeDosN87qGAciIkp374Wj6rLlD7EqNx7xI3xgWlTvggRDnXTBFjjm129pIioESgwPLa41yXtZcy3RYTtSytnKTTtVIu6HsdfKDYW2J68sMLeGIhZTHSHPdezksdBHcfE_cUjcLHVjiooxDtS3l4aGFVAa_P5qPj-H8eZt6vCbf9y7_yYtneukJNPFjb001C-RUYvr6AnUb3f-hKT3ebcleSoPaFJFr_8fgrstsHm5FRTJG-Umfdrt6nW-HSFAGrAGl-4rb7sGHd1kd8BpJadxNM7geJT6PlmVIVDHF_EVOSzzuo8Ha3cVtTp56DIyA56WRz4kCf8zdUzbYgMFB2bf3dPFUfy05xuCGK3GC4ukyPfAUsYCPT4uVDDLizQuNnvcmIUp1tBOz8eiE8Q8j8jlxupKS9GR9y1uLR';
//if(isset($_GET["callbackPayload"])) unset($_POST['data']);

// Ecwid now sends the payload in "enc_data" (AES-128-GCM). The legacy "data"
// field (AES-128-CBC) is kept as a fallback for older stores.
if (isset($_POST["enc_data"]) || isset($_POST["data"])) {

// Functions to decrypt the payment request from Ecwid

    function getEcwidPayload($app_secret_key, $data) {
        // Get the encryption key (16 first bytes of the app's client_secret key)
        $encryption_key = substr($app_secret_key, 0, 16);

        // Decrypt payload
        $json_data = aes_128_decrypt($encryption_key, $data);

        // Decode json
        $json_decoded = json_decode($json_data, true);
        return $json_decoded;
    }

    function aes_128_decrypt($key, $data) {
        // Ecwid sends data in url-safe base64. Convert the raw data to the original base64 first
        $base64_original = str_replace(array('-', '_'), array('+', '/'), $data);

        // Get binary data
        $decoded = base64_decode($base64_original);

        // Initialization vector is the first 16 bytes of the received data
        $iv = substr($decoded, 0, 16);

        // Current format: AES-128-GCM, where the last 16 bytes are the auth tag
        // and the payload is everything between the IV and the tag
        $tag = substr($decoded, -16);
        $payload = substr($decoded, 16, -16);
        $json = openssl_decrypt($payload, "aes-128-gcm", $key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($json === false) {
            // Legacy format: AES-128-CBC, payload is everything after the IV
            $json = openssl_decrypt(substr($decoded, 16), "aes-128-cbc", $key, OPENSSL_RAW_DATA, $iv);
        }

        return $json;
    }

    // Get payload from the POST and decrypt it
    $ecwid_payload = isset($_POST['enc_data'])? $_POST['enc_data'] : $_POST['data'];

    // The resulting JSON from payment request will be in $order variable
    $order = getEcwidPayload($client_secret, $ecwid_payload);
    if(is_array($order) && count($order) > 0){
        $ecwidReturnUrl = urlencode($order['returnUrl']??'');
        $isOMR = false;
        $exchangeRate = $order['merchantAppSettings']['money_exchange'];
        $exchangeRate = (float) !empty($exchangeRate) && is_numeric($exchangeRate)? $exchangeRate : 0;
        if(isset($order['cart']['currency']) && strtolower($order['cart']['currency']) == 'omr') {
            $isOMR = true;
            $exchangeRate = 1;
        }
        if($isOMR || $exchangeRate > 0) {
            $config = [
                'isTestMode' => (int)($order['merchantAppSettings']['testMode'] != 'false' ? 1 : 0), ## Set 1 to work with test mode
                'public_key' => $order['merchantAppSettings']['publishable_key'],
                'private_key' => $order['merchantAppSettings']['secret_key'],
                's4d_license' => $order['merchantAppSettings']['s4d_license'],
                'remoteLicenseOnly' => true,
            ];
            $thawani = new \s4d\payment\thawani($config, $AppConfig['s4d_secret'], function ($message) {
                ## Do nothing
            });
            if ($thawani->isActiveLicense) {
                $items = [];
                $totalPrice = 0;
//                $sysTotal = $order['cart']['order']['total'] * 1000;
                $sysTotal = ceil(currency_exchange($exchangeRate, $order['cart']['order']['total']) * 1000);
                if (is_array($order['cart']) && is_array($order['cart']['order']) && is_array($order['cart']['order']['items']) && count($order['cart']['order']['items']) > 0) {
                    foreach ($order['cart']['order']['items'] as $item) {
                        $unitPrice = ceil(currency_exchange($exchangeRate,$item['productPrice'] > 0? $item['productPrice'] : $item['price']) * 1000);
                        array_push($items, ['name' => $item['name'], 'unit_amount' => $unitPrice, 'quantity' => $item['quantity']]);
                        $totalPrice += ($unitPrice * $item['quantity']);
                    }
                }
                if ($sysTotal > $totalPrice) {
                    array_push($items, [
                        'name' => 'Other',
                        'unit_amount' => $sysTotal - $totalPrice,
                        'quantity' => 1,
                    ]);
                    $totalPrice += $sysTotal - $totalPrice;
                }
                $order['order']['id'] = empty($order['order']['id'])? $order['cart']['order']['id'] : $order['order']['id'];
                if(isset($order['cart']['order']['discountCoupon']) && isset($order['cart']['order']['discountCoupon']['discount']) && !empty($order['cart']['order']['discountCoupon']['discount'])){
                    $items = [];
                    array_push($items, [
                        'name' => 'Order '.$order['order']['id'],
                        'unit_amount' => $sysTotal,
                        'quantity' => 1,
                    ]);
                }
                if($totalPrice !=  $sysTotal){
                    $items = [];
                    array_push($items, [
                        'name' => 'Order '.$order['order']['id'],
                        'unit_amount' => $sysTotal,
                        'quantity' => 1,
                    ]);
                }
                parse_str(parse_url($order['returnUrl'])['query'], $orderUrl);
                $thawaniInvoiceId = rand(1000, 9999) . $orderUrl['orderId'];
                $ciphertext_raw = openssl_encrypt($order['token'], $cipher, $client_secret, 0, $iv);
                $callbackPayload = base64_encode($ciphertext_raw);
                $configPayload = base64_encode(openssl_encrypt(json_encode($config), $cipher, $client_secret, 0, $iv));
                $callbackUrl = "https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . "?storeId=" . $order['storeId'] . "&orderNumber=" . $order['cart']['order']['orderNumber'] . "&callbackPayload=" . $callbackPayload . '&config=' . $configPayload.'&callbackUrl=' . $ecwidReturnUrl;
                $meta = [
                    'client_reference_id' => $thawaniInvoiceId,
                    'products' => $items,
                    'success_url' => $callbackUrl . '&status=PAID',
                    'cancel_url' => $callbackUrl . '&status=CANCELLED',
                    'metadata' => [
                        'order_id' => $orderUrl['orderId'],
                        'storeId' => $order['storeId'],
                        'customerName' => isset($order['cart']['order']['shippingPerson']['name']) ? $order['cart']['order']['shippingPerson']['name'] : $order['cart']['order']['billingPerson']['name'],
                        'customerPhone' => isset($order['cart']['order']['shippingPerson']['phone']) ? $order['cart']['order']['shippingPerson']['phone'] : $order['cart']['order']['billingPerson']['phone'],
                        'customerEmail' => isset($order['cart']['order']['email']) ? $order['cart']['order']['email'] : '',
                    ]
                ];
                $order['cart']['order']['refererUrl'] = rtrim($order['cart']['order']['refererUrl'], '/').'/';
                $url = $thawani->generatePaymentUrl($meta);
                if (!empty($url)) {
                    $_SESSION['thawani_payment_session'] = $thawani->payment_id;
                } else {
                    $url = $order['cart']['order']['refererUrl'] . 'checkout/payment?errorMsg=' . htmlspecialchars('There is an error with gateway.<br />' . json_encode($thawani->responseData));
                }
            } else {
                $url = $order['cart']['order']['refererUrl'] . 'checkout/payment?errorMsg=' . htmlspecialchars('Thawani App is not licensed. You need to checkout <a href="https://www.s4d.om">Source for Development</a>');
            }
        }else{
            $url = $order['cart']['order']['refererUrl'] . 'checkout/payment?errorMsg=' . htmlspecialchars('Thawani gateway only support Omani Rial');
        }
        $url = str_replace('wix:', '', $url);
        header('location:'.$url);
        echo "<script>window.location = '$url';</script>";
        exit;
    }
}
// If we are returning back to storefront. Callback from payment

if (isset($_GET["callbackPayload"]) && isset($_GET["status"])) {
    $client_id = $AppConfig['ecwid_app'];
    $c = base64_decode($_GET['callbackPayload']);
    $token = openssl_decrypt($c, $cipher, $client_secret, 0, $iv);
    $config = openssl_decrypt(base64_decode($_GET['config']), $cipher, $client_secret, 0, $iv);
    $storeId = $_GET['storeId'];
    $orderNumber = $_GET['orderNumber'];
    $status = $_GET['status'];
    $returnUrl = "https://app.ecwid.com/custompaymentapps/$storeId?orderId=$orderNumber&clientId=$client_id";
    if(!empty($config)){
        $config = json_decode($config, true);
    }
    if(is_array($config) && !empty($token)){
//        echo '<pre dir="ltr">' . print_r(compact('config', '_GET', 'token', 'c'), true) . '</pre>'; exit;
        if(!empty($_GET['callbackUrl'])){
            $returnUrl = urldecode($_GET['callbackUrl']);
        }
        $thawani = new \s4d\payment\thawani($config, $AppConfig['s4d_secret']);
        $thawani->checkPaymentStatus($_SESSION['thawani_payment_session']);
        $json = json_encode(array(
            "paymentStatus" => $thawani->payment_status == 1? 'PAID' : 'CANCELLED',
            "externalTransactionId" => "transaction_".$orderNumber
        ));
        unset($_SESSION['thawani_payment_session']);
        $url = "https://app.ecwid.com/api/v3/$storeId/orders/transaction_$orderNumber";
        // Send request to update order
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer ' . $token, 'Content-Type: application/json', 'Content-Length: ' . strlen($json)));
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        header('location:'.$returnUrl);
        echo "<script>window.location = '$returnUrl'</script>";
        exit;
    }else{
        exit('wrong TOKEN');
    }
} else {

    header('HTTP/1.0 403 Forbidden');
    echo '`Access forbidden`!';

}
function currency_exchange($rate, $input){
    return $rate > 0? $rate * $input : $input;
}

?>
