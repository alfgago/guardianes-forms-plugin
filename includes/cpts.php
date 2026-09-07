<?php

/**
 * Custom Post Types y taxonomias.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function gnf_register_cpts() {
	register_post_type(
		'centro_educativo',
		array(
			'label'               => 'Centros Educativos',
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => 'gnf-admin',
			'has_archive'         => false,
			'show_in_rest'        => false,
			'supports'            => array( 'title' ),
			'menu_icon'           => 'dashicons-building',
			'menu_position'       => 21,
			'exclude_from_search' => true,
		)
	);

	register_post_type(
		'reto',
		array(
			'label'               => 'Retos',
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => 'gnf-admin',
			'has_archive'         => false,
			'show_in_rest'        => false,
			'supports'            => array( 'title', 'thumbnail', 'page-attributes' ),
			'menu_icon'           => 'dashicons-flag',
			'menu_position'       => 22,
			'exclude_from_search' => true,
			'hierarchical'        => true,
		)
	);

	register_taxonomy(
		'gn_region',
		array( 'centro_educativo' ),
		array(
			'labels'            => array(
				'name'          => 'Direcciones Regionales',
				'singular_name' => 'Direccion Regional',
				'menu_name'     => 'Direcciones Regionales',
				'all_items'     => 'Todas las Direcciones Regionales',
				'edit_item'     => 'Editar Direccion Regional',
				'view_item'     => 'Ver Direccion Regional',
				'update_item'   => 'Actualizar Direccion Regional',
				'add_new_item'  => 'Agregar Nueva Direccion Regional',
				'new_item_name' => 'Nombre de la Nueva Direccion Regional',
				'search_items'  => 'Buscar Direcciones Regionales',
				'not_found'     => 'No se encontraron direcciones regionales.',
			),
			'public'            => false,
			'show_ui'           => true,
			'hierarchical'      => false,
			'show_in_menu'      => false,
			'show_admin_column' => true,
			'capabilities'      => array(
				'manage_terms' => 'manage_options',
				'edit_terms'   => 'manage_options',
				'delete_terms' => 'manage_options',
				'assign_terms' => 'manage_options',
			),
		)
	);
}
add_action( 'init', 'gnf_register_cpts', 5 );

/**
 * Renderiza el campo de activacion al crear una Direccion Regional.
 *
 * @return void
 */
function gnf_render_region_active_add_field() {
	?>
	<div class="form-field term-gnf-dre-activa-wrap">
		<label for="gnf_dre_activa">DRE activa para pilotaje</label>
		<input type="hidden" name="gnf_dre_activa_present" value="1" />
		<label for="gnf_dre_activa" style="font-weight: 400;">
			<input type="checkbox" id="gnf_dre_activa" name="gnf_dre_activa" value="1" checked="checked" />
			Mostrar esta Direccion Regional en las opciones disponibles para usuarios.
		</label>
		<p>Solo las DRE activas aparecen en registros, filtros publicos y buscadores de centros.</p>
	</div>
	<?php
}
add_action( 'gn_region_add_form_fields', 'gnf_render_region_active_add_field' );

/**
 * Renderiza el campo de activacion al editar una Direccion Regional.
 *
 * @param WP_Term $term Termino editado.
 * @return void
 */
function gnf_render_region_active_edit_field( $term ) {
	$active = function_exists( 'gnf_is_region_active' )
		? gnf_is_region_active( $term->term_id )
		: '1' === (string) get_term_meta( $term->term_id, 'gnf_dre_activa', true );
	?>
	<tr class="form-field term-gnf-dre-activa-wrap">
		<th scope="row">
			<label for="gnf_dre_activa">DRE activa para pilotaje</label>
		</th>
		<td>
			<input type="hidden" name="gnf_dre_activa_present" value="1" />
			<label for="gnf_dre_activa">
				<input type="checkbox" id="gnf_dre_activa" name="gnf_dre_activa" value="1" <?php checked( $active ); ?> />
				Mostrar esta Direccion Regional en las opciones disponibles para usuarios.
			</label>
			<p class="description">Solo las DRE activas aparecen en registros, filtros publicos y buscadores de centros.</p>
		</td>
	</tr>
	<?php
}
add_action( 'gn_region_edit_form_fields', 'gnf_render_region_active_edit_field' );

/**
 * Guarda el estado de activacion desde el formulario nativo de gn_region.
 *
 * @param int $term_id ID del termino.
 * @return void
 */
function gnf_save_region_active_meta( $term_id ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( ! isset( $_POST['gnf_dre_activa_present'] ) ) {
		return;
	}

	$active = isset( $_POST['gnf_dre_activa'] ) ? '1' : '0';
	update_term_meta( $term_id, 'gnf_dre_activa', $active );
}
add_action( 'created_gn_region', 'gnf_save_region_active_meta' );
add_action( 'edited_gn_region', 'gnf_save_region_active_meta' );

/**
 * Agrega columna visible de Direccion Regional en el listado wp-admin de centros.
 *
 * @param array<string,string> $columns Columnas actuales.
 * @return array<string,string>
 */
