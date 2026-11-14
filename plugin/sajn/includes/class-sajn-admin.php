<?php
/**
 * Admin screens: connections, settings, and log.
 *
 * @package sajn
 */

defined( 'ABSPATH' ) || exit;

final class Sajn_Admin {

	const CAPABILITY = 'manage_options';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_sajn_save_settings', array( __CLASS__, 'save_settings' ) );
		add_action( 'admin_post_sajn_save_connection', array( __CLASS__, 'save_connection' ) );
		add_action( 'admin_post_sajn_delete_connection', array( __CLASS__, 'delete_connection' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( SAJN_PLUGIN_FILE ), array( __CLASS__, 'action_links' ) );
	}

	public static function menu() {
		add_menu_page( 'sajn', 'sajn', self::CAPABILITY, 'sajn', array( __CLASS__, 'render_connections' ), 'dashicons-edit-page', 81 );
		add_submenu_page( 'sajn', __( 'Connections', 'sajn' ), __( 'Connections', 'sajn' ), self::CAPABILITY, 'sajn', array( __CLASS__, 'render_connections' ) );
		add_submenu_page( 'sajn', __( 'Settings', 'sajn' ), __( 'Settings', 'sajn' ), self::CAPABILITY, 'sajn-settings', array( __CLASS__, 'render_settings' ) );
		add_submenu_page( 'sajn', __( 'Log', 'sajn' ), __( 'Log', 'sajn' ), self::CAPABILITY, 'sajn-log', array( __CLASS__, 'render_log' ) );
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=sajn-settings' ) ) . '">' . esc_html__( 'Settings', 'sajn' ) . '</a>' );
		return $links;
	}

	/* Settings */

	public static function render_settings() {
		$has_key = Sajn_Api::has_api_key();
		$me      = $has_key ? Sajn_Api::me() : null;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'sajn settings', 'sajn' ); ?></h1>
			<?php self::notice(); ?>

