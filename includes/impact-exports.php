<?php
/** Collective exports from an already authorized report snapshot. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function gnf_impact_export_error( $code, $message, $status = 500 ) {
	return new WP_Error( $code, $message, array( 'status' => $status ) );
}

function gnf_impact_export_ready( $scoped ) {
	if ( is_wp_error( $scoped ) ) {
		return $scoped;
	}
	if ( ! is_array( $scoped ) || empty( $scoped['ready'] ) ) {
		return gnf_impact_export_error( 'impact_preparing', 'El reporte se esta preparando. Vuelva a intentar en unos momentos.', 503 );
	}
	return true;
}

function gnf_impact_export_escape( $value ) {
	return htmlspecialchars( (string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
}

function gnf_impact_export_number( $value ) {
	return is_numeric( $value ) && is_finite( (float) $value ) ? 0 + $value : 0;
}

/** Use only the scoped catalog, never the global metric catalog or cached URLs. */
function gnf_impact_export_catalog( $scoped ) {
	return (array) ( $scoped['impact']['catalog'] ?? array() );
}

function gnf_impact_export_source_label( $metric ) {
	$labels = array( 'inscripcion' => 'Inscripción', 'eco-retos' => 'Eco Retos', 'center' => 'Centro y matrícula', 'entry' => 'Participación en retos', 'response' => 'Respuestas de formularios' );
	$source = (string) ( $metric['source'] ?? '' );
	return (string) ( $metric['sourceLabel'] ?? ( $labels[ $source ] ?? ( $source ?: 'Sin fuente' ) ) );
}

/** Metadata shared by every workbook and PDF, including implicitly restricted scopes. */
function gnf_impact_export_metadata( $scoped ) {
	$filters = (array) ( $scoped['filters'] ?? array() );
	$region = (int) ( $filters['region'] ?? 0 );
	$labels = array();
	foreach ( (array) ( $scoped['impact']['regions'] ?? array() ) as $item ) {
		$labels[] = (string) ( $item['label'] ?? '' );
	}
	$sources = array();
	foreach ( gnf_impact_export_catalog( $scoped ) as $metric ) {
		$sources[] = gnf_impact_export_source_label( $metric );
	}
	return array(
		'Año' => (int) ( $scoped['year'] ?? 0 ),
		'Fecha de corte' => (string) ( $scoped['generatedAt'] ?? '' ),
		'Criterio' => ( 'approved' === ( $filters['mode'] ?? 'active' ) ? 'Validados' : 'Reportados' ),
		'Alcance' => $region ? 'Región ' . $region : 'Total del alcance autorizado',
		'Direcciones Regionales' => implode( '; ', array_unique( $labels ) ),
		'Circuito' => (string) ( $filters['circuit'] ?? '' ) ?: 'Todos los circuitos autorizados',
		'Fuentes' => implode( '; ', array_unique( $sources ) ) ?: 'Sin indicadores',
	);
}

/** Generate fresh nonce URLs; arbitrary request fields and secrets are never copied. */
function gnf_get_impact_export_urls( $year, $filters = array() ) {
	$filters = is_array( $filters ) ? $filters : array();
	$args = array( 'action' => 'gnf_export_impact', 'year' => (int) $year );
	foreach ( array( 'region', 'circuit', 'mode' ) as $key ) {
		if ( isset( $filters[ $key ] ) && is_scalar( $filters[ $key ] ) ) {
			$args[ $key ] = $filters[ $key ];
		}
	}
	$args['sources'] = array_values( array_filter( (array) ( $filters['sources'] ?? array() ), 'is_string' ) );
	$variants = array(
		'indicators' => array( 'indicators', 'xlsx', 'summary' ),
		'centros' => array( 'centros', 'xlsx', 'full' ),
		'retos' => array( 'retos', 'xlsx', 'full' ),
		'pdfSummary' => array( 'indicators', 'pdf', 'summary' ),
		'pdfFull' => array( 'indicators', 'pdf', 'full' ),
	);
	$urls = array();
	foreach ( $variants as $key => $variant ) {
		$query = array_merge( $args, array( 'dataset' => $variant[0], 'format' => $variant[1], 'detail' => $variant[2] ) );
		// wp_nonce_url returns HTML-escaped text; API/React consumers need a raw URL.
		$urls[ $key ] = html_entity_decode( wp_nonce_url( add_query_arg( $query, admin_url( 'admin-post.php' ) ), 'gnf_export_impact' ), ENT_QUOTES, 'UTF-8' );
	}
	return $urls;
}

