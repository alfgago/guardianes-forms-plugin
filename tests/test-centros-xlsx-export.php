<?php
// Contrato y esquema puro para la exportacion completa de centros.

$root          = __DIR__ . '/..';
$export        = @file_get_contents( $root . '/includes/centros-export.php' ) ?: '';
$reports       = file_get_contents( $root . '/includes/reports.php' );
$users         = file_get_contents( $root . '/includes/admin-users.php' );
$cpts          = file_get_contents( $root . '/includes/cpts.php' );
$react_users   = file_get_contents( $root . '/app/src/panels/admin/pages/UsuariosPage.tsx' );
$react_centros = file_get_contents( $root . '/app/src/panels/admin/pages/CentrosPage.tsx' );
$composer      = @file_get_contents( $root . '/composer.json' ) ?: '';
$bootstrap     = file_get_contents( $root . '/guardianes-formularios.php' );
$fallback      = @file_get_contents( $root . '/includes/xlsx-writer.php' ) ?: '';

$tests = 0;
$fails = 0;

function check_centros_xlsx_export( $condition, $message ) {
	global $tests, $fails;
	$tests++;
	if ( $condition ) {
		echo "  ok: {$message}\n";
	} else {
		$fails++;
		echo "  FAIL: {$message}\n";
	}
}

foreach (
	array(
		'Centro ID',
		'Centro educativo',
		'Código MEP',
		'Dirección Regional',
		'Circuito',
		'Correo institucional',
		'Correo usuario matrícula',
		'Correos para invitación',
		'Usuarios docentes',
		'Correos docentes',
		'Matrícula ID',
		'Fecha de matrícula',
		'Última actualización de matrícula',
		'Estado matrícula',
		'Cargo coordinación PBAE',
		'Nombre coordinación PBAE',
		'Teléfono coordinación PBAE',
		'Nombre registrado en matrícula',
		'Cargo registrado en matrícula',
		'Teléfono registrado en matrícula',
		'Correo registrado en matrícula',
		'Confirmaciones de matrícula',
		'Inscripción en años anteriores',
		'Puntaje total',
		'Estrella final',
	) as $column
) {
	check_centros_xlsx_export( strpos( $export, $column ) !== false, "columna {$column}" );
}

