<?php
/**
 * Wizard Maestro para llenado de matrícula y retos.
 *
 * Decisión BAE: Los docentes NO llenan formularios separados por reto.
 * Llenan un solo formulario maestro tipo wizard dividido así:
 *   Paso 1+: Un paso por cada reto matriculado
 *   Paso final: Revisión y envío
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renderiza el wizard completo.
 */
function gnf_render_wizard() {
	if ( ! is_user_logged_in() ) {
		return gnf_render_auth_block(
			array(
				'title'         => 'Formulario Bandera Azul',
				'description'   => 'Accede para completar tu matrícula y retos ecológicos.',
				'show_register' => true,
				'redirect'      => esc_url_raw( home_url( add_query_arg( array() ) ) ),
			)
		);
	}

	$user = wp_get_current_user();
	if ( ! gnf_user_has_role( $user, 'docente' ) && ! current_user_can( 'view_guardianes_docente' ) && ! current_user_can( 'manage_options' ) ) {
		return '<div class="gnf-auth"><div class="gnf-auth__card"><p class="gnf-muted">No tienes permisos para ver este formulario.</p></div></div>';
	}

	$doc_estado = gnf_get_docente_estado( $user->ID );
	if ( 'pendiente' === $doc_estado ) {
		return '<div class="gnf-wizard"><div class="gnf-wizard__card"><p class="gnf-muted">Tu cuenta está pendiente de aprobación. Recibirás una notificación cuando sea aprobada.</p></div></div>';
	}

	$anio      = gnf_get_context_year( gnf_get_active_year() );
	$anio      = gnf_normalize_year( $anio );
	$centro_id = gnf_get_centro_for_docente( $user->ID );

	if ( ! $centro_id ) {
		$centro_id = (int) get_user_meta( $user->ID, 'centro_solicitado', true );
	}

	$paso_actual = isset( $_GET['paso'] ) ? absint( $_GET['paso'] ) : 1;
	$steps       = gnf_get_wizard_steps( $centro_id, $anio );
	$total_steps = count( $steps );

	// Validar paso actual.
	if ( $paso_actual < 1 || $paso_actual > $total_steps ) {
		$paso_actual = 1;
	}

	$current_step = $steps[ $paso_actual - 1 ] ?? $steps[0];

	ob_start();
	$template = GNF_PATH . 'templates/wizard.php';
	$data     = array(
		'user'         => $user,
		'centro_id'    => $centro_id,
		'anio'         => $anio,
		'paso_actual'  => $paso_actual,
		'total_steps'  => $total_steps,
		'steps'        => $steps,
		'current_step' => $current_step,
	);
	extract( $data );
	include $template;
	return ob_get_clean();
}

/**
 * Obtiene los pasos del wizard según la matrícula del centro.
 */
function gnf_get_wizard_steps( $centro_id, $anio ) {
	$steps = array();

	// Obtener retos matriculados con formulario activo para el año.
	$retos_seleccionados = gnf_get_centro_retos_seleccionados( $centro_id, $anio );
	if ( ! empty( $retos_seleccionados ) && is_array( $retos_seleccionados ) ) {
		global $wpdb;
		$table = $wpdb->prefix . 'gn_reto_entries';

		foreach ( $retos_seleccionados as $reto_id ) {
			$reto = get_post( $reto_id );
			if ( ! $reto || 'reto' !== $reto->post_type ) {
				continue;
			}

			$entry = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE centro_id = %d AND reto_id = %d AND anio = %d",
					$centro_id,
					$reto_id,
					$anio
				)
			);

			$icono = gnf_get_reto_icon_url( $reto_id, 'thumbnail', $anio );
			$color = gnf_get_reto_color( $reto_id );
			$form_id = gnf_get_reto_form_id_for_year( $reto_id, $anio );
			if ( ! $form_id ) {
				continue;
			}

			$steps[] = array(
				'id'          => 'reto_' . $reto_id,
				'title'       => $reto->post_title,
				'type'        => 'reto',
				'reto_id'     => $reto_id,
				'form_id'     => $form_id,
				'icon'        => $icono ?: '🎯',
				'color'       => $color,
				'description' => get_field( 'descripcion', $reto_id ),
				'estado'      => $entry ? $entry->estado : 'no_iniciado',
				'entry'       => $entry,
				'puntaje_max' => gnf_get_reto_max_points( $reto_id, $anio ),
				'progress'    => $entry ? ( in_array( $entry->estado, array( 'completo', 'enviado', 'aprobado' ), true ) ? 100 : 50 ) : 0,
			);
		}
	}

	// Paso final: Revisión y Envío.
	$all_complete = gnf_are_all_retos_complete( $centro_id, $anio );
	$steps[] = array(
		'id'          => 'enviar',
		'title'       => 'Enviar Participación',
		'type'        => 'enviar',
		'form_id'     => 0,
		'icon'        => '📤',
		'description' => 'Revisa y envía tu participación',
		'estado'      => $all_complete ? 'listo' : 'bloqueado',
		'can_submit'  => $all_complete,
	);

	return $steps;
}

/**
 * AJAX: Guardar progreso del wizard.
 */
