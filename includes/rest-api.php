<?php
/**
 * REST API endpoints for React panels.
 *
 * Namespace: gnf/v1
 * All endpoints use WP cookie auth via X-WP-Nonce header.
 * Business logic delegates to existing helper functions.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rest_api_init', 'gnf_register_rest_routes' );

function gnf_register_rest_routes() {
	$ns = 'gnf/v1';

	// ── Auth ────────────────────────────────────────────────────────
	register_rest_route(
		$ns,
		'/auth/login',
		array(
			'methods'             => 'POST',
			'callback'            => 'gnf_rest_auth_login',
			'permission_callback' => '__return_true',
		)
	);

	register_rest_route(
		$ns,
		'/auth/register/docente',
		array(
			'methods'             => 'POST',
			'callback'            => 'gnf_rest_auth_register_docente',
			'permission_callback' => '__return_true',
		)
	);

	register_rest_route(
		$ns,
		'/auth/register/supervisor',
		array(
			'methods'             => 'POST',
			'callback'            => 'gnf_rest_auth_register_supervisor',
			'permission_callback' => '__return_true',
		)
	);

	register_rest_route(
		$ns,
		'/auth/me',
		array(
			'methods'             => 'GET',
			'callback'            => 'gnf_rest_auth_me',
			'permission_callback' => 'is_user_logged_in',
		)
	);

	register_rest_route(
		$ns,
		'/auth/logout',
		array(
			'methods'             => 'POST',
			'callback'            => 'gnf_rest_auth_logout',
			'permission_callback' => 'is_user_logged_in',
		)
	);

	register_rest_route(
		$ns,
		'/auth/forgot-password',
		array(
			'methods'             => 'POST',
			'callback'            => 'gnf_rest_auth_forgot_password',
			'permission_callback' => '__return_true',
		)
	);

	register_rest_route(
		$ns,
		'/auth/reset-password',
		array(
			'methods'             => 'POST',
			'callback'            => 'gnf_rest_auth_reset_password',
			'permission_callback' => '__return_true',
		)
	);

	// ── Shared ──────────────────────────────────────────────────────
	register_rest_route(
		$ns,
		'/centros/search',
		array(
			'methods'             => 'GET',
			'callback'            => 'gnf_rest_centros_search',
			'permission_callback' => '__return_true',
		)
	);

	register_rest_route(
		$ns,
		'/centros/(?P<id>\d+)',
		array(
			array(
				'methods'             => 'GET',
				'callback'            => 'gnf_rest_centro_get',
				'permission_callback' => 'is_user_logged_in',
			),
			array(
				'methods'             => 'PUT',
				'callback'            => 'gnf_rest_centro_update',
				'permission_callback' => 'is_user_logged_in',
			),
		)
	);

	register_rest_route(
		$ns,
		'/regions',
		array(
			'methods'             => 'GET',
			'callback'            => 'gnf_rest_regions',
			'permission_callback' => '__return_true',
		)
	);

	register_rest_route(
		$ns,
		'/notifications',
		array(
			'methods'             => 'GET',
			'callback'            => 'gnf_rest_notifications_list',
			'permission_callback' => 'is_user_logged_in',
		)
	);

	register_rest_route(
		$ns,
		'/notifications/(?P<id>\d+)/read',
		array(
			'methods'             => 'PUT',
			'callback'            => 'gnf_rest_notification_mark_read',
			'permission_callback' => 'is_user_logged_in',
		)
	);

	register_rest_route(
		$ns,
		'/notifications/read-all',
		array(
			'methods'             => 'PUT',
			'callback'            => 'gnf_rest_notifications_mark_all_read',
			'permission_callback' => 'is_user_logged_in',
		)
	);

	// ── Docente ─────────────────────────────────────────────────────
	register_rest_route(
		$ns,
		'/docente/dashboard',
		array(
			'methods'             => 'GET',
			'callback'            => 'gnf_rest_docente_dashboard',
			'permission_callback' => 'gnf_rest_is_docente',
		)
	);

	register_rest_route(
		$ns,
		'/docente/retos',
		array(
			'methods'             => 'GET',
			'callback'            => 'gnf_rest_docente_retos',
			'permission_callback' => 'gnf_rest_is_docente',
		)
	);

	register_rest_route(
		$ns,
		'/docente/matricula',
		array(
			array(
				'methods'             => 'GET',
				'callback'            => 'gnf_rest_docente_matricula',
				'permission_callback' => 'gnf_rest_is_docente',
			),
			array(
				'methods'             => 'POST',
				'callback'            => 'gnf_rest_docente_matricula_save',
				'permission_callback' => 'gnf_rest_is_docente',
			),
		)
	);

	register_rest_route(
		$ns,
		'/docente/matricula/retos',
		array(
			'methods'             => 'POST',
			'callback'            => 'gnf_rest_docente_matricula_add_reto',
			'permission_callback' => 'gnf_rest_is_docente',
		)
	);

	register_rest_route(
		$ns,
		'/docente/matricula/retos/(?P<id>\d+)',
		array(
			'methods'             => 'DELETE',
			'callback'            => 'gnf_rest_docente_matricula_remove_reto',
			'permission_callback' => 'gnf_rest_is_docente',
		)
	);

	register_rest_route(
		$ns,
		'/docente/wizard',
		array(
			'methods'             => 'GET',
			'callback'            => 'gnf_rest_docente_wizard',
			'permission_callback' => 'gnf_rest_is_docente',
		)
	);

	register_rest_route(
		$ns,
		'/docente/retos/(?P<reto_id>\d+)/form-html',
		array(
			'methods'             => 'GET',
			'callback'            => 'gnf_rest_docente_form_html',
			'permission_callback' => 'gnf_rest_is_docente',
		)
	);

	register_rest_route(
		$ns,
		'/events/track',
		array(
			'methods'             => 'POST',
			'callback'            => 'gnf_rest_track_event',
			'permission_callback' => '__return_true',
		)
	);

	register_rest_route(
		$ns,
		'/docente/retos/(?P<reto_id>\d+)/autosave',
		array(
			'methods'             => 'POST',
			'callback'            => 'gnf_rest_docente_autosave_reto',
			'permission_callback' => 'gnf_rest_is_docente',
		)
	);

	register_rest_route(
		$ns,
		'/docente/retos/(?P<reto_id>\d+)/remove-evidence',
		array(
			'methods'             => 'POST',
			'callback'            => 'gnf_rest_docente_remove_evidence',
			'permission_callback' => 'gnf_rest_is_docente',
		)
	);

	register_rest_route(
		$ns,
		'/docente/retos/(?P<reto_id>\d+)/finalize',
		array(
			'methods'             => 'POST',
			'callback'            => 'gnf_rest_docente_finalize_reto',
			'permission_callback' => 'gnf_rest_is_docente',
		)
	);

	register_rest_route(
		$ns,
		'/docente/retos/(?P<reto_id>\d+)/reopen',
		array(
			'methods'             => 'POST',
			'callback'            => 'gnf_rest_docente_reopen_reto',
			'permission_callback' => 'gnf_rest_is_docente',
		)
	);

	register_rest_route(
		$ns,
		'/docente/submit',
		array(
			'methods'             => 'POST',
			'callback'            => 'gnf_rest_docente_submit',
			'permission_callback' => 'gnf_rest_is_docente',
		)
	);

	// ── Supervisor ──────────────────────────────────────────────────
	register_rest_route(
		$ns,
		'/supervisor/dashboard',
		array(
			'methods'             => 'GET',
			'callback'            => 'gnf_rest_supervisor_dashboard',
			'permission_callback' => 'gnf_rest_is_supervisor',
		)
	);

	register_rest_route(
		$ns,
		'/supervisor/centros',
		array(
			'methods'             => 'GET',
			'callback'            => 'gnf_rest_supervisor_centros',
			'permission_callback' => 'gnf_rest_is_supervisor',
		)
	);

	register_rest_route(
		$ns,
		'/supervisor/centros/(?P<id>\d+)',
		array(
			'methods'             => 'GET',
			'callback'            => 'gnf_rest_supervisor_centro_detail',
			'permission_callback' => 'gnf_rest_is_supervisor',
		)
	);

	register_rest_route(
		$ns,
		'/supervisor/evidence/(?P<entry_id>\d+)/(?P<evidence_index>\d+)',
		array(
			'methods'             => 'POST',
			'callback'            => 'gnf_rest_supervisor_review_evidence',
			'permission_callback' => 'gnf_rest_is_supervisor',
		)
	);

	// ── Admin ───────────────────────────────────────────────────────
	register_rest_route(
		$ns,
		'/admin/stats',
		array(
			'methods'             => 'GET',
			'callback'            => 'gnf_rest_admin_stats',
			'permission_callback' => 'gnf_rest_is_admin',
		)
	);

	register_rest_route(
		$ns,
		'/admin/users',
		array(
			'methods'             => 'GET',
			'callback'            => 'gnf_rest_admin_users',
			'permission_callback' => 'gnf_rest_is_admin',
		)
	);

	register_rest_route(
		$ns,
		'/admin/users/(?P<id>\d+)',
		array(
			'methods'             => 'PUT',
			'callback'            => 'gnf_rest_admin_update_user',
			'permission_callback' => 'gnf_rest_is_admin',
		)
	);

	register_rest_route(
		$ns,
		'/admin/users/pending',
		array(
			'methods'             => 'GET',
			'callback'            => 'gnf_rest_admin_pending_users',
			'permission_callback' => 'gnf_rest_is_admin',
		)
	);

	register_rest_route(
		$ns,
		'/admin/users/(?P<id>\d+)/approve',
		array(
			'methods'             => 'POST',
			'callback'            => 'gnf_rest_admin_approve_user',
			'permission_callback' => 'gnf_rest_is_admin',
		)
	);

	register_rest_route(
		$ns,
		'/admin/users/(?P<id>\d+)/reject',
		array(
			'methods'             => 'POST',
			'callback'            => 'gnf_rest_admin_reject_user',
			'permission_callback' => 'gnf_rest_is_admin',
		)
	);

	register_rest_route(
		$ns,
		'/admin/centros',
		array(
			array(
				'methods'             => 'GET',
				'callback'            => 'gnf_rest_admin_centros',
				'permission_callback' => 'gnf_rest_is_admin',
			),
			array(
				'methods'             => 'POST',
				'callback'            => 'gnf_rest_admin_create_centro',
				'permission_callback' => 'gnf_rest_is_admin',
			),
		)
	);

	register_rest_route(
		$ns,
		'/admin/retos',
		array(
			'methods'             => 'GET',
			'callback'            => 'gnf_rest_admin_retos',
			'permission_callback' => 'gnf_rest_is_admin',
		)
	);

	register_rest_route(
		$ns,
		'/admin/reports',
		array(
			'methods'             => 'GET',
			'callback'            => 'gnf_rest_admin_reports',
			'permission_callback' => 'gnf_rest_is_admin',
		)
	);

	register_rest_route(
		$ns,
		'/admin/dre',
		array(
			'methods'             => 'GET',
			'callback'            => 'gnf_rest_admin_dre_list',
			'permission_callback' => 'gnf_rest_is_admin',
		)
	);

	register_rest_route(
		$ns,
		'/admin/dre/(?P<id>\d+)/toggle',
		array(
			'methods'             => 'POST',
			'callback'            => 'gnf_rest_admin_dre_toggle',
			'permission_callback' => 'gnf_rest_is_admin',
		)
	);

	register_rest_route(
		$ns,
		'/admin/seeders/retos',
		array(
			'methods'             => 'POST',
			'callback'            => 'gnf_rest_admin_seed_retos',
			'permission_callback' => 'gnf_rest_is_admin',
		)
	);

	register_rest_route(
		$ns,
		'/admin/audit-logs',
		array(
			'methods'             => 'GET',
			'callback'            => 'gnf_rest_admin_audit_logs',
			'permission_callback' => 'gnf_rest_is_admin',
		)
	);

	// ── Comité BAE ──────────────────────────────────────────────────
	register_rest_route(
		$ns,
		'/comite/dashboard',
		array(
			'methods'             => 'GET',
			'callback'            => 'gnf_rest_comite_dashboard',
			'permission_callback' => 'gnf_rest_is_comite',
		)
	);

	register_rest_route(
		$ns,
		'/comite/centros',
		array(
			'methods'             => 'GET',
			'callback'            => 'gnf_rest_comite_centros',
			'permission_callback' => 'gnf_rest_is_comite',
		)
	);

	register_rest_route(
		$ns,
		'/comite/centros/(?P<id>\d+)',
		array(
			'methods'             => 'GET',
			'callback'            => 'gnf_rest_comite_centro_detail',
			'permission_callback' => 'gnf_rest_is_comite',
		)
	);
}

// ═══════════════════════════════════════════════════════════════════════
// Permission callbacks
// ═══════════════════════════════════════════════════════════════════════

function gnf_rest_is_docente() {
	return is_user_logged_in() && gnf_user_has_role( wp_get_current_user(), 'docente' );
}

function gnf_rest_is_supervisor() {
	$user = wp_get_current_user();
	if ( ! is_user_logged_in() ) {
		return false;
	}

	if ( function_exists( 'gnf_user_can_access_panel' ) && gnf_user_can_access_panel( $user, 'panel-admin' ) ) {
		return true;
	}

	return function_exists( 'gnf_is_supervisor_account_active' )
		? gnf_is_supervisor_account_active( $user )
		: ( ( gnf_user_has_role( $user, 'supervisor' ) || gnf_user_has_role( $user, 'comite_bae' ) ) && 'activo' === gnf_get_supervisor_estado( $user->ID ) );
}

function gnf_rest_is_admin() {
	return is_user_logged_in() && ( function_exists( 'gnf_user_can_access_panel' ) ? gnf_user_can_access_panel( wp_get_current_user(), 'panel-admin' ) : ( current_user_can( 'manage_guardianes' ) || current_user_can( 'manage_options' ) ) );
}

function gnf_rest_is_comite() {
	$user = wp_get_current_user();
	if ( ! is_user_logged_in() ) {
		return false;
	}

	if ( function_exists( 'gnf_user_can_access_panel' ) && gnf_user_can_access_panel( $user, 'panel-admin' ) ) {
		return true;
	}

	return function_exists( 'gnf_is_supervisor_account_active' )
		? gnf_is_supervisor_account_active( $user )
		: ( gnf_user_has_role( $user, 'comite_bae' ) && 'activo' === gnf_get_supervisor_estado( $user->ID ) );
}

/**
 * URL de panel por defecto para respuestas auth REST.
 *
 * @param WP_User $user Usuario autenticado.
 * @return string
 */
function gnf_rest_get_default_redirect( $user ) {
	if ( function_exists( 'gnf_get_default_panel_url' ) ) {
		return gnf_get_default_panel_url( $user );
	}

	if ( gnf_user_has_role( $user, 'administrator' ) ) {
		return admin_url();
	}
	if ( gnf_user_has_role( $user, 'comite_bae' ) ) {
		return home_url( '/panel-supervisor/' );
	}
	if ( gnf_user_has_role( $user, 'supervisor' ) ) {
		return home_url( '/panel-supervisor/' );
	}
	if ( gnf_user_has_role( $user, 'docente' ) ) {
		return home_url( '/panel-docente/' );
	}

	return home_url( '/panel-docente/' );
}

/**
 * Actualiza campo ACF si existe, con fallback a post meta.
 *
 * @param string $field Campo/meta.
 * @param mixed  $value Valor.
 * @param int    $post_id Post ID.
 * @return void
 */
function gnf_rest_update_post_field( $field, $value, $post_id ) {
	if ( function_exists( 'update_field' ) ) {
		update_field( $field, $value, $post_id );
		return;
	}

	update_post_meta( $post_id, $field, $value );
}

/**
 * Obtiene el termino de region principal de un centro.
 *
 * @param int $centro_id ID del centro.
 * @return WP_Term|null
 */
function gnf_rest_get_centro_region_term( $centro_id ) {
	$terms = wp_get_object_terms( $centro_id, 'gn_region' );
	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return null;
	}

	return $terms[0];
}

/**
 * DTO base de centro para React.
 *
 * @param int $centro_id ID del centro.
 * @return array
 */
function gnf_rest_build_centro_base( $centro_id ) {
	$post        = get_post( $centro_id );
	$region_term = gnf_rest_get_centro_region_term( $centro_id );

	return array(
		'id'              => (int) $centro_id,
		'nombre'          => $post ? $post->post_title : '',
		'codigoMep'       => (string) ( get_field( 'codigo_mep', $centro_id ) ?: get_post_meta( $centro_id, 'codigo_mep', true ) ?: '' ),
		'regionId'        => $region_term ? (int) $region_term->term_id : 0,
		'regionName'      => $region_term ? $region_term->name : '',
		'circuito'        => (string) get_post_meta( $centro_id, 'circuito', true ),
		'direccion'       => (string) ( get_field( 'direccion', $centro_id ) ?: get_post_meta( $centro_id, 'direccion', true ) ?: '' ),
		'provincia'       => (string) ( get_field( 'provincia', $centro_id ) ?: get_post_meta( $centro_id, 'provincia', true ) ?: '' ),
		'canton'          => (string) ( get_field( 'canton', $centro_id ) ?: get_post_meta( $centro_id, 'canton', true ) ?: '' ),
		'nivelEducativo'  => (string) ( get_field( 'nivel_educativo', $centro_id ) ?: get_post_meta( $centro_id, 'nivel_educativo', true ) ?: '' ),
		'dependencia'     => (string) ( get_field( 'dependencia', $centro_id ) ?: get_post_meta( $centro_id, 'dependencia', true ) ?: '' ),
		'jornada'         => (string) ( get_field( 'jornada', $centro_id ) ?: get_post_meta( $centro_id, 'jornada', true ) ?: '' ),
		'tipologia'       => (string) ( get_field( 'tipologia', $centro_id ) ?: get_post_meta( $centro_id, 'tipologia', true ) ?: '' ),
		'tipoCentroEducativo' => (string) ( get_field( 'tipo_centro_educativo', $centro_id ) ?: get_post_meta( $centro_id, 'tipo_centro_educativo', true ) ?: '' ),
	);
}

