<?php
/**
 * Durable reporting snapshots. Operational evidence scores remain live.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function gnf_report_snapshot_key( $year ) {
	return 'gnf_report_snapshot_' . absint( $year ) . '_v1';
}

function gnf_queue_report_refresh( $year ) {
	$error = get_option( 'gnf_report_error_' . absint( $year ), array() );
	$retry_at = ! empty( $error['at'] ) ? (int) $error['at'] + 300 : 0;
	$args = array( absint( $year ) );
	if ( ! wp_next_scheduled( 'gnf_refresh_report_snapshot', $args ) ) {
		wp_schedule_single_event( max( time() + 5, $retry_at ), 'gnf_refresh_report_snapshot', $args );
	}
}

function gnf_get_report_snapshot( $year ) {
	$year = gnf_normalize_year( $year );
	$snapshot = get_option( gnf_report_snapshot_key( $year ), array() );
	$snapshot = is_array( $snapshot ) ? $snapshot : array();
	$snapshot['ready'] = ! empty( $snapshot['ready'] );
	$snapshot['year'] = $year;
	$snapshot['stale'] = ! $snapshot['ready'] || time() - (int) ( $snapshot['generatedTimestamp'] ?? 0 ) >= 2 * HOUR_IN_SECONDS;
	if ( $snapshot['stale'] ) {
		gnf_queue_report_refresh( $year );
	}
	$snapshot['refreshing'] = (bool) wp_next_scheduled( 'gnf_refresh_report_snapshot', array( $year ) );
	return $snapshot;
}

function gnf_report_cron_schedules( $schedules ) {
	$schedules['gnf_two_hours'] = array( 'interval' => 2 * HOUR_IN_SECONDS, 'display' => 'Guardianes: cada 2 horas' );
	return $schedules;
}

function gnf_schedule_report_snapshots() {
	$event = wp_get_scheduled_event( 'gnf_report_snapshots_tick' );
	if ( $event && 'gnf_two_hours' !== $event->schedule ) {
		wp_clear_scheduled_hook( 'gnf_report_snapshots_tick' );
	}
	if ( ! wp_next_scheduled( 'gnf_report_snapshots_tick' ) ) {
		wp_schedule_event( time() + 30, 'gnf_two_hours', 'gnf_report_snapshots_tick' );
	}
	$year = gnf_get_active_year();
	// Init runs on every WordPress page: never load the large private snapshot here.
	if ( ! in_array( $year, (array) get_option( 'gnf_report_snapshot_years', array() ), true ) ) {
		gnf_queue_report_refresh( $year );
	}
}

function gnf_report_snapshots_tick() {
	$years = (array) get_option( 'gnf_report_snapshot_years', array() );
	$years[] = gnf_get_active_year();
	foreach ( array_unique( array_map( 'absint', $years ) ) as $year ) {
		if ( $year ) { gnf_queue_report_refresh( $year ); }
	}
}

/**
 * INSERT IGNORE, unlike add_option's upsert, admits exactly one worker.
 */
function gnf_acquire_report_lock( $year ) {
	$key = 'gnf_report_lock_' . absint( $year );
	$token = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( '', true );
	$lock = array( 'token' => $token, 'expires' => time() + 600 );
	global $wpdb;
	$raw = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $key ) );
	$old = $raw ? maybe_unserialize( $raw ) : false;
	if ( is_array( $old ) && (int) $old['expires'] < time() ) {
		gnf_release_report_lock( $year, $old );
	}
	$inserted = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $key, maybe_serialize( $lock ) ) );
	wp_cache_delete( $key, 'options' );
	wp_cache_delete( 'notoptions', 'options' );
	return 1 === $inserted ? $lock : false;
}

