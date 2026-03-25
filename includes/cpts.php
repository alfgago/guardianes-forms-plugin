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