/**
 * DTO anual del centro para React.
 *
 * @param int $centro_id ID del centro.
 * @param int $anio      Año.
 * @return array
 */
function gnf_rest_build_centro_annual( $centro_id, $anio ) {
	$anio              = gnf_normalize_year( $anio );
	$retos_seleccionad = array_map( 'absint', (array) gnf_get_centro_retos_seleccionados( $centro_id, $anio ) );
	return array(
		'centroId'          => (int) $centro_id,
		'anio'              => (int) $anio,
		'metaEstrellas'     => (int) gnf_get_centro_meta_estrellas( $centro_id, $anio ),
		'puntajeTotal'      => (int) gnf_get_centro_puntaje_total( $centro_id, $anio ),
		'estrellaFinal'     => (int) gnf_get_centro_estrella_final( $centro_id, $anio ),
		'retosSeleccionados'=> $retos_seleccionad,
		'comiteEstudiantes' => (int) gnf_get_centro_anual_field( $centro_id, 'comite_estudiantes', $anio, 0 ),
		'matriculaEstado'   => (string) gnf_get_centro_anual_field( $centro_id, 'estado_matricula', $anio, '' ),
	);
}

/**
 * Devuelve conteos de entries por centro y estado.
 *
 * @param int[] $centro_ids IDs de centro.
 * @param int   $anio       Año.
 * @return array
 */
function gnf_rest_get_entry_counts_by_centro( $centro_ids, $anio ) {
	global $wpdb;

	$centro_ids = array_values( array_filter( array_map( 'absint', (array) $centro_ids ) ) );
	if ( empty( $centro_ids ) ) {
		return array();
	}

	$table        = $wpdb->prefix . 'gn_reto_entries';
	$placeholders = implode( ',', array_fill( 0, count( $centro_ids ), '%d' ) );
	$rows         = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT centro_id, estado, COUNT(*) as cnt FROM {$table} WHERE anio = %d AND centro_id IN ({$placeholders}) GROUP BY centro_id, estado",
			array_merge( array( (int) $anio ), $centro_ids )
		)
	);

	$counts = array();
	foreach ( $rows as $row ) {
		$centro_id = (int) $row->centro_id;
		if ( ! isset( $counts[ $centro_id ] ) ) {
			$counts[ $centro_id ] = array(
				'aprobados'  => 0,
				'enviados'   => 0,
				'correccion' => 0,
				'enProgreso' => 0,
			);
		}

		$estado = (string) $row->estado;
		$cantidad = (int) $row->cnt;
		if ( 'aprobado' === $estado ) {
			$counts[ $centro_id ]['aprobados'] += $cantidad;
		} elseif ( 'enviado' === $estado ) {
			$counts[ $centro_id ]['enviados'] += $cantidad;
		} elseif ( 'correccion' === $estado ) {
			$counts[ $centro_id ]['correccion'] += $cantidad;
		} elseif ( in_array( $estado, array( 'en_progreso', 'no_iniciado', 'completo' ), true ) ) {
			$counts[ $centro_id ]['enProgreso'] += $cantidad;
		}
	}

	return $counts;
}

/**
 * Construye el payload CentroWithStats esperado por React.
 *
 * @param int   $centro_id ID del centro.
 * @param int   $anio      Año.
 * @param array $counts    Conteos indexados por centro.
 * @param array $extra     Campos adicionales.
 * @return array
 */
function gnf_rest_build_centro_with_stats( $centro_id, $anio, $counts = array(), $extra = array() ) {
	$base   = gnf_rest_build_centro_base( $centro_id );
	$annual = gnf_rest_build_centro_annual( $centro_id, $anio );
	$stats  = $counts[ $centro_id ] ?? array();

	// Count evidence states across all entries for this centro.
	global $wpdb;
	$ev_table = $wpdb->prefix . 'gn_reto_entries';
	$ev_rows  = $wpdb->get_col( $wpdb->prepare(
		"SELECT evidencias FROM {$ev_table} WHERE centro_id = %d AND anio = %d AND evidencias IS NOT NULL AND evidencias != ''",
		$centro_id,
		$anio
	) );
	$ev_pendientes = 0;
	$ev_aprobadas  = 0;
	$ev_rechazadas = 0;
	$ev_total      = 0;
	foreach ( $ev_rows as $ev_json ) {
		$evs = json_decode( $ev_json, true );
		foreach ( (array) $evs as $ev ) {
			if ( ! empty( $ev['replaced'] ) ) continue;
			$puntos = $ev['puntos'] ?? null;
			if ( null === $puntos ) continue;
			$ev_total++;
			$est = $ev['estado'] ?? 'pendiente';
			if ( 'aprobada' === $est ) $ev_aprobadas++;
			elseif ( 'rechazada' === $est ) $ev_rechazadas++;
			else $ev_pendientes++;
		}
	}

	return array_merge(
		$base,
		array(
			'annual'         => $annual,
			'retosCount'     => count( (array) $annual['retosSeleccionados'] ),
			'aprobados'      => (int) ( $stats['aprobados'] ?? 0 ),
			'enviados'       => (int) ( $stats['enviados'] ?? 0 ),
			'correccion'     => (int) ( $stats['correccion'] ?? 0 ),
			'enProgreso'     => (int) ( $stats['enProgreso'] ?? 0 ),
			'evPendientes'   => $ev_pendientes,
			'evAprobadas'    => $ev_aprobadas,
			'evRechazadas'   => $ev_rechazadas,
			'evTotal'        => $ev_total,
		),
		$extra
	);
}

/**
 * Obtiene la revision del comité para un año.
 *
 * @param int $centro_id ID del centro.
 * @param int $anio      Año.
 * @return array
 */
function gnf_rest_get_comite_review( $centro_id, $anio ) {
	$reviews = get_post_meta( $centro_id, 'gnf_comite_reviews', true );
	$reviews = is_array( $reviews ) ? $reviews : array();

	foreach ( $reviews as $review ) {
		if ( (int) ( $review['anio'] ?? 0 ) === (int) $anio ) {
			return $review;
		}
	}

	return array(
		'anio'      => (int) $anio,
		'status'    => '',
		'notes'     => '',
		'userId'    => 0,
		'userName'  => '',
		'updatedAt' => '',
	);
}

/**
 * Persiste la revision anual del comité.
 *
 * @param int   $centro_id ID del centro.
 * @param int   $anio      Año.
 * @param array $review    Review normalizada.
 * @return void
 */
function gnf_rest_save_comite_review( $centro_id, $anio, $review ) {
	$reviews = get_post_meta( $centro_id, 'gnf_comite_reviews', true );
	$reviews = is_array( $reviews ) ? $reviews : array();
	$saved   = false;

	foreach ( $reviews as $index => $row ) {
		if ( (int) ( $row['anio'] ?? 0 ) === (int) $anio ) {
			$reviews[ $index ] = $review;
			$saved             = true;
			break;
		}
	}

	if ( ! $saved ) {
		$reviews[] = $review;
	}

	update_post_meta( $centro_id, 'gnf_comite_reviews', array_values( $reviews ) );
}

/**
 * Agrega una entrada al historial anual del comité.
 *
 * @param int    $centro_id ID del centro.
 * @param int    $anio      Año.
 * @param string $action    Acción.
 * @param string $details   Detalle visible en UI.
 * @param WP_User $user     Usuario que ejecuta la acción.
 * @return void
 */
function gnf_rest_append_comite_historial( $centro_id, $anio, $action, $details, $user ) {
	$history   = get_post_meta( $centro_id, 'gnf_comite_historial', true );
	$history   = is_array( $history ) ? $history : array();
	$centro    = get_post( $centro_id );
	$history[] = array(
		'id'           => wp_generate_uuid4(),
		'anio'         => (int) $anio,
		'centroId'     => (int) $centro_id,
		'centroNombre' => $centro ? $centro->post_title : '',
		'action'       => (string) $action,
		'details'      => (string) $details,
		'userId'       => (int) $user->ID,
		'userName'     => (string) $user->display_name,
		'createdAt'    => current_time( 'mysql' ),
	);

	update_post_meta( $centro_id, 'gnf_comite_historial', array_values( $history ) );
}

/**
 * Obtiene observaciones anuales del comité.
 *
 * @param int      $centro_id ID del centro.
 * @param int|null $anio      Año opcional.
 * @return array
 */
function gnf_rest_get_comite_observations( $centro_id, $anio = null ) {
	$items = get_post_meta( $centro_id, 'gnf_comite_observaciones', true );
	$items = is_array( $items ) ? $items : array();

	if ( null === $anio ) {
		return $items;
	}

	return array_values(
		array_filter(
			$items,
			static function ( $item ) use ( $anio ) {
				return (int) ( $item['anio'] ?? 0 ) === (int) $anio;
			}
		)
	);
}

/**
 * Busca un centro existente por titulo exacto.
 *
 * @param string $title Titulo del centro.
 * @return int
 */
function gnf_rest_find_existing_centro_by_title( $title ) {
	$title = trim( (string) $title );
	if ( '' === $title ) {
		return 0;
	}

	$posts = get_posts(
		array(
			'post_type'      => 'centro_educativo',
			'post_status'    => array( 'publish', 'pending', 'draft' ),
			'title'          => $title,
			'posts_per_page' => 1,
			'fields'         => 'ids',
		)
	);

	return ! empty( $posts ) ? (int) $posts[0] : 0;
}

/**
 * Error consistente para centros ya tomados por otro docente.
 *
 * @param int $centro_id ID del centro.
 * @return WP_Error
 */
function gnf_rest_get_centro_claimed_error( $centro_id = 0 ) {
	$centro_id            = absint( $centro_id );
	$correo_institucional = '';

	if ( $centro_id ) {
		$correo_institucional = (string) ( get_field( 'correo_institucional', $centro_id ) ?: get_post_meta( $centro_id, 'correo_institucional', true ) ?: '' );
	}

	$message = 'Este centro educativo ya tiene una cuenta registrada. Si necesitas cambios, solicita apoyo al administrador.';
	if ( '' !== $correo_institucional ) {
		$message = sprintf(
			'Este centro educativo ya esta siendo utilizado con el correo %s. Si necesitas cambios, solicita apoyo al administrador.',
			$correo_institucional
		);
	}

	return new WP_Error(
		'centro_taken',
		$message,
		array( 'status' => 409 )
	);
}

/**
 * Crea un centro pendiente desde el payload de registro React.
 *
 * @param WP_REST_Request $request Request.
 * @return int|WP_Error
 */
function gnf_rest_create_pending_centro_from_request( WP_REST_Request $request ) {
	$nombre = sanitize_text_field( (string) $request->get_param( 'centroNombre' ) );
	$codigo = sanitize_text_field( (string) $request->get_param( 'centroCodigoMep' ) );
	if ( '' === $nombre ) {
		return new WP_Error( 'invalid_centro', 'Debes indicar el nombre del centro.', array( 'status' => 400 ) );
	}

	$existing_centro_id = 0;
	if ( $codigo && function_exists( 'gnf_find_centro_by_codigo' ) ) {
		$existing_centro_id = (int) gnf_find_centro_by_codigo( $codigo );
	}
	if ( ! $existing_centro_id ) {
		$existing_centro_id = gnf_rest_find_existing_centro_by_title( $nombre );
	}

	if ( $existing_centro_id ) {
		if ( function_exists( 'gnf_is_centro_claimed_by_other_docente' ) && gnf_is_centro_claimed_by_other_docente( $existing_centro_id ) ) {
			return gnf_rest_get_centro_claimed_error( $existing_centro_id );
		}

		return $existing_centro_id;
	}

	$post_id = wp_insert_post(
		array(
			'post_title'  => $nombre,
			'post_type'   => 'centro_educativo',
			'post_status' => 'pending',
		),
		true
	);

	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}

	gnf_rest_update_post_field( 'codigo_mep', $codigo, $post_id );
	gnf_rest_update_post_field( 'direccion', sanitize_text_field( (string) $request->get_param( 'centroDireccion' ) ), $post_id );
	gnf_rest_update_post_field( 'provincia', sanitize_text_field( (string) $request->get_param( 'centroProvincia' ) ), $post_id );
	gnf_rest_update_post_field( 'canton', sanitize_text_field( (string) $request->get_param( 'centroCanton' ) ), $post_id );
	gnf_rest_update_post_field( 'nivel_educativo', sanitize_text_field( (string) $request->get_param( 'centroNivelEducativo' ) ), $post_id );
	gnf_rest_update_post_field( 'dependencia', sanitize_text_field( (string) $request->get_param( 'centroDependencia' ) ), $post_id );
	update_post_meta( $post_id, 'estado_centro', 'pendiente_de_revision_admin' );

	$region_id = absint( $request->get_param( 'regionId' ) ?: $request->get_param( 'centroRegionId' ) );
	if ( $region_id ) {
		wp_set_object_terms( $post_id, array( $region_id ), 'gn_region', false );
		update_post_meta( $post_id, 'region', $region_id );
	}

	return (int) $post_id;
}

// ═══════════════════════════════════════════════════════════════════════
// Auth endpoints
// ═══════════════════════════════════════════════════════════════════════

/**
 * Serializa el usuario autenticado para el frontend React.
 *
 * @param WP_User $user Usuario autenticado.
 * @return array<string,mixed>
 */
function gnf_rest_build_auth_user( $user ) {
	$data = array(
		'id'    => $user->ID,
		'name'  => $user->display_name,
		'email' => $user->user_email,
		'roles' => array_values( $user->roles ),
	);

	if ( gnf_user_has_role( $user, 'docente' ) ) {
		$data['centroId'] = gnf_get_centro_for_docente( $user->ID );
		$data['estado']   = gnf_get_docente_estado( $user->ID );
	} elseif ( gnf_user_has_role( $user, 'supervisor' ) || gnf_user_has_role( $user, 'comite_bae' ) ) {
		$region_ids = function_exists( 'gnf_get_user_regions' )
			? array_values( array_map( 'intval', gnf_get_user_regions( $user->ID ) ) )
			: array_values( array_filter( array( (int) gnf_get_user_region( $user->ID ) ) ) );

		$data['regionId']    = ! empty( $region_ids ) ? (int) $region_ids[0] : 0;
		$data['regionIds']   = $region_ids;
		$data['regionNames'] = function_exists( 'gnf_get_user_region_names' )
			? array_values( array_map( 'strval', gnf_get_user_region_names( $user->ID ) ) )
			: array();
		$data['estado']      = gnf_get_supervisor_estado( $user->ID );
	}

	return $data;
}

function gnf_rest_auth_login( WP_REST_Request $request ) {
	$username = sanitize_text_field( $request->get_param( 'username' ) );
	$password = $request->get_param( 'password' );

	$creds = array(
		'user_login'    => $username,
		'user_password' => $password,
		'remember'      => true,
	);

	$user = wp_signon( $creds, is_ssl() );

	if ( is_wp_error( $user ) ) {
		return new WP_Error(
			'login_failed',
			'Los datos de acceso no son validos. Revisa tu correo y contrasena.',
			array( 'status' => 401 )
		);
	}

	wp_set_current_user( $user->ID );
	if ( function_exists( 'gnf_get_primary_panel_slug' ) && '' === gnf_get_primary_panel_slug( $user ) ) {
		if ( function_exists( 'gnf_log_panel_access_context' ) ) {
			gnf_log_panel_access_context(
				'rest_auth_login_denied',
				$user,
				array(
					'route' => '/gnf/v1/auth/login',
				)
			);
		}

		wp_logout();

		return new WP_Error(
			'no_panel_access',
			'La cuenta se autentico, pero no tiene un rol o permisos validos para un panel en este sitio.',
			array( 'status' => 403 )
		);
	}

	gnf_log_audit_event(
		'auth_login',
		array(
			'actor_user_id' => $user->ID,
			'message'       => 'Inicio de sesion desde panel React.',
		)
	);

	return array(
		'user'        => gnf_rest_build_auth_user( $user ),
		'redirectUrl' => gnf_rest_get_default_redirect( $user ),
	);
}

