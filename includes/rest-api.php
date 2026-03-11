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
			'methods'             => 'GET',
			'callback'            => 'gnf_rest_docente_matricula',
			'permission_callback' => 'gnf_rest_is_docente',
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
		'/supervisor/entries/(?P<id>\d+)',
		array(
			'methods'             => 'POST',
			'callback'            => 'gnf_rest_supervisor_update_entry',
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
			'methods'             => 'GET',
			'callback'            => 'gnf_rest_admin_centros',
			'permission_callback' => 'gnf_rest_is_admin',
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

	register_rest_route(
		$ns,
		'/comite/centros/(?P<id>\d+)/validate',
		array(
			'methods'             => 'POST',
			'callback'            => 'gnf_rest_comite_validate',
			'permission_callback' => 'gnf_rest_is_comite',
		)
	);

	register_rest_route(
		$ns,
		'/comite/centros/(?P<id>\d+)/observation',
		array(
			'methods'             => 'POST',
			'callback'            => 'gnf_rest_comite_observation',
			'permission_callback' => 'gnf_rest_is_comite',
		)
	);

	register_rest_route(
		$ns,
		'/comite/historial',
		array(
			'methods'             => 'GET',
			'callback'            => 'gnf_rest_comite_historial',
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
	return is_user_logged_in() && ( gnf_user_has_role( $user, 'supervisor' ) || gnf_user_has_role( $user, 'comite_bae' ) || current_user_can( 'manage_options' ) );
}

function gnf_rest_is_admin() {
	return current_user_can( 'manage_options' );
}

function gnf_rest_is_comite() {
	$user = wp_get_current_user();
	return is_user_logged_in() && ( gnf_user_has_role( $user, 'comite_bae' ) || current_user_can( 'manage_options' ) );
}

// ═══════════════════════════════════════════════════════════════════════
// Auth endpoints
// ═══════════════════════════════════════════════════════════════════════

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
		return new WP_Error( 'login_failed', $user->get_error_message(), array( 'status' => 401 ) );
	}

	wp_set_current_user( $user->ID );

	// Determine redirect based on role.
	$redirect = home_url( '/' );
	if ( gnf_user_has_role( $user, 'docente' ) ) {
		$redirect = home_url( '/panel-docente/' );
	} elseif ( gnf_user_has_role( $user, 'supervisor' ) || gnf_user_has_role( $user, 'comite_bae' ) ) {
		$redirect = home_url( '/panel-supervisor/' );
	} elseif ( in_array( 'administrator', (array) $user->roles, true ) ) {
		$redirect = home_url( '/panel-admin/' );
	}

	return array(
		'user'        => array(
			'id'    => $user->ID,
			'name'  => $user->display_name,
			'email' => $user->user_email,
			'roles' => array_values( $user->roles ),
		),
		'redirectUrl' => $redirect,
	);
}

function gnf_rest_auth_register_docente( WP_REST_Request $request ) {
	$nombre   = sanitize_text_field( $request->get_param( 'nombre' ) );
	$email    = sanitize_email( $request->get_param( 'email' ) );
	$password = $request->get_param( 'password' );

	if ( email_exists( $email ) ) {
		return new WP_Error( 'email_exists', 'Este correo ya está registrado.', array( 'status' => 400 ) );
	}

	$user_id = wp_create_user( $email, $password, $email );
	if ( is_wp_error( $user_id ) ) {
		return $user_id;
	}

	$user = new WP_User( $user_id );
	$user->set_role( 'docente' );
	wp_update_user( array( 'ID' => $user_id, 'display_name' => $nombre ) );

	// Mark as pending.
	update_user_meta( $user_id, 'gnf_docente_estado', 'pendiente' );

	// Handle centro assignment.
	$centro_id = (int) $request->get_param( 'centroId' );
	if ( $centro_id ) {
		update_user_meta( $user_id, 'gnf_centro_id', $centro_id );
	}

	// Notify admins.
	if ( function_exists( 'gnf_notify_admins' ) ) {
		gnf_notify_admins( 'registro', sprintf( 'Nuevo docente registrado: %s (%s)', $nombre, $email ), 'user', $user_id );
	}

	return array(
		'user'        => array(
			'id'    => $user_id,
			'name'  => $nombre,
			'email' => $email,
			'roles' => array( 'docente' ),
		),
		'redirectUrl' => home_url( '/panel-docente/' ),
	);
}

function gnf_rest_auth_register_supervisor( WP_REST_Request $request ) {
	$nombre   = sanitize_text_field( $request->get_param( 'nombre' ) );
	$email    = sanitize_email( $request->get_param( 'email' ) );
	$password = $request->get_param( 'password' );
	$region   = (int) $request->get_param( 'regionId' );

	if ( email_exists( $email ) ) {
		return new WP_Error( 'email_exists', 'Este correo ya está registrado.', array( 'status' => 400 ) );
	}

	$user_id = wp_create_user( $email, $password, $email );
	if ( is_wp_error( $user_id ) ) {
		return $user_id;
	}

	$user = new WP_User( $user_id );
	$user->set_role( 'supervisor' );
	wp_update_user( array( 'ID' => $user_id, 'display_name' => $nombre ) );

	update_user_meta( $user_id, 'gnf_supervisor_estado', 'pendiente' );
	update_user_meta( $user_id, 'gnf_region_id', $region );

	if ( $request->get_param( 'cargo' ) ) {
		update_user_meta( $user_id, 'gnf_cargo', sanitize_text_field( $request->get_param( 'cargo' ) ) );
	}
	if ( $request->get_param( 'telefono' ) ) {
		update_user_meta( $user_id, 'gnf_telefono', sanitize_text_field( $request->get_param( 'telefono' ) ) );
	}

	if ( function_exists( 'gnf_notify_admins' ) ) {
		gnf_notify_admins( 'registro', sprintf( 'Nuevo supervisor registrado: %s (%s)', $nombre, $email ), 'user', $user_id );
	}

	return array(
		'user' => array(
			'id'    => $user_id,
			'name'  => $nombre,
			'email' => $email,
			'roles' => array( 'supervisor' ),
		),
	);
}

