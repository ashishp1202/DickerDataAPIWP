<?php
define('ACCESSTOKEN', '237082ED-EB96-4738-89E3-5C2F1ADD0C7F');
define('ACOOUNTCODE', '325026');
define('PRODUCTSKUSARR', array("8D8K2AA", "83Z45AA", "83Z51AA", "A4LZ8AA", "NEATBAR2-PAD-BUNDLE", "NEATBARPRO-PAD-BUNDLE", "A40-031", "A30-020"));
function generateUuidV4()
{
  $data = random_bytes(16);

  // Set version to 0100
  $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);

  // Set bits 6-7 to 10
  $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

  return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}



function AVP_AccessKeyRequest()
{
  $curl = curl_init();
  $transactionID = generateUuidV4();
  curl_setopt_array($curl, array(
    CURLOPT_URL => 'https://b2b-api-test.dickerdata.com.au/api/AccessKeyRequest',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => '{
  "TransactionID": "' . $transactionID . '",
  "AccessToken": "' . ACCESSTOKEN . '",
  "AccountCode":  "' . ACOOUNTCODE . '"
}',
    CURLOPT_HTTPHEADER => array(
      'Content-Type: application/json',
      'Accept: application/json',
      'DD-TransactionID: ' . $transactionID,
      'DD-ApiVersion: v1',
      'Authorization: Bearer ' . ACCESSTOKEN
    ),
  ));

  $response = curl_exec($curl);
  $jsonResponse = json_decode($response);
  curl_close($curl);
  if (isset($jsonResponse->AccessKey) && !empty($jsonResponse->AccessKey)) {
    return $jsonResponse->AccessKey;
  }
}


function AVP_GetProductPriceBySKU(array $sku = PRODUCTSKUSARR)
{
  $accessKey = AVP_AccessKeyRequest();
  $transactionID = generateUuidV4();
  $curl = curl_init();
  $postData = json_encode([
    'Products' => $sku
  ]);
  curl_setopt_array($curl, array(
    CURLOPT_URL => 'https://b2b-api-test.dickerdata.com.au/api/DickerData/GetPrice',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => $postData,
    CURLOPT_HTTPHEADER => array(
      'Content-Type: application/json',
      'Accept: application/json',
      'DD-TransactionID: ' . $transactionID,
      'DD-ApiVersion: v1',
      'Authorization: Bearer ' . $accessKey,
      //'Cookie: __cf_bm=9lIxTCqhM2wE83GfKPK_8bFd4gEh7fszHXumfRld67M-1747289786-1.0.1.1-1W5ayr_ODEbfgd9tpTDPv.DRzf.6LwH8rPKe1ieAtFOf2CdQt_mYd8x68L_oOYCR3E4hHIUwq6OEO2z5pxeCxtnx5zQOaE7CGlEV9uvqArQ'
    ),
  ));

  $response = curl_exec($curl);
  return json_decode($response);
}

/**
 * Create an order to Dicker Data after WooCommerce order success
 * @param int $order_id
 */
function AVP_CreateOrder(int $order_id)
{
  $order = wc_get_order($order_id);
  $accessKey = AVP_AccessKeyRequest();
  $transactionID = generateUuidV4();
  $curl = curl_init();
  $items = [];
  foreach ($order->get_items() as $item_id => $item) {
    $product = $item->get_product();
    if (! $product) continue;
    $dicker_product_unit_price = get_post_meta($product->get_id(), '_dicker_product_unit_price', true);
    $items[] = [
      'Product' => [
        'UseSystemPrice'         => true,
        'Quantity'     => $item->get_quantity(),
        'UnitPrice'        => $dicker_product_unit_price ? $dicker_product_unit_price : $item->get_total(),
        'PartNumber'          => $product ? $product->get_sku() : '',
      ]
    ];
  }
  echo "<pre>";
  print_r(json_encode($items));
  $companyName = $order->get_billing_company() ? $order->get_billing_company() : '-';
  curl_setopt_array($curl, array(
    CURLOPT_URL => 'https://b2b-api-test.dickerdata.com.au/api/Order/CreateOrder',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => '{
  "OrderIn": {
    "Header": {
      "OrderNumber":       "' . $order_id . '",
      "BranchAccountCode": "325026"
    },
    "Delivery": {
      "CompanyName": "' . $companyName . '",
      "DeliveryContact": {
        "FirstName": "' . $order->get_billing_first_name() . '",
        "LastName":  "' . $order->get_billing_last_name() . '",
        "Email":     "' . $order->get_billing_email() . '",
        "Phone":     "' . $order->get_billing_phone() . '"
      },
      "DeliveryAddress": {
        "CompanyName": "' . $companyName . '",
        "Address01":   "' . $order->get_billing_address_1() . '",
        "Address02":   "' . $order->get_billing_address_2() . '",
        "Suburb":      "' . $order->get_billing_city() . '",
        "State":       "' . $order->get_billing_state() . '",
        "Postcode":    "' . $order->get_billing_postcode() . '",
        "Country":     "' . $order->get_billing_country() . '"
      },
      "Attention":      "' . $order->get_billing_first_name() . '",
      "PartShipped":    false,
      "ShippingMethod": "DropShip"
    },
    "Items": ' . json_encode($items) . '
  }
}
',
    CURLOPT_HTTPHEADER => array(
      'Content-Type: application/json',
      'Accept: application/json',
      'DD-TransactionID: ' . $transactionID,
      'DD-ApiVersion: v1',
      'Authorization: Bearer ' . $accessKey,
      //'Cookie: __cf_bm=9lIxTCqhM2wE83GfKPK_8bFd4gEh7fszHXumfRld67M-1747289786-1.0.1.1-1W5ayr_ODEbfgd9tpTDPv.DRzf.6LwH8rPKe1ieAtFOf2CdQt_mYd8x68L_oOYCR3E4hHIUwq6OEO2z5pxeCxtnx5zQOaE7CGlEV9uvqArQ'
    ),
  ));

  $response = curl_exec($curl);

  return json_decode($response);
}
