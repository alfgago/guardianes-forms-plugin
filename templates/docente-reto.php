<?php
/**
 * Template opcional para detalle de reto en el panel docente.
 *
 * Este archivo puede incluirse desde páginas específicas si se requiere vista individual.
 */
?>
<div class="gnf-card">
	<h3><?php the_title(); ?></h3>
	<p><?php echo esc_html( get_field( 'descripcion' ) ); ?></p>
</div>
