<?php
/**
 * Reportes y exportaciones.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exporta CSV por region o global.
 */
function gnf_export_csv( $region_id = null, $anio = null ) {
	if ( ! current_user_can( 'manage_options' ) && ! gnf_user_has_role( wp_get_current_user(), 'supervisor' ) ) {
		wp_die( 'Sin permisos' );
	}

	$anio  = gnf_normalize_year( $anio );
	$items = gnf_get_report_data( $region_id, $anio );

	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=guardianes-' . ( $region_id ? 'region-' . $region_id : 'global' ) . '-' . $anio . '.csv' );
	$output = fopen( 'php://output', 'w' );
	fputcsv( $output, array( 'Centro', 'Region', 'Reto ID', 'Puntaje', 'Estado', 'Año' ) );
	foreach ( $items as $item ) {
		fputcsv(
			$output,
			array(
				$item->centro,
				$item->region,
				$item->reto_id,
				$item->puntaje,
				$item->estado,
				$item->anio,
			)
		);
	}
	fclose( $output );
	exit;
}

/**
 * Obtiene datos para el reporte (CSV o Tabla).
 */
function gnf_get_report_data( $region_id = null, $anio = null ) {
	global $wpdb;
	$anio  = gnf_normalize_year( $anio );
	$table = $wpdb->prefix . 'gn_reto_entries';

	$where   = $wpdb->prepare( 'WHERE e.anio = %d', $anio );
	$join    = 'INNER JOIN ' . $wpdb->posts . ' c ON c.ID = e.centro_id';
	$join   .= ' LEFT JOIN ' . $wpdb->term_relationships . ' tr ON tr.object_id = c.ID';
	$join   .= ' LEFT JOIN ' . $wpdb->term_taxonomy . ' tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = "gn_region"';
	$join   .= ' LEFT JOIN ' . $wpdb->terms . ' t ON t.term_id = tt.term_id';

	if ( $region_id ) {
		$where .= $wpdb->prepare( ' AND t.term_id = %d', $region_id );
	}

	return $wpdb->get_results(
		"SELECT e.*, c.post_title as centro, t.name as region FROM {$table} e {$join} {$where} AND e.estado IN ('aprobado','enviado')"
	);
}

/**
 * Endpoint admin_post para exportar.
 */
function gnf_handle_export_csv() {
	$region = isset( $_GET['region'] ) ? absint( $_GET['region'] ) : null;
	$anio   = isset( $_GET['year'] ) ? absint( $_GET['year'] ) : gnf_get_context_year( gnf_get_active_year() );
	gnf_export_csv( $region, $anio );
}
add_action( 'admin_post_gnf_export_csv', 'gnf_handle_export_csv' );

/**
 * Resuelve el alcance permitido para exportaciones de centros.
 *
 * @param int|null $region_id Region solicitada.
 * @param string   $circuito  Circuito solicitado.
 * @return array{region_ids:int[],circuito:string}
 */
function gnf_get_centros_export_scope( $region_id = null, $circuito = '' ) {
	$user     = wp_get_current_user();
	$circuito = function_exists( 'gnf_normalize_circuito' ) ? gnf_normalize_circuito( $circuito ) : trim( (string) $circuito );

	if ( current_user_can( 'manage_options' ) ) {
		return array(
			'region_ids' => $region_id ? array( absint( $region_id ) ) : array(),
			'circuito'   => $circuito,
		);
	}

	if ( ! gnf_user_has_role( $user, 'supervisor' ) && ! gnf_user_has_role( $user, 'comite_bae' ) ) {
		wp_die( 'Sin permisos' );
	}

	$allowed_regions = function_exists( 'gnf_get_user_regions' ) ? gnf_get_user_regions( $user->ID ) : array();
	$allowed_regions = array_values( array_filter( array_map( 'absint', (array) $allowed_regions ) ) );

	if ( empty( $allowed_regions ) ) {
		wp_die( 'Sin region asignada' );
	}

	if ( $region_id ) {
		$requested_region = absint( $region_id );
		if ( ! in_array( $requested_region, $allowed_regions, true ) ) {
			wp_die( 'Sin permisos para esta region' );
		}
		$allowed_regions = array( $requested_region );
	}

	$user_circuito = function_exists( 'gnf_get_user_circuito' ) ? gnf_get_user_circuito( $user->ID ) : '';
	if ( '' !== $user_circuito ) {
		if ( '' !== $circuito && $circuito !== $user_circuito ) {
			wp_die( 'Sin permisos para este circuito' );
		}
		$circuito = $user_circuito;
	}

	return array(
		'region_ids' => $allowed_regions,
		'circuito'   => $circuito,
	);
}

