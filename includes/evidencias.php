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
	$upload_dir  = wp_upload_dir();
	$target_base = trailingslashit( $upload_dir['basedir'] ) . 'guardianes/' . $anio . '/' . $centro_id . '/' . $reto_id . '/';

	wp_mkdir_p( $target_base );

	foreach ( $fields as $field_id => $field ) {
		$type = isset( $field['type'] ) ? $field['type'] : '';
		if ( 'file-upload' !== $type && 'file' !== $type ) {
			continue;
		}

		$value = $field['value'];
		if ( empty( $value ) ) {
			continue;
		}

		$files = is_array( $value ) ? $value : array( $value );
		foreach ( $files as $file_path ) {
			// Normaliza path absoluto.
			$abs_path = ( false === strpos( $file_path, ABSPATH ) ) ? $upload_dir['basedir'] . '/' . ltrim( $file_path, '/' ) : $file_path;
			$abs_path = wp_normalize_path( $abs_path );

			if ( ! file_exists( $abs_path ) ) {
				continue;
			}

			$filename = wp_basename( $abs_path );
			$unique   = wp_unique_filename( $target_base, $filename );
			$dest     = $target_base . $unique;
			if ( $abs_path !== $dest ) {
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

			$evidence = array(
				'field_id'   => (int) $field_id,
				'tipo'       => $tipo,
				'ruta'       => str_replace( wp_normalize_path( $upload_dir['basedir'] ), $upload_dir['baseurl'], wp_normalize_path( $dest ) ),
				'nombre'     => $unique,
				'path_local' => $dest,
			);

			// Validación de fecha por EXIF (solo imágenes).
			if ( 'imagen' === $tipo ) {
				$metadata = wp_read_image_metadata( $dest );
				$year_ok  = false;
				if ( ! empty( $metadata['created_timestamp'] ) ) {
					$photo_year = (int) gmdate( 'Y', $metadata['created_timestamp'] );
					if ( $photo_year === (int) $anio ) {
						$year_ok = true;
					}
				}
				if ( ! $year_ok ) {
					$evidence['requires_year_validation'] = true;
					$evidence['warning']                  = 'Fecha de foto no coincide con el año activo ' . $anio;
					gnf_notify_invalid_photo_date( $centro_id, $dest );
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
	$region = get_post_meta( $centro_id, 'region', true );
	if ( empty( $region ) ) {
		$terms = wp_get_post_terms( $centro_id, 'gn_region', array( 'fields' => 'ids' ) );
		$region = $terms ? $terms[0] : '';
	}
	$supervisores = gnf_get_supervisores_by_region( $region );
	$mensaje      = 'Evidencia requiere validación de año: ' . wp_basename( $file_path );
	if ( $supervisores ) {
		foreach ( $supervisores as $sup ) {
			gnf_insert_notification( $sup->ID, 'invalid_photo_date', $mensaje, 'evidencia', $centro_id );
		}
	}
}
