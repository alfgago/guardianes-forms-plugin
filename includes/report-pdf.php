<?php
/**
 * Reporte final PDF por centro educativo.
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

/**
 * Determina el estado del documento a partir de los retos seleccionados.
 *
 * @param int[] $selected_ids IDs seleccionados.
 * @param array $entry_states Estados indexados por reto.
 * @return string
 */
function gnf_center_report_status_from_entries( $selected_ids, $entry_states ) {
	$selected_ids = array_values( array_unique( array_filter( array_map( 'intval', (array) $selected_ids ) ) ) );
	if ( empty( $selected_ids ) ) {
		return 'draft';
	}
	foreach ( $selected_ids as $reto_id ) {
		if ( 'aprobado' !== (string) ( $entry_states[ $reto_id ] ?? '' ) ) {
			return 'draft';
		}
	}
	return 'final';
}

/**
 * Consulta el estado vigente del reporte de un centro.
 */
function gnf_get_center_report_status( $centro_id, $anio ) {
	global $wpdb;
	$selected = function_exists( 'gnf_get_centro_retos_seleccionados' ) ? gnf_get_centro_retos_seleccionados( $centro_id, $anio ) : array();
	$table    = $wpdb->prefix . 'gn_reto_entries';
	$rows     = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT reto_id, estado FROM {$table} WHERE centro_id = %d AND anio = %d",
			absint( $centro_id ),
			absint( $anio )
		)
	);
	$states = array();
	foreach ( (array) $rows as $row ) {
		$states[ (int) $row->reto_id ] = (string) $row->estado;
	}
	return gnf_center_report_status_from_entries( $selected, $states );
}

/**
 * Escapa texto para la plantilla sin depender de WordPress.
 *
 * @param mixed $value Valor.
 * @return string
 */
