<?php
// Ensure this is being run within the context of WordPress
require_once('wp-load.php');

// Trigger the custom WP-Cron event
do_action('wcospa_check_pending_orders_event');

// This file needs to go to root folder e.g. public_html then add CRON job to trigger this
// cd /home/customer/www/staging18.zerotech.com.au/public_html; wp cron event run wcospa_check_pending_orders_event --allow-root >/dev/null 2>&1