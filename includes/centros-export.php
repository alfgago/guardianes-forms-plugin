<?php
/**
 * Exportacion completa y por lotes de centros educativos.
 *
 * @package Guardianes_Formularios
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Esquema fijo de la hoja de centros.
 *
 * @return array<string,string> Encabezado => clave del registro.
 */
function gnf_centros_export_column_map() {
	return array(
		'Centro ID'                    => 'centro_id',
		'Centro educativo'             => 'nombre',
		'Estado publicación'           => 'post_status',
		'Estado administrativo'        => 'estado_centro',
		'Fecha creación'               => 'created_at',
		'Última modificación'          => 'updated_at',
		'Código MEP'                   => 'codigo_mep',
		'Código presupuestario'        => 'codigo_presupuestario',
		'Dirección Regional'           => 'region_name',
		'Dirección Regional ID'        => 'region_id',
		'Circuito'                     => 'circuito',
		'Dirección'                    => 'direccion',
		'Provincia'                    => 'provincia',
		'Cantón'                       => 'canton',
		'Distrito'                     => 'distrito',
		'Poblado'                      => 'poblado',
		'Dependencia'                  => 'dependencia',
		'Zona'                         => 'zona',
		'Teléfono'                     => 'telefono',
		'Teléfono 2'                   => 'telefono2',
		'Correo institucional'         => 'correo_institucional',
		'Correo usuario matrícula'     => 'matricula_user_email',
		'Correos para invitación'      => 'contact_emails',
		'Nivel educativo'              => 'nivel_educativo',
		'Jornada'                      => 'jornada',
		'Tipo centro educativo'        => 'tipo_centro',
		'Tipología'                    => 'tipologia',
		'Total estudiantes'            => 'total_estudiantes',
		'Estudiantes hombres'          => 'estudiantes_hombres',
		'Estudiantes mujeres'          => 'estudiantes_mujeres',
		'Estudiantes migrantes'        => 'estudiantes_migrantes',
		'Último galardón'              => 'ultimo_galardon',
		'Último año de participación'  => 'ultimo_anio_participacion',
		'Último año participación (otro)' => 'ultimo_anio_participacion_otro',
		'Año'                          => 'anio',
		'Matrícula ID'                 => 'matricula_id',
		'Usuario matrícula ID'         => 'matricula_user_id',
		'Fecha de matrícula'           => 'matricula_created_at',
		'Última actualización de matrícula' => 'matricula_updated_at',
		'Estado matrícula'             => 'matricula_estado',
		'Centro existente al matricular' => 'matricula_centro_existe',
		'Cargo coordinación PBAE'      => 'coordinador_cargo',
		'Nombre coordinación PBAE'     => 'coordinador_nombre',
		'Teléfono coordinación PBAE'   => 'coordinador_telefono',
		'Nombre registrado en matrícula' => 'matricula_docente_nombre',
		'Cargo registrado en matrícula' => 'matricula_docente_cargo',
		'Teléfono registrado en matrícula' => 'matricula_docente_telefono',
		'Correo registrado en matrícula' => 'matricula_docente_email',
		'Confirmación de correo registrada' => 'matricula_docente_email_confirm',
		'Confirmaciones de matrícula'  => 'matricula_confirmaciones',
		'Inscripción en años anteriores' => 'inscripcion_anterior',
		'Meta estrellas'               => 'meta_estrellas',
		'Comité de estudiantes'        => 'comite_estudiantes',
		'Retos seleccionados IDs'      => 'reto_ids',
		'Cantidad de retos'            => 'reto_count',
		'Puntaje total'                => 'puntaje_total',
		'Estrella final'               => 'estrella_final',
		'Usuarios docentes IDs'        => 'docente_ids',
		'Usuarios docentes'            => 'docente_logins',
		'Nombres docentes'             => 'docente_names',
		'Correos docentes'             => 'docente_emails',
		'Estados docentes'             => 'docente_statuses',
		'Teléfonos docentes'           => 'docente_phones',
		'Cargos docentes'              => 'docente_positions',
		'Identificaciones docentes'    => 'docente_identifications',
		'Fechas registro docentes'     => 'docente_registered_at',
		'Registrado por docente'       => 'registered_label',
		'Tiene matrícula del año'      => 'has_matricula_label',
		'Diagnóstico'                  => 'diagnostico',
	);
}