function gnf_report_html_escape( $value ) {
	return htmlspecialchars( (string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
}

/**
 * Admite únicamente enlaces HTTP(S) en el documento descargable.
 *
 * @param mixed $value URL.
 * @return string
 */
function gnf_report_safe_url( $value ) {
	$value  = trim( (string) $value );
	$scheme = '' !== $value ? strtolower( (string) parse_url( $value, PHP_URL_SCHEME ) ) : '';
	return in_array( $scheme, array( 'http', 'https' ), true ) ? $value : '';
}

function gnf_report_safe_image_data_uri( $value ) {
	$value = trim( (string) $value );
	return preg_match( '#^data:image/(?:jpeg|png|gif|webp);base64,[a-z0-9+/=\r\n]+$#i', $value ) ? $value : '';
}

/**
 * Convierte valores almacenados a texto legible.
 *
 * @param mixed $value Valor.
 * @return string
 */
function gnf_report_format_value( $value ) {
	if ( is_bool( $value ) ) {
		return $value ? 'Sí' : 'No';
	}
	if ( is_array( $value ) ) {
		$items = array();
		foreach ( $value as $item ) {
			if ( is_scalar( $item ) && '' !== trim( (string) $item ) ) {
				$items[] = trim( (string) $item );
			}
		}
		return implode( '; ', $items );
	}
	if ( is_scalar( $value ) ) {
		return trim( (string) $value );
	}
	return '';
}

/**
 * Formatea fechas ISO o MySQL para lectura humana.
 *
 * @param mixed $value Fecha.
 * @param bool  $with_time Incluir hora.
 * @return string
 */
function gnf_report_format_date( $value, $with_time = false ) {
	$value = trim( (string) $value );
	if ( '' === $value ) {
		return 'No disponible';
	}
	$timestamp = strtotime( $value );
	if ( false === $timestamp ) {
		return $value;
	}
	return date( $with_time ? 'd/m/Y H:i' : 'd/m/Y', $timestamp );
}

/**
 * Etiqueta de estados de reto o evidencia.
 *
 * @param string $status Estado.
 * @return string
 */
function gnf_report_status_label( $status ) {
	$labels = array(
		'aprobado'       => 'Aprobado',
		'aprobada'       => 'Aprobada',
		'enviado'        => 'En revisión',
		'pendiente'      => 'Pendiente',
		'correccion'     => 'Requiere corrección',
		'rechazada'      => 'Rechazada',
		'en_progreso'    => 'En progreso',
		'completo'       => 'Completado por el centro',
		'no_iniciado'    => 'No iniciado',
		'sin_evidencias' => 'Sin evidencias',
	);
	$status = trim( (string) $status );
	return $labels[ $status ] ?? ( '' !== $status ? ucfirst( str_replace( '_', ' ', $status ) ) : 'Sin estado' );
}

/**
 * Etiqueta de la tipificación de rechazo.
 *
 * @param string $reason Motivo.
 * @return string
 */
function gnf_report_rejection_reason_label( $reason ) {
	$reason = trim( (string) $reason );
	if ( function_exists( 'gnf_get_evidence_rejection_reason_label' ) ) {
		$label = gnf_get_evidence_rejection_reason_label( $reason );
		if ( '' !== $label ) {
			return rtrim( $label, '.' );
		}
	}
	$labels = array(
		'no_corresponde'       => 'Evidencia no corresponde',
		'otra_accion_reto'     => 'Evidencia de otra acción o reto',
		'evidencia_otro_reto'  => 'Evidencia de otra acción o reto',
		'ya_valorada'          => 'Evidencia ya valorada',
		'accion_no_amigable'   => 'Acción debe ser amigable',
	);
	return $labels[ $reason ] ?? '';
}

/**
 * Convierte una imagen local validada en data URI para Dompdf.
 *
 * @param string $path Ruta local.
 * @return string
 */
function gnf_report_image_data_uri( $path ) {
	$path = trim( (string) $path );
	if ( '' === $path || ! file_exists( $path ) || ! is_readable( $path ) ) {
		return '';
	}

	$real_path = realpath( $path );
	if ( false === $real_path ) {
		return '';
	}

	if ( function_exists( 'wp_upload_dir' ) ) {
		$uploads   = wp_upload_dir();
		$real_base = ! empty( $uploads['basedir'] ) ? realpath( $uploads['basedir'] ) : false;
		if ( false === $real_base || 0 !== strpos( str_replace( '\\', '/', $real_path ), rtrim( str_replace( '\\', '/', $real_base ), '/' ) . '/' ) ) {
			return '';
		}
	}

	$size = filesize( $real_path );
	if ( false === $size || $size > 6 * 1024 * 1024 ) {
		return '';
	}

	$mime = '';
	if ( function_exists( 'mime_content_type' ) ) {
		$mime = (string) mime_content_type( $real_path );
	}
	$allowed = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );
	if ( ! in_array( $mime, $allowed, true ) ) {
		return '';
	}
	$dimensions = @getimagesize( $real_path );
	if ( ! is_array( $dimensions ) || empty( $dimensions[0] ) || empty( $dimensions[1] ) || (int) $dimensions[0] > 8000 || (int) $dimensions[1] > 8000 || (int) $dimensions[0] * (int) $dimensions[1] > 20000000 ) {
		return '';
	}

	$content = file_get_contents( $real_path );
	return false === $content ? '' : 'data:' . $mime . ';base64,' . base64_encode( $content );
}

/**
 * Construye filas legibles con todos los valores guardados en matrícula.
 *
 * @param array $data Datos JSON de matrícula.
 * @return array<int,array{label:string,value:string}>
 */
function gnf_report_build_matricula_fields( $data ) {
	$definitions = function_exists( 'gnf_get_matricula_field_definitions' ) ? gnf_get_matricula_field_definitions() : array();
	$rows        = array();

	foreach ( (array) $data as $raw_key => $raw_value ) {
		$key = str_replace( '-', '_', (string) $raw_key );
		if ( 'bae_meta_estrellas' === $key || preg_match( '/password|contrasena|token|nonce|secret/i', $key ) ) {
			continue;
		}
		$definition = $definitions[ $key ] ?? array();
		$type       = (string) ( $definition['type'] ?? '' );
		if ( in_array( $type, array( 'tab', 'message', 'accordion' ), true ) ) {
			continue;
		}

		$value = $raw_value;
		if ( ! empty( $definition['choices'] ) && is_array( $definition['choices'] ) ) {
			$choice_values = is_array( $value ) ? $value : array( $value );
			$value         = array_map(
				static function ( $choice ) use ( $definition ) {
					$choice = (string) $choice;
					return $definition['choices'][ $choice ] ?? $choice;
				},
				$choice_values
			);
		}

		if ( in_array( $key, array( 'bae_retos_seleccionados', 'centro_id_existente' ), true ) ) {
			$ids   = is_array( $raw_value ) ? $raw_value : array( $raw_value );
			$names = array();
			foreach ( $ids as $id ) {
				$post = function_exists( 'get_post' ) ? get_post( absint( $id ) ) : null;
				$names[] = $post ? $post->post_title : (string) $id;
			}
			$value = $names;
		}

		$value = gnf_report_format_value( $value );
		if ( '' === $value ) {
			continue;
		}
		$label = trim( (string) ( $definition['label'] ?? '' ) );
		if ( '' === $label ) {
			$label = ucwords( str_replace( '_', ' ', $key ) );
		}
		$rows[] = array( 'label' => $label, 'value' => $value );
	}

	return $rows;
}

/**
 * Construye el DTO completo del reporte final.
 *
 * @param int $centro_id Centro educativo.
 * @param int $anio Año.
 * @return array|WP_Error
 */
function gnf_build_center_report_data( $centro_id, $anio ) {
	global $wpdb;
	$centro_id = absint( $centro_id );
	$anio      = function_exists( 'gnf_normalize_year' ) ? gnf_normalize_year( $anio ) : absint( $anio );
	$post      = get_post( $centro_id );
	if ( ! $post || 'centro_educativo' !== $post->post_type ) {
		return new WP_Error( 'center_not_found', 'Centro educativo no encontrado.' );
	}

	$batch  = gnf_build_centros_export_batch_maps( array( $centro_id ), $anio );
	$center = gnf_build_centro_export_record( $centro_id, $anio, $batch );
	$row    = $batch['matriculas'][ $centro_id ] ?? array();
	$raw    = ! empty( $row['data'] ) ? json_decode( (string) $row['data'], true ) : array();
	$center['matriculaFields'] = gnf_report_build_matricula_fields( is_array( $raw ) ? $raw : array() );

	$table       = $wpdb->prefix . 'gn_reto_entries';
	$entries_raw = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE centro_id = %d AND anio = %d ORDER BY updated_at ASC, id ASC",
			$centro_id,
			$anio
		)
	);
	$by_reto     = array();
	$entry_states = array();
	foreach ( (array) $entries_raw as $entry ) {
		$by_reto[ (int) $entry->reto_id ] = $entry;
		$entry_states[ (int) $entry->reto_id ] = (string) $entry->estado;
	}

	$selected = array_map( 'absint', (array) gnf_get_centro_retos_seleccionados( $centro_id, $anio ) );
	$reto_ids = array_values( array_unique( array_merge( $selected, array_keys( $by_reto ) ) ) );
	$retos    = array();
	$image_bytes = 0;
	$image_limit = 32 * 1024 * 1024;
	foreach ( $reto_ids as $reto_id ) {
		$reto  = get_post( $reto_id );
		$entry = $by_reto[ $reto_id ] ?? null;
		if ( $entry ) {
			$formatted  = gnf_format_reto_entry( $entry, $anio );
			$evidencias = ! empty( $entry->evidencias ) ? json_decode( $entry->evidencias, true ) : array();
			$evidencias = gnf_enrich_evidencias( is_array( $evidencias ) ? $evidencias : array(), $reto_id, $anio );
			$field_labels = array();
			foreach ( (array) ( $formatted['responses'] ?? array() ) as $response ) {
				$field_labels[ absint( $response['fieldId'] ?? 0 ) ] = (string) ( $response['label'] ?? '' );
			}
			foreach ( $evidencias as &$evidencia ) {
				$field_id                    = absint( $evidencia['field_id'] ?? 0 );
				$evidencia['questionLabel']  = $field_labels[ $field_id ] ?? sprintf( 'Evidencia del campo %d', $field_id );
				$evidencia['url']            = (string) ( $evidencia['ruta'] ?? $evidencia['url'] ?? '' );
				$image_data = gnf_report_image_data_uri( $evidencia['path_local'] ?? '' );
				$image_size = $image_data ? (int) floor( strlen( $image_data ) * 0.75 ) : 0;
				if ( $image_size && $image_bytes + $image_size <= $image_limit ) {
					$evidencia['imageDataUri'] = $image_data;
					$image_bytes += $image_size;
				} else {
					$evidencia['imageDataUri'] = '';
				}
			}
			unset( $evidencia );
			$formatted['evidencias'] = $evidencias;
			$retos[] = $formatted;
			continue;
		}

		$retos[] = array(
			'id'              => 0,
			'retoId'          => $reto_id,
			'retoTitulo'      => $reto ? $reto->post_title : sprintf( 'Reto %d', $reto_id ),
			'titulo'          => $reto ? $reto->post_title : sprintf( 'Reto %d', $reto_id ),
			'estado'          => 'sin_evidencias',
			'puntaje'         => 0,
			'puntajeMaximo'   => gnf_get_reto_max_points( $reto_id, $anio ),
			'supervisorNotes' => '',
			'responses'       => array(),
			'evidencias'      => array(),
			'updatedAt'       => null,
		);
	}

	return array(
		'year'        => $anio,
		'generatedAt' => function_exists( 'current_time' ) ? current_time( 'mysql' ) : gmdate( 'Y-m-d H:i:s' ),
		'status'      => gnf_center_report_status_from_entries( $selected, $entry_states ),
		'center'      => $center,
		'award'       => array(
			'projected' => function_exists( 'gnf_get_center_award_result' ) ? gnf_get_center_award_result( $centro_id, $anio, 'projected' ) : array(),
			'validated' => function_exists( 'gnf_get_center_award_result' ) ? gnf_get_center_award_result( $centro_id, $anio, 'validated' ) : array(),
		),
		'retos'       => $retos,
	);
}

