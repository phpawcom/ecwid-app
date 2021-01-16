<?php

$client_secret = "uozHNpknoA2lKQGAu7nVJmBiga309iAv"; // This is a dummy value. Place your client_secret key here. You received it from Ecwid team in email when registering the app
//$cipher = "AES-128-CBC";
$iv = "abcdefghijklmnopqrstuvwx";// this can be generated random if you plan to store it for later but in this case e.g. openssl_random_pseudo_bytes($ivlen);
$cipher = "aes-128-gcm";
$ivlen = openssl_cipher_iv_length($cipher);
$tag = 0;

// If this is a payment request
$_POST['data'] = '456QJ7r5xIiqK-B1FnNm-EnxJIN2xojCjWRqtu2zFyNxSFKh8724ro2dvjDX7gVw6F4ZbOErkJzd1ijO446MjYwe0Q-PyQMpDV2ayBTdYBJbg0NkyXWdjA5q3qiz0vkOALkemMj4q_XI-QcZxWCzCJkq5exca1ed8SA_-NQHAPAMufyCfMhdF4zpw2ybHsAb-LX-AtMqDP0cc9l-0S2mvJRqsOHa-WDzICW0VHFmSZXO_CFiwERBfN7prUe6Zxl6dWTEKGozvS8RpeMO673gKaKXzrnO8w2pPQiUDOvVwsZqklhqUADG_re5SZecSLBZlxT5WUZ0UvCkxjOkufOW6-uPRIgptsk1ChpUkzKZNFrxjfSZzkwknJMBLFUMKlDuNYa3EOrq7agZSd_wjk0DEUXPpkrHbvFPdXOfWJZX8D98Gujxh_yDJC4R7GeNiLAP3mAciiI8c6En_U06wOHjH7WqF-4XJwiE4pc4LB8kzSClf8fabGYquofQ3zDnVhrywSXkDuqguC4Py32xOXO4USQlBYnmO3LIsUATXJ0XSv12EV7Ll_zRyDI-TmRzbCpzlrWbzI_kBvmqU3tp13OdwgRIe_AyQQcEhn9-6ogxVvKHEndlOdEyXegcK5FLvJ3895TeQISv9OA9ihmsjZXdSc7kdCkzy5NgPTHHp3-MEjISW6QSRwT_9x8htrR5TL3_5dJLOIDUbdii6YQN-Pt_6IY-467Kf21TbKTb9LN_SAvxeqt0JWALscejWlDG-oKhME5M5XvU-HgYmg_hQ71TkDc661-BZMdDF5w55TDqu_z_Z9pev7o3SGSOiz761wx5mUYMUkaLZgeqeZeM9VyGaoDjUFBYF5lgyUC8S8EWz37-0lwV_P__Ev7uuUsWOlXuF_cS388-u3WVB7AWAnA_4ZdlCT2GwWP-z6XJGIPqqj9WqR-QKaBId_t7tqSJTkmToma9cFkqTxoSE4ye63-h_FEt3AV2bHbcaTLLdyro9jZybKCWcZI_C5MVo2N-VRoUUO8sRyNw2W6EUhyU8qwOOyCUO3HMZfe4yk_rSCreggLeip6Osyi4qOrK4DaBKKKJ2kRwIzJRiYZKIIG_ADoqQg3os79dXgy-j-Ko-_NQPr1KUWXOpEvoFAUGZviyDI5ECcodf3tOCP3y6U87WUCBtrI0rZZinO6JxLDTjXBzUcwxJO_fJwYWBUErY90HYn0enqq7dl3KTt2bPkEnsJ9bMTCAYteibQLTykuAL6KAbU2clWnDcu_oHUFsVoiMxlCzha2_sPmZewbZfw8Fg1_eFm6SzW4fAXqr7uZQ8hLDNXFbGCDy-EZyBV7hpoQbH3ozNx7bCtOQYAcA7GRbDTA-utxMMrxZMR-XGy8-FwVQHTz07N8NCKwIntkrIsTgirUTyReFy_gnWUOvrOKTtqNlxJVq2x1kyF1KMOnXq6y7MBGng6SSGuEQq8Pg2PrU9CdvZM-UEfV7M-Kxa7BEcu1gZAaF1RrS2CT6HfyZ8BPNWRPNFUWzhS4Q3pnoCc-hehxao0b3Hs0iK1IyXK901A5UKN-GEWEgZuSLnOp-4t_eeLhVPWswxVgsueRmXt5i8TE817C9AHtY0qIE0TU6wfErdgWkAad25C9ya25JfFkSbkJhqoDBkhS-fr4nnIjqxowpKTsgI8VRDr8_DyAA8n0Zz9fdYCNLeBoeGyc_XnwYKR0uOKMtKHWd8CQpFJ2F-EcTXnFteVnRlRNVugOeF9aIw5834eiSLlMvZ3HTIGzVx9uv8NtZrl4eVrigKkGLCSlTcIiPel0SyHIKjSShAw0C20DSJpSl020-u5zcmwUkbnu9TGm_XBaxx9cNf69whfsCSM4nDhjtuFsNzq0c71wNml3caZXI6rB6PT3VBCgWhOgF0YeuekKAX8DNKcQ1CSC4WOslTMbcIskD4RmBe-ej3bdlP3SmyJ3tfdGbk0jZ9sLh-ApRhnKZGvX7tp2NH89gqIs4kbwTr75tNaoWZ7Y_-MbnAhh9JIliEmGEo8UaJ0lTnUwlKjpKgrOMCFiTBZaXqEah21SmiQvv-gDdhju__unAs8_zjc3l0tee4-XrbSQKZ9yog-PVw1lQs4-MmZVRJ7JjY22Fw-ed9GFqIT_js-ii74G26u7lagsV8YLd3QDn1vD_mEkjsiaSRTKBLjYpWqnNehhnwflLudwgVmeMQzUypM9GnC9hFot9rBauTvkxt8KTHypyy0iOVQzja8Yh9CBCVGQn7VGFi6stSvBsss6sb6p5JdrVGUglYongZju8dU3xYEmpq8zUnSTKPU8mLyOk_hI0pjzkiVbhJxD19TF45c6oI2N58Aewkp-l9n19nDE4muwadc8GyXbJyw4r78BCtVQtnMGGs09HOLPy6EITZVa0X7IQqUwe-fY40L8Q-gRtRXJVs4VTCIeRkcU8D6aFOLYh3Emy10vO9JifCZ2FiJWUXEUihUmpVFb3mIv2damkZZsCKULdoYdW09eYOJijbthf9FwuOwJQ4BQoi_LKfoTqmNSZCS0Zbqvvetc9aIw7qz4UMJei8rlbiXKZS1cFdjrvcJ5JxTuMIPHXO0mcon4iHaWthsKj3hMnV3A8FY7gwSEFn5edczBwVbPqM-ZvoBYJMgV5JwMExEpitXlHznnEseobcX7TG1MnN3_KeIX79E7CVbf96-zJnGHGOgRh5vhugrx1brXZrK3XNMEZv_z9Myo0heDGniDOaa0AI1mS08RmKXPiNg7tZ7gUs8Rfj8aurjvCWa4csHKw1ts_LK36iTew7aQ9IyuzUHqQbjGBaM0P_3VTnM5KgG5HlbXHola9wE8r5AbKwrkoRAzHmyA04qO-IyzBjCXEgMXgjT3mrJwnn9bMCe8V1F6ikXEIoXvyoL7aFTRazA38aWOQpFm8tce0Kjg26bU10UG6uy8tvcYjN9DCl7FGfTn6Ijux9e0xcaOt4024tioXuT2u6ZAhfA52cx4mLh3lCCcM05DAfYtfvXi3Mhb4wDVP_cXT0MeWEDEtNkENvn8Pw8dXGirYee7Gzbam4n_wIR7yd2ORMEm1so_XoBag6ytUX9v_3QN8MHM6hr4HFlVcNj5rGSshGNDfXeHt50oTW5wTeroDIgyqV5z49qIz1DyrGeOIkp1xotuaBb_H9ISfi-FrS9vci3acfdQgott_VNAvqUJDveeldEufyVxZjcB_oNmXiYDCCIVrAiKIVe_zVEO82MrSxlkQP_0He406nTP3WdXp-s8FxRmchH4kQfsWMYwlkpl1PSwrhe61DlH8fHZ3l46DTaTdlMTD1VvW_gNNXzLLINF435apJZQUF4l9dGNiz841dVx-5T63w6cWzEYGg1VjhbeIYR0ppg3enC3-fnpJra4mKWyUuYZYt_Bv9NVUMtj4zJGhH4uCORAGwCKtirfrT_ryM97xpwFFZe6sgMLTshSnk4ZEYyPECPDkCuBHDvlYfLfLePxTJmKRxoqOqxvRs9WtlrShXa3XtsM';
if (isset($_POST["data"])) {

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

    // The payload itself is is the rest of the received data
    $payload = substr($decoded, 16);

    // Decrypt raw binary payload
    $json = openssl_decrypt($payload, "aes-128-cbc", $key, OPENSSL_RAW_DATA, $iv);
    //$json = mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $key, $payload, MCRYPT_MODE_CBC, $iv); // You can use this instead of openssl_decrupt, if mcrypt is enabled in your system

    return $json;
  }

  // Get payload from the POST and decrypt it
  $ecwid_payload = $_POST['data'];

  // The resulting JSON from payment request will be in $order variable
  $order = getEcwidPayload($client_secret, $ecwid_payload);

  // Debug preview of the request decoded earlier
  echo "<h3>REQUEST DETAILS</h3>";

      // Account info from merchant app settings in app interface in Ecwid CP
    echo '<pre dir="ltr">' . print_r($order, true) . '</pre>';
      $x_account_id = $order['merchantAppSettings']['merchantId'];
      $api_key = $order['merchantAppSettings']['apiKey'];

      // OPTIONAL: Split name field into two fields: first name and last name
      $fullName = explode(" ", $order["cart"]["order"]["billingPerson"]["name"]);
      $firstName = $fullName[0]; $lastName = $fullName[1];

      // Encode access token and prepare calltack URL template
      $ciphertext_raw = openssl_encrypt($order['token'],$cipher, $client_secret,$options=0,$iv,$tag);
      $callbackPayload = base64_encode( $ciphertext_raw);
      $callbackUrl = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]"."?storeId=".$order['storeId']."&orderNumber=".$order['cart']['order']['orderNumber']."&callbackPayload=".$callbackPayload;

      // Request parameters to pass into payment gateway
      $request = array(
        "x_account_id" => $x_account_id,
        "x_api_key" => $api_key,
        "x_amount" => $order["cart"]["order"]["total"],
        "x_currency" => $order["cart"]["currency"],
        "x_customer_billing_address1" => str_replace(PHP_EOL, ' ' , $order["cart"]["order"]["billingPerson"]["street"]),
        "x_customer_billing_city" => $order["cart"]["order"]["billingPerson"]["city"],
        "x_customer_billing_country" => $order["cart"]["order"]["billingPerson"]["countryCode"],
        "x_customer_billing_state" => $order["cart"]["order"]["billingPerson"]["stateOrProvinceCode"],
        "x_customer_billing_postcode" => $order["cart"]["order"]["billingPerson"]["postalCode"],
        "x_customer_email" => $order["cart"]["order"]["email"],
        "x_customer_first_name" => $firstName,
        "x_customer_last_name" => $lastName,
        "x_customer_phone" => $order["cart"]["order"]["billingPerson"]["phone"],
        "x_customer_shipping_address1" => str_replace(PHP_EOL, ' ', $order["cart"]["order"]["shippingPerson"]["street"]),
        "x_customer_shipping_city" => $order["cart"]["order"]["shippingPerson"]["city"],
        "x_customer_shipping_country" => $order["cart"]["order"]["shippingPerson"]["countryCode"],
        "x_customer_shipping_state" => $order["cart"]["order"]["shippingPerson"]["stateOrProvinceCode"],
        "x_customer_shipping_postcode" => $order["cart"]["order"]["shippingPerson"]["postalCode"],
        "x_customer_shipping_phone" => $order["cart"]["order"]["shippingPerson"]["phone"],
        "x_description" => "Order number". $order['cart']['order']['referenceTransactionId'],
        "x_reference" => $order['cart']['order']['referenceTransactionId'],
        "x_url_success" => $callbackUrl."&status=PAID",
        "x_url_error" => $callbackUrl."&status=CANCELLED",
        "x_url_cancel" => $order["returnUrl"]
      );

        // Sign the payment request
        $signature = payment_sign($request,$api_key);
        $request["x_signature"] = $signature;

        // Print the request variables to debug
        echo "<br/>";
        foreach ($request as $name => $value) {
          echo "$name: $value<br/>";
        }
        echo "<br/>";

        // Print form on a page to submit it from a button press
        echo "<form action='https://example.paymentpage.com/checkout' method='post' id='payment_form'>";
            foreach ($request as $name => $value) {
                echo "<input type='hidden' name='$name' value='$value'></input>";
            }
        echo "<input type='submit' value='Submit'>";    
        echo "</form>";
        echo "<script>document.querySelector('#payment_form).submit();</script>";

}

      // Function to sign the payment request form
      function payment_sign($query, $api_key) {
            $clear_text = '';
            ksort($query);
            foreach ($query as $key => $value) {
                if (substr($key, 0, 2) === "x_") {
                    $clear_text .= $key . $value;
                }
            }
            $hash = hash_hmac("sha256", $clear_text, $api_key);
            return str_replace('-', '', $hash);
      }


// If we are returning back to storefront. Callback from payment

if (isset($_GET["callbackPayload"]) && isset($_GET["status"])) {

    // Set variables
    $client_id = "thawani-gateway-dev";
    $c = base64_decode($_GET['callbackPayload']);
    $token = openssl_decrypt($c, $cipher, $client_secret, $options=0, $iv,$tag);
    $storeId = $_GET['storeId'];
    $orderNumber = $_GET['orderNumber'];
    $status = $_GET['status'];
    $returnUrl = "https://app.ecwid.com/custompaymentapps/$storeId?orderId=$orderNumber&clientId=$client_id";

    // Prepare request body for updating the order
    $json = json_encode(array(
        "paymentStatus" => $status,
        "externalTransactionId" => "transaction_".$orderNumber
    ));

    // URL used to update the order via Ecwid REST API
    $url = "https://app.ecwid.com/api/v3/$storeId/orders/transaction_$orderNumber?token=$token";
  
    // Send request to update order
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json','Content-Length: ' . strlen($json)));
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS,$json);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    // return customer back to storefront
    echo "<script>window.location = '$returnUrl'</script>";

}

else { 

  header('HTTP/1.0 403 Forbidden');
  echo 'Access forbidden!';

}


?>