check_centros_xlsx_export(
	strpos( $export, 'function gnf_iter_centros_export_records' ) !== false
		&& strpos( $export, "'post_status'            => 'publish'" ) !== false
		&& strpos( $export, 'gnf_get_centros_with_matricula( $anio )' ) !== false
		&& strpos( $export, "'post__in'               => \$participant_ids" ) !== false
		&& strpos( $export, "'key'     => 'estado_centro'" ) === false
		&& strpos( $export, "'posts_per_page'         => 200" ) !== false,
	'consulta solo centros inscritos en el pilotaje del año, en lotes de 200'
);
check_centros_xlsx_export(
	strpos( $export, "\$matricula_user_id = absint( \$row['user_id'] ?? 0 );" ) !== false
		&& strpos( $export, "\$maps['users'][ \$centro_id ][] = \$matricula_user_id;" ) !== false,
	'el usuario que hizo la matrícula se incluye como contacto del centro'
);
check_centros_xlsx_export(
	strpos( $export, 'function gnf_centros_export_contact_emails' ) !== false
		&& strpos( $export, "'contact_emails'" ) !== false,
	'la exportación consolida correos válidos para invitaciones'
);
check_centros_xlsx_export(
	strpos( $composer, '"openspout/openspout": "3.7.4"' ) !== false,
	'OpenSpout queda fijado a una version compatible'
);
check_centros_xlsx_export(
	strpos( $export, 'error_reporting( $previous_error_reporting & ~E_DEPRECATED & ~E_USER_DEPRECATED );' ) !== false
		&& strpos( $export, 'error_reporting( $previous_error_reporting );' ) !== false,
	'avisos deprecados no contaminan la descarga en PHP reciente'
);
check_centros_xlsx_export(
	file_exists( $root . '/includes/xlsx-writer.php' )
		&& strpos( $bootstrap, "require_once 'includes/xlsx-writer.php';" ) !== false
		&& strpos( $fallback, 'class GNF_XLSX_Writer' ) !== false
		&& strpos( $fallback, 'class_exists( \'ZipArchive\' )' ) !== false
		&& strpos( $fallback, 'PclZip' ) !== false,
	'el XLSX tiene un generador autocontenido con fallback de WordPress'
);
check_centros_xlsx_export(
	strpos( $reports, 'admin_post_gnf_export_centros_xlsx' ) !== false
		&& strpos( $reports, "current_user_can( 'manage_options' )" ) !== false,
	'endpoint XLSX registrado y restringido'
);
check_centros_xlsx_export(
	strpos( $reports, 'gnf_iter_centros_export_records' ) !== false
		&& strpos( $reports, 'gnf_prepare_file_download_response' ) !== false,
	'el CSV existente comparte el iterador y limpia la respuesta'
);
check_centros_xlsx_export(
	! preg_match( "/'posts_per_page'\\s*=>\\s*-1/", $export )
		&& ! preg_match( "/'number'\\s*=>\\s*-1/", $export ),
	'el servicio nuevo no hace consultas ilimitadas'
);
check_centros_xlsx_export(
	strpos( $export, "setName( 'Centros Educativos' )" ) !== false
		&& strpos( $export, "setName( 'Docentes sin centro' )" ) === false,
	'el XLSX contiene una sola hoja de centros inscritos'
);
check_centros_xlsx_export(
	strpos( $export, 'function gnf_export_safe_cell_value' ) !== false
		&& strpos( $export, '$value = gnf_export_safe_cell_value( $value );' ) !== false,
	'XLSX y CSV neutralizan formulas en valores controlables'
);
check_centros_xlsx_export(
	strpos( $export, "'fields'                 => 'ids'" ) === false
		&& strpos( $export, "\$ids   = array_values( array_map( 'absint', wp_list_pluck( \$query->posts, 'ID' ) ) );" ) !== false,
	'los centros del lote quedan precargados como objetos'
);
check_centros_xlsx_export(
	strpos( $export, "get_post_meta( \$centro_id, 'region', true )" ) !== false
		&& strpos( $export, "'include'    => \$legacy_region_ids" ) !== false,
	'la DRE usa el metadato historico como respaldo por lote'
);
check_centros_xlsx_export(
	strpos( $export, "if ( \$region_id && absint( \$record['region_id'] ?? 0 ) !== \$region_id )" ) !== false
		&& strpos( $export, "\$record_circuito = gnf_normalize_circuito( \$record['circuito'] ?? '' );" ) !== false
		&& strpos( $export, "\$args['tax_query']" ) === false,
	'los filtros se aplican al registro canonico y conservan datos historicos'
);
check_centros_xlsx_export(
	strpos( $users, 'Descargar inscritos XLSX' ) !== false
		&& strpos( $react_users, 'gnf_export_centros_xlsx' ) === false
		&& strpos( $react_users, 'gnf_export_centros_diagnostico_csv' ) === false
		&& strpos( $cpts, 'Descargar inscritos XLSX' ) !== false
		&& strpos( $react_centros, 'gnf_export_centros_xlsx' ) !== false
		&& strpos( $react_centros, 'Descargar inscritos XLSX' ) !== false,
	'la descarga de inscritos aparece en Centros Educativos y en gnf-usuarios'
);

check_centros_xlsx_export(
	strpos( $export, 'gnf_export_centros_xlsx_fallback' ) !== false
		&& strpos( $export, 'PHP_VERSION_ID >= 80200' ) !== false
		&& strpos( $export, "wp_die( 'El servidor no pudo generar el archivo XLSX.'" ) !== false,
	'OpenSpout ausente o fuera de su rango PHP activa el generador autocontenido'
);