/**
 * Renderiza una tabla de pares etiqueta/valor.
 *
 * @param array $rows Filas.
 * @return string
 */
function gnf_report_render_pairs( $rows ) {
	$html = '<table class="pairs"><tbody>';
	foreach ( (array) $rows as $row ) {
		$value = gnf_report_format_value( $row[1] ?? '' );
		if ( '' === $value ) {
			$value = 'No registrado';
		}
		$html .= '<tr><th>' . gnf_report_html_escape( $row[0] ?? '' ) . '</th><td>' . nl2br( gnf_report_html_escape( $value ) ) . '</td></tr>';
	}
	return $html . '</tbody></table>';
}

/**
 * Renderiza el HTML completo del reporte.
 *
 * @param array $report Datos del reporte.
 * @return string
 */
function gnf_render_center_report_html( $report ) {
	$center    = (array) ( $report['center'] ?? array() );
	$projected = (array) ( $report['award']['projected'] ?? array() );
	$validated = (array) ( $report['award']['validated'] ?? array() );
	$retos     = (array) ( $report['retos'] ?? array() );
	$year      = (int) ( $report['year'] ?? 0 );
	$name      = (string) ( $center['nombre'] ?? 'Centro educativo' );
	$emails    = gnf_report_format_value( $center['contact_emails'] ?? array() );
	$status    = 'final' === (string) ( $report['status'] ?? '' ) ? 'final' : 'draft';
	$title     = 'final' === $status ? 'Reporte final de participación' : 'Borrador de participación';

	$center_rows = array(
		array( 'Código MEP', $center['codigo_mep'] ?? '' ),
		array( 'Código presupuestario', $center['codigo_presupuestario'] ?? '' ),
		array( 'Dirección Regional', $center['region_name'] ?? '' ),
		array( 'Circuito educativo', $center['circuito'] ?? '' ),
		array( 'Provincia', $center['provincia'] ?? '' ),
		array( 'Cantón', $center['canton'] ?? '' ),
		array( 'Distrito', $center['distrito'] ?? '' ),
		array( 'Poblado', $center['poblado'] ?? '' ),
		array( 'Dirección exacta', $center['direccion'] ?? '' ),
		array( 'Dependencia', $center['dependencia'] ?? '' ),
		array( 'Zona', $center['zona'] ?? '' ),
		array( 'Nivel educativo', $center['nivel_educativo'] ?? '' ),
		array( 'Jornada', $center['jornada'] ?? '' ),
		array( 'Tipo de centro', $center['tipo_centro'] ?? '' ),
		array( 'Tipología', $center['tipologia'] ?? '' ),
		array( 'Teléfono', $center['telefono'] ?? '' ),
		array( 'Teléfono alterno', $center['telefono2'] ?? '' ),
		array( 'Correo institucional', $center['correo_institucional'] ?? '' ),
	);
	$matricula_rows = array(
		array( 'Año de participación', $year ),
		array( 'Estado de matrícula', gnf_report_status_label( $center['matricula_estado'] ?? '' ) ),
		array( 'Total de estudiantes', $center['total_estudiantes'] ?? 0 ),
		array( 'Estudiantes hombres', $center['estudiantes_hombres'] ?? 0 ),
		array( 'Estudiantes mujeres', $center['estudiantes_mujeres'] ?? 0 ),
		array( 'Estudiantes migrantes', $center['estudiantes_migrantes'] ?? 0 ),
		array( 'Estudiantes del Comité BAE', $center['comite_estudiantes'] ?? 0 ),
		array( 'Coordinación PBAE', $center['coordinador_nombre'] ?? '' ),
		array( 'Cargo de coordinación', $center['coordinador_cargo'] ?? '' ),
		array( 'Teléfono de coordinación', $center['coordinador_telefono'] ?? '' ),
		array( 'Persona que inscribió', $center['matricula_docente_nombre'] ?? '' ),
		array( 'Cargo de quien inscribió', $center['matricula_docente_cargo'] ?? '' ),
		array( 'Teléfono de quien inscribió', $center['matricula_docente_telefono'] ?? '' ),
		array( 'Correos de contacto', $emails ),
		array( 'Fecha de matrícula', gnf_report_format_date( $center['matricula_created_at'] ?? '', true ) ),
		array( 'Última actualización', gnf_report_format_date( $center['matricula_updated_at'] ?? '', true ) ),
	);

	$special_awards = array();
	foreach ( (array) ( $validated['awards'] ?? array() ) as $award ) {
		if ( ! empty( $award['achieved'] ) ) {
			$special_awards[] = (string) ( $award['label'] ?? '' );
		}
	}
	$missing_required = array_map(
		static function ( $item ) {
			$labels = array( 'agua' => 'Agua', 'electricidad' => 'Energía', 'residuos' => 'Residuos' );
			return $labels[ $item ] ?? ucfirst( (string) $item );
		},
		(array) ( $validated['missingRequired'] ?? array() )
	);

	$html = '<!doctype html><html lang="es"><head><meta charset="utf-8"><style>';
	$html .= '@page{margin:18mm 14mm 17mm;}*{box-sizing:border-box}body{font-family:"DejaVu Sans",sans-serif;color:#24342e;font-size:9.5pt;line-height:1.42;margin:0}.footer{position:fixed;bottom:-11mm;left:0;right:0;border-top:1px solid #d7dfdb;padding-top:4px;color:#69766f;font-size:7.5pt}.cover{page-break-after:always;padding-top:12mm}.brand{color:#176b55;font-size:10pt;font-weight:bold;text-transform:uppercase;letter-spacing:.6px}.document-status{display:inline-block;margin-bottom:12px;padding:5px 8px;border:1px solid #d6b56b;background:#fff8e7;color:#7c5709;font-size:8pt;font-weight:bold;text-transform:uppercase}.accent{width:44px;height:5px;background:#e8b63d;margin:11px 0 20px}.cover h1{font-size:29pt;line-height:1.12;margin:0 0 8px;color:#173f35}.cover h2{font-size:15pt;margin:0;color:#50645c;font-weight:normal}.cover-meta{margin-top:20mm;border-left:5px solid #db654f;padding:10px 14px;background:#f5f7f6}.score-grid{margin-top:12mm;width:100%;border-collapse:separate;border-spacing:8px}.score-grid td{width:33.33%;border:1px solid #d7dfdb;padding:12px;background:#fff}.score-number{font-size:23pt;font-weight:bold;color:#176b55}.score-label{font-size:8pt;color:#69766f;text-transform:uppercase}.section{margin:0 0 14px}.section-title{font-size:15pt;color:#173f35;border-bottom:2px solid #e8b63d;padding-bottom:5px;margin:0 0 9px}.pairs{width:100%;border-collapse:collapse}.pairs th,.pairs td{border-bottom:1px solid #e5eae7;padding:5px 6px;vertical-align:top}.pairs th{width:35%;text-align:left;color:#52635c;background:#f6f8f7;font-weight:600}.pairs td{overflow-wrap:anywhere}.award-box{border:1px solid #cbd8d2;background:#f4f8f6;padding:10px 12px;margin-bottom:14px}.award-grid{width:100%;border-collapse:collapse}.award-grid td{width:33.33%;text-align:center;padding:7px;border-right:1px solid #d7dfdb}.award-grid td:last-child{border-right:0}.award-value{font-size:18pt;font-weight:bold;color:#176b55}.reto{page-break-before:auto;margin:0 0 16px}.reto-head{background:#173f35;color:#fff;padding:9px 11px}.reto-title{font-size:13pt;font-weight:bold}.reto-meta{font-size:8.5pt;margin-top:3px;color:#dce9e4}.response-table{width:100%;border-collapse:collapse;margin-top:8px}.response-table th,.response-table td{border:1px solid #dfe6e2;padding:5px;text-align:left;vertical-align:top}.response-table th{background:#f2f6f4}.evidence{page-break-inside:avoid;border:1px solid #d7dfdb;margin-top:8px;padding:8px}.evidence.rejected{border-left:4px solid #db654f}.evidence.approved{border-left:4px solid #176b55}.evidence.pending{border-left:4px solid #e8b63d}.evidence img{display:block;max-width:190px;max-height:135px;margin:7px 0;border:1px solid #d7dfdb}.evidence-name{font-weight:bold;color:#173f35;overflow-wrap:anywhere}.muted{color:#69766f}.note{background:#fff7e4;padding:6px 8px;margin-top:6px}.empty{color:#69766f;font-style:italic;padding:8px 0}.page-break{page-break-before:always}.raw-fields{font-size:8.5pt}.raw-fields tr{page-break-inside:avoid}</style></head><body>';
	$html .= '<div class="footer">Movimiento Guardianes de la Naturaleza · Reporte ' . gnf_report_html_escape( $year ) . ' · Generado ' . gnf_report_html_escape( gnf_report_format_date( $report['generatedAt'] ?? '', true ) ) . '</div>';
	$html .= '<section class="cover"><div class="brand">Bandera Azul Ecológica</div><div class="accent"></div>';
	if ( 'draft' === $status ) {
		$html .= '<div class="document-status">Documento preliminar · Pendiente de validación</div>';
	}
	$html .= '<h1>' . gnf_report_html_escape( $title ) . '</h1><h2>' . gnf_report_html_escape( $name ) . '</h2>';
	$html .= '<div class="cover-meta"><strong>Participación ' . gnf_report_html_escape( $year ) . '</strong><br>' . gnf_report_html_escape( $center['region_name'] ?? '' ) . ' · Circuito ' . gnf_report_html_escape( $center['circuito'] ?? '' ) . '<br>Código MEP: ' . gnf_report_html_escape( $center['codigo_mep'] ?? '' ) . '</div>';
	$html .= '<table class="score-grid"><tr><td><div class="score-number">' . gnf_report_html_escape( $validated['score'] ?? $center['puntaje_total'] ?? 0 ) . '</div><div class="score-label">Puntaje validado</div></td><td><div class="score-number">' . gnf_report_html_escape( $validated['stars'] ?? $center['estrella_final'] ?? 0 ) . '</div><div class="score-label">Estrellas validadas</div></td><td><div class="score-number">' . count( $retos ) . '</div><div class="score-label">Eco retos inscritos</div></td></tr></table></section>';
	$html .= '<section class="section"><h2 class="section-title">Información del centro educativo</h2>' . gnf_report_render_pairs( $center_rows ) . '</section>';
	$html .= '<section class="section"><h2 class="section-title">Matrícula y contacto</h2>' . gnf_report_render_pairs( $matricula_rows ) . '</section>';
	$html .= '<section class="section"><h2 class="section-title">Galardón calculado</h2><div class="award-box"><table class="award-grid"><tr><td><div class="award-value">' . gnf_report_html_escape( $projected['score'] ?? 0 ) . '</div><div class="score-label">Puntaje con evidencia activa</div></td><td><div class="award-value">' . gnf_report_html_escape( $validated['score'] ?? 0 ) . '</div><div class="score-label">Puntaje validado</div></td><td><div class="award-value">' . gnf_report_html_escape( $validated['stars'] ?? 0 ) . '</div><div class="score-label">Estrellas validadas</div></td></tr></table>';
	$html .= '<p><strong>Rúbrica:</strong> ' . gnf_report_html_escape( $validated['rubricLabel'] ?? $projected['rubricLabel'] ?? 'No determinada' ) . '</p>';
	if ( $missing_required ) {
		$html .= '<p><strong>Requisitos base pendientes:</strong> ' . gnf_report_html_escape( implode( ', ', $missing_required ) ) . '.</p>';
	}
	if ( $special_awards ) {
		$html .= '<p><strong>Reconocimientos adicionales:</strong> ' . gnf_report_html_escape( implode( ', ', $special_awards ) ) . '.</p>';
	}
	$html .= '</div></section>';

	if ( ! empty( $center['matriculaFields'] ) ) {
		$raw_rows = array();
		foreach ( $center['matriculaFields'] as $field ) {
			$raw_rows[] = array( $field['label'] ?? '', $field['value'] ?? '' );
		}
		$html .= '<section class="section raw-fields"><h2 class="section-title">Registro completo de matrícula</h2>' . gnf_report_render_pairs( $raw_rows ) . '</section>';
	}

	$html .= '<div class="page-break"></div><section class="section"><h2 class="section-title">Participación por eco reto</h2></section>';
	if ( empty( $retos ) ) {
		$html .= '<p class="empty">No hay eco retos registrados para este período.</p>';
	}
	foreach ( $retos as $reto ) {
		$title     = (string) ( $reto['retoTitulo'] ?? $reto['titulo'] ?? 'Eco reto' );
		$score     = (int) ( $reto['puntaje'] ?? 0 );
		$max_score = (int) ( $reto['puntajeMaximo'] ?? 0 );
		$html .= '<section class="reto"><div class="reto-head"><div class="reto-title">' . gnf_report_html_escape( $title ) . '</div><div class="reto-meta">' . gnf_report_html_escape( $score . ' / ' . $max_score . ' puntos' ) . ' · ' . gnf_report_html_escape( gnf_report_status_label( $reto['estado'] ?? '' ) ) . '</div></div>';
		$responses = array_values(
			array_filter(
				(array) ( $reto['responses'] ?? array() ),
				static function ( $response ) {
					return ! empty( $response['hasValue'] ) && '' !== gnf_report_format_value( $response['displayValue'] ?? '' );
				}
			)
		);
		if ( $responses ) {
			$html .= '<table class="response-table"><thead><tr><th>Dato registrado</th><th>Respuesta</th><th>Puntos</th></tr></thead><tbody>';
			foreach ( $responses as $response ) {
				$html .= '<tr><td>' . gnf_report_html_escape( $response['label'] ?? '' ) . '</td><td>' . nl2br( gnf_report_html_escape( gnf_report_format_value( $response['displayValue'] ?? '' ) ) ) . '</td><td>' . gnf_report_html_escape( $response['puntos'] ?? 0 ) . '</td></tr>';
			}
			$html .= '</tbody></table>';
		}

		$evidences = (array) ( $reto['evidencias'] ?? array() );
		if ( empty( $evidences ) ) {
			$html .= '<p class="empty">No hay evidencias cargadas.</p>';
		}
		foreach ( $evidences as $evidence ) {
			$status = (string) ( $evidence['estado'] ?? 'pendiente' );
			$class  = 'aprobada' === $status ? 'approved' : ( 'rechazada' === $status ? 'rejected' : 'pending' );
			if ( ! empty( $evidence['replaced'] ) ) {
				$class = 'rejected';
			}
			$name_value = (string) ( $evidence['nombre'] ?? $evidence['filename'] ?? 'Archivo de evidencia' );
			$url        = gnf_report_safe_url( $evidence['url'] ?? $evidence['ruta'] ?? '' );
			$html .= '<div class="evidence ' . $class . '"><div class="muted">' . gnf_report_html_escape( $evidence['questionLabel'] ?? 'Evidencia' ) . '</div><div class="evidence-name">' . gnf_report_html_escape( $name_value ) . '</div>';
			$html .= '<div><strong>Estado:</strong> ' . gnf_report_html_escape( ! empty( $evidence['replaced'] ) ? 'Reemplazada por el centro' : gnf_report_status_label( $status ) ) . ' · <strong>Puntos:</strong> ' . gnf_report_html_escape( $evidence['puntos'] ?? 0 ) . '</div>';
			$html .= '<div><strong>Fecha original:</strong> ' . gnf_report_html_escape( gnf_report_format_date( $evidence['original_date'] ?? $evidence['photo_date'] ?? '' ) ) . '</div>';
			$image_data = gnf_report_safe_image_data_uri( $evidence['imageDataUri'] ?? '' );
			if ( '' !== $image_data ) {
				$html .= '<img src="' . gnf_report_html_escape( $image_data ) . '" alt="Vista previa">';
			}
			if ( '' !== $url ) {
				$html .= '<div><a href="' . gnf_report_html_escape( $url ) . '">Abrir archivo original</a></div>';
			}
			$reason = gnf_report_rejection_reason_label( $evidence['review_reason'] ?? '' );
			if ( '' !== $reason ) {
				$html .= '<div class="note"><strong>Motivo:</strong> ' . gnf_report_html_escape( $reason ) . '</div>';
			}
			if ( ! empty( $evidence['supervisor_comment'] ) ) {
				$html .= '<div class="note"><strong>Comentario de revisión:</strong> ' . nl2br( gnf_report_html_escape( $evidence['supervisor_comment'] ) ) . '</div>';
			}
			$html .= '</div>';
		}
		if ( ! empty( $reto['supervisorNotes'] ) ) {
			$html .= '<div class="note"><strong>Comentario general del reto:</strong> ' . nl2br( gnf_report_html_escape( $reto['supervisorNotes'] ) ) . '</div>';
		}
		$html .= '</section>';
	}

	return $html . '</body></html>';
}