/**
 * Query base para centros matriculados exportables.
 *
 * @param int[]  $region_ids Region term IDs. Empty = all regions.
 * @param string $circuito   Circuito normalizado.
 * @param int    $anio       Año.
 * @return WP_Post[]
 */
function gnf_get_centros_matriculados_for_export( $region_ids, $circuito, $anio ) {
	$centros_ids = gnf_get_centros_with_matricula( $anio );
	if ( empty( $centros_ids ) ) {
		return array();
	}

	$args = array(
		'post_type'      => 'centro_educativo',
		'posts_per_page' => -1,
		'post__in'       => $centros_ids,
		'orderby'        => 'title',
		'order'          => 'ASC',
	);

	if ( ! empty( $region_ids ) ) {
		$args['tax_query'] = array(
			array(
				'taxonomy' => 'gn_region',
				'field'    => 'term_id',
				'terms'    => array_values( array_map( 'absint', (array) $region_ids ) ),
			),
		);
	}

	if ( '' !== $circuito ) {
		$args['meta_query'] = array(
			array(
				'key'     => 'circuito',
				'value'   => function_exists( 'gnf_get_circuito_query_values' ) ? gnf_get_circuito_query_values( $circuito ) : array( $circuito ),
				'compare' => 'IN',
			),
		);
	}

	return get_posts( $args );
}

function gnf_get_centro_export_region_name( $centro_id ) {
	$terms = wp_get_object_terms( $centro_id, 'gn_region' );
	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return '';
	}

	return (string) $terms[0]->name;
}

function gnf_get_centro_export_region_id( $centro_id ) {
	$terms = wp_get_object_terms( $centro_id, 'gn_region', array( 'fields' => 'ids' ) );
	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return 0;
	}

	return absint( $terms[0] );
}

function gnf_get_centros_export_filename( $prefix, $region_id, $circuito, $anio ) {
	$scope = $region_id ? 'region-' . absint( $region_id ) : 'dre';
	if ( '' !== $circuito ) {
		$scope .= '-circuito-' . sanitize_title( $circuito );
	}

	return sprintf( '%s-%s-%d.csv', sanitize_title( $prefix ), $scope, absint( $anio ) );
}

function gnf_get_centro_export_profile( $centro_id ) {
	$tipologia = (string) ( get_field( 'tipologia', $centro_id ) ?: get_post_meta( $centro_id, 'tipologia', true ) ?: '' );
	$tipo      = (string) ( get_field( 'tipo_centro_educativo', $centro_id ) ?: get_post_meta( $centro_id, 'tipo_centro_educativo', true ) ?: '' );

	return array(
		'tipologia'                => $tipologia,
		'tipologia_label'          => function_exists( 'gnf_get_centro_choice_label' ) ? gnf_get_centro_choice_label( 'tipologia', $tipologia ) : $tipologia,
		'tipo_centro_educativo'    => $tipo,
		'tipo_centro_label'        => function_exists( 'gnf_get_centro_choice_label' ) ? gnf_get_centro_choice_label( 'tipo_centro_educativo', $tipo ) : $tipo,
	);
}

function gnf_export_yes_no( $value ) {
	return $value ? 'Sí' : 'No';
}

function gnf_get_centro_export_diagnostic( $has_registered, $has_matricula, $is_publish, $region_name, $docente_count ) {
	$messages = array();

	if ( ! $is_publish ) {
		$messages[] = 'Centro no publicado';
	}
	if ( '' === trim( (string) $region_name ) ) {
		$messages[] = 'Centro sin DRE';
	}
	if ( $has_registered && ! $has_matricula ) {
		$messages[] = 'Registrado por docente sin matrícula del año';
	}
	if ( ! $has_registered && $has_matricula ) {
		$messages[] = 'Matrícula del año sin cuenta docente asociada';
	}
	if ( ! $has_registered && ! $has_matricula ) {
		$messages[] = 'Sin registro docente ni matrícula del año';
	}
	if ( $has_registered && 0 === (int) $docente_count ) {
		$messages[] = 'Registro docente detectado sin usuario docente activo asociado';
	}

	return empty( $messages ) ? 'Registrado y matriculado en el año' : implode( '; ', $messages );
}

