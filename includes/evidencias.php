<?php
/**
 * Manejo de evidencias de archivos.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Procesa campos de WPForms y mueve archivos a carpeta dedicada.
 * Cada entrada de evidencia incluye 'field_id' para scoring por campo.
 */
function gnf_collect_evidencias( $fields, $anio, $centro_id, $reto_id ) {
	$evidencias  = array();
	$field_points_map = gnf_get_reto_field_points( $reto_id, $anio );
	$upload_dir  = wp_upload_dir();
	$target_base = trailingslashit( $upload_dir['basedir'] ) . 'guardianes/' . $anio . '/' . $centro_id . '/' . $reto_id . '/';
	$base_dir    = wp_normalize_path( $upload_dir['basedir'] );
	$base_url    = trailingslashit( $upload_dir['baseurl'] );

	wp_mkdir_p( $target_base );
	$target_base = wp_normalize_path( $target_base );

	foreach ( $fields as $field_id => $field ) {
		$type = isset( $field['type'] ) ? $field['type'] : '';
		if ( 'file-upload' !== $type && 'file' !== $type ) {
			continue;
		}

		$value = $field['value'];
		if ( empty( $value ) ) {
			continue;
		}

		// WPForms Modern Upload (Dropzone) stores file data as JSON in the
		// hidden input, e.g. [{"file":"hash.png","url":"https://..."}].
		// The autosave sends this JSON string. Parse it and extract URLs.
		$files = array();
		if ( is_array( $value ) ) {
			$files = $value;
		} elseif ( is_string( $value ) ) {
			$trimmed = trim( $value );
			if ( 0 === strpos( $trimmed, '[' ) || 0 === strpos( $trimmed, '{' ) ) {
				$parsed = json_decode( $trimmed, true );
				if ( is_array( $parsed ) ) {
					// Single object: wrap in array.
					if ( isset( $parsed['url'] ) || isset( $parsed['file'] ) ) {
						$parsed = array( $parsed );
					}
					foreach ( $parsed as $file_obj ) {
						if ( is_array( $file_obj ) && ! empty( $file_obj['url'] ) ) {
							$files[] = $file_obj['url'];
						} elseif ( is_array( $file_obj ) && ! empty( $file_obj['file'] ) ) {
							$files[] = 'wpforms/tmp/' . $file_obj['file'];
						} elseif ( is_string( $file_obj ) && '' !== $file_obj ) {
							$files[] = $file_obj;
						}
					}
				}
			}
			// If not JSON or parsing failed, treat as a plain path/URL.
			if ( empty( $files ) && '' !== $trimmed ) {
				$files = array( $trimmed );
			}
		}
		foreach ( $files as $file_path ) {
			$file_path = is_string( $file_path ) ? trim( $file_path ) : '';
			if ( '' === $file_path ) {
				continue;
			}

			// Normaliza path absoluto.
			if ( 0 === strpos( $file_path, $base_url ) ) {
				$abs_path = str_replace( $base_url, trailingslashit( $upload_dir['basedir'] ), $file_path );
			} elseif ( 0 === strpos( wp_normalize_path( $file_path ), $base_dir ) ) {
				$abs_path = $file_path;
			} elseif ( 0 === strpos( $file_path, 'http' ) ) {
				$parsed_path = wp_parse_url( $file_path, PHP_URL_PATH );
				$uploads_rel = wp_parse_url( $base_url, PHP_URL_PATH );
				if ( $parsed_path && $uploads_rel && 0 === strpos( $parsed_path, $uploads_rel ) ) {
					$relative = ltrim( substr( $parsed_path, strlen( $uploads_rel ) ), '/' );
					$abs_path = trailingslashit( $upload_dir['basedir'] ) . $relative;
				} else {
					$abs_path = $file_path;
				}
			} elseif ( false === strpos( $file_path, ABSPATH ) ) {
				$abs_path = trailingslashit( $upload_dir['basedir'] ) . ltrim( $file_path, '/' );
			} else {
				$abs_path = $file_path;
			}
			$abs_path = wp_normalize_path( $abs_path );

			if ( ! file_exists( $abs_path ) ) {
				continue;
			}

			$filename = wp_basename( $abs_path );
			if ( 0 === strpos( $abs_path, $target_base ) ) {
				$unique = wp_basename( $abs_path );
				$dest   = $abs_path;
			} else {
				$existing_dest = $target_base . $filename;
				if ( file_exists( $existing_dest ) && filesize( $existing_dest ) === filesize( $abs_path ) ) {
					$unique = $filename;
					$dest   = $existing_dest;
				} else {
					$unique = wp_unique_filename( $target_base, $filename );
					$dest   = $target_base . $unique;
				}
			}
			if ( $abs_path !== $dest && ! file_exists( $dest ) ) {
				copy( $abs_path, $dest );
			}

			// Valida MIME/extension.
			$ft = wp_check_filetype_and_ext( $dest, wp_basename( $dest ) );
			if ( empty( $ft['ext'] ) || empty( $ft['type'] ) ) {
				@unlink( $dest ); // phpcs:ignore
				continue;
			}

			$ext  = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
			$tipo = 'archivo';
			if ( in_array( $ext, array( 'jpg', 'jpeg', 'png', 'gif' ), true ) ) {
				$tipo = 'imagen';
			} elseif ( in_array( $ext, array( 'mp4', 'mov', 'avi' ), true ) ) {
				$tipo = 'video';
			} elseif ( 'pdf' === $ext ) {
				$tipo = 'pdf';
			}

			// Lookup field points for this evidence.
			$field_puntos = isset( $field_points_map[ (int) $field_id ] )
				? absint( $field_points_map[ (int) $field_id ]['puntos'] )
				: null;

			$evidence = array(
				'field_id'           => (int) $field_id,
				'tipo'               => $tipo,
				'ruta'               => str_replace( wp_normalize_path( $upload_dir['basedir'] ), $upload_dir['baseurl'], wp_normalize_path( $dest ) ),
				'nombre'             => $unique,
				'path_local'         => $dest,
				'puntos'             => $field_puntos,
				'estado'             => $field_puntos !== null ? 'pendiente' : null,
				'supervisor_comment' => null,
				'reviewed_by'        => null,
				'reviewed_at'        => null,
			);

			// EXIF date — always store if available. Auto-reject on year mismatch.
			if ( 'imagen' === $tipo ) {
				if ( ! function_exists( 'wp_read_image_metadata' ) ) {
					require_once ABSPATH . 'wp-admin/includes/image.php';
				}
				$metadata      = wp_read_image_metadata( $dest );
				$has_exif_date = ! empty( $metadata['created_timestamp'] );
				if ( $has_exif_date ) {
					$photo_date = gmdate( 'Y-m-d', $metadata['created_timestamp'] );
					$photo_year = (int) gmdate( 'Y', $metadata['created_timestamp'] );
					$evidence['photo_date'] = $photo_date;
					if ( $photo_year !== (int) $anio ) {
						$evidence['estado']             = 'rechazada';
						$evidence['supervisor_comment']  = sprintf(
							'Rechazada automáticamente: la fecha EXIF de la foto (%s) no corresponde al año activo (%d).',
							$photo_date,
							$anio
						);
						$evidence['reviewed_by']         = 0; // System.
						$evidence['reviewed_at']         = current_time( 'mysql' );
					}
				}
			}

			$evidencias[] = $evidence;
		}
	}

	return $evidencias;
}

