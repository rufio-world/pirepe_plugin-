<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use NovaBuilder\Rendering\Renderer;
use NovaBuilder\Templates\Templates;

get_header();
$template_id = Templates::instance()->get_active_template_id();
$data = $template_id ? get_post_meta( $template_id, NOVABUILDER_META_KEY, true ) : array();
?>
<main class="novabuilder-template">
	<?php
	if ( ! empty( $data['content'] ) ) {
		echo Renderer::instance()->render_builder_content( $data );
	} else {
		while ( have_posts() ) {
			the_post();
			the_content();
		}
	}
	?>
</main>
<?php
get_footer();