function gnf_get_centro_export_headers( $include_docente_columns = false ) {
	$headers = array(
		'Centro Educativo',
		'Código MEP',
		'Dirección Regional',
		'Circuito',
		'Tipo de Centro Educativo',
		'Tipología',
		'Retos Seleccionados',
		'Docentes asociados',
		'Registrado por docente',
		'Tiene matrícula del año',
		'Visible en panel DRE',
		'Diagnóstico',
		'Año',
	);

	if ( $include_docente_columns ) {
		array_splice(
			$headers,
			2,
			0,
			array(
				'Usuario docente ID',
				'Docente',
				'Correo docente',
			)
		);
	}

	return $headers;
}

function gnf_get_centro_export_row( $centro, $anio, $matricula_ids, $registered_ids, $include_docente_columns = false ) {
	$centro_id       = $centro instanceof WP_Post ? (int) $centro->ID : absint( $centro['id'] ?? 0 );
	$centro_title    = $centro instanceof WP_Post ? $centro->post_title : (string) ( $centro['nombre'] ?? '' );
	$profile         = $centro_id ? gnf_get_centro_export_profile( $centro_id ) : array();
	$docente_ids     = $centro_id && function_exists( 'gnf_get_claiming_docente_ids_for_centro' ) ? gnf_get_claiming_docente_ids_for_centro( $centro_id ) : array();
	$has_registered  = $centro_id && in_array( $centro_id, array_map( 'absint', (array) $registered_ids ), true );
	$has_matricula   = $centro_id && in_array( $centro_id, array_map( 'absint', (array) $matricula_ids ), true );
	$is_publish      = $centro_id && 'publish' === get_post_status( $centro_id );
	$region_name     = $centro_id ? gnf_get_centro_export_region_name( $centro_id ) : (string) ( $centro['region'] ?? '' );
	$circuito_centro = $centro_id
		? ( function_exists( 'gnf_normalize_circuito' ) ? gnf_normalize_circuito( get_post_meta( $centro_id, 'circuito', true ) ) : (string) get_post_meta( $centro_id, 'circuito', true ) )
		: (string) ( $centro['circuito'] ?? '' );
	$retos_ids       = $centro_id ? gnf_get_centro_retos_seleccionados( $centro_id, $anio ) : array();

	$row = array(
		$centro_title,
		$centro_id ? ( get_post_meta( $centro_id, 'codigo_mep', true ) ?: '' ) : '',
		$region_name,
		$circuito_centro,
		$profile['tipo_centro_label'] ?? '',
		$profile['tipologia_label'] ?? '',
		count( $retos_ids ),
		count( $docente_ids ),
		gnf_export_yes_no( $has_registered ),
		gnf_export_yes_no( $has_matricula ),
		gnf_export_yes_no( $has_matricula && $is_publish ),
		gnf_get_centro_export_diagnostic( $has_registered, $has_matricula, $is_publish, $region_name, count( $docente_ids ) ),
		$anio,
	);

	if ( $include_docente_columns ) {
		$docente_id    = absint( $centro['docente_id'] ?? ( $docente_ids[0] ?? 0 ) );
		$docente       = $docente_id ? get_userdata( $docente_id ) : null;
		$docente_name  = $docente instanceof WP_User ? $docente->display_name : (string) ( $centro['docente_name'] ?? '' );
		$docente_email = $docente instanceof WP_User ? $docente->user_email : (string) ( $centro['docente_email'] ?? '' );
		array_splice( $row, 2, 0, array( $docente_id ?: '', $docente_name, $docente_email ) );
	}

	return $row;
}

/**
 * Exporta CSV con listado de centros matriculados (una fila por centro).
 *
 * Columnas: Centro, Código MEP, Dirección Regional, Circuito, Tipo, Tipología,
 * Retos seleccionados y columnas diagnósticas.
 *
 * @param int|null $region_id Filtrar por región (term_id). Null = todas.
 * @param int|null $anio      Año. Default: año activo.
 */
function gnf_export_centros_csv( $region_id = null, $anio = null, $circuito = '' ) {
	if ( ! current_user_can( 'manage_options' ) && ! gnf_user_has_role( wp_get_current_user(), 'supervisor' ) && ! gnf_user_has_role( wp_get_current_user(), 'comite_bae' ) ) {
		wp_die( 'Sin permisos' );
	}

	$anio    = gnf_normalize_year( $anio );
	$scope   = gnf_get_centros_export_scope( $region_id, $circuito );
	$centros = gnf_get_centros_matriculados_for_export( $scope['region_ids'], $scope['circuito'], $anio );

	$filename = gnf_get_centros_export_filename( 'centros-matriculados', $region_id, $scope['circuito'], $anio );
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=' . $filename );

	$output = fopen( 'php://output', 'w' );
	fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) ); // BOM UTF-8.
	fputcsv( $output, gnf_get_centro_export_headers( false ) );

	$matricula_ids  = gnf_get_centros_with_matricula( $anio );
	$registered_ids = gnf_get_registered_centro_ids();
	foreach ( $centros as $centro ) {
		fputcsv( $output, gnf_get_centro_export_row( $centro, $anio, $matricula_ids, $registered_ids ) );
	}

	fclose( $output );
	exit;
}