function gnf_add_centro_region_admin_column( $columns ) {
	$updated = array();

	foreach ( $columns as $key => $label ) {
		$updated[ $key ] = $label;
		if ( 'title' === $key ) {
			$updated['gnf_region'] = 'Direccion Regional';
		}
	}

	if ( ! isset( $updated['gnf_region'] ) ) {
		$updated['gnf_region'] = 'Direccion Regional';
	}

	return $updated;
}
add_filter( 'manage_centro_educativo_posts_columns', 'gnf_add_centro_region_admin_column' );

/**
 * Renderiza la columna de Direccion Regional en wp-admin.
 *
 * @param string $column  Nombre de la columna.
 * @param int    $post_id ID del post.
 * @return void
 */
function gnf_render_centro_region_admin_column( $column, $post_id ) {
	if ( 'gnf_region' !== $column ) {
		return;
	}

	$terms = wp_get_object_terms( $post_id, 'gn_region' );
	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		echo 'Sin region';
		return;
	}

	echo esc_html( implode( ', ', wp_list_pluck( $terms, 'name' ) ) );
}
add_action( 'manage_centro_educativo_posts_custom_column', 'gnf_render_centro_region_admin_column', 10, 2 );

/**
 * Agrega filtro por Direccion Regional al listado nativo de centros.
 *
 * @param string $post_type Post type actual.
 * @return void
 */
function gnf_render_centro_region_admin_filter( $post_type ) {
	if ( 'centro_educativo' !== $post_type ) {
		return;
	}

	$selected = isset( $_GET['gnf_region_filter'] ) ? absint( wp_unslash( $_GET['gnf_region_filter'] ) ) : 0;

	wp_dropdown_categories(
		array(
			'show_option_all' => 'Todas las Direcciones Regionales',
			'taxonomy'        => 'gn_region',
			'name'            => 'gnf_region_filter',
			'orderby'         => 'name',
			'selected'        => $selected,
			'hide_empty'      => false,
			'value_field'     => 'term_id',
		)
	);
}
add_action( 'restrict_manage_posts', 'gnf_render_centro_region_admin_filter' );

/**
 * Aplica el filtro por Direccion Regional al query del listado nativo.
 *
 * @param WP_Query $query Query actual.
 * @return void
 */
function gnf_filter_centro_admin_query_by_region( $query ) {
	if ( ! is_admin() || ! $query instanceof WP_Query || ! $query->is_main_query() ) {
		return;
	}

	$post_type = $query->get( 'post_type' );
	if ( 'centro_educativo' !== $post_type ) {
		return;
	}

	$region_id = isset( $_GET['gnf_region_filter'] ) ? absint( wp_unslash( $_GET['gnf_region_filter'] ) ) : 0;
	if ( ! $region_id ) {
		return;
	}

	$query->set(
		'tax_query',
		array(
			array(
				'taxonomy' => 'gn_region',
				'field'    => 'term_id',
				'terms'    => array( $region_id ),
			),
		)
	);
}
add_action( 'pre_get_posts', 'gnf_filter_centro_admin_query_by_region' );

/**
 * Añade la entrada al panel docente en cada fila de centro.
 *
 * @param array<string,string> $actions Acciones existentes.
 * @param WP_Post              $post    Centro actual.
 * @return array<string,string>
 */
function gnf_add_centro_docente_row_action( $actions, $post ) {
	if ( ! current_user_can( 'manage_options' ) || ! $post instanceof WP_Post || 'centro_educativo' !== $post->post_type ) {
		return $actions;
	}

	static $primary_docentes = null;
	if ( null === $primary_docentes ) {
		global $wp_query;
		$visible_ids = $wp_query instanceof WP_Query
			? array_map( 'absint', wp_list_pluck( (array) $wp_query->posts, 'ID' ) )
			: array();
		if ( ! $visible_ids ) {
			$visible_ids = array( $post->ID );
		}
		$primary_docentes = function_exists( 'gnf_get_primary_docentes_for_centros' )
			? gnf_get_primary_docentes_for_centros( $visible_ids )
			: array();
	}

	$docente_id = absint( $primary_docentes[ $post->ID ] ?? 0 );
	$url        = $docente_id && function_exists( 'gnf_build_impersonate_url' )
		? gnf_build_impersonate_url(
			$docente_id,
			gnf_get_current_admin_return_url( admin_url( 'edit.php?post_type=centro_educativo' ) )
		)
		: '';
	if ( $url ) {
		$actions['gnf_enter_docente'] = '<a href="' . esc_url( $url ) . '">Entrar como docente</a>';
	}

	return $actions;
}
add_filter( 'post_row_actions', 'gnf_add_centro_docente_row_action', 20, 2 );

/**
 * Muestra la descarga XLSX sobre el listado nativo de centros.
 *
 * @param string $which Posición del tablenav.
 * @return void
 */
function gnf_render_centros_xlsx_admin_button( $which ) {
	global $typenow;
	if ( 'top' !== $which || 'centro_educativo' !== $typenow || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( ! function_exists( 'gnf_get_centros_xlsx_export_url' ) ) {
		return;
	}

	$region = isset( $_GET['gnf_region_filter'] ) ? absint( wp_unslash( $_GET['gnf_region_filter'] ) ) : 0;
	echo '<a class="button button-primary" style="margin-left:8px" href="' . esc_url( gnf_get_centros_xlsx_export_url( gnf_get_active_year(), $region ) ) . '">Descargar inscritos XLSX</a>';
}
add_action( 'manage_posts_extra_tablenav', 'gnf_render_centros_xlsx_admin_button' );
