<?php
/**
 * Orquestacion de BD para fusionar centros duplicados.
 * Usa la logica pura de merge-centros-logic.php + helpers ACF/puntajes existentes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Lanza excepcion si una escritura de $wpdb fallo (devolvio false). */
function gnf_merge_db_or_throw( $result, $context ) {
	global $wpdb;
	if ( false === $result ) {
		throw new \RuntimeException( $context . ': ' . ( $wpdb->last_error ? $wpdb->last_error : 'wpdb write failed' ) );
	}
	return $result;
}

/**
 * Detecta grupos de centros duplicados (mismo codigo MEP + region + circuito + nombre).
 *
 * @return array<int,array{key:string,canonical:int,duplicates:int[]}>
 */
function gnf_merge_find_duplicate_groups() {
	$ids = get_posts(
		array(
			'post_type'      => 'centro_educativo',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);

	$groups = array();
	foreach ( (array) $ids as $id ) {
		if ( get_post_meta( $id, 'gnf_merged_into', true ) ) {
			continue; // ya fusionado
		}
		$codigo   = get_post_meta( $id, 'codigo_mep', true );
		$circuito = get_post_meta( $id, 'circuito', true );
		$terms    = wp_get_object_terms( $id, 'gn_region', array( 'fields' => 'ids', 'orderby' => 'term_id' ) );
		$region   = ( ! is_wp_error( $terms ) && ! empty( $terms ) ) ? (int) $terms[0] : 0;
		$key      = gnf_merge_centro_key( $codigo, $region, $circuito, get_the_title( $id ) );
		if ( null === $key ) {
			continue; // codigo vacio -> no se fusiona
		}
		$groups[ $key ][] = (int) $id;
	}

	$result = array();
	foreach ( $groups as $key => $members ) {
		if ( count( $members ) < 2 ) {
			continue;
		}
		sort( $members ); // ID asc -> el primero creado es el canonical
		$result[] = array(
			'key'        => $key,
			'canonical'  => $members[0],
			'duplicates' => array_slice( $members, 1 ),
		);
	}

	return $result;
}

/**
 * Estructura de stats inicial para una fusion.
 *
 * @return array<string,mixed>
 */
function gnf_merge_new_stats( $canonical_id, $dup_id ) {
	return array(
		'canonical'             => (int) $canonical_id,
		'dup'                   => (int) $dup_id,
		'entries_moved'         => 0,
		'entries_conflicts'     => 0,
		'matriculas_moved'      => 0,
		'matriculas_conflicts'  => 0,
		'notifs_moved'          => 0,
		'audit_moved'           => 0,
		'users_moved'           => 0,
		'annual_copied'         => 0,
		'annual_merged'         => 0,
		'meta_backfilled'       => array(),
		'years_touched'         => array(),
	);
}

/**
 * Fusiona un duplicado en el canonical (solo escrituras transaccionales de BD).
 * NO manda a papelera ni registra auditoria: eso lo hace gnf_merge_post_commit
 * DESPUES del COMMIT. Devuelve stats. Si $dry_run, no escribe nada.
 */
function gnf_merge_centros_pair( $canonical_id, $dup_id, $dry_run = true ) {
	$canonical_id = (int) $canonical_id;
	$dup_id       = (int) $dup_id;
	$stats        = gnf_merge_new_stats( $canonical_id, $dup_id );

	if ( $canonical_id === $dup_id || ! $canonical_id || ! $dup_id ) {
		return $stats;
	}

	gnf_merge_entries( $canonical_id, $dup_id, $dry_run, $stats );
	gnf_merge_matriculas( $canonical_id, $dup_id, $dry_run, $stats );
	gnf_merge_simple_refs( $canonical_id, $dup_id, $dry_run, $stats );
	gnf_merge_annual_data( $canonical_id, $dup_id, $dry_run, $stats );
	gnf_merge_backfill_meta( $canonical_id, $dup_id, $dry_run, $stats );
	gnf_merge_finalize( $canonical_id, $dup_id, $dry_run, $stats );

	return $stats;
}

/** Repunta gn_reto_entries; conflictos por "mas avanzada". */
function gnf_merge_entries( $canonical_id, $dup_id, $dry_run, &$stats ) {
	global $wpdb;
	$table    = $wpdb->prefix . 'gn_reto_entries';
	$dup_rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE centro_id = %d", $dup_id ), ARRAY_A ); // phpcs:ignore

	foreach ( (array) $dup_rows as $d ) {
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE centro_id = %d AND reto_id = %d AND anio = %d", $canonical_id, $d['reto_id'], $d['anio'] ), ARRAY_A ); // phpcs:ignore
		$stats['years_touched'][ (int) $d['anio'] ] = true;

		if ( ! $existing ) {
			$stats['entries_moved']++;
			if ( ! $dry_run ) {
				gnf_merge_db_or_throw( $wpdb->update( $table, array( 'centro_id' => $canonical_id ), array( 'id' => $d['id'] ), array( '%d' ), array( '%d' ) ), 'repoint entry ' . $d['id'] );
			}
			continue;
		}

		$stats['entries_conflicts']++;
		$a = array(
			'estado'           => $d['estado'],
			'puntaje'          => $d['puntaje'],
			'evidencias_count' => gnf_merge_count_evidencias( $d['evidencias'] ),
			'updated_at'       => $d['updated_at'],
		);
		$b = array(
			'estado'           => $existing['estado'],
			'puntaje'          => $existing['puntaje'],
			'evidencias_count' => gnf_merge_count_evidencias( $existing['evidencias'] ),
			'updated_at'       => $existing['updated_at'],
		);
		$dup_wins = gnf_merge_entry_a_wins( $a, $b );

		if ( ! $dry_run ) {
			if ( $dup_wins ) {
				gnf_merge_db_or_throw( $wpdb->delete( $table, array( 'id' => $existing['id'] ), array( '%d' ) ), 'delete canonical entry ' . $existing['id'] );
				gnf_merge_db_or_throw( $wpdb->update( $table, array( 'centro_id' => $canonical_id ), array( 'id' => $d['id'] ), array( '%d' ), array( '%d' ) ), 'repoint entry ' . $d['id'] );
			} else {
				gnf_merge_db_or_throw( $wpdb->delete( $table, array( 'id' => $d['id'] ), array( '%d' ) ), 'delete dup entry ' . $d['id'] );
			}
		}
	}
}

/** Repunta gn_matriculas; conflictos por "mas avanzada" + union de retos_seleccionados. */
function gnf_merge_matriculas( $canonical_id, $dup_id, $dry_run, &$stats ) {
	global $wpdb;
	$table    = $wpdb->prefix . 'gn_matriculas';
	$dup_rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE centro_id = %d", $dup_id ), ARRAY_A ); // phpcs:ignore

	foreach ( (array) $dup_rows as $d ) {
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE centro_id = %d AND anio = %d", $canonical_id, $d['anio'] ), ARRAY_A ); // phpcs:ignore
		$stats['years_touched'][ (int) $d['anio'] ] = true;

		if ( ! $existing ) {
			$stats['matriculas_moved']++;
			if ( ! $dry_run ) {
				gnf_merge_db_or_throw( $wpdb->update( $table, array( 'centro_id' => $canonical_id ), array( 'id' => $d['id'] ), array( '%d' ), array( '%d' ) ), 'repoint matricula ' . $d['id'] );
			}
			continue;
		}

		$stats['matriculas_conflicts']++;
		$dup_wins  = gnf_merge_matricula_a_wins(
			array( 'estado' => $d['estado'], 'updated_at' => $d['updated_at'] ),
			array( 'estado' => $existing['estado'], 'updated_at' => $existing['updated_at'] )
		);
		$union     = gnf_merge_union_reto_ids(
			json_decode( (string) $d['retos_seleccionados'], true ),
			json_decode( (string) $existing['retos_seleccionados'], true )
		);
		$estrellas = max( (int) $d['meta_estrellas'], (int) $existing['meta_estrellas'] );

		if ( ! $dry_run ) {
			if ( $dup_wins ) {
				gnf_merge_db_or_throw( $wpdb->delete( $table, array( 'id' => $existing['id'] ), array( '%d' ) ), 'delete canonical matricula ' . $existing['id'] );
				gnf_merge_db_or_throw( $wpdb->update(
					$table,
					array( 'centro_id' => $canonical_id, 'retos_seleccionados' => wp_json_encode( $union ), 'meta_estrellas' => $estrellas ),
					array( 'id' => $d['id'] ),
					array( '%d', '%s', '%d' ),
					array( '%d' )
				), 'repoint matricula ' . $d['id'] );
			} else {
				gnf_merge_db_or_throw( $wpdb->update(
					$table,
					array( 'retos_seleccionados' => wp_json_encode( $union ), 'meta_estrellas' => $estrellas ),
					array( 'id' => $existing['id'] ),
					array( '%s', '%d' ),
					array( '%d' )
				), 'update canonical matricula ' . $existing['id'] );
				gnf_merge_db_or_throw( $wpdb->delete( $table, array( 'id' => $d['id'] ), array( '%d' ) ), 'delete dup matricula ' . $d['id'] );
			}
		}
	}
}

/** Repunta notificaciones, audit logs y user meta de docentes. */
function gnf_merge_simple_refs( $canonical_id, $dup_id, $dry_run, &$stats ) {
	global $wpdb;
	$notif = $wpdb->prefix . 'gn_notificaciones';
	$audit = $wpdb->prefix . 'gn_audit_logs';

	$stats['notifs_moved'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$notif} WHERE relacion_tipo = 'centro' AND relacion_id = %d", $dup_id ) ); // phpcs:ignore
	$stats['audit_moved']  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$audit} WHERE centro_id = %d", $dup_id ) ); // phpcs:ignore
	$stats['users_moved']  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} WHERE meta_key IN ('centro_educativo_id','centro_solicitado','gnf_centro_id') AND meta_value = %s", (string) $dup_id ) ); // phpcs:ignore

	if ( $dry_run ) {
		return;
	}

	gnf_merge_db_or_throw( $wpdb->query( $wpdb->prepare( "UPDATE {$notif} SET relacion_id = %d WHERE relacion_tipo = 'centro' AND relacion_id = %d", $canonical_id, $dup_id ) ), 'repoint notifs' ); // phpcs:ignore
	gnf_merge_db_or_throw( $wpdb->query( $wpdb->prepare( "UPDATE {$audit} SET centro_id = %d WHERE centro_id = %d", $canonical_id, $dup_id ) ), 'repoint audit' ); // phpcs:ignore
	foreach ( array( 'centro_educativo_id', 'centro_solicitado', 'gnf_centro_id' ) as $mk ) {
		gnf_merge_db_or_throw( $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->usermeta} SET meta_value = %s WHERE meta_key = %s AND meta_value = %s", (string) $canonical_id, $mk, (string) $dup_id ) ), 'repoint usermeta ' . $mk ); // phpcs:ignore
	}
}

