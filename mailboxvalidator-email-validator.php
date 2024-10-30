<?php
/**
Plugin Name:  MailboxValidator Email Validator
Plugin URL:   https://developer.wordpress.org/plugins/mailboxvalidator-email-validator
Description:  This plugin enables you to block invalid, disposable, free or role-based email from registering for your service. This plugin has been tested successfully to support WooCommerce, JetPack, Contact Form 7, Formidable forms and many more.
Version:      1.6.3
Author:       MailboxValidator
Author URI:   https://mailboxvalidator.com
License:      GNU
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
Text Domain:  mailboxvalidator-email-validator
 */

require_once __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

// Defined plugin version.
define( 'MBV_PLUGIN_VER', '1.6.3' );

// Need to include because some plugin called is_email in front end.
include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
 
$mailboxvalidator_email_validator = new MailboxValidatorEmailValidator();

add_action( 'admin_notices', [$mailboxvalidator_email_validator, 'mbv_general_admin_notice'] );
add_action( 'wp_ajax_mailboxvalidator_email_validator_submit_feedback', [$mailboxvalidator_email_validator, 'submit_feedback'] );
add_action( 'admin_footer_text', [$mailboxvalidator_email_validator, 'admin_footer_text'] );
// add_action ( 'admin_init', [$mailboxvalidator_email_validator, 'mbv_localization']);
add_action( 'admin_enqueue_scripts', [$mailboxvalidator_email_validator, 'plugin_enqueues'] );

add_filter( 'registration_errors', [$mailboxvalidator_email_validator, 'mbv_reg_validate_email'], 10, 3 );

if ( is_plugin_active( 'contact-form-7/wp-contact-form-7.php' ) ) { // Contact Form 7
	add_filter( 'wpcf7_validate_email', [$mailboxvalidator_email_validator, 'mbv_wpcf7_custom_email_validator_filter'], 5, 2 ); // Email field
	add_filter( 'wpcf7_validate_email*', [$mailboxvalidator_email_validator, 'mbv_wpcf7_custom_email_validator_filter'], 5, 2 ); // Req. Email field
} elseif ( is_plugin_active('formidable/formidable.php') ) { // Formidable
	add_action( 'frm_validate_entry', [$mailboxvalidator_email_validator, 'mbv_frm_validate_entry'], 1, 2 );
} elseif ( is_plugin_active('caldera-forms/caldera-core.php') ) { // Caldera Form
	add_filter( 'caldera_forms_validate_field_email', [$mailboxvalidator_email_validator, 'mbv_caldera_forms_validate_email'], 11, 3 );
} elseif ( is_plugin_active('profile-builder/index.php') ) { // Profile Builder
	add_filter( 'wppb_check_form_field_default-e-mail', [$mailboxvalidator_email_validator, 'mbv_wppb_validate_email'], 11, 4 );
} elseif ( is_plugin_active('contact-form-plugin/contact_form.php' ) ) { //contact form BWS
	add_filter( 'cntctfrm_check_form', [$mailboxvalidator_email_validator, 'mbv_bws_validate_email'], 11 );
} elseif ( is_plugin_active('woocommerce/woocommerce.php' ) ) { //woocommerce/woocommerce
	add_action( 'woocommerce_after_checkout_validation', [$mailboxvalidator_email_validator, 'mbv_woo_validate_email'], 10, 2);
} else { // Other plugins that used is_email
	add_filter( 'is_email', [$mailboxvalidator_email_validator, 'mbv_email_validator_filter'] );
}
// add_filter( 'is_email', 'mbv_email_validator_filter' );
$mailboxvalidator_email_validator->mbv_comment_validate_email();

class MailboxValidatorEmailValidator
{
	protected $global_status = '';
	
	public function __construct()
	{
		// add_action ( 'admin_init', [$this, 'mbv_localization']);
		// add_action ( 'admin_init', array( $this, 'mbv_localization' ) );
		add_action( 'admin_menu', [$this, 'mbv_admin_add_page'] );
		// add the admin settings and such
		add_action( 'admin_init', [$this, 'mbv_plugin_admin_init'] );
	}

	/*
	 * Localization
	 */
	public function mbv_localization()
	{
		// load_plugin_textdomain( 'mailboxvalidator-email-validator', false, dirname( plugin_basename( __FILE__ ) ) . '/langs' );
		load_plugin_textdomain( 'mailboxvalidator-email-validator', false, plugins_url( '/langs' ) );
	}
	
