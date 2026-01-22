<?php
namespace NovaBuilder\Editor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Editor {
	private static $instance;

	public static function instance() : Editor {
		if ( null === self::$instance ) {
			self::$instance = new Editor();
			self::$instance->boot();
		}

		return self::$instance;
	}

	private function boot() : void {
		add_action( 'admin_menu', array( $this, 'register_editor_page' ) );
		add_filter( 'post_row_actions', array( $this, 'add_edit_link' ), 10, 2 );
		add_filter( 'page_row_actions', array( $this, 'add_edit_link' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function register_editor_page() : void {
		add_submenu_page(
			'novabuilder',
			__( 'Editor', 'novabuilder' ),
			__( 'Editor', 'novabuilder' ),
			'edit_posts',
			'novabuilder-editor',
			array( $this, 'render_editor' )
		);
	}

	public function add_edit_link( array $actions, \WP_Post $post ) : array {
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}

		$url = admin_url( 'admin.php?page=novabuilder-editor&post_id=' . $post->ID );
		$actions['novabuilder'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $url ),
			esc_html__( 'Edit with NovaBuilder', 'novabuilder' )
		);

		return $actions;
	}

	public function enqueue_assets( string $hook ) : void {
		if ( 'novabuilder_page_novabuilder-editor' !== $hook ) {
			return;
		}

		$asset_path = NOVABUILDER_PATH . 'build/index.asset.php';
		$asset = file_exists( $asset_path ) ? include $asset_path : array(
			'dependencies' => array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-data' ),
			'version' => NOVABUILDER_VERSION,
		);

		wp_enqueue_style(
			'novabuilder-editor',
			NOVABUILDER_URL . 'assets/editor.css',
			array(),
			NOVABUILDER_VERSION
		);

		wp_enqueue_script(
			'novabuilder-editor',
			NOVABUILDER_URL . 'build/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_localize_script(
			'novabuilder-editor',
			'NovaBuilderSettings',
			array(
				'restUrl' => esc_url_raw( rest_url( 'novabuilder/v1' ) ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
				'postId' => isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0,
			)
		);
	}

	public function render_editor() : void {
		?>
		<div class="wrap novabuilder-editor-wrap">
			<div id="novabuilder-editor-root"></div>
		</div>
		<?php
	}
}
