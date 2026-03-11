<?php
/**
 * Comité BAE Panel - Dashboard para miembros del Comité Bandera Azul.
 *
 * @package Guardianes_Formularios
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renderiza el panel del Comité BAE.
 *
 * @return string HTML del panel.
 */
function gnf_render_comite_panel() {
	// Verificar permisos.
	$user = wp_get_current_user();
	if ( ! in_array( 'comite_bae', (array) $user->roles, true ) && ! current_user_can( 'manage_options' ) ) {
		return '<div class="gnf-alert gnf-alert--error">No tienes permisos para acceder a este panel.</div>';
	}

	ob_start();

	$anio_activo = gnf_get_active_year();
	$anio        = gnf_normalize_year( isset( $_GET['gnf_year'] ) ? absint( $_GET['gnf_year'] ) : null );
	$centro_id   = isset( $_GET['centro_id'] ) ? absint( $_GET['centro_id'] ) : 0;

	// Get available years.
	global $wpdb;
	$table = $wpdb->prefix . 'gn_reto_entries';
	$years_available = $wpdb->get_col( "SELECT DISTINCT anio FROM {$table} ORDER BY anio DESC" );
	if ( empty( $years_available ) ) {
		$years_available = array( $anio_activo );
	}

	if ( $centro_id ) {
		// Detail view - reuse supervisor-centro template with comite context.
		$entries_by_centro = array();
		$entries_raw = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE anio = %d AND centro_id = %d",
			$anio,
			$centro_id
		) );
		foreach ( (array) $entries_raw as $entry ) {
			$entries_by_centro[ $entry->centro_id ][ $entry->reto_id ] = $entry;
		}

		$region_slug = ''; // Comite has access to all regions.

		include GNF_PATH . 'templates/comite-centro.php';
	} else {
		// Dashboard view.
		$data = gnf_get_comite_data( $anio );
		$data['anio_activo']     = $anio_activo;
		$data['years_available'] = $years_available;

		include GNF_PATH . 'templates/comite-dashboard.php';
	}

	return ob_get_clean();
}

/**
 * Obtiene datos para el panel del Comité.
 *
 * @param int $anio Año activo.
 * @return array Datos del panel.
 */