	public function mbv_admin_add_page() 
	{
		if (!is_admin()) {
			return;
		}

		add_action('wp_enqueue_script', 'load_jquery');
		add_options_page( 'MailboxValidator Email Validator', 'MailboxValidator Email Validator', 'manage_options', 'mailboxvalidator-email-validator', [$this, 'mbv_plugin_options_page'] );
		wp_register_script( 'mailboxvalidator_email_validator_script', plugins_url( '/assets/js/mbv.js', __FILE__ ), array( 'jquery' ) );
		wp_enqueue_script('mailboxvalidator_email_validator_chart_js', 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.8.0/Chart.min.js', [], null, true);
		wp_enqueue_script( 'mailboxvalidator_email_validator_script' );
		wp_enqueue_script( 'mbv_tagsinput_js', plugins_url( '/assets/js/jquery.tagsinput.min.js', __FILE__ ), [], null, true);
		wp_enqueue_style( 'mbv_tagsinput_css', esc_url_raw( 'https://cdnjs.cloudflare.com/ajax/libs/jquery-tagsinput/1.3.6/jquery.tagsinput.min.css' ), [], null );
		
		$this->create_table();
	
	}
	public function mbv_plugin_options_page() 
	{
		

		$tab = (isset($_GET['tab'])) ? $_GET['tab'] : 'settings';

		switch ($tab) {
			// Statistic
			case 'statistic':
				global $wpdb;
				
				$table_name = $wpdb->prefix . 'mailboxvalidator_email_validator_log';

				if (isset($_POST['purge'])) {
					$wpdb->query('TRUNCATE TABLE ' . $table_name);
				}

				// Remove logs older than 30 days.
				$wpdb->query('DELETE FROM ' . $table_name . ' WHERE date_created <="' . date('Y-m-d H:i:s', strtotime('-30 days')) . '"');

				// Prepare logs for last 30 days.
				$results = $wpdb->get_results('SELECT DATE_FORMAT(date_created, "%Y-%m-%d") AS date, COUNT(*) AS total FROM ' . $table_name . ' GROUP BY date ORDER BY date', OBJECT);

				$lines = [];
				for ($d = 30; $d > 0; --$d) {
					$lines[date('Y-m-d', strtotime('-' . $d . ' days'))] = 0;
				}

				foreach ($results as $result) {
					$lines[$result->date] = $result->total;
				}

				ksort($lines);

				$labels = [];
				$total_emails_blocked = [];

				foreach ($lines as $date => $value) {
					$labels[] = $date;
					$total_emails_blocked[] = ($value) ? $value : 0;
				}

				// Add index to table id not exist.
				$results = $wpdb->get_results('SELECT COUNT(*) AS total FROM information_schema.statistics WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "' . $table_name . '" AND INDEX_NAME = "idx_email_address"', OBJECT);

				if ($results[0]->total == 0) {
					$wpdb->query('ALTER TABLE `' . $table_name . '` ADD INDEX `idx_email_address` (`email_address`);');
				}
				
				// $sub_title1 = esc_html_e('MailboxValidator Email Validator' ,'mailboxvalidator-email-validator');
				$sub_title1 = __('MailboxValidator Email Validator' ,'mailboxvalidator-email-validator');
				// $sub_description1 = esc_html_e('This plugin enables you to block invalid, disposable, free or role-based email, from registering for your service. This plugin has been tested successfully to support WooCommerce, JetPack, Contact Form 7, Formidable forms and many more.' ,'mailboxvalidator-email-validator');
				$sub_description1 = __('This plugin enables you to block invalid, disposable, free or role-based email, from registering for your service. This plugin has been tested successfully to support WooCommerce, JetPack, Contact Form 7, Formidable forms and many more.' ,'mailboxvalidator-email-validator');
				// $sub_description2 = printf( __( 'The email blocking will be activated immediately once you have enabled the blocking options. No additional coding is required. Please visit <a href="%s">here</a> to learn more about how the blocking works for each supported plugin.', 'mailboxvalidator-email-validator' ), 'https://www.mailboxvalidator.com/resources/articles/mailboxvalidator-email-validator-wordpress-plugin-whats-next/' );
				$sub_description2 =  __( 'The email blocking will be activated immediately once you have enabled the blocking options. No additional coding is required. Please visit <a href="%s">here</a> to learn more about how the blocking works for each supported plugin.', 'mailboxvalidator-email-validator' );
				$sub_description2 = str_replace("%s", 'https://www.mailboxvalidator.com/resources/articles/mailboxvalidator-email-validator-wordpress-plugin-whats-next/', $sub_description2);
				
				echo '
				<div class="wrap">
					<h2>' . $sub_title1 . '</h2>
					<p style="font-size: 14px;">' . $sub_description1 . '
					</p>
					<p style="font-size: 14px;"> ' . $sub_description2 . ' </p>
					' . $this->admin_tabs() . '

					<h3>Block Statistics For The Past 30 Days</h3>

					<p>
						<canvas id="line_chart" style="width:100%;height:400px"></canvas>
					</p>

					<div class="clear"></div>

					<p>
						<form id="form-purge" method="post">
							<input type="hidden" name="purge" value="true">
							<input type="submit" name="submit" id="btn-purge" class="button button-primary" value="Purge All Logs" />
						</form>
					</p>

				</div>
				<script>
				jQuery(document).ready(function($){
					function get_color(){
						var r = Math.floor(Math.random() * 200);
						var g = Math.floor(Math.random() * 200);
						var b = Math.floor(Math.random() * 200);

						return \'rgb(\' + r + \', \' + g + \', \' + b + \', 0.4)\';
					}

					var ctx = document.getElementById(\'line_chart\').getContext(\'2d\');
					var line = new Chart(ctx, {
						type: \'line\',
						data: {
							labels: [\'' . implode('\', \'', $labels) . '\'],
							datasets: [{
								label: \'Total Email Blocked\',
								data: [' . implode(', ', $total_emails_blocked) . '],
								backgroundColor: get_color()
							}]
						},
						options: {
							title: {
								display: true,
								text: \'Total Email Blocked\'
							},
							scales: {
								yAxes: [{
									ticks: {
										beginAtZero:true
									}
								}]
							}
						}
					});
				});
				</script>';
				break;
			case 'settings':
			default:
				$sub_title1 = __('MailboxValidator Email Validator' ,'mailboxvalidator-email-validator');
				$sub_description1 = __('This plugin enables you to block invalid, disposable, free or role-based email, from registering for your service. This plugin has been tested successfully to support WooCommerce, JetPack, Contact Form 7, Formidable forms and many more.' ,'mailboxvalidator-email-validator');
				$sub_description2 =  __( 'The email blocking will be activated immediately once you have enabled the blocking options. No additional coding is required. Please visit <a href="%s">here</a> to learn more about how the blocking works for each supported plugin.', 'mailboxvalidator-email-validator' );
				$sub_description2 = str_replace("%s", 'https://www.mailboxvalidator.com/resources/articles/mailboxvalidator-email-validator-wordpress-plugin-whats-next/', $sub_description2);
				echo '
				<div class="wrap">
					<h2>' . $sub_title1 . '</h2>
					<p style="font-size: 14px;">' . $sub_description1 . '</p>
					<p style="font-size: 14px;"> ' . $sub_description2 . '</p>
					' . $this->admin_tabs() . '';
				$mbv_options = get_option( 'mbv_email_validator' );
				$api_key = isset( $mbv_options['api_key'] ) ? $mbv_options['api_key'] : '';
				if ( $api_key != '' ) {
					$url = 'https://api.mailboxvalidator.com/plan?key=' . $api_key;
					$results = wp_remote_get( $url );
					if ( !is_wp_error( $results ) ) {
						$body = wp_remote_retrieve_body( $results );

						// Decode the return json results and return the data.
						$data = json_decode( $body, true );
						
						if ( $data['plan_name'] != '' ) {
							if ( $data['plan_name'] == 'API-FREE' ) {
								$is_low_credit = ( $data['credits_available'] < 100 ) ? true : false ;
							} else {
								$is_low_credit = ( $data['credits_available'] < ( $data['credits_limit'] * 0.1 ) ) ? true : false ;
							}
							// Now print the plan info
							echo '<div id="api_key_information"><h2>'. __('Plan Information', 'mailboxvalidator-email-validator' ) . '</h2><table class="form-table">';
							echo '<tr><th scope="row"><label>'. __('Plan Name', 'mailboxvalidator-email-validator' ) . '</label></th><td><p>' . $data['plan_name'] . '</p></td></tr>';
							echo '<tr><th scope="row"><label>'. __('Credits Available', 'mailboxvalidator-email-validator' ) . '</label></th><td><p>' . $data['credits_available'] . '<span style="margin-left: 20px"></span>'  . ( $is_low_credit ? '<a href="https://www.mailboxvalidator.com/plans#api" target="_blank" class="button">Get More Credits</a>' : '' ) . '</p></td></tr>';
							echo '<tr><th scope="row"><label>'. __('Next Renewal Date', 'mailboxvalidator-email-validator' ) . '</label></th><td><p>' . $data['next_renewal_date'] . '</p></td></tr>';
							echo '</table></div>';
						}
					}
				}
				echo '
						<div id="mbv_plugin_settings">
						<form action="options.php" method="post">';
				settings_fields( 'mbv_email_validator' );
				do_settings_sections( 'mbv_plugin' );
				echo '
							<input name="Submit" type="submit" value="' . __( 'Save Changes' ) . '" class="button button-primary" />
							<br/><br/><label>* Currently custom error message will only available for the following plugins: Contact Form 7, Formidable forms, WooCommerce, Caldera Form, Profile Builder, and contact form BWS.</label>
						</form>
					</div>

					<div class="clear"></div>
				</div>';
				break;
		}
	}

	private function admin_tabs()
	{
		$disable_tabs = false;

		$tab = (isset($_GET['tab'])) ? $_GET['tab'] : 'settings';

		return '
		' . $this->global_status . '
		<h2 class="nav-tab-wrapper">
			<a href="' . (($disable_tabs) ? 'javascript:;' : admin_url('options-general.php?page=mailboxvalidator-email-validator&tab=settings')) . '" class="nav-tab' . (($tab == 'settings') ? ' nav-tab-active' : '') . '">Settings</a>
			<a href="' . (($disable_tabs) ? 'javascript:;' : admin_url('options-general.php?page=mailboxvalidator-email-validator&tab=statistic')) . '" class="nav-tab' . (($tab == 'statistic') ? ' nav-tab-active' : '') . '">Statistics</a>
		</h2>';
	}
	
	public function mbv_plugin_admin_init()
	{

		register_setting( 'mbv_email_validator', 'mbv_email_validator', [$this, 'mbv_sanitize_settings_input'] );
		add_settings_section( 'mbv_plugin_main', __('Main Settings', 'mailboxvalidator-email-validator' ), [$this, 'mbv_plugin_section_text'], 'mbv_plugin' );
		add_settings_field( 'mbv_api_key', __('MailboxValidator API Key', 'mailboxvalidator-email-validator' ), [$this, 'mbv_api_key_setting'], 'mbv_plugin', 'mbv_plugin_main' );
		// add_settings_field( 'mbv_plan_info', 'MailboxValidator API Key', 'mbv_api_key_setting', 'mbv_plugin', 'mbv_plugin_main' );
		// add_settings_field( 'mbv_remaining_credits', 'Remaining Credits', 'mbv_show_remaining_credits', 'mbv_plugin', 'mbv_plugin_main' );
		add_settings_field( 'mbv_debug_mode_option', __('Debug Mode', 'mailboxvalidator-email-validator' ), [$this, 'mbv_debug_mode_setting_option'], 'mbv_plugin', 'mbv_plugin_main' );
		add_settings_field( 'mbv_valid_email_option', __('Block Invalid Email', 'mailboxvalidator-email-validator' ), [$this, 'mbv_valid_email_setting_option'], 'mbv_plugin', 'mbv_plugin_main' );
		add_settings_field( 'mbv_valid_error_message', 'Error Message for valid email *', [$this, 'mbv_valid_error_message_setting'], 'mbv_plugin', 'mbv_plugin_main' );
		add_settings_field( 'mbv_disposable_option', __('Block Disposable Email', 'mailboxvalidator-email-validator' ), [$this, 'mbv_disposable_setting_option'], 'mbv_plugin', 'mbv_plugin_main' );
		add_settings_field( 'mbv_disposable_error_message', 'Error Message for disposable email *', [$this, 'mbv_disposable_error_message_setting'], 'mbv_plugin', 'mbv_plugin_main' );
		add_settings_field( 'mbv_free_option', __('Block Free Email', 'mailboxvalidator-email-validator' ), [$this, 'mbv_free_setting_option'], 'mbv_plugin', 'mbv_plugin_main' );
		add_settings_field( 'mbv_free_error_message', 'Error Message for free email *', [$this, 'mbv_free_error_message_setting'], 'mbv_plugin', 'mbv_plugin_main' );
		add_settings_field( 'mbv_role_option', __('Block Role-Based Email', 'mailboxvalidator-email-validator' ), [$this, 'mbv_role_setting_option'], 'mbv_plugin', 'mbv_plugin_main' );
		add_settings_field( 'mbv_role_error_message', 'Error Message for role email *', [$this, 'mbv_role_error_message_setting'], 'mbv_plugin', 'mbv_plugin_main' );
		add_settings_field( 'mbv_custom_domain_blacklist', __('Block Custom Email Domains', 'mailboxvalidator-email-validator' ), [$this, 'mbv_custom_domain_blacklist_option'], 'mbv_plugin', 'mbv_plugin_main' );
		add_settings_field( 'mbv_custom_domain_error_message', 'Error Message for custom email domain *', [$this, 'mbv_custom_domain_error_message_setting'], 'mbv_plugin', 'mbv_plugin_main' );

	}
	
	public function mbv_sanitize_settings_input($input){
		// Check the values of radio buttons
		if (! in_array($input['debug_mode_on_off'], array('on', 'off'))) {
			add_settings_error(
					'mbv_debug_mode_option_error_message',  // setting title
					'mbv_optionerror',            // error ID
					'Invalid option value detected.',   // error message
					'error'                             // type of message
				);
			$input['debug_mode_on_off'] = 'on';
		}
		if (! in_array($input['valid_email_on_off'], array('on', 'off'))) {
			add_settings_error(
					'mbv_invalid_error_message',  // setting title
					'mbv_optionerror',            // error ID
					'Invalid option value detected.',   // error message
					'error'                             // type of message
				);
			$input['valid_email_on_off'] = 'on';
		}
		if (! in_array($input['disposable_on_off'], array('on', 'off'))) {
			add_settings_error(
					'mbv_disposable_on_off',      // setting title
					'mbv_optionerror',            // error ID
					'Invalid option value detected.',   // error message
					'error'                             // type of message
				);
			$input['disposable_on_off'] = 'on';
		}
		if (! in_array($input['free_on_off'], array('on', 'off'))) {
			add_settings_error(
					'mbv_free_on_off',            // setting title
					'mbv_optionerror',            // error ID
					'Invalid option value detected.',   // error message
					'error'                             // type of message
				);
			$input['free_on_off'] = 'on';
		}
		if (! in_array($input['role_on_off'], array('on', 'off'))) {
			add_settings_error(
					'mbv_role_on_off',            // setting title
					'mbv_optionerror',            // error ID
					'Invalid option value detected.',   // error message
					'error'                             // type of message
				);
			$input['role_on_off'] = 'on';
		}
		// Check for blacklisted_domains input
		if (isset($input['blacklisted_domains'])) {
			if (empty($input['blacklisted_domains'])) {
				// empty, let it pass
			} else if (strpos(';', $input['blacklisted_domains']) == False) {
				// one domain
				if (filter_var($input['blacklisted_domains'], FILTER_VALIDATE_DOMAIN) == False) {
					add_settings_error(
						'mbv_blacklisted_domains',            // setting title
						'mbv_optionerror',            // error ID
						'Invalid domain name detected.',   // error message
						'error'                             // type of message
					);
				}
			} else if (strpos(';', $input['blacklisted_domains']) != False){
				// multiple domains(seperated by ;)
				$blacklisted_domains_array = explode( ';', $input['blacklisted_domains'] );
				foreach ($blacklisted_domains_array as $array_key => $domain) {
					if (filter_var($domain, FILTER_VALIDATE_DOMAIN) == False) {
						add_settings_error(
							'mbv_blacklisted_domains',            // setting title
							'mbv_optionerror',            // error ID
							'Invalid domain name detected.',   // error message
							'error'                             // type of message
						);
					}
				}
			}
		}
		// Sanitize inputs
		$input['api_key'] = strip_tags(stripslashes( $input['api_key'] ));
		$input['debug_mode_on_off'] = strip_tags(stripslashes( $input['debug_mode_on_off'] ));
		$input['valid_email_on_off'] = strip_tags(stripslashes( $input['valid_email_on_off'] ));
		$input['disposable_on_off'] = strip_tags(stripslashes( $input['disposable_on_off'] ));
		$input['free_on_off'] = strip_tags(stripslashes( $input['free_on_off'] ));
		$input['role_on_off'] = strip_tags(stripslashes( $input['role_on_off'] ));
		$input['valid_error_message'] = strip_tags(stripslashes( $input['valid_error_message'] ));
		$input['disposable_error_message'] = strip_tags(stripslashes( $input['disposable_error_message'] ));
		$input['free_error_message'] = strip_tags(stripslashes( $input['free_error_message'] ));
		$input['role_error_message'] = strip_tags(stripslashes( $input['role_error_message'] ));
		$input['custom_domain_error_message'] = strip_tags(stripslashes( $input['custom_domain_error_message'] ));
		$input['blacklisted_domains'] = strip_tags(stripslashes( $input['blacklisted_domains'] ));
		return $input;
	}
	
	public function mbv_plugin_section_text()
	{

		echo '<p style="font-size: 14px;">'. __('Please enter a MailboxValidator API key to enable the email blocking.', 'mailboxvalidator-email-validator' ) . '</p>';

	}

	/*public function mbv_show_remaining_credits() {
		$options = get_option( 'mbv_email_validator' );
		$remaining_credits = isset( $options['remaining_credits'] ) ? $options['remaining_credits'] : '0';
		echo $remaining_credits . '<br/>';
		echo '<input id="remaining_credits" name="mbv_email_validator[remaining_credits]" type="hidden" value="' . esc_attr( $remaining_credits ). '" style="margin-bottom: 5px;"/><br />';
		// if ($remaining_credits < 100) {
			
		// }
	}*/

	public function mbv_api_key_setting()
	{

		$options = get_option( 'mbv_email_validator' );
		$api_key = isset( $options['api_key'] ) ? $options['api_key'] : ' ';
		echo '<input id="api_key" name="mbv_email_validator[api_key]" size="40" type="text" value="' . esc_attr( $api_key ). '" style="margin-bottom: 5px;" required/><br />';
		printf( __( 'You can sign up for a <a href="%s" target="blank">free MailboxValidator API key</a>.', 'mailboxvalidator-email-validator' ), esc_url( 'https://www.mailboxvalidator.com/plans#api' ) );

	}

	public function mbv_debug_mode_setting_option()
	{

		$options = get_option( 'mbv_email_validator' );
		$debug_mode_on_off = isset( $options['debug_mode_on_off'] ) ? $options['debug_mode_on_off'] : 'off';
		echo '<label><input type="radio" name="mbv_email_validator[debug_mode_on_off]" id="debug_mode_option" value="on"' . ( ( $debug_mode_on_off == 'on' ) ? ' checked' : '' ) . ' /> '. __('On', 'mailboxvalidator-email-validator' ) . '</label>
		<label><input type="radio" name="mbv_email_validator[debug_mode_on_off]" id="debug_mode_option" value="off"' . ( ( $debug_mode_on_off == 'off' ) ? ' checked' : '' ) . ' style="margin-left: 5px;" /> '. __('Off', 'mailboxvalidator-email-validator' ) . '</label><br />';
		_e( 'Log down the API transaction details for debugging purpose. Please disable this option for the live mode.', 'mailboxvalidator-email-validator' );

	}

	public function mbv_valid_email_setting_option()
	{

		$options = get_option( 'mbv_email_validator' );
		$valid_email_on_off = isset( $options['valid_email_on_off'] ) ? $options['valid_email_on_off'] : 'off';
		echo '<label><input type="radio" name="mbv_email_validator[valid_email_on_off]" id="valid_email_option" value="on"' . ( ( $valid_email_on_off == 'on' ) ? ' checked' : '' ) . ' /> '. __('On', 'mailboxvalidator-email-validator' ) . '</label>
		<label><input type="radio" name="mbv_email_validator[valid_email_on_off]" id="valid_email_option" value="off"' . ( ( $valid_email_on_off == 'off' ) ? ' checked' : '' ) . ' style="margin-left: 5px;" /> '. __('Off', 'mailboxvalidator-email-validator' ) . '</label><br />';
		_e( 'Block invalid email from using your service. This option will perform a comprehensive validation, including SMTP server check.', 'mailboxvalidator-email-validator' );

	}

	public function mbv_disposable_setting_option()
	{

		$options = get_option( 'mbv_email_validator' );
		$disposable_on_off = isset( $options['disposable_on_off'] ) ? $options['disposable_on_off'] : 'off';
		echo '<label><input type="radio" name="mbv_email_validator[disposable_on_off]" id="disposable_option" value="on"' . ( ( $disposable_on_off == 'on' ) ? ' checked' : '' ) . ' /> '. __('On', 'mailboxvalidator-email-validator' ) . '</label>
		<label><input type="radio" name="mbv_email_validator[disposable_on_off]" id="disposable_option" value="off"' . ( ( $disposable_on_off == 'off' ) ? ' checked' : '' ) . ' style="margin-left: 5px;" /> '. __('Off', 'mailboxvalidator-email-validator' ) . '</label><br />';
		_e( 'Block disposable email from registering for your service.', 'mailboxvalidator-email-validator' );

	}

	public function mbv_free_setting_option()
	{

		$options = get_option( 'mbv_email_validator' );
		$free_on_off = isset( $options['free_on_off'] ) ? $options['free_on_off'] : 'off';
		echo '<label><input type="radio" name="mbv_email_validator[free_on_off]" id="free_option" value="on"' . ( ( $free_on_off == 'on' ) ? ' checked' : '' ) . ' /> '. __('On', 'mailboxvalidator-email-validator' ) . '</label>
		<label><input type="radio" name="mbv_email_validator[free_on_off]" id="free_option" value="off"' . ( ( $free_on_off == 'off' ) ? ' checked' : '' ) . ' style="margin-left: 5px;" /> '. __('Off', 'mailboxvalidator-email-validator' ) . '</label><br />';
		_e( 'Block free email type from registering for your service.', 'mailboxvalidator-email-validator' );

	}

	public function mbv_role_setting_option()
	{

		$options = get_option( 'mbv_email_validator' );
		$role_on_off = isset( $options['role_on_off'] ) ? $options['role_on_off'] : 'off';
		echo '<label><input type="radio" name="mbv_email_validator[role_on_off]" id="role_option" value="on"' . ( ( $role_on_off == 'on' ) ? ' checked' : '' ) . ' /> '. __('On', 'mailboxvalidator-email-validator' ) . '</label>
		<label><input type="radio" name="mbv_email_validator[role_on_off]" id="role_option" value="off"' . ( ( $role_on_off == 'off' ) ? ' checked' : '' ) . ' style="margin-left: 5px;" /> '. __('Off', 'mailboxvalidator-email-validator' ) . '</label><br />';
		_e( 'Block role-based type email, such as admin@, support@, sales@ and so on, from registering for your service.', 'mailboxvalidator-email-validator' );

	}

	public function mbv_custom_domain_blacklist_option()
	{
		
		$options = get_option( 'mbv_email_validator' );
		$blacklisted_domains = isset( $options['blacklisted_domains'] ) ? $options['blacklisted_domains'] : '';
		echo '<input id="blacklist_domain" name="mbv_email_validator[blacklisted_domains]" size="40" type="text" value="' . $blacklisted_domains . '" style="margin-bottom: 5px;"/><br />';
		// echo '​<textarea id="blacklist_domain" name="mbv_email_validator[blacklisted_domains]" rows="10" cols="70">' . $blacklisted_domains. '</textarea>';
		echo '<label>';
		_e( 'Block email domains from registering for your service. Please enter one domain name in each row.', 'mailboxvalidator-email-validator' );
		echo '</label>';
	}

	public function mbv_valid_error_message_setting()
	{

		$options = get_option( 'mbv_email_validator' );
		$valid_error_message = $options['valid_error_message'] ?? 'Invalid email address. Please enter a valid email address.';
		echo '<input id="valid_error_message" name="mbv_email_validator[valid_error_message]" style="width:100%" type="text" value="' . $valid_error_message . '" maxlength="255" />';
		echo '<label>Design your custom error message for invalid email at here.</label>';

	}

	public function mbv_disposable_error_message_setting()
	{

		$options = get_option( 'mbv_email_validator' );
		$disposable_error_message = $options['disposable_error_message'] ?? 'Invalid email address. Please enter a non-disposable email address.';
		echo '<input id="disposable_error_message" name="mbv_email_validator[disposable_error_message]" style="width:100%" type="text" value="' . $disposable_error_message . '" maxlength="255" />';
		echo '<label>Design your custom error message for disposable email at here.</label>';

	}

	public function mbv_free_error_message_setting()
	{

		$options = get_option( 'mbv_email_validator' );
		$free_error_message = $options['free_error_message'] ?? 'Invalid email address. Please enter a non-free email address.';
		echo '<input id="free_error_message" name="mbv_email_validator[free_error_message]" style="width:100%" type="text" value="' . $free_error_message . '" maxlength="255" />';
		echo '<label>Design your custom error message for free email at here.</label>';

	}

	public function mbv_role_error_message_setting()
	{

		$options = get_option( 'mbv_email_validator' );
		$role_error_message = $options['role_error_message'] ?? 'Invalid email address. Please enter a non-role email address.';
		echo '<input id="role_error_message" name="mbv_email_validator[role_error_message]" style="width:100%" type="text" value="' . $role_error_message . '" maxlength="255" />';
		echo '<label>Design your custom error message for role email at here.</label>';

	}

	public function mbv_custom_domain_error_message_setting()
	{

		$options = get_option( 'mbv_email_validator' );
		$custom_domain_error_message = $options['custom_domain_error_message'] ?? 'This email address domain has been denied access.';
		echo '<input id="custom_domain_error_message" name="mbv_email_validator[custom_domain_error_message]" style="width:100%" type="text" value="' . $custom_domain_error_message . '" maxlength="255" />';
		echo '<label>Design your custom error message for blocked email domain(s).</label>';

	}

	public function mbv_general_admin_notice()
	{

		$options = get_option( 'mbv_email_validator' );
		
		// Show notice if the MBV API key not been saved yet
		if ( $options['api_key'] == '' || $options['api_key'] == ' ' ) {
			/* echo '<div class="notice notice-warning is-dismissible">
				 <p>Please get your MailboxValidator API key from <a href="https://www.mailboxvalidator.com/plans#api">here</a> and save in <a href="options-general.php?page=email-validator">setting page</a>.</p>
			 </div>';*/
			 // $notice1 = '<div class="notice notice-warning is-dismissible"><p>' . ;
			 echo '<div class="notice notice-warning is-dismissible"><p>';
			 // esc_html_e('Please get your MailboxValidator API key from <a href="https://www.mailboxvalidator.com/plans#api">here</a> and save in <a href="options-general.php?page=email-validator">setting page</a>.','mailboxvalidator-email-validator');
			 printf( __( 'Please sign up for a <a href="%1$s">free MailboxValidator API key</a> to enable the email blocking.', 'mailboxvalidator-email-validator' ), 'https://www.mailboxvalidator.com/plans#api', 'options-general.php?page=mailboxvalidator-email-validator' );
			 echo '</p></div>';
		} 
		
		//TBD
		// Show notice if the remaining credits is running low (less than 100)
		/*if ( $options['remaining_credits'] < 100 ) {
			 echo '<div class="notice notice-error is-dismissible">
				 <p>Your MailboxValidator API credits is low. Please purchase more credits from <a href="https://www.mailboxvalidator.com/plans#api">here</a>.</p>
			 </div>';
		}*/

	}
	
	// Enqueue the script.
	public function plugin_enqueues( $hook )
	{

		if ( $hook == 'plugins.php' ) {
			// Add in required libraries for feedback modal
			wp_enqueue_script( 'jquery-ui-dialog' );
			wp_enqueue_style( 'wp-jquery-ui-dialog' );

			// wp_enqueue_script( 'mailboxvalidator_email_validator_admin_script', plugins_url( '/assets/js/feedback.js', __FILE__ ), ['jquery'], null, true );
			// Register the script
			wp_register_script( 'mailboxvalidator_email_validator_admin_script', plugins_url( '/assets/js/feedback.js', __FILE__ ), ['jquery'], null, true );
			 
			// Localize the script with new data
			$translation_array = array(
				'some_string' => __( 'Quick Feedback', 'mailboxvalidator-email-validator' ),
				'some_string1' => __( 'Please select your feedback.', 'mailboxvalidator-email-validator' ),
			);
			wp_localize_script( 'mailboxvalidator_email_validator_admin_script', 'object_name', $translation_array );
			 
			// Enqueued script with localized data.
			wp_enqueue_script( 'mailboxvalidator_email_validator_admin_script' );
		}
	}
	
	public function admin_footer_text( $footer_text )
	{
		// $plugin_name = substr( basename( __FILE__ ), 0, strpos( basename( __FILE__ ), '.' ) );
		$plugin_name = 'mailboxvalidator-email-validator';
		$current_screen = get_current_screen();

		if ( ( $current_screen && strpos( $current_screen->id, $plugin_name ) !== false ) ) {
			$footer_text .= sprintf(
				__( 'Love our plugin? Please leave us a %2$s rating. A huge thanks in advance!', $plugin_name ),
				'<strong>' . __( 'MailboxValidator Email Validator', $plugin_name ) . '</strong>',
				'<a href="https://wordpress.org/support/plugin/' . $plugin_name . '/reviews/?filter=5/#new-post" target="_blank">&#9733;&#9733;&#9733;&#9733;&#9733;</a>'
			 );
		}

		if ( $current_screen->id == 'plugins' ) {
			return $footer_text . '
			<div id="mailboxvalidator-email-validator-feedback-modal" class="hidden" style="max-width:800px">
				<span id="mailboxvalidator-email-validator-feedback-response"></span>
				<p>
					<strong>'. __('Would you mind sharing with us the reason to deactivate the plugin', 'mailboxvalidator-email-validator' ) . '?</strong>
				</p>
				<p>
					<label>
						<input type="radio" name="mailboxvalidator-email-validator-feedback" value="1"> '. __('I no longer need the plugin', 'mailboxvalidator-email-validator' ) . '
					</label>
				</p>
				<p>
					<label>
						<input type="radio" name="mailboxvalidator-email-validator-feedback" value="2"> '. __('I couldn\'t get the plugin to work', 'mailboxvalidator-email-validator' ) . '
					</label>
				</p>
				<p>
					<label>
						<input type="radio" name="mailboxvalidator-email-validator-feedback" value="3"> '. __('The plugin doesn\'t meet my requirements', 'mailboxvalidator-email-validator' ) . '
					</label>
				</p>
				<p>
					<label>
						<input type="radio" name="mailboxvalidator-email-validator-feedback" value="4"> '. __('Other concerns', 'mailboxvalidator-email-validator' ) . '
						<br><br>
						<textarea id="mailboxvalidator-email-validator-feedback-other" style="display:none;width:100%"></textarea>
					</label>
				</p>
				<p>
					<div style="float:left">
						<input type="button" id="mailboxvalidator-email-validator-submit-feedback-button" class="button button-danger" value="'. esc_attr__('Submit & Deactivate', 'mailboxvalidator-email-validator' ) . '" />
					</div>
					<div style="float:right">
						<a href="#">'. __('Skip & Deactivate', 'mailboxvalidator-email-validator' ) . '</a>
					</div>
				</p>
			</div>';
		}

		return $footer_text;
	}

	public function submit_feedback()
	{
		$feedback = ( isset( $_POST['feedback'] ) ) ? $_POST['feedback'] : '';
		$others = sanitize_text_field (( isset( $_POST['others'] ) ) ? $_POST['others'] : '' );

		$options = [
			1 => "I no longer need the plugin",
			2 => "I couldn't get the plugin to work",
			3 => "The plugin doesn't meet my requirements",
			4 => "Other concerns" . ( ( $others ) ? ( " - " . $others ) : "" ),
		];

		if ( isset($options[$feedback] )) {
			if ( ! class_exists( 'WP_Http' ) ) {
				include_once ABSPATH . WPINC . '/class-http.php';
			}

			$request = new WP_Http();
			$response = $request->request( 'https://www.mailboxvalidator.com/wp-plugin-feedback?' . http_build_query( [
				'name'    => 'mailboxvalidator-email-validator',
				'message' => $options[$feedback],
			] ), ['timeout' => 5] );
		}
	}
	
	private function create_table()
	{
		$GLOBALS['wpdb']->query('
		CREATE TABLE IF NOT EXISTS ' . $GLOBALS['wpdb']->prefix . 'mailboxvalidator_email_validator_log (
			`log_id` INT(11) NOT NULL AUTO_INCREMENT,
			`email_address` VARCHAR(255) NOT NULL COLLATE \'utf8_bin\',
			`email_domain` VARCHAR(255) NOT NULL COLLATE \'utf8_bin\',
			`status` VARCHAR(10) NOT NULL COLLATE \'utf8_bin\',
			`is_disposable` VARCHAR(10) NOT NULL COLLATE \'utf8_bin\',
			`is_free` VARCHAR(10) NOT NULL COLLATE \'utf8_bin\',
			`is_role` VARCHAR(10) NOT NULL COLLATE \'utf8_bin\',
			`is_blacklisted` VARCHAR(10) NOT NULL COLLATE \'utf8_bin\',
			`date_created` DATETIME NOT NULL,
			PRIMARY KEY (`log_id`),
			INDEX `idx_email_address` (`email_address`),
			INDEX `idx_email_domain` (`email_domain`),
			INDEX `idx_status` (`status`),
			INDEX `idx_is_disposable` (`is_disposable`),
			INDEX `idx_date_created` (`date_created`),
			INDEX `idx_is_free` (`is_free`),
			INDEX `idx_is_role` (`is_role`),
			INDEX `idx_is_blacklisted` (`is_blacklisted`)
		) COLLATE=\'utf8_bin\'');
	}
	
	
	private function mbv_single( $emailAddress, $api_key, $debug_mode_on_off )
	{
		try{
			// Now we need to send the data to MBV API Key and return back the result.
			/*$url = 'https://api.mailboxvalidator.com/v1/validation/single?key=' . str_replace( ' ', '', $api_key ) . '&email=' . str_replace( ' ', '', $emailAddress ) . '&source=wordpress';

			// Now we used WordPress custom HTTP API method to get the result from MBV API.
			$results = wp_remote_get( $url );

			if ( !is_wp_error( $results ) ) {
				$body = wp_remote_retrieve_body( $results );

				// Decode the return json results and return the data.
				$data = json_decode( $body, true );
				
				if ($debug_mode_on_off == true) {
					file_put_contents ( __DIR__ . '/mbv_plugin_logs.log' , var_export( $data, true ) . PHP_EOL, FILE_APPEND );
				}
				
				// update remaining_credits
				// $mbv_options = get_option( 'mbv_email_validator' );
				// $mbv_options['remaining_credits'] = $data['credits_available'];
				// update_option('mbv_email_validator', $mbv_options );
				
				return $data;
			} else {
				// if connection error, let it pass
				return true;
			}*/
			$mbv = new \MailboxValidator\EmailValidation ($api_key);
			$results = $mbv->validateEmail(str_replace( ' ', '', $emailAddress ));
			if ($results != null) {
				foreach ($results as $key => $value) {
					$data[$key] = $value;
				}
				if ($debug_mode_on_off === 'on') {
						file_put_contents ( __DIR__ . '/mbv_plugin_logs.log' , var_export( $data, true ) . PHP_EOL, FILE_APPEND );
					}
				return $data;
			} else {
				return true;
			}
		}
		catch( Exception $e ) {
			return true;
		}
	}

	private function mbv_is_valid_email( $api_result )
	{
		if ( $api_result != '' ) {
			// if ( $api_result['error_message'] === '' ) {
			if ( !(array_key_exists('error_message', $api_result)) ) {
				if ( $api_result['status']) {
					return true;
				} else {
					return false;
				}
			} else {
				// If error message occured, let it pass first.
				return true;
			}
		} else {
			// If error message occured, let it pass first.
			return true;
		}
	}

	private function mbv_is_role( $api_result )
	{
		if ( $api_result != '' ) {
			// if ( $api_result['error_message'] === '' ) {
			if ( !(array_key_exists('error_message', $api_result)) ) {
				if ( $api_result['is_role']) {
					return true;
				} else {
					return false;
				}
			} else {
				// If error message occured, let it pass first.
				return false;
			}
		} else {
			// If error message occured, let it pass first.
			return false;
		}
	}

	private function mbv_is_free( $emailAddress, $api_key, $debug_mode_on_off )
	{
		try {
			$mbv = new \MailboxValidator\EmailValidation ($api_key);
			$results = $mbv->isFreeEmail(str_replace( ' ', '', $emailAddress ));
			if ($results != null) {
				foreach ($results as $key => $value) {
					$data[$key] = $value;
				}
				if ($debug_mode_on_off === 'on') {
					file_put_contents ( __DIR__ . '/mbv_plugin_logs.log' , var_export( $data, true ) . PHP_EOL, FILE_APPEND );
				}
				// if ( $data['error_message'] === '' ) {
				if ( !(array_key_exists('error_message', $data)) ) {
					if ( $data['is_free']) {
						return true;
					} else {
						return false;
					}
				} else {
					// If error message occured, let it pass first.
					return false;
				}
			} else {
				return false;
			}
		}
		catch( Exception $e ) {
			return false;
		}
	}


	private function mbv_is_disposable( $emailAddress, $api_key, $debug_mode_on_off )
	{
		try {
			$mbv = new \MailboxValidator\EmailValidation ($api_key);
			$results = $mbv->isDisposableEmail(str_replace( ' ', '', $emailAddress ));
			if ($results != null) {
				foreach ($results as $key => $value) {
					$data[$key] = $value;
				}
				if ($debug_mode_on_off === 'on') {
					file_put_contents ( __DIR__ . '/mbv_plugin_logs.log' , var_export( $data, true ) . PHP_EOL, FILE_APPEND );
				}
				// if ( $data['error_message'] === '' ) {
				if ( !(array_key_exists('error_message', $data)) ) {
					if ( $data['is_disposable'] ) {
						return true;
					} else {
						return false;
					}
				} else {
					// If error message occured, let it pass first.
					return false;
				}
			} else {
				return false;
			}
		}
		catch( Exception $e ) {
			return false;
		}

	}

	private function mbv_get_admin_email ()
	{
		// check if user have set the mailer plugin or not. If not, get the admin email.
		if ( defined( 'WPMS_PLUGIN_VER' ) ) {
			$wp_mail_options = get_option( 'wp_mail_smtp' );
			$admin_email = sanitize_email ( $wp_mail_options['mail']['from_email'] );
		} else {
			$admin_email = sanitize_email ( get_option( 'admin_email' ) );
		}
		return $admin_email;
	}

	private function mbv_email_validation( $email, $mbv_options )
	{
		
		global $wpdb;
		
		$datetime_started = date('Y-m-d H:i:s');

		$table_name = $wpdb->prefix . 'mailboxvalidator_email_validator_log';
		
		// Sanitize email address before continue
		$email = sanitize_email ( $email );
		
		$mbv_validation_result = array();
		
		$email_parts = explode( '@', $email );
		
		// First check the domain blacklisted list, if the domain is in the list, straight return false
		if ( $mbv_options['blacklisted_domains'] !== '' ) {
			$blacklisted_domains_array = explode( ';', $mbv_options['blacklisted_domains'] );
			
			// $email_parts = explode( '@', $email );
			
			if ( ( isset( $email_parts[1] ) ) && ( in_array( $email_parts[1], $blacklisted_domains_array ) ) ) {
				
				// if (get_option('ip2location_country_blocker_log_enabled') && $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
					$wpdb->query('INSERT INTO ' . $table_name . ' (email_address, email_domain, status, is_disposable, is_free, is_role, is_blacklisted, date_created) VALUES ("' . $email . '", "' . $email_parts[1] . '", "-", "-", "-", "-", "True", "' . $datetime_started . '")');
				// }
				
				// return false;
				$mbv_validation_result['status'] = false;
				$mbv_validation_result['reason'] = 'blacklisted_domains';
				return $mbv_validation_result;
			}
		}
		
		// Let user choose to log down the api response to log file or not.
		$debug_mode_on_off = isset( $mbv_options['debug_mode_on_off'] ) ? $mbv_options['debug_mode_on_off'] : 'off';
		
		$single_result = $mbv_options['valid_email_on_off'] == 'on' || $mbv_options['role_on_off'] == 'on' ? $this->mbv_single( $email, $mbv_options['api_key'], $debug_mode_on_off ) : '';
		$is_valid_email = $mbv_options['valid_email_on_off'] == 'on' && $single_result != '' ? $this->mbv_is_valid_email( $single_result ) : true;
		$is_role = $mbv_options['role_on_off'] == 'on' && $single_result != '' ? $this->mbv_is_role( $single_result ) : false;
		// $is_disposable = $mbv_options['disposable_on_off'] == 'on' ? $this->mbv_is_disposable( $email, $mbv_options['api_key'], $debug_mode_on_off ) : false;
		// $is_free = $mbv_options['free_on_off'] == 'on' ? $this->mbv_is_free( $email, $mbv_options['api_key'], $debug_mode_on_off ) : false;
		if ($mbv_options['disposable_on_off'] === 'on') {
			if ($single_result != '' && $single_result['error_message'] == '') {
				$is_disposable = ($single_result['is_disposable']) ? true : false;
			} else {
				$is_disposable = $this->mbv_is_disposable( $email , $mbv_options['api_key'], $debug_mode_on_off );
			}
		} else {
			$is_disposable = false;
		}
		if ($mbv_options['free_on_off'] === 'on') {
			if ($single_result != '' && $single_result['error_message'] == '') {
				$is_free = ($single_result['is_free']) ? true : false;
			} else {
				$is_free = $this->mbv_is_free( $email , $mbv_options['api_key'], $debug_mode_on_off );
			}
		} else {
			$is_free = false;
		}
		
		if( $is_valid_email == false ){
			$mbv_validation_result['status'] = false;
			// $mbv_validation_result['reason'] = 'invalid_email';
			if ( ( $mbv_options['disposable_on_off'] == 'on' ) && ( $is_disposable == true ) ) {
				$mbv_validation_result['reason'] = 'disposable_email';
			} else {
				$mbv_validation_result['reason'] = 'invalid_email';
			}
			// if (get_option('ip2location_country_blocker_log_enabled') && $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
				$wpdb->query('INSERT INTO ' . $table_name . ' (email_address, email_domain, status, is_disposable, is_free, is_role, is_blacklisted, date_created) VALUES ("' . $email . '", "' . $email_parts[1] . '", "' .$single_result['status']. '", "' .$single_result['is_disposable']. '", "' .$single_result['is_free']. '", "' .$single_result['is_role']. '", "False", "' . $datetime_started . '")');
			// }
		} elseif( $is_disposable == true ){
			$mbv_validation_result['status'] = false;
			$mbv_validation_result['reason'] = 'disposable_email';
			// if (get_option('ip2location_country_blocker_log_enabled') && $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
				$wpdb->query('INSERT INTO ' . $table_name . ' (email_address, email_domain, status, is_disposable, is_free, is_role, is_blacklisted, date_created) VALUES ("' . $email . '", "' . $email_parts[1] . '", "-", "True", "-", "-", "False", "' . $datetime_started . '")');
			// }
		} elseif( $is_free == true ){
			$mbv_validation_result['status'] = false;
			$mbv_validation_result['reason'] = 'free_email';
			// if (get_option('ip2location_country_blocker_log_enabled') && $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
				$wpdb->query('INSERT INTO ' . $table_name . ' (email_address, email_domain, status, is_disposable, is_free, is_role, is_blacklisted, date_created) VALUES ("' . $email . '", "' . $email_parts[1] . '", "-", "-", "True", "-", "False", "' . $datetime_started . '")');
			// }
		} elseif( $is_role == true ){
			$mbv_validation_result['status'] = false;
			$mbv_validation_result['reason'] = 'role_email';
			// if (get_option('ip2location_country_blocker_log_enabled') && $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
				$wpdb->query('INSERT INTO ' . $table_name . ' (email_address, email_domain, status, is_disposable, is_free, is_role, is_blacklisted, date_created) VALUES ("' . $email . '", "' . $email_parts[1] . '", "' .$single_result['status']. '", "' .$single_result['is_disposable']. '", "' .$single_result['is_free']. '", "' .$single_result['is_role']. '", "False", "' . $datetime_started . '")');
			// }
		} else {
			// return true;
			$mbv_validation_result['status'] = true;
		}
		return $mbv_validation_result;
	}


	public function mbv_email_validator_filter( $email )
	{
		// Sanitize email address before continue
		$email = sanitize_email ( $email );

		// Get option settings to know which validator is been called
		$mbv_options = get_option( 'mbv_email_validator' );
		
		$mbv_options['api_key'] = trim($mbv_options['api_key']);
		
		$admin_email = $this->mbv_get_admin_email();
		
		if ( is_admin() ) {
			// If user have logged as admin, meaning most probably they are editing the settings, let it pass if is_email been called.
			return true;
		}
		if( ( is_plugin_active( 'formidable/formidable.php' ) ) | ( is_plugin_active( 'caldera-forms/caldera-core.php' ) ) | ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) ) {
			// If the email is same with the admin_email, meaning either Caldera or Formidable are most probably setup the email before send. Pass to avoid unneccasary call to API.
			if ( $email == $admin_email ) {
				return true;
			}
		}
		if ( ( $_SERVER['REQUEST_URI'] == '/wp-login.php' ) | ( $_SERVER['REQUEST_URI'] == '/wp-login.php?loggedout=true' )| ( $_SERVER['REQUEST_URI'] == '/wp-cron.php' ) ) {
			// if wp-login.php is been called for login to dashboard, skip the check.
			return true;
		}

		// if ( ( ! ( $mbv_options['api_key'] == '' ) ) && ( $email != '' ) ) { 
		// if ( ( ! ( $mbv_options['api_key'] == '' ) ) && ( $email != '' ) && ( $email != $admin_email ) ) { 
		if ( ( ! ( $mbv_options['api_key'] == '' ) ) && ( $email != '' ) && ( $email != $admin_email ) && (filter_var(trim($email), FILTER_VALIDATE_EMAIL)) && (preg_match('/^[A-Z\d]+$/', $mbv_options['api_key'])) ) { 
			// do the email validation
			
			$validation_result = $this->mbv_email_validation( trim($email), $mbv_options );
			
			if ( ( is_array( $validation_result ) ) && array_key_exists( 'status', $validation_result ) ) {
				if ( $validation_result['status'] == false ) {
					$_SESSION['mbv_validate'] = true;
					$_SESSION['mbv_reason'] = $validation_result['reason'];
					$_SESSION['mbv_status'] = $validation_result['status'];
					return false;
				} else {
					return true;
				}
			} else {
				return true;
			}
			
		} else {
			// If the user do not enter the API key, or ignore the admin notice, or the $email is empty, just let it pass.
			// file_put_contents( plugins_url( '/mbv_plugin_logs.log', __FILE__ ) , 'API key: ' . $mbv_options['api_key'] . PHP_EOL, FILE_APPEND );
			return true;
		}
	}

