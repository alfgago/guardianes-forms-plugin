<?php
/**
 * Integración central con WPForms.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normaliza fields de WPForms a key => valor usando slug del label.
 */
function gnf_normalize_fields( $fields ) {
	$output = array();
	foreach ( $fields as $field_id => $field ) {
		$key             = ! empty( $field['name'] ) ? sanitize_title( $field['name'] ) : $field_id;
		if ( is_array( $field['value'] ) ) {
			$output[ $key ] = array_map(
				function ( $val ) {
					return is_array( $val ) ? array_map( 'sanitize_text_field', $val ) : sanitize_text_field( (string) $val );
				},
				$field['value']
			);
		} else {
			$output[ $key ] = sanitize_text_field( $field['value'] );
		}
		$output['_raw'][ $key ] = $field;
	}
	return $output;
}

/**
 * Define tipos de archivo permitidos según reto.
 */
function gnf_wpforms_allowed_types( $allowed, $field_id, $form_data ) {
	$form_id  = absint( $form_data['id'] ?? 0 );
	$reto     = gnf_get_reto_by_form_id( $form_id );
	if ( ! $reto ) {
		return $allowed;
	}

	$tipos = gnf_get_reto_allowed_tipos( $reto->ID );
	if ( empty( $tipos ) ) {
		return $allowed;
	}

	$exts = array();
	foreach ( $tipos as $tipo ) {
		$exts = array_merge( $exts, gnf_allowed_extensions_for_tipo( $tipo ) );
	}

	$exts = array_unique( $exts );
	if ( empty( $exts ) ) {
		return $allowed;
	}

	return implode( ',', $exts );
}
add_filter( 'wpforms_field_file_upload_allowed_file_types', 'gnf_wpforms_allowed_types', 10, 3 );

/**
 * Valida upload con mensaje amigable si no cumple tipos permitidos.
 */
function gnf_wpforms_validate_upload_types( $field_id, $field_submit, $form_data, $fields ) {
	$form_id = absint( $form_data['id'] ?? 0 );
	$reto    = gnf_get_reto_by_form_id( $form_id );
	if ( ! $reto ) {
		return;
	}
	$tipos = gnf_get_reto_allowed_tipos( $reto->ID );
	if ( empty( $tipos ) ) {
		return;
	}
	$allowed_exts = array();
	foreach ( $tipos as $tipo ) {
		$allowed_exts = array_merge( $allowed_exts, gnf_allowed_extensions_for_tipo( $tipo ) );
	}
	$allowed_exts = array_unique( $allowed_exts );
	if ( empty( $allowed_exts ) ) {
		return;
	}

	$files = is_array( $field_submit ) ? $field_submit : array( $field_submit );
	foreach ( $files as $file ) {
		if ( ! $file ) {
			continue;
		}
		$ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, $allowed_exts, true ) ) {
			wpforms()->process->errors[ $form_id ][ $field_id ] = sprintf(
				'Tipo de archivo no permitido para este reto. Extensiones validas: %s',
				implode( ', ', $allowed_exts )
			);
			break;
		}
	}

	return $entry_row;
}
add_action( 'wpforms_process_validate_file-upload', 'gnf_wpforms_validate_upload_types', 10, 4 );

/**
 * Maneja envío completado de WPForms para retos.
 */
function gnf_wpforms_process_complete( $fields, $entry, $form_data, $entry_id ) {
	$form_id      = absint( $form_data['id'] ?? 0 );
	$normalized   = gnf_normalize_fields( $fields );

	$reto_post = gnf_get_reto_by_form_id( $form_id );
	if ( $reto_post ) {
		gnf_handle_reto_submission( $reto_post, $normalized, $entry_id, $form_data, $fields );
	}
}
add_action( 'wpforms_process_complete', 'gnf_wpforms_process_complete', 20, 4 );

/**
 * Maneja guardados parciales de Save & Resume -> estado en_progreso.
 * Intentamos cubrir hooks del add-on; si no existen, no rompe.
 */