function gnf_get_comite_data( $anio ) {
	global $wpdb;

	$table_entries = $wpdb->prefix . 'gn_reto_entries';

	// Auto-filtrar por la DRE asignada al usuario comité (si no es admin).
	$user = wp_get_current_user();
	$user_region = 0;
	if ( ! current_user_can( 'manage_options' ) ) {
		$user_region = absint( get_user_meta( $user->ID, 'gnf_region', true ) );
		if ( ! $user_region ) {
			$user_region = absint( get_user_meta( $user->ID, 'region', true ) );
		}
	}

	// Filtros.
	$region_filter   = $user_region ? $user_region : ( isset( $_GET['region'] ) ? absint( $_GET['region'] ) : 0 );
	$circuito_filter = isset( $_GET['circuito'] ) ? sanitize_text_field( wp_unslash( $_GET['circuito'] ) ) : '';
	$estado_filter   = isset( $_GET['estado'] ) ? sanitize_key( $_GET['estado'] ) : '';
	$search          = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';

	// Solo centros con matrícula activa.
	$centros_con_matricula = gnf_get_centros_with_matricula( $anio );

	// Si hay filtro de región, obtener IDs de centros en esa región para acotar stats.
	$region_centro_ids = array();
	if ( $region_filter ) {
		$region_centro_ids = get_posts( array(
			'post_type'      => 'centro_educativo',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'post_status'    => 'publish',
			'tax_query'      => array( array(
				'taxonomy' => 'gn_region',
				'field'    => 'term_id',
				'terms'    => $region_filter,
			) ),
		) );
	}

	// Intersectar región con matrícula para stats y listado.
	$centros_filtrados = ! empty( $centros_con_matricula ) ? $centros_con_matricula : array();
	if ( ! empty( $region_centro_ids ) ) {
		$centros_filtrados = array_intersect( $centros_filtrados, $region_centro_ids );
	}

	$centro_clause = '';
	if ( ! empty( $centros_filtrados ) ) {
		$ids_str = implode( ',', array_map( 'absint', $centros_filtrados ) );
		$centro_clause = " AND centro_id IN ({$ids_str})";
	} elseif ( $region_filter || ! empty( $centros_con_matricula ) ) {
		$centro_clause = ' AND centro_id = 0';
	}

	// Estadísticas (filtradas por región si aplica).
	$stats = array(
		'total_centros'      => 0,
		'centros_completos'  => 0,
		'retos_aprobados'    => 0,
		'retos_pendientes'   => 0,
		'puntos_totales'     => 0,
	);

	$stats['total_centros'] = $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(DISTINCT centro_id) FROM {$table_entries} WHERE anio = %d{$centro_clause}",
		$anio
	) );

	$centros_con_participacion = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT DISTINCT centro_id FROM {$table_entries} WHERE anio = %d{$centro_clause}",
			$anio
		)
	);
	$centros_completos = 0;
	foreach ( (array) $centros_con_participacion as $centro_participante_id ) {
		$centro_participante_id = absint( $centro_participante_id );
		if ( ! $centro_participante_id ) {
			continue;
		}

		$retos_matriculados = gnf_get_centro_retos_seleccionados( $centro_participante_id, $anio );
		if ( empty( $retos_matriculados ) ) {
			continue;
		}

		$aprobados_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT reto_id) FROM {$table_entries} WHERE centro_id = %d AND anio = %d AND estado = 'aprobado'",
				$centro_participante_id,
				$anio
			)
		);
		if ( $aprobados_count >= count( $retos_matriculados ) ) {
			$centros_completos++;
		}
	}
	$stats['centros_completos'] = $centros_completos;

	$stats['retos_aprobados'] = $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$table_entries} WHERE estado = 'aprobado' AND anio = %d{$centro_clause}",
		$anio
	) );

	$stats['retos_pendientes'] = $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$table_entries} WHERE estado = 'enviado' AND anio = %d{$centro_clause}",
		$anio
	) );

	$stats['puntos_totales'] = $wpdb->get_var( $wpdb->prepare(
		"SELECT SUM(puntaje) FROM {$table_entries} WHERE estado = 'aprobado' AND anio = %d{$centro_clause}",
		$anio
	) ) ?: 0;

	// Obtener centros para la vista (solo con matrícula).
	$centros_args = array(
		'post_type'      => 'centro_educativo',
		'posts_per_page' => 20,
		'paged'          => isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'post_status'    => 'publish',
		'post__in'       => ! empty( $centros_con_matricula ) ? $centros_con_matricula : array( 0 ),
	);

	if ( $region_filter ) {
		$centros_args['tax_query'] = array(
			array(
				'taxonomy' => 'gn_region',
				'field'    => 'term_id',
				'terms'    => $region_filter,
			),
		);
	}

	if ( $circuito_filter ) {
		if ( ! isset( $centros_args['meta_query'] ) ) {
			$centros_args['meta_query'] = array();
		}
		$centros_args['meta_query'][] = array(
			'key'   => 'circuito',
			'value' => $circuito_filter,
		);
	}

	if ( $search ) {
		$centros_args['s'] = $search;
	}

	// Filtrar por estado de participación (intersectar con matrícula).
	if ( $estado_filter ) {
		$centro_ids_by_estado = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT centro_id FROM {$table_entries} WHERE estado = %s AND anio = %d",
			$estado_filter,
			$anio
		) );
		$centro_ids_by_estado = array_map( 'absint', (array) $centro_ids_by_estado );
		$intersect = array_intersect( $centro_ids_by_estado, $centros_con_matricula );
		if ( ! empty( $intersect ) ) {
			$centros_args['post__in'] = array_values( $intersect );
		} else {
			$centros_args['post__in'] = array( 0 ); // No results.
		}
	}

	$centros_query = new WP_Query( $centros_args );

	// Obtener entries por centro.
	$entries_by_centro = array();
	if ( $centros_query->have_posts() ) {
		$centro_ids = wp_list_pluck( $centros_query->posts, 'ID' );
		$entries = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table_entries} WHERE centro_id IN (" . implode( ',', array_map( 'intval', $centro_ids ) ) . ") AND anio = %d",
			$anio
		) );

		foreach ( $entries as $entry ) {
			if ( ! isset( $entries_by_centro[ $entry->centro_id ] ) ) {
				$entries_by_centro[ $entry->centro_id ] = array();
			}
			$entries_by_centro[ $entry->centro_id ][ $entry->reto_id ] = $entry;
		}
	}

	// Historial de validaciones recientes.
	$historial = $wpdb->get_results( $wpdb->prepare(
		"SELECT e.*, p.post_title as centro_nombre, r.post_title as reto_nombre, u.display_name as supervisor_nombre
		 FROM {$table_entries} e
		 LEFT JOIN {$wpdb->posts} p ON e.centro_id = p.ID
		 LEFT JOIN {$wpdb->posts} r ON e.reto_id = r.ID
		 LEFT JOIN {$wpdb->users} u ON e.supervisor_id = u.ID
		 WHERE e.anio = %d AND e.estado IN ('aprobado', 'correccion')
		 ORDER BY e.updated_at DESC
		 LIMIT 20",
		$anio
	) );

	// Regiones para filtro.
	$regiones = get_terms( array(
		'taxonomy'   => 'gn_region',
		'hide_empty' => false,
	) );

	// Obtener circuitos únicos (solo centros con matrícula, opcionalmente filtrados por región).
	$circuitos_args = array(
		'post_type'      => 'centro_educativo',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'post__in'       => ! empty( $centros_con_matricula ) ? $centros_con_matricula : array( 0 ),
	);
	if ( $region_filter ) {
		$circuitos_args['tax_query'] = array(
			array(
				'taxonomy' => 'gn_region',
				'field'    => 'term_id',
				'terms'    => $region_filter,
			),
		);
	}
	$circuitos_query = new WP_Query( $circuitos_args );
	$circuitos_disponibles = array();
	foreach ( $circuitos_query->posts as $cid ) {
		$circ = get_post_meta( $cid, 'circuito', true );
		if ( $circ && ! in_array( $circ, $circuitos_disponibles, true ) ) {
			$circuitos_disponibles[] = $circ;
		}
	}
	sort( $circuitos_disponibles );

	return array(
		'stats'                 => $stats,
		'centros'               => $centros_query,
		'entries_by_centro'     => $entries_by_centro,
		'historial'             => $historial,
		'regiones'              => $regiones,
		'anio'                  => $anio,
		'circuitos_disponibles' => $circuitos_disponibles,
		'user_region_locked'    => $user_region > 0,
		'filters'               => array(
			'region'   => $region_filter,
			'circuito' => $circuito_filter,
			'estado'   => $estado_filter,
			'search'   => $search,
		),
	);
}