	public function mbv_wpcf7_custom_email_validator_filter( $result, $tags )
	{

		$tags = new WPCF7_FormTag( $tags );

		$type = $tags->type;

		$name = $tags->name;
		// Sanitize email address before continue
		$email = sanitize_email ( $_POST[$name] );

		// Get option settings to know which validator is been called
		$mbv_options = get_option( 'mbv_email_validator' );
		
		$tags = new WPCF7_FormTag( $tags );
		
		$type = $tags->type;

		$name = $tags->name;
		
		$admin_email = $this->mbv_get_admin_email();
		
		$email = $_POST[ $name ];
		
		if ( is_admin() ) {
			// If user have logged as admin, meaning most probably they are editing the settings, let it pass if is_email been called.
			return true;
		}
		
		if ( ( $_SERVER['REQUEST_URI'] == '/wp-login.php' ) | ( $_SERVER['REQUEST_URI'] == '/wp-login.php?loggedout=true' ) ) {
			// if wp-login.php is been called for login to dashboard, skip the check.
			return true;
		}

		if ( ( 'email' == $type || 'email*' == $type ) && ( $mbv_options['api_key'] != '' ) && ( $email != '' ) ) {
			$validation_result = $this->mbv_email_validation( $email, $mbv_options );
			
			if ( ( is_array( $validation_result ) ) && array_key_exists( 'status', $validation_result ) ) {
				if ( $validation_result['status'] == false ) {
					switch( $validation_result['reason'] ){
						case 'blacklisted_domains':
							$result->invalidate( $tags, __( $mbv_options['blacklisted_domain_error_message'] ?? 'This email address domain has been denied access.', 'mailboxvalidator-email-validator' ));
							break;
						case 'invalid_email':
							$result->invalidate( $tags, __( $mbv_options['valid_error_message'] ?? 'Please enter a valid email address.', 'mailboxvalidator-email-validator' ));
							break;
						case 'disposable_email':
							$result->invalidate( $tags, __( $mbv_options['disposable_error_message'] ?? 'Please enter a non-disposable email address.', 'mailboxvalidator-email-validator' ));
							break;
						case 'free_email':
							$result->invalidate( $tags, __( $mbv_options['free_error_message'] ?? 'Please enter a non-free email address.', 'mailboxvalidator-email-validator' ));
							break;
						case 'role_email':
							$result->invalidate( $tags, __( $mbv_options['role_error_message'] ?? 'Please enter a non-role email address.', 'mailboxvalidator-email-validator' ));
							break;
						// case default:
							// return false;
					}
				// } else {
					// return true;
				}
			// } else {
				// return true;
			}
		// } else {
			// If the user do not enter the API key, or ignore the admin notice, or the $email is empty, just let it pass.
			// return true;
		}
		
		return $result;
	}

