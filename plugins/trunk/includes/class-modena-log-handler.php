<?php
if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}

class Modena_Log_Handler extends WC_Log_Handler_File {
  public function handle($timestamp, $level, $message, $context) {

    $entry = self::format_entry($timestamp, $level, $message, $context);

    return $this->add($entry, 'modena-log');
  }
}