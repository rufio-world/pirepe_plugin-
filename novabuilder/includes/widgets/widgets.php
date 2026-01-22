<?php
namespace NovaBuilder\Widgets;

use NovaBuilder\Rendering\Renderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Widgets {
	private static $instance;
	private $widgets = array();

	public static function instance() : Widgets {
		if ( null === self::$instance ) {
			self::$instance = new Widgets();
			self::$instance->boot();
		}

		return self::$instance;
	}

	private function boot() : void {
		$this->register_defaults();
		do_action( 'novabuilder_register_widgets', $this );
	}

	public function register( string $name, callable $renderer ) : void {
		$this->widgets[ $name ] = $renderer;
	}

	public function render( string $name, array $settings, array $children ) : string {
		if ( ! isset( $this->widgets[ $name ] ) ) {
			return $this->render_children( $children );
		}

		return call_user_func( $this->widgets[ $name ], $settings, $children, array( $this, 'render_children' ) );
	}

	private function render_children( array $children ) : string {
		$output = '';
		foreach ( $children as $child ) {
			$output .= Renderer::instance()->render_node( $child );
		}
		return $output;
	}

	private function register_defaults() : void {
		$this->register( 'container', function( $settings, $children, $render_children ) {
			return '<div class="novabuilder-container">' . $render_children( $children ) . '</div>';
		} );

		$this->register( 'columns', function( $settings, $children, $render_children ) {
			return '<div class="novabuilder-columns">' . $render_children( $children ) . '</div>';
		} );

		$this->register( 'column', function( $settings, $children, $render_children ) {
			return '<div class="novabuilder-column">' . $render_children( $children ) . '</div>';
		} );

		$this->register( 'heading', function( $settings ) {
			$tag = isset( $settings['tag'] ) ? sanitize_text_field( $settings['tag'] ) : 'h2';
			$text = isset( $settings['text'] ) ? wp_kses_post( $settings['text'] ) : '';
			return sprintf( '<%1$s class="novabuilder-heading">%2$s</%1$s>', esc_attr( $tag ), $text );
		} );

		$this->register( 'text', function( $settings ) {
			$text = isset( $settings['text'] ) ? wp_kses_post( $settings['text'] ) : '';
			return '<div class="novabuilder-text">' . $text . '</div>';
		} );

		$this->register( 'image', function( $settings ) {
			$url = isset( $settings['url'] ) ? esc_url( $settings['url'] ) : '';
			$alt = isset( $settings['alt'] ) ? esc_attr( $settings['alt'] ) : '';
			return $url ? '<img class="novabuilder-image" src="' . $url . '" alt="' . $alt . '" loading="lazy" />' : '';
		} );

		$this->register( 'button', function( $settings ) {
			$text = isset( $settings['text'] ) ? esc_html( $settings['text'] ) : '';
			$url = isset( $settings['url'] ) ? esc_url( $settings['url'] ) : '#';
			return '<a class="novabuilder-button" href="' . $url . '">' . $text . '</a>';
		} );

		$this->register( 'spacer', function( $settings ) {
			$height = isset( $settings['height'] ) ? esc_attr( $settings['height'] ) : '24px';
			return '<div class="novabuilder-spacer" style="height:' . $height . '"></div>';
		} );

		$this->register( 'divider', function() {
			return '<hr class="novabuilder-divider" />';
		} );

		$this->register( 'icon', function( $settings ) {
			$icon = isset( $settings['icon'] ) ? esc_html( $settings['icon'] ) : '★';
			return '<span class="novabuilder-icon" aria-hidden="true">' . $icon . '</span>';
		} );
	}
}
