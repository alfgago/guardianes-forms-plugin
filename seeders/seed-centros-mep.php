<?php
/**
 * Seeder para importar centros educativos del MEP.
 *
 * Uso desde WP-CLI:
 *   wp eval-file seeders/seed-centros-mep.php
 *   wp eval-file seeders/seed-centros-mep.php --dry-run
 *
 * O pasar un archivo especifico:
 *   wp eval-file seeders/seed-centros-mep.php /path/to/escuelas-mep.csv
 */

if ( ! defined( 'ABSPATH' ) ) {
	$wp_load = dirname( dirname( dirname( dirname( __DIR__ ) ) ) ) . '/wp-load.php';
	if ( file_exists( $wp_load ) ) {
		require_once $wp_load;
	} else {
		die( 'No se pudo cargar WordPress' );
	}
}

/**
 * Shared stats template for centro imports.
 *
 * @return array<string,mixed>
 */
function gnf_get_centros_import_stats_template() {
	return array(
		'total'      => 0,
		'created'    => 0,
		'updated'    => 0,
		'skipped'    => 0,
		'errors'     => array(),
		'duplicates' => 0,
	);
}

/**
 * Returns the pilot DRE list normalized for fast lookups.
 *
 * @return string[]
 */
function gnf_get_centros_dres_activas_normalizadas() {
	static $normalized = null;

	if ( null !== $normalized ) {
		return $normalized;
	}

	$dres_activas_piloto = array(
		'OCCIDENTE',
		'SAN JOSE NORTE',
		'HEREDIA',
		'NICOYA',
		'LIBERIA',
		'SANTA CRUZ',
		'COTO',
		'CANAS',
	);

	$normalized = array_map(
		static function( $dre ) {
			return gnf_normalize_region_name( $dre );
		},
		$dres_activas_piloto
	);

	return $normalized;
}

/**
 * Normalizes free-text values for importer lookup keys.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function gnf_normalize_centros_lookup_value( $value ) {
	return strtolower( trim( (string) $value ) );
}

/**
 * Normalizes region names for cache lookups.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function gnf_normalize_region_name( $value ) {
	return strtoupper( remove_accents( trim( (string) $value ) ) );
}

/**
 * Builds a normalized title+region lookup key.
 *
 * @param string $nombre         Centro title.
 * @param int    $region_term_id Region term ID.
 * @return string
 */
function gnf_get_centros_title_region_lookup_key( $nombre, $region_term_id ) {
	return gnf_normalize_centros_lookup_value( $nombre ) . '|' . (int) $region_term_id;
}

/**
 * Builds a normalized title+circuit lookup key.
 *
 * @param string $nombre   Centro title.
 * @param string $circuito Circuito.
 * @return string
 */
function gnf_get_centros_title_circuit_lookup_key( $nombre, $circuito ) {
	return gnf_normalize_centros_lookup_value( $nombre ) . '|' . gnf_normalize_centros_lookup_value( $circuito );
}

/**
 * Counts CSV data rows excluding the header line.
 *
 * @param string $csv_path CSV path.
 * @return int
 */
