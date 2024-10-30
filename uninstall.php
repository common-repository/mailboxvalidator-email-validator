<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

delete_option( 'mbv_email_validator' );
delete_option( 'MBV_PLUGIN_VER' );

$GLOBALS['wpdb']->query('DROP TABLE IF EXISTS ' . $GLOBALS['wpdb']->prefix . 'mailboxvalidator_email_validator_log');

wp_cache_flush();