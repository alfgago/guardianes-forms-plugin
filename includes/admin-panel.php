<?php
/**
 * Admin Panel - Frontend Dashboard para Administradores.
 *
 * @package Guardianes_Formularios
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renderiza el panel de administrador frontend.
 *
 * @return string HTML del panel.
 */
function gnf_render_admin_panel() {
	// Verificar que el usuario sea administrador.
	if ( ! current_user_can( 'manage_guardianes' ) && ! current_user_can( 'manage_options' ) ) {
		return '<div class="gnf-alert gnf-alert--error">No tienes permisos para acceder a este panel.</div>';
	}

	ob_start();

	// Obtener tab activo.
	$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'inicio';
	$anio_activo = gnf_get_active_year();
	$anio        = isset( $_GET['gnf_year'] ) ? absint( $_GET['gnf_year'] ) : $anio_activo;
	$anio        = gnf_normalize_year( $anio );

	// Obtener estadísticas.
	$stats = gnf_get_admin_stats( $anio );

	global $wpdb;
	$table_entries = $wpdb->prefix . 'gn_reto_entries';
	$years_available = $wpdb->get_col( "SELECT DISTINCT anio FROM {$table_entries} ORDER BY anio DESC" );
	if ( empty( $years_available ) ) {
		$years_available = array( $anio_activo );
	}
	if ( ! in_array( $anio_activo, $years_available, true ) ) {
		array_unshift( $years_available, $anio_activo );
	}

	// Obtener datos según el tab.
	$data = array(
		'stats'       => $stats,
		'active_tab'  => $active_tab,
		'current_url' => remove_query_arg( array( 'action', 'user_id', 'centro_id', '_wpnonce' ) ),
		'anio'        => $anio,
		'anio_activo' => $anio_activo,
		'years_available' => $years_available,
	);

	switch ( $active_tab ) {
		case 'usuarios':
			$data['pending_docentes']    = gnf_get_pending_users( 'docente' );
			$data['pending_supervisors'] = gnf_get_pending_users( 'supervisor' );
			$data['all_users']           = gnf_get_all_plugin_users();
			break;

		case 'centros':
			$data['centros'] = gnf_get_centros_for_admin( $anio );
			break;

		case 'retos':
			$data['retos'] = gnf_get_retos_for_admin( $anio );
			break;

		case 'reportes':
			$data['report_data'] = gnf_get_reports_data( $anio );
			break;

		case 'configuracion':
			// Obtener regiones con su estado de activación para pilotaje.
			$regiones_dre = get_terms( array(
				'taxonomy'   => 'gn_region',
				'hide_empty' => false,
				'orderby'    => 'name',
			) );
			$data['regiones_dre'] = array();
			if ( ! is_wp_error( $regiones_dre ) ) {
				foreach ( $regiones_dre as $reg ) {
					$activa = get_term_meta( $reg->term_id, 'gnf_dre_activa', true );
					$data['regiones_dre'][] = array(
						'term_id' => $reg->term_id,
						'name'    => $reg->name,
						'activa'  => ( '' === $activa ) ? true : (bool) $activa, // Por defecto activa.
					);
				}
			}
			break;
	}

	include GNF_PATH . 'templates/admin-dashboard.php';

	return ob_get_clean();
}

/**
 * Obtiene estadísticas generales del sistema.
 *
 * @return array Estadísticas.
 */