/** Fence every persistent write against the exact lease, not a cached read. */
function gnf_report_owned_option( $year, $lock, $key, $value = null, $delete = false ) {
	global $wpdb;
	if ( time() >= (int) $lock['expires'] ) { throw new RuntimeException( 'Report lease expired' ); }
	$lock_key = 'gnf_report_lock_' . absint( $year );
	if ( false === $wpdb->query( 'START TRANSACTION' ) ) { throw new RuntimeException( 'Report transaction failed' ); }
	try {
		// Hold the lease row through commit, including under READ COMMITTED isolation.
		$owner = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s FOR UPDATE", $lock_key ) );
		if ( $owner !== maybe_serialize( $lock ) || time() >= (int) $lock['expires'] ) { throw new RuntimeException( 'Report lease replaced' ); }
		if ( $delete ) {
			$sql = $wpdb->prepare( "DELETE target FROM {$wpdb->options} target INNER JOIN {$wpdb->options} lease ON lease.option_name = %s AND lease.option_value = %s WHERE target.option_name = %s", $lock_key, maybe_serialize( $lock ), $key );
		} else {
			$sql = $wpdb->prepare( "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) SELECT %s, %s, 'no' FROM {$wpdb->options} lease WHERE lease.option_name = %s AND lease.option_value = %s ON DUPLICATE KEY UPDATE option_value = VALUES(option_value), autoload = 'no'", $key, maybe_serialize( $value ), $lock_key, maybe_serialize( $lock ) );
		}
		$result = $wpdb->query( $sql );
		$owner = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $lock_key ) );
		if ( false === $result || $owner !== maybe_serialize( $lock ) ) { throw new RuntimeException( 'Report persistence failed or lease replaced' ); }
		if ( ! $delete ) {
			$saved = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $key ) );
			if ( $saved !== maybe_serialize( $value ) ) { throw new RuntimeException( 'Report checkpoint was not saved' ); }
		}
		if ( time() >= (int) $lock['expires'] || false === $wpdb->query( 'COMMIT' ) ) { throw new RuntimeException( 'Report commit failed or lease expired' ); }
	} catch ( Throwable $error ) {
		$wpdb->query( 'ROLLBACK' );
		wp_cache_delete( $key, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		throw $error;
	}
	wp_cache_delete( $key, 'options' );
	wp_cache_delete( 'notoptions', 'options' );
}

function gnf_release_report_lock( $year, $lock ) {
	global $wpdb;
	$key = 'gnf_report_lock_' . absint( $year );
	// Compare-and-delete prevents an expired worker from releasing a newer lock.
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $key, maybe_serialize( $lock ) ) );
	wp_cache_delete( $key, 'options' );
	wp_cache_delete( 'notoptions', 'options' );
}

/**
 * One cron event processes at most 200 centers, then checkpoints its cursor.
 * Only a completely built snapshot replaces the last good one.
 */
