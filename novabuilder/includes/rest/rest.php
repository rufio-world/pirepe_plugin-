<?php
namespace NovaBuilder\Rest;

use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Rest {
	private static $instance;

	public static function instance() : Rest {
		if ( null === self::$instance ) {
			self::$instance = new Rest();
			self::$instance->boot();
		}

		return self::$instance;
	}

	private function boot() : void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() : void {
		register_rest_route(
			'novabuilder/v1',
			'/builder/(?P<id>\d+)',
			array(
				array(
					'methods' => 'GET',
					'callback' => array( $this, 'get_builder_data' ),
					'permission_callback' => array( $this, 'can_edit' ),
				),
				array(
					'methods' => 'POST',
					'callback' => array( $this, 'save_builder_data' ),
					'permission_callback' => array( $this, 'can_edit' ),
				),
			)
		);

		register_rest_route(
			'novabuilder/v1',
			'/templates',
			array(
				'methods' => 'GET',
				'callback' => array( $this, 'list_templates' ),
				'permission_callback' => array( $this, 'can_edit' ),
			)
		);

		register_rest_route(
			'novabuilder/v1',
			'/templates/resolve',
			array(
				'methods' => 'POST',
				'callback' => array( $this, 'resolve_template' ),
				'permission_callback' => array( $this, 'can_edit' ),
			)
		);

		register_rest_route(
			'novabuilder/v1',
			'/tokens',
			array(
				array(
					'methods' => 'GET',
					'callback' => array( $this, 'get_tokens' ),
					'permission_callback' => array( $this, 'can_edit' ),
				),
				array(
					'methods' => 'POST',
					'callback' => array( $this, 'save_tokens' ),
					'permission_callback' => array( $this, 'can_edit' ),
				),
			)
		);
	}

	public function can_edit() : bool {
		return current_user_can( 'edit_posts' );
	}

	public function get_builder_data( WP_REST_Request $request ) : WP_REST_Response {
		$post_id = absint( $request['id'] );
		$data = get_post_meta( $post_id, NOVABUILDER_META_KEY, true );

		if ( empty( $data ) ) {
			$data = array(
				'version' => '1.0.0',
				'content' => array(),
				'settings' => array(),
			);
		}

		return new WP_REST_Response( $data, 200 );
	}

	public function save_builder_data( WP_REST_Request $request ) : WP_REST_Response {
		$post_id = absint( $request['id'] );
		$data = $request->get_json_params();

		if ( ! is_array( $data ) ) {
			return new WP_REST_Response( array( 'message' => 'Invalid payload.' ), 400 );
		}

		update_post_meta( $post_id, NOVABUILDER_META_KEY, $data );

		return new WP_REST_Response( array( 'success' => true ), 200 );
	}

	public function list_templates() : WP_REST_Response {
		$templates = get_posts(
			array(
				'post_type' => NOVABUILDER_TEMPLATE_CPT,
				'numberposts' => -1,
			)
		);

		$response = array();
		foreach ( $templates as $template ) {
			$response[] = array(
				'id' => $template->ID,
				'title' => $template->post_title,
				'location' => get_post_meta( $template->ID, '_novabuilder_location', true ),
				'conditions' => get_post_meta( $template->ID, '_novabuilder_conditions', true ),
			);
		}

		return new WP_REST_Response( $response, 200 );
	}

	public function resolve_template( WP_REST_Request $request ) : WP_REST_Response {
		$params = $request->get_json_params();
		$location = isset( $params['location'] ) ? sanitize_text_field( $params['location'] ) : '';
		$context = isset( $params['context'] ) && is_array( $params['context'] ) ? $params['context'] : array();

		if ( empty( $location ) ) {
			return new WP_REST_Response( array( 'message' => 'Location required.' ), 400 );
		}

		$templates = get_posts(
			array(
				'post_type' => NOVABUILDER_TEMPLATE_CPT,
				'meta_key' => '_novabuilder_location',
				'meta_value' => $location,
				'numberposts' => -1,
			)
		);

		if ( empty( $templates ) ) {
			return new WP_REST_Response( array( 'id' => 0 ), 200 );
		}

		foreach ( $templates as $template ) {
			$conditions = get_post_meta( $template->ID, '_novabuilder_conditions', true );
			if ( $this->conditions_match( $conditions, $context ) ) {
				return new WP_REST_Response( array( 'id' => $template->ID ), 200 );
			}
		}

		return new WP_REST_Response( array( 'id' => 0 ), 200 );
	}

	public function get_tokens() : WP_REST_Response {
		$tokens = get_option( 'novabuilder_design_tokens', array() );
		return new WP_REST_Response( $tokens, 200 );
	}

	public function save_tokens( WP_REST_Request $request ) : WP_REST_Response {
		$tokens = $request->get_json_params();
		if ( ! is_array( $tokens ) ) {
			return new WP_REST_Response( array( 'message' => 'Invalid tokens.' ), 400 );
		}
		update_option( 'novabuilder_design_tokens', $tokens );
		return new WP_REST_Response( array( 'success' => true ), 200 );
	}

	private function conditions_match( $conditions, array $context ) : bool {
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
