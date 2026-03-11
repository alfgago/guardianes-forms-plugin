<?php

/**
 * Custom Post Types y taxonomías.
 */

if (! defined('ABSPATH')) {
	exit;
}


function gnf_register_cpts()
{
	register_post_type(
		'centro_educativo',
		array(
			'label'               => 'Centros Educativos',
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => false,
			'has_archive'         => false,
			'show_in_rest'        => false,
			'supports'            => array('title'),
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
			'show_in_menu'        => false,
			'has_archive'         => false,
			'show_in_rest'        => false,
			'supports'            => array('title', 'thumbnail', 'page-attributes'),
			'menu_icon'           => 'dashicons-flag',
			'menu_position'       => 22,
			'exclude_from_search' => true,
			'hierarchical'        => true,
		)
	);

	register_taxonomy(
		'gn_region',
		array('centro_educativo'),
		array(
			'labels'       => array(
				'name'              => 'Direcciones Regionales',
				'singular_name'     => 'Dirección Regional',
				'menu_name'         => 'Direcciones Regionales',
				'all_items'         => 'Todas las Direcciones Regionales',
				'edit_item'         => 'Editar Dirección Regional',
				'view_item'         => 'Ver Dirección Regional',
				'update_item'       => 'Actualizar Dirección Regional',
				'add_new_item'      => 'Agregar Nueva Dirección Regional',
				'new_item_name'     => 'Nombre de la Nueva Dirección Regional',
				'search_items'      => 'Buscar Direcciones Regionales',
				'not_found'         => 'No se encontraron direcciones regionales.',
			),
			'public'       => false,
			'show_ui'      => true,
			'hierarchical' => false,
			'show_in_menu' => false, // Lo mostramos en nuestro menú personalizado.
			'capabilities' => array(
				'manage_terms' => 'manage_options',
				'edit_terms'   => 'manage_options',
				'delete_terms' => 'manage_options',
				'assign_terms' => 'manage_options',
			),
		)
	);
}
add_action('init', 'gnf_register_cpts', 5);