function gnf_refresh_report_snapshot( $year ) {
	global $wpdb;
	$year = gnf_normalize_year( $year );
	$lock = gnf_acquire_report_lock( $year );
	if ( ! $lock ) { gnf_queue_report_refresh( $year ); return; }
	$job_key = 'gnf_report_job_' . $year;
	try {
		$job = get_option( $job_key, array() );
		if ( empty( $job ) || time() - (int) ( $job['started'] ?? 0 ) > 2 * HOUR_IN_SECONDS ) {
			$catalog = gnf_get_impact_metric_catalog( $year );
			$map = gnf_impact_reto_map( $year );
			foreach ( $catalog as &$metric ) {
				$metric['available'] = gnf_impact_metric_is_available( $metric, $year, $map );
				$metric['sourceLabel'] = gnf_report_source_label( $metric );
				$metric['source'] = gnf_report_source_key( $metric );
			}
			unset( $metric );
			$wpdb->last_error = '';
			$center_ids = gnf_get_centros_with_matricula( $year );
			if ( ! empty( $wpdb->last_error ) ) { throw new RuntimeException( 'Report enrollment query failed' ); }
			$job = array( 'started' => time(), 'cursor' => 0, 'ids' => array_values( array_unique( array_map( 'absint', $center_ids ) ) ), 'catalog' => array_values( $catalog ), 'centros' => array() );
		}
		$ids = array_slice( $job['ids'], $job['cursor'], 200 );
		if ( $ids ) {
			$records = gnf_get_impact_center_records( $year, $ids, true );
			foreach ( $records as $record ) {
				$job['centros'][] = gnf_report_center_snapshot( $record, $year, $job['catalog'] );
			}
			$job['cursor'] += count( $ids );
		}
		if ( $job['cursor'] < count( $job['ids'] ) ) {
			gnf_report_owned_option( $year, $lock, $job_key, $job );
			gnf_queue_report_refresh( $year );
			return;
		}
		$snapshot = array( 'ready' => true, 'year' => $year, 'generatedTimestamp' => time(), 'generatedAt' => current_time( 'mysql' ), 'centros' => $job['centros'], 'impact' => array() );
		foreach ( array( 'active', 'approved' ) as $mode ) {
			$snapshot['impact'][ $mode ] = gnf_aggregate_report_centers( $job['centros'], $job['catalog'], $mode );
			$snapshot['impact'][ $mode ]['year'] = $year;
			$snapshot['impact'][ $mode ]['generatedAt'] = $snapshot['generatedAt'];
		}
		gnf_report_owned_option( $year, $lock, gnf_report_snapshot_key( $year ), $snapshot );
		$years = (array) get_option( 'gnf_report_snapshot_years', array() );
		$years[] = $year;
		gnf_report_owned_option( $year, $lock, 'gnf_report_snapshot_years', array_values( array_unique( $years ) ) );
		gnf_report_owned_option( $year, $lock, $job_key, null, true );
		gnf_report_owned_option( $year, $lock, 'gnf_report_error_' . $year, null, true );
	} catch ( Throwable $error ) {
		// Keep the previous snapshot and cursor; the next scheduled tick can retry.
		try { gnf_report_owned_option( $year, $lock, 'gnf_report_error_' . $year, array( 'at' => time(), 'message' => 'No se pudo actualizar el Panel de Impacto.' ) ); } catch ( Throwable $ignored ) { /* A successor owns recovery. */ }
		error_log( '[Guardianes] Report snapshot failed for year ' . $year . ': ' . get_class( $error ) );
	} finally {
		gnf_release_report_lock( $year, $lock );
	}
}

function gnf_report_source_key( $metric ) {
	return ! empty( $metric['reto'] ) ? 'reto-' . $metric['reto'] : ( 'center' === ( $metric['source'] ?? '' ) ? 'inscripcion' : 'eco-retos' );
}

function gnf_report_source_label( $metric ) {
	$names = array( 'agua' => 'Reto Agua', 'electricidad' => 'Reto Energía', 'residuos' => 'Reto Residuos', 'gestion-de-organicos' => 'Gestión de Orgánicos', 'limpiezas' => 'Reto Limpiezas', 'eco-lonchera' => 'Eco Loncheras', 'jardines-y-polinizadores' => 'Jardines para Polinizadores', 'huerta' => 'Reto Huerta', 'eco-gira' => 'Eco Gira', 'eco-emprendimiento' => 'Eco Emprendimiento', 'siembra-de-arboles' => 'Siembra de Árboles', 'eco-clubes' => 'Eco Clubes Estudiantiles', 'artistico-eco-murales' => 'Eco Murales', 'evento-sostenible' => 'Evento Sostenible', 'bienestar-animal' => 'Bienestar Animal', 'comodin' => 'Reto Comodín' );
	return ! empty( $metric['reto'] ) ? ( $names[ $metric['reto'] ] ?? $metric['reto'] ) : ( 'center' === ( $metric['source'] ?? '' ) ? 'Inscripción' : 'Eco Retos' );
}