/**
 * AJAX: descarga un archivo de evidencia si el usuario tiene permisos.
 */
function gnf_ajax_descargar_evidencia() {
	gnf_verify_ajax_nonce();
	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		wp_send_json_error( 'No autorizado' );
	}

	$centro_id = absint( $_GET['centro_id'] ?? 0 );
	$path      = isset( $_GET['file'] ) ? wp_normalize_path( base64_decode( sanitize_text_field( wp_unslash( $_GET['file'] ) ) ) ) : '';

	if ( ! $centro_id || empty( $path ) ) {
		wp_send_json_error( 'Archivo inválido' );
	}

	if ( ! gnf_user_can_access_centro( $user_id, $centro_id ) ) {
		wp_send_json_error( 'Sin permisos' );
	}

	$uploads_base = wp_normalize_path( wp_upload_dir()['basedir'] );
	if ( 0 !== strpos( $path, $uploads_base ) ) {
		wp_send_json_error( 'Ruta fuera de uploads' );
	}

	if ( ! file_exists( $path ) ) {
		wp_send_json_error( 'No encontrado' );
	}

	header( 'Content-Description: File Transfer' );
	header( 'Content-Type: application/octet-stream' );
	header( 'Content-Disposition: attachment; filename="' . basename( $path ) . '"' );
	header( 'Content-Length: ' . filesize( $path ) );
	readfile( $path ); // phpcs:ignore
	exit;
}
add_action( 'wp_ajax_gnf_descargar_evidencia', 'gnf_ajax_descargar_evidencia' );
add_action( 'wp_ajax_nopriv_gnf_descargar_evidencia', 'gnf_ajax_descargar_evidencia' );

/**
 * Dispara notificación para fotos sin fecha válida.
 */
function gnf_notify_invalid_photo_date( $centro_id, $file_path ) {
	// La notificación final para supervisión se emite una vez que existe
	// el reto entry, para incluir centro/reto exactos y evitar duplicados.
	// Este hook se conserva solo por compatibilidad y trazabilidad local.
	do_action( 'gnf_invalid_photo_date_detected', (int) $centro_id, wp_basename( (string) $file_path ) );
}