/** Parse, authorize and scope on every download, even when the URL was signed earlier. */
function gnf_prepare_impact_export_request( $request ) {
	$admin = ( function_exists( 'gnf_rest_is_admin' ) && gnf_rest_is_admin() ) || current_user_can( 'manage_options' );
	if ( ! is_user_logged_in() || ! ( $admin || ( function_exists( 'gnf_rest_is_supervisor' ) && gnf_rest_is_supervisor() ) ) ) {
		return gnf_impact_export_error( 'impact_forbidden', 'Sin permisos para exportar el Panel de Impacto.', 403 );
	}
	$request = is_array( $request ) ? wp_unslash( $request ) : array();
	foreach ( array( '_wpnonce', 'year', 'region', 'circuit', 'mode', 'dataset', 'format', 'detail' ) as $key ) {
		if ( isset( $request[ $key ] ) && ! is_scalar( $request[ $key ] ) ) {
			return gnf_impact_export_error( 'impact_invalid_request', 'Parametros de exportacion invalidos.', 400 );
		}
	}
	if ( ! wp_verify_nonce( (string) ( $request['_wpnonce'] ?? '' ), 'gnf_export_impact' ) ) {
		return gnf_impact_export_error( 'impact_nonce_expired', 'El enlace ha caducado. Actualice el panel y vuelva a descargar.', 403 );
	}
	$year = (string) ( $request['year'] ?? gmdate( 'Y' ) );
	$region = (string) ( $request['region'] ?? '0' );
	if ( ! preg_match( '/^[0-9]{4}$/D', $year ) || (int) $year < 2000 || (int) $year > 2100 || ! preg_match( '/^[0-9]+$/D', $region ) ) {
		return gnf_impact_export_error( 'impact_invalid_request', 'Anio o region invalidos.', 400 );
	}
	$dataset = (string) ( $request['dataset'] ?? 'indicators' );
	$format = (string) ( $request['format'] ?? 'xlsx' );
	$detail = (string) ( $request['detail'] ?? 'summary' );
	$mode = (string) ( $request['mode'] ?? 'active' );
	if ( ! in_array( $dataset, array( 'indicators', 'centros', 'retos' ), true ) || ! in_array( $format, array( 'xlsx', 'pdf' ), true ) || ! in_array( $detail, array( 'summary', 'full' ), true ) || ! in_array( $mode, array( 'active', 'approved' ), true ) ) {
		return gnf_impact_export_error( 'impact_invalid_request', 'Tipo de exportacion no admitido.', 400 );
	}
	$sources = $request['sources'] ?? array();
	if ( is_string( $sources ) ) {
		$sources = '' === $sources ? array() : explode( ',', $sources );
	}
	if ( ! is_array( $sources ) ) {
		return gnf_impact_export_error( 'impact_invalid_request', 'Fuentes invalidas.', 400 );
	}
	foreach ( $sources as $source ) {
		if ( ! is_string( $source ) || ! preg_match( '/^[a-zA-Z0-9_-]+$/D', $source ) ) {
			return gnf_impact_export_error( 'impact_invalid_request', 'Fuentes invalidas.', 400 );
		}
	}
	if ( ! function_exists( 'gnf_get_report_snapshot' ) || ! function_exists( 'gnf_scope_report_snapshot' ) ) {
		return gnf_impact_export_error( 'impact_preparing', 'El servicio de reportes se esta preparando. Vuelva a intentar.', 503 );
	}
	$snapshot = gnf_get_report_snapshot( (int) $year );
	if ( is_wp_error( $snapshot ) ) {
		return $snapshot;
	}
	$scoped = gnf_scope_report_snapshot( $snapshot, (int) $region, trim( (string) ( $request['circuit'] ?? '' ) ), $mode, array_values( array_unique( $sources ) ) );
	$ready = gnf_impact_export_ready( $scoped );
	if ( is_wp_error( $ready ) ) {
		return $ready;
	}
	return array( 'scoped' => $scoped, 'dataset' => $dataset, 'format' => $format, 'detail' => $detail );
}

/** Strings stay inline text (not formulas); nested/raw payloads are not exported. */
function gnf_impact_export_cell( $value ) {
	if ( is_array( $value ) ) {
		$value = implode( '; ', array_filter( $value, 'is_scalar' ) );
	}
	if ( ! is_scalar( $value ) && null !== $value ) {
		return '';
	}
	return is_string( $value ) && preg_match( '/^[\x00-\x20]*[=+\-@]/', $value ) ? "'" . $value : ( $value ?? '' );
}

function gnf_impact_export_add_row( $writer, $row, $header = false ) {
	$writer->add_row( array_map( 'gnf_impact_export_cell', $row ), $header );
}

/** The snapshot's center list is territorial, but its challenge list is not source-filtered. */
function gnf_impact_export_retos( $center, $scoped ) {
	$sources = (array) ( $scoped['filters']['sources'] ?? array() );
	return array_values( array_filter( (array) ( $center['retos'] ?? array() ), static function ( $reto ) use ( $sources ) {
		return ! $sources || in_array( (string) ( $reto['source'] ?? '' ), $sources, true );
	} ) );
}