function gnf_wpforms_handle_partial( $partial_entry_id, $form_data = array(), $fields = array(), $email = '' ) {
	// Acepta 3 o 4 argumentos por compatibilidad con distintas versiones del add-on.
	if ( is_array( $partial_entry_id ) && empty( $form_data ) ) {
		// Cuando el primer argumento es $form_data.
		$form_data         = $partial_entry_id;
		$partial_entry_id  = $email ? $email : 0;
		$fields            = is_array( $fields ) ? $fields : array();
	}

	$form_id   = absint( $form_data['id'] ?? 0 );
	$reto_post = gnf_get_reto_by_form_id( $form_id );
	if ( ! $reto_post ) {
		return;
	}

	$normalized = gnf_normalize_fields( $fields );
	gnf_store_reto_entry( $reto_post, $normalized, $partial_entry_id, $form_data, $fields, 'en_progreso' );
}
add_action( 'wpforms_save_resume_after_save', 'gnf_wpforms_handle_partial', 10, 4 );
add_action( 'wpforms_save_resume_email_sent', 'gnf_wpforms_handle_partial', 10, 4 );
add_action( 'wpforms_save_resume_partial_entry_created', 'gnf_wpforms_handle_partial', 10, 4 );

/**
 * Lógica de matrícula inicial.
 *
 * Maneja tanto centros nuevos como existentes y registra los retos seleccionados.
 */