	public function mbv_frm_validate_entry( $errors, $values )
	{
		// Sanitize email address before continue
		$email = sanitize_email ( $email );

		foreach ( $values['item_meta'] as $key => $value ) {
			if ( preg_match( "/^\S+@\S+\.\S+$/", $value ) ) {
					$mbv_options = get_option( 'mbv_email_validator' );
					$email = $value;
					$admin_email = $this->mbv_get_admin_email();
					if ( ( $email != $admin_email ) && ( $mbv_options['api_key'] != '' ) && ( $email != '' ) ) {
						$validation_result = $this->mbv_email_validation( $email, $mbv_options );
			
						if ( ( is_array( $validation_result ) ) && array_key_exists( 'status', $validation_result ) ) {
							if ( $validation_result['status'] == false ) {
								switch( $validation_result['reason'] ){
									case 'blacklisted_domains':
										$errors['ct_error'] = $mbv_options['blacklisted_domain_error_message'] ?? 'This email address domain has been denied access.';
										break;
									case 'invalid_email':
										$errors['ct_error'] = $mbv_options['valid_error_message'] ?? 'Please enter a valid email address.';
										break;
									case 'disposable_email':
										$errors['ct_error'] = $mbv_options['disposable_error_message'] ?? 'Please enter a non-disposable email address.';
										break;
									case 'free_email':
										$errors['ct_error'] = $mbv_options['free_error_message'] ?? 'Please enter a non-free email address.';
										break;
									case 'role_email':
										$errors['ct_error'] = $mbv_options['role_error_message'] ?? 'Please enter a non-role email address.';
										// $errors['ct_error'] = 'Please enter a non-role email address.';
										break;
								}
							}
						}
					}
				}
			}
		// return false;
		return $errors;
	}

