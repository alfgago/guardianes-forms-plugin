<?php
/**
 * Identidad estable de evidencias para notificaciones.
 *
 * @package Guardianes_Formularios
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function gnf_user_receives_only_rejections( $user_id ) {
	$user = get_userdata( $user_id );
	return $user && in_array( 'docente', (array) $user->roles, true )
		&& ! array_intersect( array( 'administrator', 'supervisor', 'comite_bae', 'dre' ), (array) $user->roles );
}

function gnf_get_docente_action_notification_types() {
	return array( 'evidencia_rechazada', 'evidencia_en_pausa', 'invalid_photo_date', 'correccion' );
}

function gnf_docente_notification_matches_state( $type, $state ) {
	return in_array( $type, gnf_get_docente_action_notification_types(), true )
		&& ( 'evidencia_en_pausa' === $type ? 'en_pausa' === $state : 'rechazada' === $state );
}

function gnf_docente_notification_is_actionable( $type, $evidences ) {
	foreach ( (array) $evidences as $evidence ) {
		if ( empty( $evidence['replaced'] ) && gnf_docente_notification_matches_state( $type, $evidence['estado'] ?? '' ) ) {
			return true;
		}
	}
	return false;
}

/** Recover notifications discarded by the former rejection-only policy, in batches. */
function gnf_backfill_docente_pause_notifications( $user_id ) {
	if ( get_user_meta( $user_id, '_gnf_pause_notifications_backfilled_v1', true ) ) {
		return;
	}
	global $wpdb;
	$cursor = absint( get_user_meta( $user_id, '_gnf_pause_notifications_cursor_v1', true ) );
	$entries = $wpdb->get_results( $wpdb->prepare(
		"SELECT id, reto_id, evidencias FROM {$wpdb->prefix}gn_reto_entries WHERE user_id = %d AND id > %d ORDER BY id ASC LIMIT 200",
		$user_id, $cursor
	) );
	if ( ! is_array( $entries ) ) {
		return;
	}
	$last_processed = $cursor;
	foreach ( $entries as $entry ) {
		$evidences = json_decode( (string) $entry->evidencias, true );
		foreach ( (array) $evidences as $evidence ) {
			if ( ! is_array( $evidence ) || ! empty( $evidence['replaced'] ) || 'en_pausa' !== ( $evidence['estado'] ?? '' ) ) {
				continue;
			}
			$relation = gnf_get_evidence_notification_relation_type( $evidence );
			$exists = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}gn_notificaciones WHERE user_id = %d AND tipo = %s AND relacion_tipo = %s AND relacion_id = %d LIMIT 1",
				$user_id, 'evidencia_en_pausa', $relation, $entry->id
			) );
			if ( $exists ) {
				continue;
			}
			$message = sprintf( 'Tu evidencia "%s" del reto "%s" se encuentra en pausa.', $evidence['nombre'] ?? $evidence['filename'] ?? 'archivo', get_the_title( $entry->reto_id ) );
			$reason = gnf_get_evidence_review_reason_label( $evidence['review_reason'] ?? '' );
			if ( $reason ) {
				$message .= ' Motivo: ' . $reason;
			}
			if ( ! empty( $evidence['supervisor_comment'] ) ) {
				$message .= ' Comentario: ' . $evidence['supervisor_comment'];
			}
			$inserted = $wpdb->insert( $wpdb->prefix . 'gn_notificaciones', array(
				'user_id' => $user_id, 'tipo' => 'evidencia_en_pausa', 'mensaje' => $message,
				'relacion_tipo' => $relation, 'relacion_id' => (int) $entry->id,
				'leido' => 0, 'created_at' => ! empty( $evidence['reviewed_at'] ) ? $evidence['reviewed_at'] : current_time( 'mysql' ),
			), array( '%d', '%s', '%s', '%s', '%d', '%d', '%s' ) );
			if ( false === $inserted ) {
				if ( $last_processed > $cursor ) {
					update_user_meta( $user_id, '_gnf_pause_notifications_cursor_v1', $last_processed );
				}
				return;
			}
		}
		$last_processed = (int) $entry->id;
	}
	if ( $last_processed > $cursor ) {
		update_user_meta( $user_id, '_gnf_pause_notifications_cursor_v1', $last_processed );
	}
	if ( count( $entries ) < 200 ) {
		update_user_meta( $user_id, '_gnf_pause_notifications_backfilled_v1', 1 );
	}
}