function gnf_rest_auth_me() {
	$user = wp_get_current_user();
	$data = array(
		'id'    => $user->ID,
		'name'  => $user->display_name,
		'email' => $user->user_email,
		'roles' => array_values( $user->roles ),
	);

	if ( gnf_user_has_role( $user, 'docente' ) ) {
		$data['centroId'] = gnf_get_centro_for_docente( $user->ID );
		$data['estado']   = gnf_get_docente_estado( $user->ID );
	}

	return $data;
}

function gnf_rest_auth_logout() {
	wp_logout();
	return array( 'success' => true );
}

// ═══════════════════════════════════════════════════════════════════════
// Shared endpoints
// ═══════════════════════════════════════════════════════════════════════

function gnf_rest_centros_search( WP_REST_Request $request ) {
	$term = sanitize_text_field( $request->get_param( 'term' ) );
	if ( strlen( $term ) < 2 ) {
		return array();
	}

	$args = array(
		'post_type'      => 'centro_educativo',
		'posts_per_page' => 10,
		's'              => $term,
		'post_status'    => 'publish',
	);

	$query   = new WP_Query( $args );
	$results = array();

	foreach ( $query->posts as $post ) {
		$results[] = array(
			'id'        => $post->ID,
			'nombre'    => $post->post_title,
			'codigoMep' => get_field( 'codigo_mep', $post->ID ) ?: '',
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

	$terms = wp_get_object_terms( $id, 'gn_region' );
	return array(
		'id'         => $id,
		'nombre'     => $post->post_title,
		'codigoMep'  => get_field( 'codigo_mep', $id ) ?: '',
		'regionId'   => ! empty( $terms ) ? $terms[0]->term_id : 0,
		'regionName' => ! empty( $terms ) ? $terms[0]->name : '',
		'direccion'  => get_field( 'direccion', $id ) ?: '',
		'provincia'  => get_field( 'provincia', $id ) ?: '',
		'canton'     => get_field( 'canton', $id ) ?: '',
	);
}

function gnf_rest_centro_update( WP_REST_Request $request ) {
	$id = (int) $request->get_param( 'id' );

	if ( ! gnf_user_can_access_centro( get_current_user_id(), $id ) && ! current_user_can( 'manage_options' ) ) {
		return new WP_Error( 'forbidden', 'Sin acceso a este centro.', array( 'status' => 403 ) );
	}

	$fields = array( 'direccion', 'provincia', 'canton' );
	foreach ( $fields as $field ) {
		$value = $request->get_param( $field );
		if ( $value !== null ) {
			update_field( $field, sanitize_text_field( $value ), $id );
		}
	}

	return array( 'success' => true );
}

function gnf_rest_regions() {
	$terms   = get_terms( array( 'taxonomy' => 'gn_region', 'hide_empty' => false ) );
	$regions = array();

	if ( ! is_wp_error( $terms ) ) {
		foreach ( $terms as $term ) {
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
		function ( $item ) {
			return array(
				'id'           => (int) $item->id,
				'userId'       => (int) $item->user_id,
				'tipo'         => $item->tipo,
				'mensaje'      => $item->mensaje,
				'relacionTipo' => $item->relacion_tipo,
				'relacionId'   => (int) $item->relacion_id,
				'leido'        => (bool) $item->leido,
				'createdAt'    => $item->created_at,
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
		'tieneMatricula'   => ! empty( $retos_sel ),
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
			'obligatorio'   => in_array( $reto_id, gnf_get_obligatorio_reto_ids(), true ),
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

	$retos_selected   = $centro_id ? gnf_get_centro_retos_seleccionados( $centro_id, $anio ) : array();
	$meta_estrellas   = $centro_id && function_exists( 'gnf_get_centro_meta_estrellas' ) ? gnf_get_centro_meta_estrellas( $centro_id, $anio ) : 1;
	$comite_est       = $centro_id && function_exists( 'gnf_get_centro_comite_estudiantes' ) ? gnf_get_centro_comite_estudiantes( $centro_id, $anio ) : 0;
	$obligatorio_ids  = gnf_get_obligatorio_reto_ids();

	// Build retosDisponibles: all published retos with active form for year.
	$retos_disponibles = array();
	$retos_q = get_posts( array(
		'post_type'      => 'reto',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
	) );
	foreach ( $retos_q as $reto ) {
		if ( ! gnf_reto_has_form_for_year( $reto->ID, $anio ) ) {
			continue;
		}
		$retos_disponibles[] = array(
			'id'            => $reto->ID,
			'titulo'        => $reto->post_title,
			'descripcion'   => get_field( 'descripcion', $reto->ID ) ?: '',
			'iconUrl'       => gnf_get_reto_icon_url( $reto->ID, 'thumbnail', $anio ),
			'puntajeMaximo' => gnf_get_reto_max_points( $reto->ID, $anio ),
			'obligatorio'   => in_array( $reto->ID, $obligatorio_ids, true ),
			'hasForm'       => true,
		);
	}

	return array(
		'centro'              => $centro_data,
		'retosDisponibles'    => $retos_disponibles,
		'retosSeleccionados'  => array_values( array_map( 'intval', $retos_selected ) ),
		'metaEstrellas'       => (int) $meta_estrellas,
		'comiteEstudiantes'   => (int) $comite_est,
	);
}

function gnf_rest_docente_matricula_add_reto( WP_REST_Request $request ) {
	$user_id   = get_current_user_id();
	$reto_id   = (int) $request->get_param( 'retoId' );
	$centro_id = gnf_get_centro_for_docente( $user_id );
	$anio      = gnf_get_active_year();

	$current = gnf_get_centro_retos_seleccionados( $centro_id, $anio );
	if ( ! in_array( $reto_id, $current, true ) ) {
		$current[] = $reto_id;
		gnf_set_centro_retos_seleccionados( $centro_id, $anio, $current );
	}

	return array( 'success' => true );
}

function gnf_rest_docente_matricula_remove_reto( WP_REST_Request $request ) {
	$user_id   = get_current_user_id();
	$reto_id   = (int) $request->get_param( 'id' );
	$centro_id = gnf_get_centro_for_docente( $user_id );
	$anio      = gnf_get_active_year();

	// Do not allow removing obligatory retos.
	if ( in_array( $reto_id, gnf_get_obligatorio_reto_ids(), true ) ) {
		return new WP_Error( 'cannot_remove', 'No se puede quitar un reto obligatorio.', array( 'status' => 400 ) );
	}

	$current = gnf_get_centro_retos_seleccionados( $centro_id, $anio );
	$current = array_values( array_diff( $current, array( $reto_id ) ) );
	gnf_set_centro_retos_seleccionados( $centro_id, $anio, $current );

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
			'formId'        => (int) ( $step['form_id'] ?? 0 ),
			'estado'        => $step['estado'] ?? 'no_iniciado',
			'puntaje'       => $entry ? (int) $entry->puntaje : 0,
			'puntajeMaximo' => (int) ( $step['puntaje_max'] ?? 0 ),
		);
	}

	return $result;
}

function gnf_rest_docente_form_html( WP_REST_Request $request ) {
	$reto_id = (int) $request->get_param( 'reto_id' );
	$anio    = (int) ( $request->get_param( 'year' ) ?: gnf_get_active_year() );
	$form_id = gnf_get_reto_form_id_for_year( $reto_id, $anio );

	if ( ! $form_id ) {
		return new WP_Error( 'no_form', 'No hay formulario para este reto.', array( 'status' => 404 ) );
	}

	$html = do_shortcode( '[wpforms id="' . $form_id . '"]' );
	return array( 'html' => $html );
}

function gnf_rest_docente_finalize_reto( WP_REST_Request $request ) {
	$reto_id   = (int) $request->get_param( 'reto_id' );
	$user_id   = get_current_user_id();
	$centro_id = gnf_get_centro_for_docente( $user_id );
	$anio      = gnf_get_active_year();

	global $wpdb;
	$table = $wpdb->prefix . 'gn_reto_entries';

	$wpdb->update(
		$table,
		array( 'estado' => 'completo', 'updated_at' => current_time( 'mysql' ) ),
		array( 'centro_id' => $centro_id, 'reto_id' => $reto_id, 'anio' => $anio )
	);

	return array( 'success' => true );
}

function gnf_rest_docente_reopen_reto( WP_REST_Request $request ) {
	$reto_id   = (int) $request->get_param( 'reto_id' );
	$user_id   = get_current_user_id();
	$centro_id = gnf_get_centro_for_docente( $user_id );
	$anio      = gnf_get_active_year();

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
		array( 'estado' => 'en_progreso', 'updated_at' => current_time( 'mysql' ) ),
		array( 'id' => $entry->id )
	);

	return array( 'success' => true );
}

function gnf_rest_docente_submit( WP_REST_Request $request ) {
	$user_id   = get_current_user_id();
	$anio      = (int) ( $request->get_param( 'year' ) ?: gnf_get_active_year() );
	$centro_id = gnf_get_centro_for_docente( $user_id );

	global $wpdb;
	$table = $wpdb->prefix . 'gn_reto_entries';

	$wpdb->update(
		$table,
		array( 'estado' => 'enviado', 'updated_at' => current_time( 'mysql' ) ),
		array( 'centro_id' => $centro_id, 'anio' => $anio, 'estado' => 'completo' )
	);

	return array( 'success' => true );
}

// ═══════════════════════════════════════════════════════════════════════
// Supervisor endpoints
// ═══════════════════════════════════════════════════════════════════════

function gnf_rest_supervisor_dashboard( WP_REST_Request $request ) {
	$anio    = (int) ( $request->get_param( 'year' ) ?: gnf_get_active_year() );
	$user_id = get_current_user_id();
	$region  = gnf_get_user_region( $user_id );

	$region_name = '';
	if ( $region ) {
		$term = get_term( $region, 'gn_region' );
		if ( $term && ! is_wp_error( $term ) ) {
			$region_name = $term->name;
		}
	}

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

	// Filter by region if supervisor (not admin/comité).
	if ( $region && ! current_user_can( 'gnf_view_all_regions' ) ) {
		$region_centros = get_posts( array(
			'post_type'   => 'centro_educativo',
			'numberposts' => -1,
			'fields'      => 'ids',
			'tax_query'   => array( array( 'taxonomy' => 'gn_region', 'terms' => $region ) ),
		) );
		$centro_ids = array_intersect( $centro_ids, $region_centros );
	}

	$stats = array( 'centros' => count( $centro_ids ), 'pendientes' => 0, 'aprobados' => 0, 'correccion' => 0, 'enviados' => 0, 'enProgreso' => 0 );

	if ( ! empty( $centro_ids ) ) {
		$placeholders = implode( ',', array_fill( 0, count( $centro_ids ), '%d' ) );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT estado, COUNT(*) as cnt FROM {$entries_table} WHERE centro_id IN ({$placeholders}) AND anio = %d GROUP BY estado",
				array_merge( $centro_ids, array( $anio ) )
			)
		);
		foreach ( $rows as $row ) {
			if ( isset( $stats[ $row->estado ] ) ) {
				$stats[ $row->estado ] = (int) $row->cnt;
			}
			if ( $row->estado === 'enviado' ) {
				$stats['enviados'] = (int) $row->cnt;
			}
		}
	}

	return array(
		'stats'      => $stats,
		'regionName' => $region_name,
	);
}

function gnf_rest_supervisor_centros( WP_REST_Request $request ) {
	$anio     = (int) ( $request->get_param( 'year' ) ?: gnf_get_active_year() );
	$circuito = sanitize_text_field( $request->get_param( 'circuito' ) ?? '' );
	$user_id  = get_current_user_id();
	$region   = gnf_get_user_region( $user_id );

	// Only centros with active matricula.
	$centros_con_matricula = gnf_get_centros_with_matricula( $anio );
	if ( empty( $centros_con_matricula ) ) {
		return array();
	}

	$centros_args = array(
		'post_type'      => 'centro_educativo',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'post__in'       => $centros_con_matricula,
	);

	// Region-scope for supervisors (not admin/comité).
	if ( $region && ! current_user_can( 'gnf_view_all_regions' ) ) {
		$centros_args['tax_query'] = array(
			array(
				'taxonomy' => 'gn_region',
				'field'    => 'id',
				'terms'    => array( $region ),
			),
		);
	}

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

	// Batch-load entries for all centros.
	global $wpdb;
	$table = $wpdb->prefix . 'gn_reto_entries';
	$placeholders = implode( ',', array_fill( 0, count( $centro_ids ), '%d' ) );
	$entries_raw = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE anio = %d AND centro_id IN ({$placeholders})",
			array_merge( array( $anio ), $centro_ids )
		)
	);

	$entries_by_centro = array();
	foreach ( (array) $entries_raw as $entry ) {
		$entries_by_centro[ $entry->centro_id ][] = $entry;
	}

	$result = array();
	foreach ( $centros->posts as $post ) {
		$terms      = wp_get_object_terms( $post->ID, 'gn_region' );
		$entries    = $entries_by_centro[ $post->ID ] ?? array();
		$retos_sel  = gnf_get_centro_retos_seleccionados( $post->ID, $anio );
		$puntaje    = gnf_get_centro_puntaje_total( $post->ID, $anio );
		$estrella   = gnf_get_centro_estrella_final( $post->ID, $anio );
		$meta       = function_exists( 'gnf_get_centro_meta_estrellas' ) ? gnf_get_centro_meta_estrellas( $post->ID, $anio ) : 1;

		$counts = array( 'aprobados' => 0, 'enviados' => 0, 'correccion' => 0, 'enProgreso' => 0 );
		foreach ( $entries as $e ) {
			if ( 'aprobado' === $e->estado )   $counts['aprobados']++;
			if ( 'enviado' === $e->estado )    $counts['enviados']++;
			if ( 'correccion' === $e->estado ) $counts['correccion']++;
			if ( in_array( $e->estado, array( 'en_progreso', 'no_iniciado', 'completo' ), true ) ) $counts['enProgreso']++;
		}

		$result[] = array(
			'id'            => $post->ID,
			'nombre'        => $post->post_title,
			'codigoMep'     => get_field( 'codigo_mep', $post->ID ) ?: '',
			'regionName'    => ! empty( $terms ) ? $terms[0]->name : '',
			'circuito'      => get_post_meta( $post->ID, 'circuito', true ) ?: '',
			'metaEstrellas' => $meta,
			'puntajeTotal'  => $puntaje,
			'estrellaFinal' => $estrella,
			'retosCount'    => count( $retos_sel ),
			'aprobados'     => $counts['aprobados'],
			'enviados'      => $counts['enviados'],
			'correccion'    => $counts['correccion'],
			'enProgreso'    => $counts['enProgreso'],
		);
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

	$terms     = wp_get_object_terms( $centro_id, 'gn_region' );
	$retos_sel = gnf_get_centro_retos_seleccionados( $centro_id, $anio );
	$puntaje   = gnf_get_centro_puntaje_total( $centro_id, $anio );
	$estrella  = gnf_get_centro_estrella_final( $centro_id, $anio );
	$meta      = function_exists( 'gnf_get_centro_meta_estrellas' ) ? gnf_get_centro_meta_estrellas( $centro_id, $anio ) : 1;

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

	$entries = array();
	foreach ( $entries_raw as $entry ) {
		$entries[] = gnf_format_reto_entry( $entry, $anio );
	}

	return array(
		'centro'  => array(
			'id'            => $centro_id,
			'nombre'        => $post->post_title,
			'codigoMep'     => get_field( 'codigo_mep', $centro_id ) ?: '',
			'regionName'    => ! empty( $terms ) ? $terms[0]->name : '',
			'direccion'     => get_field( 'direccion', $centro_id ) ?: '',
			'circuito'      => get_post_meta( $centro_id, 'circuito', true ) ?: '',
			'metaEstrellas' => $meta,
			'puntajeTotal'  => $puntaje,
			'estrellaFinal' => $estrella,
		),
		'entries' => $entries,
	);
}