function gnf_handle_matricula_submission( $normalized_fields, $entry_id, $form_data ) {
	global $wpdb;

	$user_id = get_current_user_id();
	$anio    = isset( $normalized_fields['anio'] ) ? absint( $normalized_fields['anio'] ) : gnf_get_context_year( gnf_get_active_year() );
	$anio    = gnf_normalize_year( $anio );

	$nivel_educativo = gnf_normalize_centro_choice( 'nivel_educativo', (string) ( $normalized_fields['centro-nivel-educativo'] ?? $normalized_fields['centro-modalidad'] ?? '' ) );
	$dependencia     = gnf_normalize_centro_choice( 'dependencia', (string) ( $normalized_fields['centro-dependencia'] ?? '' ) );
	$jornada         = gnf_normalize_centro_choice( 'jornada', (string) ( $normalized_fields['centro-jornada'] ?? $normalized_fields['centro-horario'] ?? '' ) );
	$tipologia       = gnf_normalize_centro_choice( 'tipologia', (string) ( $normalized_fields['centro-tipologia'] ?? '' ) );
	$correo_inst     = sanitize_email( (string) ( $normalized_fields['centro-correo-institucional'] ?? '' ) );
	$ultimo_galardon = max( 1, min( 5, absint( $normalized_fields['centro-ultimo-galardon-estrellas'] ?? 1 ) ) );
	$ultimo_anio_participacion = sanitize_text_field( (string) ( $normalized_fields['centro-ultimo-anio-participacion'] ?? '' ) );
	$ultimo_anio_otro = absint( $normalized_fields['centro-ultimo-anio-participacion-otro'] ?? 0 );
	$coordinador_cargo = gnf_normalize_centro_choice( 'coordinador_cargo', (string) ( $normalized_fields['coordinador-cargo'] ?? '' ) );
	$coordinador_nombre = sanitize_text_field( (string) ( $normalized_fields['coordinador-nombre'] ?? '' ) );
	$coordinador_celular = sanitize_text_field( (string) ( $normalized_fields['coordinador-celular'] ?? '' ) );
	if ( 'director' === $coordinador_cargo && empty( $coordinador_nombre ) ) {
		$coordinador_nombre = sanitize_text_field( (string) ( $normalized_fields['docente-nombre'] ?? '' ) );
	}

	$centro_existe = ( $normalized_fields['centro-existe'] ?? '' ) === 'Sí';
	$centro_id     = 0;

	if ( $centro_existe ) {
		$centro_id = absint( $normalized_fields['centro-id-existente'] ?? 0 );
	} else {
		$nombre    = sanitize_text_field( (string) ( $normalized_fields['centro-nombre'] ?? 'Centro sin nombre' ) );
		$codigo    = sanitize_text_field( (string) ( $normalized_fields['centro-codigo-mep'] ?? '' ) );
		$region    = absint( $normalized_fields['centro-region'] ?? 0 );
		$direccion = sanitize_textarea_field( (string) ( $normalized_fields['centro-direccion'] ?? '' ) );
		$telefono  = sanitize_text_field( (string) ( $normalized_fields['centro-telefono'] ?? '' ) );
		$circuito  = sanitize_text_field( (string) ( $normalized_fields['centro-circuito'] ?? '' ) );
		$canton    = sanitize_text_field( (string) ( $normalized_fields['centro-canton'] ?? '' ) );
		$provincia = sanitize_text_field( (string) ( $normalized_fields['centro-provincia'] ?? '' ) );

		if ( $codigo ) {
			$existing = gnf_find_centro_by_codigo( $codigo );
			if ( $existing ) {
				$centro_id = $existing;
			}
		}

		if ( ! $centro_id ) {
			$centro_id = wp_insert_post(
				array(
					'post_type'   => 'centro_educativo',
					'post_status' => 'pending',
					'post_title'  => $nombre,
				)
			);
			if ( is_wp_error( $centro_id ) ) {
				return;
			}
		}

		if ( $nombre ) {
			wp_update_post(
				array(
					'ID'         => $centro_id,
					'post_title' => $nombre,
				)
			);
		}

		if ( $codigo ) {
			update_post_meta( $centro_id, 'codigo_mep', $codigo );
		}
		update_post_meta( $centro_id, 'direccion', $direccion );
		update_post_meta( $centro_id, 'telefono', $telefono );
		update_post_meta( $centro_id, 'circuito', $circuito );
		update_post_meta( $centro_id, 'canton', $canton );
		update_post_meta( $centro_id, 'provincia', $provincia );
		update_post_meta( $centro_id, 'estado_centro', 'pendiente_de_revision_admin' );
		if ( $region ) {
			update_post_meta( $centro_id, 'region', $region );
			wp_set_object_terms( $centro_id, array( $region ), 'gn_region', false );
		}
	}

	if ( ! $centro_id ) {
		return;
	}

	if ( ! empty( $normalized_fields['centro-nombre'] ) ) {
		wp_update_post(
			array(
				'ID'         => $centro_id,
				'post_title' => sanitize_text_field( (string) $normalized_fields['centro-nombre'] ),
			)
		);
	}
	update_post_meta( $centro_id, 'codigo_mep', sanitize_text_field( (string) ( $normalized_fields['centro-codigo-mep'] ?? '' ) ) );
	update_post_meta( $centro_id, 'correo_institucional', $correo_inst );
	update_post_meta( $centro_id, 'direccion', sanitize_textarea_field( (string) ( $normalized_fields['centro-direccion'] ?? '' ) ) );
	update_post_meta( $centro_id, 'telefono', sanitize_text_field( (string) ( $normalized_fields['centro-telefono'] ?? '' ) ) );
	update_post_meta( $centro_id, 'nivel_educativo', $nivel_educativo );
	update_post_meta( $centro_id, 'dependencia', $dependencia );
	update_post_meta( $centro_id, 'jornada', $jornada );
	update_post_meta( $centro_id, 'tipologia', $tipologia );
	update_post_meta( $centro_id, 'modalidad', $nivel_educativo );
	update_post_meta( $centro_id, 'horario', $jornada );
	update_post_meta( $centro_id, 'circuito', sanitize_text_field( (string) ( $normalized_fields['centro-circuito'] ?? '' ) ) );
	update_post_meta( $centro_id, 'canton', sanitize_text_field( (string) ( $normalized_fields['centro-canton'] ?? '' ) ) );
	update_post_meta( $centro_id, 'provincia', sanitize_text_field( (string) ( $normalized_fields['centro-provincia'] ?? '' ) ) );
	update_post_meta( $centro_id, 'codigo_presupuestario', sanitize_text_field( (string) ( $normalized_fields['centro-codigo-presupuestario'] ?? '' ) ) );
	update_post_meta( $centro_id, 'total_estudiantes', absint( $normalized_fields['centro-total-estudiantes'] ?? 0 ) );
	update_post_meta( $centro_id, 'estudiantes_hombres', absint( $normalized_fields['centro-estudiantes-hombres'] ?? 0 ) );
	update_post_meta( $centro_id, 'estudiantes_mujeres', absint( $normalized_fields['centro-estudiantes-mujeres'] ?? 0 ) );
	update_post_meta( $centro_id, 'estudiantes_migrantes', absint( $normalized_fields['centro-estudiantes-migrantes'] ?? 0 ) );
	update_post_meta( $centro_id, 'ultimo_galardon_estrellas', $ultimo_galardon );
	update_post_meta( $centro_id, 'ultimo_anio_participacion', $ultimo_anio_participacion );
	update_post_meta( $centro_id, 'ultimo_anio_participacion_otro', $ultimo_anio_otro );
	update_post_meta( $centro_id, 'coordinador_pbae_cargo', $coordinador_cargo );
	update_post_meta( $centro_id, 'coordinador_pbae_nombre', $coordinador_nombre );
	update_post_meta( $centro_id, 'coordinador_pbae_celular', $coordinador_celular );
	$region = absint( $normalized_fields['centro-region'] ?? 0 );
	if ( $region ) {
		update_post_meta( $centro_id, 'region', $region );
		wp_set_object_terms( $centro_id, array( $region ), 'gn_region', false );
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		update_post_meta( $centro_id, 'estado_centro', 'pendiente_de_revision_admin' );
	}

	if ( $user_id ) {
		$docentes = (array) get_field( 'docentes_asociados', $centro_id );
		if ( ! in_array( $user_id, $docentes, true ) ) {
			$docentes[] = $user_id;
			if ( function_exists( 'update_field' ) ) {
				update_field( 'docentes_asociados', $docentes, $centro_id );
			}
		}
		update_user_meta( $user_id, 'centro_educativo_id', $centro_id );
		$current_status = get_user_meta( $user_id, 'gnf_docente_status', true );
		if ( empty( $current_status ) || 'pendiente' === $current_status ) {
			update_user_meta( $user_id, 'gnf_docente_status', 'pendiente' );
		}
		update_user_meta( $user_id, 'docente_cargo', $normalized_fields['docente-cargo'] ?? '' );
		update_user_meta( $user_id, 'docente_telefono', $normalized_fields['docente-telefono'] ?? '' );
	}

	$meta_estrellas_raw = $normalized_fields['bae-meta-estrellas'] ?? '1 estrella';
	$meta_estrellas     = (int) preg_replace( '/[^0-9]/', '', $meta_estrellas_raw );
	$meta_estrellas     = max( 1, min( 5, $meta_estrellas ) );

	$retos_raw = $normalized_fields['bae-retos-seleccionados'] ?? array();
	if ( is_string( $retos_raw ) ) {
		$retos_raw = array_map( 'trim', explode( ',', $retos_raw ) );
	}

	$retos_seleccionados = array();
	foreach ( (array) $retos_raw as $reto_value ) {
		if ( is_numeric( $reto_value ) ) {
			$retos_seleccionados[] = (int) $reto_value;
		} else {
			$found_retos = get_posts(
				array(
					'post_type'      => 'reto',
					'title'          => $reto_value,
					'post_status'    => 'any',
					'posts_per_page' => 1,
				)
			);
			if ( ! empty( $found_retos ) ) {
				$retos_seleccionados[] = $found_retos[0]->ID;
			}
		}
	}

	$obligatorios       = gnf_get_obligatorio_reto_ids();
	$retos_seleccionados = array_values( array_unique( array_merge( $obligatorios, $retos_seleccionados ) ) );
	$retos_seleccionados = gnf_expand_retos_con_hijos( $retos_seleccionados );
	$retos_seleccionados = gnf_filter_reto_ids_by_year_form( $retos_seleccionados, $anio );
	$retos_seleccionados = gnf_sort_reto_ids_required_first( $retos_seleccionados );

	$table = $wpdb->prefix . 'gn_matriculas';
	$existing_matricula = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT id FROM {$table} WHERE centro_id = %d AND anio = %d",
			$centro_id,
			$anio
		)
	);

	$matricula_data = array(
		'centro_id'           => $centro_id,
		'user_id'             => $user_id,
		'anio'                => $anio,
		'meta_estrellas'      => $meta_estrellas,
		'retos_seleccionados' => wp_json_encode( $retos_seleccionados, JSON_UNESCAPED_UNICODE ),
		'data'                => wp_json_encode( $normalized_fields, JSON_UNESCAPED_UNICODE ),
		'estado'              => 'pendiente',
		'updated_at'          => current_time( 'mysql' ),
	);

	if ( $existing_matricula ) {
		$wpdb->update( $table, $matricula_data, array( 'id' => $existing_matricula->id ) );
	} else {
		$matricula_data['created_at'] = current_time( 'mysql' );
		$wpdb->insert( $table, $matricula_data );
	}

	gnf_set_centro_anual_data(
		$centro_id,
		$anio,
		array(
			'retos_seleccionados' => $retos_seleccionados,
			'meta_estrellas'      => $meta_estrellas,
			'comite_estudiantes'  => absint( $normalized_fields['bae-comite-estudiantes'] ?? 0 ),
			'estado_matricula'    => 'pendiente',
		)
	);

	gnf_notify_admins_new_matricula( $centro_id, $user_id );
}

