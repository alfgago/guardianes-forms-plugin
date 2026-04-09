<?php
/**
 * Panel de supervisor y acciones de revision.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function gnf_render_supervisor_panel() {
	if ( ! is_user_logged_in() ) {
		return gnf_render_auth_block(
			array(
				'title'         => 'Panel DRE',
				'description'   => 'Accede para revisar centros y retos de tu region.',
				'show_register' => false,
				'redirect'      => esc_url_raw( home_url( add_query_arg( array() ) ) ),
			)
		);
	}

	$user = wp_get_current_user();
	if ( ! gnf_user_has_role( $user, 'supervisor' ) && ! gnf_user_has_role( $user, 'comite_bae' ) && ! current_user_can( 'view_guardianes_supervisor' ) && ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_network_options' ) ) {
		return '<div class="gnf-auth"><div class="gnf-auth__card"><p class="gnf-muted">No tienes permisos para ver este panel.</p></div></div>';
	}

	// Comité BAE ve todas las regiones.
	$is_comite = gnf_user_has_role( $user, 'comite_bae' ) || current_user_can( 'gnf_view_all_regions' );
	$region_slug = $is_comite ? '' : gnf_get_user_region( $user->ID );
	$anio_activo = gnf_get_active_year();
	$anio        = gnf_normalize_year( isset( $_GET['gnf_year'] ) ? absint( $_GET['gnf_year'] ) : null );
	$centro_id   = isset( $_GET['centro_id'] ) ? absint( $_GET['centro_id'] ) : 0;
	$tab         = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : '';

	global $wpdb;

	// Vista de notificaciones: cargar datos mínimos y template.
	if ( 'notificaciones' === $tab ) {
		$notificaciones = gnf_get_supervisor_notificaciones( $user->ID );
		$data = array(
			'user'            => $user,
			'region_slug'     => $region_slug,
			'anio'            => $anio,
			'anio_activo'     => $anio_activo,
			'years_available' => array(),
			'notificaciones'  => $notificaciones,
		);
		$table_entries = $wpdb->prefix . 'gn_reto_entries';
		$data['years_available'] = $wpdb->get_col( "SELECT DISTINCT anio FROM {$table_entries} ORDER BY anio DESC" );
		if ( empty( $data['years_available'] ) ) {
			$data['years_available'] = array( $anio_activo );
		}
		if ( ! in_array( $anio_activo, $data['years_available'] ) ) {
			array_unshift( $data['years_available'], $anio_activo );
		}
		ob_start();
		include GNF_PATH . 'templates/supervisor-notificaciones.php';
		return ob_get_clean();
	}

	// Obtener años disponibles.
	$table_entries = $wpdb->prefix . 'gn_reto_entries';
	$years_available = $wpdb->get_col( "SELECT DISTINCT anio FROM {$table_entries} ORDER BY anio DESC" );
	if ( empty( $years_available ) ) {
		$years_available = array( $anio_activo );
	}
	if ( ! in_array( $anio_activo, $years_available ) ) {
		array_unshift( $years_available, $anio_activo );
	}

	$circuito_filter = isset( $_GET['circuito'] ) ? sanitize_text_field( wp_unslash( $_GET['circuito'] ) ) : '';

	// Solo centros con matrícula activa para el año.
	$centros_con_matricula = gnf_get_centros_with_matricula( $anio );
	$centros_args = array(
		'post_type'      => 'centro_educativo',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'post__in'       => ! empty( $centros_con_matricula ) ? $centros_con_matricula : array( 0 ),
	);

	if ( $region_slug ) {
		$centros_args['tax_query'] = array(
			array(
				'taxonomy' => 'gn_region',
				'field'    => 'id',
				'terms'    => array( $region_slug ),
			),
		);
	}

	if ( $circuito_filter ) {
		$centros_args['meta_query'] = array(
			array(
				'key'   => 'circuito',
				'value' => $circuito_filter,
			),
		);
	}

	$centros = new WP_Query( $centros_args );

	// Obtener circuitos únicos para la región (solo centros con matrícula).
	$circuitos_disponibles = array();
	$all_centros_for_circuits = new WP_Query( array(
		'post_type'      => 'centro_educativo',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'post__in'       => ! empty( $centros_con_matricula ) ? $centros_con_matricula : array( 0 ),
		'tax_query'      => $region_slug ? array(
			array(
				'taxonomy' => 'gn_region',
				'field'    => 'id',
				'terms'    => array( $region_slug ),
			),
		) : array(),
	) );
	foreach ( $all_centros_for_circuits->posts as $cid ) {
		$circ = get_post_meta( $cid, 'circuito', true );
		if ( $circ && ! in_array( $circ, $circuitos_disponibles, true ) ) {
			$circuitos_disponibles[] = $circ;
		}
	}
	sort( $circuitos_disponibles );
	$centro_ids = wp_list_pluck( $centros->posts, 'ID' );

	global $wpdb;
	$table = $wpdb->prefix . 'gn_reto_entries';
	$entries_by_centro = array();
	if ( $centro_ids ) {
		$cache_key = 'gnf_sup_entries_' . md5( implode( '-', $centro_ids ) . '_' . $anio );
		$entries_by_centro = get_transient( $cache_key );
		if ( false === $entries_by_centro ) {
			$center_placeholders = implode( ',', array_fill( 0, count( $centro_ids ), '%d' ) );
			$sql                 = $wpdb->prepare(
				"SELECT * FROM {$table} WHERE anio = %d AND centro_id IN ({$center_placeholders})",
				array_merge( array( $anio ), $centro_ids )
			);
			$entries_raw = $wpdb->get_results( $sql );
			$entries_by_centro = array();
			foreach ( (array) $entries_raw as $entry ) {
				$entries_by_centro[ $entry->centro_id ][ $entry->reto_id ] = $entry;
			}
			set_transient( $cache_key, $entries_by_centro, HOUR_IN_SECONDS );
		}
	}

	$data = array(
		'user'                  => $user,
		'region_slug'           => $region_slug,
		'centros'               => $centros,
		'centro_id'             => $centro_id,
		'entries_by_centro'     => $entries_by_centro,
		'anio'                  => $anio,
		'anio_activo'           => $anio_activo,
		'years_available'       => $years_available,
		'circuito_filter'       => $circuito_filter,
		'circuitos_disponibles' => $circuitos_disponibles,
	);

	ob_start();
	$template = $centro_id ? GNF_PATH . 'templates/supervisor-centro.php' : GNF_PATH . 'templates/supervisor-dashboard.php';
	include $template;
	wp_reset_postdata();
	return ob_get_clean();
}