/** Mergea datos anuales ACF: copia años faltantes, une retos_seleccionados y toma el max de estrellas en años compartidos. */
function gnf_merge_annual_data( $canonical_id, $dup_id, $dry_run, &$stats ) {
	$dup_rows = function_exists( 'gnf_get_centro_anual_rows' ) ? gnf_get_centro_anual_rows( $dup_id ) : array();

	foreach ( (array) $dup_rows as $row ) {
		$anio = absint( isset( $row['anio'] ) ? $row['anio'] : 0 );
		if ( ! $anio ) {
			continue;
		}
		$stats['years_touched'][ $anio ] = true;
		$exists  = false;
		$can_row = gnf_get_centro_anual_row( $canonical_id, $anio, $exists );

		if ( ! $exists ) {
			$stats['annual_copied']++;
			if ( ! $dry_run ) {
				gnf_set_centro_anual_data( $canonical_id, $anio, $row );
			}
			continue;
		}

		$stats['annual_merged']++;
		if ( ! $dry_run ) {
			$union = gnf_merge_union_reto_ids(
				isset( $can_row['retos_seleccionados'] ) ? $can_row['retos_seleccionados'] : array(),
				isset( $row['retos_seleccionados'] ) ? $row['retos_seleccionados'] : array()
			);
			gnf_set_centro_anual_field( $canonical_id, 'retos_seleccionados', $union, $anio );

			$max_estrellas = max(
				(int) ( isset( $can_row['meta_estrellas'] ) ? $can_row['meta_estrellas'] : 0 ),
				(int) ( isset( $row['meta_estrellas'] ) ? $row['meta_estrellas'] : 0 )
			);
			gnf_set_centro_anual_field( $canonical_id, 'meta_estrellas', $max_estrellas, $anio );
		}
	}
}