/**
 * Notifica a administradores sobre nueva matrícula.
 */
function gnf_notify_admins_new_matricula( $centro_id, $user_id ) {
	$centro_title = get_the_title( $centro_id );
	$user         = get_user_by( 'id', $user_id );
	$user_name    = $user ? $user->display_name : 'Usuario';

	// Crear notificación en tabla.
	global $wpdb;
	$table = $wpdb->prefix . 'gn_notificaciones';

	// Notificar a todos los admins.
	$admins = get_users( array( 'role' => 'administrator' ) );
	foreach ( $admins as $admin ) {
		$wpdb->insert(
			$table,
			array(
				'user_id'      => $admin->ID,
				'tipo'         => 'nueva_matricula',
				'mensaje'      => sprintf( 'Nueva matrícula: %s por %s', $centro_title, $user_name ),
				'relacion_tipo' => 'centro',
				'relacion_id'  => $centro_id,
				'leido'        => 0,
				'created_at'   => current_time( 'mysql' ),
			)
		);
	}
}

/**
 * Encuentra un centro educativo por código MEP.
 */
function gnf_find_centro_by_codigo( $codigo ) {
	$query = new WP_Query(
		array(
			'post_type'      => 'centro_educativo',
			'posts_per_page' => 1,
			'meta_query'     => array(
				array(
					'key'   => 'codigo_mep',
					'value' => $codigo,
				),
			),
			'fields' => 'ids',
		)
	);
	if ( $query->have_posts() ) {
		return $query->posts[0];
	}
	return 0;
}