function gnf_rest_auth_register_docente( WP_REST_Request $request ) {
	$nombre   = sanitize_text_field( $request->get_param( 'nombre' ) );
	$email    = sanitize_email( $request->get_param( 'email' ) );
	$password = $request->get_param( 'password' );
	$centro_id = absint( $request->get_param( 'centroId' ) );

	if ( '' === $nombre || '' === $email || '' === (string) $password ) {
		return new WP_Error( 'missing_fields', 'Nombre, correo y contraseña son obligatorios.', array( 'status' => 400 ) );
	}

	if ( email_exists( $email ) ) {
		return new WP_Error( 'email_exists', 'Este correo ya está registrado.', array( 'status' => 400 ) );
	}

	if ( $centro_id ) {
		if ( 'centro_educativo' !== get_post_type( $centro_id ) ) {
			return new WP_Error( 'invalid_centro', 'El centro educativo seleccionado no es valido.', array( 'status' => 400 ) );
		}

		if ( gnf_is_centro_claimed_by_other_docente( $centro_id ) ) {
			return gnf_rest_get_centro_claimed_error( $centro_id );
		}
	}

	$user_id = wp_create_user( $email, $password, $email );
	if ( is_wp_error( $user_id ) ) {
		return $user_id;
	}

	if ( ! function_exists( 'gnf_set_user_role_strict' ) || ! gnf_set_user_role_strict( $user_id, 'docente', 'rest_auth_register_docente' ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $user_id );
		return new WP_Error( 'role_assignment_failed', 'No se pudo asignar el rol de docente.', array( 'status' => 500 ) );
	}
	wp_update_user( array( 'ID' => $user_id, 'display_name' => $nombre ) );

	update_user_meta( $user_id, 'gnf_docente_status', 'activo' );
	update_user_meta( $user_id, 'gnf_identificacion', sanitize_text_field( (string) $request->get_param( 'identificacion' ) ) );
	update_user_meta( $user_id, 'gnf_telefono', sanitize_text_field( (string) $request->get_param( 'telefono' ) ) );
	update_user_meta( $user_id, 'gnf_cargo', sanitize_text_field( (string) $request->get_param( 'cargo' ) ) );

	if ( ! $centro_id && $request->get_param( 'centroNombre' ) ) {
		$centro_id = gnf_rest_create_pending_centro_from_request( $request );
		if ( is_wp_error( $centro_id ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
			wp_delete_user( $user_id );
			return $centro_id;
		}
	}

	if ( $centro_id ) {
		if ( gnf_is_centro_claimed_by_other_docente( $centro_id, $user_id ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
			wp_delete_user( $user_id );
			return gnf_rest_get_centro_claimed_error( $centro_id );
		}
		gnf_sync_docente_centro_assignment( $user_id, $centro_id, array( 'sync_correo_institucional' => true ) );
	}

	if ( function_exists( 'gnf_approve_docente' ) ) {
		gnf_approve_docente( $user_id );
	}

	if ( function_exists( 'gnf_notify_admins' ) ) {
		gnf_notify_admins( 'registro', sprintf( 'Nuevo docente registrado: %s (%s)', $nombre, $email ), 'user', $user_id );
		if ( $centro_id ) {
			gnf_notify_admins( 'docente_solicita_acceso', 'Nuevo docente solicita unirse al centro ' . get_the_title( $centro_id ), 'centro', $centro_id );
		}
	}

	if ( $centro_id ) {
		$docentes_activos = (array) get_field( 'docentes_asociados', $centro_id );
		foreach ( $docentes_activos as $docente_id ) {
			if ( (int) $docente_id === (int) $user_id ) {
				continue;
			}
			gnf_insert_notification( $docente_id, 'docente_solicita_acceso', 'Nuevo docente solicita unirse a tu centro.', 'centro', $centro_id );
		}
	}

	wp_set_current_user( $user_id );
	wp_set_auth_cookie( $user_id, true );
	gnf_log_audit_event(
		'auth_register_docente',
		array(
			'actor_user_id' => $user_id,
			'centro_id'     => $centro_id,
			'message'       => 'Registro de escuela creado.',
		)
	);
	if ( function_exists( 'gnf_clear_admin_stats_cache' ) ) {
		gnf_clear_admin_stats_cache();
	}

	return array(
		'user'        => gnf_rest_build_auth_user( get_userdata( $user_id ) ),
		'redirectUrl' => gnf_rest_get_default_redirect( get_userdata( $user_id ) ),
	);
}

function gnf_rest_auth_register_supervisor( WP_REST_Request $request ) {
	$nombre   = sanitize_text_field( $request->get_param( 'nombre' ) );
	$email    = sanitize_email( $request->get_param( 'email' ) );
	$password = $request->get_param( 'password' );
	$region   = (int) $request->get_param( 'regionId' );
	$rol      = sanitize_key( (string) ( $request->get_param( 'rolSolicitado' ) ?: $request->get_param( 'rol_solicitado' ) ?: 'supervisor' ) );
	$role_to_set = 'comite_bae' === $rol ? 'comite_bae' : 'supervisor';

	if ( '' === $nombre || '' === $email || '' === (string) $password || ! $region ) {
		return new WP_Error( 'missing_fields', 'Nombre, correo, contraseña y región son obligatorios.', array( 'status' => 400 ) );
	}

	if ( email_exists( $email ) ) {
		return new WP_Error( 'email_exists', 'Este correo ya está registrado.', array( 'status' => 400 ) );
	}

	$user_id = wp_create_user( $email, $password, $email );
	if ( is_wp_error( $user_id ) ) {
		return $user_id;
	}

	if ( ! function_exists( 'gnf_set_user_role_strict' ) || ! gnf_set_user_role_strict( $user_id, $role_to_set, 'rest_auth_register_supervisor' ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $user_id );
		return new WP_Error( 'role_assignment_failed', 'No se pudo asignar el rol solicitado.', array( 'status' => 500 ) );
	}
	wp_update_user( array( 'ID' => $user_id, 'display_name' => $nombre ) );

	update_user_meta( $user_id, 'gnf_supervisor_status', 'pendiente' );
	update_user_meta( $user_id, 'gnf_supervisor_estado', 'pendiente' );
	if ( function_exists( 'gnf_set_user_regions' ) ) {
		gnf_set_user_regions( $user_id, array( $region ) );
	} else {
		update_user_meta( $user_id, 'region', $region );
	}
	update_user_meta( $user_id, 'gnf_rol_solicitado', $role_to_set );

	if ( $request->get_param( 'cargo' ) ) {
		update_user_meta( $user_id, 'gnf_cargo', sanitize_text_field( $request->get_param( 'cargo' ) ) );
	}
	if ( $request->get_param( 'telefono' ) ) {
		update_user_meta( $user_id, 'gnf_telefono', sanitize_text_field( $request->get_param( 'telefono' ) ) );
	}
	if ( $request->get_param( 'identificacion' ) ) {
		update_user_meta( $user_id, 'gnf_identificacion', sanitize_text_field( $request->get_param( 'identificacion' ) ) );
	}
	if ( $request->get_param( 'justificacion' ) ) {
		update_user_meta( $user_id, 'gnf_justificacion', sanitize_textarea_field( $request->get_param( 'justificacion' ) ) );
	}

	if ( function_exists( 'gnf_notify_admins' ) ) {
		gnf_notify_admins(
			'registro',
			sprintf(
				'Nueva solicitud de %s pendiente de autorizacion manual: %s (%s)',
				'comite_bae' === $role_to_set ? 'comite BAE' : 'supervisor',
				$nombre,
				$email
			),
			'user',
			$user_id
		);
	}
	if ( function_exists( 'gnf_email_admins_manual_authorization_required' ) ) {
		gnf_email_admins_manual_authorization_required( $user_id );
	}

	wp_set_current_user( $user_id );
	wp_set_auth_cookie( $user_id, true );

	gnf_log_audit_event(
		'auth_register_supervisor',
		array(
			'actor_user_id' => $user_id,
			'message'       => 'Registro de supervisor/comite creado.',
			'meta'          => array(
				'requested_role' => $role_to_set,
				'region_id'      => $region,
			),
		)
	);
	if ( function_exists( 'gnf_clear_admin_stats_cache' ) ) {
		gnf_clear_admin_stats_cache();
	}

	return array(
		'user' => gnf_rest_build_auth_user( get_userdata( $user_id ) ),
		'redirectUrl' => gnf_rest_get_default_redirect( get_userdata( $user_id ) ),
	);
}

function gnf_rest_auth_me() {
	$user = wp_get_current_user();
	return gnf_rest_build_auth_user( $user );
}

function gnf_rest_auth_logout() {
	wp_logout();
	return array( 'success' => true );
}

function gnf_rest_auth_forgot_password( WP_REST_Request $request ) {
	$identifier   = sanitize_text_field( (string) $request->get_param( 'identifier' ) );
	$redirect_url = esc_url_raw( (string) $request->get_param( 'redirectUrl' ) );

	if ( '' === $identifier ) {
		return new WP_Error( 'missing_identifier', 'Debes indicar tu correo o usuario.', array( 'status' => 400 ) );
	}

	$user = false;
	if ( is_email( $identifier ) ) {
		$user = get_user_by( 'email', $identifier );
	}
	if ( ! $user ) {
		$user = get_user_by( 'login', $identifier );
	}
	if ( ! $user ) {
		return array(
			'success' => true,
			'message' => 'Si la cuenta existe, enviamos un enlace para restablecer la contrasena.',
		);
	}

	if ( '' === $redirect_url ) {
		$redirect_url = home_url( '/panel-docente/' );
	}

	$reset_key = get_password_reset_key( $user );
	if ( is_wp_error( $reset_key ) ) {
		return new WP_Error(
			'reset_key_error',
			'No se pudo procesar la solicitud en este momento. Intenta de nuevo.',
			array( 'status' => 500 )
		);
	}

	$reset_url = add_query_arg(
		array(
			'reset' => '1',
			'login' => rawurlencode( $user->user_login ),
			'key'   => rawurlencode( $reset_key ),
		),
		$redirect_url
	);

	$subject = 'Recupera tu acceso a Bandera Azul';
	$message = "Hola {$user->display_name},\n\n";
	$message .= "Recibimos una solicitud para restablecer tu contrasena.\n";
	$message .= "Puedes continuar desde este enlace seguro:\n\n";
	$message .= esc_url_raw( $reset_url ) . "\n\n";
	$message .= "Si no solicitaste este cambio, puedes ignorar este correo.\n";

	wp_mail( $user->user_email, $subject, $message );

	return array(
		'success' => true,
		'message' => 'Si la cuenta existe, enviamos un enlace para restablecer la contrasena.',
	);
}

function gnf_rest_auth_reset_password( WP_REST_Request $request ) {
	$login    = sanitize_text_field( (string) $request->get_param( 'login' ) );
	$key      = sanitize_text_field( (string) $request->get_param( 'key' ) );
	$password = (string) $request->get_param( 'password' );

	if ( '' === $login || '' === $key || '' === $password ) {
		return new WP_Error( 'missing_fields', 'Faltan datos para restablecer la contrasena.', array( 'status' => 400 ) );
	}

	if ( strlen( $password ) < 8 ) {
		return new WP_Error( 'weak_password', 'La nueva contrasena debe tener al menos 8 caracteres.', array( 'status' => 400 ) );
	}

	$user = check_password_reset_key( $key, $login );
	if ( is_wp_error( $user ) || ! ( $user instanceof WP_User ) ) {
		return new WP_Error( 'invalid_reset_key', 'El enlace de recuperacion ya no es valido o expiro.', array( 'status' => 400 ) );
	}

	reset_password( $user, $password );

	return array(
		'success' => true,
		'message' => 'Tu contrasena fue actualizada. Ya puedes iniciar sesion.',
	);
}

// ═══════════════════════════════════════════════════════════════════════
// Shared endpoints
// ═══════════════════════════════════════════════════════════════════════

function gnf_rest_centros_search( WP_REST_Request $request ) {
	$term            = sanitize_text_field( $request->get_param( 'term' ) );
	$region_id       = absint( $request->get_param( 'region' ) );
	$include_claimed = rest_sanitize_boolean( $request->get_param( 'includeClaimed' ) );
	if ( strlen( $term ) < 2 && ! $region_id ) {
		return array();
	}

	$args = array(
		'post_type'      => 'centro_educativo',
		'posts_per_page' => $region_id ? -1 : 20,
		'post_status'    => array( 'publish', 'pending' ),
		'orderby'        => 'title',
		'order'          => 'ASC',
	);
	if ( strlen( $term ) >= 2 ) {
		$args['s'] = $term;
	}
	if ( $region_id ) {
		$args['tax_query'] = array(
			array(
				'taxonomy' => 'gn_region',
				'field'    => 'term_id',
				'terms'    => array( $region_id ),
			),
		);
	}

	$query   = new WP_Query( $args );
	$results = array();

	foreach ( $query->posts as $post ) {
		$terms      = wp_get_object_terms( $post->ID, 'gn_region' );
		$region     = ! empty( $terms ) && ! is_wp_error( $terms ) ? $terms[0] : null;
		$is_claimed = gnf_is_centro_claimed_by_other_docente( $post->ID );
		if ( $is_claimed && ! $include_claimed ) {
			continue;
		}

		$results[] = array(
			'id'                  => $post->ID,
			'nombre'              => $post->post_title,
			'codigoMep'           => get_field( 'codigo_mep', $post->ID ) ?: '',
			'regionId'            => $region ? (int) $region->term_id : 0,
			'regionName'          => $region ? $region->name : '',
			'claimed'             => $is_claimed,
			'correoInstitucional' => (string) ( get_field( 'correo_institucional', $post->ID ) ?: get_post_meta( $post->ID, 'correo_institucional', true ) ?: '' ),
		);
	}

	return $results;
}

function gnf_rest_centro_get( WP_REST_Request $request ) {
	$id   = (int) $request->get_param( 'id' );
	$post = get_post( $id );

	if ( ! $post || 'centro_educativo' !== $post->post_type ) {
		return new WP_Error( 'not_found', 'Centro no encontrado.', array( 'status' => 404 ) );
	}

	if ( ! gnf_user_can_access_centro( get_current_user_id(), $id ) && ! gnf_rest_is_admin() ) {
		return new WP_Error( 'forbidden', 'Sin acceso a este centro.', array( 'status' => 403 ) );
	}

	$terms = wp_get_object_terms( $id, 'gn_region' );
	return array(
		'id'                   => $id,
		'nombre'               => $post->post_title,
		'codigoMep'            => get_field( 'codigo_mep', $id ) ?: '',
		'regionId'             => ! empty( $terms ) ? (int) $terms[0]->term_id : 0,
		'regionName'           => ! empty( $terms ) ? $terms[0]->name : '',
		'direccion'            => get_field( 'direccion', $id ) ?: '',
		'provincia'            => get_field( 'provincia', $id ) ?: '',
		'canton'               => get_field( 'canton', $id ) ?: '',
		'telefono'             => get_field( 'telefono', $id ) ?: '',
		'correoInstitucional'  => get_field( 'correo_institucional', $id ) ?: '',
		'circuito'             => get_field( 'circuito', $id ) ?: '',
		'codigoPresupuestario' => get_field( 'codigo_presupuestario', $id ) ?: '',
		'nivelEducativo'       => get_field( 'nivel_educativo', $id ) ?: '',
		'dependencia'          => get_field( 'dependencia', $id ) ?: '',
		'jornada'              => get_field( 'jornada', $id ) ?: '',
		'tipologia'            => get_field( 'tipologia', $id ) ?: '',
		'tipoCentroEducativo'  => get_field( 'tipo_centro_educativo', $id ) ?: '',
	);
}

function gnf_rest_centro_update( WP_REST_Request $request ) {
	$id = (int) $request->get_param( 'id' );

	if ( ! gnf_user_can_access_centro( get_current_user_id(), $id ) && ! gnf_rest_is_admin() ) {
		return new WP_Error( 'forbidden', 'Sin acceso a este centro.', array( 'status' => 403 ) );
	}

	$nombre = sanitize_text_field( (string) $request->get_param( 'nombre' ) );
	if ( '' !== $nombre ) {
		wp_update_post(
			array(
				'ID'         => $id,
				'post_title' => $nombre,
			)
		);
	}

	$codigo_mep = sanitize_text_field( (string) $request->get_param( 'codigoMep' ) );
	if ( '' !== $codigo_mep ) {
		update_field( 'codigo_mep', $codigo_mep, $id );
	}

	$fields = array(
		'direccion'            => 'direccion',
		'provincia'            => 'provincia',
		'canton'               => 'canton',
		'telefono'             => 'telefono',
		'correoInstitucional'  => 'correo_institucional',
		'circuito'             => 'circuito',
		'codigoPresupuestario' => 'codigo_presupuestario',
		'nivelEducativo'       => 'nivel_educativo',
		'dependencia'          => 'dependencia',
		'jornada'              => 'jornada',
		'tipologia'            => 'tipologia',
		'tipoCentroEducativo'  => 'tipo_centro_educativo',
	);
	foreach ( $fields as $request_key => $meta_key ) {
		$value = $request->get_param( $request_key );
		if ( $value !== null ) {
			update_field( $meta_key, sanitize_text_field( (string) $value ), $id );
		}
	}

	$region_id = absint( $request->get_param( 'regionId' ) );
	if ( $region_id ) {
		wp_set_object_terms( $id, array( $region_id ), 'gn_region', false );
		update_post_meta( $id, 'region', $region_id );
	}

	gnf_log_audit_event(
		'centro_update',
		array(
			'actor_user_id' => get_current_user_id(),
			'centro_id'     => $id,
			'panel'         => gnf_rest_is_admin() ? 'admin' : 'docente',
			'message'       => 'Datos del centro educativo actualizados.',
		)
	);

	$response_request = new WP_REST_Request( 'GET', '/gnf/v1/centros/' . $id );
	$response_request->set_param( 'id', $id );
	return gnf_rest_centro_get( $response_request );
}

function gnf_rest_admin_create_centro( WP_REST_Request $request ) {
	$nombre     = sanitize_text_field( (string) $request->get_param( 'nombre' ) );
	$codigo_mep = sanitize_text_field( (string) $request->get_param( 'codigoMep' ) );

	if ( '' === $nombre ) {
		return new WP_Error( 'missing_name', 'Debes indicar el nombre del centro.', array( 'status' => 400 ) );
	}

	$existing_centro_id = 0;
	if ( '' !== $codigo_mep && function_exists( 'gnf_find_centro_by_codigo' ) ) {
		$existing_centro_id = (int) gnf_find_centro_by_codigo( $codigo_mep );
	}
	if ( ! $existing_centro_id ) {
		$existing_centro_id = (int) gnf_rest_find_existing_centro_by_title( $nombre );
	}

	if ( $existing_centro_id ) {
		return new WP_Error(
			'centro_exists',
			'Ya existe un centro con ese nombre o codigo MEP.',
			array(
				'status'   => 409,
				'centroId' => $existing_centro_id,
			)
		);
	}

	$post_id = wp_insert_post(
		array(
			'post_title'  => $nombre,
			'post_type'   => 'centro_educativo',
			'post_status' => 'publish',
		),
		true
	);

	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}

	if ( '' !== $codigo_mep ) {
		gnf_rest_update_post_field( 'codigo_mep', $codigo_mep, $post_id );
	}

	$fields = array(
		'direccion'            => 'direccion',
		'provincia'            => 'provincia',
		'canton'               => 'canton',
		'telefono'             => 'telefono',
		'correoInstitucional'  => 'correo_institucional',
		'circuito'             => 'circuito',
		'codigoPresupuestario' => 'codigo_presupuestario',
		'nivelEducativo'       => 'nivel_educativo',
		'dependencia'          => 'dependencia',
		'jornada'              => 'jornada',
		'tipologia'            => 'tipologia',
		'tipoCentroEducativo'  => 'tipo_centro_educativo',
	);

	foreach ( $fields as $request_key => $meta_key ) {
		$value = $request->get_param( $request_key );
		if ( null !== $value ) {
			gnf_rest_update_post_field( $meta_key, sanitize_text_field( (string) $value ), $post_id );
		}
	}

	$region_id = absint( $request->get_param( 'regionId' ) );
	if ( $region_id ) {
		wp_set_object_terms( $post_id, array( $region_id ), 'gn_region', false );
		update_post_meta( $post_id, 'region', $region_id );
	}

	update_post_meta( $post_id, 'estado_centro', 'activo' );

	gnf_log_audit_event(
		'admin_create_centro',
		array(
			'actor_user_id' => get_current_user_id(),
			'centro_id'     => (int) $post_id,
			'panel'         => 'admin',
			'message'       => 'Centro educativo creado desde el panel admin.',
		)
	);

	$response_request = new WP_REST_Request( 'GET', '/gnf/v1/centros/' . $post_id );
	$response_request->set_param( 'id', $post_id );
	return gnf_rest_centro_get( $response_request );
}

function gnf_rest_regions() {
	$terms   = get_terms( array( 'taxonomy' => 'gn_region', 'hide_empty' => false ) );
	$regions = array();
	$is_admin_view = gnf_rest_is_admin();

	if ( ! is_wp_error( $terms ) ) {
		foreach ( $terms as $term ) {
			$is_active = '1' === (string) get_term_meta( $term->term_id, 'gnf_dre_activa', true );

			if ( ! $is_admin_view && ! $is_active ) {
				continue;
			}

			$regions[] = array(
				'id'   => $term->term_id,
				'name' => $term->name,
				'slug' => $term->slug,
			);
		}
	}

	return $regions;
}

function gnf_rest_notifications_list() {
	global $wpdb;
	$table   = $wpdb->prefix . 'gn_notificaciones';
	$user_id = get_current_user_id();

	$items = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC LIMIT 50",
			$user_id
		)
	);

	return array_map(
		static function ( $item ) use ( $user_id ) {
			$context = function_exists( 'gnf_build_notification_context' ) ? gnf_build_notification_context( $item, $user_id ) : array();

			return array(
				'id'                   => (int) $item->id,
				'userId'               => (int) $item->user_id,
				'tipo'                 => $item->tipo,
				'mensaje'              => $item->mensaje,
				'relacionTipo'         => $item->relacion_tipo,
				'relacionId'           => (int) $item->relacion_id,
				'leido'                => (bool) $item->leido,
				'createdAt'            => $item->created_at,
				'actionTarget'         => $context['actionTarget'] ?? null,
				'actionLabel'          => $context['actionLabel'] ?? '',
				'canReview'            => ! empty( $context['canReview'] ),
				'entryId'              => (int) ( $context['entryId'] ?? 0 ),
				'retoId'               => (int) ( $context['retoId'] ?? 0 ),
				'retoTitulo'           => (string) ( $context['retoTitulo'] ?? '' ),
				'centroId'             => (int) ( $context['centroId'] ?? 0 ),
				'centroNombre'         => (string) ( $context['centroNombre'] ?? '' ),
				'regionName'           => (string) ( $context['regionName'] ?? '' ),
				'circuito'             => (string) ( $context['circuito'] ?? '' ),
				'year'                 => (int) ( $context['year'] ?? 0 ),
				'entryStatus'          => (string) ( $context['entryStatus'] ?? '' ),
				'hasRejectedEvidence'  => ! empty( $context['hasRejectedEvidence'] ),
				'evidenceItems'        => array_values( (array) ( $context['evidenceItems'] ?? array() ) ),
				'requiresYearValidation' => ! empty( $context['requiresYearValidation'] ),
			);
		},
		$items
	);
}

