<?php
/**
 * Seeder para importar centros educativos del MEP.
 *
 * Uso desde WP-CLI:
 *   wp eval-file seeders/seed-centros-mep.php
 *   wp eval-file seeders/seed-centros-mep.php --dry-run
 *
 * O pasar un archivo específico:
 *   wp eval-file seeders/seed-centros-mep.php /path/to/escuelas-mep.csv
 */

if ( ! defined( 'ABSPATH' ) ) {
	// Cargar WordPress si se ejecuta desde CLI.
	$wp_load = dirname( dirname( dirname( dirname( __DIR__ ) ) ) ) . '/wp-load.php';
	if ( file_exists( $wp_load ) ) {
		require_once $wp_load;
	} else {
		die( 'No se pudo cargar WordPress' );
	}
}

/**
 * Importa centros educativos desde un archivo CSV.
 *
 * Formato CSV esperado (escuelas-mep.csv del MEP):
 * "DIRECCION REGIONAL",CIRCUITO,CODIGO,NOMBRE,PROVINCIA,CANTON,DISTRITO,POBLADO,DEPENDENCIA,ZONA,DireccionPlan,TELEFONO1,TELEFONO2
 *
 * @param string $csv_path Ruta al archivo CSV.
 * @param bool   $dry_run  Si es true, no inserta, solo reporta.
 * @return array Estadísticas de la importación.
 */