function gnf_ajax_save_wizard_progress() {
	gnf_verify_ajax_nonce();

	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		wp_send_json_error( 'No autorizado' );
	}

	$step_id    = sanitize_text_field( $_POST['step_id'] ?? '' );
	$step_data  = isset( $_POST['step_data'] ) ? $_POST['step_data'] : array();
	$centro_id  = absint( $_POST['centro_id'] ?? 0 );
	$anio       = gnf_get_context_year( gnf_get_active_year() );
	$anio       = gnf_normalize_year( $anio );

	if ( ! $centro_id ) {
		$centro_id = gnf_get_centro_for_docente( $user_id );
	}

	// Guardar en user meta como progreso temporal.
	$progress_key = 'gnf_wizard_progress_' . $centro_id . '_' . $anio;
	$progress     = get_user_meta( $user_id, $progress_key, true ) ?: array();
	$progress[ $step_id ] = array(
		'data'      => $step_data,
		'saved_at'  => current_time( 'mysql' ),
	);
	update_user_meta( $user_id, $progress_key, $progress );

	wp_send_json_success( array( 'message' => 'Progreso guardado' ) );
}
add_action( 'wp_ajax_gnf_save_wizard_progress', 'gnf_ajax_save_wizard_progress' );

/**
 * AJAX: Marcar reto como completo (listo para revisión).
 */
function gnf_ajax_finalizar_reto() {
	gnf_verify_ajax_nonce();

	$user_id  = get_current_user_id();
	$entry_id = absint( $_POST['entry_id'] ?? 0 );
	$anio    = gnf_get_context_year( gnf_get_active_year() );
	$anio    = gnf_normalize_year( $anio );

	if ( ! $user_id || ! $entry_id ) {
		wp_send_json_error( 'Datos inválidos' );
	}

	global $wpdb;
	$table = $wpdb->prefix . 'gn_reto_entries';
	$entry = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $entry_id ) );

	if ( ! $entry ) {
		wp_send_json_error( 'Entrada no encontrada' );
	}

	if ( (int) $entry->anio !== $anio ) {
		wp_send_json_error( 'La entrada no corresponde al año seleccionado.' );
	}

	// Verificar permisos.
	if ( (int) $entry->user_id !== $user_id && ! gnf_user_can_access_centro( $user_id, $entry->centro_id ) ) {
		wp_send_json_error( 'Sin permisos' );
	}

	// Solo permitir transición desde en_progreso o no_iniciado.
	if ( ! in_array( $entry->estado, array( 'en_progreso', 'no_iniciado' ), true ) ) {
		wp_send_json_error( 'No se puede finalizar un reto en estado "' . $entry->estado . '".' );
	}

	// Cambiar estado a "completo".
	gnf_update_entry_estado( $entry_id, 'completo' );

	wp_send_json_success( array( 'message' => 'Reto marcado como completo' ) );
}
add_action( 'wp_ajax_gnf_finalizar_reto', 'gnf_ajax_finalizar_reto' );

/**
 * AJAX: Enviar todos los retos para revisión.
 */
function gnf_ajax_enviar_participacion() {
	gnf_verify_ajax_nonce();

	$user_id   = get_current_user_id();
	$centro_id = absint( $_POST['centro_id'] ?? 0 );
	$anio      = gnf_get_context_year( gnf_get_active_year() );
	$anio      = gnf_normalize_year( $anio );

	if ( ! $user_id || ! $centro_id ) {
		wp_send_json_error( 'Datos inválidos' );
	}

	// Verificar permisos.
	if ( ! gnf_user_can_access_centro( $user_id, $centro_id ) ) {
		wp_send_json_error( 'Sin permisos' );
	}

	// Verificar que todos los retos estén completos.
	if ( ! gnf_are_all_retos_complete( $centro_id, $anio ) ) {
		wp_send_json_error( 'No todos los retos están completos. Completa todos los retos antes de enviar.' );
	}

	// Obtener retos matriculados.
	$retos_seleccionados = gnf_get_centro_retos_seleccionados( $centro_id, $anio );
	if ( empty( $retos_seleccionados ) ) {
		wp_send_json_error( 'No hay retos matriculados' );
	}

	global $wpdb;
	$table = $wpdb->prefix . 'gn_reto_entries';

	// Cambiar estado de todos los retos "completos" a "enviados".
	foreach ( $retos_seleccionados as $reto_id ) {
		$entry = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE centro_id = %d AND reto_id = %d AND anio = %d",
				$centro_id,
				$reto_id,
				$anio
			)
		);

		if ( $entry && 'completo' === $entry->estado ) {
			gnf_update_entry_estado( $entry->id, 'enviado' );
		}
	}

	// Notificar a supervisores de la región.
	$region = get_post_meta( $centro_id, 'region', true );
	if ( empty( $region ) ) {
		$terms = wp_get_post_terms( $centro_id, 'gn_region', array( 'fields' => 'ids' ) );
		$region = $terms ? $terms[0] : '';
	}

	if ( $region ) {
		$supervisores = gnf_get_supervisores_by_region( $region );
		$centro_title = get_the_title( $centro_id );
		foreach ( $supervisores as $sup ) {
			gnf_insert_notification(
				$sup->ID,
				'participacion_enviada',
				sprintf( 'Nueva participación enviada: %s', $centro_title ),
				'centro',
				$centro_id
			);
		}
	}

	gnf_clear_supervisor_cache();

	wp_send_json_success( array( 'message' => 'Participación enviada correctamente. Los supervisores revisarán tu envío.' ) );
}
add_action( 'wp_ajax_gnf_enviar_participacion', 'gnf_ajax_enviar_participacion' );