/**
 * Encabezados de la hoja principal.
 *
 * @return string[]
 */
function gnf_centros_export_headers() {
	return array_keys( gnf_centros_export_column_map() );
}

/**
 * Fuerza como texto valores que Excel podría interpretar como fórmulas.
 *
 * @param mixed $value Valor de celda.
 * @return mixed
 */
function gnf_export_safe_cell_value( $value ) {
	if ( is_string( $value ) && preg_match( '/^[\x00-\x20]*[=+\-@]/', $value ) ) {
		return "'" . $value;
	}
	return $value;
}

/**
 * Convierte un registro asociativo en una fila estable.
 *
 * @param array<string,mixed> $record Registro normalizado.
 * @return array<int,mixed>
 */
function gnf_centros_export_row( $record ) {
	$row = array();
	foreach ( gnf_centros_export_column_map() as $key ) {
		$value = $record[ $key ] ?? '';
		if ( is_array( $value ) ) {
			$value = implode( '; ', array_map( 'strval', $value ) );
		}
		$value = gnf_export_safe_cell_value( $value );
		$row[] = $value;
	}

	return $row;
}

/**
 * Normaliza valores potencialmente serializados a IDs.
 *
 * @param mixed $value Valor almacenado.
 * @return int[]
 */
function gnf_centros_export_normalize_ids( $value ) {
	if ( is_string( $value ) ) {
		$decoded = json_decode( $value, true );
		if ( is_array( $decoded ) ) {
			$value = $decoded;
		} else {
			$value = maybe_unserialize( $value );
		}
	}

	return array_values( array_unique( array_filter( array_map( 'absint', (array) $value ) ) ) );
}

/**
 * Normaliza y deduplica los correos útiles para convocatorias.
 *
 * @param array<int,mixed> $values Correos individuales, listas o arreglos.
 * @return string[]
 */
function gnf_centros_export_contact_emails( $values ) {
	$queue  = array_values( (array) $values );
	$emails = array();

	while ( ! empty( $queue ) ) {
		$value = array_shift( $queue );
		if ( is_array( $value ) ) {
			$queue = array_merge( array_values( $value ), $queue );
			continue;
		}

		$parts = preg_split( '/[\s,;]+/', trim( (string) $value ), -1, PREG_SPLIT_NO_EMPTY );
		foreach ( (array) $parts as $part ) {
			$email = strtolower( trim( (string) $part ) );
			$valid = function_exists( 'is_email' ) ? is_email( $email ) : filter_var( $email, FILTER_VALIDATE_EMAIL );
			if ( $valid && ! in_array( $email, $emails, true ) ) {
				$emails[] = $email;
			}
		}
	}

	return $emails;
}

/**
 * Carga relaciones de un lote sin consultas por fila.
 *
 * @param int[] $centro_ids IDs del lote.
 * @param int   $anio       Año exportado.
 * @return array<string,array>
 */