function gnf_report_numeric_response( $value ) {
	if ( ! is_scalar( $value ) || is_bool( $value ) ) { return null; }
	$text = trim( str_replace( array( '$', "\xe2\x82\xa1", "\xc2\xa0" ), '', (string) $value ) );
	if ( ! preg_match( '/^[+-]?\d[\d.,\s]*$/', $text ) ) { return null; }
	$text = preg_replace( '/\s+/', '', $text );
	if ( false !== strpos( $text, ',' ) && false !== strpos( $text, '.' ) ) {
		$decimal = strrpos( $text, ',' ) > strrpos( $text, '.' ) ? ',' : '.';
		$text = str_replace( ',' === $decimal ? '.' : ',', '', $text );
	}
	$text = str_replace( ',', '.', $text );
	return is_numeric( $text ) && is_finite( (float) $text ) ? (float) $text : null;
}

function gnf_report_yes_response( $value ) {
	if ( ! is_scalar( $value ) ) { return null; }
	$text = strtolower( trim( str_replace( array( 'Sí', 'sí' ), 'si', (string) $value ) ) );
	if ( in_array( $text, array( 'si', 'yes', '1', 'true' ), true ) ) { return 1.0; }
	if ( in_array( $text, array( 'no', '0', 'false' ), true ) ) { return 0.0; }
	return null;
}

function gnf_report_center_snapshot( $record, $year, $display_catalog ) {
	$catalog = gnf_get_impact_metric_catalog( $year );
	$availability = array_column( $display_catalog, 'available', 'key' );
	$values = array();
	foreach ( array( 'active', 'approved' ) as $mode ) {
		foreach ( $catalog as $key => $metric ) {
			$value = gnf_impact_metric_value_for_record( $record, $metric, $mode );
			if ( empty( $availability[ $key ] ) ) { $value = null; }
			if ( 'response' === $metric['source'] ) {
				$entry = $record['entries'][ $metric['reto'] ] ?? array();
				$response = $entry['responses'][ $metric['field'] ] ?? null;
				if ( ! gnf_impact_entry_qualifies( $entry, $mode ) || null === $response || '' === trim( (string) ( is_scalar( $response ) ? $response : '' ) ) ) { $value = null; }
				elseif ( 'sum' === ( $metric['operation'] ?? '' ) ) { $value = gnf_report_numeric_response( $response ); }
				elseif ( 'count_yes' === ( $metric['operation'] ?? '' ) ) { $value = gnf_report_yes_response( $response ); }
			}
			$values[ $mode ][ $key ] = $value;
		}
	}
	return array( 'profile' => $record['profile'], 'values' => $values, 'stats' => $record['stats'], 'retos' => array_values( $record['retos'] ?? array() ) );
}

/** Two indexed bulk queries refresh access boundaries without recomputing statistics. */
function gnf_report_live_center_scopes( $ids ) {
	global $wpdb;
	if ( ! $ids ) { return array(); }
	$id_sql = implode( ',', array_unique( array_map( 'absint', $ids ) ) );
	$rows = $wpdb->get_results( "SELECT p.ID, p.post_status, pm.meta_key, pm.meta_value FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key IN ('circuito','region','estado_centro') WHERE p.ID IN ($id_sql) AND p.post_type = 'centro_educativo'", ARRAY_A );
	if ( ! empty( $wpdb->last_error ) ) { throw new RuntimeException( 'Unable to verify current center permissions' ); }
	$scopes = array();
	foreach ( $rows as $row ) {
		$id = (int) $row['ID'];
		if ( ! isset( $scopes[ $id ] ) ) { $scopes[ $id ] = array( 'region_id' => 0, 'region_name' => '', 'circuito' => '', 'post_status' => $row['post_status'], 'estado_centro' => '' ); }
		if ( 'region' === $row['meta_key'] ) { $scopes[ $id ]['region_id'] = absint( $row['meta_value'] ); }
		elseif ( $row['meta_key'] ) { $scopes[ $id ][ $row['meta_key'] ] = $row['meta_value']; }
	}
	$regions = $wpdb->get_results( "SELECT tr.object_id, t.term_id, t.name FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'gn_region' INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id WHERE tr.object_id IN ($id_sql) ORDER BY t.term_id", ARRAY_A );
	if ( ! empty( $wpdb->last_error ) ) { throw new RuntimeException( 'Unable to verify current center regions' ); }
	$seen = array();
	foreach ( $regions as $row ) {
		$id = (int) $row['object_id'];
		if ( isset( $scopes[ $id ] ) && ! isset( $seen[ $id ] ) ) { $scopes[ $id ]['region_id'] = (int) $row['term_id']; $scopes[ $id ]['region_name'] = $row['name']; $seen[ $id ] = true; }
	}
	return $scopes;
}