function gnf_import_centros_from_csv( $csv_path, $dry_run = false ) {
	$stats = array(
		'total'     => 0,
		'created'   => 0,
		'updated'   => 0,
		'skipped'   => 0,
		'errors'    => array(),
		'duplicates' => 0,
	);

	if ( ! file_exists( $csv_path ) ) {
		$stats['errors'][] = 'Archivo no encontrado: ' . $csv_path;
		return $stats;
	}

	$handle = fopen( $csv_path, 'r' );
	if ( ! $handle ) {
		$stats['errors'][] = 'No se pudo abrir el archivo';
		return $stats;
	}

	// Leer encabezados.
	$headers = fgetcsv( $handle );
	if ( ! $headers ) {
		$stats['errors'][] = 'CSV vacío o sin encabezados';
		fclose( $handle );
		return $stats;
	}

	// Normalizar encabezados (eliminar espacios, saltos de línea, convertir a minúsculas).
	$headers = array_map( function( $h ) {
		$h = preg_replace( '/\s+/', ' ', trim( $h ) ); // Colapsar espacios/saltos.
		$h = strtolower( $h );
		$h = str_replace( ' ', '_', $h );
		return sanitize_key( $h );
	}, $headers );

	// Mapeo de columnas - soporta múltiples nombres posibles.
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
				$col_idx[ $field ] = $idx;
				break;
			}
		}
	}

	// Debug: mostrar mapeo de columnas encontrado.
	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		WP_CLI::log( 'Columnas mapeadas: ' . wp_json_encode( $col_idx ) );
		WP_CLI::log( 'Encabezados encontrados: ' . implode( ', ', $headers ) );
	}

	// Verificar columnas mínimas.
	if ( ! isset( $col_idx['nombre'] ) ) {
		$stats['errors'][] = 'Columna "nombre" no encontrada. Encabezados: ' . implode( ', ', $headers );
		fclose( $handle );
		return $stats;
	}

	// Cache de regiones para evitar consultas repetidas.
	$regiones_cache = array();
	$dres_activas_piloto = array(
		'OCCIDENTE',
		'SAN JOSE NORTE',
		'HEREDIA',
		'NICOYA',
		'LIBERIA',
		'SANTA CRUZ',
		'COTO',
		'CAÑAS',
	);
	$dres_activas_normalizadas = array_map(
		function( $dre ) {
			return strtoupper( remove_accents( $dre ) );
		},
		$dres_activas_piloto
	);

	// Cache de nombres procesados para detectar duplicados en el mismo CSV.
	$nombres_procesados = array();

	$line_number = 1; // Ya leímos la cabecera.

	while ( ( $row = fgetcsv( $handle ) ) !== false ) {
		$line_number++;

		// Obtener valores de cada columna.
		$nombre    = isset( $col_idx['nombre'] ) ? trim( $row[ $col_idx['nombre'] ] ?? '' ) : '';
		$codigo    = isset( $col_idx['codigo_mep'] ) ? trim( $row[ $col_idx['codigo_mep'] ] ?? '' ) : '';
		$regional  = isset( $col_idx['direccion_regional'] ) ? trim( $row[ $col_idx['direccion_regional'] ] ?? '' ) : '';
		$circuito  = isset( $col_idx['circuito'] ) ? trim( $row[ $col_idx['circuito'] ] ?? '' ) : '';
		$canton    = isset( $col_idx['canton'] ) ? trim( $row[ $col_idx['canton'] ] ?? '' ) : '';
		$provincia = isset( $col_idx['provincia'] ) ? trim( $row[ $col_idx['provincia'] ] ?? '' ) : '';
		$distrito  = isset( $col_idx['distrito'] ) ? trim( $row[ $col_idx['distrito'] ] ?? '' ) : '';
		$poblado   = isset( $col_idx['poblado'] ) ? trim( $row[ $col_idx['poblado'] ] ?? '' ) : '';
		$dependencia = isset( $col_idx['dependencia'] ) ? trim( $row[ $col_idx['dependencia'] ] ?? '' ) : '';
		$zona      = isset( $col_idx['zona'] ) ? trim( $row[ $col_idx['zona'] ] ?? '' ) : '';
		$dir_plan  = isset( $col_idx['direccion_plan'] ) ? trim( $row[ $col_idx['direccion_plan'] ] ?? '' ) : '';
		$tel1      = isset( $col_idx['telefono1'] ) ? trim( $row[ $col_idx['telefono1'] ] ?? '' ) : '';
		$tel2      = isset( $col_idx['telefono2'] ) ? trim( $row[ $col_idx['telefono2'] ] ?? '' ) : '';

		// Omitir filas vacías o la fila TOTAL.
		if ( empty( $nombre ) || strtoupper( $nombre ) === 'TOTAL' || strtoupper( $regional ) === 'TOTAL' ) {
			$stats['skipped']++;
			continue;
		}

		$stats['total']++;

		// Limpiar código MEP - "0" significa sin código (escuelas privadas).
		if ( '0' === $codigo || '-' === $codigo ) {
			$codigo = '';
		}

		// Crear clave única para detectar duplicados: nombre + regional + circuito.
		$unique_key = strtolower( $nombre . '|' . $regional . '|' . $circuito );

		// Verificar si ya procesamos este centro en el mismo CSV.
		if ( isset( $nombres_procesados[ $unique_key ] ) ) {
			$stats['duplicates']++;
			$stats['skipped']++;
			continue;
		}
		$nombres_procesados[ $unique_key ] = true;

		// Buscar si ya existe en la base de datos.
		$existing_id = gnf_find_existing_centro( $nombre, $codigo, $regional, $circuito );

		if ( $dry_run ) {
			if ( $existing_id ) {
				$stats['updated']++;
			} else {
				$stats['created']++;
			}
			continue;
		}

		// Crear o actualizar el centro educativo.
		if ( $existing_id ) {
			$centro_id = $existing_id;
			wp_update_post( array(
				'ID'         => $centro_id,
				'post_title' => $nombre,
			) );
			$stats['updated']++;
		} else {
			$centro_id = wp_insert_post( array(
				'post_type'   => 'centro_educativo',
				'post_status' => 'publish',
				'post_title'  => $nombre,
			) );
			if ( is_wp_error( $centro_id ) ) {
				$stats['errors'][] = "Línea {$line_number}: Error creando '{$nombre}' - " . $centro_id->get_error_message();
				continue;
			}
			$stats['created']++;
		}

		// Guardar metadatos.
		if ( $codigo ) {
			update_post_meta( $centro_id, 'codigo_mep', $codigo );
		}
		if ( $circuito ) {
			update_post_meta( $centro_id, 'circuito', $circuito );
		}
		if ( $canton ) {
			update_post_meta( $centro_id, 'canton', $canton );
		}
		if ( $provincia ) {
			update_post_meta( $centro_id, 'provincia', $provincia );
		}
		if ( $distrito ) {
			update_post_meta( $centro_id, 'distrito', $distrito );
		}
		if ( $poblado ) {
			update_post_meta( $centro_id, 'poblado', $poblado );
		}
		if ( $dependencia ) {
			update_post_meta( $centro_id, 'dependencia', strtolower( $dependencia ) );
		}
		if ( $zona ) {
			update_post_meta( $centro_id, 'zona', strtolower( $zona ) );
		}
		if ( $dir_plan ) {
			update_post_meta( $centro_id, 'direccion_planificacion', $dir_plan );
		}
		if ( $tel1 && '-' !== $tel1 ) {
			update_post_meta( $centro_id, 'telefono', $tel1 );
		}
		if ( $tel2 && '-' !== $tel2 ) {
			update_post_meta( $centro_id, 'telefono2', $tel2 );
		}

		// Estado activo por defecto.
		update_post_meta( $centro_id, 'estado_centro', 'activo' );

		// Crear/asignar región como taxonomía.
		if ( $regional ) {
			if ( ! isset( $regiones_cache[ $regional ] ) ) {
				$term = term_exists( $regional, 'gn_region' );
				if ( ! $term ) {
					$term = wp_insert_term( $regional, 'gn_region' );
				}
				if ( ! is_wp_error( $term ) ) {
					$regiones_cache[ $regional ] = is_array( $term ) ? $term['term_id'] : $term;
				}
			}
			if ( isset( $regiones_cache[ $regional ] ) ) {
				$region_term_id = (int) $regiones_cache[ $regional ];
				wp_set_object_terms( $centro_id, array( $region_term_id ), 'gn_region', false );
				update_post_meta( $centro_id, 'region', $region_term_id );

				// Pilotaje: solo algunas DRE quedan activas por defecto.
				$regional_normalizada = strtoupper( remove_accents( $regional ) );
				$dre_activa = in_array( $regional_normalizada, $dres_activas_normalizadas, true ) ? 1 : 0;
				update_term_meta( $region_term_id, 'gnf_dre_activa', $dre_activa );
			}
		}

		// Mostrar progreso cada 500 registros.
		if ( defined( 'WP_CLI' ) && WP_CLI && 0 === $stats['total'] % 500 ) {
			WP_CLI::log( "Procesados: {$stats['total']}..." );
		}
	}

	fclose( $handle );
	return $stats;
}

