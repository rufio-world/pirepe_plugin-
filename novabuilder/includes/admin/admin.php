<?php
namespace NovaBuilder\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Admin {
	private static $instance;

	public static function instance() : Admin {
		if ( null === self::$instance ) {
			self::$instance = new Admin();
			self::$instance->boot();
		}

		return self::$instance;
	}

	private function boot() : void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public function register_menu() : void {
		add_menu_page(
			__( 'NovaBuilder', 'novabuilder' ),
			__( 'NovaBuilder', 'novabuilder' ),
			'manage_options',
			'novabuilder',
			array( $this, 'render_settings' ),
			'dashicons-layout',
			58
		);

		add_submenu_page(
			'novabuilder',
			__( 'Settings', 'novabuilder' ),
			__( 'Settings', 'novabuilder' ),
			'manage_options',
			'novabuilder',
			array( $this, 'render_settings' )
		);

		add_submenu_page(
			'novabuilder',
			__( 'Templates', 'novabuilder' ),
			__( 'Templates', 'novabuilder' ),
			'edit_posts',
			'edit.php?post_type=' . NOVABUILDER_TEMPLATE_CPT
		);
	}

	public function register_settings() : void {
		register_setting( 'novabuilder_settings', 'novabuilder_settings', array( $this, 'sanitize_settings' ) );
		register_setting( 'novabuilder_settings', 'novabuilder_design_tokens', array( $this, 'sanitize_tokens' ) );

		add_settings_section(
			'novabuilder_general',
			__( 'General Settings', 'novabuilder' ),
			'__return_false',
			'novabuilder'
		);

		add_settings_field(
			'enable_builder',
			__( 'Enable Builder', 'novabuilder' ),
			array( $this, 'render_enable_builder' ),
			'novabuilder',
			'novabuilder_general'
		);

		add_settings_section(
			'novabuilder_tokens',
			__( 'Design Tokens', 'novabuilder' ),
			'__return_false',
			'novabuilder'
		);

		add_settings_field(
			'design_tokens',
			__( 'Global Tokens (JSON)', 'novabuilder' ),
			array( $this, 'render_tokens_field' ),
			'novabuilder',
			'novabuilder_tokens'
		);
	}

	public function sanitize_settings( array $settings ) : array {
		$settings['enable_builder'] = isset( $settings['enable_builder'] ) ? 1 : 0;
		return $settings;
	}

	public function sanitize_tokens( $tokens ) : array {
		if ( is_string( $tokens ) ) {
			$decoded = json_decode( wp_unslash( $tokens ), true );
			if ( is_array( $decoded ) ) {
				return $decoded;
			}
			return array();
		}

		return is_array( $tokens ) ? $tokens : array();
	}

	public function render_enable_builder() : void {
		$options = get_option( 'novabuilder_settings', array() );
		$enabled = isset( $options['enable_builder'] ) && $options['enable_builder'];
		?>
		<label>
			<input type="checkbox" name="novabuilder_settings[enable_builder]" value="1" <?php checked( $enabled ); ?> />
			<?php esc_html_e( 'Enable NovaBuilder editor for supported post types.', 'novabuilder' ); ?>
		</label>
		<?php
	}

	public function render_settings() : void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'NovaBuilder Settings', 'novabuilder' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'novabuilder_settings' );
				do_settings_sections( 'novabuilder' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	public function render_tokens_field() : void {
		$tokens = get_option( 'novabuilder_design_tokens', array() );
		$value = wp_json_encode( $tokens, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		?>
		<textarea name="novabuilder_design_tokens" rows="10" class="large-text code"><?php echo esc_textarea( $value ); ?></textarea>
		<p class="description"><?php esc_html_e( 'Define global colors, typography, and spacing tokens as JSON.', 'novabuilder' ); ?></p>
		<?php
	}
}
