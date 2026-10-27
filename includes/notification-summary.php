<?php
/** Lightweight notification selection. No forms, preview URLs or reviewer lookups. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function gnf_invalidate_notification_summary( $user_id ) {
	delete_transient( 'gnf_notif_summary_v1_' . (int) $user_id );
}

/** Bounded legacy recovery, with no repeated incomplete migration on each heartbeat. */
function gnf_prepare_notification_backfill( $user_id, $rejections_only, $heartbeat = false ) {
	if ( ! $rejections_only || get_user_meta( $user_id, '_gnf_pause_notifications_backfilled_v1', true ) ) { return; }
	$key = 'gnf_notif_backfill_' . (int) $user_id;
	if ( $heartbeat && get_transient( $key ) ) { return; }
	gnf_backfill_docente_pause_notifications( $user_id );
	gnf_invalidate_notification_summary( $user_id );
	if ( ! get_user_meta( $user_id, '_gnf_pause_notifications_backfilled_v1', true ) ) { set_transient( $key, 1, 600 ); }
}

/** A signature read never joins or reads evidence. Permissions/revisions fence reuse. */
function gnf_notification_summary_dependencies( $user_id, $rejections_only, $centers ) {
	global $wpdb;
	$signature = $wpdb->get_row( $wpdb->prepare(
		"SELECT COUNT(*) AS total, COALESCE(MAX(id), 0) AS latest_id, COALESCE(MAX(created_at), '') AS latest_at,
		 COALESCE(SUM(leido <> 0), 0) AS read_count, COALESCE(SUM(CASE WHEN leido <> 0 THEN id ELSE 0 END), 0) AS read_ids
		 FROM {$wpdb->prefix}gn_notificaciones WHERE user_id = %d", $user_id
	) );
	if ( ! $signature ) { return new WP_Error( 'notifications_unavailable', 'No se pudieron consultar las notificaciones.', array( 'status' => 503 ) ); }
	$versions = array();
	foreach ( $centers as $center ) { $versions[ (int) $center ] = gnf_panel_cache_version( 'center_' . (int) $center ); }
	ksort( $versions );
	return array( 'user' => $user_id, 'policy' => $rejections_only, 'signature' => $signature, 'panel' => gnf_panel_cache_context( $rejections_only ), 'centers' => $versions );
}

function gnf_get_notification_summary( $user_id ) {
	$rejections_only = gnf_user_receives_only_rejections( $user_id );
	gnf_prepare_notification_backfill( $user_id, $rejections_only, true );
	$key = 'gnf_notif_summary_v1_' . (int) $user_id;
	$cacheable = function_exists( 'gnf_panel_cache_context' ) && function_exists( 'gnf_panel_cache_version' );
	$cached = $cacheable ? get_transient( $key ) : false;
	$centers = is_array( $cached ) ? ( $cached['centers'] ?? array() ) : array();
	$dependencies = $cacheable ? gnf_notification_summary_dependencies( $user_id, $rejections_only, $centers ) : array( 'user' => $user_id );
	if ( ! is_array( $dependencies ) ) { return $dependencies; }
	$fingerprint = hash( 'sha256', serialize( $dependencies ) );
	if ( is_array( $cached ) && $cached['fingerprint'] === $fingerprint ) { return $cached['summary']; }
	$centers = array();
	$items = gnf_select_notification_rows( $user_id, $rejections_only, static function ( $item ) use ( $user_id, $rejections_only, &$centers ) {
		if ( ! empty( $item->entry_centro ) ) { $centers[ (int) $item->entry_centro ] = (int) $item->entry_centro; }
		if ( $rejections_only && ! gnf_notification_summary_is_actionable( $item, $user_id ) ) { return null; }
		return array(
			'id' => (int) $item->id, 'leido' => (bool) $item->leido,
			'fingerprint' => hash( 'sha256', serialize( array( $item->tipo, $item->mensaje, $item->relacion_tipo, $item->relacion_id, $item->created_at ) ) ),
		);
	}, true );
	if ( ! is_array( $items ) ) { return $items; }
	if ( $cacheable ) {
		// Keep the pre-selection revisions: a concurrent invalidation cannot bless stale rows.
		foreach ( $centers as $center ) {
			if ( ! isset( $dependencies['centers'][ $center ] ) ) { $dependencies['centers'][ $center ] = gnf_panel_cache_version( 'center_' . $center ); }
		}
		$dependencies['centers'] = array_intersect_key( $dependencies['centers'], $centers );
		ksort( $dependencies['centers'] );
		$fingerprint = hash( 'sha256', serialize( $dependencies ) );
	}
	$summary = array(
		'unreadCount' => count( array_filter( $items, static function ( $item ) { return ! $item['leido']; } ) ),
		'total' => count( $items ),
		'version' => hash( 'sha256', serialize( array( $fingerprint, $items ) ) ),
	);
	if ( $cacheable ) { set_transient( $key, array( 'fingerprint' => $fingerprint, 'centers' => array_values( $centers ), 'summary' => $summary ), 600 ); }
	return $summary;
}

