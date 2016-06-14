<?php
/**
 * Correios integration.
 *
 * @package WooCommerce_Correios/Classes/Integration
 * @since   3.0.0
 * @version 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Correios integration class.
 */
class WC_Correios_Integration extends WC_Integration {

	/**
	 * Initialize integration actions.
	 */
	public function __construct() {
		$this->id                 = 'correios-integration';
		$this->method_title       = __( 'Correios', 'woocommerce-correios' );

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Define user set variables.
		$this->tracking_enable         = $this->get_option( 'tracking_enable' );
		$this->tracking_debug          = $this->get_option( 'tracking_debug' );
		$this->autofill_enable         = $this->get_option( 'autofill_enable' );
		$this->autofill_expire         = $this->get_option( 'autofill_expire' );
		$this->autofill_force          = $this->get_option( 'autofill_force' );
		$this->autofill_empty_database = $this->get_option( 'autofill_empty_database' );
		$this->autofill_debug          = $this->get_option( 'autofill_debug' );

		// Actions.
		add_action( 'woocommerce_update_options_integration_' .  $this->id, array( $this, 'process_admin_options' ) );

		// Tracking history actions.
		add_filter( 'woocommerce_correios_enable_tracking_history', array( $this, 'setup_tracking_history' ), 10 );
		add_filter( 'woocommerce_correios_enable_tracking_debug', array( $this, 'setup_tracking_debug' ), 10 );

		// Autofill address actions.
		add_filter( 'woocommerce_correios_enable_autofill_addresses', array( $this, 'setup_autofill_addresses' ), 10 );
		add_filter( 'woocommerce_correios_enable_autofill_addresses_debug', array( $this, 'setup_autofill_addresses_debug' ), 10 );
		add_filter( 'woocommerce_correios_autofill_addresses_expire_time', array( $this, 'setup_autofill_addresses_expire_time' ), 10 );
		add_filter( 'woocommerce_correios_autofill_addresses_force_autofill', array( $this, 'setup_autofill_addresses_force_autofill' ), 10 );
		add_action( 'wp_ajax_correios_autofill_addresses_empty_database', array( $this, 'ajax_empty_database' ) );
	}