if ( file_exists( $root . '/includes/centros-export.php' ) ) {
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', $root . '/' );
	}
	require_once $root . '/includes/centros-export.php';
	$headers = gnf_centros_export_headers();
	$row     = gnf_centros_export_row(
		array(
			'centro_id'      => 10,
			'nombre'         => 'Escuela Demo',
			'codigo_mep'     => 'A-01',
			'region_name'    => 'Nicoya',
			'docente_logins' => array( 'ana', 'luis' ),
			'docente_emails' => array( 'ana@example.org', 'luis@example.org' ),
		)
	);
	$mapped = array_combine( $headers, $row );
	check_centros_xlsx_export( 10 === $mapped['Centro ID'], 'fila conserva el ID' );
	check_centros_xlsx_export( 'ana; luis' === $mapped['Usuarios docentes'], 'fila concatena usuarios' );

	$emails = gnf_centros_export_contact_emails(
		array(
			'CENTRO@EXAMPLE.ORG',
			array( 'docente@example.org', 'centro@example.org', 'no-es-correo' ),
			'docente2@example.org; docente@example.org',
		)
	);
	check_centros_xlsx_export(
		array( 'centro@example.org', 'docente@example.org', 'docente2@example.org' ) === $emails,
		'correos de invitación se normalizan y deduplican'
	);

	$unsafe = array_combine(
		$headers,
		gnf_centros_export_row(
			array(
				'nombre'        => '=HYPERLINK("https://example.org")',
				'docente_names' => array( '@SUM(1+1)' ),
			)
		)
	);
	check_centros_xlsx_export( "'=HYPERLINK(\"https://example.org\")" === $unsafe['Centro educativo'], 'neutraliza formula en nombre de centro' );
	check_centros_xlsx_export( "'@SUM(1+1)" === $unsafe['Nombres docentes'], 'neutraliza formula en datos docentes' );
}

if ( file_exists( $root . '/includes/xlsx-writer.php' ) && class_exists( 'ZipArchive' ) ) {
	$previous_error_reporting = error_reporting();
	error_reporting( $previous_error_reporting & ~E_DEPRECATED & ~E_USER_DEPRECATED );
	require_once $root . '/includes/xlsx-writer.php';

	$temp_xlsx = tempnam( sys_get_temp_dir(), 'gnf-centros-' );
	$writer    = new GNF_XLSX_Writer( $temp_xlsx, 'Centros Educativos' );
	$writer->add_row( gnf_centros_export_headers(), true );
	$writer->close();

	$zip       = new ZipArchive();
	$zip_open  = true === $zip->open( $temp_xlsx );
	$workbook  = $zip_open ? (string) $zip->getFromName( 'xl/workbook.xml' ) : '';
	$worksheet = $zip_open ? (string) $zip->getFromName( 'xl/worksheets/sheet1.xml' ) : '';
	$has_package_files = $zip_open
		&& false !== $zip->locateName( '[Content_Types].xml' )
		&& false !== $zip->locateName( '_rels/.rels' )
		&& false !== $zip->locateName( 'xl/styles.xml' )
		&& false !== $zip->locateName( 'xl/_rels/workbook.xml.rels' );
	if ( $zip_open ) {
		$zip->close();
	}
	@unlink( $temp_xlsx );
	error_reporting( $previous_error_reporting );

	check_centros_xlsx_export( $zip_open && 1 === substr_count( $workbook, '<sheet ' ) && false !== strpos( $workbook, 'Centros Educativos' ), 'el archivo real contiene una sola hoja de centros' );
	check_centros_xlsx_export( false !== strpos( $worksheet, 'Matrícula ID' ), 'el archivo real contiene los encabezados completos' );
	check_centros_xlsx_export( $has_package_files, 'el archivo real contiene la estructura Open XML requerida' );
	check_centros_xlsx_export( false !== simplexml_load_string( $workbook ) && false !== simplexml_load_string( $worksheet ), 'workbook y hoja contienen XML bien formado' );
}

echo "\n{$tests} checks, {$fails} failures\n";
exit( $fails ? 1 : 0 );
