<?php
/**
 * Creación de tablas custom.
 *
 * Estados de reto (campo `estado` en gn_reto_entries):
 * - no_iniciado: El docente aún no ha comenzado a llenar el formulario
 * - en_progreso: El docente ha guardado progreso parcial (Save & Resume)
 * - completo: El docente completó el checklist pero NO ha enviado a revisión
 * - enviado: El docente envió el reto para revisión del supervisor
 * - aprobado: El supervisor aprobó el reto (se asignan puntos automáticamente)
 * - correccion: El supervisor solicitó correcciones
 *
 * Flujo: no_iniciado → en_progreso → completo → enviado → aprobado
 *                                        ↓
 *                                   correccion → enviado
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function gnf_create_tables() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset_collate = $wpdb->get_charset_collate();

	$table_entries = $wpdb->prefix . 'gn_reto_entries';
	$sql_entries   = "CREATE TABLE {$table_entries} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		wpforms_entry_id bigint(20) unsigned NOT NULL,
		user_id bigint(20) unsigned NOT NULL,
		centro_id bigint(20) unsigned NOT NULL,
		reto_id bigint(20) unsigned NOT NULL,
		anio int(11) NOT NULL,
		data longtext NULL,
		evidencias longtext NULL,
		autoevaluacion longtext NULL,
		puntaje int(11) DEFAULT 0,
		estado varchar(20) DEFAULT 'no_iniciado',
		supervisor_notes longtext NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY user_idx (user_id),
		KEY centro_idx (centro_id),
		KEY reto_idx (reto_id),
		KEY estado_idx (estado),
		KEY anio_idx (anio),
		UNIQUE KEY centro_reto_anio (centro_id, reto_id, anio)
	) {$charset_collate};";

	$table_notif = $wpdb->prefix . 'gn_notificaciones';
	$sql_notif   = "CREATE TABLE {$table_notif} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned NOT NULL,
		tipo varchar(100) NOT NULL,
		mensaje text NOT NULL,
		relacion_tipo varchar(100) NULL,
		relacion_id bigint(20) unsigned NULL,
		leido tinyint(1) DEFAULT 0,
		created_at datetime NOT NULL,
		PRIMARY KEY (id),
		KEY user_idx (user_id),
		KEY leido_idx (leido)
	) {$charset_collate};";

	$table_matriculas = $wpdb->prefix . 'gn_matriculas';
	$sql_matriculas   = "CREATE TABLE {$table_matriculas} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		centro_id bigint(20) unsigned NOT NULL,
		user_id bigint(20) unsigned NOT NULL,
		anio int(11) NOT NULL,
		meta_estrellas int(11) DEFAULT 1,
		retos_seleccionados longtext NULL,
		data longtext NULL,
		estado varchar(20) DEFAULT 'pendiente',
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY (id),
		KEY centro_idx (centro_id),
		KEY user_idx (user_id),
		KEY anio_idx (anio),
		UNIQUE KEY centro_anio_unique (centro_id, anio)
	) {$charset_collate};";

	dbDelta( $sql_entries );
	dbDelta( $sql_notif );
	dbDelta( $sql_matriculas );
}