function gnf_rest_notification_mark_read( WP_REST_Request $request ) {
	global $wpdb;
	$table = $wpdb->prefix . 'gn_notificaciones';
	$id    = (int) $request->get_param( 'id' );

	$wpdb->update( $table, array( 'leido' => 1 ), array( 'id' => $id, 'user_id' => get_current_user_id() ) );
	return array( 'success' => true );
}

function gnf_rest_notifications_mark_all_read() {
	global $wpdb;
	$table = $wpdb->prefix . 'gn_notificaciones';

	$wpdb->update(
		$table,
		array( 'leido' => 1 ),
		array( 'user_id' => get_current_user_id(), 'leido' => 0 ),
		array( '%d' ),
		array( '%d', '%d' )
	);

	return array( 'success' => true );
}

// ═══════════════════════════════════════════════════════════════════════
// Docente endpoints
// ═══════════════════════════════════════════════════════════════════════

function gnf_rest_docente_dashboard( WP_REST_Request $request ) {
	$user_id   = get_current_user_id();
	$anio      = (int) ( $request->get_param( 'year' ) ?: gnf_get_active_year() );
	$centro_id = gnf_get_centro_for_docente( $user_id );

	if ( ! $centro_id ) {
		return new WP_Error( 'no_centro', 'No tienes un centro asignado.', array( 'status' => 400 ) );
	}

	$centro = get_post( $centro_id );
	$terms  = wp_get_object_terms( $centro_id, 'gn_region' );

	$retos_sel  = gnf_get_centro_retos_seleccionados( $centro_id, $anio );
	$entries    = gnf_get_user_reto_entries( $user_id, $anio );
	$puntaje    = gnf_get_centro_puntaje_total( $centro_id, $anio );
	$estrella   = gnf_get_centro_estrella_final( $centro_id, $anio );
	$meta       = gnf_get_centro_meta_estrellas( $centro_id, $anio );
	$matricula_estado = function_exists( 'gnf_get_centro_matricula_estado' )
		? gnf_get_centro_matricula_estado( $centro_id, $anio )
		: ( ! empty( $retos_sel ) ? 'pendiente' : 'no_iniciado' );

	$counts = array( 'aprobados' => 0, 'enviados' => 0, 'correccion' => 0, 'en_progreso' => 0 );
	foreach ( $entries as $e ) {
		if ( isset( $counts[ $e->estado ] ) ) {
			$counts[ $e->estado ]++;
		}
		if ( $e->estado === 'en_progreso' || $e->estado === 'no_iniciado' || $e->estado === 'completo' ) {
			$counts['en_progreso']++;
		}
	}

	$all_complete = ! empty( $retos_sel ) && $counts['aprobados'] >= count( $retos_sel );

	return array(
		'centro'           => array(
			'id'         => $centro_id,
			'nombre'     => $centro ? $centro->post_title : '',
			'codigoMep'  => get_field( 'codigo_mep', $centro_id ) ?: '',
			'regionName' => ! empty( $terms ) ? $terms[0]->name : '',
			'estado'     => get_post_status( $centro_id ),
		),
		'docenteEstado'    => gnf_get_docente_estado( $user_id ),
		'metaEstrellas'    => $meta,
		'puntajeTotal'     => $puntaje,
		'estrellaFinal'    => $estrella,
		'retosCount'       => count( $retos_sel ),
		'aprobados'        => $counts['aprobados'],
		'enviados'         => $counts['enviados'],
		'correccion'       => $counts['correccion'],
		'enProgreso'       => $counts['en_progreso'],
		'tieneMatricula'   => 'no_iniciado' !== $matricula_estado,
		'allRetosComplete' => $all_complete,
	);
}

function gnf_rest_docente_retos( WP_REST_Request $request ) {
	$user_id   = get_current_user_id();
	$anio      = (int) ( $request->get_param( 'year' ) ?: gnf_get_active_year() );
	$centro_id = gnf_get_centro_for_docente( $user_id );
	$retos_sel = gnf_get_centro_retos_seleccionados( $centro_id, $anio );
	$entries   = gnf_get_user_reto_entries( $user_id, $anio );

	// Index entries by reto_id.
	$entries_by_reto = array();
	foreach ( $entries as $e ) {
		$entries_by_reto[ $e->reto_id ] = $e;
	}

	$result = array();
	foreach ( $retos_sel as $reto_id ) {
		$reto = get_post( $reto_id );
		if ( ! $reto ) {
			continue;
		}

		$entry   = $entries_by_reto[ $reto_id ] ?? null;
		$max_pts = gnf_get_reto_max_points( $reto_id, $anio );

		$result[] = array(
			'id'            => $reto_id,
			'titulo'        => $reto->post_title,
			'descripcion'   => get_field( 'descripcion', $reto_id ) ?: '',
			'color'         => gnf_get_reto_color( $reto_id ),
			'iconUrl'       => gnf_get_reto_icon_url( $reto_id, 'thumbnail', $anio ),
			'pdfUrl'        => gnf_get_reto_pdf_url( $reto_id, $anio ),
			'puntajeMaximo' => $max_pts,
			'obligatorio'   => gnf_is_reto_required( $reto_id, $anio ),
			'formId'        => gnf_get_reto_form_id_for_year( $reto_id, $anio ),
			'entry'         => $entry
				? array(
					'id'              => (int) $entry->id,
					'retoId'          => (int) $entry->reto_id,
					'centroId'        => (int) $entry->centro_id,
					'userId'          => (int) $entry->user_id,
					'anio'            => (int) $entry->anio,
					'estado'          => $entry->estado,
					'puntaje'         => (int) $entry->puntaje,
					'puntajeMaximo'   => $max_pts,
					'supervisorNotes' => $entry->supervisor_notes ?: '',
					'evidencias'      => $entry->evidencias ? json_decode( $entry->evidencias, true ) : array(),
					'createdAt'       => $entry->created_at,
					'updatedAt'       => $entry->updated_at,
				)
				: null,
		);
	}

	return $result;
}

function gnf_rest_docente_matricula( WP_REST_Request $request ) {
	$user_id   = get_current_user_id();
	$anio      = (int) ( $request->get_param( 'year' ) ?: gnf_get_active_year() );
	$centro_id = gnf_get_centro_for_docente( $user_id );

	$prefill = function_exists( 'gnf_get_matricula_prefill_data' )
		? gnf_get_matricula_prefill_data( $user_id, $centro_id, $anio )
		: array();

	$centro_data = null;
	if ( $centro_id ) {
		$post = get_post( $centro_id );
		if ( $post ) {
			$centro_data = array(
				'id'        => $centro_id,
				'nombre'    => $post->post_title,
				'codigoMep' => get_post_meta( $centro_id, 'codigo_mep', true ) ?: '',
				'direccion' => get_post_meta( $centro_id, 'direccion', true ) ?: '',
				'provincia' => get_post_meta( $centro_id, 'provincia', true ) ?: '',
				'canton'    => get_post_meta( $centro_id, 'canton', true ) ?: '',
			);
		}
	}

	$retos_selected = $centro_id ? gnf_get_centro_retos_seleccionados( $centro_id, $anio ) : array();
	$meta_estrellas = $centro_id && function_exists( 'gnf_get_centro_meta_estrellas' ) ? gnf_get_centro_meta_estrellas( $centro_id, $anio ) : 1;
	$comite_est     = $centro_id && function_exists( 'gnf_get_centro_comite_estudiantes' ) ? gnf_get_centro_comite_estudiantes( $centro_id, $anio ) : 0;

	$retos_disponibles = array();
	foreach ( gnf_get_available_retos_for_year( $anio ) as $reto ) {
		$retos_disponibles[] = array(
			'id'            => (int) $reto->ID,
			'titulo'        => $reto->post_title,
			'descripcion'   => get_field( 'descripcion', $reto->ID ) ?: '',
			'iconUrl'       => gnf_get_reto_icon_url( $reto->ID, 'thumbnail', $anio ),
			'puntajeMaximo' => gnf_get_reto_max_points( $reto->ID, $anio ),
			'obligatorio'   => gnf_is_reto_required( $reto->ID, $anio ),
			'hasForm'       => true,
		);
	}

	$choice_sets = gnf_get_centro_profile_choice_sets();
	$field_defs  = function_exists( 'gnf_get_matricula_field_definitions' ) ? gnf_get_matricula_field_definitions() : array();
	$regions     = get_terms(
		array(
			'taxonomy'   => 'gn_region',
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		)
	);

	return array(
		'centro'             => $centro_data,
		'retosDisponibles'   => $retos_disponibles,
		'retosSeleccionados' => array_values( array_map( 'intval', $retos_selected ) ),
		'metaEstrellas'      => (int) $meta_estrellas,
		'comiteEstudiantes'  => (int) $comite_est,
		'prefill'            => array(
			'centroExiste'                  => (string) ( $prefill['centro_existe'] ?? 'Sí' ),
			'centroIdExistente'            => (int) ( $prefill['centro_id_existente'] ?? $centro_id ),
			'centroNombre'                 => (string) ( $prefill['centro_nombre'] ?? '' ),
			'centroCodigoMep'              => (string) ( $prefill['centro_codigo_mep'] ?? '' ),
			'centroCorreoInstitucional'    => (string) ( $prefill['centro_correo_institucional'] ?? '' ),
			'centroTelefono'               => (string) ( $prefill['centro_telefono'] ?? '' ),
			'centroNivelEducativo'         => (string) ( $prefill['centro_nivel_educativo'] ?? '' ),
			'centroDependencia'            => (string) ( $prefill['centro_dependencia'] ?? '' ),
			'centroJornada'                => (string) ( $prefill['centro_jornada'] ?? '' ),
			'centroTipologia'              => (string) ( $prefill['centro_tipologia'] ?? '' ),
			'centroTipoCentroEducativo'    => (string) ( $prefill['centro_tipo_centro_educativo'] ?? '' ),
			'centroRegion'                 => (int) ( $prefill['centro_region'] ?? 0 ),
			'centroCircuito'               => (string) ( $prefill['centro_circuito'] ?? '' ),
			'centroProvincia'              => (string) ( $prefill['centro_provincia'] ?? '' ),
			'centroCanton'                 => (string) ( $prefill['centro_canton'] ?? '' ),
			'centroCodigoPresupuestario'   => (string) ( $prefill['centro_codigo_presupuestario'] ?? '' ),
			'centroDireccion'              => (string) ( $prefill['centro_direccion'] ?? '' ),
			'centroTotalEstudiantes'       => (int) ( $prefill['centro_total_estudiantes'] ?? 0 ),
			'centroEstudiantesHombres'     => (int) ( $prefill['centro_estudiantes_hombres'] ?? 0 ),
			'centroEstudiantesMujeres'     => (int) ( $prefill['centro_estudiantes_mujeres'] ?? 0 ),
			'centroEstudiantesMigrantes'   => (int) ( $prefill['centro_estudiantes_migrantes'] ?? 0 ),
			'centroUltimoGalardonEstrellas'=> (string) ( $prefill['centro_ultimo_galardon_estrellas'] ?? '1' ),
			'centroUltimoAnioParticipacion'=> (string) ( $prefill['centro_ultimo_anio_participacion'] ?? '2025' ),
			'centroUltimoAnioParticipacionOtro' => (string) ( $prefill['centro_ultimo_anio_participacion_otro'] ?? '' ),
			'coordinadorCargo'             => (string) ( $prefill['coordinador_cargo'] ?? '' ),
			'coordinadorNombre'            => (string) ( $prefill['coordinador_nombre'] ?? '' ),
			'coordinadorCelular'           => (string) ( $prefill['coordinador_celular'] ?? '' ),
			'representanteNombre'          => (string) ( $prefill['docente_nombre'] ?? '' ),
			'representanteCargo'           => (string) ( $prefill['docente_cargo'] ?? '' ),
			'representanteTelefono'        => (string) ( $prefill['docente_telefono'] ?? '' ),
			'representanteEmail'           => (string) ( $prefill['docente_email'] ?? '' ),
			'representanteEmailConfirm'    => (string) ( $prefill['docente_email_confirm'] ?? '' ),
			'docenteConfirmaciones'        => array_values( array_map( 'strval', (array) ( $prefill['docente_confirmaciones'] ?? array() ) ) ),
			'comiteEstudiantes'            => (int) ( $prefill['bae_comite_estudiantes'] ?? $comite_est ),
			'inscripcionAnterior'          => (string) ( $prefill['bae_inscripcion_anterior'] ?? 'No' ),
			'metaEstrellas'                => (string) ( $prefill['bae_meta_estrellas'] ?? '1 estrella' ),
		),
		'fieldDefs'          => $field_defs,
		'choiceSets'         => $choice_sets,
		'provincias'         => array_values( gnf_get_cr_provinces() ),
		'cantonesPorProvincia' => gnf_get_cr_province_canton_map(),
		'regiones'           => array_map(
			static function ( $term ) {
				return array(
					'id'   => (int) $term->term_id,
					'name' => $term->name,
				);
			},
			is_array( $regions ) ? $regions : array()
		),
	);
}