function gnf_rest_supervisor_update_entry( WP_REST_Request $request ) {
	$entry_id = (int) $request->get_param( 'id' );
	$action   = sanitize_text_field( $request->get_param( 'action' ) );
	$notes    = sanitize_textarea_field( $request->get_param( 'notes' ) ?? '' );

	global $wpdb;
	$table = $wpdb->prefix . 'gn_reto_entries';
	$entry = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $entry_id ) );

	if ( ! $entry ) {
		return new WP_Error( 'not_found', 'Entrada no encontrada.', array( 'status' => 404 ) );
	}

	if ( 'enviado' !== $entry->estado ) {
		return new WP_Error( 'invalid_state', 'Solo se pueden revisar entradas enviadas.', array( 'status' => 400 ) );
	}

	// Verify supervisor has access to this centro.
	if ( ! gnf_user_can_access_centro( get_current_user_id(), $entry->centro_id ) && ! current_user_can( 'manage_options' ) ) {
		return new WP_Error( 'forbidden', 'Sin acceso a este centro.', array( 'status' => 403 ) );
	}

	// Block approval if evidence has year-validation alerts.
	if ( 'aprobar' === $action && ! empty( $entry->evidencias ) ) {
		$evidencias = json_decode( $entry->evidencias, true );
		foreach ( (array) $evidencias as $ev ) {
			if ( ! empty( $ev['requires_year_validation'] ) ) {
				return new WP_Error( 'year_validation', 'No puedes aprobar: existe evidencia marcada para validación de año.', array( 'status' => 400 ) );
			}
		}
	}

	if ( 'aprobar' === $action ) {
		$wpdb->update(
			$table,
			array( 'estado' => 'aprobado', 'updated_at' => current_time( 'mysql' ) ),
			array( 'id' => $entry_id )
		);

		// Recalculate points based on checklist.
		if ( function_exists( 'gnf_recalcular_puntaje_reto' ) ) {
			$updated_entry     = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $entry_id ) );
			$puntaje_calculado = gnf_recalcular_puntaje_reto( $updated_entry );
			$wpdb->update( $table, array( 'puntaje' => $puntaje_calculado ), array( 'id' => $entry_id ), array( '%d' ), array( '%d' ) );
		}

		gnf_recalcular_puntaje_centro( $entry->centro_id, (int) $entry->anio );

		// Notify docente.
		if ( function_exists( 'gnf_insert_notification' ) ) {
			$reto = get_post( $entry->reto_id );
			gnf_insert_notification(
				$entry->user_id,
				'aprobado',
				sprintf( 'Tu reto "%s" ha sido aprobado.', $reto ? $reto->post_title : '' ),
				'reto_entry',
				$entry_id
			);
		}
	} elseif ( 'correccion' === $action ) {
		$wpdb->update(
			$table,
			array( 'estado' => 'correccion', 'supervisor_notes' => $notes, 'updated_at' => current_time( 'mysql' ) ),
			array( 'id' => $entry_id )
		);

		gnf_recalcular_puntaje_centro( $entry->centro_id, (int) $entry->anio );

		if ( function_exists( 'gnf_request_correction' ) ) {
			gnf_request_correction( $entry_id, $notes ?: 'Se solicitó corrección', get_current_user_id() );
		}
	}

	gnf_clear_supervisor_cache();

	return array( 'success' => true );
}