function gnf_aggregate_report_centers( $centers, $catalog, $mode ) {
	$keys = array_column( $catalog, 'key' );
	$blank = array_fill_keys( $keys, null );
	$empty = array( 'values' => $blank, 'coverage' => array_fill_keys( $keys, 0 ) );
	$total = array_merge( $empty, array( 'id' => 'total', 'label' => 'Total del alcance seleccionado' ) );
	$regions = array(); $circuits = array();
	foreach ( $centers as $center ) {
		$p = $center['profile']; $rid = (string) (int) $p['region_id']; $circuit = gnf_normalize_circuito( $p['circuito'] ?? '' );
		$cid = $rid . '|' . ( $circuit ?: 'sin-circuito' );
		if ( ! isset( $regions[ $rid ] ) ) { $regions[ $rid ] = array_merge( $empty, array( 'id' => $rid, 'label' => $p['region_name'] ?: 'Sin DRE' ) ); }
		if ( ! isset( $circuits[ $cid ] ) ) { $circuits[ $cid ] = array_merge( $empty, array( 'id' => $cid, 'label' => $circuit ? 'Circuito ' . $circuit : 'Sin circuito', 'regionId' => (int) $rid, 'regionName' => $p['region_name'], 'circuito' => $circuit ) ); }
		foreach ( $keys as $key ) {
			$value = $center['values'][ $mode ][ $key ] ?? null;
			if ( null === $value ) { continue; }
			foreach ( array( &$total, &$regions[ $rid ], &$circuits[ $cid ] ) as &$scope ) {
				$scope['values'][ $key ] = ( $scope['values'][ $key ] ?? 0 ) + $value;
				$scope['coverage'][ $key ]++;
			}
			unset( $scope );
		}
	}
	return array( 'mode' => $mode, 'catalog' => array_values( $catalog ), 'total' => $total, 'regions' => $regions, 'circuits' => $circuits );
}

/**
 * Permissions are evaluated on every read/download, never baked into a snapshot.
 */