function gnf_rest_docente_matricula_save( WP_REST_Request $request ) {
	$user_id   = get_current_user_id();
	$anio      = (int) ( $request->get_param( 'year' ) ?: gnf_get_active_year() );
	$fields    = $request->get_param( 'fields' );
	$centro_id = gnf_get_centro_for_docente( $user_id );
	$user      = get_userdata( $user_id );

	if ( ! is_array( $fields ) ) {
		return new WP_Error( 'invalid_payload', 'No se recibieron datos validos de matricula.', array( 'status' => 400 ) );
	}

	$retos = array_map( 'absint', (array) ( $fields['retosSeleccionados'] ?? array() ) );
	$retos = array_values( array_unique( array_filter( $retos ) ) );
	$retos = gnf_resolve_reto_ids_for_year( $retos, $anio );
	$retos = gnf_sort_reto_ids_required_first( $retos );
	if ( empty( $retos ) ) {
		return new WP_Error( 'missing_retos', 'Debes seleccionar al menos un eco reto.', array( 'status' => 400 ) );
	}

	$email = sanitize_email( (string) ( $fields['representanteEmail'] ?? '' ) );
	$email_confirm = sanitize_email( (string) ( $fields['representanteEmailConfirm'] ?? '' ) );
	if ( $email && $email_confirm && strtolower( $email ) !== strtolower( $email_confirm ) ) {
		return new WP_Error( 'email_mismatch', 'La confirmacion de correo no coincide.', array( 'status' => 400 ) );
	}

	$provincia = sanitize_text_field( (string) ( $fields['centroProvincia'] ?? '' ) );
	$canton    = sanitize_text_field( (string) ( $fields['centroCanton'] ?? '' ) );
	if ( $provincia && $canton && ! gnf_is_valid_cr_province_canton( $provincia, $canton ) ) {
		return new WP_Error( 'invalid_canton', 'El canton seleccionado no pertenece a la provincia elegida.', array( 'status' => 400 ) );
	}

	$normalized = array(
		'centro-existe'                    => 'Sí',
		'centro-id-existente'              => $centro_id,
		'centro-nombre'                    => sanitize_text_field( (string) ( $fields['centroNombre'] ?? '' ) ),
		'centro-codigo-mep'                => sanitize_text_field( (string) ( $fields['centroCodigoMep'] ?? '' ) ),
		'centro-correo-institucional'      => sanitize_email( (string) ( $fields['centroCorreoInstitucional'] ?? '' ) ),
		'centro-telefono'                  => sanitize_text_field( (string) ( $fields['centroTelefono'] ?? '' ) ),
		'centro-nivel-educativo'           => gnf_normalize_centro_choice( 'nivel_educativo', sanitize_text_field( (string) ( $fields['centroNivelEducativo'] ?? '' ) ) ),
		'centro-dependencia'               => gnf_normalize_centro_choice( 'dependencia', sanitize_text_field( (string) ( $fields['centroDependencia'] ?? '' ) ) ),
		'centro-jornada'                   => gnf_normalize_centro_choice( 'jornada', sanitize_text_field( (string) ( $fields['centroJornada'] ?? '' ) ) ),
		'centro-tipologia'                 => gnf_normalize_centro_choice( 'tipologia', sanitize_text_field( (string) ( $fields['centroTipologia'] ?? '' ) ) ),
		'centro-tipo-centro-educativo'     => gnf_normalize_centro_choice( 'tipo_centro_educativo', sanitize_text_field( (string) ( $fields['centroTipoCentroEducativo'] ?? '' ) ) ),
		'centro-region'                    => absint( $fields['centroRegion'] ?? 0 ),
		'centro-circuito'                  => sanitize_text_field( (string) ( $fields['centroCircuito'] ?? '' ) ),
		'centro-provincia'                 => $provincia,
		'centro-canton'                    => $canton,
		'centro-codigo-presupuestario'     => sanitize_text_field( (string) ( $fields['centroCodigoPresupuestario'] ?? '' ) ),
		'centro-direccion'                 => sanitize_textarea_field( (string) ( $fields['centroDireccion'] ?? '' ) ),
		'centro-total-estudiantes'         => absint( $fields['centroTotalEstudiantes'] ?? 0 ),
		'centro-estudiantes-hombres'       => absint( $fields['centroEstudiantesHombres'] ?? 0 ),
		'centro-estudiantes-mujeres'       => absint( $fields['centroEstudiantesMujeres'] ?? 0 ),
		'centro-estudiantes-migrantes'     => absint( $fields['centroEstudiantesMigrantes'] ?? 0 ),
		'centro-ultimo-galardon-estrellas' => (string) max( 1, min( 5, absint( $fields['centroUltimoGalardonEstrellas'] ?? 1 ) ) ),
		'centro-ultimo-anio-participacion' => sanitize_text_field( (string) ( $fields['centroUltimoAnioParticipacion'] ?? '2025' ) ),
		'centro-ultimo-anio-participacion-otro' => sanitize_text_field( (string) ( $fields['centroUltimoAnioParticipacionOtro'] ?? '' ) ),
		'coordinador-cargo'                => gnf_normalize_centro_choice( 'coordinador_cargo', sanitize_text_field( (string) ( $fields['coordinadorCargo'] ?? '' ) ) ),
		'coordinador-nombre'               => sanitize_text_field( (string) ( $fields['coordinadorNombre'] ?? '' ) ),
		'coordinador-celular'              => sanitize_text_field( (string) ( $fields['coordinadorCelular'] ?? '' ) ),
		'docente-nombre'                   => sanitize_text_field( (string) ( $fields['representanteNombre'] ?? wp_get_current_user()->display_name ) ),
		'docente-cargo'                    => sanitize_text_field( (string) ( $fields['representanteCargo'] ?? '' ) ),
		'docente-telefono'                 => sanitize_text_field( (string) ( $fields['representanteTelefono'] ?? '' ) ),
		'docente-email'                    => $email,
		'docente-email-confirm'            => $email_confirm,
		'docente-confirmaciones'           => array_values(
			array_unique(
				array_filter(
					array_map(
						'sanitize_text_field',
						(array) ( $fields['docenteConfirmaciones'] ?? array() )
					)
				)
			)
		),
		'anio'                             => $anio,
		'bae-comite-estudiantes'           => absint( $fields['comiteEstudiantes'] ?? 0 ),
		'bae-inscripcion-anterior'         => sanitize_text_field( (string) ( $fields['inscripcionAnterior'] ?? 'No' ) ),
		'bae-meta-estrellas'               => sanitize_text_field( (string) ( $fields['metaEstrellas'] ?? '1 estrella' ) ),
		'bae-retos-seleccionados'          => $retos,
	);
	$normalized['centro-modalidad'] = $normalized['centro-nivel-educativo'];
	$normalized['centro-horario']   = $normalized['centro-jornada'];
	$normalized['centro-existe']     = sanitize_text_field( (string) ( $fields['centroExiste'] ?? ( $centro_id ? 'Sí' : 'No' ) ) );
	$normalized['centro-id-existente'] = absint( $fields['centroIdExistente'] ?? $centro_id );

	if ( $user instanceof WP_User && is_email( $user->user_email ) ) {
		$normalized['centro-correo-institucional'] = $user->user_email;
	}

	gnf_handle_matricula_submission(
		$normalized,
		0,
		array(
			'id'     => 0,
			'engine' => 'react',
			'source' => 'rest_matricula',
		)
	);

	$centro_id = gnf_get_centro_for_docente( $user_id );

	gnf_log_audit_event(
		'docente_matricula_save',
		array(
			'actor_user_id' => $user_id,
			'centro_id'     => $centro_id,
			'anio'          => $anio,
			'panel'         => 'docente',
			'message'       => 'Matricula guardada desde React.',
			'meta'          => array(
				'retos' => $retos,
			),
		)
	);

	if ( function_exists( 'gnf_clear_admin_stats_cache' ) ) {
		gnf_clear_admin_stats_cache( $anio );
	}

	return array(
		'success' => true,
		'message' => 'Matricula guardada correctamente.',
	);
}

function gnf_rest_docente_matricula_add_reto( WP_REST_Request $request ) {
	$user_id   = get_current_user_id();
	$reto_id   = (int) $request->get_param( 'retoId' );
	$centro_id = gnf_get_centro_for_docente( $user_id );
	$anio      = (int) ( $request->get_param( 'year' ) ?: gnf_get_active_year() );

	$current = gnf_get_centro_retos_seleccionados( $centro_id, $anio );
	if ( ! in_array( $reto_id, $current, true ) ) {
		$current[] = $reto_id;
		gnf_set_centro_retos_seleccionados( $centro_id, $anio, $current );
	}

	gnf_log_audit_event(
		'matricula_add_reto',
		array(
			'actor_user_id' => $user_id,
			'centro_id'     => $centro_id,
			'reto_id'       => $reto_id,
			'anio'          => $anio,
			'panel'         => 'docente',
			'message'       => 'Reto agregado a la matricula.',
		)
	);

	return array( 'success' => true );
}

function gnf_rest_docente_matricula_remove_reto( WP_REST_Request $request ) {
	$user_id   = get_current_user_id();
	$reto_id   = (int) $request->get_param( 'id' );
	$centro_id = gnf_get_centro_for_docente( $user_id );
	$anio      = (int) ( $request->get_param( 'year' ) ?: gnf_get_active_year() );

	// Do not allow removing obligatory retos.
	if ( gnf_is_reto_required( $reto_id, $anio ) ) {
		return new WP_Error( 'cannot_remove', 'No se puede quitar un reto obligatorio.', array( 'status' => 400 ) );
	}

	$current = gnf_get_centro_retos_seleccionados( $centro_id, $anio );
	$current = array_values( array_diff( $current, array( $reto_id ) ) );
	gnf_set_centro_retos_seleccionados( $centro_id, $anio, $current );

	gnf_log_audit_event(
		'matricula_remove_reto',
		array(
			'actor_user_id' => $user_id,
			'centro_id'     => $centro_id,
			'reto_id'       => $reto_id,
			'anio'          => $anio,
			'panel'         => 'docente',
			'message'       => 'Reto removido de la matricula.',
		)
	);

	return array( 'success' => true );
}

function gnf_rest_docente_wizard( WP_REST_Request $request ) {
	$user_id   = get_current_user_id();
	$anio      = (int) ( $request->get_param( 'year' ) ?: gnf_get_active_year() );
	$centro_id = gnf_get_centro_for_docente( $user_id );

	if ( ! $centro_id || ! function_exists( 'gnf_get_wizard_steps' ) ) {
		return array();
	}

	$steps  = gnf_get_wizard_steps( $centro_id, $anio );
	$result = array();
	foreach ( $steps as $step ) {
		if ( 'enviar' === ( $step['type'] ?? '' ) ) {
			continue;
		}
		$entry = $step['entry'] ?? null;
		$result[] = array(
			'retoId'        => (int) ( $step['reto_id'] ?? 0 ),
			'retoTitulo'    => $step['title'] ?? '',
			'retoColor'     => $step['color'] ?? '',
			'retoIconUrl'   => is_string( $step['icon'] ?? '' ) && str_starts_with( $step['icon'], 'http' ) ? $step['icon'] : '',
			'pdfUrl'        => gnf_get_reto_pdf_url( (int) ( $step['reto_id'] ?? 0 ), $anio ),
			'formId'        => (int) ( $step['form_id'] ?? 0 ),
			'estado'        => $step['estado'] ?? 'no_iniciado',
			'puntaje'       => $entry ? (int) $entry->puntaje : 0,
			'puntajeMaximo' => (int) ( $step['puntaje_max'] ?? 0 ),
		);
	}

	return $result;
}

function gnf_rest_get_docente_entry_row( $centro_id, $reto_id, $anio ) {
	global $wpdb;
	$table = $wpdb->prefix . 'gn_reto_entries';

	return $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE centro_id = %d AND reto_id = %d AND anio = %d",
			$centro_id,
			$reto_id,
			$anio
		)
	);
}

function gnf_rest_get_region_reviewers( $region_id ) {
	$region_id = absint( $region_id );
	if ( ! $region_id ) {
		return array();
	}

	$users = get_users(
		array(
			'role__in' => array( 'supervisor', 'comite_bae' ),
			'number'   => 400,
		)
	);

	$unique = array();
	foreach ( $users as $user ) {
		if ( function_exists( 'gnf_user_has_region_access' ) && ! gnf_user_has_region_access( $user->ID, $region_id ) ) {
			continue;
		}

		$unique[ $user->ID ] = $user;
	}

	return array_values( $unique );
}

/**
 * Obtiene el alcance regional efectivo del usuario para panel supervisor/comité.
 *
 * @param int $user_id          Usuario actual.
 * @param int $requested_region Región solicitada por filtro.
 * @return array<string,mixed>
 */
function gnf_rest_get_user_region_scope( $user_id, $requested_region = 0 ) {
	$user             = get_userdata( $user_id );
	$available_region_ids = function_exists( 'gnf_get_user_regions' )
		? array_values( array_map( 'absint', gnf_get_user_regions( $user_id ) ) )
		: array_filter( array( (int) gnf_get_user_region( $user_id ) ) );
	$requested_region = absint( $requested_region );
	$selected_region_ids = $available_region_ids;
	$can_filter_all      = $user instanceof WP_User && gnf_user_has_role( $user, 'comite_bae' ) && count( $available_region_ids ) > 1;

	if ( $requested_region && in_array( $requested_region, $available_region_ids, true ) ) {
		$selected_region_ids = array( $requested_region );
	}

	$available_region_names = array();
	foreach ( $available_region_ids as $region_id ) {
		$term = get_term( $region_id, 'gn_region' );
		if ( $term && ! is_wp_error( $term ) ) {
			$available_region_names[ $region_id ] = (string) $term->name;
		}
	}

	$selected_label = '';
	if ( 1 === count( $selected_region_ids ) ) {
		$selected_label = $available_region_names[ $selected_region_ids[0] ] ?? '';
	} elseif ( count( $selected_region_ids ) > 1 ) {
		$selected_label = $can_filter_all ? 'Ver todo' : implode( ', ', array_values( $available_region_names ) );
	}

	return array(
		'availableRegionIds'   => $available_region_ids,
		'selectedRegionIds'    => $selected_region_ids,
		'selectedRegionId'     => 1 === count( $selected_region_ids ) ? (int) $selected_region_ids[0] : 0,
		'availableRegionNames' => $available_region_names,
		'selectedLabel'        => $selected_label,
		'canFilterAll'         => $can_filter_all,
	);
}

/**
 * Obtiene IDs de centros dentro del alcance regional seleccionado.
 *
 * @param int[] $region_ids IDs de región.
 * @return int[]
 */
function gnf_rest_get_region_scope_centros( $region_ids ) {
	$region_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $region_ids ) ) ) );
	if ( empty( $region_ids ) ) {
		return array();
	}

	return get_posts(
		array(
			'post_type'   => 'centro_educativo',
			'numberposts' => -1,
			'fields'      => 'ids',
			'tax_query'   => array(
				array(
					'taxonomy' => 'gn_region',
					'field'    => 'term_id',
					'terms'    => $region_ids,
				),
			),
		)
	);
}

function gnf_rest_notify_review_submission( $centro_id, $anio, $user_id = 0 ) {
	$region = get_post_meta( $centro_id, 'region', true );
	if ( empty( $region ) ) {
		$terms  = wp_get_post_terms( $centro_id, 'gn_region', array( 'fields' => 'ids' ) );
		$region = $terms ? $terms[0] : 0;
	}

	$reviewers    = gnf_rest_get_region_reviewers( (int) $region );
	$centro_title = get_the_title( $centro_id );
	$mensaje      = sprintf( 'La participación de "%s" (%d) fue enviada para revision.', $centro_title, (int) $anio );

	foreach ( $reviewers as $reviewer ) {
		gnf_insert_or_refresh_notification( $reviewer->ID, 'participacion_enviada', $mensaje, 'centro', $centro_id );
	}

	if ( $user_id ) {
		gnf_insert_or_refresh_notification( $user_id, 'participacion_enviada', 'Tu participación fue enviada a supervision y comite.', 'centro', $centro_id );
	}
}

function gnf_rest_notify_feedback_response( $entry, $tipo, $mensaje ) {
	if ( ! $entry ) {
		return;
	}

	$centro_id = (int) $entry->centro_id;
	$region    = get_post_meta( $centro_id, 'region', true );
	if ( empty( $region ) ) {
		$terms  = wp_get_post_terms( $centro_id, 'gn_region', array( 'fields' => 'ids' ) );
		$region = $terms ? $terms[0] : 0;
	}

	foreach ( gnf_rest_get_region_reviewers( (int) $region ) as $reviewer ) {
		gnf_insert_or_refresh_notification( $reviewer->ID, $tipo, $mensaje, 'reto_entry', (int) $entry->id );
	}
}

function gnf_rest_build_saved_form_state( $entry, $anio ) {
	if ( ! $entry ) {
		return array(
			'entry'      => null,
			'savedValues'=> array(),
			'savedAt'    => '',
		);
	}

	$data = ! empty( $entry->data ) ? json_decode( $entry->data, true ) : array();
	return array(
		'entry'       => gnf_format_reto_entry( $entry, $anio ),
		'savedValues' => (array) ( $data['__raw_values__'] ?? array() ),
		'savedAt'     => (string) ( $data['__saved_at'] ?? $entry->updated_at ?? '' ),
	);
}

function gnf_rest_get_wpforms_conditional_rules( $form_id ) {
	if ( ! function_exists( 'wpforms' ) ) {
		return array();
	}

	$form = wpforms()->form->get( absint( $form_id ) );
	if ( ! $form || empty( $form->post_content ) ) {
		return array();
	}

	$form_data = json_decode( $form->post_content, true );
	$fields    = $form_data['fields'] ?? array();
	if ( empty( $fields ) || ! is_array( $fields ) ) {
		return array();
	}

	$rules = array();
	foreach ( $fields as $field ) {
		$field_id = absint( $field['id'] ?? 0 );
		if ( ! $field_id || empty( $field['conditionals'] ) || ! is_array( $field['conditionals'] ) ) {
			continue;
		}

		$groups = array();
		foreach ( $field['conditionals'] as $group ) {
			if ( ! is_array( $group ) ) {
				continue;
			}

			$group_rules = array();
			foreach ( $group as $rule ) {
				$parent_field_id = absint( $rule['field'] ?? 0 );
				if ( ! $parent_field_id ) {
					continue;
				}

				$value = (string) ( $rule['value'] ?? '' );

				// WPForms stores choice indices (1-based) for radio/checkbox/select
				// conditional values. Resolve the index to the actual choice value
				// so the frontend can compare against the DOM value directly.
				$parent_field = $fields[ $parent_field_id ] ?? null;
				if ( $parent_field && ! empty( $parent_field['choices'] ) && is_array( $parent_field['choices'] ) && is_numeric( $value ) ) {
					$choice_index = (int) $value;
					$choice       = $parent_field['choices'][ $choice_index ] ?? null;
					if ( $choice ) {
						$value = (string) ( $choice['value'] ?? $choice['label'] ?? $value );
					}
				}

				$group_rules[] = array(
					'fieldId'  => $parent_field_id,
					'operator' => (string) ( $rule['operator'] ?? 'is' ),
					'value'    => $value,
				);
			}

			if ( ! empty( $group_rules ) ) {
				$groups[] = $group_rules;
			}
		}

		if ( ! empty( $groups ) ) {
			$rules[] = array(
				'fieldId'         => $field_id,
				'conditionalType' => (string) ( $field['conditional_type'] ?? 'show' ),
				'groups'          => $groups,
			);
		}
	}

	return $rules;
}