/** @return string|WP_Error Final path; input must already be scoped by the caller. */
function gnf_write_impact_xlsx( $scoped, $path, $dataset = 'indicators' ) {
	$ready = gnf_impact_export_ready( $scoped );
	if ( is_wp_error( $ready ) ) { return $ready; }
	if ( ! in_array( $dataset, array( 'indicators', 'centros', 'retos' ), true ) ) {
		return gnf_impact_export_error( 'impact_invalid_dataset', 'Conjunto de datos no admitido.', 400 );
	}
	$writer = null;
	try {
		require_once __DIR__ . '/xlsx-writer.php';
		if ( ! function_exists( 'gnf_centros_export_column_map' ) ) { require_once __DIR__ . '/centros-export.php'; }
		if ( ! function_exists( 'gnf_report_indicator_export_rows' ) ) {
			return gnf_impact_export_error( 'impact_rows_unavailable', 'Los indicadores se estan preparando. Vuelva a intentar.', 503 );
		}
		$writer = new GNF_XLSX_Writer( $path, 'Resumen' );
		gnf_impact_export_add_row( $writer, array( 'Panel de Impacto', 'Valor' ), true );
		foreach ( gnf_impact_export_metadata( $scoped ) as $label => $value ) { gnf_impact_export_add_row( $writer, array( $label, $value ) ); }
		$summary_labels = array( 'totalCentros' => 'Centros participantes', 'totalAprobados' => 'Retos aprobados', 'promedioPuntaje' => 'Promedio de puntaje', 'promedioEstrellas' => 'Promedio de estrellas', 'centrosConEvidencias' => 'Centros con evidencias' );
		foreach ( $summary_labels as $key => $label ) { gnf_impact_export_add_row( $writer, array( $label, gnf_impact_export_number( $scoped['summary'][ $key ] ?? 0 ) ) ); }
		$writer->add_sheet( 'Indicadores' );
		$first = true;
		foreach ( gnf_report_indicator_export_rows( $scoped ) as $row ) {
			gnf_impact_export_add_row( $writer, $row, $first ); $first = false;
		}
		$catalog = gnf_impact_export_catalog( $scoped );
		$writer->add_sheet( 'Fuentes' );
		gnf_impact_export_add_row( $writer, array( 'Fuente', 'Fuente clave', 'Indicador', 'Indicador clave', 'Unidad', 'Disponibilidad' ), true );
		foreach ( $catalog as $metric ) {
			gnf_impact_export_add_row( $writer, array( gnf_impact_export_source_label( $metric ), $metric['source'] ?? '', $metric['title'], $metric['key'], $metric['unit'] ?? '', false === ( $metric['available'] ?? true ) ? 'No disponible' : 'Disponible' ) );
		}
		$map = gnf_centros_export_column_map();
		$headers = array_merge( array_keys( $map ), array( 'Puntaje del corte', 'Retos aprobados del corte', 'Estrellas del corte', 'Evidencias del corte' ) );
		foreach ( $catalog as $metric ) { $headers[] = $metric['title'] . ' (' . ( $metric['unit'] ?? '' ) . ')'; }
		$mode = 'approved' === ( $scoped['filters']['mode'] ?? 'active' ) ? 'approved' : 'active';
		$writer->add_sheet( 'Centros' );
		gnf_impact_export_add_row( $writer, $headers, true );
		foreach ( (array) ( $scoped['centros'] ?? array() ) as $center ) {
			$row = array();
			foreach ( $map as $key ) { $row[] = $center['profile'][ $key ] ?? ''; }
			foreach ( array( 'puntaje', 'aprobados', 'estrellas', 'evidencias' ) as $key ) { $row[] = gnf_impact_export_number( $center['stats'][ $key ] ?? 0 ); }
			foreach ( $catalog as $metric ) {
				$value = $center['values'][ $mode ][ $metric['key'] ] ?? null;
				$row[] = false === ( $metric['available'] ?? true ) ? 'No disponible' : ( null === $value ? 'Sin datos' : gnf_impact_export_number( $value ) );
			}
			gnf_impact_export_add_row( $writer, $row );
		}
		if ( 'retos' === $dataset ) {
			// A normalized row per center/reto, with scalar evidence counts only.
			$count_keys = array( 'total' => true );
			foreach ( (array) ( $scoped['centros'] ?? array() ) as $center ) {
				foreach ( gnf_impact_export_retos( $center, $scoped ) as $reto ) {
					foreach ( (array) ( $reto['evidenceCounts'] ?? array() ) as $key => $value ) { if ( is_numeric( $value ) ) { $count_keys[ $key ] = true; } }
				}
			}
			$writer->add_sheet( 'Retos' );
			$headers = array( 'Año', 'Fecha de corte', 'Modo', 'Centro ID', 'Centro educativo', 'Dirección Regional', 'Circuito', 'Reto ID', 'Reto', 'Fuente', 'Estado', 'Puntos' );
			foreach ( $count_keys as $key => $unused ) { $headers[] = 'Evidencias: ' . $key; }
			gnf_impact_export_add_row( $writer, $headers, true );
			foreach ( (array) ( $scoped['centros'] ?? array() ) as $center ) {
				$p = $center['profile'];
				foreach ( gnf_impact_export_retos( $center, $scoped ) as $reto ) {
					$row = array( (int) $scoped['year'], $scoped['generatedAt'] ?? '', $mode, $p['centro_id'] ?? 0, $p['nombre'] ?? '', $p['region_name'] ?? '', $p['circuito'] ?? '', $reto['id'] ?? 0, $reto['title'] ?? '', $reto['source'] ?? '', $reto['state'] ?? '', gnf_impact_export_number( $reto['points'] ?? 0 ) );
					foreach ( $count_keys as $key => $unused ) { $row[] = gnf_impact_export_number( $reto['evidenceCounts'][ $key ] ?? 0 ); }
					gnf_impact_export_add_row( $writer, $row );
				}
			}
		}
		return $writer->close();
	} catch ( Throwable $error ) {
		@unlink( $path );
		return gnf_impact_export_error( 'impact_xlsx_failed', 'No se pudo generar el XLSX. Compruebe el espacio temporal y ZipArchive o PclZip; vuelva a intentar.' );
	} finally {
		unset( $writer );
	}
}

function gnf_impact_export_display_number( $value ) {
	if ( null === $value ) { return 'Sin datos'; }
	$value = gnf_impact_export_number( $value );
	return number_format( $value, floor( $value ) == $value ? 0 : 2, ',', '.' );
}

