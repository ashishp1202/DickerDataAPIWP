<?php
// Show custom price field in product edit page (admin)
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


add_action('woocommerce_thankyou', 'call_third_party_api_after_order', 10, 1);

function call_third_party_api_after_order($order_id)
{
  if (!$order_id) {
    return;
  }


  $dickerDataOrder = AVP_CreateOrder($order_id);
  if ($dickerDataOrder->ResponseHeader->Status === 'SUCCESS' && $dickerDataOrder->OrderOut->Status === 'SUCCESS' && !empty($dickerDataOrder->OrderOut->SalesOrderNumber)) {
    update_post_meta($order_id, '_dickerData_SalesOrderNumber', sanitize_text_field($dickerDataOrder->OrderOut->SalesOrderNumber));
  }
}

add_action('woocommerce_admin_order_data_after_order_details', 'AVP_display_custom_order_meta_in_admin');

function AVP_display_custom_order_meta_in_admin($order)
{
  $order_id = $order->get_id();
  $dickerData_SalesOrderNumber = get_post_meta($order_id, '_dickerData_SalesOrderNumber', true);

  if (!empty($dickerData_SalesOrderNumber)) {
    echo '<p><strong>' . __('Dicker Data Sales Order Number', 'woocommerce') . ':</strong> ' . esc_html($dickerData_SalesOrderNumber) . '</p>';
  }
}
