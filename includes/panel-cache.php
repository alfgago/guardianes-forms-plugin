<?php
/** Private panel read caches. Forms and operational evidence review remain live. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function gnf_panel_cache_version( $scope ) {
	return (string) get_option( 'gnf_panel_version_' . $scope, '0' );
}

function gnf_bump_panel_cache_version( $scope ) {
	update_option( 'gnf_panel_version_' . $scope, bin2hex( random_bytes( 16 ) ), false );
}

function gnf_invalidate_docente_panel_cache( $centro_id ) {
	if ( $centro_id ) { gnf_bump_panel_cache_version( 'center_' . absint( $centro_id ) ); }
}

function gnf_invalidate_supervisor_panel_cache( $user_id = 0 ) {
	gnf_bump_panel_cache_version( $user_id ? 'supervisor_' . absint( $user_id ) : 'supervisors' );
}

function gnf_panel_cache_context( $teacher ) {
	$user = wp_get_current_user();
	$id = (int) $user->ID;
	$center = $teacher ? (int) gnf_get_centro_for_docente( $id ) : 0;
	return array(
		'user' => $id, 'roles' => (array) $user->roles, 'caps' => (array) $user->allcaps,
		'year' => gnf_get_active_year(), 'center' => $center,
		'state' => $teacher ? gnf_get_docente_estado( $id ) : gnf_get_supervisor_estado( $id ),
		'regions' => $teacher ? array() : gnf_get_user_regions( $id ),
		'circuit' => $teacher ? '' : gnf_get_user_circuito( $id ),
		'original' => function_exists( 'gnf_get_impersonate_original_user' ) ? gnf_get_impersonate_original_user() : 0,
		'config' => gnf_panel_cache_version( 'config' ),
		'profile' => gnf_panel_cache_version( 'user_' . $id ),
		'version' => $teacher ? gnf_panel_cache_version( 'center_' . $center ) : array(
			gnf_panel_cache_version( 'supervisors' ), gnf_panel_cache_version( 'supervisor_' . $id ), gnf_panel_cache_version( 'territory' ),
		),
	);
}

function gnf_panel_cache_route( $request ) {
	return 'GET' === $request->get_method() && in_array( $request->get_route(), array(
		'/gnf/v1/docente/dashboard', '/gnf/v1/docente/retos', '/gnf/v1/docente/wizard',
		'/gnf/v1/supervisor/dashboard', '/gnf/v1/supervisor/centros',
	), true );
}

/** Only operational data is stored; action permissions and signed links are rebuilt. */
function gnf_panel_cache_without_actions( $data ) {
	if ( ! is_array( $data ) ) { return $data; }
	foreach ( $data as $key => $value ) {
		if ( in_array( $key, array( 'reportPdfUrl', 'canDownloadSchoolReport', 'reportPdfProvisional', 'docenteImpersonateUrl', 'canImpersonateDocente' ), true ) ) {
			unset( $data[ $key ] );
		} elseif ( is_array( $value ) ) {
			$data[ $key ] = gnf_panel_cache_without_actions( $value );
		}
	}
	return $data;
}

function gnf_panel_cache_restore_actions( $data, $route, $context ) {
	if ( '/gnf/v1/docente/dashboard' === $route ) {
		return array_merge( $data, gnf_get_docente_school_report_payload( $context['center'], $context['year'], ! empty( $data['allRetosComplete'] ) ? 'final' : 'draft' ) );
	}
	if ( '/gnf/v1/supervisor/centros' === $route ) {
		foreach ( $data as &$center ) {
			if ( ! is_array( $center ) || empty( $center['id'] ) ) { continue; }
			if ( isset( $center['annual'] ) ) {
				$center['annual']['reportPdfUrl'] = gnf_get_center_report_download_url( $center['id'], $context['year'] );
			}
			$url = current_user_can( 'manage_options' ) ? gnf_build_centro_docente_impersonate_url( $center['id'] ) : '';
			$center['docenteImpersonateUrl'] = $url;
			$center['canImpersonateDocente'] = '' !== $url;
		}
		unset( $center );
	}
	return $data;
}

/** rest_dispatch_request is called by core AFTER the route permission callback. */
function gnf_panel_cache_dispatch( $result, $request, $route, $handler ) {
	if ( null !== $result || ! gnf_panel_cache_route( $request ) ) { return $result; }
	// Fail closed even if another caller invokes this filter outside core dispatch.
	if ( empty( $handler['permission_callback'] ) || true !== call_user_func( $handler['permission_callback'], $request ) ) { return $result; }
	$teacher = 0 === strpos( $route, '/gnf/v1/docente/' );
	$context = gnf_panel_cache_context( $teacher );
	if ( ! $context['user'] || ( $teacher && ! $context['center'] ) ) { return $result; }
	$params = $request->get_params();
	unset( $params['_wpnonce'], $params['_fields'], $params['_embed'] );
	ksort( $params );
	$key = 'gnf_panel_v1_' . hash( 'sha256', wp_json_encode( array( $route, $params, $context ) ) );
	$cached = get_transient( $key );
	if ( is_array( $cached ) && isset( $cached['data'] ) ) {
		$response = new WP_REST_Response( gnf_panel_cache_restore_actions( $cached['data'], $route, $context ), 200 );
		$response->header( 'X-GNF-Panel-Cache', 'hit' );
		return $response;
	}
	$result = call_user_func( $handler['callback'], $request );
	if ( is_wp_error( $result ) ) { return $result; }
	$response = rest_ensure_response( $result );
	if ( 200 !== $response->get_status() || ! is_array( $response->get_data() ) ) { return $response; }
	$data = gnf_panel_cache_without_actions( $response->get_data() );
	// Unknown signed fields must never become persistent cached credentials.
	if ( false === strpos( wp_json_encode( $data ), '_wpnonce' ) && false === strpos( wp_json_encode( $data ), 'nonce' )
		&& $context === gnf_panel_cache_context( $teacher ) ) {
		set_transient( $key, array( 'data' => $data ), 2 * HOUR_IN_SECONDS );
	}
	$response->header( 'X-GNF-Panel-Cache', 'miss' );
	return $response;
}

