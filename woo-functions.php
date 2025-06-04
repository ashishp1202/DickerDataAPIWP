<?php

/**
 * Show extra field to WooCommerce product below the price for the Unit Price from Dicker Data
 */
add_action('woocommerce_product_options_pricing', 'AVP_add_custom_admin_price_field');
function AVP_add_custom_admin_price_field()
{
  woocommerce_wp_text_input(array(
    'id' => '_dicker_product_unit_price',
    'label' => __('Dicker Product Unit Price', 'woocommerce'),
    'desc_tip' => true,
    'description' => __('This is a unit price from the dicker data.', 'woocommerce'),
    'type' => 'text',
  ));
}

/**
 * Insert the Sales Order Number after the payment via Dicker API
 */
add_action('woocommerce_thankyou', 'AVP_call_third_party_api_after_order', 10, 1);
function AVP_call_third_party_api_after_order($order_id)
{
  if (!$order_id) {
    return;
  }
  $order = wc_get_order($order_id);
  $productSKUs = [];
  foreach ($order->get_items() as $item_id => $item) {
    $product = $item->get_product();
    if (! $product) continue;
    $productSKUs[] = $product->get_id();
  }
  $getProductDataBySKU = AVP_GetProductPriceBySKU($productSKUs);
  if (isset($getProductDataBySKU->ResponseHeader->Status) && ($getProductDataBySKU->ResponseHeader->Status === 'SUCCESS')) {
    if (isset($getProductDataBySKU->Out) && !empty($getProductDataBySKU->Out)) {
      foreach ($getProductDataBySKU->Out as $productData) {
        add_only_unitPrice_from_object_dickerdata($productData);
      }
    }
  }
  //Create order with Dicker Data API and save Sales Order Number
  $dickerDataOrder = AVP_CreateOrder($order_id);
  if ($dickerDataOrder->ResponseHeader->Status === 'SUCCESS' && $dickerDataOrder->OrderOut->Status === 'SUCCESS' && !empty($dickerDataOrder->OrderOut->SalesOrderNumber)) {
    update_post_meta($order_id, '_dickerData_SalesOrderNumber', sanitize_text_field($dickerDataOrder->OrderOut->SalesOrderNumber));
    AVP_all_request_response_log("Sales Order Number Added", array($order_id, $dickerDataOrder->OrderOut->SalesOrderNumber));
  }
}
/**
 * Show the Sales Order Number in admin order detail page
 */
add_action('woocommerce_admin_order_data_after_order_details', 'AVP_display_custom_order_meta_in_admin');
function AVP_display_custom_order_meta_in_admin($order)
{
  $order_id = $order->get_id();
  $dickerData_SalesOrderNumber = get_post_meta($order_id, '_dickerData_SalesOrderNumber', true);

  if (!empty($dickerData_SalesOrderNumber)) {
    echo '<p><strong>' . __('Dicker Data Sales Order Number', 'woocommerce') . ':</strong> ' . esc_html($dickerData_SalesOrderNumber) . '</p>';
  }
}

/**
 * Get the price from API just before the add to cart
 */
add_filter('woocommerce_add_to_cart_validation', 'AVP_before_add_to_cart_get_sku', 10, 5);

function AVP_before_add_to_cart_get_sku($passed, $product_id, $quantity, $variation_id = 0, $variations = null)
{

  // Get the correct product object
  $product = $variation_id ? wc_get_product($variation_id) : wc_get_product($product_id);

  // Get the SKU
  $sku = $product->get_sku();

  $productSKUs = [];
  $productSKUs[] = $sku;
  $getProductDataBySKU = AVP_GetProductPriceBySKU($productSKUs);
  if (isset($getProductDataBySKU->ResponseHeader->Status) && ($getProductDataBySKU->ResponseHeader->Status === 'SUCCESS')) {
    if (isset($getProductDataBySKU->Out) && !empty($getProductDataBySKU->Out)) {
      foreach ($getProductDataBySKU->Out as $productData) {
        add_only_unitPrice_from_object_dickerdata($productData);

        AVP_all_request_response_log("Update the product Unit Price before adding to cart", $productData);
      }
    }
  }

  return $passed; // Return true to allow, false to block
}