function gnf_get_admin_stats( $anio = null ) {
	global $wpdb;

	$table_entries = $wpdb->prefix . 'gn_reto_entries';
	$anio          = gnf_normalize_year( $anio );

	// Total centros activos.
	$total_centros = wp_count_posts( 'centro_educativo' );
	$centros_activos = $total_centros->publish ?? 0;

	// Total usuarios por rol.
	$docentes    = count( get_users( array( 'role' => 'docente', 'fields' => 'ID' ) ) );
	$supervisors = count( get_users( array( 'role' => 'supervisor', 'fields' => 'ID' ) ) );
	$comite      = count( get_users( array( 'role' => 'comite_bae', 'fields' => 'ID' ) ) );

	// Usuarios pendientes de aprobación.
	$pending_docentes = count( get_users( array(
		'role'       => 'docente',
		'meta_key'   => 'gnf_docente_status',
		'meta_value' => 'pendiente',
		'fields'     => 'ID',
	) ) );

	$pending_supervisors = count( get_users( array(
		'meta_key'   => 'gnf_supervisor_status',
		'meta_value' => 'pendiente',
		'fields'     => 'ID',
	) ) );

	// Retos por estado.
	$retos_enviados = $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$table_entries} WHERE estado = 'enviado' AND anio = %d",
		$anio
	) );

	$retos_aprobados = $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$table_entries} WHERE estado = 'aprobado' AND anio = %d",
		$anio
	) );

	$retos_correccion = $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$table_entries} WHERE estado = 'correccion' AND anio = %d",
		$anio
	) );

	// Puntos totales otorgados.
	$puntos_totales = $wpdb->get_var( $wpdb->prepare(
		"SELECT SUM(puntaje) FROM {$table_entries} WHERE estado = 'aprobado' AND anio = %d",
		$anio
	) );

	// Actividad reciente.
	$actividad_reciente = $wpdb->get_results( $wpdb->prepare(
		"SELECT e.*, p.post_title as centro_nombre, r.post_title as reto_nombre
		 FROM {$table_entries} e
		 LEFT JOIN {$wpdb->posts} p ON e.centro_id = p.ID
		 LEFT JOIN {$wpdb->posts} r ON e.reto_id = r.ID
		 WHERE e.anio = %d
		 ORDER BY e.updated_at DESC
		 LIMIT 10",
		$anio
	) );

	return array(
		'centros_activos'     => (int) $centros_activos,
		'total_docentes'      => (int) $docentes,
		'total_supervisors'   => (int) $supervisors,
		'total_comite'        => (int) $comite,
		'pending_docentes'    => (int) $pending_docentes,
		'pending_supervisors' => (int) $pending_supervisors,
		'retos_enviados'      => (int) $retos_enviados,
		'retos_aprobados'     => (int) $retos_aprobados,
		'retos_correccion'    => (int) $retos_correccion,
		'puntos_totales'      => (int) $puntos_totales,
		'actividad_reciente'  => $actividad_reciente,
		'anio'                => $anio,
	);
}

/**
 * Obtiene usuarios pendientes de aprobación.
 *
 * @param string $role Rol a buscar.
 * @return array Usuarios pendientes.
 */
function gnf_get_pending_users( $role = 'docente' ) {
	if ( 'supervisor' === $role ) {
		return get_users( array(
			'meta_key'   => 'gnf_supervisor_status',
			'meta_value' => 'pendiente',
			'orderby'    => 'registered',
			'order'      => 'DESC',
		) );
	}

	return get_users( array(
		'role'       => $role,
		'meta_key'   => 'gnf_docente_status',
		'meta_value' => 'pendiente',
		'orderby'    => 'registered',
		'order'      => 'DESC',
	) );
}

/**
 * Obtiene todos los usuarios del plugin.
 *
 * @return array Usuarios agrupados por rol.
 */
function gnf_get_all_plugin_users() {
	$docentes = get_users( array(
		'role'    => 'docente',
		'orderby' => 'display_name',
		'order'   => 'ASC',
	) );

	$supervisors = get_users( array(
		'role'    => 'supervisor',
		'orderby' => 'display_name',
		'order'   => 'ASC',
	) );

	$comite = get_users( array(
		'role'    => 'comite_bae',
		'orderby' => 'display_name',
		'order'   => 'ASC',
	) );

	return array(
		'docentes'    => $docentes,
		'supervisors' => $supervisors,
		'comite'      => $comite,
	);
}

/**
 * Obtiene centros educativos para el panel admin.
 * Solo incluye centros con matrícula activa para el año.
 *
 * @param int|null $anio Año para filtrar matrícula.
 * @return WP_Query Query de centros.
 */