/**
 * Genera un PDF en una ruta temporal.
 *
 * @param array  $report Datos del reporte.
 * @param string $output_path Ruta destino.
 * @return true|WP_Error
 */
function gnf_generate_center_report_pdf( $report, $output_path ) {
	if ( ! class_exists( '\\Dompdf\\Dompdf' ) ) {
		$autoload = defined( 'GNF_PATH' ) ? GNF_PATH . 'vendor/autoload.php' : dirname( __DIR__ ) . '/vendor/autoload.php';
		if ( file_exists( $autoload ) ) {
			require_once $autoload;
		}
	}
	if ( ! class_exists( '\\Dompdf\\Dompdf' ) ) {
		return new WP_Error( 'pdf_library_missing', 'El generador PDF no está instalado.' );
	}

	$options = new \Dompdf\Options();
	$options->set( 'defaultFont', 'DejaVu Sans' );
	$options->set( 'isRemoteEnabled', false );
	$options->set( 'isHtml5ParserEnabled', true );
	$options->set( 'isPhpEnabled', false );
	$dompdf = new \Dompdf\Dompdf( $options );
	$dompdf->loadHtml( gnf_render_center_report_html( $report ), 'UTF-8' );
	$dompdf->setPaper( 'A4', 'portrait' );
	$dompdf->render();
	$canvas = $dompdf->getCanvas();
	$canvas->page_text( 500, 806, '{PAGE_NUM} / {PAGE_COUNT}', null, 8, array( 0.41, 0.46, 0.44 ) );
	$written = file_put_contents( $output_path, $dompdf->output() );
	return false === $written ? new WP_Error( 'pdf_write_failed', 'No se pudo escribir el reporte PDF.' ) : true;
}