// ═══════════════════════════════════════════════════════════════════════
// Admin endpoints
// ═══════════════════════════════════════════════════════════════════════

function gnf_rest_admin_stats( WP_REST_Request $request ) {
	$anio = (int) ( $request->get_param( 'year' ) ?: gnf_get_active_year() );

	if ( function_exists( 'gnf_get_admin_stats' ) ) {
		$stats = gnf_get_admin_stats( $anio );

		// Normalize to camelCase for frontend.
		return array(
			'centrosActivos'    => (int) ( $stats['centros_activos'] ?? 0 ),
			'totalDocentes'     => (int) ( $stats['total_docentes'] ?? 0 ),
			'totalSupervisors'  => (int) ( $stats['total_supervisors'] ?? 0 ),
			'totalComite'       => (int) ( $stats['total_comite'] ?? 0 ),
			'pendingDocentes'   => (int) ( $stats['pending_docentes'] ?? 0 ),
			'pendingSupervisors' => (int) ( $stats['pending_supervisors'] ?? 0 ),
			'retosEnviados'     => (int) ( $stats['retos_enviados'] ?? 0 ),
			'retosAprobados'    => (int) ( $stats['retos_aprobados'] ?? 0 ),
			'retosCorreccion'   => (int) ( $stats['retos_correccion'] ?? 0 ),
			'puntosTotales'     => (int) ( $stats['puntos_totales'] ?? 0 ),
		);
	}

	return array(
		'centrosActivos'    => 0,
		'totalDocentes'     => 0,
		'totalSupervisors'  => 0,
		'totalComite'       => 0,
		'pendingDocentes'   => 0,
		'pendingSupervisors' => 0,
		'retosEnviados'     => 0,
		'retosAprobados'    => 0,
		'retosCorreccion'   => 0,
		'puntosTotales'     => 0,
	);
}