/** Both badge and history use the newest 50 eligible, deduplicated notifications. */
function gnf_select_notification_rows( $user_id, $rejections_only, $format, $summary = false ) {
	global $wpdb;
	$table = $wpdb->prefix . 'gn_notificaciones';
	$type_filter = $rejections_only ? " AND n.tipo IN ('evidencia_rechazada', 'evidencia_en_pausa', 'invalid_photo_date', 'correccion')" : '';
	$columns = 'n.*';
	$join = '';
	if ( $summary ) {
		// One batched read replaces the per-notification enrichment/query pipeline.
		$columns = 'n.*, e.anio AS entry_anio, e.centro_id AS entry_centro';
		if ( $rejections_only ) { $columns .= ', e.evidencias AS entry_evidencias'; }
		$join = " LEFT JOIN {$wpdb->prefix}gn_reto_entries e ON e.id = n.relacion_id AND (n.relacion_tipo = 'reto_entry' OR n.relacion_tipo LIKE 'reto_entry_evidence:%')";
	}
	$result = $seen = array();
	$offset = 0;
	do {
		$items = $wpdb->get_results( $wpdb->prepare(
			"SELECT {$columns} FROM {$table} n{$join} WHERE n.user_id = %d{$type_filter} ORDER BY n.created_at DESC, n.id DESC LIMIT 50 OFFSET %d",
			$user_id, $offset
		) );
		if ( ! is_array( $items ) ) {
			return new WP_Error( 'notifications_unavailable', 'No se pudieron consultar las notificaciones.', array( 'status' => 503 ) );
		}
		foreach ( $items as $item ) {
			$key = (string) $item->id;
			if ( $rejections_only && gnf_get_notification_evidence_scope_key( $item->relacion_tipo ) ) {
				$key = $item->relacion_tipo . ':' . $item->relacion_id;
			}
			if ( isset( $seen[ $key ] ) ) { continue; }
			$formatted = $format( $item );
			if ( null === $formatted ) { continue; }
			$seen[ $key ] = true;
			$result[] = $formatted;
			if ( count( $result ) >= 50 ) { break; }
		}
		$offset += 50;
	} while ( $rejections_only && count( $items ) === 50 && count( $result ) < 50 );
	return $result;
}

/** Only legacy evidence without a persisted review state needs date normalization. */
function gnf_notification_summary_is_actionable( $item, $user_id ) {
	$relation = (string) $item->relacion_tipo;
	if ( ! $item->relacion_id || ( 'reto_entry' !== $relation && 0 !== strpos( $relation, 'reto_entry_evidence:' ) ) ) { return false; }
	$evidences = json_decode( (string) ( $item->entry_evidencias ?? '' ), true );
	if ( ! is_array( $evidences ) ) { return false; }
	$legacy = false;
	foreach ( $evidences as $evidence ) {
		if ( is_array( $evidence ) && empty( $evidence['replaced'] ) && ! isset( $evidence['estado'] ) ) { $legacy = true; break; }
	}
	$key = '';
	if ( $legacy ) {
		// Cache only legacy eligibility, never full contexts or signed/private links.
		// Every source field affecting identity, state or date is part of the key.
		$key = 'gnf_notif_legacy_v1_' . hash( 'sha256', serialize( array( $user_id, $item->tipo, $relation, $item->mensaje, $item->relacion_id, $item->entry_evidencias, $item->entry_anio ) ) );
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) { return $cached['actionable']; }
		foreach ( $evidences as &$evidence ) {
			if ( ! is_array( $evidence ) || ! empty( $evidence['replaced'] ) || isset( $evidence['estado'] ) ) { continue; }
			$missing_state = ! array_key_exists( 'estado', $evidence );
			if ( function_exists( 'gnf_apply_evidence_original_date' ) ) {
				$evidence = gnf_apply_evidence_original_date( $evidence, (int) $item->entry_anio );
			}
			$has_date_issue = function_exists( 'gnf_evidence_has_verifiable_date_issue' ) && gnf_evidence_has_verifiable_date_issue( $evidence );
			$evidence['estado'] = $has_date_issue ? 'rechazada' : 'pendiente';
			if ( $has_date_issue && $missing_state ) { $evidence['supervisor_comment'] = $evidence['warning'] ?? 'Rechazada: fecha EXIF no coincide con el año activo.'; }
		}
		unset( $evidence );
	}
	$matches = gnf_filter_notification_evidences( $item->tipo, $relation, $item->mensaje, $evidences );
	$actionable = gnf_docente_notification_is_actionable( $item->tipo, $matches );
	if ( $key ) { set_transient( $key, array( 'actionable' => $actionable ), 7200 ); }
	return $actionable;
}