	/**
	 * Get tracking log url.
	 *
	 * @return string
	 */
	protected function get_tracking_log_link() {
		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.2', '>=' ) ) {
			return ' <a href="' . esc_url( admin_url( 'admin.php?page=wc-status&tab=logs&log_file=correios-tracking-history-' . sanitize_file_name( wp_hash( 'correios-tracking-history' ) ) . '.log' ) ) . '">' . __( 'View logs.', 'woocommerce-correios' ) . '</a>';
		}
	}

	/**
	 * Initialize integration settings fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'tracking' => array(
				'title'       => __( 'Tracking History Table', 'woocommerce-correios' ),
				'type'        => 'title',
				'description' => __( 'Displays a table with informations about the shipping in My Account > View Order page.', 'woocommerce-correios' ),
			),
			'tracking_enable' => array(
				'title'   => __( 'Enable/Disable', 'woocommerce-correios' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Tracking History Table', 'woocommerce-correios' ),
				'default' => 'no',
			),
			'tracking_debug' => array(
				'title'       => __( 'Debug Log', 'woocommerce-correios' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable logging for Tracking History', 'woocommerce-correios' ),
				'default'     => 'no',
				'description' => sprintf( __( 'Log %s events, such as WebServices requests.', 'woocommerce-correios' ), __( 'Tracking History Table', 'woocommerce-correios' ) ) . $this->get_tracking_log_link(),
			),
		);

		if ( $this->check_if_support_autofill() ) {
			$this->form_fields += array(
				'autofill_addresses' => array(
					'title'       => __( 'Autofill Addresses', 'woocommerce-correios' ),
					'type'        => 'title',
					'description' => __( 'Displays a table with informations about the shipping in My Account > View Order page.', 'woocommerce-correios' ),
				),
				'autofill_enable' => array(
					'title'   => __( 'Enable/Disable', 'woocommerce-correios' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable Autofill Addresses', 'woocommerce-correios' ),
					'default' => 'no',
				),
				'autofill_expire' => array(
					'title'       => __( 'Postcode Expire', 'woocommerce-correios' ),
					'type'        => 'select',
					'default'     => 'forever',
					'class'       => 'wc-enhanced-select',
					'description' => __( 'Defines how long a postcode will stay saved in the database before a new query.', 'woocommerce-correios' ),
					'options'     => array(
						'1'     => __( '1 month', 'woocommerce-correios' ),
						'2'     => sprintf( __( '%d month', 'woocommerce-correios' ), 2 ),
						'3'     => sprintf( __( '%d month', 'woocommerce-correios' ), 3 ),
						'4'     => sprintf( __( '%d month', 'woocommerce-correios' ), 4 ),
						'5'     => sprintf( __( '%d month', 'woocommerce-correios' ), 5 ),
						'6'     => sprintf( __( '%d month', 'woocommerce-correios' ), 6 ),
						'7'     => sprintf( __( '%d month', 'woocommerce-correios' ), 7 ),
						'8'     => sprintf( __( '%d month', 'woocommerce-correios' ), 8 ),
						'9'     => sprintf( __( '%d month', 'woocommerce-correios' ), 9 ),
						'10'    => sprintf( __( '%d month', 'woocommerce-correios' ), 10 ),
						'11'    => sprintf( __( '%d month', 'woocommerce-correios' ), 11 ),
						'12'    => sprintf( __( '%d month', 'woocommerce-correios' ), 12 ),
						'forever' => __( 'Forever', 'woocommerce-correios' ),
					),
				),
				'autofill_force' => array(
					'title'       => __( 'Force Autofill', 'woocommerce-correios' ),
					'type'        => 'checkbox',
					'label'       => __( 'Enable Force Autofill', 'woocommerce-correios' ),
					'description' => __( 'When enabled will autofill all addresses after the user finish to fill the postcode, even if the addresses are already filled.', 'woocommerce-correios' ),
					'default'     => 'no',
				),
				'autofill_empty_database' => array(
					'title'       => __( 'Empty Database', 'woocommerce-correios' ),
					'type'        => 'button',
					'label'       => __( 'Empty database', 'woocommerce-correios' ),
					'description' => __( 'Delete all the saved postcodes in the database, use this option if you have issues with outdated postcodes.', 'woocommerce-correios' ),
				),
				'autofill_debug' => array(
					'title'       => __( 'Debug Log', 'woocommerce-correios' ),
					'type'        => 'checkbox',
					'label'       => __( 'Enable logging for Autofill Addresses', 'woocommerce-correios' ),
					'default'     => 'no',
					'description' => sprintf( __( 'Log %s events, such as WebServices requests.', 'woocommerce-correios' ), __( 'Autofill Addresses', 'woocommerce-correios' ) ) . $this->get_tracking_log_link(),
				),
			);
		}
	}

	/**
	 * Correios options page.
	 */
	public function admin_options() {
		echo '<h2>' . esc_html( $this->get_method_title() ) . '</h2>';
		echo wp_kses_post( wpautop( $this->get_method_description() ) );

		include WC_Correios::get_plugin_path() . 'includes/admin/views/html-admin-help-message.php';

		echo '<div><input type="hidden" name="section" value="' . esc_attr( $this->id ) . '" /></div>';
		echo '<table class="form-table">' . $this->generate_settings_html( $this->get_form_fields(), false ) . '</table>';

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		wp_enqueue_script( $this->id . '-admin', plugins_url( 'assets/js/admin/integration' . $suffix . '.js', WC_Correios::get_main_file() ), array( 'jquery', 'jquery-blockui' ), WC_Correios::VERSION, true );
		wp_localize_script(
			$this->id . '-admin',
			'WCCorreiosIntegrationAdminParams',
			array(
				'i18n_confirm_message' => __( 'Are you sure you want to delete all postcodes from the database?', 'woocommerce-correios' ),
				'empty_database_nonce' => wp_create_nonce( 'woocommerce_correios_autofill_addresses_nonce' ),
			)
		);
	}

	/**
	 * Generate Button Input HTML.
	 *
	 * @param string $key
	 * @param array $data
	 * @return string
	 */
	public function generate_button_html( $key, $data ) {
		$field_key = $this->get_field_key( $key );
		$defaults  = array(
			'title'       => '',
			'label'       => '',
			'desc_tip'    => false,
			'description' => '',
		);

		$data = wp_parse_args( $data, $defaults );

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?></label>
				<?php echo $this->get_tooltip_html( $data ); ?>
			</th>
			<td class="forminp">
				<fieldset>
					<legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
					<button class="button-secondary" type="button" id="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['label'] ); ?></button>
					<?php echo $this->get_description_html( $data ); ?>
				</fieldset>
			</td>
		</tr>
		<?php

		return ob_get_clean();
	}

	/**
	 * Enable tracking history.
	 *
	 * @return bool
	 */
	public function setup_tracking_history() {
		return 'yes' === $this->tracking_enable;
	}

	/**
	 * Set up tracking debug.
	 *
	 * @return bool
	 */
	public function setup_tracking_debug() {
		return 'yes' === $this->tracking_debug;
	}

	/**
	 * Check if support autofill.
	 *
	 * @return bool
	 */
	protected function check_if_support_autofill() {
		return defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.5.0', '>=' ) && class_exists( 'SoapClient' );
	}

	/**
	 * Enable autofill addresses.
	 *
	 * @return bool
	 */
	public function setup_autofill_addresses() {
		return 'yes' === $this->autofill_enable && $this->check_if_support_autofill();
	}

	/**
	 * Set up autofill addresses debug.
	 *
	 * @return bool
	 */
	public function setup_autofill_addresses_debug() {
		return 'yes' === $this->autofill_debug;
	}

	/**
	 * Set up autofill addresses expire time.
	 *
	 * @return string
	 */
	public function setup_autofill_addresses_expire_time() {
		return $this->autofill_expire;
	}

	/**
	 * Set up autofill addresses force autofill.
	 *
	 * @return string
	 */
	public function setup_autofill_addresses_force_autofill() {
		return $this->autofill_force;
	}

	/**
	 * Ajax empty database.
	 */
	public function ajax_empty_database() {
		global $wpdb;

		if ( ! isset( $_POST['nonce'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Missing parameters!', 'woocommerce-correios' ) ) );
			exit;
		}

		if ( ! wp_verify_nonce( $_POST['nonce'], 'woocommerce_correios_autofill_addresses_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce!', 'woocommerce-correios' ) ) );
			exit;
		}

		$table_name = $wpdb->prefix . WC_Correios_Autofill_Addresses::$table;
		$wpdb->query( "DROP TABLE IF EXISTS $table_name;" );

		WC_Correios_Autofill_Addresses::create_database();

		wp_send_json_success( array( 'message' => __( 'Postcode database emptied successfully!', 'woocommerce-correios' ) ) );
	}
}