/**
 * Busca un centro educativo existente por código MEP o por combinación nombre+región+circuito.
 *
 * @param string $nombre   Nombre del centro.
 * @param string $codigo   Código MEP.
 * @param string $regional Dirección regional.
 * @param string $circuito Circuito.
 * @return int|null ID del centro existente o null.
 */
function gnf_find_existing_centro( $nombre, $codigo, $regional, $circuito ) {
	global $wpdb;

	// 1. Buscar por código MEP (si existe).
	if ( $codigo ) {
		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			 WHERE p.post_type = 'centro_educativo'
			   AND p.post_status IN ('publish', 'draft')
			   AND pm.meta_key = 'codigo_mep'
			   AND pm.meta_value = %s
			 LIMIT 1",
			$codigo
		) );
		if ( $existing ) {
			return (int) $existing;
		}
	}

	// 2. Buscar por nombre exacto + región + circuito.
	$meta_query = array(
		'relation' => 'AND',
	);

	// Si hay circuito, usarlo para mayor precisión.
	if ( $circuito ) {
		$meta_query[] = array(
			'key'   => 'circuito',
			'value' => $circuito,
		);
	}

	// Si hay región (string), intentar resolver a term_id y usarlo como filtro.
	if ( $regional ) {
		$term = term_exists( $regional, 'gn_region' );
		if ( $term && ! is_wp_error( $term ) ) {
			$term_id = is_array( $term ) ? (int) $term['term_id'] : (int) $term;
			if ( $term_id ) {
				$meta_query[] = array(
					'key'   => 'region',
					'value' => (string) $term_id,
				);
			}
		}
	}

	$query_args = array(
		'post_type'      => 'centro_educativo',
		'post_status'    => array( 'publish', 'draft' ),
		'title'          => $nombre,
		'posts_per_page' => 1,
		'fields'         => 'ids',
	);

	if ( ! empty( $meta_query ) && count( $meta_query ) > 1 ) {
		$query_args['meta_query'] = $meta_query;
	}

	// WP_Query no soporta búsqueda exacta por título, usar filtro.
	add_filter( 'posts_where', 'gnf_exact_title_filter', 10, 2 );
	$query = new WP_Query( $query_args );
	remove_filter( 'posts_where', 'gnf_exact_title_filter', 10, 2 );

	if ( $query->have_posts() ) {
		return $query->posts[0];
	}

	return null;
}

