<?php
namespace NovaBuilder\Rendering;

use NovaBuilder\Widgets\Widgets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Renderer {
	private static $instance;

	public static function instance() : Renderer {
		if ( null === self::$instance ) {
			self::$instance = new Renderer();
			self::$instance->boot();
		}

		return self::$instance;
	}

	private function boot() : void {
		add_filter( 'the_content', array( $this, 'inject_builder_content' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
	}

	public function inject_builder_content( string $content ) : string {
		if ( ! is_singular() ) {
			return $content;
		}

		$post_id = get_the_ID();
		$data = get_post_meta( $post_id, NOVABUILDER_META_KEY, true );

		if ( empty( $data['content'] ) ) {
			return $content;
		}

		$rendered = $this->render_nodes( $data['content'] );
		$styles = $this->render_styles( $data['content'] );

		return $content . $styles . $rendered;
	}

	public function render_builder_content( array $data ) : string {
		if ( empty( $data['content'] ) ) {
			return '';
		}

		$rendered = $this->render_nodes( $data['content'] );
		$styles = $this->render_styles( $data['content'] );

		return $styles . $rendered;
	}

	private function render_nodes( array $nodes ) : string {
		$output = '';
		foreach ( $nodes as $node ) {
			$output .= $this->render_node( $node );
		}

		return $output;
	}

	public function render_node( array $node ) : string {
		$type = isset( $node['type'] ) ? sanitize_text_field( $node['type'] ) : '';
		$settings = isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : array();
		$children = isset( $node['children'] ) && is_array( $node['children'] ) ? $node['children'] : array();
		$id = isset( $node['id'] ) ? sanitize_html_class( $node['id'] ) : uniqid( 'nb-' );
		$motion = isset( $node['motion'] ) && is_array( $node['motion'] ) ? $node['motion'] : array();
		$motion_class = $this->motion_class( $motion );

		$classes = array( 'novabuilder-node', 'novabuilder-node-' . $type );
		if ( $motion_class ) {
			$classes[] = $motion_class;
		}

		$wrapper = '<div class="' . esc_attr( implode( ' ', $classes ) ) . '" data-nb-id="' . esc_attr( $id ) . '">';
		$content = Widgets::instance()->render( $type, $settings, $children );

		return $wrapper . $content . '</div>';
	}

	private function render_styles( array $nodes ) : string {
		$css = '';
		foreach ( $nodes as $node ) {
			$css .= $this->node_styles( $node );
		}

		if ( empty( $css ) ) {
			return '';
		}

		return '<style class="novabuilder-styles">' . $css . '</style>';
	}

	private function node_styles( array $node ) : string {
		$id = isset( $node['id'] ) ? sanitize_html_class( $node['id'] ) : uniqid( 'nb-' );
		$styles = isset( $node['styles'] ) && is_array( $node['styles'] ) ? $node['styles'] : array();
		$children = isset( $node['children'] ) && is_array( $node['children'] ) ? $node['children'] : array();

		$css = '';
		$rules = array();
		foreach ( $styles as $property => $value ) {
			if ( '' === $value ) {
				continue;
			}
			$rules[] = sanitize_key( $property ) . ':' . esc_attr( $value );
		}

		if ( ! empty( $rules ) ) {
			$css .= '[data-nb-id="' . esc_attr( $id ) . '"]{' . implode( ';', $rules ) . '}';
		}

		foreach ( $children as $child ) {
			$css .= $this->node_styles( $child );
		}

		return $css;
	}

	public function enqueue_frontend_assets() : void {
		if ( ! is_singular() ) {
			return;
		}

		$post_id = get_the_ID();
		$data = get_post_meta( $post_id, NOVABUILDER_META_KEY, true );

		if ( empty( $data['content'] ) ) {
			return;
		}

		wp_enqueue_style(
			'novabuilder-frontend',
			NOVABUILDER_URL . 'assets/frontend.css',
			array(),
			NOVABUILDER_VERSION
		);
	}

	private function motion_class( array $motion ) : string {
		$animation = isset( $motion['animation'] ) ? sanitize_key( $motion['animation'] ) : '';
		$allowed = array(
			'fade-up' => 'novabuilder-anim-fade-up',
			'fade-in' => 'novabuilder-anim-fade-in',
			'slide-left' => 'novabuilder-anim-slide-left',
		);

		return isset( $allowed[ $animation ] ) ? $allowed[ $animation ] : '';
	}
}