/**
 * Construye mapa compacto de respuestas de campos WPForms para scoring.
 * { field_id: value } donde value es el valor respondido o 1/0 para files.
 *
 * @param array $raw_fields Campos WPForms indexados por field_id.
 * @return array
 */
function gnf_build_fields_summary( $raw_fields ) {
	$summary = array();
	foreach ( (array) $raw_fields as $field_id => $field ) {
		$type  = $field['type'] ?? '';
		$value = $field['value'] ?? '';

		if ( in_array( $type, array( 'file-upload', 'file' ), true ) ) {
			$summary[ (int) $field_id ] = empty( $value ) ? 0 : 1;
		} else {
			$summary[ (int) $field_id ] = $value;
		}
	}
	return $summary;
}

/**
 * Conserva valores crudos por field_id para rehidratar el formulario en React.
 *
 * @param array $raw_fields Campos WPForms indexados por field_id.
 * @return array
 */
function gnf_build_raw_field_values( $raw_fields ) {
	$values = array();
	foreach ( (array) $raw_fields as $field_id => $field ) {
		$value                    = $field['value'] ?? '';
		$values[ (int) $field_id ] = is_array( $value ) ? array_values( $value ) : $value;
	}
	return $values;
}

/**
 * Fusiona la data persistida del entry con el snapshot actual del formulario.
 *
 * @param array $existing_data     JSON previo decodificado.
 * @param array $normalized_fields Campos normalizados del request actual.
 * @param array $fields_summary    Resumen compacto para scoring.
 * @param array $raw_field_values  Valores crudos por field_id.
 * @return array
 */