/**
 * Crea una clave estable a partir de los datos persistentes del archivo.
 *
 * @param array<string,mixed> $evidence Evidencia.
 * @return string
 */
function gnf_get_evidence_notification_key( $evidence ) {
	$parts = array(
		(string) max( 0, (int) ( $evidence['field_id'] ?? 0 ) ),
		(string) ( $evidence['ruta'] ?? $evidence['url'] ?? $evidence['path_local'] ?? '' ),
		(string) ( $evidence['nombre'] ?? $evidence['filename'] ?? '' ),
		(string) ( $evidence['tipo'] ?? $evidence['type'] ?? '' ),
	);

	return hash( 'sha256', implode( "\n", $parts ) );
}

/**
 * Codifica la identidad dentro de relacion_tipo sin cambiar el esquema SQL.
 *
 * @param array<string,mixed> $evidence Evidencia.
 * @return string
 */
function gnf_get_evidence_notification_relation_type( $evidence ) {
	return 'reto_entry_evidence:' . gnf_get_evidence_notification_key( $evidence );
}

/**
 * Extrae una identidad válida desde relacion_tipo.
 *
 * @param string $relation_type Relación.
 * @return string
 */
function gnf_get_notification_evidence_scope_key( $relation_type ) {
	$prefix        = 'reto_entry_evidence:';
	$relation_type = (string) $relation_type;
	if ( 0 !== strpos( $relation_type, $prefix ) ) {
		return '';
	}
	$key = substr( $relation_type, strlen( $prefix ) );
	return preg_match( '/^[a-f0-9]{64}$/', $key ) ? $key : '';
}

/**
 * Decide si una evidencia actual pertenece a una notificación concreta.
 *
 * @param string              $notification_type Tipo de notificación.
 * @param string              $relation_type     Relación persistida.
 * @param string              $message           Mensaje histórico.
 * @param array<string,mixed> $evidence          Evidencia actual.
 * @return bool
 */
function gnf_notification_should_include_evidence( $notification_type, $relation_type, $message, $evidence ) {
	$scope_key = gnf_get_notification_evidence_scope_key( $relation_type );
	if ( '' !== $scope_key ) {
		return hash_equals( $scope_key, gnf_get_evidence_notification_key( $evidence ) );
	}

	$file_name        = trim( (string) ( $evidence['nombre'] ?? $evidence['filename'] ?? '' ) );
	$comment          = trim( (string) ( $evidence['supervisor_comment'] ?? '' ) );
	$file_mentioned   = '' !== $file_name && false !== strpos( (string) $message, $file_name );
	$comment_mentioned = '' !== $comment && false !== strpos( (string) $message, $comment );

	if ( in_array( (string) $notification_type, array( 'evidencia_aprobada', 'evidencia_rechazada', 'evidencia_en_pausa' ), true ) ) {
		return $file_mentioned;
	}
	if ( $file_mentioned || $comment_mentioned ) {
		return true;
	}

	$status         = (string) ( $evidence['estado'] ?? '' );
	$has_date_issue = function_exists( 'gnf_evidence_has_verifiable_date_issue' ) && gnf_evidence_has_verifiable_date_issue( $evidence );
	return in_array( (string) $notification_type, array( 'invalid_photo_date', 'correccion' ), true )
		&& ( 'rechazada' === $status || $has_date_issue );
}

/**
 * Filtra evidencias conservando sus índices y evitando coincidencias ambiguas.
 *
 * @param string                   $notification_type Tipo de notificación.
 * @param string                   $relation_type     Relación persistida.
 * @param string                   $message           Mensaje histórico.
 * @param array<int,array<string,mixed>> $evidences Evidencias actuales.
 * @return array<int,array<string,mixed>>
 */
function gnf_filter_notification_evidences( $notification_type, $relation_type, $message, $evidences ) {
	$matches = array();
	foreach ( (array) $evidences as $index => $evidence ) {
		if ( ! is_array( $evidence ) || ! empty( $evidence['replaced'] ) ) {
			continue;
		}
		if ( gnf_notification_should_include_evidence( $notification_type, $relation_type, $message, $evidence ) ) {
			$matches[ $index ] = $evidence;
		}
	}

	$requires_unique_match = '' !== gnf_get_notification_evidence_scope_key( $relation_type )
		|| in_array( (string) $notification_type, array( 'evidencia_aprobada', 'evidencia_rechazada', 'evidencia_en_pausa' ), true );
	if ( $requires_unique_match && 1 !== count( $matches ) ) {
		return array();
	}

	return $matches;
}