function gnf_build_centros_export_batch_maps( $centro_ids, $anio ) {
	global $wpdb;

	$centro_ids = array_values( array_filter( array_map( 'absint', (array) $centro_ids ) ) );
	$maps       = array(
		'matriculas' => array(),
		'users'      => array(),
		'regions'    => array(),
		'scores'     => array(),
	);
	if ( empty( $centro_ids ) ) {
		return $maps;
	}

	update_meta_cache( 'post', $centro_ids );
	$id_placeholders = implode( ',', array_fill( 0, count( $centro_ids ), '%d' ) );

	$matricula_table = $wpdb->prefix . 'gn_matriculas';
	$matricula_rows  = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$matricula_table} WHERE anio = %d AND centro_id IN ({$id_placeholders})",
			array_merge( array( absint( $anio ) ), $centro_ids )
		),
		ARRAY_A
	);
	foreach ( (array) $matricula_rows as $row ) {
		$centro_id        = absint( $row['centro_id'] ?? 0 );
		$matricula_user_id = absint( $row['user_id'] ?? 0 );
		$maps['matriculas'][ $centro_id ] = $row;
		if ( $centro_id && $matricula_user_id ) {
			$maps['users'][ $centro_id ][] = $matricula_user_id;
		}
	}

	$entry_table = $wpdb->prefix . 'gn_reto_entries';
	$score_rows  = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT centro_id, SUM(puntaje) AS puntaje FROM {$entry_table} WHERE anio = %d AND centro_id IN ({$id_placeholders}) GROUP BY centro_id",
			array_merge( array( absint( $anio ) ), $centro_ids )
		),
		ARRAY_A
	);
	foreach ( (array) $score_rows as $row ) {
		$maps['scores'][ absint( $row['centro_id'] ?? 0 ) ] = absint( $row['puntaje'] ?? 0 );
	}

	$meta_keys        = array( 'centro_educativo_id', 'centro_solicitado', 'gnf_centro_id' );
	$key_placeholders = implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) );
	$user_rows        = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key IN ({$key_placeholders}) AND CAST(meta_value AS UNSIGNED) IN ({$id_placeholders})",
			array_merge( $meta_keys, $centro_ids )
		),
		ARRAY_A
	);
	foreach ( (array) $user_rows as $row ) {
		$centro_id = absint( $row['meta_value'] ?? 0 );
		$user_id   = absint( $row['user_id'] ?? 0 );
		if ( $centro_id && $user_id ) {
			$maps['users'][ $centro_id ][] = $user_id;
		}
	}
	foreach ( $centro_ids as $centro_id ) {
		$associated = gnf_centros_export_normalize_ids( get_post_meta( $centro_id, 'docentes_asociados', true ) );
		if ( $associated ) {
			$maps['users'][ $centro_id ] = array_merge( $maps['users'][ $centro_id ] ?? array(), $associated );
		}
		$maps['users'][ $centro_id ] = array_values( array_unique( array_map( 'absint', $maps['users'][ $centro_id ] ?? array() ) ) );
		sort( $maps['users'][ $centro_id ], SORT_NUMERIC );
	}

	$all_user_ids = array_values( array_unique( array_merge( ...array_values( $maps['users'] ?: array( array() ) ) ) ) );
	if ( $all_user_ids && function_exists( 'cache_users' ) ) {
		cache_users( $all_user_ids );
		update_meta_cache( 'user', $all_user_ids );
	}

	$terms = wp_get_object_terms(
		$centro_ids,
		'gn_region',
		array( 'fields' => 'all_with_object_id' )
	);
	if ( ! is_wp_error( $terms ) ) {
		foreach ( $terms as $term ) {
			$object_id = absint( $term->object_id ?? 0 );
			if ( $object_id && ! isset( $maps['regions'][ $object_id ] ) ) {
				$maps['regions'][ $object_id ] = array(
					'id'   => absint( $term->term_id ),
					'name' => (string) $term->name,
				);
			}
		}
	}

	$legacy_region_ids     = array();
	$legacy_region_centros = array();
	foreach ( $centro_ids as $centro_id ) {
		if ( isset( $maps['regions'][ $centro_id ] ) ) {
			continue;
		}

		$legacy_region_id = absint( get_post_meta( $centro_id, 'region', true ) );
		if ( $legacy_region_id ) {
			$legacy_region_centros[ $centro_id ] = $legacy_region_id;
			$legacy_region_ids[]                 = $legacy_region_id;
		}
	}

	if ( $legacy_region_ids ) {
		$legacy_terms = get_terms(
			array(
				'taxonomy'   => 'gn_region',
				'hide_empty' => false,
				'include'    => $legacy_region_ids,
			)
		);
		if ( ! is_wp_error( $legacy_terms ) ) {
			$legacy_terms_by_id = array();
			foreach ( $legacy_terms as $term ) {
				$legacy_terms_by_id[ absint( $term->term_id ) ] = (string) $term->name;
			}
			foreach ( $legacy_region_centros as $centro_id => $legacy_region_id ) {
				if ( isset( $legacy_terms_by_id[ $legacy_region_id ] ) ) {
					$maps['regions'][ $centro_id ] = array(
						'id'   => $legacy_region_id,
						'name' => $legacy_terms_by_id[ $legacy_region_id ],
					);
				}
			}
		}
	}

	return $maps;
}