function gnf_count_centros_csv_rows( $csv_path ) {
	if ( ! file_exists( $csv_path ) ) {
		return 0;
	}

	$total_lines = count( file( $csv_path ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	return max( 0, $total_lines - 1 );
}

/**
 * Counts JSON entries in the supplement file.
 *
 * @param string $json_path JSON path.
 * @return int
 */
function gnf_count_centros_json_entries( $json_path ) {
	if ( ! file_exists( $json_path ) ) {
		return 0;
	}

	$json_content = file_get_contents( $json_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( false === $json_content ) {
		return 0;
	}

	$entries = json_decode( $json_content, true );
	return is_array( $entries ) ? count( $entries ) : 0;
}

/**
 * Builds an importer cache of existing centros and regions.
 *
 * @return array<string,mixed>
 */
function gnf_build_centros_import_cache() {
	global $wpdb;

	$cache = array(
		'by_code'          => array(),
		'by_title_region'  => array(),
		'by_title_circuit' => array(),
		'regions_by_name'  => array(),
		'records'          => array(),
		'dres_activas'     => gnf_get_centros_dres_activas_normalizadas(),
	);

	$rows = $wpdb->get_results(
		"SELECT p.ID,
				p.post_title,
				MAX(CASE WHEN pm.meta_key = 'codigo_mep' THEN pm.meta_value END) AS codigo_mep,
				MAX(CASE WHEN pm.meta_key = 'region' THEN pm.meta_value END) AS region_term_id,
				MAX(CASE WHEN pm.meta_key = 'circuito' THEN pm.meta_value END) AS circuito
		 FROM {$wpdb->posts} p
		 LEFT JOIN {$wpdb->postmeta} pm
		   ON p.ID = pm.post_id
		  AND pm.meta_key IN ('codigo_mep', 'region', 'circuito')
		 WHERE p.post_type = 'centro_educativo'
		   AND p.post_status IN ('publish', 'draft')
		 GROUP BY p.ID, p.post_title"
	);

	foreach ( (array) $rows as $row ) {
		$centro_id      = (int) $row->ID;
		$codigo         = trim( (string) $row->codigo_mep );
		$region_term_id = (int) $row->region_term_id;
		$circuito       = trim( (string) $row->circuito );

		$cache['records'][ $centro_id ] = array(
			'title'          => (string) $row->post_title,
			'codigo_mep'     => $codigo,
			'region_term_id' => $region_term_id,
			'circuito'       => $circuito,
		);

		if ( '' !== $codigo ) {
			$cache['by_code'][ gnf_normalize_centros_lookup_value( $codigo ) ] = $centro_id;
		}

		if ( $region_term_id > 0 ) {
			$cache['by_title_region'][ gnf_get_centros_title_region_lookup_key( $row->post_title, $region_term_id ) ] = $centro_id;
		}

		if ( '' !== $circuito ) {
			$cache['by_title_circuit'][ gnf_get_centros_title_circuit_lookup_key( $row->post_title, $circuito ) ] = $centro_id;
		}
	}

	$terms = get_terms(
		array(
			'taxonomy'   => 'gn_region',
			'hide_empty' => false,
		)
	);

	if ( ! is_wp_error( $terms ) ) {
		foreach ( $terms as $term ) {
			$cache['regions_by_name'][ gnf_normalize_region_name( $term->name ) ] = (int) $term->term_id;
		}
	}

	return $cache;
}

/**
 * Resolves or creates a region term and updates the importer cache.
 *
 * @param string              $regional Region display name.
 * @param array<string,mixed> &$cache   Import cache.
 * @return int
 */
function gnf_prepare_centros_region_term( $regional, &$cache ) {
	$normalized = gnf_normalize_region_name( $regional );
	if ( '' === $normalized ) {
		return 0;
	}

	if ( isset( $cache['regions_by_name'][ $normalized ] ) ) {
		return (int) $cache['regions_by_name'][ $normalized ];
	}

	$term = term_exists( $regional, 'gn_region' );
	if ( ! $term ) {
		$term = wp_insert_term( $regional, 'gn_region' );
	}

	if ( is_wp_error( $term ) ) {
		return 0;
	}

	$term_id = is_array( $term ) ? (int) $term['term_id'] : (int) $term;
	$cache['regions_by_name'][ $normalized ] = $term_id;

	return $term_id;
}

/**
 * Syncs importer lookup keys after a create/update.
 *
 * @param int                 $centro_id      Centro ID.
 * @param string              $nombre         Centro title.
 * @param string              $codigo         Codigo MEP.
 * @param int                 $region_term_id Region term ID.
 * @param string              $circuito       Circuito value.
 * @param array<string,mixed> &$cache         Import cache.
 * @return void
 */
function gnf_sync_centros_import_cache_record( $centro_id, $nombre, $codigo, $region_term_id, $circuito, &$cache ) {
	$cache['records'][ $centro_id ] = array(
		'title'          => $nombre,
		'codigo_mep'     => $codigo,
		'region_term_id' => (int) $region_term_id,
		'circuito'       => $circuito,
	);

	if ( '' !== $codigo ) {
		$cache['by_code'][ gnf_normalize_centros_lookup_value( $codigo ) ] = (int) $centro_id;
	}

	if ( $region_term_id > 0 ) {
		$cache['by_title_region'][ gnf_get_centros_title_region_lookup_key( $nombre, $region_term_id ) ] = (int) $centro_id;
	}

	if ( '' !== $circuito ) {
		$cache['by_title_circuit'][ gnf_get_centros_title_circuit_lookup_key( $nombre, $circuito ) ] = (int) $centro_id;
	}
}

/**
 * Upserts a single centro record.
 *
 * @param array<string,string> $data     Parsed centro data.
 * @param string               $line_ref Human-friendly line reference for errors.
 * @param bool                 $dry_run  Whether writes are disabled.
 * @param array<string,mixed>  &$cache   Import cache.
 * @return array<string,mixed>
 */
function gnf_import_single_centro_record( $data, $line_ref, $dry_run, &$cache ) {
	$nombre      = trim( (string) ( $data['nombre'] ?? '' ) );
	$codigo      = trim( (string) ( $data['codigo'] ?? '' ) );
	$regional    = trim( (string) ( $data['regional'] ?? '' ) );
	$circuito    = trim( (string) ( $data['circuito'] ?? '' ) );
	$canton      = trim( (string) ( $data['canton'] ?? '' ) );
	$provincia   = trim( (string) ( $data['provincia'] ?? '' ) );
	$distrito    = trim( (string) ( $data['distrito'] ?? '' ) );
	$poblado     = trim( (string) ( $data['poblado'] ?? '' ) );
	$dependencia = trim( (string) ( $data['dependencia'] ?? '' ) );
	$zona        = trim( (string) ( $data['zona'] ?? '' ) );
	$dir_plan    = trim( (string) ( $data['direccion_plan'] ?? '' ) );
	$tel1        = trim( (string) ( $data['telefono1'] ?? '' ) );
	$tel2        = trim( (string) ( $data['telefono2'] ?? '' ) );

	$existing_id = gnf_find_existing_centro( $nombre, $codigo, $regional, $circuito, $cache );
	$result      = array(
		'action'    => $existing_id ? 'updated' : 'created',
		'centro_id' => (int) $existing_id,
	);

	if ( $dry_run ) {
		return $result;
	}

	$record = $existing_id && isset( $cache['records'][ $existing_id ] ) ? $cache['records'][ $existing_id ] : array();

	if ( $existing_id ) {
		$centro_id = (int) $existing_id;
		if ( ! isset( $record['title'] ) || $record['title'] !== $nombre ) {
			wp_update_post(
				array(
					'ID'         => $centro_id,
					'post_title' => $nombre,
				)
			);
		}
	} else {
		$centro_id = wp_insert_post(
			array(
				'post_type'   => 'centro_educativo',
				'post_status' => 'publish',
				'post_title'  => $nombre,
			)
		);

		if ( is_wp_error( $centro_id ) ) {
			return array(
				'action' => 'error',
				'error'  => "{$line_ref}: Error creando '{$nombre}' - " . $centro_id->get_error_message(),
			);
		}
	}

	if ( '' !== $codigo ) {
		update_post_meta( $centro_id, 'codigo_mep', $codigo );
	}
	if ( '' !== $circuito ) {
		update_post_meta( $centro_id, 'circuito', $circuito );
	}
	if ( '' !== $canton ) {
		update_post_meta( $centro_id, 'canton', $canton );
	}
	if ( '' !== $provincia ) {
		update_post_meta( $centro_id, 'provincia', $provincia );
	}
	if ( '' !== $distrito ) {
		update_post_meta( $centro_id, 'distrito', $distrito );
	}
	if ( '' !== $poblado ) {
		update_post_meta( $centro_id, 'poblado', $poblado );
	}
	if ( '' !== $dependencia ) {
		update_post_meta( $centro_id, 'dependencia', strtolower( $dependencia ) );
	}
	if ( '' !== $zona ) {
		update_post_meta( $centro_id, 'zona', strtolower( $zona ) );
	}
	if ( '' !== $dir_plan ) {
		update_post_meta( $centro_id, 'direccion_planificacion', $dir_plan );
	}
	if ( '' !== $tel1 && '-' !== $tel1 ) {
		update_post_meta( $centro_id, 'telefono', $tel1 );
	}
	if ( '' !== $tel2 && '-' !== $tel2 ) {
		update_post_meta( $centro_id, 'telefono2', $tel2 );
	}

	update_post_meta( $centro_id, 'estado_centro', 'activo' );

	$region_term_id = 0;
	if ( '' !== $regional ) {
		$region_term_id = gnf_prepare_centros_region_term( $regional, $cache );
		if ( $region_term_id > 0 ) {
			$current_region = isset( $record['region_term_id'] ) ? (int) $record['region_term_id'] : 0;
			if ( $current_region !== $region_term_id ) {
				wp_set_object_terms( $centro_id, array( $region_term_id ), 'gn_region', false );
				update_post_meta( $centro_id, 'region', $region_term_id );
			}

			$dre_activa = in_array( gnf_normalize_region_name( $regional ), $cache['dres_activas'], true ) ? 1 : 0;
			update_term_meta( $region_term_id, 'gnf_dre_activa', $dre_activa );
		}
	}

	$final_codigo = '' !== $codigo ? $codigo : (string) ( $record['codigo_mep'] ?? '' );
	$final_region = $region_term_id > 0 ? $region_term_id : (int) ( $record['region_term_id'] ?? 0 );
	$final_circuito = '' !== $circuito ? $circuito : (string) ( $record['circuito'] ?? '' );

	gnf_sync_centros_import_cache_record( (int) $centro_id, $nombre, $final_codigo, $final_region, $final_circuito, $cache );

	$result['centro_id'] = (int) $centro_id;
	return $result;
}

/**
 * Normalizes CSV headers and maps supported column aliases.
 *
 * @param array<int,mixed> $headers Raw header row.
 * @return array{headers: array<int,string>, col_idx: array<string,int>}
 */
function gnf_map_centros_csv_columns( $headers ) {
	$headers = array_map(
		static function( $header ) {
			$header = preg_replace( '/\s+/', ' ', trim( (string) $header ) );
			$header = strtolower( $header );
			$header = str_replace( ' ', '_', $header );
			return sanitize_key( $header );
		},
		$headers
	);

	$col_map = array(
		'codigo_mep'         => array( 'codigo', 'codigo_mep', 'codigo_presup', 'code', 'mep' ),
		'nombre'             => array( 'nombre', 'name', 'centro', 'institucion' ),
		'direccion_regional' => array( 'direccion_regional', 'direccion__regional', 'regional', 'dre', 'region' ),
		'circuito'           => array( 'circuito', 'circuit' ),
		'canton'             => array( 'canton', 'county' ),
		'provincia'          => array( 'provincia', 'province' ),
		'distrito'           => array( 'distrito', 'district' ),
		'poblado'            => array( 'poblado', 'localidad' ),
		'dependencia'        => array( 'dependencia', 'tipo' ),
		'zona'               => array( 'zona', 'zone' ),
		'direccion_plan'     => array( 'direccionplan', 'direccion_plan', 'direccion_planificacion' ),
		'telefono1'          => array( 'telefono1', 'telefono', 'phone1' ),
		'telefono2'          => array( 'telefono2', 'phone2' ),
	);

	$col_idx = array();
	foreach ( $col_map as $field => $aliases ) {
		foreach ( $aliases as $alias ) {
			$idx = array_search( $alias, $headers, true );
			if ( false !== $idx ) {
				$col_idx[ $field ] = (int) $idx;
				break;
			}
		}
	}

	return array(
		'headers' => $headers,
		'col_idx' => $col_idx,
	);
}

/**
 * Processes a CSV batch.
 *
 * @param string $csv_path CSV path.
 * @param int    $offset   Number of data rows already consumed.
 * @param int    $limit    Max number of rows to consume in this batch.
 * @param bool   $dry_run  Whether writes are disabled.
 * @return array<string,mixed>
 */
function gnf_import_centros_from_csv_batch( $csv_path, $offset = 0, $limit = 250, $dry_run = false ) {
	$stats = gnf_get_centros_import_stats_template();
	$total = gnf_count_centros_csv_rows( $csv_path );

	if ( $offset >= $total ) {
		return array(
			'stats'       => $stats,
			'total_rows'  => $total,
			'processed'   => 0,
			'next_offset' => $total,
			'has_more'    => false,
		);
	}

	if ( ! file_exists( $csv_path ) ) {
		$stats['errors'][] = 'Archivo no encontrado: ' . $csv_path;
		return array(
			'stats'       => $stats,
			'total_rows'  => 0,
			'processed'   => 0,
			'next_offset' => $offset,
			'has_more'    => false,
		);
	}

	$file = new SplFileObject( $csv_path, 'r' );
	$file->setFlags( SplFileObject::READ_CSV );
	$file->setCsvControl( ',' );

	$headers = $file->fgetcsv();
	if ( empty( $headers ) || array( null ) === $headers ) {
		$stats['errors'][] = 'CSV vacio o sin encabezados';
		return array(
			'stats'       => $stats,
			'total_rows'  => $total,
			'processed'   => 0,
			'next_offset' => $offset,
			'has_more'    => false,
		);
	}

	$mapped  = gnf_map_centros_csv_columns( $headers );
	$col_idx = $mapped['col_idx'];

	if ( defined( 'WP_CLI' ) && WP_CLI && 0 === $offset ) {
		WP_CLI::log( 'Columnas mapeadas: ' . wp_json_encode( $col_idx ) );
		WP_CLI::log( 'Encabezados encontrados: ' . implode( ', ', $mapped['headers'] ) );
	}

	if ( ! isset( $col_idx['nombre'] ) ) {
		$stats['errors'][] = 'Columna "nombre" no encontrada. Encabezados: ' . implode( ', ', $mapped['headers'] );
		return array(
			'stats'       => $stats,
			'total_rows'  => $total,
			'processed'   => 0,
			'next_offset' => $offset,
			'has_more'    => false,
		);
	}

	$lookup_cache = gnf_build_centros_import_cache();
	$file->seek( $offset + 1 );

	$processed = 0;

	while ( ! $file->eof() && $processed < $limit ) {
		$row = $file->current();
		$file->next();

		if ( false === $row || array( null ) === $row ) {
			$processed++;
			$stats['skipped']++;
			continue;
		}

		$line_number = $offset + $processed + 2;
		$processed++;

		$nombre   = isset( $col_idx['nombre'] ) ? trim( (string) ( $row[ $col_idx['nombre'] ] ?? '' ) ) : '';
		$codigo   = isset( $col_idx['codigo_mep'] ) ? trim( (string) ( $row[ $col_idx['codigo_mep'] ] ?? '' ) ) : '';
		$regional = isset( $col_idx['direccion_regional'] ) ? trim( (string) ( $row[ $col_idx['direccion_regional'] ] ?? '' ) ) : '';

		if ( '' === $nombre || 'TOTAL' === strtoupper( $nombre ) || 'TOTAL' === strtoupper( $regional ) ) {
			$stats['skipped']++;
			continue;
		}

		if ( '0' === $codigo || '-' === $codigo ) {
			$codigo = '';
		}

		$stats['total']++;

		$result = gnf_import_single_centro_record(
			array(
				'nombre'         => $nombre,
				'codigo'         => $codigo,
				'regional'       => $regional,
				'circuito'       => isset( $col_idx['circuito'] ) ? trim( (string) ( $row[ $col_idx['circuito'] ] ?? '' ) ) : '',
				'canton'         => isset( $col_idx['canton'] ) ? trim( (string) ( $row[ $col_idx['canton'] ] ?? '' ) ) : '',
				'provincia'      => isset( $col_idx['provincia'] ) ? trim( (string) ( $row[ $col_idx['provincia'] ] ?? '' ) ) : '',
				'distrito'       => isset( $col_idx['distrito'] ) ? trim( (string) ( $row[ $col_idx['distrito'] ] ?? '' ) ) : '',
				'poblado'        => isset( $col_idx['poblado'] ) ? trim( (string) ( $row[ $col_idx['poblado'] ] ?? '' ) ) : '',
				'dependencia'    => isset( $col_idx['dependencia'] ) ? trim( (string) ( $row[ $col_idx['dependencia'] ] ?? '' ) ) : '',
				'zona'           => isset( $col_idx['zona'] ) ? trim( (string) ( $row[ $col_idx['zona'] ] ?? '' ) ) : '',
				'direccion_plan' => isset( $col_idx['direccion_plan'] ) ? trim( (string) ( $row[ $col_idx['direccion_plan'] ] ?? '' ) ) : '',
				'telefono1'      => isset( $col_idx['telefono1'] ) ? trim( (string) ( $row[ $col_idx['telefono1'] ] ?? '' ) ) : '',
				'telefono2'      => isset( $col_idx['telefono2'] ) ? trim( (string) ( $row[ $col_idx['telefono2'] ] ?? '' ) ) : '',
			),
			'Linea ' . $line_number,
			$dry_run,
			$lookup_cache
		);

		if ( 'error' === $result['action'] ) {
			$stats['errors'][] = $result['error'];
			continue;
		}

		$stats[ $result['action'] ]++;
	}

	$next_offset = min( $total, $offset + $processed );
	if ( 0 === $processed && $file->eof() ) {
		$next_offset = $total;
	}

	return array(
		'stats'       => $stats,
		'total_rows'  => $total,
		'processed'   => $processed,
		'next_offset' => $next_offset,
		'has_more'    => $next_offset < $total,
	);
}

/**
 * Imports centros from a CSV file.
 *
 * @param string $csv_path CSV path.
 * @param bool   $dry_run  Whether writes are disabled.
 * @return array<string,mixed>
 */
function gnf_import_centros_from_csv( $csv_path, $dry_run = false ) {
	$stats      = gnf_get_centros_import_stats_template();
	$offset     = 0;
	$batch_size = 250;

	do {
		$batch = gnf_import_centros_from_csv_batch( $csv_path, $offset, $batch_size, $dry_run );
		$stats['total']      += $batch['stats']['total'];
		$stats['created']    += $batch['stats']['created'];
		$stats['updated']    += $batch['stats']['updated'];
		$stats['skipped']    += $batch['stats']['skipped'];
		$stats['duplicates'] += $batch['stats']['duplicates'];
		$stats['errors']      = array_merge( $stats['errors'], $batch['stats']['errors'] );
		$offset               = (int) $batch['next_offset'];

		if ( defined( 'WP_CLI' ) && WP_CLI && $offset > 0 ) {
			WP_CLI::log( "Procesados CSV: {$offset}/{$batch['total_rows']}" );
		}

		if ( ! empty( $batch['stats']['errors'] ) && 0 === $batch['processed'] ) {
			break;
		}
	} while ( ! empty( $batch['has_more'] ) );

	return $stats;
}

/**
 * Finds an existing centro by code or by exact title plus region/circuit.
 *
 * @param string                   $nombre   Centro name.
 * @param string                   $codigo   Codigo MEP.
 * @param string                   $regional Region name.
 * @param string                   $circuito Circuito.
 * @param array<string,mixed>|null $cache    Optional importer cache.
 * @return int|null
 */
function gnf_find_existing_centro( $nombre, $codigo, $regional, $circuito, $cache = null ) {
	global $wpdb;

	if ( is_array( $cache ) ) {
		$codigo_key = gnf_normalize_centros_lookup_value( $codigo );
		if ( '' !== $codigo_key && isset( $cache['by_code'][ $codigo_key ] ) ) {
			return (int) $cache['by_code'][ $codigo_key ];
		}

		$region_term_id = 0;
		if ( '' !== trim( (string) $regional ) ) {
			$normalized_region = gnf_normalize_region_name( $regional );
			$region_term_id    = isset( $cache['regions_by_name'][ $normalized_region ] ) ? (int) $cache['regions_by_name'][ $normalized_region ] : 0;
		}

		if ( $region_term_id > 0 ) {
			$key = gnf_get_centros_title_region_lookup_key( $nombre, $region_term_id );
			if ( isset( $cache['by_title_region'][ $key ] ) ) {
				return (int) $cache['by_title_region'][ $key ];
			}
		}

		if ( '' !== trim( (string) $circuito ) ) {
			$key = gnf_get_centros_title_circuit_lookup_key( $nombre, $circuito );
			if ( isset( $cache['by_title_circuit'][ $key ] ) ) {
				return (int) $cache['by_title_circuit'][ $key ];
			}
		}

		return null;
	}

	if ( $codigo ) {
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				 WHERE p.post_type = 'centro_educativo'
				   AND p.post_status IN ('publish', 'draft')
				   AND pm.meta_key = 'codigo_mep'
				   AND pm.meta_value = %s
				 LIMIT 1",
				$codigo
			)
		);
		if ( $existing ) {
			return (int) $existing;
		}
	}

	$region_term_id = 0;
	if ( $regional ) {
		$term = term_exists( $regional, 'gn_region' );
		if ( $term && ! is_wp_error( $term ) ) {
			$region_term_id = is_array( $term ) ? (int) $term['term_id'] : (int) $term;
		}
	}

	if ( $region_term_id ) {
		$query_args = array(
			'post_type'      => 'centro_educativo',
			'post_status'    => array( 'publish', 'draft' ),
			'title'          => $nombre,
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'   => 'region',
					'value' => (string) $region_term_id,
				),
			),
		);

		add_filter( 'posts_where', 'gnf_exact_title_filter', 10, 2 );
		$query = new WP_Query( $query_args );
		remove_filter( 'posts_where', 'gnf_exact_title_filter', 10, 2 );

		if ( $query->have_posts() ) {
			return (int) $query->posts[0];
		}
	}

	if ( $circuito ) {
		$query_args = array(
			'post_type'      => 'centro_educativo',
			'post_status'    => array( 'publish', 'draft' ),
			'title'          => $nombre,
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'   => 'circuito',
					'value' => $circuito,
				),
			),
		);

		add_filter( 'posts_where', 'gnf_exact_title_filter', 10, 2 );
		$query = new WP_Query( $query_args );
		remove_filter( 'posts_where', 'gnf_exact_title_filter', 10, 2 );

		if ( $query->have_posts() ) {
			return (int) $query->posts[0];
		}
	}

	return null;
}

