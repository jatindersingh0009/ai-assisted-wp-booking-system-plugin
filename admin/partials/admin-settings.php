<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap wpbs-admin">
	<h1><?php esc_html_e( 'Settings', 'wp-booking-system' ); ?></h1>
	<?php settings_errors( 'wpbs_settings' ); ?>

	<nav class="nav-tab-wrapper">
		<?php
		$tabs = array(
			'general' => __( 'General', 'wp-booking-system' ),
			'hours'   => __( 'Business Hours', 'wp-booking-system' ),
			'booking' => __( 'Booking Rules', 'wp-booking-system' ),
			'paypal'  => __( 'PayPal', 'wp-booking-system' ),
			'stripe'  => __( 'Stripe', 'wp-booking-system' ),
		);
		foreach ( $tabs as $slug => $label ) :
			$url = admin_url( 'admin.php?page=wpbs-settings&tab=' . $slug );
		?>
		<a href="<?php echo esc_url( $url ); ?>" class="nav-tab<?php echo $active_tab === $slug ? ' nav-tab-active' : ''; ?>"><?php echo esc_html( $label ); ?></a>
		<?php endforeach; ?>
	</nav>

	<form method="post" class="wpbs-settings-form">
		<?php wp_nonce_field( 'wpbs_settings_save', 'wpbs_settings_nonce' ); ?>
		<input type="hidden" name="tab" value="<?php echo esc_attr( $active_tab ); ?>">

		<?php if ( 'general' === $active_tab ) : ?>
		<table class="form-table">
			<tr><th><?php esc_html_e( 'Timezone', 'wp-booking-system' ); ?></th><td>
				<select name="timezone">
				<?php foreach ( timezone_identifiers_list() as $tz ) : ?>
					<option value="<?php echo esc_attr( $tz ); ?>" <?php selected( get_option( 'wpbs_timezone', 'UTC' ), $tz ); ?>><?php echo esc_html( $tz ); ?></option>
				<?php endforeach; ?>
				</select>
			</td></tr>
			<tr><th><?php esc_html_e( 'Currency Code', 'wp-booking-system' ); ?></th><td><input type="text" name="currency" value="<?php echo esc_attr( get_option( 'wpbs_currency', 'USD' ) ); ?>" maxlength="3"></td></tr>
			<tr><th><?php esc_html_e( 'Currency Symbol', 'wp-booking-system' ); ?></th><td><input type="text" name="currency_symbol" value="<?php echo esc_attr( get_option( 'wpbs_currency_symbol', '$' ) ); ?>" maxlength="5"></td></tr>
			<tr><th><?php esc_html_e( 'Admin Email', 'wp-booking-system' ); ?></th><td><input type="email" name="admin_email" value="<?php echo esc_attr( get_option( 'wpbs_admin_email', get_option( 'admin_email' ) ) ); ?>" class="regular-text"></td></tr>
			<tr><th><?php esc_html_e( 'From Name', 'wp-booking-system' ); ?></th><td><input type="text" name="from_name" value="<?php echo esc_attr( get_option( 'wpbs_from_name', get_bloginfo( 'name' ) ) ); ?>" class="regular-text"></td></tr>
			<tr><th><?php esc_html_e( 'From Email', 'wp-booking-system' ); ?></th><td><input type="email" name="from_email" value="<?php echo esc_attr( get_option( 'wpbs_from_email', get_option( 'admin_email' ) ) ); ?>" class="regular-text"></td></tr>
		</table>

		<?php elseif ( 'hours' === $active_tab ) :
			$hours = get_option( 'wpbs_business_hours', array() );
			$days  = array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' );
		?>
		<table class="form-table">
			<thead><tr><th><?php esc_html_e( 'Day', 'wp-booking-system' ); ?></th><th><?php esc_html_e( 'Open', 'wp-booking-system' ); ?></th><th><?php esc_html_e( 'Close', 'wp-booking-system' ); ?></th><th><?php esc_html_e( 'Enabled', 'wp-booking-system' ); ?></th></tr></thead>
			<tbody>
			<?php foreach ( $days as $day ) :
				$dh = $hours[ $day ] ?? array( 'enabled' => false, 'open' => '09:00', 'close' => '17:00' );
			?>
			<tr>
				<th><?php echo esc_html( ucfirst( $day ) ); ?></th>
				<td><input type="time" name="hours_<?php echo esc_attr( $day ); ?>_open" value="<?php echo esc_attr( $dh['open'] ); ?>"></td>
				<td><input type="time" name="hours_<?php echo esc_attr( $day ); ?>_close" value="<?php echo esc_attr( $dh['close'] ); ?>"></td>
				<td><input type="checkbox" name="hours_<?php echo esc_attr( $day ); ?>_enabled" <?php checked( $dh['enabled'] ); ?>></td>
			</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<?php elseif ( 'booking' === $active_tab ) :
			$rules = get_option( 'wpbs_booking_rules', array() );
		?>
		<table class="form-table">
			<tr><th><?php esc_html_e( 'Minimum Advance (hours)', 'wp-booking-system' ); ?></th><td><input type="number" name="min_advance_hours" min="0" value="<?php echo esc_attr( $rules['min_advance_hours'] ?? 2 ); ?>"><p class="description"><?php esc_html_e( 'How many hours in advance a booking must be made.', 'wp-booking-system' ); ?></p></td></tr>
			<tr><th><?php esc_html_e( 'Maximum Advance (days)', 'wp-booking-system' ); ?></th><td><input type="number" name="max_advance_days" min="1" value="<?php echo esc_attr( $rules['max_advance_days'] ?? 60 ); ?>"></td></tr>
			<tr><th><?php esc_html_e( 'Cancellation Window (hours)', 'wp-booking-system' ); ?></th><td><input type="number" name="cancellation_hours" min="0" value="<?php echo esc_attr( $rules['cancellation_hours'] ?? 24 ); ?>"><p class="description"><?php esc_html_e( 'Minimum hours before appointment that customers can cancel.', 'wp-booking-system' ); ?></p></td></tr>
			<tr><th><?php esc_html_e( 'Slot Interval (minutes)', 'wp-booking-system' ); ?></th><td><input type="number" name="slot_interval_minutes" min="5" step="5" value="<?php echo esc_attr( $rules['slot_interval_minutes'] ?? 30 ); ?>"></td></tr>
		</table>

		<?php elseif ( 'paypal' === $active_tab ) : ?>
		<table class="form-table">
			<tr><th><?php esc_html_e( 'Mode', 'wp-booking-system' ); ?></th><td>
				<select name="paypal_mode">
					<option value="sandbox" <?php selected( get_option( 'wpbs_paypal_mode', 'sandbox' ), 'sandbox' ); ?>><?php esc_html_e( 'Sandbox (Testing)', 'wp-booking-system' ); ?></option>
					<option value="live" <?php selected( get_option( 'wpbs_paypal_mode', 'sandbox' ), 'live' ); ?>><?php esc_html_e( 'Live', 'wp-booking-system' ); ?></option>
				</select>
			</td></tr>
			<tr><th><?php esc_html_e( 'Client ID', 'wp-booking-system' ); ?></th><td><input type="text" name="paypal_client_id" value="<?php echo esc_attr( get_option( 'wpbs_paypal_client_id', '' ) ); ?>" class="large-text"></td></tr>
			<tr><th><?php esc_html_e( 'Secret Key', 'wp-booking-system' ); ?></th><td><input type="password" name="paypal_secret" value="<?php echo esc_attr( get_option( 'wpbs_paypal_secret', '' ) ); ?>" class="large-text" autocomplete="new-password"></td></tr>
			<tr><th><?php esc_html_e( 'Webhook ID', 'wp-booking-system' ); ?></th><td><input type="text" name="paypal_webhook_id" value="<?php echo esc_attr( get_option( 'wpbs_paypal_webhook_id', '' ) ); ?>" class="large-text">
				<p class="description"><?php printf( esc_html__( 'Webhook URL: %s', 'wp-booking-system' ), '<code>' . esc_html( home_url( '/wpbs-webhook/paypal/' ) ) . '</code>' ); ?></p>
			</td></tr>
		</table>

		<?php elseif ( 'stripe' === $active_tab ) : ?>
		<table class="form-table">
			<tr><th><?php esc_html_e( 'Mode', 'wp-booking-system' ); ?></th><td>
				<select name="stripe_mode">
					<option value="test" <?php selected( get_option( 'wpbs_stripe_mode', 'test' ), 'test' ); ?>><?php esc_html_e( 'Test', 'wp-booking-system' ); ?></option>
					<option value="live" <?php selected( get_option( 'wpbs_stripe_mode', 'test' ), 'live' ); ?>><?php esc_html_e( 'Live', 'wp-booking-system' ); ?></option>
				</select>
			</td></tr>
			<tr><th><?php esc_html_e( 'Publishable Key', 'wp-booking-system' ); ?></th><td><input type="text" name="stripe_publishable_key" value="<?php echo esc_attr( get_option( 'wpbs_stripe_publishable_key', '' ) ); ?>" class="large-text"></td></tr>
			<tr><th><?php esc_html_e( 'Secret Key', 'wp-booking-system' ); ?></th><td><input type="password" name="stripe_secret_key" value="<?php echo esc_attr( get_option( 'wpbs_stripe_secret_key', '' ) ); ?>" class="large-text" autocomplete="new-password"></td></tr>
			<tr><th><?php esc_html_e( 'Webhook Secret', 'wp-booking-system' ); ?></th><td><input type="password" name="stripe_webhook_secret" value="<?php echo esc_attr( get_option( 'wpbs_stripe_webhook_secret', '' ) ); ?>" class="large-text" autocomplete="new-password">
				<p class="description"><?php printf( esc_html__( 'Webhook URL: %s', 'wp-booking-system' ), '<code>' . esc_html( home_url( '/wpbs-webhook/stripe/' ) ) . '</code>' ); ?></p>
			</td></tr>
		</table>
		<?php endif; ?>

		<p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'wp-booking-system' ); ?></button></p>
	</form>
</div>