function gnf_rest_docente_form_html( WP_REST_Request $request ) {
	$reto_id   = (int) $request->get_param( 'reto_id' );
	$anio      = (int) ( $request->get_param( 'year' ) ?: gnf_get_active_year() );
	$user_id   = get_current_user_id();
	$centro_id = gnf_get_centro_for_docente( $user_id );
	$form_id   = gnf_get_reto_form_id_for_year( $reto_id, $anio );

	if ( ! $form_id ) {
		return new WP_Error( 'no_form', 'No hay formulario para este reto.', array( 'status' => 404 ) );
	}

	$entry       = $centro_id ? gnf_rest_get_docente_entry_row( $centro_id, $reto_id, $anio ) : null;
	$saved_state = gnf_rest_build_saved_form_state( $entry, $anio );
	$field_points = array();
	foreach ( gnf_get_reto_field_points( $reto_id, $anio ) as $field_id => $info ) {
		$field_points[] = array(
			'fieldId' => (int) $field_id,
			'label'   => (string) ( $info['label'] ?? '' ),
			'puntos'  => (int) ( $info['puntos'] ?? 0 ),
			'tipo'    => (string) ( $info['tipo'] ?? '' ),
		);
	}

	$html = do_shortcode( '[wpforms id="' . $form_id . '"]' );
	return array(
		'html'        => $html,
		'formId'      => (int) $form_id,
		'fieldPoints' => $field_points,
		'conditionalRules' => gnf_rest_get_wpforms_conditional_rules( $form_id ),
		'savedValues' => $saved_state['savedValues'],
		'savedAt'     => $saved_state['savedAt'],
		'entry'       => $saved_state['entry'],
		'reto'        => array(
			'id'                => $reto_id,
			'titulo'            => get_the_title( $reto_id ),
			'color'             => gnf_get_reto_color( $reto_id ),
			'iconUrl'           => gnf_get_reto_icon_url( $reto_id, 'medium', $anio ),
			'pdfUrl'            => gnf_get_reto_pdf_url( $reto_id, $anio ),
			'puntajeMaximo'     => gnf_get_reto_max_points( $reto_id, $anio ),
		),
	);
}

function gnf_rest_docente_autosave_reto( WP_REST_Request $request ) {
	$reto_id   = (int) $request->get_param( 'reto_id' );
	$anio      = (int) ( $request->get_param( 'year' ) ?: gnf_get_active_year() );
	$user_id   = get_current_user_id();
	$centro_id = gnf_get_centro_for_docente( $user_id );
	$reto_post = get_post( $reto_id );
	$fields    = $request->get_param( 'fields' );

	if ( ! $reto_post || 'reto' !== $reto_post->post_type ) {
		return new WP_Error( 'not_found', 'Reto no encontrado.', array( 'status' => 404 ) );
	}

	if ( ! $centro_id ) {
		return new WP_Error( 'missing_centro', 'No hay un centro asociado a este usuario.', array( 'status' => 400 ) );
	}

	if ( empty( $fields ) || ! is_array( $fields ) ) {
		return new WP_Error( 'missing_fields', 'No hay datos para guardar.', array( 'status' => 400 ) );
	}

	$raw_fields = array();
	foreach ( $fields as $field_id => $field ) {
		$fid = absint( $field_id );
		if ( ! $fid || ! is_array( $field ) ) {
			continue;
		}

		$value     = $field['value'] ?? '';
		$field_type = sanitize_key( (string) ( $field['type'] ?? 'text' ) );

		// File-upload values contain JSON from Dropzone — don't strip with
		// sanitize_text_field which could corrupt the JSON structure.
		if ( 'file-upload' === $field_type ) {
			if ( is_array( $value ) ) {
				$value = array_map( 'wp_strip_all_tags', array_map( 'strval', $value ) );
			} else {
				$value = wp_strip_all_tags( (string) $value );
			}
		} elseif ( is_array( $value ) ) {
			$value = array_map(
				static function ( $item ) {
					return sanitize_text_field( (string) $item );
				},
				$value
			);
		} else {
			$value = sanitize_text_field( (string) $value );
		}

		$raw_fields[ $fid ] = array(
			'id'    => $fid,
			'type'  => $field_type,
			'name'  => sanitize_text_field( (string) ( $field['name'] ?? ('Campo ' . $fid) ) ),
			'value' => $value,
		);
	}

	if ( empty( $raw_fields ) ) {
		return new WP_Error( 'missing_fields', 'No hay campos validos para guardar.', array( 'status' => 400 ) );
	}

	$normalized_fields         = gnf_normalize_fields( $raw_fields );
	$normalized_fields['anio'] = $anio;
	gnf_store_reto_entry( $reto_post, $normalized_fields, 0, array( 'id' => (int) ( $request->get_param( 'formId' ) ?: 0 ) ), $raw_fields, 'en_progreso', $centro_id );

	$entry = gnf_rest_get_docente_entry_row( $centro_id, $reto_id, $anio );
	if ( ! $entry ) {
		return new WP_Error( 'save_failed', 'No se pudo guardar el progreso.', array( 'status' => 500 ) );
	}

	$saved_state = gnf_rest_build_saved_form_state( $entry, $anio );
	gnf_log_audit_event(
		'docente_autosave_reto',
		array(
			'actor_user_id' => $user_id,
			'centro_id'     => $centro_id,
			'reto_id'       => $reto_id,
			'anio'          => $anio,
			'panel'         => 'docente',
			'message'       => 'Guardado automatico del reto.',
			'meta'          => array(
				'field_count' => count( $raw_fields ),
			),
		)
	);
	if ( function_exists( 'gnf_clear_admin_stats_cache' ) ) {
		gnf_clear_admin_stats_cache( $anio );
	}
	return array(
		'success' => true,
		'savedAt' => $saved_state['savedAt'],
		'entry'   => $saved_state['entry'],
	);
}

function gnf_rest_docente_remove_evidence( WP_REST_Request $request ) {
	$reto_id   = (int) $request->get_param( 'reto_id' );
	$anio      = (int) ( $request->get_param( 'year' ) ?: gnf_get_active_year() );
	$index     = $request->get_param( 'index' );
	$user_id   = get_current_user_id();
	$centro_id = gnf_get_centro_for_docente( $user_id );

	if ( ! $centro_id ) {
		return new WP_Error( 'missing_centro', 'No hay un centro asociado.', array( 'status' => 400 ) );
	}

	if ( null === $index || '' === $index ) {
		return new WP_Error( 'missing_index', 'Falta el indice de evidencia.', array( 'status' => 400 ) );
	}

	$index = (int) $index;
	$entry = gnf_rest_get_docente_entry_row( $centro_id, $reto_id, $anio );

	if ( ! $entry ) {
		return new WP_Error( 'not_found', 'Entrada no encontrada.', array( 'status' => 404 ) );
	}

	if ( in_array( $entry->estado, array( 'enviado', 'aprobado' ), true ) ) {
		return new WP_Error( 'not_editable', 'No se puede editar en este estado.', array( 'status' => 403 ) );
	}

	$evidencias = $entry->evidencias ? json_decode( $entry->evidencias, true ) : array();
	if ( ! is_array( $evidencias ) || ! isset( $evidencias[ $index ] ) ) {
		return new WP_Error( 'invalid_index', 'Indice de evidencia invalido.', array( 'status' => 400 ) );
	}

	$removed = $evidencias[ $index ];

	// Delete the physical file if it exists.
	if ( ! empty( $removed['path_local'] ) && file_exists( $removed['path_local'] ) ) {
		$uploads_base = wp_normalize_path( wp_upload_dir()['basedir'] );
		$norm_path    = wp_normalize_path( $removed['path_local'] );
		if ( 0 === strpos( $norm_path, $uploads_base ) ) {
			@unlink( $norm_path ); // phpcs:ignore
		}
	}

	array_splice( $evidencias, $index, 1 );

	global $wpdb;
	$table = $wpdb->prefix . 'gn_reto_entries';
	$wpdb->update(
		$table,
		array(
			'evidencias' => wp_json_encode( array_values( $evidencias ) ),
			'updated_at' => current_time( 'mysql' ),
		),
		array( 'id' => (int) $entry->id ),
		array( '%s', '%s' ),
		array( '%d' )
	);

	// Recalculate score.
	$updated_entry = gnf_rest_get_docente_entry_row( $centro_id, $reto_id, $anio );
	if ( $updated_entry && function_exists( 'gnf_recalcular_puntaje_reto' ) ) {
		if ( function_exists( 'gnf_refresh_reto_entry_score' ) ) {
			gnf_refresh_reto_entry_score( $updated_entry );
		}
		gnf_clear_supervisor_cache();
		$updated_entry = gnf_rest_get_docente_entry_row( $centro_id, $reto_id, $anio );
	}

	$saved_state = gnf_rest_build_saved_form_state( $updated_entry, $anio );
	return array(
		'success' => true,
		'entry'   => $saved_state['entry'],
	);
}

function gnf_rest_docente_finalize_reto( WP_REST_Request $request ) {
	$reto_id   = (int) $request->get_param( 'reto_id' );
	$user_id   = get_current_user_id();
	$centro_id = gnf_get_centro_for_docente( $user_id );
	$anio      = (int) ( $request->get_param( 'year' ) ?: gnf_get_active_year() );

	global $wpdb;
	$table = $wpdb->prefix . 'gn_reto_entries';
	$entry = gnf_rest_get_docente_entry_row( $centro_id, $reto_id, $anio );

	if ( ! $entry ) {
		return new WP_Error( 'missing_entry', 'Guarda al menos una evidencia antes de finalizar el reto.', array( 'status' => 400 ) );
	}

	if ( in_array( $entry->estado, array( 'enviado', 'aprobado' ), true ) ) {
		return array( 'success' => true );
	}

	$wpdb->update(
		$table,
		array( 'estado' => 'completo', 'updated_at' => current_time( 'mysql' ) ),
		array( 'id' => $entry->id )
	);

	if ( ! empty( $entry->supervisor_notes ) ) {
		gnf_rest_notify_feedback_response(
			$entry,
			'feedback_actualizado',
			sprintf(
				'El centro "%s" actualizo el reto "%s" despues del feedback.',
				get_the_title( $centro_id ),
				get_the_title( $reto_id )
			)
		);
	}

	gnf_log_audit_event(
		'docente_finalize_reto',
		array(
			'actor_user_id' => $user_id,
			'centro_id'     => $centro_id,
			'reto_id'       => $reto_id,
			'anio'          => $anio,
			'panel'         => 'docente',
			'message'       => 'Reto marcado como completo.',
		)
	);
	if ( function_exists( 'gnf_clear_admin_stats_cache' ) ) {
		gnf_clear_admin_stats_cache( $anio );
	}

	return array( 'success' => true );
}

function gnf_rest_docente_reopen_reto( WP_REST_Request $request ) {
	$reto_id   = (int) $request->get_param( 'reto_id' );
	$user_id   = get_current_user_id();
	$centro_id = gnf_get_centro_for_docente( $user_id );
	$anio      = (int) ( $request->get_param( 'year' ) ?: gnf_get_active_year() );

	global $wpdb;
	$table = $wpdb->prefix . 'gn_reto_entries';

	$entry = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE centro_id = %d AND reto_id = %d AND anio = %d",
			$centro_id,
			$reto_id,
			$anio
		)
	);

	if ( ! $entry || 'correccion' !== $entry->estado ) {
		return new WP_Error( 'invalid_state', 'Este reto no está en estado de corrección.', array( 'status' => 400 ) );
	}

	$wpdb->update(
		$table,
		array(
			'estado'           => 'en_progreso',
			'updated_at'       => current_time( 'mysql' ),
		),
		array( 'id' => $entry->id )
	);
	if ( function_exists( 'gnf_clear_admin_stats_cache' ) ) {
		gnf_clear_admin_stats_cache( $anio );
	}

	return array( 'success' => true );
}

function gnf_rest_docente_submit( WP_REST_Request $request ) {
	$user_id   = get_current_user_id();
	$anio      = (int) ( $request->get_param( 'year' ) ?: gnf_get_active_year() );
	$centro_id = gnf_get_centro_for_docente( $user_id );

	if ( ! gnf_are_all_retos_complete( $centro_id, $anio ) ) {
		return new WP_Error( 'incomplete', 'Completa todos los retos antes de enviar la participación.', array( 'status' => 400 ) );
	}

	global $wpdb;
	$table = $wpdb->prefix . 'gn_reto_entries';

	$wpdb->update(
		$table,
		array( 'estado' => 'enviado', 'updated_at' => current_time( 'mysql' ) ),
		array( 'centro_id' => $centro_id, 'anio' => $anio, 'estado' => 'completo' )
	);

	gnf_rest_notify_review_submission( $centro_id, $anio, $user_id );
	gnf_log_audit_event(
		'docente_submit_participacion',
		array(
			'actor_user_id' => $user_id,
			'centro_id'     => $centro_id,
			'anio'          => $anio,
			'panel'         => 'docente',
			'message'       => 'Participacion anual enviada a revision.',
		)
	);
	if ( function_exists( 'gnf_clear_admin_stats_cache' ) ) {
		gnf_clear_admin_stats_cache( $anio );
	}

	return array( 'success' => true );
}

// ═══════════════════════════════════════════════════════════════════════
// Supervisor endpoints
// ═══════════════════════════════════════════════════════════════════════

function gnf_rest_supervisor_dashboard( WP_REST_Request $request ) {
	$anio         = (int) ( $request->get_param( 'year' ) ?: gnf_get_active_year() );
	$user_id      = get_current_user_id();
	$region_scope = gnf_rest_get_user_region_scope( $user_id, (int) $request->get_param( 'region' ) );

	// Count entries by estado for supervisor's region.
	global $wpdb;
	$entries_table = $wpdb->prefix . 'gn_reto_entries';
	$mat_table     = $wpdb->prefix . 'gn_matriculas';

	$centro_ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT centro_id FROM {$mat_table} WHERE anio = %d",
			$anio
		)
	);

	// Filter by region for all supervisor/comité users.
	$region_centros = gnf_rest_get_region_scope_centros( $region_scope['selectedRegionIds'] ?? array() );
	$centro_ids     = array_values(
		array_intersect(
			array_map( 'intval', (array) $centro_ids ),
			array_map( 'intval', (array) $region_centros )
		)
	);

	$stats = array( 'centros' => count( $centro_ids ), 'pendientes' => 0, 'aprobados' => 0, 'correccion' => 0, 'enviados' => 0, 'enProgreso' => 0 );

	if ( ! empty( $centro_ids ) ) {
		$placeholders = implode( ',', array_fill( 0, count( $centro_ids ), '%d' ) );
		$rows         = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT estado, COUNT(*) as cnt FROM {$entries_table} WHERE centro_id IN ({$placeholders}) AND anio = %d GROUP BY estado",
				array_merge( $centro_ids, array( $anio ) )
			)
		);
		foreach ( $rows as $row ) {
			if ( 'aprobado' === $row->estado ) {
				$stats['aprobados'] = (int) $row->cnt;
			} elseif ( 'correccion' === $row->estado ) {
				$stats['correccion'] = (int) $row->cnt;
			} elseif ( 'enviado' === $row->estado ) {
				$stats['enviados'] = (int) $row->cnt;
				$stats['pendientes'] = (int) $row->cnt;
			} elseif ( in_array( $row->estado, array( 'en_progreso', 'no_iniciado', 'completo' ), true ) ) {
				$stats['enProgreso'] += (int) $row->cnt;
			}
		}
	}

	return array(
		'stats'              => $stats,
		'regionName'         => (string) ( $region_scope['selectedLabel'] ?? '' ),
		'selectedRegionId'   => (int) ( $region_scope['selectedRegionId'] ?? 0 ),
		'availableRegionIds' => array_values( array_map( 'intval', (array) ( $region_scope['availableRegionIds'] ?? array() ) ) ),
		'canFilterAll'       => ! empty( $region_scope['canFilterAll'] ),
	);
}

function gnf_rest_supervisor_centros( WP_REST_Request $request ) {
	$anio         = (int) ( $request->get_param( 'year' ) ?: gnf_get_active_year() );
	$circuito     = sanitize_text_field( $request->get_param( 'circuito' ) ?? '' );
	$user_id      = get_current_user_id();
	$region_scope = gnf_rest_get_user_region_scope( $user_id, (int) $request->get_param( 'region' ) );

	// Only centros with active matricula.
	$centros_con_matricula = gnf_get_centros_with_matricula( $anio );
	if ( empty( $centros_con_matricula ) ) {
		return array();
	}

	$region_ids = array_values( array_map( 'intval', (array) ( $region_scope['selectedRegionIds'] ?? array() ) ) );
	if ( empty( $region_ids ) ) {
		return array();
	}

	$centros_args = array(
		'post_type'      => 'centro_educativo',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'post__in'       => $centros_con_matricula,
	);

	// Region-scope for all supervisor/comité users.
	$centros_args['tax_query'] = array(
		array(
			'taxonomy' => 'gn_region',
			'field'    => 'term_id',
			'terms'    => $region_ids,
		),
	);

	if ( $circuito ) {
		$centros_args['meta_query'] = array(
			array(
				'key'   => 'circuito',
				'value' => $circuito,
			),
		);
	}

	$centros = new WP_Query( $centros_args );

	if ( ! $centros->have_posts() ) {
		return array();
	}

	$centro_ids = wp_list_pluck( $centros->posts, 'ID' );
	$counts     = gnf_rest_get_entry_counts_by_centro( $centro_ids, $anio );

	$result = array();
	foreach ( $centros->posts as $post ) {
		$result[] = gnf_rest_build_centro_with_stats( $post->ID, $anio, $counts );
	}

	wp_reset_postdata();
	return $result;
}

