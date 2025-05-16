<?php
// Step 1: Schedule the task
add_action('init', function () {
  if (!wp_next_scheduled('dickerdata_hourly_cron_hook')) {
    wp_schedule_event(time(), 'hourly', 'dickerdata_hourly_cron_hook');
  }
});

// Step 2: Hook your function to it
add_action('dickerdata_hourly_cron_hook', 'dickerdata_custom_hourly_function');

dickerdata_custom_hourly_function();
function dickerdata_custom_hourly_function()
{
  $getProductDataBySKU = AVP_GetProductPriceBySKU();
  echo "<pre>";
  print_r($getProductDataBySKU);
  exit();
}