function gnf_rest_admin_pending_users() {
	$users = get_users( array(
		'meta_query' => array(
			'relation' => 'OR',
			array( 'key' => 'gnf_docente_estado', 'value' => 'pendiente' ),
			array( 'key' => 'gnf_supervisor_estado', 'value' => 'pendiente' ),
		),
	) );

	return array_map( function ( $u ) {
		return array(
			'id'           => $u->ID,
			'name'         => $u->display_name,
			'email'        => $u->user_email,
			'role'         => $u->roles[0] ?? 'docente',
			'registeredAt' => $u->user_registered,
		);
	}, $users );
}

function gnf_rest_admin_approve_user( WP_REST_Request $request ) {
	$user_id = (int) $request->get_param( 'id' );

	if ( function_exists( 'gnf_approve_docente' ) ) {
		gnf_approve_docente( $user_id );
	}
	update_user_meta( $user_id, 'gnf_docente_estado', 'activo' );
	update_user_meta( $user_id, 'gnf_supervisor_estado', 'activo' );

	return array( 'success' => true );
}

function gnf_rest_admin_reject_user( WP_REST_Request $request ) {
	$user_id = (int) $request->get_param( 'id' );

	// Remove pending meta.
	delete_user_meta( $user_id, 'gnf_docente_estado' );
	delete_user_meta( $user_id, 'gnf_supervisor_estado' );

	// Optionally delete the user.
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $user_id );

	return array( 'success' => true );
}