function gnf_rest_supervisor_centro_detail( WP_REST_Request $request ) {
	$centro_id = (int) $request->get_param( 'id' );
	$anio      = (int) ( $request->get_param( 'year' ) ?: gnf_get_active_year() );

	$post = get_post( $centro_id );
	if ( ! $post || 'centro_educativo' !== $post->post_type ) {
		return new WP_Error( 'not_found', 'Centro no encontrado.', array( 'status' => 404 ) );
	}

	if ( ! gnf_user_can_access_centro( get_current_user_id(), $centro_id ) && ! current_user_can( 'manage_options' ) ) {
		return new WP_Error( 'forbidden', 'Sin acceso a este centro.', array( 'status' => 403 ) );
	}

	// Get all entries for this centro/year.
	global $wpdb;
	$table       = $wpdb->prefix . 'gn_reto_entries';
	$entries_raw = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE centro_id = %d AND anio = %d",
			$centro_id,
			$anio
		)
	);

	$entries         = array();
	$retos_with_entry = array();
	foreach ( $entries_raw as $entry ) {
		$entries[]                              = gnf_format_reto_entry( $entry, $anio );
		$retos_with_entry[ (int) $entry->reto_id ] = true;
	}

	// Include stub entries for selected retos that don't have submissions yet.
	$retos_seleccionados = gnf_get_centro_retos_seleccionados( $centro_id, $anio );
	foreach ( (array) $retos_seleccionados as $reto_id ) {
		if ( isset( $retos_with_entry[ (int) $reto_id ] ) ) {
			continue;
		}
		$reto    = get_post( $reto_id );
		$max_pts = gnf_get_reto_max_points( $reto_id, $anio );
		$entries[] = array(
			'id'              => 0,
			'retoId'          => (int) $reto_id,
			'retoTitulo'      => $reto ? $reto->post_title : '',
			'retoColor'       => $reto ? gnf_get_reto_color( $reto->ID ) : '',
			'retoIconUrl'     => $reto ? gnf_get_reto_icon_url( $reto->ID, 'thumbnail', $anio ) : '',
			'centroId'        => $centro_id,
			'userId'          => 0,
			'anio'            => $anio,
			'estado'          => 'sin_evidencias',
			'puntaje'         => 0,
			'puntajeMaximo'   => $max_pts,
			'supervisorNotes' => '',
			'evidencias'      => array(),
			'responses'       => gnf_build_reto_form_responses( (int) $reto_id, $anio, array(), array() ),
			'createdAt'       => null,
			'updatedAt'       => null,
		);
	}

	return array(
		'centro'  => gnf_rest_build_centro_with_stats( $centro_id, $anio, gnf_rest_get_entry_counts_by_centro( array( $centro_id ), $anio ) ),
		'entries' => $entries,
	);
}

/**
 * Supervisor reviews an individual evidence (approve or reject).
 *
 * POST /gnf/v1/supervisor/evidence/{entry_id}/{evidence_index}
 * Body: { "action": "aprobar"|"rechazar", "comment": "string" }
 */
function gnf_rest_supervisor_review_evidence( WP_REST_Request $request ) {
	$entry_id = (int) $request->get_param( 'entry_id' );
	$ev_index = (int) $request->get_param( 'evidence_index' );
	$action   = sanitize_text_field( $request->get_param( 'action' ) ?? '' );
	$comment  = sanitize_textarea_field( $request->get_param( 'comment' ) ?? '' );

	if ( ! in_array( $action, array( 'aprobar', 'rechazar' ), true ) ) {
		return new WP_Error( 'invalid_action', 'Acción inválida. Use "aprobar" o "rechazar".', array( 'status' => 400 ) );
	}
	if ( 'rechazar' === $action && empty( $comment ) ) {
		return new WP_Error( 'missing_comment', 'El comentario es requerido al rechazar.', array( 'status' => 400 ) );
	}

	global $wpdb;
	$table = $wpdb->prefix . 'gn_reto_entries';
	$entry = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $entry_id ) );

	if ( ! $entry ) {
		return new WP_Error( 'not_found', 'Entrada no encontrada.', array( 'status' => 404 ) );
	}
	if ( ! gnf_user_can_access_centro( get_current_user_id(), $entry->centro_id ) && ! current_user_can( 'manage_options' ) ) {
		return new WP_Error( 'forbidden', 'Sin acceso a este centro.', array( 'status' => 403 ) );
	}

	$evidencias = json_decode( $entry->evidencias ?? '[]', true );
	// Enrich old evidence data with puntos/estado from config.
	$evidencias = gnf_enrich_evidencias( $evidencias, $entry->reto_id, $entry->anio );
	if ( ! is_array( $evidencias ) || ! isset( $evidencias[ $ev_index ] ) ) {
		return new WP_Error( 'invalid_index', 'Índice de evidencia inválido.', array( 'status' => 400 ) );
	}

	$ev = $evidencias[ $ev_index ];

	// Check against enriched puntos (handles old data without puntos field).
	if ( null === ( $ev['puntos'] ?? null ) ) {
		return new WP_Error( 'informational', 'Esta evidencia es informativa y no puede ser revisada.', array( 'status' => 400 ) );
	}
	if ( ! empty( $ev['replaced'] ) ) {
		return new WP_Error( 'replaced', 'Esta evidencia fue reemplazada.', array( 'status' => 400 ) );
	}

	$now     = current_time( 'mysql' );
	$user_id = get_current_user_id();

	$evidencias[ $ev_index ]['estado']             = 'aprobar' === $action ? 'aprobada' : 'rechazada';
	$evidencias[ $ev_index ]['supervisor_comment']  = $comment ?: null;
	$evidencias[ $ev_index ]['reviewed_by']         = $user_id;
	$evidencias[ $ev_index ]['reviewed_at']         = $now;

	$wpdb->update(
		$table,
		array(
			'evidencias' => wp_json_encode( $evidencias, JSON_UNESCAPED_UNICODE ),
			'updated_at' => $now,
		),
		array( 'id' => $entry_id ),
		array( '%s', '%s' ),
		array( '%d' )
	);

	$updated_entry = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $entry_id ) );
	gnf_refresh_reto_entry_score( $updated_entry );
	gnf_clear_supervisor_cache();

	$reto = get_post( $entry->reto_id );
	$reto_title = $reto ? $reto->post_title : '';
	$ev_nombre  = $ev['nombre'] ?? 'archivo';
	if ( 'aprobar' === $action ) {
		gnf_insert_notification(
			$entry->user_id,
			'evidencia_aprobada',
			sprintf( 'Tu evidencia "%s" del reto "%s" fue aprobada.', $ev_nombre, $reto_title ),
			'reto_entry',
			$entry_id
		);
	} else {
		gnf_insert_notification(
			$entry->user_id,
			'evidencia_rechazada',
			sprintf( 'Tu evidencia "%s" del reto "%s" fue rechazada: %s', $ev_nombre, $reto_title, $comment ),
			'reto_entry',
			$entry_id
		);
	}

	gnf_log_audit_event(
		'aprobar' === $action ? 'supervisor_approve_evidence' : 'supervisor_reject_evidence',
		array(
			'actor_user_id'  => $user_id,
			'centro_id'      => (int) $entry->centro_id,
			'reto_id'        => (int) $entry->reto_id,
			'anio'           => (int) $entry->anio,
			'panel'          => 'supervisor',
			'message'        => 'aprobar' === $action ? 'Supervisor aprobó evidencia.' : 'Supervisor rechazó evidencia.',
			'meta'           => array(
				'entry_id'       => $entry_id,
				'evidence_index' => $ev_index,
				'evidence_name'  => $ev_nombre,
				'comment'        => $comment,
			),
		)
	);

	if ( function_exists( 'gnf_clear_admin_stats_cache' ) ) {
		gnf_clear_admin_stats_cache( (int) $entry->anio );
	}

	$computed = gnf_get_reto_entry_computed_status( $updated_entry );

	return array(
		'success'       => true,
		'evidence'      => $evidencias[ $ev_index ],
		'entry_puntaje' => (int) $updated_entry->puntaje,
		'entry_status'  => $computed,
	);
}

// ═══════════════════════════════════════════════════════════════════════
// Admin endpoints
// ═══════════════════════════════════════════════════════════════════════

function gnf_rest_admin_get_primary_role( WP_User $user ) {
	$priority = array( 'administrator', 'docente', 'supervisor', 'comite_bae' );

	foreach ( $priority as $role ) {
		if ( in_array( $role, (array) $user->roles, true ) ) {
			return $role;
		}
	}

	return $user->roles[0] ?? 'docente';
}

function gnf_rest_admin_get_user_status( WP_User $user, $role ) {
	if ( 'docente' === $role ) {
		return gnf_get_docente_estado( $user->ID ) ?: 'activo';
	}

	if ( in_array( $role, array( 'supervisor', 'comite_bae' ), true ) ) {
		return gnf_get_supervisor_estado( $user->ID ) ?: 'activo';
	}

	return 'activo';
}

function gnf_rest_admin_build_user_item( WP_User $user ) {
	$role            = gnf_rest_admin_get_primary_role( $user );
	$status          = gnf_rest_admin_get_user_status( $user, $role );
	$centro_id       = 'docente' === $role ? gnf_get_centro_for_docente( $user->ID ) : 0;
	$region_ids      = function_exists( 'gnf_get_user_regions' ) ? gnf_get_user_regions( $user->ID ) : array_filter( array( gnf_get_user_region( $user->ID ) ) );
	$region_id       = ! empty( $region_ids ) ? (int) $region_ids[0] : 0;
	$can_impersonate = current_user_can( 'manage_options' ) && 'administrator' !== $role && get_current_user_id() !== (int) $user->ID;

	if ( empty( $region_ids ) && $centro_id ) {
		$region_ids = array_filter( array( (int) get_post_meta( $centro_id, 'region', true ) ) );
		$region_id  = ! empty( $region_ids ) ? (int) $region_ids[0] : 0;
	}

	$region_names = function_exists( 'gnf_get_user_region_names' ) ? gnf_get_user_region_names( $user->ID ) : array();
	if ( empty( $region_names ) && $region_id ) {
		$region_term  = get_term( $region_id, 'gn_region' );
		$region_names = ( $region_term && ! is_wp_error( $region_term ) ) ? array( $region_term->name ) : array();
	}

	$impersonate_url = '';
	if ( $can_impersonate ) {
		$impersonate_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=gnf_impersonate&user_id=' . $user->ID ),
			'gnf_impersonate'
		);
	}

	return array(
		'id'             => (int) $user->ID,
		'name'           => $user->display_name,
		'email'          => $user->user_email,
		'role'           => $role,
		'status'         => $status,
		'telefono'       => (string) get_user_meta( $user->ID, 'gnf_telefono', true ),
		'cargo'          => (string) get_user_meta( $user->ID, 'gnf_cargo', true ),
		'identificacion' => (string) get_user_meta( $user->ID, 'gnf_identificacion', true ),
		'centroId'       => $centro_id,
		'regionId'       => $region_id ? (int) $region_id : 0,
		'regionIds'      => array_values( array_map( 'intval', (array) $region_ids ) ),
		'registeredAt'   => $user->user_registered,
		'centroName'     => $centro_id ? get_the_title( $centro_id ) : '',
		'regionName'     => implode( ', ', array_filter( array_map( 'strval', $region_names ) ) ),
		'regionNames'    => array_values( array_map( 'strval', (array) $region_names ) ),
		'panelUrl'       => function_exists( 'gnf_get_default_panel_url' ) ? gnf_get_default_panel_url( $user ) : home_url(),
		'canImpersonate' => $can_impersonate,
		'impersonateUrl' => $impersonate_url,
	);
}

function gnf_rest_admin_update_user( WP_REST_Request $request ) {
	$user_id = (int) $request->get_param( 'id' );
	$user    = get_userdata( $user_id );

	if ( ! $user ) {
		return new WP_Error( 'not_found', 'Usuario no encontrado.', array( 'status' => 404 ) );
	}

	$role  = gnf_rest_admin_get_primary_role( $user );
	$name  = sanitize_text_field( (string) $request->get_param( 'name' ) );
	$email = sanitize_email( (string) $request->get_param( 'email' ) );

	if ( '' === $name || '' === $email ) {
		return new WP_Error( 'missing_fields', 'Nombre y correo son obligatorios.', array( 'status' => 400 ) );
	}

	$email_owner = email_exists( $email );
	if ( $email_owner && (int) $email_owner !== $user_id ) {
		return new WP_Error( 'email_exists', 'Ese correo ya pertenece a otra cuenta.', array( 'status' => 400 ) );
	}

	$updated = wp_update_user(
		array(
			'ID'           => $user_id,
			'display_name' => $name,
			'user_email'   => $email,
		)
	);
	if ( is_wp_error( $updated ) ) {
		return $updated;
	}

	update_user_meta( $user_id, 'gnf_telefono', sanitize_text_field( (string) $request->get_param( 'telefono' ) ) );
	update_user_meta( $user_id, 'gnf_cargo', sanitize_text_field( (string) $request->get_param( 'cargo' ) ) );
	update_user_meta( $user_id, 'gnf_identificacion', sanitize_text_field( (string) $request->get_param( 'identificacion' ) ) );

	if ( 'docente' === $role ) {
		$status = sanitize_key( (string) $request->get_param( 'status' ) );
		if ( in_array( $status, array( 'activo', 'pendiente' ), true ) ) {
			update_user_meta( $user_id, 'gnf_docente_status', $status );
		}

		$new_centro_id  = absint( $request->get_param( 'centroId' ) );
		$prev_centro_id = gnf_get_centro_for_docente( $user_id );
		if ( $new_centro_id && 'centro_educativo' !== get_post_type( $new_centro_id ) ) {
			return new WP_Error( 'invalid_centro', 'El centro educativo seleccionado no es valido.', array( 'status' => 400 ) );
		}
		if ( $new_centro_id && gnf_is_centro_claimed_by_other_docente( $new_centro_id, $user_id ) ) {
			return gnf_rest_get_centro_claimed_error( $new_centro_id );
		}

		if ( $prev_centro_id && $prev_centro_id !== $new_centro_id ) {
			gnf_detach_docente_from_centro( $user_id, $prev_centro_id );
		}

		if ( $new_centro_id ) {
			gnf_sync_docente_centro_assignment( $user_id, $new_centro_id, array( 'sync_correo_institucional' => true ) );
		} else {
			gnf_clear_docente_centro_assignment( $user_id, $prev_centro_id );
		}
	} elseif ( in_array( $role, array( 'supervisor', 'comite_bae' ), true ) ) {
		$status = sanitize_key( (string) $request->get_param( 'status' ) );
		if ( in_array( $status, array( 'activo', 'pendiente' ), true ) ) {
			update_user_meta( $user_id, 'gnf_supervisor_status', $status );
		}

		$region_ids = 'comite_bae' === $role
			? gnf_normalize_region_ids( $request->get_param( 'regionIds' ) )
			: array( absint( $request->get_param( 'regionId' ) ) );

		if ( empty( $region_ids ) ) {
			$region_ids = gnf_normalize_region_ids( $request->get_param( 'regionId' ) );
		}

		if ( empty( $region_ids ) ) {
			return new WP_Error( 'missing_region', 'Debes asignar al menos una región.', array( 'status' => 400 ) );
		}

		gnf_set_user_regions( $user_id, $region_ids );
	}

	gnf_log_audit_event(
		'admin_update_user',
		array(
			'actor_user_id'  => get_current_user_id(),
			'target_user_id' => $user_id,
			'panel'          => 'admin',
			'message'        => 'Admin actualizo un usuario.',
			'meta'           => array(
				'role' => $role,
			),
		)
	);

	if ( function_exists( 'gnf_clear_admin_stats_cache' ) ) {
		gnf_clear_admin_stats_cache();
	}

	return gnf_rest_admin_build_user_item( get_userdata( $user_id ) );
}

function gnf_rest_admin_users() {
	$users = get_users(
		array(
			'orderby'  => 'registered',
			'order'    => 'DESC',
			'role__in' => array( 'docente', 'supervisor', 'comite_bae', 'administrator' ),
		)
	);

	$result = array();
	foreach ( $users as $user ) {
		$result[] = gnf_rest_admin_build_user_item( $user );
	}

	return $result;
}

function gnf_rest_admin_stats( WP_REST_Request $request ) {
	$anio = (int) ( $request->get_param( 'year' ) ?: gnf_get_active_year() );

	$stats = function_exists( 'gnf_get_admin_stats_summary' ) ? gnf_get_admin_stats_summary( $anio ) : array();

	return array(
		'centros'      => (int) ( $stats['centros_activos'] ?? 0 ),
		'pendientes'   => (int) ( $stats['retos_enviados'] ?? 0 ),
		'aprobados'    => (int) ( $stats['retos_aprobados'] ?? 0 ),
		'correccion'   => (int) ( $stats['retos_correccion'] ?? 0 ),
		'enviados'     => (int) ( $stats['retos_enviados'] ?? 0 ),
		'enProgreso'   => (int) ( $stats['retos_en_progreso'] ?? 0 ),
		'totalUsers'   => (int) ( $stats['total_docentes'] ?? 0 ),
		'pendingUsers' => (int) ( $stats['pending_docentes'] ?? 0 ),
	);
}

