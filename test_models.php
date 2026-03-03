<?php
$apiKey = 'AIzaSyCocLYcScDnEVZ025itq3_N9jL1ufDgTsU';
$url = 'https://generativelanguage.googleapis.com/v1beta/models?key=' . $apiKey;

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
curl_close($ch);

file_put_contents('models_list.json', $response);
echo "Saved to models_list.json";
?>