/**
 * Filter used for exact-title matching in WP_Query.
 *
 * @param string   $where SQL WHERE clause.
 * @param WP_Query $query Current query.
 * @return string
 */
function gnf_exact_title_filter( $where, $query ) {
	global $wpdb;
	$title = $query->get( 'title' );
	if ( $title ) {
		$where .= $wpdb->prepare( " AND {$wpdb->posts}.post_title = %s", $title );
	}
	return $where;
}

/**
 * Processes a JSON batch.
 *
 * @param string $json_path JSON path.
 * @param int    $offset    Number of entries already consumed.
 * @param int    $limit     Max number of entries to consume in this batch.
 * @param bool   $dry_run   Whether writes are disabled.
 * @return array<string,mixed>
 */
function gnf_import_centros_from_json_batch( $json_path, $offset = 0, $limit = 250, $dry_run = false ) {
	$stats = gnf_get_centros_import_stats_template();

	if ( ! file_exists( $json_path ) ) {
		$stats['errors'][] = 'Archivo JSON no encontrado: ' . $json_path;
		return array(
			'stats'       => $stats,
			'total_rows'  => 0,
			'processed'   => 0,
			'next_offset' => $offset,
			'has_more'    => false,
		);
	}

	$json_content = file_get_contents( $json_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( false === $json_content ) {
		$stats['errors'][] = 'No se pudo leer el archivo JSON';
		return array(
			'stats'       => $stats,
			'total_rows'  => 0,
			'processed'   => 0,
			'next_offset' => $offset,
			'has_more'    => false,
		);
	}

	$entries = json_decode( $json_content, true );
	if ( ! is_array( $entries ) ) {
		$stats['errors'][] = 'JSON invalido o no es un array';
		return array(
			'stats'       => $stats,
			'total_rows'  => 0,
			'processed'   => 0,
			'next_offset' => $offset,
			'has_more'    => false,
		);
	}

	$total        = count( $entries );
	$lookup_cache = gnf_build_centros_import_cache();
	$chunk        = array_slice( $entries, $offset, $limit, true );
	$processed    = 0;

	foreach ( $chunk as $idx => $entry ) {
		$processed++;

		$nombre = isset( $entry['NOMBRE'] ) ? trim( (string) $entry['NOMBRE'] ) : '';
		if ( '' === $nombre ) {
			$stats['skipped']++;
			continue;
		}

		$codigo = isset( $entry['CODIGO'] ) ? trim( (string) $entry['CODIGO'] ) : '';
		if ( '0000' === $codigo || '0' === $codigo || '-' === $codigo ) {
			$codigo = '';
		}

		$stats['total']++;

		$result = gnf_import_single_centro_record(
			array(
				'nombre'         => $nombre,
				'codigo'         => $codigo,
				'regional'       => isset( $entry['DIRECCION_REGIONAL'] ) ? trim( (string) $entry['DIRECCION_REGIONAL'] ) : '',
				'circuito'       => isset( $entry['CIRCUITO'] ) ? trim( (string) $entry['CIRCUITO'] ) : '',
				'canton'         => isset( $entry['CANTON'] ) ? trim( (string) $entry['CANTON'] ) : '',
				'provincia'      => isset( $entry['PROVINCIA'] ) ? trim( (string) $entry['PROVINCIA'] ) : '',
				'distrito'       => isset( $entry['DISTRITO'] ) ? trim( (string) $entry['DISTRITO'] ) : '',
				'poblado'        => isset( $entry['POBLADO'] ) ? trim( (string) $entry['POBLADO'] ) : '',
				'dependencia'    => isset( $entry['DEPENDENCIA'] ) ? trim( (string) $entry['DEPENDENCIA'] ) : '',
				'zona'           => isset( $entry['ZONA'] ) ? trim( (string) $entry['ZONA'] ) : '',
				'telefono1'      => isset( $entry['TELEFONO1'] ) ? trim( (string) $entry['TELEFONO1'] ) : '',
				'telefono2'      => isset( $entry['TELEFONO2'] ) ? trim( (string) $entry['TELEFONO2'] ) : '',
				'direccion_plan' => '',
			),
			'JSON #' . ( (int) $idx + 1 ),
			$dry_run,
			$lookup_cache
		);

		if ( 'error' === $result['action'] ) {
			$stats['errors'][] = $result['error'];
			continue;
		}

		$stats[ $result['action'] ]++;
	}

	$next_offset = min( $total, $offset + $processed );

	return array(
		'stats'       => $stats,
		'total_rows'  => $total,
		'processed'   => $processed,
		'next_offset' => $next_offset,
		'has_more'    => $next_offset < $total,
	);
}

/**
 * Imports centros from the JSON supplement.
 *
 * @param string $json_path JSON path.
 * @param bool   $dry_run  Whether writes are disabled.
 * @return array<string,mixed>
 */
function gnf_import_centros_from_json( $json_path, $dry_run = false ) {
	$stats      = gnf_get_centros_import_stats_template();
	$offset     = 0;
	$batch_size = 250;

	do {
		$batch = gnf_import_centros_from_json_batch( $json_path, $offset, $batch_size, $dry_run );
		$stats['total']      += $batch['stats']['total'];
		$stats['created']    += $batch['stats']['created'];
		$stats['updated']    += $batch['stats']['updated'];
		$stats['skipped']    += $batch['stats']['skipped'];
		$stats['duplicates'] += $batch['stats']['duplicates'];
		$stats['errors']      = array_merge( $stats['errors'], $batch['stats']['errors'] );
		$offset               = (int) $batch['next_offset'];

		if ( defined( 'WP_CLI' ) && WP_CLI && $offset > 0 ) {
			WP_CLI::log( "Procesados JSON: {$offset}/{$batch['total_rows']}" );
		}

		if ( ! empty( $batch['stats']['errors'] ) && 0 === $batch['processed'] ) {
			break;
		}
	} while ( ! empty( $batch['has_more'] ) );

	return $stats;
}

/**
 * Execute if called directly from CLI/WP-CLI.
 */
if ( php_sapi_name() === 'cli' || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
	$csv_file = null;
	$dry_run  = false;

	$args = isset( $argv ) ? $argv : array();
	foreach ( $args as $arg ) {
		if ( '--dry-run' === $arg ) {
			$dry_run = true;
		} elseif ( false !== strpos( $arg, '.csv' ) && file_exists( $arg ) ) {
			$csv_file = $arg;
		} elseif ( false !== strpos( $arg, '.csv' ) ) {
			$relative = __DIR__ . '/' . basename( $arg );
			if ( file_exists( $relative ) ) {
				$csv_file = $relative;
			}
		}
	}

	if ( ! $csv_file ) {
		$seeders_csv = __DIR__ . '/escuelas-mep.csv';
		$plugin_root = dirname( __DIR__ ) . '/escuelas-mep.csv';
		if ( file_exists( $seeders_csv ) ) {
			$csv_file = $seeders_csv;
		} elseif ( file_exists( $plugin_root ) ) {
			$csv_file = $plugin_root;
		} else {
			$csv_file = __DIR__ . '/centros-mep.csv';
		}
	}

	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		WP_CLI::log( '=== Importador de Centros Educativos MEP ===' );
		WP_CLI::log( "Archivo: {$csv_file}" );
		if ( $dry_run ) {
			WP_CLI::log( '(Modo dry-run, no se realizaran cambios)' );
		}
		WP_CLI::log( '' );
	} else {
		echo "Importando centros desde: {$csv_file}\n";
		if ( $dry_run ) {
			echo "(Modo dry-run, no se realizaran cambios)\n";
		}
	}

	$stats = gnf_import_centros_from_csv( $csv_file, $dry_run );

	$json_file = __DIR__ . '/centros_educativos_2024.json';
	if ( file_exists( $json_file ) ) {
		$log = defined( 'WP_CLI' ) && WP_CLI
			? function( $msg ) { WP_CLI::log( $msg ); }
			: function( $msg ) { echo $msg . "\n"; };

		$log( '' );
		$log( "=== Importando JSON: {$json_file} ===" );

		$json_stats = gnf_import_centros_from_json( $json_file, $dry_run );

		$log( "  JSON: {$json_stats['created']} nuevos, {$json_stats['updated']} actualizados, {$json_stats['skipped']} omitidos, {$json_stats['duplicates']} duplicados" );

		$stats['total']      += $json_stats['total'];
		$stats['created']    += $json_stats['created'];
		$stats['updated']    += $json_stats['updated'];
		$stats['skipped']    += $json_stats['skipped'];
		$stats['duplicates'] += $json_stats['duplicates'];
		$stats['errors']      = array_merge( $stats['errors'], $json_stats['errors'] );
	}

	$results = array(
		'Total procesados'  => $stats['total'],
		'Creados'           => $stats['created'],
		'Actualizados'      => $stats['updated'],
		'Omitidos'          => $stats['skipped'],
		'Duplicados en CSV' => $stats['duplicates'],
	);

	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		WP_CLI::log( '' );
		WP_CLI::log( '=== Resultados Totales ===' );
		foreach ( $results as $label => $value ) {
			WP_CLI::log( "  {$label}: {$value}" );
		}

		if ( ! empty( $stats['errors'] ) ) {
			WP_CLI::warning( 'Se encontraron ' . count( $stats['errors'] ) . ' errores:' );
			foreach ( $stats['errors'] as $error ) {
				WP_CLI::log( "  - {$error}" );
			}
		} else {
			WP_CLI::success( 'Importacion completada sin errores.' );
		}
	} else {
		echo "\nResultados Totales:\n";
		foreach ( $results as $label => $value ) {
			echo "  {$label}: {$value}\n";
		}

		if ( ! empty( $stats['errors'] ) ) {
			echo "\nErrores:\n";
			foreach ( $stats['errors'] as $error ) {
				echo "  - {$error}\n";
			}
		}
	}
}
