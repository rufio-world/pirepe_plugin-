<?php
namespace NovaBuilder;

use NovaBuilder\Admin\Admin;
use NovaBuilder\Editor\Editor;
use NovaBuilder\Rendering\Renderer;
use NovaBuilder\Rest\Rest;
use NovaBuilder\Templates\Templates;
use NovaBuilder\Widgets\Widgets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {
	private static $instance;

	public static function instance() : Plugin {
		if ( null === self::$instance ) {
			self::$instance = new Plugin();
			self::$instance->boot();
		}

		return self::$instance;
	}

	private function boot() : void {
		spl_autoload_register( array( $this, 'autoload' ) );

		Admin::instance();
		Editor::instance();
		Rest::instance();
		Renderer::instance();
		Templates::instance();
		Widgets::instance();

		add_action( 'init', array( $this, 'register_meta' ) );
	}

	public static function activate() : void {
		if ( ! post_type_exists( NOVABUILDER_TEMPLATE_CPT ) ) {
			register_post_type(
				NOVABUILDER_TEMPLATE_CPT,
				array(
					'label' => __( 'NovaBuilder Templates', 'novabuilder' ),
					'public' => false,
					'show_ui' => true,
					'supports' => array( 'title' ),
				)
			);
		}

		$starter_templates = array(
			'Nova Header' => 'header',
			'Nova Footer' => 'footer',
			'Nova Single' => 'single',
		);

		foreach ( $starter_templates as $title => $location ) {
			$existing = get_posts(
				array(
					'post_type' => NOVABUILDER_TEMPLATE_CPT,
					'title' => $title,
					'numberposts' => 1,
				)
			);

			if ( ! empty( $existing ) ) {
				continue;
			}

			$template_id = wp_insert_post(
				array(
					'post_title' => $title,
					'post_type' => NOVABUILDER_TEMPLATE_CPT,
					'post_status' => 'publish',
				)
			);

			if ( $template_id && ! is_wp_error( $template_id ) ) {
				update_post_meta( $template_id, '_novabuilder_location', $location );
				update_post_meta(
					$template_id,
					NOVABUILDER_META_KEY,
					array(
						'version' => '1.0.0',
						'content' => array(),
						'settings' => array(),
					)
				);
			}
		}
	}

	public function autoload( string $class ) : void {
		if ( 0 !== strpos( $class, __NAMESPACE__ . '\\' ) ) {
			return;
		}

		$path = str_replace( array( __NAMESPACE__ . '\\', '\\' ), array( '', DIRECTORY_SEPARATOR ), $class );
		$file = NOVABUILDER_PATH . 'includes/' . strtolower( $path ) . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}

	public function register_meta() : void {
		register_post_meta(
			'',
			NOVABUILDER_META_KEY,
			array(
				'show_in_rest' => array(
					'schema' => array(
						'type'       => 'object',
						'properties' => array(
							'version' => array( 'type' => 'string' ),
							'content' => array( 'type' => 'array' ),
							'settings' => array( 'type' => 'object' ),
						),
					),
				),
				'single'       => true,
				'type'         => 'object',
				'auth_callback' => function() {
					return current_user_can( 'edit_posts' );
				},
				'sanitize_callback' => function( $value ) {
					if ( ! is_array( $value ) ) {
						return array();
					}
					return $value;
				},
			)
		);
	}
}