/** Collective HTML only: no profile, teacher, evidence URLs or form payloads. */
function gnf_render_impact_pdf_html( $scoped, $detail = 'summary' ) {
	$ready = gnf_impact_export_ready( $scoped );
	if ( is_wp_error( $ready ) ) { return $ready; }
	if ( ! in_array( $detail, array( 'summary', 'full' ), true ) ) { return gnf_impact_export_error( 'impact_invalid_detail', 'Detalle no admitido.', 400 ); }
	$e = 'gnf_impact_export_escape';
	$n = 'gnf_impact_export_display_number';
	$metadata = gnf_impact_export_metadata( $scoped );
	$html = '<!doctype html><html lang="es"><head><meta charset="UTF-8"><title>Panel de Impacto</title><style>
	@page { margin: 66px 40px 54px; }
	body { font-family: DejaVu Sans, sans-serif; font-size: 9px; line-height: 1.5; color: #24343a; }
	.page-header { position: fixed; top: -43px; left: 0; right: 0; border-bottom: 2px solid #167b67; padding-bottom: 8px; font-size: 8px; color: #526369; }
	.page-footer { position: fixed; bottom: -30px; left: 0; font-size: 7px; color: #65767c; }
	h1 { font-size: 30px; line-height: 1.2; color: #126552; margin: 5px 0 7px; }
	h2 { font-size: 16px; color: #126552; margin: 22px 0 8px; page-break-after: avoid; }
	h3 { font-size: 11px; margin: 13px 0 5px; page-break-after: avoid; }
	p { margin: 4px 0 9px; } .subtitle { color: #526369; font-size: 11px; }
	.meta { background: #f0f5f4; padding: 10px 13px; margin: 15px 0; }
	.kpis { width: 100%; border-collapse: collapse; margin: 16px 0; }
	.kpis td { width: 25%; padding: 10px 8px; border-top: 3px solid #167b67; background: #f0f5f4; vertical-align: top; }
	.kpi-number { font-size: 23px; color: #126552; font-weight: bold; } .kpi-label { font-size: 8px; }
	table.data { width: 100%; border-collapse: collapse; table-layout: fixed; margin: 7px 0 13px; }
	thead { display: table-header-group; } tfoot { display: table-footer-group; }
	.data th { text-align: left; background: #126552; color: #ffffff; padding: 7px; font-size: 8px; }
	.data td { border-bottom: 1px solid #dce5e4; padding: 7px; vertical-align: top; overflow-wrap: break-word; word-wrap: break-word; }
	.data tr:nth-child(even) td { background: #f4f7f7; } tr { page-break-inside: avoid; }
	.numeric { text-align: right; } .muted { color: #65767c; } .unavailable { color: #8d5320; }
	.chart { margin: 8px 0 15px; page-break-inside: avoid; }
	.chart-title { font-size: 9px; font-weight: bold; margin-bottom: 4px; }
	.chart-row { width: 100%; border-collapse: collapse; } .chart-row td { padding: 3px 0; vertical-align: middle; }
	.bar-track { width: 100%; height: 7px; background: #e2ecea; } .bar-fill { height: 7px; background: #229780; }
	.territorial { page-break-before: always; } .note { border-left: 3px solid #3a879b; padding: 7px 10px; color: #526369; }
	</style></head><body>';
	$html .= '<div class="page-footer">Informe colectivo - Corte: ' . $e( $scoped['generatedAt'] ?? '' ) . '</div>';
	$html .= '<h1>Panel de Impacto</h1><p class="subtitle">' . ( 'full' === $detail ? 'Informe completo' : 'Resumen ejecutivo' ) . ' colectivo / ' . $e( $scoped['year'] ?? '' ) . '</p><div class="meta">';
	foreach ( $metadata as $label => $value ) { $html .= '<div><strong>' . $e( $label ) . ':</strong> ' . $e( $value ) . '</div>'; }
	$html .= '</div><table class="kpis"><tr>';
	foreach ( array( 'totalCentros' => 'Centros participantes', 'totalAprobados' => 'Retos aprobados', 'promedioPuntaje' => 'Puntaje promedio', 'promedioEstrellas' => 'Estrellas promedio' ) as $key => $label ) {
		$html .= '<td><div class="kpi-number">' . $n( $scoped['summary'][ $key ] ?? 0 ) . '</div><div class="kpi-label">' . $e( $label ) . '</div></td>';
	}
	$html .= '</tr></table><p class="muted">Centros con evidencias: ' . $n( $scoped['summary']['centrosConEvidencias'] ?? 0 ) . '</p>';
	if ( ! empty( $scoped['stale'] ) ) { $html .= '<p class="note">Corte anterior disponible mientras se actualiza el reporte.</p>'; }
	$groups = array();
	foreach ( gnf_impact_export_catalog( $scoped ) as $metric ) { $groups[ (string) ( $metric['source'] ?? '' ) ][] = $metric; }
	$chart_candidates = array_values( array_filter( gnf_impact_export_catalog( $scoped ), static function ( $metric ) use ( $scoped ) {
		return false !== ( $metric['available'] ?? true ) && gnf_impact_export_number( $scoped['impact']['total']['values'][ $metric['key'] ] ?? 0 ) > 0;
	} ) );
	// Favor measured results over participation counts; charts never replace totals.
	usort( $chart_candidates, static function ( $a, $b ) {
		return ( ( 'centros' !== ( $b['unit'] ?? '' ) ? 2 : 0 ) + ( 'sum' === ( $b['operation'] ?? '' ) ? 1 : 0 ) ) <=> ( ( 'centros' !== ( $a['unit'] ?? '' ) ? 2 : 0 ) + ( 'sum' === ( $a['operation'] ?? '' ) ? 1 : 0 ) );
	} );
	$chart_keys = array_column( array_slice( $chart_candidates, 0, 6 ), 'key' );
	if ( ! $groups ) { $html .= '<p class="note">Sin indicadores para los filtros seleccionados.</p>'; }
	foreach ( $groups as $metrics ) {
		$html .= '<h2>Fuente: ' . $e( gnf_impact_export_source_label( $metrics[0] ) ) . '</h2><table class="data"><thead><tr><th style="width:58%">Indicador</th><th style="width:19%">Unidad</th><th style="width:23%">Total del alcance</th></tr></thead><tbody>';
		foreach ( $metrics as $metric ) {
			$available = false !== ( $metric['available'] ?? true );
			$html .= '<tr><td>' . $e( $metric['title'] ) . '</td><td>' . $e( $metric['unit'] ?? '' ) . '</td><td class="numeric' . ( $available ? '' : ' unavailable' ) . '">' . ( $available ? $n( $scoped['impact']['total']['values'][ $metric['key'] ] ?? null ) : 'No disponible' ) . '</td></tr>';
		}
		$html .= '</tbody></table>';
		foreach ( $metrics as $metric ) {
			$total = gnf_impact_export_number( $scoped['impact']['total']['values'][ $metric['key'] ] ?? 0 );
			if ( ! in_array( $metric['key'], $chart_keys, true ) || $total <= 0 ) { continue; }
			$regions = array_values( (array) ( $scoped['impact']['regions'] ?? array() ) );
			usort( $regions, static function ( $a, $b ) use ( $metric ) { return ( $b['values'][ $metric['key'] ] ?? 0 ) <=> ( $a['values'][ $metric['key'] ] ?? 0 ); } );
			if ( ! $regions ) { continue; }
			$html .= '<div class="chart"><div class="chart-title">' . $e( $metric['title'] ) . ' - aporte regional al total</div><table class="chart-row">';
			foreach ( array_slice( $regions, 0, 5 ) as $region ) {
				$value = gnf_impact_export_number( $region['values'][ $metric['key'] ] ?? 0 );
				$width = number_format( max( 0, min( 100, 100 * $value / $total ) ), 2, '.', '' );
				$html .= '<tr><td style="width:36%;padding-right:8px">' . $e( $region['label'] ?? '' ) . '</td><td style="width:42%"><div class="bar-track"><div class="bar-fill" style="width:' . $width . '%"></div></div></td><td class="numeric" style="width:22%">' . $n( $value ) . ' ' . $e( $metric['unit'] ?? '' ) . '</td></tr>';
			}
			$html .= '</table><div class="muted">Hasta cinco DRE con mayor aporte; barras como proporción del total autorizado de este indicador.</div></div>';
		}
	}
	if ( 'full' === $detail ) {
		foreach ( array( 'regions' => 'Por Dirección Regional', 'circuits' => 'Por circuito' ) as $collection => $heading ) {
			$html .= '<div class="territorial"><h2>' . $heading . '</h2>';
			if ( empty( $scoped['impact'][ $collection ] ) ) { $html .= '<p class="muted">Sin territorios para los filtros seleccionados.</p>'; }
			foreach ( $groups as $metrics ) {
				// Compact source tables bound row growth while retaining every measurement.
				foreach ( array_chunk( $metrics, 4 ) as $columns ) {
					$html .= '<h3>Fuente: ' . $e( gnf_impact_export_source_label( $columns[0] ) ) . '</h3><table class="data"><thead><tr><th style="width:28%">Territorio</th>';
					foreach ( $columns as $metric ) { $html .= '<th style="width:' . ( 72 / count( $columns ) ) . '%">' . $e( $metric['title'] ) . '<br>' . $e( $metric['unit'] ?? '' ) . '</th>'; }
					$html .= '</tr></thead><tbody>';
					foreach ( (array) ( $scoped['impact'][ $collection ] ?? array() ) as $territory ) {
						$label = ( 'circuits' === $collection ? ( $territory['regionName'] ?? '' ) . ' / ' : '' ) . ( $territory['label'] ?? '' );
						$html .= '<tr><td>' . $e( $label ) . '</td>';
						foreach ( $columns as $metric ) {
							$value = false === ( $metric['available'] ?? true ) ? 'No disponible' : $n( $territory['values'][ $metric['key'] ] ?? null );
							$html .= '<td class="numeric">' . $e( $value ) . '</td>';
						}
						$html .= '</tr>';
					}
					$html .= '</tbody></table>';
				}
			}
			$html .= '</div>';
		}
	}
	return $html . '</body></html>';
}

/** @return true|WP_Error Uses only the already bundled Dompdf. */
function gnf_generate_impact_pdf( $scoped, $path, $detail = 'summary' ) {
	$html = gnf_render_impact_pdf_html( $scoped, $detail );
	if ( is_wp_error( $html ) ) { return $html; }
	$previous_errors = error_reporting();
	// Bundled PHP 7.4-compatible Composer packages emit deprecations on PHP 8.4.
	error_reporting( $previous_errors & ~E_DEPRECATED & ~E_USER_DEPRECATED );
	try {
		if ( ! class_exists( '\Dompdf\Dompdf' ) ) {
			$autoload = dirname( __DIR__ ) . '/vendor/autoload.php';
			if ( file_exists( $autoload ) ) { require_once $autoload; }
		}
		if ( ! class_exists( '\Dompdf\Dompdf' ) ) { return gnf_impact_export_error( 'impact_pdf_unavailable', 'El generador PDF no esta disponible. Vuelva a intentar o descargue el XLSX.' ); }
		$options = new \Dompdf\Options();
		$options->set( 'defaultFont', 'DejaVu Sans' );
		$options->set( 'isRemoteEnabled', false );
		$options->set( 'isPhpEnabled', false );
		$options->set( 'isJavascriptEnabled', false );
		$dompdf = new \Dompdf\Dompdf( $options );
		$dompdf->loadHtml( $html, 'UTF-8' );
		$dompdf->setPaper( 'A4', 'portrait' );
		$dompdf->render();
		$canvas = $dompdf->getCanvas();
		$font = $dompdf->getFontMetrics()->getFont( 'DejaVu Sans', 'normal' );
		$canvas->page_text( 30, 20, 'GUARDIANES DE LA NATURALEZA / PANEL DE IMPACTO / ' . (int) ( $scoped['year'] ?? 0 ), $font, 6, array( 0.32, 0.39, 0.41 ) );
		$canvas->page_script( static function ( $page_number, $page_count, $page_canvas, $font_metrics ) {
			$page_canvas->line( 30, 36, 565, 36, array( 0.08, 0.48, 0.40 ), 1 );
		} );
		$canvas->page_text( 495, 811, '{PAGE_NUM} / {PAGE_COUNT}', $font, 8, array( 0.32, 0.39, 0.41 ) );
		if ( false === @file_put_contents( $path, $dompdf->output() ) ) { throw new RuntimeException( 'PDF write failed' ); }
		return true;
	} catch ( Throwable $error ) {
		@unlink( $path );
		return gnf_impact_export_error( 'impact_pdf_failed', 'No se pudo generar el PDF. Compruebe el espacio temporal; vuelva a intentar o descargue el XLSX.' );
	} finally {
		error_reporting( $previous_errors );
	}
}

function gnf_impact_export_die( $error ) {
	$data = $error->get_error_data();
	$status = is_array( $data ) ? (int) ( $data['status'] ?? 500 ) : 500;
	if ( ! in_array( $status, array( 400, 401, 403, 500, 503 ), true ) ) { $status = 500; }
	if ( 503 === $status && ! headers_sent() ) { header( 'Retry-After: 30' ); }
	$panel = function_exists( 'gnf_rest_is_admin' ) && gnf_rest_is_admin() ? '/panel-admin/' : '/panel-supervisor/';
	$link = function_exists( 'home_url' ) ? '<p><a href="' . gnf_impact_export_escape( add_query_arg( array( 'p' => 'reportes' ), home_url( $panel ) ) ) . '">Volver al panel</a></p>' : '';
	wp_die( gnf_impact_export_escape( $error->get_error_message() ) . $link, 'Exportacion de impacto', array( 'response' => $status ) );
}

/** No center profiles or other PII are persisted in the PDF queue. */
function gnf_impact_pdf_payload( $scoped ) {
	return array_intersect_key( $scoped, array_flip( array( 'ready', 'year', 'generatedAt', 'stale', 'impact', 'summary', 'filters' ) ) );
}

function gnf_impact_pdf_job_key( $scoped, $detail ) {
	$user = get_current_user_id();
	$admin = function_exists( 'gnf_rest_is_admin' ) && gnf_rest_is_admin();
	$regions = function_exists( 'gnf_get_user_regions' ) ? array_map( 'intval', (array) gnf_get_user_regions( $user ) ) : array();
	sort( $regions );
	$territories = array_keys( (array) ( $scoped['impact']['circuits'] ?? array() ) ); sort( $territories );
	$centers = array();
	foreach ( (array) ( $scoped['centros'] ?? array() ) as $center ) {
		$p = $center['profile'];
		$circuit = function_exists( 'gnf_normalize_circuito' ) ? gnf_normalize_circuito( $p['circuito'] ?? '' ) : trim( (string) ( $p['circuito'] ?? '' ) );
		$centers[] = array( (int) ( $p['centro_id'] ?? 0 ), (int) ( $p['region_id'] ?? 0 ), $circuit );
	}
	sort( $centers );
	$filters = (array) ( $scoped['filters'] ?? array() );
	$filters['sources'] = (array) ( $filters['sources'] ?? array() ); sort( $filters['sources'] ); ksort( $filters );
	$signature = array( $user, $admin, $regions, function_exists( 'gnf_get_user_circuito' ) ? gnf_get_user_circuito( $user ) : '', $territories, $centers, $scoped['year'], $scoped['generatedAt'] ?? '', $filters, $detail );
	return 'gnf_impact_pdf_job_' . hash( 'sha256', serialize( $signature ) );
}

function gnf_impact_pdf_invalidate_option( $key ) {
	wp_cache_delete( $key, 'options' ); wp_cache_delete( 'notoptions', 'options' );
}

/** Always read durable ownership directly, not from a potentially stale object cache. */
function gnf_impact_pdf_read( $key ) {
	global $wpdb;
	$raw = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $key ) );
	if ( ! empty( $wpdb->last_error ) ) { throw new RuntimeException( 'PDF persistence read failed' ); }
	return null === $raw ? false : maybe_unserialize( $raw );
}

/** @return int|false One inserted row, zero for a duplicate, false for SQL failure. */
function gnf_impact_pdf_insert( $key, $value ) {
	global $wpdb;
	$result = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $key, maybe_serialize( $value ) ) );
	gnf_impact_pdf_invalidate_option( $key );
	return $result;
}

/** A single SQL statement fences both the exact lease token and expected job generation. */
function gnf_impact_pdf_owned_update( $key, $expected, $value, $lock_key, $lock ) {
	global $wpdb;
	if ( (int) $lock['expires'] <= time() ) { return 0; }
	$result = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} job INNER JOIN {$wpdb->options} lease ON lease.option_name = %s AND lease.option_value = %s SET job.option_value = %s WHERE job.option_name = %s AND job.option_value = %s", $lock_key, maybe_serialize( $lock ), maybe_serialize( $value ), $key, maybe_serialize( $expected ) ) );
	gnf_impact_pdf_invalidate_option( $key );
	return $result;
}

/** Atomically compare-and-delete so a stale worker cannot release a newer lease. */
function gnf_impact_pdf_compare_delete( $key, $value ) {
	global $wpdb;
	$removed = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $key, maybe_serialize( $value ) ) );
	gnf_impact_pdf_invalidate_option( $key );
	return 1 === $removed;
}

function gnf_impact_pdf_private_directory() {
	$root = rtrim( sys_get_temp_dir(), '/\\' ) . DIRECTORY_SEPARATOR . 'gnf-impact-private';
	if ( ! is_dir( $root ) && ! @mkdir( $root, 0700, true ) && ! is_dir( $root ) ) { throw new RuntimeException( 'Private temp directory unavailable' ); }
	$resolved = realpath( $root );
	foreach ( array( ABSPATH, defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : '', $_SERVER['DOCUMENT_ROOT'] ?? '' ) as $public_root ) {
		$public_root = $public_root ? realpath( $public_root ) : false;
		if ( $public_root && 0 === strpos( strtolower( $resolved . DIRECTORY_SEPARATOR ), strtolower( rtrim( $public_root, '/\\' ) . DIRECTORY_SEPARATOR ) ) ) { throw new RuntimeException( 'Temp directory must not be public' ); }
	}
	return $resolved;
}

function gnf_impact_pdf_is_private_file( $path ) {
	if ( ! is_string( $path ) || ! is_file( $path ) || is_link( $path ) ) { return false; }
	try { return realpath( dirname( $path ) ) === gnf_impact_pdf_private_directory() && 0 === strpos( basename( $path ), 'impact-' ); }
	catch ( Throwable $error ) { return false; }
}

/** A single job per event bounds cleanup work; no public upload directory is used. */
function gnf_cleanup_impact_pdf_job( $key, $force = false, $expected = null ) {
	global $wpdb;
	if ( ! preg_match( '/^gnf_impact_pdf_job_[a-f0-9]{64}$/D', (string) $key ) ) { return false; }
	try {
		$job = gnf_impact_pdf_read( $key );
		if ( ! is_array( $job ) || ( null !== $expected && $job !== $expected ) || ( ! $force && (int) $job['expires'] > time() ) ) { return false; }
		$lock_key = str_replace( 'gnf_impact_pdf_job_', 'gnf_impact_pdf_lock_', $key );
		$lock = gnf_impact_pdf_read( $lock_key );
		$removed = $wpdb->query( $wpdb->prepare( "DELETE job FROM {$wpdb->options} job LEFT JOIN {$wpdb->options} lease ON lease.option_name = %s WHERE job.option_name = %s AND job.option_value = %s AND COALESCE(lease.option_value, '') = %s", $lock_key, $key, maybe_serialize( $job ), $lock ? maybe_serialize( $lock ) : '' ) );
		gnf_impact_pdf_invalidate_option( $key );
		if ( 1 !== $removed ) { return false; }
		// Capture the lease before deletion; never read and release a successor lease.
		if ( $lock ) { gnf_impact_pdf_compare_delete( $lock_key, $lock ); }
		if ( gnf_impact_pdf_is_private_file( $job['path'] ?? null ) && ! @unlink( $job['path'] ) ) {
			gnf_impact_pdf_insert( $key, $job );
			wp_schedule_single_event( time() + 30, 'gnf_cleanup_impact_pdf_job', array( $key ) );
			return false;
		}
		return true;
	} catch ( Throwable $error ) { return false; }
}

/** Called only after the request has reauthorized and scoped the current snapshot. */
function gnf_get_impact_pdf_job( $scoped, $detail = 'summary' ) {
	try { return gnf_prepare_impact_pdf_job( $scoped, $detail ); }
	catch ( Throwable $error ) { return gnf_impact_export_error( 'impact_pdf_persistence_failed', 'No se pudo guardar la preparación del PDF. Vuelva al panel y reintente.', 503 ); }
}

function gnf_prepare_impact_pdf_job( $scoped, $detail ) {
	$ready = gnf_impact_export_ready( $scoped ); if ( is_wp_error( $ready ) ) { return $ready; }
	if ( ! in_array( $detail, array( 'summary', 'full' ), true ) ) { return gnf_impact_export_error( 'impact_invalid_detail', 'Detalle no admitido.', 400 ); }
	$key = gnf_impact_pdf_job_key( $scoped, $detail );
	$job = gnf_impact_pdf_read( $key );
	if ( ! $job ) {
		$new = array( 'generation' => bin2hex( random_bytes( 16 ) ), 'userId' => get_current_user_id(), 'status' => 'queued', 'created' => time(), 'deadline' => time() + 900, 'expires' => time() + 3600, 'detail' => $detail, 'payload' => gnf_impact_pdf_payload( $scoped ) );
		$inserted = gnf_impact_pdf_insert( $key, $new );
		if ( false === $inserted ) { throw new RuntimeException( 'PDF job insert failed' ); }
		if ( 1 === $inserted ) {
			$scheduled = wp_schedule_single_event( time() + 1, 'gnf_run_impact_pdf_job', array( $key ) );
			$cleanup = wp_schedule_single_event( $new['expires'], 'gnf_cleanup_impact_pdf_job', array( $key ) );
			if ( ! $scheduled || ! $cleanup ) { gnf_cleanup_impact_pdf_job( $key, true, $new ); return gnf_impact_export_error( 'impact_pdf_queue_failed', 'No se pudo programar el PDF. Vuelva al panel y reintente.', 503 ); }
		}
		$job = gnf_impact_pdf_read( $key );
	}
	if ( ! is_array( $job ) ) { throw new RuntimeException( 'PDF job was not persisted' ); }
	if ( ! is_array( $job ) || (int) ( $job['userId'] ?? 0 ) !== get_current_user_id() ) { return gnf_impact_export_error( 'impact_pdf_forbidden', 'No tiene acceso a este archivo.', 403 ); }
	if ( (int) $job['expires'] <= time() || ( 'ready' !== $job['status'] && (int) $job['deadline'] <= time() ) || 'failed' === $job['status'] ) {
		gnf_cleanup_impact_pdf_job( $key, true, $job );
		return gnf_impact_export_error( 'impact_pdf_job_failed', 'No se pudo preparar el PDF a tiempo. Vuelva al panel y reintente; tambien puede descargar el XLSX.', 503 );
	}
	if ( 'ready' === $job['status'] ) {
		if ( ! gnf_impact_pdf_is_private_file( $job['path'] ?? null ) ) {
			gnf_cleanup_impact_pdf_job( $key, true, $job );
			return gnf_impact_export_error( 'impact_pdf_file_missing', 'El archivo temporal ya no esta disponible. Vuelva al panel y reintente.', 503 );
		}
		return array( 'key' => $key, 'status' => 'ready', 'path' => $job['path'], 'record' => $job );
	}
	$lock_key = str_replace( 'gnf_impact_pdf_job_', 'gnf_impact_pdf_lock_', $key );
	$lock = gnf_impact_pdf_read( $lock_key );
	if ( ( ! $lock || (int) $lock['expires'] < time() ) && ! wp_next_scheduled( 'gnf_run_impact_pdf_job', array( $key ) ) && ! wp_schedule_single_event( time() + 1, 'gnf_run_impact_pdf_job', array( $key ) ) ) { throw new RuntimeException( 'PDF worker scheduling failed' ); }
	return array( 'key' => $key, 'status' => 'queued' );
}

function gnf_run_impact_pdf_job( $key ) {
	if ( ! preg_match( '/^gnf_impact_pdf_job_[a-f0-9]{64}$/D', (string) $key ) ) { return; }
	try { $job = gnf_impact_pdf_read( $key ); }
	catch ( Throwable $error ) { return; }
	if ( ! is_array( $job ) || in_array( $job['status'], array( 'ready', 'failed' ), true ) ) { return; }
	if ( (int) $job['deadline'] <= time() ) { gnf_cleanup_impact_pdf_job( $key, true, $job ); return; }
	$lock_key = str_replace( 'gnf_impact_pdf_job_', 'gnf_impact_pdf_lock_', $key );
	try { $old = gnf_impact_pdf_read( $lock_key ); }
	catch ( Throwable $error ) { return; }
	if ( $old && (int) $old['expires'] < time() ) { gnf_impact_pdf_compare_delete( $lock_key, $old ); }
	$lock = array( 'token' => bin2hex( random_bytes( 16 ) ), 'expires' => time() + 600 );
	if ( 1 !== gnf_impact_pdf_insert( $lock_key, $lock ) ) { return; }
	$path = null;
	$claimed = false;
	try {
		// Another worker may have finished between the first read and lease acquisition.
		$current = gnf_impact_pdf_read( $key );
		if ( ! is_array( $current ) || in_array( $current['status'], array( 'ready', 'failed' ), true ) || (int) $current['deadline'] <= time() ) { return; }
		$job = $current;
		if ( function_exists( 'wp_raise_memory_limit' ) ) { wp_raise_memory_limit( 'admin' ); }
		if ( function_exists( 'set_time_limit' ) ) { @set_time_limit( 300 ); }
		$path = gnf_impact_pdf_private_directory() . DIRECTORY_SEPARATOR . 'impact-' . bin2hex( random_bytes( 16 ) ) . '.pdf';
		$handle = @fopen( $path, 'x' );
		if ( ! $handle ) { throw new RuntimeException( 'Private file unavailable' ); }
		fclose( $handle );
		@chmod( $path, 0600 );
		// Persist the private path before rendering so TTL cleanup also handles fatal timeouts.
		$working = $job; $working['status'] = 'working'; $working['path'] = $path;
		if ( 1 !== gnf_impact_pdf_owned_update( $key, $job, $working, $lock_key, $lock ) ) { return; }
		if ( gnf_impact_pdf_is_private_file( $job['path'] ?? null ) ) { @unlink( $job['path'] ); }
		$job = $working; $claimed = true;
		$result = gnf_generate_impact_pdf( $job['payload'], $path, $job['detail'] );
		if ( is_wp_error( $result ) ) { throw new RuntimeException( 'PDF rendering failed' ); }
		// Do not publish after cancellation, TTL expiry, or lease takeover.
		if ( (int) $job['expires'] <= time() ) { return; }
		$published = $job; $published['status'] = 'ready'; unset( $published['payload'] );
		$saved = gnf_impact_pdf_owned_update( $key, $job, $published, $lock_key, $lock );
		// On SQL failure the tracked working file remains recoverable; never claim ready.
		if ( 1 === $saved || false === $saved ) { $path = null; }
	} catch ( Throwable $error ) {
		if ( $claimed ) {
			$failed = $job; $failed['status'] = 'failed'; unset( $failed['payload'] );
			gnf_impact_pdf_owned_update( $key, $job, $failed, $lock_key, $lock );
		}
	} finally {
		if ( $path && gnf_impact_pdf_is_private_file( $path ) ) { @unlink( $path ); }
		gnf_impact_pdf_compare_delete( $lock_key, $lock );
	}
}

function gnf_render_impact_export_waiting_html( $scoped ) {
	$panel = function_exists( 'gnf_rest_is_admin' ) && gnf_rest_is_admin() ? '/panel-admin/' : '/panel-supervisor/';
	$filters = array_intersect_key( (array) ( $scoped['filters'] ?? array() ), array_flip( array( 'region', 'circuit', 'mode', 'sources' ) ) );
	$filters['sources'] = implode( ',', (array) ( $filters['sources'] ?? array() ) );
	$url = add_query_arg( array_merge( $filters, array( 'p' => 'reportes', 'year' => (int) $scoped['year'] ) ), home_url( $panel ) );
	return '<!doctype html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta http-equiv="refresh" content="5"><title>Preparando PDF</title></head><body style="font-family:system-ui,sans-serif;max-width:640px;margin:60px auto;padding:24px;color:#24343a"><h1>Preparando Panel de Impacto</h1><p>El PDF se esta generando en segundo plano. La descarga comenzara automaticamente cuando este listo.</p><p><a href="' . gnf_impact_export_escape( $url ) . '">Volver al panel</a></p></body></html>';
}

function gnf_handle_export_impact() {
	$prepared = gnf_prepare_impact_export_request( $_GET );
	if ( is_wp_error( $prepared ) ) { gnf_impact_export_die( $prepared ); return; }
	$pdf_job = null;
	if ( 'pdf' === $prepared['format'] ) {
		$pdf_job = gnf_get_impact_pdf_job( $prepared['scoped'], $prepared['detail'] );
		if ( is_wp_error( $pdf_job ) ) { gnf_impact_export_die( $pdf_job ); return; }
		if ( 'ready' !== $pdf_job['status'] ) {
			nocache_headers(); header( 'Content-Type: text/html; charset=UTF-8' ); header( 'Cache-Control: private, no-store' );
			echo gnf_render_impact_export_waiting_html( $prepared['scoped'] ); exit;
		}
	}
	$path = $pdf_job ? $pdf_job['path'] : wp_tempnam( 'gnf-impact-xlsx' );
	if ( ! $path ) { gnf_impact_export_die( gnf_impact_export_error( 'impact_temp_failed', 'No se pudo crear el archivo temporal. Vuelva a intentar.' ) ); return; }
	$error = null;
	try {
		$result = $pdf_job ? true : gnf_write_impact_xlsx( $prepared['scoped'], $path, $prepared['dataset'] );
		if ( is_wp_error( $result ) ) { $error = $result; }
		if ( ! $error ) {
			if ( function_exists( 'gnf_prepare_file_download_response' ) ) { gnf_prepare_file_download_response(); }
			nocache_headers();
			if ( headers_sent() ) { throw new RuntimeException( 'Download headers already sent' ); }
			$filename = 'impacto-' . (int) $prepared['scoped']['year'] . '-' . $prepared['dataset'] . '-' . $prepared['detail'] . '.' . $prepared['format'];
			header( 'Content-Type: ' . ( 'pdf' === $prepared['format'] ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' ) );
			header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
			header( 'Content-Length: ' . filesize( $path ) );
			header( 'X-Content-Type-Options: nosniff' );
			header( 'Cache-Control: private, no-store' );
			if ( false === readfile( $path ) ) { throw new RuntimeException( 'Download read failed' ); }
		}
	} catch ( Throwable $exception ) {
		$error = gnf_impact_export_error( 'impact_download_failed', 'No se pudo entregar el archivo. Vuelva a intentar la descarga.' );
	} finally {
		if ( $pdf_job ) { gnf_cleanup_impact_pdf_job( $pdf_job['key'], true, $pdf_job['record'] ); }
		else { @unlink( $path ); }
	}
	// wp_die may exit; always clean up before invoking it.
	if ( $error ) { gnf_impact_export_die( $error ); return; }
	exit;
}

if ( function_exists( 'add_action' ) ) {
	add_action( 'admin_post_gnf_export_impact', 'gnf_handle_export_impact' );
	add_action( 'gnf_run_impact_pdf_job', 'gnf_run_impact_pdf_job' );
	add_action( 'gnf_cleanup_impact_pdf_job', 'gnf_cleanup_impact_pdf_job' );
}
