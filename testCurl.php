<?php
$curl = curl_init();

$postHeader = array(
    "appCode:ITSM",
    "appKey:",
);
$postBody = array(
    'to[]' => '',
    'subject' => 'test email',
    'body' => 'this is a test email'
);

curl_setopt_array($curl, array(
    CURLOPT_URL => "https://itsm-dev.cmwf.ocp.uat.dbs.com/common-server/email/send",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT=> 30,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_CUSTOMREQUEST => "POST",
    CURLOPT_POSTFIELDS => http_build_query($postBody),
    CURLOPT_HTTPHEADER => $postHeader,
));

$response  = curl_exec($curl);
curl_close($curl);
var_dump($response);exit();