/**
 * Obtiene la fila anual sin repetir lecturas por cada campo.
 *
 * @param int        $centro_id Centro.
 * @param int        $anio      Año.
 * @param array|null $matricula Matrícula del lote.
 * @param int        $score     Puntaje calculado.
 * @return array<string,mixed>
 */
function gnf_build_centro_export_annual_record( $centro_id, $anio, $matricula, $score ) {
	$exists = false;
	$annual = function_exists( 'gnf_get_centro_anual_row' )
		? gnf_get_centro_anual_row( $centro_id, $anio, $exists )
		: array();
	$data   = is_array( $matricula ) ? json_decode( (string) ( $matricula['data'] ?? '' ), true ) : array();
	$data   = is_array( $data ) ? $data : array();

	$reto_ids = $exists ? (array) ( $annual['retos_seleccionados'] ?? array() ) : array();
	if ( empty( $reto_ids ) && is_array( $matricula ) ) {
		$reto_ids = gnf_centros_export_normalize_ids( $matricula['retos_seleccionados'] ?? array() );
	}
	$reto_ids = array_values( array_unique( array_filter( array_map( 'absint', $reto_ids ) ) ) );

	$puntaje = $exists ? absint( $annual['puntaje_total'] ?? 0 ) : absint( $score );
	$estrella = $exists ? absint( $annual['estrella_final'] ?? 0 ) : 0;
	if ( ! $estrella && $puntaje && function_exists( 'gnf_calcular_estrella_por_puntaje' ) ) {
		$estrella = absint( gnf_calcular_estrella_por_puntaje( $puntaje ) );
	}

	return array(
		'data'                => $data,
		'matricula_id'        => is_array( $matricula ) ? absint( $matricula['id'] ?? 0 ) : 0,
		'matricula_user_id'   => is_array( $matricula ) ? absint( $matricula['user_id'] ?? 0 ) : 0,
		'matricula_created_at' => is_array( $matricula ) ? (string) ( $matricula['created_at'] ?? '' ) : '',
		'matricula_updated_at' => is_array( $matricula ) ? (string) ( $matricula['updated_at'] ?? '' ) : '',
		'matricula_estado'    => $exists ? (string) ( $annual['estado_matricula'] ?? '' ) : (string) ( $matricula['estado'] ?? '' ),
		'meta_estrellas'      => $exists ? absint( $annual['meta_estrellas'] ?? 0 ) : absint( $matricula['meta_estrellas'] ?? 0 ),
		'comite_estudiantes'  => $exists ? absint( $annual['comite_estudiantes'] ?? 0 ) : absint( $data['bae-comite-estudiantes'] ?? 0 ),
		'reto_ids'            => $reto_ids,
		'puntaje_total'       => $puntaje,
		'estrella_final'      => $estrella,
	);
}

/**
 * Devuelve un diagnóstico legible de la relación centro/matrícula/docentes.
 *
 * @param bool   $registered     Tiene docentes.
 * @param bool   $has_matricula  Tiene matrícula.
 * @param bool   $published      Está publicado.
 * @param string $region_name    Nombre DRE.
 * @return string
 */
function gnf_centros_export_diagnostic( $registered, $has_matricula, $published, $region_name ) {
	$messages = array();
	if ( ! $published ) {
		$messages[] = 'Centro no publicado';
	}
	if ( '' === trim( (string) $region_name ) ) {
		$messages[] = 'Centro sin DRE';
	}
	if ( $registered && ! $has_matricula ) {
		$messages[] = 'Registrado por docente sin matrícula del año';
	}
	if ( ! $registered && $has_matricula ) {
		$messages[] = 'Matrícula del año sin cuenta docente asociada';
	}
	if ( ! $registered && ! $has_matricula ) {
		$messages[] = 'Sin registro docente ni matrícula del año';
	}

	return $messages ? implode( '; ', $messages ) : 'Registrado y matriculado en el año';
}

/**
 * Construye un registro completo desde los mapas precargados.
 *
 * @param int                  $centro_id Centro.
 * @param int                  $anio      Año.
 * @param array<string,array>  $batch     Mapas del lote.
 * @return array<string,mixed>
 */