function gnf_rest_admin_centros( WP_REST_Request $request ) {
	$anio   = (int) ( $request->get_param( 'year' ) ?: gnf_get_active_year() );
	$region = (int) ( $request->get_param( 'region' ) ?: 0 );
	$search = sanitize_text_field( $request->get_param( 's' ) ?? '' );
	$page   = max( 1, (int) ( $request->get_param( 'page' ) ?: 1 ) );

	$centros_con_matricula = gnf_get_centros_with_matricula( $anio );

	$args = array(
		'post_type'      => 'centro_educativo',
		'posts_per_page' => 20,
		'paged'          => $page,
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

	if ( $search ) {
		$args['s'] = $search;
	}

	$query = new WP_Query( $args );

	if ( ! $query->have_posts() ) {
		return array( 'items' => array(), 'total' => 0, 'totalPages' => 0 );
	}

	$centro_ids = wp_list_pluck( $query->posts, 'ID' );

	// Batch-load entries.
	global $wpdb;
	$table        = $wpdb->prefix . 'gn_reto_entries';
	$placeholders = implode( ',', array_fill( 0, count( $centro_ids ), '%d' ) );
	$entries_raw  = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT centro_id, estado, COUNT(*) as cnt FROM {$table} WHERE anio = %d AND centro_id IN ({$placeholders}) GROUP BY centro_id, estado",
			array_merge( array( $anio ), $centro_ids )
		)
	);

	$stats_by_centro = array();
	foreach ( $entries_raw as $row ) {
		if ( ! isset( $stats_by_centro[ $row->centro_id ] ) ) {
			$stats_by_centro[ $row->centro_id ] = array();
		}
		$stats_by_centro[ $row->centro_id ][ $row->estado ] = (int) $row->cnt;
	}

	$items = array();
	foreach ( $query->posts as $post ) {
		$terms   = wp_get_object_terms( $post->ID, 'gn_region' );
		$puntaje = gnf_get_centro_puntaje_total( $post->ID, $anio );
		$estrella = gnf_get_centro_estrella_final( $post->ID, $anio );
		$centro_stats = $stats_by_centro[ $post->ID ] ?? array();

		$items[] = array(
			'id'            => $post->ID,
			'nombre'        => $post->post_title,
			'codigoMep'     => get_field( 'codigo_mep', $post->ID ) ?: '',
			'regionName'    => ! empty( $terms ) ? $terms[0]->name : '',
			'puntajeTotal'  => $puntaje,
			'estrellaFinal' => $estrella,
			'aprobados'     => $centro_stats['aprobado'] ?? 0,
			'enviados'      => $centro_stats['enviado'] ?? 0,
			'correccion'    => $centro_stats['correccion'] ?? 0,
		);
	}

	wp_reset_postdata();

	return array(
		'items'      => $items,
		'total'      => $query->found_posts,
		'totalPages' => $query->max_num_pages,
	);
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
			'puntosTotales' => (int) ( $stats->puntos_totales ?? 0 ),
		);
	}

	return $result;
}

function gnf_rest_admin_reports( WP_REST_Request $request ) {
	$anio = (int) ( $request->get_param( 'year' ) ?: gnf_get_active_year() );

	if ( ! function_exists( 'gnf_get_reports_data' ) ) {
		return array( 'porRegion' => array(), 'topCentros' => array(), 'porMes' => array() );
	}

	$data = gnf_get_reports_data( $anio );

	// Normalize to camelCase for frontend.
	$por_region = array();
	foreach ( (array) ( $data['por_region'] ?? array() ) as $row ) {
		$por_region[] = array(
			'region'    => $row->region ?: 'Sin región',
			'termId'    => (int) ( $row->term_id ?? 0 ),
			'centros'   => (int) ( $row->centros ?? 0 ),
			'aprobados' => (int) ( $row->aprobados ?? 0 ),
			'pendientes' => (int) ( $row->pendientes ?? 0 ),
			'puntos'    => (int) ( $row->puntos ?? 0 ),
		);
	}

	$top_centros = array();
	foreach ( (array) ( $data['top_centros'] ?? array() ) as $row ) {
		$top_centros[] = array(
			'id'            => (int) $row->ID,
			'nombre'        => $row->post_title,
			'puntajeTotal'  => (int) $row->puntaje_total_anual,
			'estrellaAnual' => (int) ( $row->estrella_anual ?? 0 ),
		);
	}

	$por_mes = array();
	foreach ( (array) ( $data['por_mes'] ?? array() ) as $row ) {
		$por_mes[] = array(
			'mes'       => (int) $row->mes,
			'aprobados' => (int) ( $row->aprobados ?? 0 ),
			'enviados'  => (int) ( $row->enviados ?? 0 ),
		);
	}

	return array(
		'porRegion'  => $por_region,
		'topCentros' => $top_centros,
		'porMes'     => $por_mes,
	);
}