			<?php if ( is_wp_error( $me ) ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( sprintf( /* translators: %s: error message */ __( 'sajn rejected the API key: %s', 'sajn' ), $me->get_error_message() ) ); ?></p></div>
			<?php elseif ( is_array( $me ) ) : ?>
				<div class="notice notice-success inline"><p>
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: workspace name, 2: organization name, 3: user email */
							__( 'Connected to the workspace %1$s in %2$s as %3$s.', 'sajn' ),
							isset( $me['workspace']['name'] ) ? $me['workspace']['name'] : '',
							isset( $me['organization']['name'] ) ? $me['organization']['name'] : '',
							isset( $me['email'] ) ? $me['email'] : ''
						)
					);
					?>
				</p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="sajn_save_settings">
				<?php wp_nonce_field( 'sajn_save_settings' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="sajn-api-key"><?php esc_html_e( 'API key', 'sajn' ); ?></label></th>
						<td>
							<input type="password" id="sajn-api-key" name="api_key" class="regular-text" autocomplete="off" placeholder="<?php echo $has_key ? esc_attr__( 'Saved. Enter a new key to replace it.', 'sajn' ) : 'sajn_…'; ?>">
							<p class="description"><?php esc_html_e( 'In sajn, go to Settings → Organization → Developer → API keys and create a key. The plugin creates documents in the key\'s workspace. The public API is included from the Team plan.', 'sajn' ); ?></p>
							<?php if ( $has_key ) : ?>
								<p><label><input type="checkbox" name="remove_api_key" value="1"> <?php esc_html_e( 'Remove the saved key', 'sajn' ); ?></label></p>
							<?php endif; ?>
						</td>
					</tr>
					<?php if ( 'https://app.sajn.se' !== Sajn_Api::base_url() ) : ?>
						<tr>
							<th scope="row"><?php esc_html_e( 'sajn address', 'sajn' ); ?></th>
							<td><code><?php echo esc_html( Sajn_Api::base_url() ); ?></code> <span class="description"><?php esc_html_e( 'Set by SAJN_BASE_URL in wp-config.php.', 'sajn' ); ?></span></td>
						</tr>
					<?php endif; ?>
				</table>
				<?php submit_button( __( 'Save settings', 'sajn' ) ); ?>
			</form>
		</div>
		<?php
	}

	public static function save_settings() {
		self::authorize( 'sajn_save_settings' );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Checked in authorize().
		$settings = get_option( Sajn_Api::SETTINGS_KEY, array() );
		$api_key  = isset( $_POST['api_key'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) ) : '';

		if ( ! empty( $_POST['remove_api_key'] ) ) {
			$settings['api_key'] = '';
		} elseif ( '' !== $api_key ) {
			$settings['api_key'] = $api_key;
		}
		// phpcs:enable

		update_option( Sajn_Api::SETTINGS_KEY, $settings, false );
		self::redirect( 'admin.php?page=sajn-settings', 'settings_saved' );
	}

	/* Connections */

	public static function render_connections() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen switch.
		$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : '';
		if ( 'edit' === $action ) {
			self::render_connection_edit();
			return;
		}

		$connections = Sajn_Connections::all();
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'sajn connections', 'sajn' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=sajn&action=edit' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add connection', 'sajn' ); ?></a>
			<hr class="wp-header-end">
			<?php self::notice(); ?>
			<?php self::api_key_warning(); ?>
			<p><?php esc_html_e( 'A connection sends a document from a sajn template for signing each time someone submits a form.', 'sajn' ); ?></p>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'sajn' ); ?></th>
						<th><?php esc_html_e( 'Form', 'sajn' ); ?></th>
						<th><?php esc_html_e( 'Template', 'sajn' ); ?></th>
						<th><?php esc_html_e( 'Status', 'sajn' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! $connections ) : ?>
						<tr><td colspan="4"><?php esc_html_e( 'No connections yet.', 'sajn' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $connections as $connection ) : ?>
						<?php
						$edit_url   = admin_url( 'admin.php?page=sajn&action=edit&id=' . rawurlencode( $connection['id'] ) );
						$delete_url = wp_nonce_url( admin_url( 'admin-post.php?action=sajn_delete_connection&id=' . rawurlencode( $connection['id'] ) ), 'sajn_delete_connection_' . $connection['id'] );
						?>
						<tr>
							<td>
								<strong><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( '' !== $connection['name'] ? $connection['name'] : __( '(no name)', 'sajn' ) ); ?></a></strong>
								<div class="row-actions">
									<span class="edit"><a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'sajn' ); ?></a> | </span>
									<span class="trash"><a href="<?php echo esc_url( $delete_url ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this connection?', 'sajn' ) ); ?>');"><?php esc_html_e( 'Delete', 'sajn' ); ?></a></span>
								</div>
							</td>
							<td><?php echo esc_html( self::form_label( $connection['source'], $connection['form_id'] ) ); ?></td>
							<td><?php echo esc_html( $connection['template_name'] ); ?></td>
							<td><?php echo $connection['active'] ? esc_html__( 'Active', 'sajn' ) : esc_html__( 'Paused', 'sajn' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	private static function render_connection_edit() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only lookup.
		$id         = isset( $_GET['id'] ) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : '';
		$saved      = '' !== $id ? Sajn_Connections::get( $id ) : null;
		$connection = $saved ? array_merge( Sajn_Connections::defaults(), $saved ) : Sajn_Connections::defaults();
		$templates  = Sajn_Api::has_api_key() ? Sajn_Api::templates() : array();
		$source     = Sajn_Sources::get( $connection['source'] );
		$ready      = $saved && $source && '' !== $connection['template_id'];
		?>
		<div class="wrap">
			<h1><?php echo $saved ? esc_html__( 'Edit connection', 'sajn' ) : esc_html__( 'Add connection', 'sajn' ); ?></h1>
			<?php self::notice(); ?>
			<?php self::api_key_warning(); ?>
			<?php if ( is_wp_error( $templates ) ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( sprintf( /* translators: %s: error message */ __( 'Could not load templates from sajn: %s', 'sajn' ), $templates->get_error_message() ) ); ?></p></div>
				<?php $templates = array(); ?>
			<?php endif; ?>
			<?php if ( ! Sajn_Sources::all() ) : ?>
				<div class="notice notice-warning"><p><?php esc_html_e( 'Install and activate Contact Form 7, WPForms, or Gravity Forms to connect a form.', 'sajn' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="sajn_save_connection">
				<input type="hidden" name="id" value="<?php echo esc_attr( $connection['id'] ); ?>">
				<?php wp_nonce_field( 'sajn_save_connection' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="sajn-name"><?php esc_html_e( 'Name', 'sajn' ); ?></label></th>
						<td><input type="text" id="sajn-name" name="name" class="regular-text" value="<?php echo esc_attr( $connection['name'] ); ?>" required></td>
					</tr>
					<tr>
						<th scope="row"><label for="sajn-form"><?php esc_html_e( 'Form', 'sajn' ); ?></label></th>
						<td>
							<select id="sajn-form" name="form" required>
								<option value=""><?php esc_html_e( 'Select a form', 'sajn' ); ?></option>
								<?php foreach ( Sajn_Sources::all() as $available ) : ?>
									<optgroup label="<?php echo esc_attr( $available->label() ); ?>">
										<?php foreach ( $available->forms() as $form_id => $title ) : ?>
											<?php $value = $available->id() . ':' . $form_id; ?>
											<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $connection['source'] . ':' . $connection['form_id'], $value ); ?>><?php echo esc_html( $title ); ?></option>
										<?php endforeach; ?>
									</optgroup>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sajn-template"><?php esc_html_e( 'sajn template', 'sajn' ); ?></label></th>
						<td>
							<select id="sajn-template" name="template_id" required>
								<option value=""><?php esc_html_e( 'Select a template', 'sajn' ); ?></option>
								<?php foreach ( $templates as $template ) : ?>
									<option value="<?php echo esc_attr( $template['id'] ); ?>" <?php selected( $connection['template_id'], $template['id'] ); ?>><?php echo esc_html( $template['name'] ); ?></option>
								<?php endforeach; ?>
							</select>
							<?php if ( ! $ready ) : ?>
								<p class="description"><?php esc_html_e( 'Save to choose how the form fills in the template.', 'sajn' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
				</table>

				<?php
				if ( $ready ) {
					self::render_mapping( $connection, $source );
				}
				submit_button( $saved ? __( 'Save connection', 'sajn' ) : __( 'Continue', 'sajn' ) );
				?>
			</form>
		</div>
		<?php
	}

	private static function render_mapping( array $connection, Sajn_Source $source ) {
		$parties = Sajn_Api::template_parties( $connection['template_id'] );
		$keys    = Sajn_Api::template_keys( $connection['template_id'] );
		$methods = Sajn_Api::signature_methods();
		$fields  = $source->fields( $connection['form_id'] );

		foreach ( array( $parties, $keys, $methods ) as $result ) {
			if ( is_wp_error( $result ) ) {
				echo '<div class="notice notice-error inline"><p>' . esc_html( sprintf( /* translators: %s: error message */ __( 'Could not load the template from sajn: %s', 'sajn' ), $result->get_error_message() ) ) . '</p></div>';
				return;
			}
		}

		$deliveries = array(
			'EMAIL'     => __( 'Email', 'sajn' ),
			'SMS'       => __( 'SMS', 'sajn' ),
			'EMAIL_SMS' => __( 'Email and SMS', 'sajn' ),
			'NONE'      => __( 'No invitation', 'sajn' ),
		);
		?>
		<h2><?php esc_html_e( 'Form fields', 'sajn' ); ?></h2>
		<p><?php esc_html_e( 'Use a form field in any box below by writing its tag. You can mix tags and text, such as "Agreement for {your-name}".', 'sajn' ); ?></p>
		<table class="widefat striped" style="max-width:640px">
			<thead><tr><th><?php esc_html_e( 'Tag', 'sajn' ); ?></th><th><?php esc_html_e( 'Field', 'sajn' ); ?></th></tr></thead>
			<tbody>
				<?php foreach ( $fields as $tag => $label ) : ?>
					<tr><td><code>{<?php echo esc_html( $tag ); ?>}</code></td><td><?php echo esc_html( $label ); ?></td></tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'Document', 'sajn' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="sajn-document-name"><?php esc_html_e( 'Document name', 'sajn' ); ?></label></th>
				<td>
					<input type="text" id="sajn-document-name" name="document_name" class="regular-text" value="<?php echo esc_attr( $connection['document_name'] ); ?>" placeholder="<?php echo esc_attr( $connection['template_name'] ); ?>">
					<p class="description"><?php esc_html_e( 'Leave empty to use the template name.', 'sajn' ); ?></p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Parties', 'sajn' ); ?></h2>
		<?php if ( ! $parties ) : ?>
			<p><?php esc_html_e( 'The template has no parties. Add them to the template in sajn.', 'sajn' ); ?></p>
		<?php endif; ?>
		<p><?php esc_html_e( 'A party you leave empty keeps the details saved in the template.', 'sajn' ); ?></p>
		<?php foreach ( $parties as $party ) : ?>
			<?php
			$party_id = $party['id'];
			$mapped   = isset( $connection['parties'][ $party_id ] ) ? $connection['parties'][ $party_id ] : array();
			$mapped   = array_merge(
				array(
					'name'             => '',
					'email'            => '',
					'phone'            => '',
					'delivery'         => 'EMAIL',
					'signature_method' => '',
				),
				$mapped
			);
			$prefix   = 'parties[' . $party_id . ']';
			?>
			<h3><?php echo esc_html( $party['name'] . ' (' . self::role_label( $party['role'] ) . ')' ); ?></h3>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Name', 'sajn' ); ?></th>
					<td><input type="text" name="<?php echo esc_attr( $prefix ); ?>[name]" class="regular-text" value="<?php echo esc_attr( $mapped['name'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Email', 'sajn' ); ?></th>
					<td><input type="text" name="<?php echo esc_attr( $prefix ); ?>[email]" class="regular-text" value="<?php echo esc_attr( $mapped['email'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Phone', 'sajn' ); ?></th>
					<td><input type="text" name="<?php echo esc_attr( $prefix ); ?>[phone]" class="regular-text" value="<?php echo esc_attr( $mapped['phone'] ); ?>">
						<p class="description"><?php esc_html_e( 'Needed for SMS, with country code, such as +46701234567.', 'sajn' ); ?></p></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Invitation', 'sajn' ); ?></th>
					<td>
						<select name="<?php echo esc_attr( $prefix ); ?>[delivery]">
							<?php foreach ( $deliveries as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $mapped['delivery'], $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Signature method', 'sajn' ); ?></th>
					<td>
						<select name="<?php echo esc_attr( $prefix ); ?>[signature_method]">
							<option value=""><?php esc_html_e( 'As in the template', 'sajn' ); ?></option>
							<?php foreach ( $methods as $method ) : ?>
								<option value="<?php echo esc_attr( $method['code'] ); ?>" <?php selected( $mapped['signature_method'], $method['code'] ); ?>><?php echo esc_html( $method['label'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</table>
		<?php endforeach; ?>

		<h2><?php esc_html_e( 'Fields to fill in', 'sajn' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php foreach ( $keys as $key ) : ?>
				<tr>
					<th scope="row"><?php echo esc_html( $key['label'] ); ?><br><code><?php echo esc_html( $key['key'] ); ?></code></th>
					<td><input type="text" name="values[<?php echo esc_attr( $key['key'] ); ?>]" class="regular-text" value="<?php echo esc_attr( isset( $connection['values'][ $key['key'] ] ) ? $connection['values'][ $key['key'] ] : '' ); ?>"></td>
				</tr>
			<?php endforeach; ?>
			<tr>
				<th scope="row"><label for="sajn-extra-values"><?php esc_html_e( 'Other keys', 'sajn' ); ?></label></th>
				<td>
					<textarea id="sajn-extra-values" name="extra_values" rows="3" class="large-text code"><?php echo esc_textarea( $connection['extra_values'] ); ?></textarea>
					<p class="description"><?php esc_html_e( 'One key per line, such as "org-number = {org-nr}". Use this for keys the list above doesn\'t show, such as form fields in an uploaded PDF.', 'sajn' ); ?></p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'After the submission', 'sajn' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="sajn-redirect"><?php esc_html_e( 'Open signing', 'sajn' ); ?></label></th>
				<td>
					<select id="sajn-redirect" name="redirect_party">
						<option value=""><?php esc_html_e( 'No, show the form\'s own confirmation', 'sajn' ); ?></option>
						<?php foreach ( $parties as $party ) : ?>
							<option value="<?php echo esc_attr( $party['id'] ); ?>" <?php selected( $connection['redirect_party'], $party['id'] ); ?>><?php echo esc_html( sprintf( /* translators: %s: party name */ __( 'Yes, for %s', 'sajn' ), $party['name'] ) ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Sends the visitor straight to the document to sign it. Choose the party who fills in the form.', 'sajn' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Status', 'sajn' ); ?></th>
				<td><label><input type="checkbox" name="active" value="1" <?php checked( $connection['active'] ); ?>> <?php esc_html_e( 'Send a document on every submission', 'sajn' ); ?></label></td>
			</tr>
		</table>
		<?php
	}

	public static function save_connection() {
		self::authorize( 'sajn_save_connection' );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Checked in authorize().
		$id       = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		$existing = Sajn_Connections::get( $id );
		$form     = isset( $_POST['form'] ) ? explode( ':', sanitize_text_field( wp_unslash( $_POST['form'] ) ), 2 ) : array();

		$connection                = array_merge( Sajn_Connections::defaults(), $existing ? $existing : array(), array( 'id' => '' !== $id ? $id : Sajn_Connections::defaults()['id'] ) );
		$connection['name']        = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$connection['source']      = isset( $form[0] ) ? sanitize_key( $form[0] ) : '';
		$connection['form_id']     = isset( $form[1] ) ? absint( $form[1] ) : 0;
		$template_id               = isset( $_POST['template_id'] ) ? sanitize_text_field( wp_unslash( $_POST['template_id'] ) ) : '';
		$template_changed          = $template_id !== $connection['template_id'];
		$connection['template_id'] = $template_id;

		$templates = Sajn_Api::templates();
		if ( ! is_wp_error( $templates ) ) {
			foreach ( $templates as $template ) {
				if ( $template['id'] === $template_id ) {
					$connection['template_name'] = $template['name'];
				}
			}
		}

		if ( $template_changed ) {
			// The mapping belonged to the previous template, and an unmapped template must not send.
			$connection['active']         = false;
			$connection['parties']        = array();
			$connection['values']         = array();
			$connection['extra_values']   = '';
			$connection['redirect_party'] = '';
		} elseif ( isset( $_POST['document_name'] ) ) {
			$connection['document_name']  = sanitize_text_field( wp_unslash( $_POST['document_name'] ) );
			$connection['parties']        = self::sanitize_parties( isset( $_POST['parties'] ) ? wp_unslash( $_POST['parties'] ) : array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized per key.
			$connection['values']         = array_map( 'sanitize_text_field', isset( $_POST['values'] ) ? (array) wp_unslash( $_POST['values'] ) : array() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$connection['extra_values']   = isset( $_POST['extra_values'] ) ? sanitize_textarea_field( wp_unslash( $_POST['extra_values'] ) ) : '';
			$connection['redirect_party'] = isset( $_POST['redirect_party'] ) ? sanitize_text_field( wp_unslash( $_POST['redirect_party'] ) ) : '';
			$connection['active']         = ! empty( $_POST['active'] );
		}
		// phpcs:enable

		Sajn_Connections::save( $connection );
		self::redirect( 'admin.php?page=sajn&action=edit&id=' . rawurlencode( $connection['id'] ), 'connection_saved' );
	}

	public static function delete_connection() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The id names the nonce that authorize() checks.
		$id = isset( $_GET['id'] ) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : '';
		self::authorize( 'sajn_delete_connection_' . $id );

		Sajn_Connections::delete( $id );
		self::redirect( 'admin.php?page=sajn', 'connection_deleted' );
	}

	/* Log */

	public static function render_log() {
		$me             = Sajn_Api::has_api_key() ? Sajn_Api::cached_get( '/me' ) : null;
		$workspace_slug = is_array( $me ) && isset( $me['workspace']['slug'] ) ? $me['workspace']['slug'] : '';
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'sajn log', 'sajn' ); ?></h1>
			<p><?php esc_html_e( 'The last 50 submissions that a connection handled.', 'sajn' ); ?></p>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th style="width:160px"><?php esc_html_e( 'Time', 'sajn' ); ?></th>
						<th><?php esc_html_e( 'Connection', 'sajn' ); ?></th>
						<th style="width:90px"><?php esc_html_e( 'Result', 'sajn' ); ?></th>
						<th><?php esc_html_e( 'Message', 'sajn' ); ?></th>
						<th><?php esc_html_e( 'Document', 'sajn' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! Sajn_Log::all() ) : ?>
						<tr><td colspan="5"><?php esc_html_e( 'Nothing yet.', 'sajn' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( Sajn_Log::all() as $entry ) : ?>
						<tr>
							<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $entry['time'] ) ); ?></td>
							<td><?php echo esc_html( $entry['connection'] ); ?></td>
							<td><?php echo $entry['success'] ? esc_html__( 'Sent', 'sajn' ) : '<strong style="color:#b32d2e">' . esc_html__( 'Failed', 'sajn' ) . '</strong>'; ?></td>
							<td><?php echo esc_html( $entry['message'] ); ?></td>
							<td>
								<?php if ( '' !== $entry['document_id'] && '' !== $workspace_slug ) : ?>
									<a href="<?php echo esc_url( Sajn_Api::base_url() . '/' . rawurlencode( $workspace_slug ) . '/documents/' . rawurlencode( $entry['document_id'] ) ); ?>" target="_blank" rel="noopener"><code><?php echo esc_html( $entry['document_id'] ); ?></code></a>
								<?php elseif ( '' !== $entry['document_id'] ) : ?>
									<code><?php echo esc_html( $entry['document_id'] ); ?></code>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/* Helpers */

	private static function sanitize_parties( $parties ) {
		$clean = array();
		foreach ( is_array( $parties ) ? $parties : array() as $party_id => $party ) {
			$delivery = isset( $party['delivery'] ) ? sanitize_key( $party['delivery'] ) : 'EMAIL';

			$clean[ sanitize_text_field( $party_id ) ] = array(
				'name'             => isset( $party['name'] ) ? sanitize_text_field( $party['name'] ) : '',
				'email'            => isset( $party['email'] ) ? sanitize_text_field( $party['email'] ) : '',
				'phone'            => isset( $party['phone'] ) ? sanitize_text_field( $party['phone'] ) : '',
				'delivery'         => array_key_exists( strtoupper( $delivery ), Sajn_Connections::DELIVERIES ) ? strtoupper( $delivery ) : 'EMAIL',
				'signature_method' => isset( $party['signature_method'] ) ? sanitize_text_field( $party['signature_method'] ) : '',
			);
		}
		return $clean;
	}

	private static function form_label( $source_id, $form_id ) {
		$source = Sajn_Sources::get( $source_id );
		if ( ! $source ) {
			return __( '(form plugin inactive)', 'sajn' );
		}
		$forms = $source->forms();
		return $source->label() . ': ' . ( isset( $forms[ $form_id ] ) ? $forms[ $form_id ] : __( '(deleted form)', 'sajn' ) );
	}

	private static function role_label( $role ) {
		$labels = array(
			'SIGNER'    => __( 'signer', 'sajn' ),
			'REVIEWER'  => __( 'reviewer', 'sajn' ),
			'ORGANIZER' => __( 'organizer', 'sajn' ),
		);
		return isset( $labels[ $role ] ) ? $labels[ $role ] : strtolower( (string) $role );
	}

	private static function api_key_warning() {
		if ( Sajn_Api::has_api_key() ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>' . wp_kses(
			sprintf(
				/* translators: %s: URL of the settings page */
				__( 'Add your sajn API key under <a href="%s">sajn → Settings</a> first.', 'sajn' ),
				esc_url( admin_url( 'admin.php?page=sajn-settings' ) )
			),
			array( 'a' => array( 'href' => array() ) )
		) . '</p></div>';
	}

	private static function notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
		$code     = isset( $_GET['sajn_notice'] ) ? sanitize_key( $_GET['sajn_notice'] ) : '';
		$messages = array(
			'settings_saved'     => __( 'Settings saved.', 'sajn' ),
			'connection_saved'   => __( 'Connection saved.', 'sajn' ),
			'connection_deleted' => __( 'Connection deleted.', 'sajn' ),
		);
		if ( isset( $messages[ $code ] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $messages[ $code ] ) . '</p></div>';
		}
	}

	private static function authorize( $nonce_action ) {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to change sajn settings.', 'sajn' ), 403 );
		}
		check_admin_referer( $nonce_action );
	}

	private static function redirect( $path, $notice ) {
		wp_safe_redirect( add_query_arg( 'sajn_notice', $notice, admin_url( $path ) ) );
		exit;
	}
}
