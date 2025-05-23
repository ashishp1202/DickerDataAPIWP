<?php
// Step 1: Schedule the task
add_action('init', function () {
  if (!wp_next_scheduled('dickerdata_hourly_cron_hook')) {
    wp_schedule_event(time(), 'hourly', 'dickerdata_hourly_cron_hook');
  }
});

// Step 2: Hook your function to it
add_action('dickerdata_hourly_cron_hook', 'dickerdata_custom_hourly_function');

function dickerdata_custom_hourly_function()
{
  $getProductDataBySKU = AVP_GetProductPriceBySKU();
  if (isset($getProductDataBySKU->ResponseHeader->Status) && ($getProductDataBySKU->ResponseHeader->Status === 'SUCCESS')) {
    if (isset($getProductDataBySKU->Out) && !empty($getProductDataBySKU->Out)) {
      foreach ($getProductDataBySKU->Out as $productData) {
        add_custom_product_from_object_dickerdata($productData);
      }
    }
  }
}

function add_custom_product_from_object_dickerdata($product_data)
{
  if (!class_exists('WC_Product')) {
    return;
  }

  $sku = sanitize_text_field($product_data->PartNumber);
  if (empty($sku)) {
    return;
  }

  // Check for existing product by SKU
  $existing_product_id = wc_get_product_id_by_sku($sku);
  $product = $existing_product_id ? wc_get_product($existing_product_id) : null;

  $is_new_product = false;

  if (!$product || !($product instanceof WC_Product)) {
    // SKU must not exist anywhere to create new
    if (wc_get_product_id_by_sku($sku)) {
      error_log("Cannot create product — SKU '{$sku}' already exists.");
      return;
    }

    $product = new WC_Product_Simple();
    $product->set_sku($sku);
    $is_new_product = true;
  }

  // Set product title
  if (!empty($product_data->Description)) {
    $product->set_name($product_data->Description);
  }

  // Set price
  if (!empty($product_data->RRPExTax)) {
    $product->set_regular_price($product_data->RRPExTax);
  }

  // Set stock
  if (!empty($product_data->SOH)) {
    $product->set_manage_stock(true);
    $product->set_stock_quantity((int)$product_data->SOH);
  }

  // Set dimensions
  if (!empty($product_data->Height)) {
    $product->set_height($product_data->Height);
  }
  if (!empty($product_data->Length)) {
    $product->set_length($product_data->Length);
  }
  if (!empty($product_data->Width)) {
    $product->set_width($product_data->Width);
  }

  // Set weight
  if (!empty($product_data->Weight)) {
    $product->set_weight($product_data->Weight);
  }


  // Save product
  $product_id = $product->save();
  add_post_meta($product_id, '_dicker_product_unit_price', $product_data->UnitPrice, true);

  if (!empty($product_data->Brand)) {
    $brand = sanitize_text_field($product_data->Brand);

    // Create brand term if not exists
    $term = term_exists($brand, 'product_brand');
    if (!$term) {
      $term = wp_insert_term($brand, 'product_brand');
      if (is_wp_error($term)) {
        error_log('Error creating brand term: ' . $term->get_error_message());
        return; // stop if fail
      }
    }

    // Attach brand term to product by name
    wp_set_object_terms($product_id, $brand, 'product_brand', false);
  }


  return $product_id;
}
