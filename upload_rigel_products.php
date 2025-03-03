<?php

require 'vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$apiUrl = "https://devadmin.medsol.technology/api/rigel-products";
$authToken = $_ENV['API_TOKEN'];

$jsonFile = 'rigel_products.json';
if (!file_exists($jsonFile)) {
    die("Error: JSON file not found.\n");
}

$jsonData = file_get_contents($jsonFile);
$products = json_decode($jsonData, true);

if (!$products) {
    die("Error: Failed to decode JSON.\n");
}

$failedDir = 'failed';
if (!is_dir($failedDir)) {
    mkdir($failedDir, 0777, true);
}

$failedFile = $failedDir . '/failed_uploads_' . date('Y-m-d') . '.txt';
$failedUploads = [];

$totalProducts = count($products);
$currentProduct = 0;

function sendPostRequest($url, $token, $data)
{
    $ch = curl_init($url);
    
    $payload = json_encode(["data" => $data]);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ["http_code" => $httpCode];
}

foreach ($products as $product) {
    $currentProduct++;
    
    $formattedData = [
        "product_name" => $product["product_name"],
        "product_identifier" => $product["product_identifier"],
        "product_images" => $product["product_images"],
        "product_subtitle" => $product["product_subtitle"],
        "product_description" => $product["product_description"],
        "technical_specifications" => $product["technical_specifications"]
    ];

    $result = sendPostRequest($apiUrl, $authToken, $formattedData);

    if ($result["http_code"] === 200 || $result["http_code"] === 201) {
        echo "Uploading $currentProduct/$totalProducts: " . $product["product_identifier"] . " - Success\n";
    } else {
        echo "Uploading $currentProduct/$totalProducts: " . $product["product_identifier"] . " - Fail\n";
        $failedUploads[] = $product["product_identifier"];
    }

    usleep(500000);
}

if (!empty($failedUploads)) {
    file_put_contents($failedFile, implode("\n", $failedUploads) . "\n", FILE_APPEND);
    echo "Failed uploads logged in: $failedFile\n";
} else {
    echo "All products uploaded successfully.\n";
}

?>
