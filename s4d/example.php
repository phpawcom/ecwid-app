<?php
session_start();
include('thawani.php');
$config = [
    'isTestMode' => 1, ## set it to 0 to use the class in production mode
    'public_key' => 'HGvTMLDssJghr9tlN9gr4DVYt0qyBy',
    'private_key' => 'rRQ26GcsZzoEhbrP2HZvLYDbn9C9et',
    's4d_license' => 'GCTH-bb08f85f2efb79fd'
];
include('s4d-production-keys.php'); ## Uncomment this file to test the class in production environment until you get your productions keys. NOTE: MAKE SURE TO TEST IN SMALL AMOUNTS LIKE 0.1 OMR (100 BISA)
$thawani = new \s4d\payment\thawani($config, '9Xb*BRt3vemqXGdF');
//$thawani->errorWarning = function($input){ echo '<b>'.$input.'</b>'; };
$thawani->debug = true;
$_REQUEST['op'] = !isset($_REQUEST['op'])? '' : $_REQUEST['op']; ## to avoid PHP notice message
switch ($_REQUEST['op']){
    default: ## Generate payment URL
        $orderId = '1001'; ## order number based on your existing system
        $input = [
            'client_reference_id' => rand(1000, 9999).$orderId,
            'products' => [
                ['name' => 'test test test test test test test test test test test test ', 'unit_amount' => 100, 'quantity' => 1],
//                ['name' => 'test', 'unit_amount' => 100, 'quantity' => 1],
            ],
            'customer_id' => 'cus_j74GyVLVDGMGccum',
            'success_url' => $thawani->currentPageUrl().'?op=checkPayment',
            'cancel_url' => $thawani->currentPageUrl().'?op=checkPayment',
            'metadata' => [
                'order_id' => $orderId
            ]
        ];
        $url = $thawani->generatePaymentUrl($input);
        echo '<pre dir="ltr">' . print_r($thawani->responseData, true) . '</pre>';
        $_SESSION['session_id'] = $thawani->payment_id;
        if(!empty($url)){
            ## method will provide you with a payment id from Thawani, you should save it to your order. You can get it using this: $thawani->payment_id
            ## header('location: '.$url); ## Redirect to payment page
            echo '<a href="'.$url.'">Click to Pay</a>';
        }
        break;
    case 'callback': ## handle Thawani callback, you need to update order status in your database or file system
        $result = $thawani->handleCallback(1);
        /*
         * $results contain some information, it will be like:
         * $results = [
         *  'is_success' => 0 for failed, 1 for successful
         *  'receipt' => receipt ID, generate for transaction
         *  'raw' => [ SESSION DATA ]
         * ];
         */
        if($thawani->payment_status == 1){
            ## successful payment
        }else{
            ## failed payment
        }
        break;
    case 'checkPayment':
        $check = $thawani->checkPaymentStatus($_SESSION['session_id']);
        if($thawani->payment_status == 1){
            ## successful payment
        }else{
            ## failed payment
        }
        $thawani->iprint_r($check);
        break;
    case 'createCustomer':
        $customer = $thawani->createCustomer('me@alrashdi.co');
        $thawani->iprint_r($customer);
        break;
    case 'getCustomer':
        $customer = $thawani->getCustomer('me@alrashdi.co');
        $thawani->iprint_r($customer);
        break;
    case 'deleteCustomer':
        $customer = $thawani->deleteCustomer('cus_vgpOo3Z91scRyFhA');
        $thawani->iprint_r($customer);
        break;
    case 'getPaymentMethods':
        $methods = $thawani->getPaymentMethods('cus_j74GyVLVDGMGccum');
        $thawani->iprint_r($methods);
        break;
    case 'getPaymentDetails':
        $result = $thawani->getPaymentDetails('202102172063');
//        $result = $thawani->getPaymentDetails();
        $thawani->iprint_r($result);
        break;
    case 'refundPayment':
        $result = $thawani->refundPayment('2021021716171', 'a test', [
//            'customer_name' => 'test',
            'order_id' => 1001
        ]);
        $thawani->iprint_r($result);
        break;
    case 'home':
        echo 'Get payment status from database';
        break;
}