function gnf_scope_report_snapshot( $snapshot, $region = 0, $circuit = '', $mode = 'active', $sources = array() ) {
	$admin = gnf_rest_is_admin();
	if ( ! $admin && ! gnf_rest_is_supervisor() ) { return new WP_Error( 'gnf_report_forbidden', 'No tienes acceso al Panel de Impacto.', array( 'status' => 403 ) ); }
	$region = $region ?? 0;
	if ( ! is_scalar( $region ) || ! preg_match( '/^\d+$/', (string) $region ) || ! is_scalar( $circuit ) || ( '' !== (string) $circuit && ! preg_match( '/^\d{1,3}$/', (string) $circuit ) ) || ! is_string( $mode ) || ! in_array( $mode, array( 'active', 'approved' ), true ) ) {
		return new WP_Error( 'gnf_report_invalid_filter', 'Los filtros de DRE, circuito o criterio son inválidos.', array( 'status' => 400 ) );
	}
	$region = absint( $region );
	$circuit = gnf_normalize_circuito( $circuit );
	$mode = 'approved' === $mode ? 'approved' : 'active';
	$allowed_regions = $admin ? null : array_map( 'absint', gnf_get_user_regions( get_current_user_id() ) );
	$assigned_circuit = $admin ? '' : gnf_normalize_circuito( gnf_get_user_circuito( get_current_user_id() ) );
	if ( ! $admin && ( ( $region && ! in_array( $region, $allowed_regions, true ) ) || ( $assigned_circuit && $circuit && $circuit !== $assigned_circuit ) ) ) {
		return new WP_Error( 'gnf_report_scope_forbidden', 'La DRE o el circuito no pertenece a tu alcance autorizado.', array( 'status' => 403 ) );
	}
	if ( ! $admin && ! $region && 1 === count( $allowed_regions ) ) { $region = (int) reset( $allowed_regions ); }
	$circuit = $assigned_circuit ?: $circuit;
	$catalog = $snapshot['impact'][ $mode ]['catalog'] ?? array();
	$available_sources = array();
	foreach ( $catalog as $metric ) { $available_sources[ $metric['source'] ?? '' ] = array( 'value' => $metric['source'] ?? '', 'label' => $metric['sourceLabel'] ?? '' ); }
	$sources = is_array( $sources ) ? $sources : explode( ',', (string) $sources );
	foreach ( $sources as $source ) {
		if ( ! is_scalar( $source ) ) { return new WP_Error( 'gnf_report_invalid_source', 'Fuente inválida.', array( 'status' => 400 ) ); }
	}
	$sources = array_values( array_unique( array_filter( array_map( 'trim', $sources ) ) ) );
	$all_sources = array_unique( array_column( $catalog, 'source' ) );
	if ( $sources && $catalog && array_diff( $sources, $all_sources ) ) { return new WP_Error( 'gnf_report_invalid_source', 'Fuente desconocida.', array( 'status' => 400 ) ); }
	if ( $sources ) { $catalog = array_values( array_filter( $catalog, static function( $m ) use ( $sources ) { return in_array( $m['source'] ?? '', $sources, true ); } ) ); }
	$centers = array(); $available_regions = array(); $available_circuits = array();
	try { $live_scopes = gnf_report_live_center_scopes( array_column( array_column( (array) ( $snapshot['centros'] ?? array() ), 'profile' ), 'centro_id' ) ); }
	catch ( Throwable $error ) { return new WP_Error( 'gnf_report_scope_unavailable', 'No se pudo verificar el acceso territorial. Intenta nuevamente.', array( 'status' => 503 ) ); }
	foreach ( (array) ( $snapshot['centros'] ?? array() ) as $center ) {
		$p = $center['profile']; $live = $live_scopes[ $p['centro_id'] ] ?? null;
		if ( ! $live || 'publish' !== $live['post_status'] || in_array( $live['estado_centro'], array( 'inactivo', 'rechazado', 'pendiente_de_revision_admin' ), true ) ) { continue; }
		$p['region_name'] = $live['region_name'] ?: ( (int) $live['region_id'] === (int) $p['region_id'] ? $p['region_name'] : 'DRE ' . $live['region_id'] );
		$p['region_id'] = $live['region_id']; $p['circuito'] = gnf_normalize_circuito( $live['circuito'] ); $center['profile'] = $p;
		$rid = (int) $p['region_id']; $cv = $p['circuito'];
		if ( ! $admin && ( ! in_array( $rid, $allowed_regions, true ) || ( $assigned_circuit && $cv !== $assigned_circuit ) ) ) { continue; }
		if ( $rid ) { $available_regions[ $rid ] = array( 'id' => $rid, 'name' => $p['region_name'] ?: 'Sin DRE' ); }
		if ( $region && $region !== $rid ) { continue; }
		if ( $cv ) { $available_circuits[ $rid . '|' . $cv ] = array( 'regionId' => $rid, 'value' => $cv, 'label' => 'Circuito ' . $cv ); }
		if ( $circuit && $cv !== $circuit ) { continue; }
		$centers[] = $center;
	}
	$report = gnf_aggregate_report_centers( $centers, $catalog, $mode );
	$report['year'] = (int) ( $snapshot['year'] ?? 0 );
	$report['generatedAt'] = (string) ( $snapshot['generatedAt'] ?? '' );
	$summary = array( 'totalCentros' => count( $centers ), 'totalAprobados' => 0, 'promedioEstrellas' => 0, 'promedioPuntaje' => 0, 'centrosConEvidencias' => 0 );
	foreach ( $centers as $center ) {
		$stats = $center['stats'];
		$summary['totalAprobados'] += (int) ( $stats['aprobados'] ?? 0 );
		$summary['promedioEstrellas'] += (int) ( $stats['estrellas'] ?? 0 );
		$summary['promedioPuntaje'] += (int) ( $stats['puntaje'] ?? 0 );
		$summary['centrosConEvidencias'] += ! empty( $center['values'][ $mode ]['centros_con_evidencias'] ) ? 1 : 0;
	}
	foreach ( array( 'promedioEstrellas', 'promedioPuntaje' ) as $key ) { $summary[ $key ] = count( $centers ) ? round( $summary[ $key ] / count( $centers ), 2 ) : 0; }
	return array( 'ready' => ! empty( $snapshot['ready'] ), 'year' => $report['year'], 'generatedAt' => $report['generatedAt'], 'stale' => ! empty( $snapshot['stale'] ), 'refreshing' => ! empty( $snapshot['refreshing'] ), 'canRefresh' => $admin, 'centros' => $centers, 'summary' => $summary, 'impact' => $report, 'availableSources' => array_values( $available_sources ), 'availableRegions' => array_values( $available_regions ), 'availableCircuits' => array_values( $available_circuits ), 'filters' => array( 'region' => $region, 'circuit' => $circuit, 'mode' => $mode, 'sources' => $sources ) );
}