function gnf_build_centro_export_record( $centro_id, $anio, $batch ) {
	$post      = get_post( $centro_id );
	$region    = $batch['regions'][ $centro_id ] ?? array( 'id' => 0, 'name' => '' );
	$matricula = $batch['matriculas'][ $centro_id ] ?? null;
	$annual    = gnf_build_centro_export_annual_record( $centro_id, $anio, $matricula, $batch['scores'][ $centro_id ] ?? 0 );
	$form_data = $annual['data'];
	$user_ids  = $batch['users'][ $centro_id ] ?? array();

	$docente_ids            = array();
	$docente_logins         = array();
	$docente_names          = array();
	$docente_emails         = array();
	$docente_statuses       = array();
	$docente_phones         = array();
	$docente_positions      = array();
	$docente_identifications = array();
	$docente_registered_at  = array();
	foreach ( $user_ids as $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user instanceof WP_User || ! in_array( 'docente', (array) $user->roles, true ) ) {
			continue;
		}
		$docente_ids[]             = (int) $user->ID;
		$docente_logins[]          = (string) $user->user_login;
		$docente_names[]           = (string) $user->display_name;
		$docente_emails[]          = (string) $user->user_email;
		$docente_statuses[]        = function_exists( 'gnf_get_docente_estado' ) ? gnf_get_docente_estado( $user->ID ) : '';
		$docente_phones[]          = (string) ( get_user_meta( $user->ID, 'gnf_telefono', true ) ?: get_user_meta( $user->ID, 'docente_telefono', true ) );
		$docente_positions[]       = (string) ( get_user_meta( $user->ID, 'gnf_cargo', true ) ?: get_user_meta( $user->ID, 'docente_cargo', true ) );
		$docente_identifications[] = (string) get_user_meta( $user->ID, 'gnf_identificacion', true );
		$docente_registered_at[]   = (string) $user->user_registered;
	}

	$meta = static function ( $key ) use ( $centro_id ) {
		return get_post_meta( $centro_id, $key, true );
	};
	$form_or_meta = static function ( $form_key, $meta_key, $default = '' ) use ( $form_data, $meta ) {
		if ( array_key_exists( $form_key, $form_data ) && '' !== $form_data[ $form_key ] ) {
			return $form_data[ $form_key ];
		}
		$value = $meta( $meta_key );
		return '' !== $value ? $value : $default;
	};
	$form_value = static function ( $form_key, $default = '' ) use ( $form_data ) {
		return array_key_exists( $form_key, $form_data ) ? $form_data[ $form_key ] : $default;
	};
	$registered    = ! empty( $docente_ids );
	$has_matricula = null !== $matricula;
	$region_name   = (string) ( $region['name'] ?? '' );
	$matricula_user_id    = absint( $annual['matricula_user_id'] ?? 0 );
	$matricula_user       = $matricula_user_id ? get_userdata( $matricula_user_id ) : null;
	$matricula_user_email = $matricula_user instanceof WP_User ? (string) $matricula_user->user_email : '';
	$correo_institucional = (string) $form_or_meta( 'centro-correo-institucional', 'correo_institucional' );
	$matricula_docente_email = (string) $form_value( 'docente-email' );
	$matricula_email_confirm = (string) $form_value( 'docente-email-confirm' );
	$contact_emails = gnf_centros_export_contact_emails(
		array(
			$correo_institucional,
			$matricula_user_email,
			$matricula_docente_email,
			$matricula_email_confirm,
			$docente_emails,
		)
	);

	return array(
		'centro_id'                 => absint( $centro_id ),
		'nombre'                    => $post instanceof WP_Post ? $post->post_title : '',
		'post_status'               => $post instanceof WP_Post ? $post->post_status : '',
		'estado_centro'             => (string) $meta( 'estado_centro' ),
		'created_at'                => $post instanceof WP_Post ? $post->post_date : '',
		'updated_at'                => $post instanceof WP_Post ? $post->post_modified : '',
		'codigo_mep'                => (string) $meta( 'codigo_mep' ),
		'codigo_presupuestario'     => (string) $form_or_meta( 'centro-codigo-presupuestario', 'codigo_presupuestario' ),
		'region_name'               => $region_name,
		'region_id'                 => absint( $region['id'] ?? 0 ),
		'circuito'                  => (string) $form_or_meta( 'centro-circuito', 'circuito' ),
		'direccion'                 => (string) $form_or_meta( 'centro-direccion', 'direccion' ),
		'provincia'                 => (string) $form_or_meta( 'centro-provincia', 'provincia' ),
		'canton'                    => (string) $form_or_meta( 'centro-canton', 'canton' ),
		'distrito'                  => (string) $meta( 'distrito' ),
		'poblado'                   => (string) $meta( 'poblado' ),
		'dependencia'               => (string) $form_or_meta( 'centro-dependencia', 'dependencia' ),
		'zona'                      => (string) $meta( 'zona' ),
		'telefono'                  => (string) $form_or_meta( 'centro-telefono', 'telefono' ),
		'telefono2'                 => (string) $meta( 'telefono2' ),
		'correo_institucional'      => $correo_institucional,
		'matricula_user_email'      => $matricula_user_email,
		'contact_emails'            => $contact_emails,
		'nivel_educativo'           => (string) $form_or_meta( 'centro-nivel-educativo', 'nivel_educativo' ),
		'jornada'                   => (string) $form_or_meta( 'centro-jornada', 'jornada' ),
		'tipo_centro'               => (string) $form_or_meta( 'centro-tipo-centro-educativo', 'tipo_centro_educativo' ),
		'tipologia'                 => (string) $form_or_meta( 'centro-tipologia', 'tipologia' ),
		'total_estudiantes'         => absint( $form_or_meta( 'centro-total-estudiantes', 'total_estudiantes', 0 ) ),
		'estudiantes_hombres'       => absint( $form_or_meta( 'centro-estudiantes-hombres', 'estudiantes_hombres', 0 ) ),
		'estudiantes_mujeres'       => absint( $form_or_meta( 'centro-estudiantes-mujeres', 'estudiantes_mujeres', 0 ) ),
		'estudiantes_migrantes'     => absint( $form_or_meta( 'centro-estudiantes-migrantes', 'estudiantes_migrantes', 0 ) ),
		'ultimo_galardon'           => (string) $form_or_meta( 'centro-ultimo-galardon-estrellas', 'ultimo_galardon_estrellas' ),
		'ultimo_anio_participacion' => (string) $form_or_meta( 'centro-ultimo-anio-participacion', 'ultimo_anio_participacion' ),
		'ultimo_anio_participacion_otro' => (string) $form_or_meta( 'centro-ultimo-anio-participacion-otro', 'ultimo_anio_participacion_otro' ),
		'anio'                      => absint( $anio ),
		'matricula_id'              => absint( $annual['matricula_id'] ),
		'matricula_user_id'         => absint( $annual['matricula_user_id'] ),
		'matricula_created_at'      => (string) $annual['matricula_created_at'],
		'matricula_updated_at'      => (string) $annual['matricula_updated_at'],
		'matricula_estado'          => (string) $annual['matricula_estado'],
		'matricula_centro_existe'   => (string) $form_value( 'centro-existe' ),
		'coordinador_cargo'         => (string) $form_or_meta( 'coordinador-cargo', 'coordinador_pbae_cargo' ),
		'coordinador_nombre'        => (string) $form_or_meta( 'coordinador-nombre', 'coordinador_pbae_nombre' ),
		'coordinador_telefono'      => (string) $form_or_meta( 'coordinador-celular', 'coordinador_pbae_celular' ),
		'matricula_docente_nombre'  => (string) $form_value( 'docente-nombre' ),
		'matricula_docente_cargo'   => (string) $form_value( 'docente-cargo' ),
		'matricula_docente_telefono' => (string) $form_value( 'docente-telefono' ),
		'matricula_docente_email'   => $matricula_docente_email,
		'matricula_docente_email_confirm' => $matricula_email_confirm,
		'matricula_confirmaciones'  => (array) $form_value( 'docente-confirmaciones', array() ),
		'inscripcion_anterior'      => (string) $form_value( 'bae-inscripcion-anterior' ),
		'meta_estrellas'            => absint( $annual['meta_estrellas'] ),
		'comite_estudiantes'        => absint( $annual['comite_estudiantes'] ),
		'reto_ids'                  => $annual['reto_ids'],
		'reto_count'                => count( $annual['reto_ids'] ),
		'puntaje_total'             => absint( $annual['puntaje_total'] ),
		'estrella_final'            => absint( $annual['estrella_final'] ),
		'docente_ids'               => $docente_ids,
		'docente_logins'            => $docente_logins,
		'docente_names'             => $docente_names,
		'docente_emails'            => $docente_emails,
		'docente_statuses'          => $docente_statuses,
		'docente_phones'            => $docente_phones,
		'docente_positions'         => $docente_positions,
		'docente_identifications'   => $docente_identifications,
		'docente_registered_at'     => $docente_registered_at,
		'registered_label'          => $registered ? 'Sí' : 'No',
		'has_matricula_label'       => $has_matricula ? 'Sí' : 'No',
		'diagnostico'               => gnf_centros_export_diagnostic( $registered, $has_matricula, 'publish' === ( $post->post_status ?? '' ), $region_name ),
	);
}

