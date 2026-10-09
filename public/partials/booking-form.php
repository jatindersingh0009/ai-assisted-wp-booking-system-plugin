<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wpbs-booking-form-wrap" id="wpbs-booking-app">

	<!-- Step Indicator -->
	<div class="wpbs-steps">
		<div class="wpbs-step active" data-step="1"><span>1</span><?php esc_html_e( 'Service', 'wp-booking-system' ); ?></div>
		<div class="wpbs-step" data-step="2"><span>2</span><?php esc_html_e( 'Date & Time', 'wp-booking-system' ); ?></div>
		<div class="wpbs-step" data-step="3"><span>3</span><?php esc_html_e( 'Details', 'wp-booking-system' ); ?></div>
		<div class="wpbs-step" data-step="4"><span>4</span><?php esc_html_e( 'Payment', 'wp-booking-system' ); ?></div>
		<div class="wpbs-step" data-step="5"><span>5</span><?php esc_html_e( 'Confirm', 'wp-booking-system' ); ?></div>
	</div>

	<!-- Step 1: Service Selection -->
	<div class="wpbs-step-content active" data-step="1">
		<h2><?php esc_html_e( 'Select a Service', 'wp-booking-system' ); ?></h2>
		<div class="wpbs-service-grid">
		<?php foreach ( $services as $service ) : ?>
			<div class="wpbs-service-card" data-service-id="<?php echo esc_attr( $service['id'] ); ?>" data-duration="<?php echo esc_attr( $service['duration'] ); ?>" data-price="<?php echo esc_attr( $service['price'] ); ?>">
				<?php if ( $service['image_id'] ) : ?>
				<img src="<?php echo esc_url( wp_get_attachment_image_url( $service['image_id'], 'medium' ) ); ?>" alt="<?php echo esc_attr( $service['name'] ); ?>">
				<?php endif; ?>
				<h3><?php echo esc_html( $service['name'] ); ?></h3>
				<?php if ( $service['description'] ) : ?>
				<p><?php echo wp_kses_post( wpautop( $service['description'] ) ); ?></p>
				<?php endif; ?>
				<div class="wpbs-service-meta">
					<span class="wpbs-duration"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> <?php echo esc_html( $service['duration'] ); ?> <?php esc_html_e( 'min', 'wp-booking-system' ); ?></span>
					<span class="wpbs-price"><?php echo esc_html( get_option( 'wpbs_currency_symbol', '$' ) . number_format( (float) $service['price'], 2 ) ); ?></span>
				</div>
				<button class="wpbs-btn wpbs-btn-primary wpbs-select-service"><?php esc_html_e( 'Select', 'wp-booking-system' ); ?></button>
			</div>
		<?php endforeach; ?>
		<?php if ( empty( $services ) ) : ?>
			<p><?php esc_html_e( 'No services available at the moment.', 'wp-booking-system' ); ?></p>
		<?php endif; ?>
		</div>
	</div>

	<!-- Step 2: Date & Time -->
	<div class="wpbs-step-content" data-step="2">
		<h2><?php esc_html_e( 'Choose Date & Time', 'wp-booking-system' ); ?></h2>
		<div class="wpbs-datetime-picker">
			<div class="wpbs-calendar-picker">
				<div class="wpbs-cal-header">
					<button type="button" id="wpbs-prev-month">&laquo;</button>
					<span id="wpbs-month-label"></span>
					<button type="button" id="wpbs-next-month">&raquo;</button>
				</div>
				<div class="wpbs-cal-days-header">
					<?php foreach ( array( 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat' ) as $d ) : ?>
					<div><?php echo esc_html( $d ); ?></div>
					<?php endforeach; ?>
				</div>
				<div id="wpbs-cal-days" class="wpbs-cal-days"></div>
			</div>
			<div class="wpbs-time-picker">
				<h3><?php esc_html_e( 'Available Time Slots', 'wp-booking-system' ); ?></h3>
				<div id="wpbs-time-slots" class="wpbs-time-slots">
					<p class="wpbs-hint"><?php esc_html_e( 'Select a date to see available times.', 'wp-booking-system' ); ?></p>
				</div>
			</div>
		</div>
		<div class="wpbs-step-nav">
			<button type="button" class="wpbs-btn wpbs-btn-back" data-target-step="1"><?php esc_html_e( 'Back', 'wp-booking-system' ); ?></button>
			<button type="button" class="wpbs-btn wpbs-btn-primary" id="wpbs-step2-next" disabled><?php esc_html_e( 'Continue', 'wp-booking-system' ); ?></button>
		</div>
	</div>

	<!-- Step 3: Details -->
	<div class="wpbs-step-content" data-step="3">
		<h2><?php esc_html_e( 'Booking Details', 'wp-booking-system' ); ?></h2>
		<div class="wpbs-booking-summary wpbs-summary-box">
			<h3><?php esc_html_e( 'Your Selection', 'wp-booking-system' ); ?></h3>
			<p><strong><?php esc_html_e( 'Service:', 'wp-booking-system' ); ?></strong> <span id="wpbs-summary-service">—</span></p>
			<p><strong><?php esc_html_e( 'Date:', 'wp-booking-system' ); ?></strong> <span id="wpbs-summary-date">—</span></p>
			<p><strong><?php esc_html_e( 'Time:', 'wp-booking-system' ); ?></strong> <span id="wpbs-summary-time">—</span></p>
			<p><strong><?php esc_html_e( 'Duration:', 'wp-booking-system' ); ?></strong> <span id="wpbs-summary-duration">—</span></p>
			<p class="wpbs-price-line"><strong><?php esc_html_e( 'Total:', 'wp-booking-system' ); ?></strong> <span id="wpbs-summary-price">—</span></p>
		</div>
		<div class="wpbs-form-group">
			<label for="wpbs-notes"><?php esc_html_e( 'Additional Notes (optional)', 'wp-booking-system' ); ?></label>
			<textarea id="wpbs-notes" rows="3" maxlength="500" placeholder="<?php esc_attr_e( 'Any special requests or information...', 'wp-booking-system' ); ?>"></textarea>
		</div>
		<div class="wpbs-step-nav">
			<button type="button" class="wpbs-btn wpbs-btn-back" data-target-step="2"><?php esc_html_e( 'Back', 'wp-booking-system' ); ?></button>
			<button type="button" class="wpbs-btn wpbs-btn-primary" id="wpbs-step3-next"><?php esc_html_e( 'Continue to Payment', 'wp-booking-system' ); ?></button>
		</div>
	</div>

	<!-- Step 4: Payment -->
	<div class="wpbs-step-content" data-step="4">
		<h2><?php esc_html_e( 'Payment', 'wp-booking-system' ); ?></h2>
		<div class="wpbs-payment-methods">
			<h3><?php esc_html_e( 'Select Payment Method', 'wp-booking-system' ); ?></h3>
			<?php if ( get_option( 'wpbs_paypal_client_id' ) ) : ?>
			<label class="wpbs-payment-option">
				<input type="radio" name="payment_method" value="paypal">
				<span class="wpbs-payment-label">
					<svg class="wpbs-paypal-icon" viewBox="0 0 24 24" width="80"><path d="M7.076 21.337H2.47a.641.641 0 0 1-.633-.74L4.944.901C5.026.382 5.474 0 5.998 0h7.46c2.57 0 4.578.543 5.69 1.81 1.01 1.15 1.304 2.42 1.012 4.287-.023.143-.047.288-.077.437-.983 5.05-4.349 6.797-8.647 6.797h-2.19c-.524 0-.968.382-1.05.9l-1.12 7.106zm14.146-14.42a3.35 3.35 0 0 0-.607-.541c-.013.076-.026.175-.041.254-.59 3.025-2.566 6.082-8.558 6.082H9.83c-.524 0-.968.382-1.05.9l-1.456 9.236a.641.641 0 0 0 .633.74h4.39c.524 0 .968-.382 1.05-.9l.476-3.017.078-.49a1.062 1.062 0 0 1 1.05-.9h.662c4.294 0 7.657-1.744 8.642-6.79.41-2.105.198-3.864-.943-5.074z" fill="#009cde"/></svg>
					PayPal
				</span>
			</label>
			<?php endif; ?>
			<?php if ( get_option( 'wpbs_stripe_publishable_key' ) ) : ?>
			<label class="wpbs-payment-option">
				<input type="radio" name="payment_method" value="stripe">
				<span class="wpbs-payment-label">
					<svg class="wpbs-stripe-icon" viewBox="0 0 60 25" width="60"><path d="M59.64 14.28h-8.06c.19 1.93 1.6 2.55 3.2 2.55 1.64 0 2.96-.37 4.05-.95v3.32a8.33 8.33 0 0 1-4.56 1.1c-4.01 0-6.83-2.5-6.83-7.48 0-4.19 2.39-7.52 6.3-7.52 3.92 0 5.96 3.28 5.96 7.5 0 .4-.04 1.26-.06 1.48zm-5.92-5.62c-1.03 0-2.17.73-2.17 2.58h4.25c0-1.85-1.07-2.58-2.08-2.58zM40.95 20.3c-1.44 0-2.32-.6-2.9-1.04l-.02 4.63-4.12.87V6.27h3.76l.08 1.02a4.7 4.7 0 0 1 3.23-1.29c2.9 0 5.62 2.6 5.62 7.4 0 5.23-2.7 7.9-5.65 7.9zM40 9.95c-.95 0-1.54.34-1.97.81l.02 6.12c.4.44.98.78 1.95.78 1.52 0 2.54-1.65 2.54-3.87 0-2.15-1.04-3.84-2.54-3.84zM28.24 5.07v3.49h4.13V5.07h-4.13zm0 3.65h4.13V20h-4.13V8.72zm-2.6 0H22.1l-.3 5.18c-.15 2.53-.34 5.26-.37 5.86l-2.7-11.04h-4.07L12 20h4.13l2.1-11.28h.04L20.6 20h3.85l.62-11.28h.04L26.4 20h4.08L30.8 8.72h-5.16zM7.13 20l.28-1.56C6.9 19 5.78 20.3 4 20.3c-2.61 0-4-2.12-4-5.26 0-3.8 2.14-5.83 4.87-5.83 1.56 0 2.54.7 3.13 1.5l.1-1.23h3.8V20H7.13zm-.11-7.27c-.47-.5-1.1-.82-1.9-.82-1.35 0-2.08 1.04-2.08 2.83 0 1.7.65 2.76 2 2.76.8 0 1.5-.4 2-.96V12.73z" fill="#6772E5"/></svg>
					<?php esc_html_e( 'Credit / Debit Card', 'wp-booking-system' ); ?>
				</span>
			</label>
			<?php endif; ?>
		</div>

		<!-- Stripe Card Element Container -->
		<div id="wpbs-stripe-section" style="display:none">
			<div id="wpbs-card-element" class="wpbs-stripe-card-input"></div>
			<div id="wpbs-card-errors" class="wpbs-error-msg" role="alert"></div>
		</div>

		<div id="wpbs-payment-processing" class="wpbs-loading" style="display:none">
			<span class="wpbs-spinner"></span> <?php esc_html_e( 'Processing payment...', 'wp-booking-system' ); ?>
		</div>

		<div id="wpbs-payment-message" class="wpbs-message" style="display:none"></div>

		<div class="wpbs-step-nav">
			<button type="button" class="wpbs-btn wpbs-btn-back" data-target-step="3"><?php esc_html_e( 'Back', 'wp-booking-system' ); ?></button>
			<button type="button" class="wpbs-btn wpbs-btn-primary" id="wpbs-pay-btn"><?php esc_html_e( 'Pay Now', 'wp-booking-system' ); ?></button>
		</div>
	</div>

	<!-- Step 5: Confirmation -->
	<div class="wpbs-step-content" data-step="5">
		<div class="wpbs-confirmation">
			<div class="wpbs-confirmation-icon">✓</div>
			<h2><?php esc_html_e( 'Booking Confirmed!', 'wp-booking-system' ); ?></h2>
			<p id="wpbs-confirm-message"></p>
			<div class="wpbs-confirm-details" id="wpbs-confirm-details"></div>
			<div class="wpbs-confirm-actions">
				<a href="<?php echo esc_url( get_option( 'wpbs_customer_dashboard' ) ? get_permalink( get_option( 'wpbs_customer_dashboard' ) ) : home_url() ); ?>" class="wpbs-btn wpbs-btn-primary"><?php esc_html_e( 'View My Bookings', 'wp-booking-system' ); ?></a>
			</div>
		</div>
	</div>

</div>
