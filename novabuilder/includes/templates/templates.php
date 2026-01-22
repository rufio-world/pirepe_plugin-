<?php
namespace NovaBuilder\Templates;

use NovaBuilder\Rendering\Renderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Templates {
	private static $instance;
	private $active_template_id = 0;

	public static function instance() : Templates {
		if ( null === self::$instance ) {
			self::$instance = new Templates();
			self::$instance->boot();
		}

		return self::$instance;
	}

	private function boot() : void {
		add_action( 'init', array( $this, 'register_cpt' ) );
		add_filter( 'template_include', array( $this, 'maybe_render_template' ) );
		add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );
		add_action( 'save_post_' . NOVABUILDER_TEMPLATE_CPT, array( $this, 'save_meta' ) );
		add_action( 'get_header', array( $this, 'inject_header' ) );
		add_action( 'get_footer', array( $this, 'inject_footer' ) );
	}

	public function register_cpt() : void {
		register_post_type(
			NOVABUILDER_TEMPLATE_CPT,
			array(
				'label' => __( 'NovaBuilder Templates', 'novabuilder' ),
				'public' => false,
				'show_ui' => true,
				'menu_icon' => 'dashicons-layout',
				'supports' => array( 'title' ),
			)
		);
	}

	public function register_meta_boxes() : void {
		add_meta_box(
			'novabuilder_template_location',
			__( 'Template Location', 'novabuilder' ),
			array( $this, 'render_location_meta' ),
			NOVABUILDER_TEMPLATE_CPT,
			'side'
		);

		add_meta_box(
			'novabuilder_template_conditions',
			__( 'Display Conditions', 'novabuilder' ),
			array( $this, 'render_conditions_meta' ),
			NOVABUILDER_TEMPLATE_CPT,
			'normal'
		);
	}

	public function render_location_meta( \WP_Post $post ) : void {
		$location = get_post_meta( $post->ID, '_novabuilder_location', true );
		wp_nonce_field( 'novabuilder_template_meta', 'novabuilder_template_meta_nonce' );
		?>
		<p>
			<label for="novabuilder-location"><?php esc_html_e( 'Location', 'novabuilder' ); ?></label>
			<select name="novabuilder_location" id="novabuilder-location" class="widefat">
				<?php foreach ( $this->locations() as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $location, $key ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php
	}

	public function render_conditions_meta( \WP_Post $post ) : void {
		$conditions = get_post_meta( $post->ID, '_novabuilder_conditions', true );
		$post_type = isset( $conditions['postType'] ) ? $conditions['postType'] : '';
		$post_ids = isset( $conditions['postIds'] ) ? implode( ',', array_map( 'absint', (array) $conditions['postIds'] ) ) : '';
		?>
		<p>
			<label for="novabuilder-condition-post-type"><?php esc_html_e( 'Post Type', 'novabuilder' ); ?></label>
			<select name="novabuilder_condition_post_type" id="novabuilder-condition-post-type" class="widefat">
				<option value=""><?php esc_html_e( 'All', 'novabuilder' ); ?></option>
				<?php foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type ) : ?>
					<option value="<?php echo esc_attr( $type->name ); ?>" <?php selected( $post_type, $type->name ); ?>>
						<?php echo esc_html( $type->labels->singular_name ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="novabuilder-condition-post-ids"><?php esc_html_e( 'Specific Post IDs (comma-separated)', 'novabuilder' ); ?></label>
			<input type="text" name="novabuilder_condition_post_ids" id="novabuilder-condition-post-ids" class="widefat" value="<?php echo esc_attr( $post_ids ); ?>" />
		</p>
		<?php
	}

	public function save_meta( int $post_id ) : void {
		if ( ! isset( $_POST['novabuilder_template_meta_nonce'] ) || ! wp_verify_nonce( $_POST['novabuilder_template_meta_nonce'], 'novabuilder_template_meta' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$location = isset( $_POST['novabuilder_location'] ) ? sanitize_text_field( wp_unslash( $_POST['novabuilder_location'] ) ) : '';
		update_post_meta( $post_id, '_novabuilder_location', $location );

		$post_type = isset( $_POST['novabuilder_condition_post_type'] ) ? sanitize_text_field( wp_unslash( $_POST['novabuilder_condition_post_type'] ) ) : '';
		$post_ids_raw = isset( $_POST['novabuilder_condition_post_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['novabuilder_condition_post_ids'] ) ) : '';
		$post_ids = array_filter( array_map( 'absint', array_map( 'trim', explode( ',', $post_ids_raw ) ) ) );

		$conditions = array();
		if ( $post_type ) {
			$conditions['postType'] = $post_type;
		}
		if ( ! empty( $post_ids ) ) {
			$conditions['postIds'] = $post_ids;
		}

		update_post_meta( $post_id, '_novabuilder_conditions', $conditions );
	}

	private function locations() : array {
		return array(
			'header' => __( 'Header', 'novabuilder' ),
			'footer' => __( 'Footer', 'novabuilder' ),
			'single' => __( 'Single', 'novabuilder' ),
			'archive' => __( 'Archive', 'novabuilder' ),
		);
	}

	public function maybe_render_template( string $template ) : string {
		if ( is_singular() ) {
			$custom = $this->get_template_for_location( 'single' );
			if ( $custom ) {
				$this->active_template_id = $custom;
				return NOVABUILDER_PATH . 'includes/templates/template-wrapper.php';
			}
		}

		if ( is_archive() ) {
			$custom = $this->get_template_for_location( 'archive' );
			if ( $custom ) {
				$this->active_template_id = $custom;
				return NOVABUILDER_PATH . 'includes/templates/template-wrapper.php';
			}
		}

		return $template;
	}

	private function get_template_for_location( string $location ) : int {
		$templates = get_posts(
			array(
				'post_type' => NOVABUILDER_TEMPLATE_CPT,
				'meta_key' => '_novabuilder_location',
				'meta_value' => $location,
				'numberposts' => -1,
			)
		);

		if ( empty( $templates ) ) {
			return 0;
		}

		$context = array(
			'postId' => is_singular() ? get_the_ID() : 0,
			'postType' => is_singular() ? get_post_type() : '',
		);

		foreach ( $templates as $template ) {
			$conditions = get_post_meta( $template->ID, '_novabuilder_conditions', true );
			if ( $this->matches_conditions( $conditions, $context ) ) {
				return $template->ID;
			}
		}

		return 0;
	}

	public function get_active_template_id() : int {
		return $this->active_template_id;
	}

	public function inject_header() : void {
		$template_id = $this->get_template_for_location( 'header' );
		if ( $template_id ) {
			echo $this->render_template_markup( $template_id );
		}
	}

	public function inject_footer() : void {
		$template_id = $this->get_template_for_location( 'footer' );
		if ( $template_id ) {
			echo $this->render_template_markup( $template_id );
		}
	}

	private function render_template_markup( int $template_id ) : string {
		$data = get_post_meta( $template_id, NOVABUILDER_META_KEY, true );
		if ( empty( $data['content'] ) ) {
			return '';
		}

		return Renderer::instance()->render_builder_content( $data );
	}

	private function matches_conditions( $conditions, array $context ) : bool {
		if ( empty( $conditions ) || ! is_array( $conditions ) ) {
			return true;
		}

		if ( isset( $conditions['postType'] ) && ! empty( $context['postType'] ) ) {
			if ( $conditions['postType'] !== $context['postType'] ) {
				return false;
			}
		}

		if ( isset( $conditions['postIds'] ) && ! empty( $context['postId'] ) ) {
			$post_ids = array_map( 'absint', (array) $conditions['postIds'] );
			if ( ! in_array( absint( $context['postId'] ), $post_ids, true ) ) {
				return false;
			}
		}

		return true;
	}
}