/**
 * Itera los centros inscritos en el pilotaje usando memoria constante.
 *
 * @param int    $anio      Año.
 * @param int    $region_id DRE opcional.
 * @param string $circuito  Circuito opcional.
 * @return Generator<array<string,mixed>>
 */
function gnf_iter_centros_export_records( $anio, $region_id = 0, $circuito = '' ) {
	$page      = 1;
	$anio      = function_exists( 'gnf_normalize_year' ) ? gnf_normalize_year( $anio ) : absint( $anio );
	$region_id = absint( $region_id );
	$circuito  = function_exists( 'gnf_normalize_circuito' ) ? gnf_normalize_circuito( $circuito ) : trim( (string) $circuito );
	$participant_ids = function_exists( 'gnf_get_centros_with_matricula' )
		? array_values( array_filter( array_map( 'absint', gnf_get_centros_with_matricula( $anio ) ) ) )
		: array();
	if ( empty( $participant_ids ) ) {
		return;
	}

	do {
		$args = array(
			'post_type'              => 'centro_educativo',
			'post_status'            => 'publish',
			'posts_per_page'         => 200,
			'paged'                  => $page,
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'post__in'               => $participant_ids,
			'no_found_rows'          => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => true,
		);

		$query = new WP_Query( $args );
		$ids   = array_values( array_map( 'absint', wp_list_pluck( $query->posts, 'ID' ) ) );
		$batch = gnf_build_centros_export_batch_maps( $ids, $anio );
		foreach ( $ids as $centro_id ) {
			$record = gnf_build_centro_export_record( $centro_id, $anio, $batch );
			if ( $region_id && absint( $record['region_id'] ?? 0 ) !== $region_id ) {
				continue;
			}
			$record_circuito = gnf_normalize_circuito( $record['circuito'] ?? '' );
			if ( '' !== $circuito && $record_circuito !== $circuito ) {
				continue;
			}
			yield $record;
		}
		$page++;
	} while ( 200 === count( $ids ) );
}