/**
 * Determina si el usuario actual puede descargar el reporte del centro.
 *
 * @param int $centro_id Centro.
 * @return bool
 */
function gnf_user_can_download_center_report( $centro_id ) {
	$user_id = get_current_user_id();
	if ( $user_id <= 0 ) {
		return false;
	}
	if ( current_user_can( 'manage_options' ) ) {
		return true;
	}
	return gnf_user_can_access_centro( $user_id, absint( $centro_id ) )
		&& ( ! function_exists( 'gnf_feature_is_enabled_for_center' ) || gnf_feature_is_enabled_for_center( 'reports', $centro_id, false ) );
}

/**
 * URL firmada de descarga.
 *
 * @param int $centro_id Centro.
 * @param int $anio Año.
 * @return string
 */
function gnf_get_center_report_download_url( $centro_id, $anio ) {
	$centro_id = absint( $centro_id );
	$anio      = absint( $anio );
	if ( ! $centro_id || ! $anio || ! gnf_user_can_download_center_report( $centro_id ) ) {
		return '';
	}
	$url = add_query_arg(
		array(
			'action'    => 'gnf_download_centro_report_pdf',
			'centro_id' => $centro_id,
			'year'      => $anio,
		),
		admin_url( 'admin-post.php' )
	);
	return wp_nonce_url( $url, 'gnf_download_center_report_' . $centro_id . '_' . $anio );
}