	public function mbv_caldera_forms_validate_email( $entry, $field, $form )
	{
		// Sanitize email address before continue
		$email = sanitize_email ( $email );

		if ( ! ( empty ( $entry ) ) && ( $entry != '' ) ) {
			$mbv_options = get_option( 'mbv_email_validator' );
			$email = $entry;
			$admin_email = $this->mbv_get_admin_email();
			if ( ( $email != $admin_email ) && ( $mbv_options['api_key'] != '' ) && ( $email != '' ) ) {
			
				$validation_result = $this->mbv_email_validation( $email, $mbv_options );
				// file_put_contents ( __DIR__ . '/mbv_plugin_logs.log' , var_export( $validation_result, true ) . PHP_EOL, FILE_APPEND );

				if ( ( is_array( $validation_result ) ) && array_key_exists( 'status', $validation_result ) ) {
					if ( $validation_result['status'] == false ) {
						switch( $validation_result['reason'] ){
							case 'blacklisted_domains':
								$errormessage = $mbv_options['blacklisted_domain_error_message'] ?? 'This email address domain has been denied access.';
								break;
							case 'invalid_email':
								$errormessage = $mbv_options['valid_error_message'] ?? 'Please enter a valid email address.';
								break;
							case 'disposable_email':
								$errormessage = $mbv_options['disposable_error_message'] ?? 'Please enter a non-disposable email address.';
								break;
							case 'free_email':
								$errormessage = $mbv_options['free_error_message'] ?? 'Please enter a non-free email address.';
								break;
							case 'role_email':
								$errormessage = $mbv_options['role_error_message'] ?? 'Please enter a non-role email address.';
								// $errormessage = 'Please enter a non-role email address.';
								break;
						}
					}
				}
			}
		}
		
		if ( $errormessage != '' ) {
			return new WP_Error( 400, __( $errormessage, 'caldera-forms' ) );
		}
	}