function gnf_rest_admin_dre_list() {
	$terms = get_terms( array( 'taxonomy' => 'gn_region', 'hide_empty' => false ) );
	$dres  = array();

	if ( ! is_wp_error( $terms ) ) {
		foreach ( $terms as $term ) {
			$dres[] = array(
				'id'      => $term->term_id,
				'name'    => $term->name,
				'slug'    => $term->slug,
				'enabled' => (bool) get_term_meta( $term->term_id, 'gnf_enabled', true ),
			);
		}
	}

	return $dres;
}

function gnf_rest_admin_dre_toggle( WP_REST_Request $request ) {
	$dre_id  = (int) $request->get_param( 'id' );
	$current = (bool) get_term_meta( $dre_id, 'gnf_enabled', true );
	$new     = ! $current;

	update_term_meta( $dre_id, 'gnf_enabled', $new ? '1' : '0' );

	return array( 'success' => true, 'enabled' => $new );
}

// ═══════════════════════════════════════════════════════════════════════
// Comité BAE endpoints
// ═══════════════════════════════════════════════════════════════════════

function gnf_rest_comite_dashboard( WP_REST_Request $request ) {
	// Reuse supervisor dashboard without region scope.
	$result = gnf_rest_supervisor_dashboard( $request );
	return array( 'stats' => $result['stats'] ?? array() );
}

function gnf_rest_comite_centros( WP_REST_Request $request ) {
	$anio     = (int) ( $request->get_param( 'year' ) ?: gnf_get_active_year() );
	$region   = (int) ( $request->get_param( 'region' ) ?: 0 );
	$circuito = sanitize_text_field( $request->get_param( 'circuito' ) ?? '' );
	$search   = sanitize_text_field( $request->get_param( 's' ) ?? '' );
	$page     = max( 1, (int) ( $request->get_param( 'page' ) ?: 1 ) );

	// Auto-filter by DRE for non-admin comité members.
	$user = wp_get_current_user();
	if ( ! current_user_can( 'manage_options' ) ) {
		$user_region = absint( get_user_meta( $user->ID, 'gnf_region', true ) );
		if ( ! $user_region ) {
			$user_region = absint( get_user_meta( $user->ID, 'region', true ) );
		}
		if ( $user_region ) {
			$region = $user_region;
		}
	}

	$centros_con_matricula = gnf_get_centros_with_matricula( $anio );
	if ( empty( $centros_con_matricula ) ) {
		return array( 'items' => array(), 'total' => 0, 'totalPages' => 0 );
	}

	$args = array(
		'post_type'      => 'centro_educativo',
		'posts_per_page' => 20,
		'paged'          => $page,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'post_status'    => 'publish',
		'post__in'       => $centros_con_matricula,
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

	if ( $circuito ) {
		$args['meta_query'] = array(
			array(
				'key'   => 'circuito',
				'value' => $circuito,
			),
		);
	}

	if ( $search ) {
		$args['s'] = $search;
	}

	$query = new WP_Query( $args );

	if ( ! $query->have_posts() ) {
		return array( 'items' => array(), 'total' => 0, 'totalPages' => 0 );
	}

	$centro_ids = wp_list_pluck( $query->posts, 'ID' );

	// Batch-load entries.
	global $wpdb;
	$table        = $wpdb->prefix . 'gn_reto_entries';
	$placeholders = implode( ',', array_fill( 0, count( $centro_ids ), '%d' ) );
	$entries_raw  = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT centro_id, estado, COUNT(*) as cnt FROM {$table} WHERE anio = %d AND centro_id IN ({$placeholders}) GROUP BY centro_id, estado",
			array_merge( array( $anio ), $centro_ids )
		)
	);

	$stats_by_centro = array();
	foreach ( $entries_raw as $row ) {
		if ( ! isset( $stats_by_centro[ $row->centro_id ] ) ) {
			$stats_by_centro[ $row->centro_id ] = array();
		}
		$stats_by_centro[ $row->centro_id ][ $row->estado ] = (int) $row->cnt;
	}

	$items = array();
	foreach ( $query->posts as $post ) {
		$terms        = wp_get_object_terms( $post->ID, 'gn_region' );
		$puntaje      = gnf_get_centro_puntaje_total( $post->ID, $anio );
		$estrella     = gnf_get_centro_estrella_final( $post->ID, $anio );
		$meta         = function_exists( 'gnf_get_centro_meta_estrellas' ) ? gnf_get_centro_meta_estrellas( $post->ID, $anio ) : 1;
		$centro_stats = $stats_by_centro[ $post->ID ] ?? array();
		$validado     = (bool) get_post_meta( $post->ID, 'gnf_comite_validado', true );

		$items[] = array(
			'id'            => $post->ID,
			'nombre'        => $post->post_title,
			'codigoMep'     => get_field( 'codigo_mep', $post->ID ) ?: '',
			'regionName'    => ! empty( $terms ) ? $terms[0]->name : '',
			'circuito'      => get_post_meta( $post->ID, 'circuito', true ) ?: '',
			'metaEstrellas' => $meta,
			'puntajeTotal'  => $puntaje,
			'estrellaFinal' => $estrella,
			'aprobados'     => $centro_stats['aprobado'] ?? 0,
			'enviados'      => $centro_stats['enviado'] ?? 0,
			'correccion'    => $centro_stats['correccion'] ?? 0,
			'validado'      => $validado,
		);
	}

	wp_reset_postdata();

	return array(
		'items'      => $items,
		'total'      => $query->found_posts,
		'totalPages' => $query->max_num_pages,
	);
}