/** Live revision signal piggybacks on the existing notification request. */
function gnf_panel_cache_after_request( $result, $handler, $request ) {
	$route = $request->get_route();
	if ( 0 !== strpos( $route, '/gnf/v1/' ) || is_wp_error( $result ) ) { return $result; }
	$response = rest_ensure_response( $result );
	if ( $response->get_status() >= 400 || ! get_current_user_id() ) { return $result; }
	$user = wp_get_current_user();
	$teacher = in_array( 'docente', (array) $user->roles, true );
	if ( ! in_array( $request->get_method(), array( 'GET', 'HEAD', 'OPTIONS' ), true ) ) {
		if ( 0 === strpos( $route, '/gnf/v1/docente/' ) ) {
			gnf_invalidate_docente_panel_cache( gnf_get_centro_for_docente( $user->ID ) );
		} elseif ( 0 === strpos( $route, '/gnf/v1/supervisor/' ) || 0 === strpos( $route, '/gnf/v1/comite/' ) ) {
			gnf_invalidate_supervisor_panel_cache( $user->ID );
		}
	}
	if ( '/gnf/v1/notifications' === $route || gnf_panel_cache_route( $request ) || 'GET' !== $request->get_method() ) {
		$context = gnf_panel_cache_context( $teacher );
		$response->header( 'X-GNF-Panel-Version', hash( 'sha256', wp_json_encode( $context ) ) );
		$response->header( 'X-GNF-Panel-Kind', $teacher ? 'docente' : 'supervisor' );
		$response->header( 'Cache-Control', 'private, no-store' );
	}
	return $response;
}

function gnf_rest_refresh_panel_cache( $request ) {
	gnf_invalidate_supervisor_panel_cache( get_current_user_id() );
	return array( 'success' => true );
}

function gnf_register_panel_cache_routes() {
	register_rest_route( 'gnf/v1', '/panel/cache-refresh', array(
		'methods' => 'POST', 'callback' => 'gnf_rest_refresh_panel_cache', 'permission_callback' => 'gnf_rest_is_supervisor',
	) );
}

function gnf_panel_cache_post_changed( $post_id, $post = null ) {
	$type = $post ? $post->post_type : get_post_type( $post_id );
	if ( 'centro_educativo' === $type ) {
		gnf_invalidate_docente_panel_cache( $post_id );
		gnf_bump_panel_cache_version( 'territory' );
	} elseif ( in_array( $type, array( 'reto', 'wpforms' ), true ) ) {
		gnf_bump_panel_cache_version( 'config' );
	}
}

function gnf_panel_cache_post_meta_changed( $meta_id, $post_id, $key ) {
	$type = get_post_type( $post_id );
	// Projected award caches are written during GETs; assigned awards are not.
	if ( 0 === strpos( $key, '_gnf_award_' ) && 0 !== strpos( $key, '_gnf_award_assigned_' ) ) { return; }
	if ( 'centro_educativo' === $type ) {
		gnf_invalidate_docente_panel_cache( $post_id );
		if ( in_array( $key, array( 'region', 'circuito', 'estado_centro', 'docentes_asociados' ), true ) ) { gnf_bump_panel_cache_version( 'territory' ); }
	} elseif ( in_array( $type, array( 'reto', 'wpforms' ), true ) ) {
		gnf_bump_panel_cache_version( 'config' );
	}
}

function gnf_panel_cache_user_changed( $meta_id, $user_id ) {
	gnf_bump_panel_cache_version( 'user_' . absint( $user_id ) );
}

function gnf_panel_cache_terms_changed( $object_id, $terms, $tt_ids, $taxonomy ) {
	if ( 'gn_region' === $taxonomy ) { gnf_panel_cache_post_changed( $object_id ); }
}

function gnf_panel_cache_option_changed( $option ) {
	if ( 0 === strpos( $option, 'options_' ) || 'anio_actual' === $option || 0 === strpos( $option, 'gnf_program_year_closed_' ) ) {
		gnf_bump_panel_cache_version( 'config' );
	}
}

add_filter( 'rest_dispatch_request', 'gnf_panel_cache_dispatch', 20, 4 );
add_filter( 'rest_request_after_callbacks', 'gnf_panel_cache_after_request', 20, 3 );
add_action( 'rest_api_init', 'gnf_register_panel_cache_routes' );
add_action( 'save_post', 'gnf_panel_cache_post_changed', 20, 2 );
add_action( 'before_delete_post', 'gnf_panel_cache_post_changed', 20, 2 );
foreach ( array( 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ) as $hook ) { add_action( $hook, 'gnf_panel_cache_post_meta_changed', 20, 3 ); }
foreach ( array( 'added_user_meta', 'updated_user_meta', 'deleted_user_meta' ) as $hook ) { add_action( $hook, 'gnf_panel_cache_user_changed', 20, 2 ); }
add_action( 'set_object_terms', 'gnf_panel_cache_terms_changed', 20, 4 );
add_action( 'updated_option', 'gnf_panel_cache_option_changed' );
add_action( 'added_option', 'gnf_panel_cache_option_changed' );
add_action( 'deleted_option', 'gnf_panel_cache_option_changed' );