	public function mbv_bws_validate_email()
	{
		global $cntctfrm_error_message;
		
		// Sanitize email address before continue
		$email = sanitize_email ( $email );
		
		if ( ! ( empty ( $_POST['cntctfrm_contact_email'] ) ) && ( $_POST['cntctfrm_contact_email'] != '' ) ) {
			$mbv_options = get_option('mbv_email_validator');
			$email = htmlspecialchars( stripslashes( $_POST['cntctfrm_contact_email'] ) );
			$admin_email = $this->mbv_get_admin_email();
			if ( ( $email != $admin_email ) && ( $mbv_options['api_key'] != '' ) && ( $email != '' ) ) {
				$validation_result = $this->mbv_email_validation($email, $mbv_options);

				if ( ( is_array( $validation_result ) ) && array_key_exists( 'status', $validation_result ) ) {
					if ( $validation_result['status'] == false ) {
						switch( $validation_result['reason'] ){
							case 'blacklisted_domains':
								$cntctfrm_error_message['error_email'] = $mbv_options['blacklisted_domain_error_message'] ?? 'This email address domain has been denied access.';
								break;
							case 'invalid_email':
								$cntctfrm_error_message['error_email'] = $mbv_options['valid_error_message'] ?? 'Please enter a valid email address.';
								break;
							case 'disposable_email':
								$cntctfrm_error_message['error_email'] = $mbv_options['disposable_error_message'] ?? 'Please enter a non-disposable email address.';
								break;
							case 'free_email':
								$cntctfrm_error_message['error_email'] = $mbv_options['free_error_message'] ?? 'Please enter a non-free email address.';
								break;
							case 'role_email':
								$cntctfrm_error_message['error_email'] = $mbv_options['role_error_message'] ?? 'Please enter a non-role email address.';
								// $cntctfrm_error_message['error_email'] = 'Please enter a non-role email address.';
								break;
						}
					}
				}
			}
		}
		
	}