function gnf_report_indicator_export_rows( $scoped ) {
	$catalog = $scoped['impact']['catalog'];
	$headers = array( 'Nivel', 'Territorio', 'Año', 'Actualizado al', 'Criterio' );
	foreach ( $catalog as $metric ) { $headers[] = $metric['title'] . ' (' . $metric['unit'] . ')'; }
	yield $headers;
	$scopes = array( array( 'Total', $scoped['impact']['total'] ) );
	foreach ( $scoped['impact']['regions'] as $region ) { $scopes[] = array( 'DRE', $region ); }
	foreach ( $scoped['impact']['circuits'] as $circuit ) { $circuit['label'] = $circuit['regionName'] . ' / ' . $circuit['label']; $scopes[] = array( 'Circuito', $circuit ); }
	foreach ( $scopes as $item ) {
		$row = array( $item[0], $item[1]['label'], $scoped['year'], $scoped['generatedAt'], 'approved' === $scoped['impact']['mode'] ? 'Validados' : 'Reportados' );
		foreach ( $catalog as $metric ) { $row[] = $item[1]['values'][ $metric['key'] ] ?? 'Sin datos'; }
		yield $row;
	}
}

function gnf_rest_reports_overview( $request ) {
	$year = gnf_normalize_year( $request->get_param( 'year' ) );
	$result = gnf_scope_report_snapshot( gnf_get_report_snapshot( $year ), $request->get_param( 'region' ), $request->get_param( 'circuit' ) ?? '', $request->get_param( 'mode' ) ?? 'approved', $request->get_param( 'sources' ) ?? array() );
	if ( is_wp_error( $result ) ) { return $result; }
	$result['exports'] = $result['ready'] && function_exists( 'gnf_get_impact_export_urls' ) ? gnf_get_impact_export_urls( $year, $result['filters'] ) : array();
	unset( $result['centros'] );
	return $result;
}

function gnf_rest_reports_refresh( $request ) {
	if ( ! gnf_rest_is_admin() ) { return new WP_Error( 'gnf_report_forbidden', 'Solo administración puede solicitar una actualización.', array( 'status' => 403 ) ); }
	$year = gnf_normalize_year( $request->get_param( 'year' ) );
	gnf_queue_report_refresh( $year );
	if ( function_exists( 'gnf_invalidate_supervisor_panel_cache' ) ) { gnf_invalidate_supervisor_panel_cache(); }
	return array( 'success' => true, 'refreshing' => true );
}

