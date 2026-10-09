<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap wpbs-admin">

<?php if ( 'list' === $action ) : ?>
	<h1>
		<?php esc_html_e( 'Services', 'wp-booking-system' ); ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpbs-services&action=new' ) ); ?>" class="page-title-action"><?php esc_html_e( '+ Add New', 'wp-booking-system' ); ?></a>
	</h1>
	<table class="wpbs-table widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Name', 'wp-booking-system' ); ?></th>
				<th><?php esc_html_e( 'Duration', 'wp-booking-system' ); ?></th>
				<th><?php esc_html_e( 'Capacity', 'wp-booking-system' ); ?></th>
				<th><?php esc_html_e( 'Price', 'wp-booking-system' ); ?></th>
				<th><?php esc_html_e( 'Status', 'wp-booking-system' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'wp-booking-system' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php if ( empty( $services ) ) : ?>
			<tr><td colspan="6"><?php esc_html_e( 'No services found. Add your first service.', 'wp-booking-system' ); ?></td></tr>
		<?php else : ?>
			<?php foreach ( $services as $svc ) : ?>
			<tr>
				<td><strong><?php echo esc_html( $svc['name'] ); ?></strong><br><small><?php echo esc_html( wp_trim_words( $svc['description'] ?? '', 10 ) ); ?></small></td>
				<td><?php echo esc_html( $svc['duration'] . ' ' . __( 'min', 'wp-booking-system' ) ); ?></td>
				<td><?php echo esc_html( $svc['capacity'] ); ?></td>
				<td><?php echo esc_html( get_option( 'wpbs_currency_symbol', '$' ) . number_format( (float) $svc['price'], 2 ) ); ?></td>
				<td><span class="wpbs-badge wpbs-badge-<?php echo $svc['is_active'] ? 'confirmed' : 'cancelled'; ?>"><?php echo $svc['is_active'] ? esc_html__( 'Active', 'wp-booking-system' ) : esc_html__( 'Inactive', 'wp-booking-system' ); ?></span></td>
				<td>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpbs-services&action=edit&id=' . $svc['id'] ) ); ?>" class="button button-small"><?php esc_html_e( 'Edit', 'wp-booking-system' ); ?></a>
					<form method="post" style="display:inline" onsubmit="return confirm('<?php esc_attr_e( 'Delete this service?', 'wp-booking-system' ); ?>')">
						<?php wp_nonce_field( 'wpbs_service_action', 'wpbs_service_nonce' ); ?>
						<input type="hidden" name="service_action" value="delete">
						<input type="hidden" name="service_id" value="<?php echo esc_attr( $svc['id'] ); ?>">
						<button type="submit" class="button button-small button-link-delete"><?php esc_html_e( 'Delete', 'wp-booking-system' ); ?></button>
					</form>
				</td>
			</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		</tbody>
	</table>

<?php else : ?>
	<!-- Add / Edit Service Form -->
	<h1><?php $service ? esc_html_e( 'Edit Service', 'wp-booking-system' ) : esc_html_e( 'Add New Service', 'wp-booking-system' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpbs-services' ) ); ?>">&larr; <?php esc_html_e( 'Back to Services', 'wp-booking-system' ); ?></a>

	<form method="post" class="wpbs-service-form">
		<?php wp_nonce_field( 'wpbs_service_action', 'wpbs_service_nonce' ); ?>
		<input type="hidden" name="service_action" value="save">
		<input type="hidden" name="service_id" value="<?php echo esc_attr( $service['id'] ?? 0 ); ?>">

		<table class="form-table">
			<tr>
				<th><label for="name"><?php esc_html_e( 'Service Name *', 'wp-booking-system' ); ?></label></th>
				<td><input type="text" id="name" name="name" class="regular-text" required value="<?php echo esc_attr( $service['name'] ?? '' ); ?>"></td>
			</tr>
			<tr>
				<th><label for="description"><?php esc_html_e( 'Description', 'wp-booking-system' ); ?></label></th>
				<td><textarea id="description" name="description" class="large-text" rows="4"><?php echo esc_textarea( $service['description'] ?? '' ); ?></textarea></td>
			</tr>
			<tr>
				<th><label for="duration"><?php esc_html_e( 'Duration (minutes) *', 'wp-booking-system' ); ?></label></th>
				<td><input type="number" id="duration" name="duration" min="5" step="5" required value="<?php echo esc_attr( $service['duration'] ?? 60 ); ?>"></td>
			</tr>
			<tr>
				<th><label for="capacity"><?php esc_html_e( 'Max Capacity', 'wp-booking-system' ); ?></label></th>
				<td><input type="number" id="capacity" name="capacity" min="1" value="<?php echo esc_attr( $service['capacity'] ?? 1 ); ?>"><p class="description"><?php esc_html_e( 'Max bookings per time slot.', 'wp-booking-system' ); ?></p></td>
			</tr>
			<tr>
				<th><label for="price"><?php esc_html_e( 'Price', 'wp-booking-system' ); ?></label></th>
				<td><input type="number" id="price" name="price" min="0" step="0.01" value="<?php echo esc_attr( $service['price'] ?? '0.00' ); ?>"></td>
			</tr>
			<tr>
				<th><label for="category"><?php esc_html_e( 'Category', 'wp-booking-system' ); ?></label></th>
				<td><input type="text" id="category" name="category" class="regular-text" value="<?php echo esc_attr( $service['category'] ?? '' ); ?>"></td>
			</tr>
			<tr>
				<th><label for="sort_order"><?php esc_html_e( 'Sort Order', 'wp-booking-system' ); ?></label></th>
				<td><input type="number" id="sort_order" name="sort_order" min="0" value="<?php echo esc_attr( $service['sort_order'] ?? 0 ); ?>"></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Status', 'wp-booking-system' ); ?></th>
				<td>
					<label><input type="radio" name="is_active" value="1" <?php checked( $service['is_active'] ?? 1, 1 ); ?>> <?php esc_html_e( 'Active', 'wp-booking-system' ); ?></label>
					<label><input type="radio" name="is_active" value="0" <?php checked( $service['is_active'] ?? 1, 0 ); ?>> <?php esc_html_e( 'Inactive', 'wp-booking-system' ); ?></label>
				</td>
			</tr>
		</table>
		<p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Save Service', 'wp-booking-system' ); ?></button></p>
	</form>
<?php endif; ?>
</div>