/**
 * Procesa la descarga del reporte PDF.
 *
 * @return void
 */
function gnf_handle_download_center_report_pdf() {
	$centro_id = isset( $_GET['centro_id'] ) ? absint( $_GET['centro_id'] ) : 0;
	$anio      = isset( $_GET['year'] ) ? absint( $_GET['year'] ) : ( function_exists( 'gnf_get_active_year' ) ? gnf_get_active_year() : (int) gmdate( 'Y' ) );
	if ( ! $centro_id || ! gnf_user_can_download_center_report( $centro_id ) ) {
		wp_die( 'Sin permisos para descargar este reporte.', 'Acceso denegado', array( 'response' => 403 ) );
	}
	check_admin_referer( 'gnf_download_center_report_' . $centro_id . '_' . $anio );

	$report = gnf_build_center_report_data( $centro_id, $anio );
	if ( is_wp_error( $report ) ) {
		wp_die( esc_html( $report->get_error_message() ), 'No se pudo generar el reporte', array( 'response' => 500 ) );
	}
	$temp = wp_tempnam( 'gnf-reporte-' . $centro_id . '-' . $anio . '.pdf' );
	if ( ! $temp ) {
		wp_die( 'No se pudo crear el archivo temporal.', 'No se pudo generar el reporte', array( 'response' => 500 ) );
	}
	$result = gnf_generate_center_report_pdf( $report, $temp );
	if ( is_wp_error( $result ) ) {
		@unlink( $temp );
		wp_die( esc_html( $result->get_error_message() ), 'No se pudo generar el reporte', array( 'response' => 500 ) );
	}

	$post = get_post( $centro_id );
	$slug = function_exists( 'sanitize_title' ) ? sanitize_title( $post ? $post->post_title : 'centro-' . $centro_id ) : 'centro-' . $centro_id;
	if ( function_exists( 'gnf_prepare_file_download_response' ) ) {
		gnf_prepare_file_download_response();
	}
	header( 'Content-Type: application/pdf' );
	$status = gnf_get_center_report_status( $centro_id, $anio );
	header( 'Content-Disposition: attachment; filename="reporte-' . ( 'final' === $status ? 'final' : 'borrador' ) . '-' . $slug . '-' . $anio . '.pdf"' );
	header( 'Content-Length: ' . filesize( $temp ) );
	readfile( $temp );
	@unlink( $temp );
	exit;
}

if ( function_exists( 'add_action' ) ) {
	add_action( 'admin_post_gnf_download_centro_report_pdf', 'gnf_handle_download_center_report_pdf' );
}
