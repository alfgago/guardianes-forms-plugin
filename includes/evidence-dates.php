<?php
/**
 * Extracción y normalización de fechas originales de evidencias.
 *
 * @package Guardianes_Formularios
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normaliza fechas EXIF, PDF, ISO o timestamps a YYYY-MM-DD.
 *
 * @param mixed $value Fecha original.
 * @return string
 */
function gnf_normalize_evidence_original_date( $value ) {
	if ( is_int( $value ) || ( is_string( $value ) && preg_match( '/^\d{10,13}$/', trim( $value ) ) ) ) {
		$timestamp = (int) $value;
		if ( $timestamp > 9999999999 ) {
			$timestamp = (int) floor( $timestamp / 1000 );
		}
		return $timestamp > 0 ? gmdate( 'Y-m-d', $timestamp ) : '';
	}

	$value = trim( (string) $value );
	if ( '' === $value ) {
		return '';
	}

	$patterns = array(
		'/^D:((?:19|20)\d{2})(\d{2})(\d{2})/',
		'/^((?:19|20)\d{2}):([01]\d):([0-3]\d)/',
		'/^((?:19|20)\d{2})-([01]\d)-([0-3]\d)/',
	);
	foreach ( $patterns as $pattern ) {
		if ( preg_match( $pattern, $value, $matches ) ) {
			$year  = (int) $matches[1];
			$month = (int) $matches[2];
			$day   = (int) $matches[3];
			return checkdate( $month, $day, $year ) ? sprintf( '%04d-%02d-%02d', $year, $month, $day ) : '';
		}
	}

	return '';
}

/**
 * Lee una muestra acotada del inicio y final del archivo.
 *
 * @param string $path Ruta local.
 * @return string
 */
function gnf_read_evidence_metadata_bytes( $path ) {
	$handle = @fopen( $path, 'rb' );
	if ( false === $handle ) {
		return '';
	}

	$chunk_size = 4 * 1024 * 1024;
	$head       = (string) fread( $handle, $chunk_size );
	$tail       = '';
	$size       = @filesize( $path );
	if ( is_int( $size ) && $size > $chunk_size ) {
		@fseek( $handle, max( 0, $size - $chunk_size ), SEEK_SET );
		$tail = (string) fread( $handle, $chunk_size );
	}
	fclose( $handle );

	return $head . $tail;
}

/**
 * Extrae una fecha ISO/EXIF cercana a una etiqueta de creación.
 *
 * @param string $bytes Contenido parcial.
 * @return string
 */
function gnf_extract_labeled_embedded_date( $bytes ) {
	$patterns = array(
		'/\/(?:CreationDate)\s*\(\s*(D:[^\)]+)\)/i',
		'/<(?:xmp:CreateDate|pdf:CreationDate)>\s*([^<]+)\s*</i',
		'/(?:xmp:CreateDate|CreateDate)\s*=\s*["\']([^"\']+)["\']/i',
		'/(?:DateTimeOriginal|DateTimeDigitized|DateTime)\x00*[^0-9]{0,32}((?:19|20)\d{2}:[01]\d:[0-3]\d(?:[ T][0-2]\d:[0-5]\d:[0-5]\d)?)/i',
	);
	foreach ( $patterns as $pattern ) {
		if ( preg_match( $pattern, $bytes, $matches ) ) {
			$date = gnf_normalize_evidence_original_date( $matches[1] );
			if ( '' !== $date ) {
				return $date;
			}
		}
	}

	return '';
}

/**
 * Extrae la fecha embebida usando metadatos nativos y formatos comunes.
 *
 * @param string $path Ruta local.
 * @param string $type Tipo de evidencia.
 * @return array{date:?string,source:string}
 */
