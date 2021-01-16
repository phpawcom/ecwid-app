<?php
error_reporting(E_ALL & !E_NOTICE);
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
        ], 'IF%x37NF6!fW$X4V', function($message) use ($response){
            $response['message'] = $message;
            echo json_encode($response); exit;
        });
        if(strtolower($thawani->license['status']) == 'active'){
            $response['status'] = 1;
        }
        break;
}
echo json_encode($response);