function gnf_render_impact_admin_toolbar( $year ) {
	if ( ! gnf_rest_is_admin() ) { return; }
	$snapshot = gnf_get_report_snapshot( $year );
	$region = isset( $_GET['gnf_report_region'] ) ? absint( $_GET['gnf_report_region'] ) : 0;
	$circuit = isset( $_GET['gnf_report_circuit'] ) && is_scalar( $_GET['gnf_report_circuit'] ) ? (string) wp_unslash( $_GET['gnf_report_circuit'] ) : '';
	$scoped = gnf_scope_report_snapshot( $snapshot, $region, $circuit, 'approved' );
	if ( is_wp_error( $scoped ) ) { echo '<div class="notice notice-error"><p>Filtro territorial inválido.</p></div>'; return; }
	$link = add_query_arg( array( 'p' => 'reportes', 'year' => $year, 'region' => $region, 'circuit' => $circuit, 'mode' => 'approved' ), home_url( '/panel-admin/' ) );
	?>
	<section style="background:#fff;border-left:4px solid #16866f;padding:18px 22px;margin:18px 0" aria-label="Panel de Impacto">
		<h2 style="margin-top:0">Panel de Impacto</h2>
		<p><?php echo esc_html( $snapshot['ready'] ? 'Datos actualizados al ' . $snapshot['generatedAt'] . ( $snapshot['stale'] ? ' (actualización en proceso)' : '' ) : 'Preparando los primeros resultados en segundo plano.' ); ?></p>
		<form method="get" style="display:flex;flex-wrap:wrap;gap:10px;align-items:end;margin-bottom:12px">
			<input type="hidden" name="page" value="gnf-admin"><input type="hidden" name="gnf_year" value="<?php echo esc_attr( $year ); ?>">
			<label>DRE<br><select name="gnf_report_region"><option value="0">Todas las DRE</option><?php foreach ( $scoped['availableRegions'] as $item ) : ?><option value="<?php echo esc_attr( $item['id'] ); ?>" <?php selected( $region, $item['id'] ); ?>><?php echo esc_html( $item['name'] ); ?></option><?php endforeach; ?></select></label>
			<label>Circuito<br><select name="gnf_report_circuit"><option value="">Todos los circuitos</option><?php foreach ( $scoped['availableCircuits'] as $item ) : ?><option value="<?php echo esc_attr( $item['value'] ); ?>" <?php selected( $circuit, $item['value'] ); ?>><?php echo esc_html( $item['label'] ); ?></option><?php endforeach; ?></select></label>
			<button type="submit" class="button">Aplicar filtros</button>
		</form>
		<a class="button button-primary" href="<?php echo esc_url( $link ); ?>">Abrir Panel de Impacto</a>
		<?php if ( $snapshot['ready'] && function_exists( 'gnf_get_impact_export_urls' ) ) : $urls = gnf_get_impact_export_urls( $year, $scoped['filters'] ); ?>
			<?php foreach ( array( 'indicators' => 'Indicadores XLSX', 'centros' => 'Centros y matrícula XLSX', 'retos' => 'Retos XLSX', 'pdfSummary' => 'PDF ejecutivo', 'pdfFull' => 'PDF completo' ) as $key => $label ) : ?>
				<a class="button" href="<?php echo esc_url( $urls[ $key ] ); ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		<?php endif; ?>
	</section>
	<?php
}

add_filter( 'cron_schedules', 'gnf_report_cron_schedules' );
add_action( 'init', 'gnf_schedule_report_snapshots' );
add_action( 'gnf_report_snapshots_tick', 'gnf_report_snapshots_tick' );
add_action( 'gnf_refresh_report_snapshot', 'gnf_refresh_report_snapshot' );