function gnf_merge_reto_entry_data( $existing_data, $normalized_fields, $fields_summary, $raw_field_values ) {
	$merged = is_array( $existing_data ) ? $existing_data : array();

	foreach ( (array) $normalized_fields as $key => $value ) {
		if ( '_raw' === $key ) {
			continue;
		}
		$merged[ $key ] = $value;
	}

	$merged['__fields__']     = array_replace( (array) ( $merged['__fields__'] ?? array() ), $fields_summary );
	$merged['__raw_values__'] = array_replace( (array) ( $merged['__raw_values__'] ?? array() ), $raw_field_values );
	$merged['__saved_at']     = current_time( 'mysql' );

	return $merged;
}

/**
 * Fusiona evidencias persistidas con nuevas evidencias sin duplicarlas.
 *
 * @param array $existing Lista previa.
 * @param array $incoming Lista nueva.
 * @return array
 */
function gnf_merge_reto_evidencias( $existing, $incoming ) {
	$merged = array();
	$seen   = array();

	foreach ( array_merge( (array) $existing, (array) $incoming ) as $evidencia ) {
		if ( ! is_array( $evidencia ) ) {
			continue;
		}

		$key = implode(
			'|',
			array(
				(string) ( $evidencia['field_id'] ?? '' ),
				(string) ( $evidencia['ruta'] ?? '' ),
				(string) ( $evidencia['nombre'] ?? '' ),
			)
		);

		if ( isset( $seen[ $key ] ) ) {
			continue;
		}

		$seen[ $key ] = true;
		$merged[]     = $evidencia;
	}

	return array_values( $merged );
}

/**
 * Procesa un envío de reto y lo almacena en wp_gn_reto_entries.
 *
 * El puntaje se calcula automáticamente basado en field_points de configuracion_por_anio (ACF).
 */
function gnf_handle_reto_submission( $reto_post, $normalized_fields, $entry_id, $form_data, $raw_fields ) {
	global $wpdb;

	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		// Intento de mapear por correo enviado en el formulario.
		if ( ! empty( $normalized_fields['email'] ) ) {
			$user = get_user_by( 'email', $normalized_fields['email'] );
			if ( $user ) {
				$user_id = $user->ID;
			}
		}
	}

	$centro_id = gnf_get_centro_for_docente( $user_id );
	if ( ! $centro_id && ! empty( $normalized_fields['centro-id'] ) ) {
		$centro_id = absint( $normalized_fields['centro-id'] );
	}

	gnf_store_reto_entry( $reto_post, $normalized_fields, $entry_id, $form_data, $raw_fields, 'en_progreso', $centro_id );
}

/**
 * Guarda reto entry con estado parametrizable.
 */