function gnf_rest_comite_centro_detail( WP_REST_Request $request ) {
	return gnf_rest_supervisor_centro_detail( $request );
}

function gnf_rest_comite_validate( WP_REST_Request $request ) {
	$centro_id = (int) $request->get_param( 'id' );
	$nota      = sanitize_textarea_field( $request->get_param( 'nota' ) ?? '' );
	$user      = wp_get_current_user();

	$post = get_post( $centro_id );
	if ( ! $post || 'centro_educativo' !== $post->post_type ) {
		return new WP_Error( 'not_found', 'Centro no encontrado.', array( 'status' => 404 ) );
	}

	// Mark centro as validated by Comité.
	update_post_meta( $centro_id, 'gnf_comite_validado', 1 );
	update_post_meta( $centro_id, 'gnf_comite_validado_por', $user->ID );
	update_post_meta( $centro_id, 'gnf_comite_validado_fecha', current_time( 'mysql' ) );
	if ( $nota ) {
		update_post_meta( $centro_id, 'gnf_comite_nota', $nota );
	}

	// Notify docentes associated with the centro.
	$docentes = get_post_meta( $centro_id, 'docentes_asociados', true );
	if ( is_array( $docentes ) ) {
		foreach ( $docentes as $docente_id ) {
			gnf_insert_notification( $docente_id, 'validado', 'Tu centro ha sido validado por el Comité Bandera Azul.', 'centro', $centro_id );
		}
	}

	// Notify supervisors of the region.
	$terms  = wp_get_post_terms( $centro_id, 'gn_region', array( 'fields' => 'ids' ) );
	$region = ! empty( $terms ) ? $terms[0] : 0;
	if ( $region && function_exists( 'gnf_get_supervisores_by_region' ) ) {
		$supervisores = gnf_get_supervisores_by_region( $region );
		$centro_title = $post->post_title;
		foreach ( $supervisores as $sup ) {
			gnf_insert_notification( $sup->ID, 'validado', sprintf( 'Centro "%s" validado por el Comité Bandera Azul.', $centro_title ), 'centro', $centro_id );
		}
	}

	return array( 'success' => true );
}

function gnf_rest_comite_observation( WP_REST_Request $request ) {
	$centro_id = (int) $request->get_param( 'id' );
	$nota      = sanitize_textarea_field( $request->get_param( 'nota' ) ?? '' );
	$user      = wp_get_current_user();

	if ( ! $nota ) {
		return new WP_Error( 'missing_nota', 'Se requiere una observación.', array( 'status' => 400 ) );
	}

	$post = get_post( $centro_id );
	if ( ! $post || 'centro_educativo' !== $post->post_type ) {
		return new WP_Error( 'not_found', 'Centro no encontrado.', array( 'status' => 404 ) );
	}

	$observaciones   = get_post_meta( $centro_id, 'gnf_comite_observaciones', true ) ?: array();
	$observaciones[] = array(
		'usuario' => $user->ID,
		'nombre'  => $user->display_name,
		'fecha'   => current_time( 'mysql' ),
		'nota'    => $nota,
	);
	update_post_meta( $centro_id, 'gnf_comite_observaciones', $observaciones );

	return array( 'success' => true );
}

function gnf_rest_comite_historial( WP_REST_Request $request ) {
	$anio = (int) ( $request->get_param( 'year' ) ?: gnf_get_active_year() );

	global $wpdb;
	$table = $wpdb->prefix . 'gn_reto_entries';

	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT e.id, e.centro_id, e.reto_id, e.estado, e.puntaje, e.supervisor_notes, e.updated_at,
			p.post_title AS centro_nombre,
			r.post_title AS reto_nombre,
			u.display_name AS supervisor_nombre
		 FROM {$table} e
		 LEFT JOIN {$wpdb->posts} p ON e.centro_id = p.ID
		 LEFT JOIN {$wpdb->posts} r ON e.reto_id = r.ID
		 LEFT JOIN {$wpdb->users} u ON e.supervisor_id = u.ID
		 WHERE e.anio = %d AND e.estado IN ('aprobado', 'correccion')
		 ORDER BY e.updated_at DESC
		 LIMIT 50",
		$anio
	) );

	$result = array();
	foreach ( $rows as $row ) {
		$result[] = array(
			'id'              => (int) $row->id,
			'centroId'        => (int) $row->centro_id,
			'centroNombre'    => $row->centro_nombre ?: '',
			'retoId'          => (int) $row->reto_id,
			'retoNombre'      => $row->reto_nombre ?: '',
			'estado'          => $row->estado,
			'puntaje'         => (int) $row->puntaje,
			'supervisorNotes' => $row->supervisor_notes ?: '',
			'supervisorNombre' => $row->supervisor_nombre ?: '',
			'updatedAt'       => $row->updated_at,
		);
	}

	return $result;
}