/**
 * AJAX: Agregar reto a la matrícula.
 */
function gnf_ajax_agregar_reto_matricula() {
	gnf_verify_ajax_nonce();

	$user_id   = get_current_user_id();
	$centro_id = absint( $_POST['centro_id'] ?? 0 );
	$reto_id   = absint( $_POST['reto_id'] ?? 0 );
	$anio      = gnf_get_context_year( gnf_get_active_year() );
	$anio      = gnf_normalize_year( $anio );

	if ( ! $user_id || ! $centro_id || ! $reto_id ) {
		wp_send_json_error( 'Datos inválidos' );
	}

	if ( ! gnf_user_can_access_centro( $user_id, $centro_id ) ) {
		wp_send_json_error( 'Sin permisos' );
	}

	if ( ! function_exists( 'gnf_reto_has_form_for_year' ) || ! gnf_reto_has_form_for_year( $reto_id, $anio ) ) {
		wp_send_json_error( 'Este reto no tiene un formulario activo para el año seleccionado.' );
	}

	$retos = gnf_get_centro_retos_seleccionados( $centro_id, $anio );
	if ( ! in_array( $reto_id, $retos, true ) ) {
		$retos[] = $reto_id;
		gnf_set_centro_retos_seleccionados( $centro_id, $anio, $retos );
	}

	wp_send_json_success( array( 'message' => 'Reto agregado a la matrícula' ) );
}
add_action( 'wp_ajax_gnf_agregar_reto_matricula', 'gnf_ajax_agregar_reto_matricula' );

/**
 * AJAX: Quitar reto de la matrícula (solo si no ha sido enviado).
 */
function gnf_ajax_quitar_reto_matricula() {
	gnf_verify_ajax_nonce();

	$user_id   = get_current_user_id();
	$centro_id = absint( $_POST['centro_id'] ?? 0 );
	$reto_id   = absint( $_POST['reto_id'] ?? 0 );
	$anio      = gnf_get_context_year( gnf_get_active_year() );
	$anio      = gnf_normalize_year( $anio );

	if ( ! $user_id || ! $centro_id || ! $reto_id ) {
		wp_send_json_error( 'Datos inválidos' );
	}

	if ( ! gnf_user_can_access_centro( $user_id, $centro_id ) ) {
		wp_send_json_error( 'Sin permisos' );
	}

	// Verificar que el reto no haya sido enviado o aprobado.
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

	if ( $entry && in_array( $entry->estado, array( 'enviado', 'aprobado' ), true ) ) {
		wp_send_json_error( 'No puedes quitar un reto que ya ha sido enviado o aprobado.' );
	}

	$retos = gnf_get_centro_retos_seleccionados( $centro_id, $anio );
	$retos = array_filter( $retos, function( $id ) use ( $reto_id ) {
		return (int) $id !== (int) $reto_id;
	} );
	$retos = array_values( $retos );
	gnf_set_centro_retos_seleccionados( $centro_id, $anio, $retos );

	// Eliminar entry si existe y no ha sido enviado.
	if ( $entry && ! in_array( $entry->estado, array( 'enviado', 'aprobado' ), true ) ) {
		$wpdb->delete( $table, array( 'id' => $entry->id ), array( '%d' ) );
	}

	wp_send_json_success( array(
		'message'     => 'Reto quitado de la matrícula',
		'total_retos' => count( $retos ),
		'retos_ids'   => array_values( $retos ),
	) );
}
add_action( 'wp_ajax_gnf_quitar_reto_matricula', 'gnf_ajax_quitar_reto_matricula' );

/**
 * Obtiene los datos necesarios para renderizar el wizard.
 *
 * @param int $user_id User ID.
 * @param int $centro_id Centro ID.
 * @param int $anio Año.
 * @return array Datos del wizard.
 */
function gnf_get_wizard_data( $user_id, $centro_id, $anio ) {
	$paso_actual = isset( $_GET['paso'] ) ? absint( $_GET['paso'] ) : 1;
	$steps       = gnf_get_wizard_steps( $centro_id, $anio );
	$total_steps = count( $steps );

	// Validar paso actual.
	if ( $paso_actual < 1 || $paso_actual > $total_steps ) {
		$paso_actual = 1;
	}

	$current_step = $steps[ $paso_actual - 1 ] ?? $steps[0];

	return array(
		'user'         => get_user_by( 'id', $user_id ),
		'centro_id'    => $centro_id,
		'anio'         => $anio,
		'paso_actual'  => $paso_actual,
		'total_steps'  => $total_steps,
		'steps'        => $steps,
		'current_step' => $current_step,
	);
}