function gnf_store_reto_entry( $reto_post, $normalized_fields, $entry_id, $form_data, $raw_fields, $estado = 'enviado', $centro_id = 0 ) {
	global $wpdb;

	$user_id = get_current_user_id();
	if ( ! $user_id && ! empty( $normalized_fields['email'] ) ) {
		$user = get_user_by( 'email', $normalized_fields['email'] );
		if ( $user ) {
			$user_id = $user->ID;
		}
	}
	if ( ! $user_id && ! empty( $normalized_fields['centro-id'] ) ) {
		$docentes = (array) get_field( 'docentes_asociados', absint( $normalized_fields['centro-id'] ) );
		if ( $docentes ) {
			$user_id = (int) $docentes[0];
		}
	}

	if ( ! $centro_id ) {
		$centro_id = gnf_get_centro_for_docente( $user_id );
		if ( ! $centro_id && ! empty( $normalized_fields['centro-id'] ) ) {
			$centro_id = absint( $normalized_fields['centro-id'] );
		}
	}

	$anio       = isset( $normalized_fields['anio'] ) ? absint( $normalized_fields['anio'] ) : gnf_get_context_year( gnf_get_active_year() );
	$anio       = gnf_normalize_year( $anio );
	$evidencias = gnf_collect_evidencias( $raw_fields, $anio, $centro_id, $reto_post->ID );

	$evidence_warning     = false;
	foreach ( (array) $evidencias as $ev ) {
		if ( ! empty( $ev['requires_year_validation'] ) ) {
			$evidence_warning = true;
			break;
		}
	}

	$table = $wpdb->prefix . 'gn_reto_entries';
	$found = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE centro_id = %d AND reto_id = %d AND anio = %d",
			$centro_id,
			$reto_post->ID,
			$anio
		)
	);

	// Mapa compacto { field_id => value_summary } para scoring automático.
	$fields_summary = gnf_build_fields_summary( $raw_fields );
	$raw_values     = gnf_build_raw_field_values( $raw_fields );

	// Guard: no sobrescribir una entrada ya aprobada.
	if ( $found && 'aprobado' === $found->estado ) {
		return $found;
	}
	// Guard: no degradar estado de enviado/completo a en_progreso.
	if ( $found && 'en_progreso' === $estado && in_array( $found->estado, array( 'enviado', 'completo' ), true ) ) {
		$estado = $found->estado;
	}

	$existing_data       = $found && ! empty( $found->data ) ? json_decode( $found->data, true ) : array();
	$existing_evidencias = $found && ! empty( $found->evidencias ) ? json_decode( $found->evidencias, true ) : array();
	$merged_data         = gnf_merge_reto_entry_data( $existing_data, $normalized_fields, $fields_summary, $raw_values );
	$merged_evidencias   = gnf_merge_reto_evidencias( $existing_evidencias, $evidencias );

	$data = array(
		'wpforms_entry_id' => (int) $entry_id,
		'user_id'          => (int) $user_id,
		'centro_id'        => (int) $centro_id,
		'reto_id'          => (int) $reto_post->ID,
		'anio'             => (int) $anio,
		'data'             => wp_json_encode( $merged_data, JSON_UNESCAPED_UNICODE ),
		'evidencias'       => wp_json_encode( $merged_evidencias, JSON_UNESCAPED_UNICODE ),
		'estado'           => $estado,
		'updated_at'       => current_time( 'mysql' ),
	);

	if ( $found ) {
		$wpdb->update(
			$table,
			$data,
			array( 'id' => $found->id ),
			array( '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);
		$entry_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $found->id ) );
	} else {
		$data['created_at'] = current_time( 'mysql' );
		$wpdb->insert(
			$table,
			$data,
			array( '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
		);
		$entry_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $wpdb->insert_id ) );
	}

	$puntaje = gnf_recalcular_puntaje_reto( $entry_row );
	$wpdb->update(
		$table,
		array( 'puntaje' => $puntaje ),
		array( 'id' => $entry_row->id ),
		array( '%d' ),
		array( '%d' )
	);

	gnf_recalcular_puntaje_centro( $centro_id, $anio );
	gnf_clear_supervisor_cache();

	// Notificar a supervisores cuando se envía reto a revisión.
	if ( 'enviado' === $estado ) {
		$region = get_post_meta( $centro_id, 'region', true );
		if ( empty( $region ) ) {
			$terms  = wp_get_post_terms( $centro_id, 'gn_region', array( 'fields' => 'ids' ) );
			$region = $terms ? $terms[0] : '';
		}
		if ( $region ) {
			$supervisores = gnf_get_supervisores_by_region( $region );
			$reto_title   = get_the_title( $reto_post->ID );
			$centro_title = get_the_title( $centro_id );
			$mensaje      = sprintf( 'Reto "%s" enviado a revisión: %s', $reto_title, $centro_title );
			foreach ( $supervisores as $sup ) {
				gnf_insert_notification( $sup->ID, 'reto_enviado', $mensaje, 'reto_entry', $entry_row->id );
			}
		}
	}

	if ( $evidence_warning ) {
		gnf_insert_notification( $user_id, 'invalid_photo_date', 'Hay evidencias de foto que requieren validar el año.', 'reto_entry', $entry_row->id );
	}
}
