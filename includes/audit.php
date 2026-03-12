<?php
/**
 * Audit logs and lightweight event tracking.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function gnf_get_primary_user_role( $user ) {
	$user = is_numeric( $user ) ? get_userdata( (int) $user ) : $user;
	if ( ! $user instanceof WP_User ) {
		return '';
	}

	$priority = array( 'administrator', 'docente', 'supervisor', 'comite_bae' );
	foreach ( $priority as $role ) {
		if ( in_array( $role, (array) $user->roles, true ) ) {
			return $role;
		}
	}

	return $user->roles[0] ?? '';
}

function gnf_log_audit_event( $event_key, $args = array() ) {
	global $wpdb;

	$table       = $wpdb->prefix . 'gn_audit_logs';
	$table_check = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	if ( $table_check !== $table ) {
		return false;
	}

	$actor_user_id = isset( $args['actor_user_id'] ) ? absint( $args['actor_user_id'] ) : get_current_user_id();
	$actor_user    = $actor_user_id ? get_userdata( $actor_user_id ) : null;
	$meta          = isset( $args['meta'] ) && is_array( $args['meta'] ) ? $args['meta'] : array();

	$inserted = $wpdb->insert(
		$table,
		array(
			'event_key'      => sanitize_key( $event_key ),
			'actor_user_id'  => $actor_user_id ?: null,
			'actor_role'     => isset( $args['actor_role'] ) ? sanitize_key( $args['actor_role'] ) : gnf_get_primary_user_role( $actor_user ),
			'target_user_id' => isset( $args['target_user_id'] ) ? absint( $args['target_user_id'] ) : null,
			'centro_id'      => isset( $args['centro_id'] ) ? absint( $args['centro_id'] ) : null,
			'reto_id'        => isset( $args['reto_id'] ) ? absint( $args['reto_id'] ) : null,
			'anio'           => isset( $args['anio'] ) ? absint( $args['anio'] ) : null,
			'panel'          => isset( $args['panel'] ) ? sanitize_key( $args['panel'] ) : '',
			'message'        => isset( $args['message'] ) ? sanitize_textarea_field( $args['message'] ) : '',
			'meta'           => ! empty( $meta ) ? wp_json_encode( $meta ) : null,
			'created_at'     => current_time( 'mysql' ),
		),
		array( '%s', '%d', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
	);

	return $inserted ? (int) $wpdb->insert_id : false;
}

function gnf_get_audit_logs( $args = array() ) {
	global $wpdb;

	$table       = $wpdb->prefix . 'gn_audit_logs';
	$table_check = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	if ( $table_check !== $table ) {
		return array();
	}

	$limit  = max( 1, min( 250, absint( $args['limit'] ?? 100 ) ) );
	$anio   = isset( $args['anio'] ) ? absint( $args['anio'] ) : 0;
	$events = isset( $args['event_key'] ) ? sanitize_key( $args['event_key'] ) : '';

	$where  = array( '1=1' );
	$params = array();

	if ( $anio ) {
		$where[]  = 'anio = %d';
		$params[] = $anio;
	}

	if ( $events ) {
		$where[]  = 'event_key = %s';
		$params[] = $events;
	}

	$sql = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY created_at DESC LIMIT %d';
	$params[] = $limit;

	$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
	if ( empty( $rows ) ) {
		return array();
	}

	foreach ( $rows as &$row ) {
		$actor         = ! empty( $row['actor_user_id'] ) ? get_userdata( (int) $row['actor_user_id'] ) : null;
		$target        = ! empty( $row['target_user_id'] ) ? get_userdata( (int) $row['target_user_id'] ) : null;
		$row['id']     = (int) $row['id'];
		$row['anio']   = (int) $row['anio'];
		$row['meta']   = ! empty( $row['meta'] ) ? json_decode( $row['meta'], true ) : array();
		$row['actorName'] = $actor ? $actor->display_name : 'Sistema';
		$row['targetName'] = $target ? $target->display_name : '';
		$row['centroName'] = ! empty( $row['centro_id'] ) ? get_the_title( (int) $row['centro_id'] ) : '';
		$row['retoTitle']  = ! empty( $row['reto_id'] ) ? get_the_title( (int) $row['reto_id'] ) : '';
	}

	return $rows;
}