/**
 * Limpia cualquier salida accidental antes de iniciar una descarga.
 *
 * @return void
 */
function gnf_prepare_file_download_response() {
	while ( ob_get_level() > 0 ) {
		ob_end_clean();
	}
	if ( function_exists( 'nocache_headers' ) ) {
		nocache_headers();
	}
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 0 );
	}
}

/**
 * Genera el XLSX con el escritor autocontenido del plugin.
 *
 * @param int    $anio       Año.
 * @param int    $region_id  DRE.
 * @param string $circuito   Circuito.
 * @param string $output_path Ruta temporal de salida.
 * @return void
 */
function gnf_export_centros_xlsx_fallback( $anio, $region_id, $circuito, $output_path ) {
	if ( ! class_exists( 'GNF_XLSX_Writer' ) ) {
		$writer_path = __DIR__ . '/xlsx-writer.php';
		if ( file_exists( $writer_path ) ) {
			require_once $writer_path;
		}
	}
	if ( ! class_exists( 'GNF_XLSX_Writer' ) ) {
		throw new RuntimeException( 'No se pudo cargar el escritor XLSX autocontenido.' );
	}

	$writer = new GNF_XLSX_Writer( $output_path, 'Centros Educativos' );
	$writer->add_row( gnf_centros_export_headers(), true );
	foreach ( gnf_iter_centros_export_records( $anio, $region_id, $circuito ) as $record ) {
		$writer->add_row( gnf_centros_export_row( $record ) );
	}
	$writer->close();
}