/**
 * Endpoint admin_post para exportar centros matriculados.
 */
function gnf_handle_export_centros_csv() {
	$region   = isset( $_GET['region'] ) ? absint( $_GET['region'] ) : null;
	$anio     = isset( $_GET['year'] ) ? absint( $_GET['year'] ) : gnf_get_context_year( gnf_get_active_year() );
	$circuito = isset( $_GET['circuito'] ) ? sanitize_text_field( wp_unslash( $_GET['circuito'] ) ) : '';
	gnf_export_centros_csv( $region, $anio, $circuito );
}
add_action( 'admin_post_gnf_export_centros_csv', 'gnf_handle_export_centros_csv' );

/**
 * Exporta una lista simple de centros matriculados para DRE: nombre y codigo.
 */
function gnf_export_centros_matriculados_simple_csv( $region_id = null, $anio = null, $circuito = '' ) {
	$anio    = gnf_normalize_year( $anio );
	$scope   = gnf_get_centros_export_scope( $region_id, $circuito );
	$centros = gnf_get_centros_matriculados_for_export( $scope['region_ids'], $scope['circuito'], $anio );

	$filename = gnf_get_centros_export_filename( 'inscritos', $region_id, $scope['circuito'], $anio );
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=' . $filename );

	$output = fopen( 'php://output', 'w' );
	fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );
	fputcsv( $output, gnf_get_centro_export_headers( false ) );

	$matricula_ids  = gnf_get_centros_with_matricula( $anio );
	$registered_ids = gnf_get_registered_centro_ids();
	foreach ( $centros as $centro ) {
		fputcsv( $output, gnf_get_centro_export_row( $centro, $anio, $matricula_ids, $registered_ids ) );
	}

	fclose( $output );
	exit;
}

/**
 * Endpoint admin_post para lista simple de centros matriculados DRE.
 */
function gnf_handle_export_centros_matriculados_simple_csv() {
	$region   = isset( $_GET['region'] ) ? absint( $_GET['region'] ) : null;
	$anio     = isset( $_GET['year'] ) ? absint( $_GET['year'] ) : gnf_get_context_year( gnf_get_active_year() );
	$circuito = isset( $_GET['circuito'] ) ? sanitize_text_field( wp_unslash( $_GET['circuito'] ) ) : '';
	gnf_export_centros_matriculados_simple_csv( $region, $anio, $circuito );
}
add_action( 'admin_post_gnf_export_centros_matriculados_simple_csv', 'gnf_handle_export_centros_matriculados_simple_csv' );

function gnf_get_centros_diagnostico_for_export( $region_ids, $circuito, $anio ) {
	$matricula_ids  = gnf_get_centros_with_matricula( $anio );
	$registered_ids = gnf_get_registered_centro_ids();
	$centro_ids     = array_values(
		array_unique(
			array_filter(
				array_map(
					'absint',
					array_merge(
						(array) $matricula_ids,
						(array) $registered_ids
					)
				)
			)
		)
	);

	if ( empty( $centro_ids ) ) {
		return array();
	}

	$args = array(
		'post_type'      => 'centro_educativo',
		'post_status'    => array( 'publish', 'pending', 'draft', 'private' ),
		'posts_per_page' => -1,
		'post__in'       => $centro_ids,
		'orderby'        => 'title',
		'order'          => 'ASC',
	);

	if ( ! empty( $region_ids ) ) {
		$args['tax_query'] = array(
			array(
				'taxonomy' => 'gn_region',
				'field'    => 'term_id',
				'terms'    => array_values( array_map( 'absint', (array) $region_ids ) ),
			),
		);
	}

	if ( '' !== $circuito ) {
		$args['meta_query'] = array(
			array(
				'key'     => 'circuito',
				'value'   => function_exists( 'gnf_get_circuito_query_values' ) ? gnf_get_circuito_query_values( $circuito ) : array( $circuito ),
				'compare' => 'IN',
			),
		);
	}

	return get_posts( $args );
}