/**
 * Maneja acciones de validación del Comité.
 */
function gnf_handle_comite_actions() {
	if ( ! isset( $_POST['gnf_comite_action'] ) || ! isset( $_POST['gnf_nonce'] ) ) {
		return;
	}

	if ( ! wp_verify_nonce( $_POST['gnf_nonce'], 'gnf_comite_action' ) ) {
		wp_die( 'Nonce inválido' );
	}

	$user = wp_get_current_user();
	if ( ! in_array( 'comite_bae', (array) $user->roles, true ) && ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Sin permisos' );
	}

	global $wpdb;
	$table = $wpdb->prefix . 'gn_reto_entries';

	$action    = sanitize_key( $_POST['gnf_comite_action'] );
	$centro_id = isset( $_POST['centro_id'] ) ? absint( $_POST['centro_id'] ) : 0;
	$nota      = isset( $_POST['nota'] ) ? sanitize_textarea_field( $_POST['nota'] ) : '';

	if ( ! $centro_id ) {
		return;
	}

	switch ( $action ) {
		case 'validar_centro':
			// Marcar centro como validado por el Comité.
			update_post_meta( $centro_id, 'gnf_comite_validado', 1 );
			update_post_meta( $centro_id, 'gnf_comite_validado_por', $user->ID );
			update_post_meta( $centro_id, 'gnf_comite_validado_fecha', current_time( 'mysql' ) );
			if ( $nota ) {
				update_post_meta( $centro_id, 'gnf_comite_nota', $nota );
			}

			// Notificar a docentes.
			$docentes = get_post_meta( $centro_id, 'docentes_asociados', true );
			if ( is_array( $docentes ) ) {
				foreach ( $docentes as $docente_id ) {
					gnf_insert_notification( $docente_id, 'validado', 'Tu centro ha sido validado por el Comité Bandera Azul.', 'centro', $centro_id );
				}
			}

			// Notificar a supervisores de la región.
			$region = get_post_meta( $centro_id, 'region', true );
			if ( empty( $region ) ) {
				$terms  = wp_get_post_terms( $centro_id, 'gn_region', array( 'fields' => 'ids' ) );
				$region = $terms ? $terms[0] : '';
			}
			if ( $region ) {
				$supervisores = gnf_get_supervisores_by_region( $region );
				$centro_title = get_the_title( $centro_id );
				foreach ( $supervisores as $sup ) {
					gnf_insert_notification( $sup->ID, 'validado', sprintf( 'Centro "%s" validado por el Comité Bandera Azul.', $centro_title ), 'centro', $centro_id );
				}
			}
			break;

		case 'observacion':
			// Agregar observación sin cambiar estados.
			if ( $nota ) {
				$observaciones = get_post_meta( $centro_id, 'gnf_comite_observaciones', true ) ?: array();
				$observaciones[] = array(
					'usuario'  => $user->ID,
					'fecha'    => current_time( 'mysql' ),
					'nota'     => $nota,
				);
				update_post_meta( $centro_id, 'gnf_comite_observaciones', $observaciones );
			}
			break;
	}

	wp_safe_redirect( remove_query_arg( array( 'gnf_comite_action' ) ) );
	exit;
}
add_action( 'init', 'gnf_handle_comite_actions' );

