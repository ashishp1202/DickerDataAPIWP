<?php
function AVP_all_request_response_log($title, $data)
{
  // Create log directory if it doesn't exist
  $log_dir = WP_CONTENT_DIR . '/dickerlogs/';
  if (!file_exists($log_dir)) {
    mkdir($log_dir, 0755, true);
  }

  // Create log file with current date
  $date = date('Y-m-d'); // Format: 2025-06-04
  $log_file = $log_dir . 'api-log-' . $date . '.log';

  // Convert array/object to readable string
  if (is_array($data) || is_object($data)) {
    $data = print_r($data, true);
  }

  // Create log entry
  $log_entry = "[" . date("H:i:s") . "] $title: " . $data . "\n";

  // Append to the daily log file
  file_put_contents($log_file, $log_entry, FILE_APPEND);
}