/**
 * Genera el XLSX completo en un archivo temporal.
 *
 * @param int    $anio       Año.
 * @param int    $region_id  DRE.
 * @param string $circuito   Circuito.
 * @param string $output_path Ruta temporal de salida.
 * @return void
 */
function gnf_generate_centros_xlsx_file( $anio, $region_id, $circuito, $output_path ) {
	if ( PHP_VERSION_ID >= 80200 || ! class_exists( '\OpenSpout\Writer\Common\Creator\WriterEntityFactory' ) ) {
		gnf_export_centros_xlsx_fallback( $anio, $region_id, $circuito, $output_path );
		return;
	}

	$writer = \OpenSpout\Writer\Common\Creator\WriterEntityFactory::createXLSXWriter();
	$writer->openToFile( $output_path );
	$writer->getCurrentSheet()->setName( 'Centros Educativos' );
	$writer->addRow( \OpenSpout\Writer\Common\Creator\WriterEntityFactory::createRowFromArray( gnf_centros_export_headers() ) );
	foreach ( gnf_iter_centros_export_records( $anio, $region_id, $circuito ) as $record ) {
		$writer->addRow( \OpenSpout\Writer\Common\Creator\WriterEntityFactory::createRowFromArray( gnf_centros_export_row( $record ) ) );
	}
	$writer->close();
}

/**
 * Envía el XLSX completo al navegador.
 *
 * @param int    $anio      Año.
 * @param int    $region_id DRE.
 * @param string $circuito  Circuito.
 * @return void
 */
function gnf_export_centros_xlsx( $anio, $region_id = 0, $circuito = '' ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Sin permisos.', 403 );
	}

	$previous_error_reporting = error_reporting();
	error_reporting( $previous_error_reporting & ~E_DEPRECATED & ~E_USER_DEPRECATED );
	$temp_path = tempnam( sys_get_temp_dir(), 'gnf-centros-' );
	$filename  = sprintf( 'centros-inscritos-pilotaje-%d.xlsx', absint( $anio ) );

	try {
		if ( false === $temp_path ) {
			throw new RuntimeException( 'No se pudo reservar el archivo temporal XLSX.' );
		}

		gnf_generate_centros_xlsx_file( $anio, $region_id, $circuito, $temp_path );
		if ( ! is_file( $temp_path ) || 0 === filesize( $temp_path ) ) {
			throw new RuntimeException( 'El archivo XLSX quedó vacío.' );
		}

		gnf_prepare_file_download_response();
		header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . filesize( $temp_path ) );
		readfile( $temp_path );
	} catch ( Throwable $error ) {
		error_log( '[Guardianes] Error al exportar centros XLSX: ' . $error->getMessage() );
		if ( false !== $temp_path && is_file( $temp_path ) ) {
			@unlink( $temp_path );
		}
		error_reporting( $previous_error_reporting );
		wp_die( 'El servidor no pudo generar el archivo XLSX.', 500 );
	}

	if ( false !== $temp_path && is_file( $temp_path ) ) {
		@unlink( $temp_path );
	}
	error_reporting( $previous_error_reporting );
	exit;
}

/**
 * URL firmada del endpoint XLSX.
 *
 * @param int    $anio      Año.
 * @param int    $region_id DRE.
 * @param string $circuito  Circuito.
 * @return string
 */
function gnf_get_centros_xlsx_export_url( $anio, $region_id = 0, $circuito = '' ) {
	$args = array(
		'action' => 'gnf_export_centros_xlsx',
		'year'   => function_exists( 'gnf_normalize_year' ) ? gnf_normalize_year( $anio ) : absint( $anio ),
	);
	if ( $region_id ) {
		$args['region'] = absint( $region_id );
	}
	if ( '' !== trim( (string) $circuito ) ) {
		$args['circuito'] = (string) $circuito;
	}

	return wp_nonce_url(
		add_query_arg( $args, admin_url( 'admin-post.php' ) ),
		'gnf_export_centros_xlsx'
	);
}