function gnf_extract_evidence_original_date( $path, $type = '' ) {
	$path = (string) $path;
	if ( '' === $path || ! is_file( $path ) || ! is_readable( $path ) ) {
		return array( 'date' => null, 'source' => 'unavailable' );
	}

	static $request_cache = array();
	$normalized_path = function_exists( 'wp_normalize_path' ) ? wp_normalize_path( $path ) : str_replace( '\\', '/', $path );
	$cache_key       = $normalized_path . '|' . strtolower( trim( (string) $type ) );
	if ( isset( $request_cache[ $cache_key ] ) ) {
		return $request_cache[ $cache_key ];
	}

	$extension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
	$type      = strtolower( trim( (string) $type ) );
	$is_image  = 'imagen' === $type || in_array( $extension, array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'heif', 'tif', 'tiff' ), true );
	$is_pdf    = 'pdf' === $type || 'pdf' === $extension;

	if ( $is_image && ! function_exists( 'wp_read_image_metadata' ) && defined( 'ABSPATH' ) ) {
		$image_functions = ABSPATH . 'wp-admin/includes/image.php';
		if ( is_file( $image_functions ) ) {
			require_once $image_functions;
		}
	}

	if ( $is_image && function_exists( 'wp_read_image_metadata' ) ) {
		$metadata = wp_read_image_metadata( $path );
		if ( ! empty( $metadata['created_timestamp'] ) ) {
			$request_cache[ $cache_key ] = array(
				'date'   => gmdate( 'Y-m-d', (int) $metadata['created_timestamp'] ),
				'source' => 'image_metadata',
			);
			return $request_cache[ $cache_key ];
		}
	}

	if ( $is_image && function_exists( 'exif_read_data' ) ) {
		$exif = @exif_read_data( $path, 'IFD0,EXIF', true );
		foreach ( array( $exif['EXIF']['DateTimeOriginal'] ?? '', $exif['EXIF']['DateTimeDigitized'] ?? '', $exif['IFD0']['DateTime'] ?? '' ) as $candidate ) {
			$date = gnf_normalize_evidence_original_date( $candidate );
			if ( '' !== $date ) {
				$request_cache[ $cache_key ] = array( 'date' => $date, 'source' => 'image_metadata' );
				return $request_cache[ $cache_key ];
			}
		}
	}

	$bytes = gnf_read_evidence_metadata_bytes( $path );
	$date  = gnf_extract_labeled_embedded_date( $bytes );
	if ( '' !== $date ) {
		$request_cache[ $cache_key ] = array(
			'date'   => $date,
			'source' => $is_pdf ? 'pdf_metadata' : ( $is_image ? 'image_metadata' : 'embedded_metadata' ),
		);
		return $request_cache[ $cache_key ];
	}

	$request_cache[ $cache_key ] = array( 'date' => null, 'source' => 'unavailable' );
	return $request_cache[ $cache_key ];
}

/**
 * Agrega fecha, fuente y comparación anual a una evidencia.
 *
 * @param array      $evidence        Evidencia.
 * @param int|null   $active_year     Año activo.
 * @param array|null $provided_metadata Metadatos capturados por el navegador/extractor.
 * @return array
 */
function gnf_apply_evidence_original_date( $evidence, $active_year = null, $provided_metadata = null ) {
	$evidence = is_array( $evidence ) ? $evidence : array();
	$date     = gnf_normalize_evidence_original_date( $evidence['original_date'] ?? $evidence['photo_date'] ?? '' );
	$source   = trim( (string) ( $evidence['date_source'] ?? '' ) );

	if ( '' === $date && ! empty( $evidence['path_local'] ) ) {
		$extracted = gnf_extract_evidence_original_date( $evidence['path_local'], $evidence['tipo'] ?? $evidence['type'] ?? '' );
		$date      = (string) ( $extracted['date'] ?? '' );
		$source    = (string) ( $extracted['source'] ?? '' );
	}

	if ( '' === $date && is_array( $provided_metadata ) ) {
		$date   = gnf_normalize_evidence_original_date( $provided_metadata['date'] ?? '' );
		$source = '' !== $date ? trim( (string) ( $provided_metadata['source'] ?? 'browser_file_metadata' ) ) : '';
	}

	if ( '' !== $date && '' === $source ) {
		$source = ! empty( $evidence['photo_date'] ) ? 'legacy_photo_date' : 'embedded_metadata';
	}

	$evidence['original_date'] = '' !== $date ? $date : null;
	$evidence['date_source']   = '' !== $date ? ( $source ?: 'embedded_metadata' ) : 'unavailable';

	$year     = '' !== $date ? (int) substr( $date, 0, 4 ) : 0;
	$mismatch = $year > 0 && (int) $active_year > 0 && $year !== (int) $active_year;
	$evidence['date_year_mismatch']     = $mismatch;
	$evidence['requires_year_validation'] = $mismatch;

	$type      = (string) ( $evidence['tipo'] ?? $evidence['type'] ?? '' );
	$file_name = (string) ( $evidence['nombre'] ?? $evidence['filename'] ?? '' );
	$is_image  = 'imagen' === $type || 1 === preg_match( '/\.(jpe?g|png|gif|webp|heic|heif|tiff?)$/i', $file_name );
	if ( $is_image && '' !== $date && 'browser_file_metadata' !== $evidence['date_source'] ) {
		$evidence['photo_date'] = $date;
	}

	return $evidence;
}