function gnf_get_centros_for_admin( $anio = null ) {
	$paged  = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
	$region = isset( $_GET['region'] ) ? absint( $_GET['region'] ) : 0;
	$estado = isset( $_GET['estado'] ) ? sanitize_key( $_GET['estado'] ) : '';
	$search = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
	$anio   = gnf_normalize_year( $anio );

	$centros_con_matricula = gnf_get_centros_with_matricula( $anio );

	$args = array(
		'post_type'      => 'centro_educativo',
		'posts_per_page' => 20,
		'paged'          => $paged,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'post__in'       => ! empty( $centros_con_matricula ) ? $centros_con_matricula : array( 0 ),
	);

	if ( $region ) {
		$args['tax_query'] = array(
			array(
				'taxonomy' => 'gn_region',
				'field'    => 'term_id',
				'terms'    => $region,
			),
		);
	}

	if ( $estado ) {
		$args['meta_query'] = array(
			array(
				'key'   => 'estado_centro',
				'value' => $estado,
			),
		);
	}

	if ( $search ) {
		$args['s'] = $search;
	}

	return new WP_Query( $args );
}

/**
 * Obtiene retos para el panel admin.
 *
 * @return array Datos de retos.
 */
function gnf_get_retos_for_admin( $anio = null ) {
	global $wpdb;

	$table_entries = $wpdb->prefix . 'gn_reto_entries';
	$anio          = gnf_normalize_year( $anio );

	$retos = get_posts( array(
		'post_type'      => 'reto',
		'posts_per_page' => -1,
		'orderby'        => 'menu_order',
		'order'          => 'ASC',
	) );

	$result = array();
	foreach ( $retos as $reto ) {
		$stats = $wpdb->get_row( $wpdb->prepare(
			"SELECT 
				COUNT(*) as total,
				SUM(CASE WHEN estado = 'aprobado' THEN 1 ELSE 0 END) as aprobados,
				SUM(CASE WHEN estado = 'enviado' THEN 1 ELSE 0 END) as enviados,
				SUM(CASE WHEN estado = 'correccion' THEN 1 ELSE 0 END) as correccion,
				SUM(puntaje) as puntos_totales
			 FROM {$table_entries}
			 WHERE reto_id = %d AND anio = %d",
			$reto->ID,
			$anio
		) );

		$result[] = array(
			'reto'  => $reto,
			'stats' => $stats,
		);
	}

	return $result;
}

/**
 * Obtiene datos para reportes.
 *
 * @return array Datos de reportes.
 */
function gnf_get_reports_data( $anio = null ) {
	global $wpdb;

	$table_entries = $wpdb->prefix . 'gn_reto_entries';
	$anio          = gnf_normalize_year( $anio );

	// Resumen por región.
	$por_region = $wpdb->get_results( $wpdb->prepare(
		"SELECT t.name as region, t.term_id,
			COUNT(DISTINCT e.centro_id) as centros,
			SUM(CASE WHEN e.estado = 'aprobado' THEN 1 ELSE 0 END) as aprobados,
			SUM(CASE WHEN e.estado = 'enviado' THEN 1 ELSE 0 END) as pendientes,
			SUM(CASE WHEN e.estado = 'aprobado' THEN e.puntaje ELSE 0 END) as puntos
		 FROM {$table_entries} e
		 INNER JOIN {$wpdb->posts} p ON e.centro_id = p.ID
		 LEFT JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
		 LEFT JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = 'gn_region'
		 LEFT JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
		 WHERE e.anio = %d
		 GROUP BY t.term_id, t.name
		 ORDER BY t.name",
		$anio
	) );

	// Top 10 centros por puntaje.
	$top_centros = $wpdb->get_results( $wpdb->prepare(
		"SELECT c.ID, c.post_title,
			COALESCE(SUM(CASE WHEN e.estado = 'aprobado' THEN e.puntaje ELSE 0 END), 0) AS puntaje_total_anual
		 FROM {$wpdb->posts} c
		 LEFT JOIN {$table_entries} e ON e.centro_id = c.ID AND e.anio = %d
		 WHERE c.post_type = 'centro_educativo' AND c.post_status = 'publish'
		 GROUP BY c.ID, c.post_title
		 ORDER BY puntaje_total_anual DESC
		 LIMIT 10",
		$anio
	) );
	foreach ( $top_centros as $i => $centro ) {
		$top_centros[ $i ]->estrella_anual = function_exists( 'gnf_calcular_estrella_por_puntaje' ) ? gnf_calcular_estrella_por_puntaje( (int) $centro->puntaje_total_anual ) : 0;
	}

	// Estadísticas mensuales del año actual.
	$por_mes = $wpdb->get_results( $wpdb->prepare(
		"SELECT MONTH(updated_at) as mes,
			SUM(CASE WHEN estado = 'aprobado' THEN 1 ELSE 0 END) as aprobados,
			SUM(CASE WHEN estado = 'enviado' THEN 1 ELSE 0 END) as enviados
		 FROM {$table_entries}
		 WHERE anio = %d
		 GROUP BY MONTH(updated_at)
		 ORDER BY mes",
		$anio
	) );

	return array(
		'por_region'  => $por_region,
		'top_centros' => $top_centros,
		'por_mes'     => $por_mes,
		'anio'        => $anio,
	);
}