	public function mbv_wppb_validate_email( $message, $field, $request_data, $form_location )
	{
		global $wpdb;
		
		// Sanitize email address before continue
		$email = sanitize_email ( $email );
		
		if ( ! ( empty ($request_data['email'] ) ) && ( $request_data['email'] != '' ) ) {
			$mbv_options = get_option('mbv_email_validator');
			$email = $request_data['email'];
			$admin_email = $this->mbv_get_admin_email();
			if ( ( $email != $admin_email ) && ( $mbv_options['api_key'] != '' ) && ( $email != '' ) ) {
				$validation_result = $this->mbv_email_validation( $email, $mbv_options );

				if ( ( is_array( $validation_result ) ) && array_key_exists( 'status', $validation_result ) ) {
					if ( $validation_result['status'] == false ) {
						switch( $validation_result['reason'] ){
							case 'blacklisted_domains':
								return __( $mbv_options['blacklisted_domain_error_message'] ?? 'This email address domain has been denied access.', 'profile-builder' );
								break;
							case 'invalid_email':
								return __( $mbv_options['valid_error_message'] ?? 'Please enter a valid email address.', 'profile-builder' );
								break;
							case 'disposable_email':
								return __( $mbv_options['disposable_error_message'] ?? 'Please enter a non-disposable email address.', 'profile-builder' );
								break;
							case 'free_email':
								return __( $mbv_options['free_error_message'] ?? 'Please enter a non-free email address.', 'profile-builder' );
								break;
							case 'role_email':
								return __( $mbv_options['role_error_message'] ?? 'Please enter a non-role email address.', 'profile-builder' );
								// return __( 'Please enter a non-role email address.', 'profile-builder' );
								break;
						}
					}
				}
			}
		}
	}