/** Backfill de meta institucional solo cuando el canonical lo tiene vacio. */
function gnf_merge_backfill_meta( $canonical_id, $dup_id, $dry_run, &$stats ) {
	$keys = array( 'correo_institucional', 'telefono', 'telefono2', 'direccion_planificacion' );
	foreach ( $keys as $k ) {
		if ( '' !== (string) get_post_meta( $canonical_id, $k, true ) ) {
			continue;
		}
		$dup_val = get_post_meta( $dup_id, $k, true );
		if ( '' === (string) $dup_val ) {
			continue;
		}
		$stats['meta_backfilled'][] = $k;
		if ( ! $dry_run ) {
			update_post_meta( $canonical_id, $k, $dup_val );
		}
	}
}

/** Recalcula puntajes del canonical y limpia caches (escrituras dentro de la transaccion). */
function gnf_merge_finalize( $canonical_id, $dup_id, $dry_run, &$stats ) {
	if ( $dry_run ) {
		return;
	}

	foreach ( array_keys( $stats['years_touched'] ) as $anio ) {
		if ( function_exists( 'gnf_recalcular_puntaje_centro' ) ) {
			gnf_recalcular_puntaje_centro( $canonical_id, $anio );
		}
		delete_transient( 'gnf_total_' . $canonical_id . '_' . $anio );
		delete_transient( 'gnf_aprobados_' . $canonical_id . '_' . $anio );
		delete_transient( 'gnf_total_' . $dup_id . '_' . $anio );
		delete_transient( 'gnf_aprobados_' . $dup_id . '_' . $anio );
	}
}

/**
 * Pasos irreversibles que deben correr DESPUES del COMMIT: marca el duplicado,
 * lo manda a papelera y registra el evento de auditoria. Solo en ejecucion real.
 */
function gnf_merge_post_commit( $canonical_id, $dup_id, $stats ) {
	update_post_meta( $dup_id, 'gnf_merged_into', $canonical_id );
	update_post_meta( $dup_id, 'gnf_merged_at', current_time( 'mysql' ) );
	wp_trash_post( $dup_id );

	if ( function_exists( 'gnf_log_audit_event' ) ) {
		gnf_log_audit_event(
			'centro_merged',
			array(
				'centro_id' => $canonical_id,
				'message'   => sprintf( 'Centro #%d fusionado en #%d', $dup_id, $canonical_id ),
				'meta'      => $stats,
			)
		);
	}
}