/**
 * Filtro para búsqueda exacta por título.
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
 * Ejecutar si se llama directamente.
 */
if ( php_sapi_name() === 'cli' || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
	// Determinar archivo CSV.
	$csv_file = null;
	$dry_run  = false;

	// Parsear argumentos.
	$args = isset( $argv ) ? $argv : array();
	foreach ( $args as $arg ) {
		if ( '--dry-run' === $arg ) {
			$dry_run = true;
		} elseif ( strpos( $arg, '.csv' ) !== false && file_exists( $arg ) ) {
			$csv_file = $arg;
		} elseif ( strpos( $arg, '.csv' ) !== false ) {
			// Intentar como ruta relativa.
			$relative = __DIR__ . '/' . basename( $arg );
			if ( file_exists( $relative ) ) {
				$csv_file = $relative;
			}
		}
	}

	// Archivo por defecto.
	if ( ! $csv_file ) {
		// Preferir el CSV grande si existe (escuelas-mep.csv).
		$seeders_csv = __DIR__ . '/escuelas-mep.csv';
		$plugin_root = dirname( __DIR__ ) . '/escuelas-mep.csv';
		if ( file_exists( $seeders_csv ) ) {
			$csv_file = $seeders_csv;
		} elseif ( file_exists( $plugin_root ) ) {
			$csv_file = $plugin_root;
		} else {
			// Fallback: CSV pequeño de ejemplo.
			$csv_file = __DIR__ . '/centros-mep.csv';
		}
	}

	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		WP_CLI::log( '=== Importador de Centros Educativos MEP ===' );
		WP_CLI::log( "Archivo: {$csv_file}" );
		if ( $dry_run ) {
			WP_CLI::log( '(Modo dry-run, no se realizarán cambios)' );
		}
		WP_CLI::log( '' );
	} else {
		echo "Importando centros desde: {$csv_file}\n";
		if ( $dry_run ) {
			echo "(Modo dry-run, no se realizarán cambios)\n";
		}
	}

	$stats = gnf_import_centros_from_csv( $csv_file, $dry_run );

	// Mostrar resultados.
	$results = array(
		'Total procesados' => $stats['total'],
		'Creados'          => $stats['created'],
		'Actualizados'     => $stats['updated'],
		'Omitidos'         => $stats['skipped'],
		'Duplicados en CSV' => $stats['duplicates'],
	);

	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		WP_CLI::log( '' );
		WP_CLI::log( '=== Resultados ===' );
		foreach ( $results as $label => $value ) {
			WP_CLI::log( "  {$label}: {$value}" );
		}

		if ( ! empty( $stats['errors'] ) ) {
			WP_CLI::warning( 'Se encontraron ' . count( $stats['errors'] ) . ' errores:' );
			foreach ( $stats['errors'] as $error ) {
				WP_CLI::log( "  - {$error}" );
			}
		} else {
			WP_CLI::success( 'Importación completada sin errores.' );
		}
	} else {
		echo "\nResultados:\n";
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