	public function mbv_woo_validate_email($fields, $errors)
	{
		// Sanitize email address before continue
		// $email = sanitize_email ( $email );
		$email = '';
		if ( !empty( $fields[ 'billing_email' ]) ) {
			$email = sanitize_email ( $fields[ 'billing_email' ] );
		} else if ( !empty( $fields[ 'shipping_email' ]) ) {
			$email = sanitize_email ( $fields[ 'shipping_email' ] );
		}
		if ( $email != '' ) {
			$mbv_options = get_option('mbv_email_validator');
			$admin_email = $this->mbv_get_admin_email();
			if ( ( $email != $admin_email ) && ( $mbv_options['api_key'] != '' ) && ( $email != '' ) ) {
				$validation_result = $this->mbv_email_validation( $email, $mbv_options );

				if ( ( is_array( $validation_result ) ) && array_key_exists( 'status', $validation_result ) ) {
					if ( $validation_result['status'] == false ) {
						switch( $validation_result['reason'] ){
							case 'blacklisted_domains':
								$errors->add( 'validation', __( $mbv_options['blacklisted_domain_error_message'] ?? 'This email address domain has been denied access.', 'mailboxvalidator-email-validator' ) );
								break;
							case 'invalid_email':
								$errors->add( 'validation', __( $mbv_options['valid_error_message'] ?? 'Please enter a valid email address.', 'mailboxvalidator-email-validator' ) );
								break;
							case 'disposable_email':
								$errors->add( 'validation', __( $mbv_options['disposable_error_message'] ?? 'Please enter a non-disposable email address.', 'mailboxvalidator-email-validator' ) );
								break;
							case 'free_email':
								$errors->add( 'validation', __( $mbv_options['free_error_message'] ?? 'Please enter a non-free email address.', 'mailboxvalidator-email-validator' ) );
								break;
							case 'role_email':
								$errors->add( 'validation', __( $mbv_options['role_error_message'] ?? 'Please enter a non-role email address.', 'mailboxvalidator-email-validator' ) );
								// $errors->add( 'validation', __( 'Please enter a non-role email address.', 'mailboxvalidator-email-validator' ) );
								break;
						}
					}
				}
			}
		}
	}
	public function mbv_reg_validate_email ( $errors, $sanitized_user_login, $user_email )
	{
		if ( (isset($_SESSION['mbv_validate']) && isset($_SESSION['mbv_status'])) && $_SESSION['mbv_validate'] && (! $_SESSION['mbv_status']) ) {
			switch( $_SESSION['mbv_reason'] ){
				case 'blacklisted_domains':
					$errors->remove('invalid_email');
					$errors->add( 'invalid_email', __( $mbv_options['blacklisted_domain_error_message'] ?? 'This email address domain has been denied access.', 'mailboxvalidator-email-validator' ) );
					break;
				case 'invalid_email':
					$errors->remove('invalid_email');
					$errors->add( 'invalid_email', __( $mbv_options['valid_error_message'] ?? 'Please enter a valid email address.', 'mailboxvalidator-email-validator' ) );
					break;
				case 'disposable_email':
					$errors->remove('invalid_email');
					$errors->add( 'invalid_email', __( $mbv_options['disposable_error_message'] ?? 'Please enter a non-disposable email address.', 'mailboxvalidator-email-validator' ) );
					// $errors[ 'invalid_email' ][0] = $mbv_options['disposable_error_message'] ?? 'Please enter a non-disposable email address.';
					break;
				case 'free_email':
					$errors->remove('invalid_email');
					$errors->add( 'invalid_email', __( $mbv_options['free_error_message'] ?? 'Please enter a non-free email address.', 'mailboxvalidator-email-validator' ) );
					break;
				case 'role_email':
					$errors->remove('invalid_email');
					$errors->add( 'invalid_email', __( $mbv_options['role_error_message'] ?? 'Please enter a non-role email address.', 'mailboxvalidator-email-validator' ) );
					// $errors->add( 'invalid_email', __( 'Please enter a non-role email address.', 'mailboxvalidator-email-validator' ) );
					break;
			}
		}
		// $_SESSION['mbv_validate'] = false;
		// $_SESSION['mbv_reason'] = '';
		return $errors;
	}
	
	public function mbv_comment_validate_email ()
	{
		add_action( 'pre_comment_on_post', array( $this, 'hook_is_email_filter' ) );
		add_action( 'comment_post', array( $this, 'dehook_is_email_filter' ) );
	}
	
	public function hook_is_email_filter()
	{

		// add_filter( 'is_email', array( $this, 'validate' ), 10, 3 );
		add_filter( 'is_email', array( $this, 'mbv_email_validator_filter' ), 10, 3 );
	}

	/**
	 * Remove the is_email Filter
	 */
	public function dehook_is_email_filter()
	{

		// remove_filter( 'is_email', array( $this, 'validate' ), 10, 3 );
		remove_filter( 'is_email', array( $this, 'mbv_email_validator_filter' ), 10, 3 );
	}
}