/**
 * Maneja acciones del panel admin (aprobar/rechazar usuarios, etc).
 * Usa POST via admin-post.php para evitar acciones destructivas vía GET.
 */
function gnf_handle_admin_panel_actions() {
	check_admin_referer( 'gnf_admin_action' );

	if ( ! current_user_can( 'manage_guardianes' ) && ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Sin permisos' );
	}

	$action  = sanitize_key( $_POST['admin_action'] ?? '' );
	$user_id = absint( $_POST['user_id'] ?? 0 );

	switch ( $action ) {
		case 'aprobar_docente':
			if ( $user_id ) {
				update_user_meta( $user_id, 'gnf_docente_status', 'activo' );
				gnf_insert_notification( $user_id, 'aprobado', 'Tu cuenta de docente ha sido aprobada.', 'user', $user_id );
			}
			break;

		case 'rechazar_docente':
			if ( $user_id ) {
				update_user_meta( $user_id, 'gnf_docente_status', 'rechazado' );
				gnf_insert_notification( $user_id, 'rechazado', 'Tu solicitud de registro ha sido rechazada.', 'user', $user_id );
			}
			break;

		case 'aprobar_supervisor':
			if ( $user_id ) {
				$user = get_user_by( 'ID', $user_id );
				if ( $user ) {
					$user->add_role( 'supervisor' );
					update_user_meta( $user_id, 'gnf_supervisor_status', 'activo' );
					gnf_insert_notification( $user_id, 'aprobado', 'Tu cuenta de supervisor ha sido aprobada.', 'user', $user_id );
				}
			}
			break;

		case 'rechazar_supervisor':
			if ( $user_id ) {
				update_user_meta( $user_id, 'gnf_supervisor_status', 'rechazado' );
				gnf_insert_notification( $user_id, 'rechazado', 'Tu solicitud de supervisor ha sido rechazada.', 'user', $user_id );
			}
			break;
	}

	wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
	exit;
}
add_action( 'admin_post_gnf_admin_panel_action', 'gnf_handle_admin_panel_actions' );

/**
 * AJAX: Obtener estadísticas actualizadas.
 */
function gnf_ajax_admin_stats() {
	check_ajax_referer( 'gnf_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_guardianes' ) && ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Sin permisos' );
	}

	$anio  = isset( $_POST['anio'] ) ? absint( $_POST['anio'] ) : gnf_get_active_year();
	$stats = gnf_get_admin_stats( $anio );
	wp_send_json_success( $stats );
}
add_action( 'wp_ajax_gnf_admin_stats', 'gnf_ajax_admin_stats' );

/**
 * AJAX: Activar/desactivar DRE para pilotaje.
 */
function gnf_ajax_toggle_dre() {
	check_ajax_referer( 'gnf_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_guardianes' ) && ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Sin permisos' );
	}

	$term_id = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
	$activa  = isset( $_POST['activa'] ) ? (bool) $_POST['activa'] : false;

	if ( ! $term_id ) {
		wp_send_json_error( 'ID de región inválido' );
	}

	$term = get_term( $term_id, 'gn_region' );
	if ( ! $term || is_wp_error( $term ) ) {
		wp_send_json_error( 'Región no encontrada' );
	}

	update_term_meta( $term_id, 'gnf_dre_activa', $activa ? 1 : 0 );

	wp_send_json_success( array(
		'term_id' => $term_id,
		'activa'  => $activa,
		'message' => $term->name . ( $activa ? ' activada' : ' desactivada' ) . ' para pilotaje.',
	) );
}
add_action( 'wp_ajax_gnf_toggle_dre', 'gnf_ajax_toggle_dre' );

