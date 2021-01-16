<?php
error_reporting(E_ALL & !E_NOTICE);
include_once (__DIR__.'/config.php');
include_once (__DIR__.'/s4d/thawani.php');
$response = ['status' => (int) 0, 'message' => ''];
header('Content-Type: application/json; charset=utf-8');
switch ($_REQUEST['op']){
    default: break;
    case 'checkLicense':
        $thawani = new s4d\payment\thawani([
            'isTestMode' => 1, ## Set 1 to work with test mode
            'public_key' => '-',
            'private_key' => '-',
            's4d_license' => $_POST['s4d_license'],
            'remoteLicenseOnly' => true,
        ], $AppConfig['s4d_secret'], function($message) use ($response){
            $response['message'] = $message;
            echo json_encode($response); exit;
        });
        if(strtolower($thawani->license['status']) == 'active'){
            $response['status'] = 1;
        }
        break;
    case 'verifyThawani':
        $thawani = new s4d\payment\thawani([
            'isTestMode' => (int) (strtolower($_POST['testMode']) == 'on'? 1 : 0), ## Set 1 to work with test mode
            'public_key' => $_POST['publishable_key'],
            'private_key' => $_POST['secret_key'],
            's4d_license' => $_POST['s4d_license'],
            'remoteLicenseOnly' => true,
        ], $AppConfig['s4d_secret'], function($message) use ($response){
            $response['message'] = $message;
            echo json_encode($response); exit;
        });
        $url = $thawani->generatePaymentUrl([
            'client_reference_id' => rand(100000, 999999),
            'products' => [
                ['name' => 'This to test the API keys', 'unit_amount' => 100, 'quantity' => 1],
            ],
            'success_url' => $thawani->currentPageUrl().'?op=checkPayment',
            'cancel_url' => $thawani->currentPageUrl().'?op=checkPayment',
            'metadata' => [
                'EcwidTest' => 'This request from APP settings for testing the API'
            ]
        ]);
        if(!empty($url)){
            $response['status'] = 1;
        }else{
            $response['message'] = isset($thawani->responseData['detail'])? $thawani->responseData['detail'] : json_encode($thawani->responseData);
        }
        break;
}
echo json_encode($response);