function gnf_get_docentes_sin_centro_for_export( $region_ids, $circuito ) {
	if ( '' !== $circuito ) {
		return array();
	}

	$users = get_users(
		array(
			'role'    => 'docente',
			'orderby' => 'display_name',
			'order'   => 'ASC',
			'number'  => -1,
		)
	);

	$rows = array();
	foreach ( $users as $user ) {
		if ( gnf_get_centro_for_docente( $user->ID ) ) {
			continue;
		}

		$user_regions = function_exists( 'gnf_get_user_regions' ) ? gnf_get_user_regions( $user->ID ) : array();
		if ( ! empty( $region_ids ) && empty( array_intersect( array_map( 'absint', (array) $region_ids ), array_map( 'absint', $user_regions ) ) ) ) {
			continue;
		}

		$region_name = '';
		if ( ! empty( $user_regions ) ) {
			$term        = get_term( (int) $user_regions[0], 'gn_region' );
			$region_name = ( $term && ! is_wp_error( $term ) ) ? (string) $term->name : '';
		}

		$rows[] = array(
			'id'            => 0,
			'nombre'        => '',
			'region'        => $region_name,
			'circuito'      => '',
			'docente_id'    => (int) $user->ID,
			'docente_name'  => $user->display_name,
			'docente_email' => $user->user_email,
		);
	}

	return $rows;
}

function gnf_get_docente_sin_centro_export_row( $row, $anio ) {
	return array(
		'',
		'',
		(int) ( $row['docente_id'] ?? 0 ),
		(string) ( $row['docente_name'] ?? '' ),
		(string) ( $row['docente_email'] ?? '' ),
		(string) ( $row['region'] ?? '' ),
		'',
		'',
		'',
		0,
		0,
		'Sí',
		'No',
		'No',
		'Cuenta docente sin centro educativo asociado',
		$anio,
	);
}

function gnf_export_centros_diagnostico_csv( $region_id = null, $anio = null, $circuito = '' ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Sin permisos.', 403 );
	}

	$anio      = gnf_normalize_year( $anio );
	$region_id = absint( $region_id );
	$circuito  = function_exists( 'gnf_normalize_circuito' ) ? gnf_normalize_circuito( $circuito ) : trim( (string) $circuito );
	$filename  = gnf_get_centros_export_filename( 'diagnostico-centros', $region_id, $circuito, $anio );
	gnf_prepare_file_download_response();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=' . $filename );

	$output = fopen( 'php://output', 'w' );
	fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );
	fputcsv( $output, gnf_centros_export_headers() );

	$row_count = 0;
	foreach ( gnf_iter_centros_export_records( $anio, $region_id, $circuito ) as $record ) {
		fputcsv( $output, gnf_centros_export_row( $record ) );
		$row_count++;
		if ( 0 === $row_count % 200 && function_exists( 'flush' ) ) {
			flush();
		}
	}
	fclose( $output );
	exit;
}

function gnf_handle_export_centros_diagnostico_csv() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Sin permisos.', 403 );
	}
	$region   = isset( $_GET['region'] ) ? absint( $_GET['region'] ) : null;
	$anio     = isset( $_GET['year'] ) ? absint( $_GET['year'] ) : gnf_get_context_year( gnf_get_active_year() );
	$circuito = isset( $_GET['circuito'] ) ? sanitize_text_field( wp_unslash( $_GET['circuito'] ) ) : '';
	gnf_export_centros_diagnostico_csv( $region, $anio, $circuito );
}
add_action( 'admin_post_gnf_export_centros_diagnostico_csv', 'gnf_handle_export_centros_diagnostico_csv' );

/**
 * Procesa la descarga XLSX completa.
 *
 * @return void
 */
function gnf_handle_export_centros_xlsx() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Sin permisos.', 403 );
	}
	check_admin_referer( 'gnf_export_centros_xlsx' );

	$anio     = isset( $_GET['year'] ) ? gnf_normalize_year( absint( $_GET['year'] ) ) : gnf_get_active_year();
	$region   = isset( $_GET['region'] ) ? absint( $_GET['region'] ) : 0;
	$circuito = isset( $_GET['circuito'] ) ? sanitize_text_field( wp_unslash( $_GET['circuito'] ) ) : '';
	gnf_export_centros_xlsx( $anio, $region, $circuito );
}
add_action( 'admin_post_gnf_export_centros_xlsx', 'gnf_handle_export_centros_xlsx' );

/**
 * Selecciona todas las entradas del centro para el reporte final.
 */
function gnf_get_entries_for_report( $centro_id, $anio ) {
	global $wpdb;
	$table = $wpdb->prefix . 'gn_reto_entries';
	return $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE centro_id = %d AND anio = %d ORDER BY updated_at ASC, id ASC",
			$centro_id,
			$anio
		)
	);
}