function gnf_rest_admin_pending_users() {
	return array_values(
		array_filter(
			gnf_rest_admin_users(),
			static function ( $user ) {
				return 'pendiente' === ( $user['status'] ?? '' );
			}
		)
	);
}

function gnf_rest_admin_approve_user( WP_REST_Request $request ) {
	$user_id = (int) $request->get_param( 'id' );
	$user    = get_userdata( $user_id );

	if ( ! $user ) {
		return new WP_Error( 'not_found', 'Usuario no encontrado.', array( 'status' => 404 ) );
	}

	if ( in_array( 'docente', (array) $user->roles, true ) ) {
		if ( function_exists( 'gnf_approve_docente' ) ) {
			gnf_approve_docente( $user_id );
		} else {
			update_user_meta( $user_id, 'gnf_docente_status', 'activo' );
		}

		$centro_id = gnf_get_centro_for_docente( $user_id );
		if ( $centro_id ) {
			gnf_sync_docente_centro_assignment( $user_id, $centro_id, array( 'sync_correo_institucional' => true ) );
			if ( 'pending' === get_post_status( $centro_id ) ) {
				wp_update_post(
					array(
						'ID'          => $centro_id,
						'post_status' => 'publish',
					)
				);
				update_post_meta( $centro_id, 'estado_centro', 'activo' );
			}
		}
	} elseif ( in_array( 'supervisor', (array) $user->roles, true ) || in_array( 'comite_bae', (array) $user->roles, true ) ) {
		gnf_approve_supervisor( $user_id );
	} else {
		return new WP_Error( 'invalid_role', 'El usuario no pertenece a un rol aprobable.', array( 'status' => 400 ) );
	}

	gnf_log_audit_event(
		'admin_approve_user',
		array(
			'target_user_id' => $user_id,
			'message'        => 'Admin aprobo un usuario.',
			'meta'           => array(
				'role' => gnf_rest_admin_get_primary_role( $user ),
			),
		)
	);
	if ( function_exists( 'gnf_clear_admin_stats_cache' ) ) {
		gnf_clear_admin_stats_cache();
	}

	return array( 'success' => true );
}

function gnf_rest_admin_reject_user( WP_REST_Request $request ) {
	$user_id = (int) $request->get_param( 'id' );
	$user    = get_userdata( $user_id );
	$centro_id = $user ? gnf_get_centro_for_docente( $user_id ) : 0;

	gnf_log_audit_event(
		'admin_reject_user',
		array(
			'target_user_id' => $user_id,
			'message'        => 'Admin rechazo un usuario.',
			'meta'           => array(
				'role' => $user ? gnf_rest_admin_get_primary_role( $user ) : '',
			),
		)
	);

	delete_user_meta( $user_id, 'gnf_docente_status' );
	delete_user_meta( $user_id, 'gnf_supervisor_status' );
	delete_user_meta( $user_id, 'gnf_docente_estado' );
	delete_user_meta( $user_id, 'gnf_supervisor_estado' );

	if ( $centro_id ) {
		gnf_clear_docente_centro_assignment( $user_id, $centro_id );
	}

	// Optionally delete the user.
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $user_id );
	if ( function_exists( 'gnf_clear_admin_stats_cache' ) ) {
		gnf_clear_admin_stats_cache();
	}

	return array( 'success' => true );
}

function gnf_rest_admin_centros( WP_REST_Request $request ) {
	$anio         = (int) ( $request->get_param( 'year' ) ?: gnf_get_active_year() );
	$region       = (int) ( $request->get_param( 'region' ) ?: 0 );
	$search       = sanitize_text_field( $request->get_param( 's' ) ?? '' );
	$estado       = sanitize_key( (string) ( $request->get_param( 'estado' ) ?? '' ) );
	$registration = gnf_normalize_centro_registration_filter( $request->get_param( 'registration' ) ?? 'registered' );

	$centros_con_matricula = gnf_get_centros_with_matricula( $anio );
	$centro_ids            = gnf_filter_centro_ids_by_registration( $centros_con_matricula, $registration );

	$args = array(
		'post_type'      => 'centro_educativo',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'post__in'       => ! empty( $centro_ids ) ? $centro_ids : array( 0 ),
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

	$query = new WP_Query( $args );

	if ( ! $query->have_posts() ) {
		return array();
	}

	$centro_ids = wp_list_pluck( $query->posts, 'ID' );
	$counts     = gnf_rest_get_entry_counts_by_centro( $centro_ids, $anio );

	$items = array();
	foreach ( $query->posts as $post ) {
		$items[] = gnf_rest_build_centro_with_stats( $post->ID, $anio, $counts );
	}

	wp_reset_postdata();
	return $items;
}

function gnf_rest_admin_retos( WP_REST_Request $request ) {
	$anio = (int) ( $request->get_param( 'year' ) ?: gnf_get_active_year() );

	if ( function_exists( 'gnf_get_retos_for_admin' ) ) {
		$retos_data = gnf_get_retos_for_admin( $anio );
	} else {
		return array();
	}

	$result = array();
	foreach ( $retos_data as $item ) {
		$reto  = $item['reto'];
		$stats = $item['stats'];

		$result[] = array(
			'id'           => $reto->ID,
			'titulo'       => $reto->post_title,
			'color'        => gnf_get_reto_color( $reto->ID ),
			'iconUrl'      => gnf_get_reto_icon_url( $reto->ID, 'thumbnail', $anio ),
			'total'        => (int) ( $stats->total ?? 0 ),
			'aprobados'    => (int) ( $stats->aprobados ?? 0 ),
			'enviados'     => (int) ( $stats->enviados ?? 0 ),
			'correccion'   => (int) ( $stats->correccion ?? 0 ),
			'enProgreso'   => (int) ( $stats->en_progreso ?? 0 ),
			'puntosTotales' => (int) ( $stats->puntos_totales ?? 0 ),
		);
	}

	return $result;
}

function gnf_rest_admin_reports( WP_REST_Request $request ) {
	$anio = (int) ( $request->get_param( 'year' ) ?: gnf_get_active_year() );
	$centro_ids = gnf_get_centros_with_matricula( $anio );
	$counts     = gnf_rest_get_entry_counts_by_centro( $centro_ids, $anio );
	$centros    = array();

	foreach ( (array) $centro_ids as $centro_id ) {
		if ( 'publish' !== get_post_status( $centro_id ) ) {
			continue;
		}
		$centros[] = gnf_rest_build_centro_with_stats( $centro_id, $anio, $counts );
	}

	$total_centros   = count( $centros );
	$total_aprobados = 0;
	$total_estrellas = 0;
	$total_puntaje   = 0;

	foreach ( $centros as $centro ) {
		$total_aprobados += (int) $centro['aprobados'];
		$total_estrellas += (int) $centro['annual']['estrellaFinal'];
		$total_puntaje   += (int) $centro['annual']['puntajeTotal'];
	}

	return array(
		'summary' => array(
			'totalCentros'      => $total_centros,
			'totalAprobados'    => $total_aprobados,
			'promedioEstrellas' => $total_centros ? round( $total_estrellas / $total_centros, 2 ) : 0,
			'promedioPuntaje'   => $total_centros ? round( $total_puntaje / $total_centros, 2 ) : 0,
		),
		'centros' => $centros,
	);
}

function gnf_rest_admin_dre_list() {
	$terms = get_terms( array( 'taxonomy' => 'gn_region', 'hide_empty' => false ) );
	$dres  = array();

	if ( ! is_wp_error( $terms ) ) {
		foreach ( $terms as $term ) {
			$current = get_term_meta( $term->term_id, 'gnf_dre_activa', true );
			$dres[] = array(
				'id'      => $term->term_id,
				'name'    => $term->name,
				'slug'    => $term->slug,
				'enabled' => '' === $current ? true : (bool) $current,
			);
		}
	}

	return $dres;
}

function gnf_rest_admin_dre_toggle( WP_REST_Request $request ) {
	$dre_id  = (int) $request->get_param( 'id' );
	$current = get_term_meta( $dre_id, 'gnf_dre_activa', true );
	$current = '' === $current ? true : (bool) $current;
	$new     = ! $current;

	update_term_meta( $dre_id, 'gnf_dre_activa', $new ? '1' : '0' );
	gnf_log_audit_event(
		'admin_toggle_region',
		array(
			'panel'   => 'admin',
			'message' => $new ? 'Region habilitada.' : 'Region deshabilitada.',
			'meta'    => array(
				'region_id' => $dre_id,
				'enabled'   => $new,
			),
		)
	);

	return array( 'success' => true, 'enabled' => $new );
}

function gnf_rest_admin_seed_retos( WP_REST_Request $request ) {
	$anio = gnf_normalize_year( $request->get_param( 'year' ) );

	require_once GNF_PATH . 'seeders/seed-retos.php';

	$result   = gnf_run_retos_seeder( false );
	$backfill = function_exists( 'gnf_backfill_required_retos_for_active_centros' )
		? gnf_backfill_required_retos_for_active_centros( $anio )
		: array( 'centros' => 0, 'actualizados' => 0 );

	gnf_log_audit_event(
		'admin_seed_retos',
		array(
			'panel'   => 'admin',
			'anio'    => $anio,
			'message' => sprintf( 'Seeder de retos ejecutado para %d.', $anio ),
			'meta'    => array(
				'backfill' => $backfill,
			),
		)
	);

	return array(
		'success'  => true,
		'year'     => $anio,
		'seeded'   => is_array( $result ) ? $result : array(),
		'backfill' => $backfill,
		'message'  => sprintf(
			'Retos %d resembrados. Se revisaron %d centros activos y se actualizaron %d.',
			$anio,
			(int) $backfill['centros'],
			(int) $backfill['actualizados']
		),
	);
}

function gnf_rest_admin_audit_logs( WP_REST_Request $request ) {
	$anio  = (int) ( $request->get_param( 'year' ) ?: 0 );
	$limit = (int) ( $request->get_param( 'limit' ) ?: 100 );

	return function_exists( 'gnf_get_audit_logs' )
		? gnf_get_audit_logs(
			array(
				'anio'  => $anio,
				'limit' => $limit,
			)
		)
		: array();
}

function gnf_rest_track_event( WP_REST_Request $request ) {
	$event = sanitize_key( $request->get_param( 'event' ) ?? '' );
	if ( ! $event ) {
		return new WP_Error( 'invalid_event', 'Evento inválido.', array( 'status' => 400 ) );
	}

	$meta = array(
		'page'      => sanitize_key( $request->get_param( 'page' ) ?? '' ),
		'role'      => sanitize_key( $request->get_param( 'role' ) ?? '' ),
		'formId'    => absint( $request->get_param( 'formId' ) ?? 0 ),
		'fieldCount'=> absint( $request->get_param( 'fieldCount' ) ?? 0 ),
		'status'    => sanitize_key( $request->get_param( 'status' ) ?? '' ),
	);

	gnf_log_audit_event(
		$event,
		array(
			'anio'     => absint( $request->get_param( 'year' ) ?? 0 ),
			'panel'    => sanitize_key( $request->get_param( 'panel' ) ?? '' ),
			'centro_id'=> absint( $request->get_param( 'centroId' ) ?? 0 ),
			'reto_id'  => absint( $request->get_param( 'retoId' ) ?? 0 ),
			'message'  => sanitize_text_field( $request->get_param( 'message' ) ?? '' ),
			'meta'     => array_filter( $meta ),
		)
	);

	return array( 'success' => true );
}

// ============================================================================
// Comite BAE endpoints
// ============================================================================

function gnf_rest_comite_dashboard( WP_REST_Request $request ) {
	return gnf_rest_supervisor_dashboard( $request );
}

function gnf_rest_comite_centros( WP_REST_Request $request ) {
	return gnf_rest_supervisor_centros( $request );
}

function gnf_rest_comite_centro_detail( WP_REST_Request $request ) {
	return gnf_rest_supervisor_centro_detail( $request );
}

function gnf_rest_comite_validate( WP_REST_Request $request ) {
	$centro_id = (int) $request->get_param( 'id' );
	$anio      = (int) ( $request->get_param( 'year' ) ?: gnf_get_active_year() );
	$action    = sanitize_key( (string) ( $request->get_param( 'action' ) ?: 'validar' ) );
	$notes     = sanitize_textarea_field( (string) ( $request->get_param( 'notes' ) ?: $request->get_param( 'nota' ) ?: '' ) );
	$user      = wp_get_current_user();
	$post      = get_post( $centro_id );

	if ( ! $post || 'centro_educativo' !== $post->post_type ) {
		return new WP_Error( 'not_found', 'Centro no encontrado.', array( 'status' => 404 ) );
	}

	if ( ! in_array( $action, array( 'validar', 'rechazar' ), true ) ) {
		return new WP_Error( 'invalid_action', 'Accion invalida.', array( 'status' => 400 ) );
	}

	$status = 'validar' === $action ? 'validado' : 'rechazado';
	$review = array(
		'anio'      => $anio,
		'status'    => $status,
		'notes'     => $notes,
		'userId'    => (int) $user->ID,
		'userName'  => (string) $user->display_name,
		'updatedAt' => current_time( 'mysql' ),
	);
	gnf_rest_save_comite_review( $centro_id, $anio, $review );
	gnf_rest_append_comite_historial(
		$centro_id,
		$anio,
		'validar' === $action ? 'Centro aprobado' : 'Centro rechazado',
		$notes ? $notes : ( 'validar' === $action ? 'Centro validado por el comite.' : 'Centro rechazado por el comite.' ),
		$user
	);

	$docentes = (array) get_post_meta( $centro_id, 'docentes_asociados', true );
	foreach ( $docentes as $docente_id ) {
		gnf_insert_notification(
			$docente_id,
			'validar' === $action ? 'validado' : 'rechazado',
			'validar' === $action ? 'Tu centro fue validado por el Comite Bandera Azul.' : 'Tu centro fue rechazado por el Comite Bandera Azul.',
			'centro',
			$centro_id
		);
	}

	gnf_log_audit_event(
		'validar' === $action ? 'comite_validate_centro' : 'comite_reject_centro',
		array(
			'actor_user_id' => (int) $user->ID,
			'centro_id'     => $centro_id,
			'anio'          => $anio,
			'panel'         => 'comite',
			'message'       => 'validar' === $action ? 'Comite valido un centro.' : 'Comite rechazo un centro.',
			'meta'          => array(
				'notes' => $notes,
			),
		)
	);

	return array(
		'success' => true,
		'review'  => $review,
	);
}

function gnf_rest_comite_observation( WP_REST_Request $request ) {
	$centro_id   = (int) $request->get_param( 'id' );
	$anio        = (int) ( $request->get_param( 'year' ) ?: gnf_get_active_year() );
	$observation = sanitize_textarea_field( (string) ( $request->get_param( 'observation' ) ?: $request->get_param( 'nota' ) ?: '' ) );
	$user        = wp_get_current_user();
	$post        = get_post( $centro_id );

	if ( '' === $observation ) {
		return new WP_Error( 'missing_observation', 'Se requiere una observacion.', array( 'status' => 400 ) );
	}

	if ( ! $post || 'centro_educativo' !== $post->post_type ) {
		return new WP_Error( 'not_found', 'Centro no encontrado.', array( 'status' => 404 ) );
	}

	$observaciones   = gnf_rest_get_comite_observations( $centro_id );
	$observaciones[] = array(
		'anio'        => $anio,
		'userId'      => (int) $user->ID,
		'userName'    => (string) $user->display_name,
		'observation' => $observation,
		'createdAt'   => current_time( 'mysql' ),
	);
	update_post_meta( $centro_id, 'gnf_comite_observaciones', array_values( $observaciones ) );
	gnf_rest_append_comite_historial( $centro_id, $anio, 'Observacion registrada', $observation, $user );
	gnf_log_audit_event(
		'comite_add_observation',
		array(
			'actor_user_id' => (int) $user->ID,
			'centro_id'     => $centro_id,
			'anio'          => $anio,
			'panel'         => 'comite',
			'message'       => 'Comite agrego una observacion.',
			'meta'          => array(
				'observation' => $observation,
			),
		)
	);

	return array( 'success' => true );
}

function gnf_rest_comite_historial( WP_REST_Request $request ) {
	$anio   = (int) ( $request->get_param( 'year' ) ?: gnf_get_active_year() );
	$region = 0;
	$user   = wp_get_current_user();

	if ( ! current_user_can( 'manage_options' ) ) {
		$region = gnf_get_user_region( $user->ID );
	}

	$centros_args = array(
		'post_type'      => 'centro_educativo',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	);
	if ( $region ) {
		$centros_args['tax_query'] = array(
			array(
				'taxonomy' => 'gn_region',
				'field'    => 'term_id',
				'terms'    => $region,
			),
		);
	}

	$centro_ids = get_posts( $centros_args );
	$items      = array();
	foreach ( (array) $centro_ids as $centro_id ) {
		$history = get_post_meta( $centro_id, 'gnf_comite_historial', true );
		$history = is_array( $history ) ? $history : array();
		foreach ( $history as $row ) {
			if ( (int) ( $row['anio'] ?? 0 ) !== $anio ) {
				continue;
			}
			$items[] = array(
				'id'           => (string) ( $row['id'] ?? wp_generate_uuid4() ),
				'centroId'     => (int) ( $row['centroId'] ?? $centro_id ),
				'centroNombre' => (string) ( $row['centroNombre'] ?? get_the_title( $centro_id ) ),
				'action'       => (string) ( $row['action'] ?? '' ),
				'details'      => (string) ( $row['details'] ?? '' ),
				'userId'       => (int) ( $row['userId'] ?? 0 ),
				'userName'     => (string) ( $row['userName'] ?? '' ),
				'createdAt'    => (string) ( $row['createdAt'] ?? '' ),
			);
		}
	}

	usort(
		$items,
		static function ( $a, $b ) {
			return strcmp( (string) ( $b['createdAt'] ?? '' ), (string) ( $a['createdAt'] ?? '' ) );
		}
	);

	return array_values( $items );
}
