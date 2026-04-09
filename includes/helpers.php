<?php

/**
 * Helpers genÃ©ricos.
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Obtiene valor desde ACF Options o opciÃ³n normal.
 */
function gnf_get_option($key, $default = '')
{
	if (function_exists('get_field')) {
		$val = get_field($key, 'option');
		if (null !== $val && '' !== $val) {
			return $val;
		}
	}

	$val = get_option($key);
	return (null !== $val && '' !== $val) ? $val : $default;
}

/**
 * AÃ±o activo configurado.
 */
function gnf_get_active_year()
{
	$year = gnf_get_option('anio_actual', gmdate('Y'));
	return absint($year);
}

/**
 * Obtiene todos los aÃ±os disponibles para navegaciÃ³n en paneles React.
 *
 * @return int[]
 */
function gnf_get_available_years()
{
	global $wpdb;

	$years = array( gnf_get_active_year() );

	$table_entries = $wpdb->prefix . 'gn_reto_entries';
	$table_exists  = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_entries ) );
	if ( $table_exists === $table_entries ) {
		$entry_years = $wpdb->get_col( "SELECT DISTINCT anio FROM {$table_entries} WHERE anio IS NOT NULL AND anio > 0" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$years       = array_merge( $years, array_map( 'absint', (array) $entry_years ) );
	}

	$table_matriculas = $wpdb->prefix . 'gn_matriculas';
	$table_exists     = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_matriculas ) );
	if ( $table_exists === $table_matriculas ) {
		$matricula_years = $wpdb->get_col( "SELECT DISTINCT anio FROM {$table_matriculas} WHERE anio IS NOT NULL AND anio > 0" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$years           = array_merge( $years, array_map( 'absint', (array) $matricula_years ) );
	}

	$reto_years = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT DISTINCT pm.meta_value
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			WHERE p.post_type = 'reto'
			  AND p.post_status IN ('publish', 'draft', 'pending', 'private')
			  AND pm.meta_key LIKE %s",
			$wpdb->esc_like( 'configuracion_por_anio_' ) . '%_anio'
		)
	);
	$years = array_merge( $years, array_map( 'absint', (array) $reto_years ) );

	$years = array_filter(
		array_unique( $years ),
		static function ( $year ) {
			return $year >= 2000 && $year <= 2100;
		}
	);
	rsort( $years, SORT_NUMERIC );

	return array_values( apply_filters( 'gnf_available_years', $years ) );
}

/**
 * Normaliza un aÃ±o vÃ¡lido de la plataforma.
 *
 * @param int|string|null $anio AÃ±o solicitado.
 * @return int
 */
function gnf_normalize_year($anio = null)
{
	$resolved = (null === $anio || '' === $anio) ? gnf_get_active_year() : absint($anio);
	if ($resolved < 2000 || $resolved > 2100) {
		$resolved = gnf_get_active_year();
	}
	return absint($resolved);
}

/**
 * Normaliza nombres de ubicacion para comparaciones.
 *
 * @param string $value Valor a normalizar.
 * @return string
 */
function gnf_normalize_geo_name($value)
{
	$value = remove_accents((string) $value);
	$value = strtolower(trim((string) preg_replace('/\s+/', ' ', $value)));
	return $value;
}

/**
 * Catalogo canonico de eco retos por año.
 *
 * @param int|null $anio Ano.
 * @return array<string,array<string,mixed>>
 */
function gnf_get_reto_catalog($anio = null)
{
	$anio = gnf_normalize_year($anio);

	if (2026 !== $anio) {
		return array();
	}

	return array(
		'agua'                     => array( 'title' => 'RETO AGUA', 'required' => true ),
		'electricidad'             => array( 'title' => 'RETO ELECTRICIDAD', 'required' => true ),
		'residuos'                 => array( 'title' => 'RETO RESIDUOS', 'required' => true ),
		'limpiezas'                => array( 'title' => 'RETO LIMPIEZAS', 'required' => false ),
		'siembra-de-arboles'       => array( 'title' => 'RETO SIEMBRA DE ÁRBOLES', 'required' => false ),
		'eco-lonchera'             => array( 'title' => 'RETO ECO LONCHERA', 'required' => false ),
		'gestion-de-organicos'     => array( 'title' => 'RETO GESTIÓN DE ORGÁNICOS (compostaje)', 'required' => false ),
		'artistico-eco-murales'    => array( 'title' => 'RETO ARTÍSTICO ECO MURALES', 'required' => false ),
		'eco-gira'                 => array( 'title' => 'ECO GIRA', 'required' => false ),
		'jardines-y-polinizadores' => array( 'title' => 'RETO JARDINES Y POLINIZADORES', 'required' => false ),
		'huerta'                   => array( 'title' => 'RETO HUERTA', 'required' => false ),
		'comodin'                  => array( 'title' => 'RETO COMODÍN', 'required' => false ),
		'eco-emprendimiento'       => array( 'title' => 'RETO ECO EMPRENDIMIENTO', 'required' => false ),
		'bienestar-animal'         => array( 'title' => 'RETO BIENESTAR ANIMAL', 'required' => false ),
		'evento-sostenible'        => array( 'title' => 'RETO EVENTO SOSTENIBLE', 'required' => false ),
	);
}

/**
 * Obtiene el slug canonico de un reto a partir del titulo.
 *
 * @param string $title Titulo del reto.
 * @return string
 */
function gnf_get_reto_canonical_slug($title)
{
	$normalized = gnf_normalize_geo_name((string) $title);
	$normalized = preg_replace('/\([^)]*\)/', '', $normalized);
	$normalized = trim((string) preg_replace('/\s+/', ' ', $normalized));

	foreach ( array( 'reto ', 'eco reto ', 'eco-reto ', 'eco ' ) as $prefix ) {
		if (0 === strpos($normalized, $prefix)) {
			$normalized = substr($normalized, strlen($prefix));
			break;
		}
	}

	$normalized = trim((string) $normalized);

	if (false !== strpos($normalized, 'captacion de agua')) {
		return '';
	}
	if (false !== strpos($normalized, 'agua')) {
		return 'agua';
	}
	if (false !== strpos($normalized, 'electric') || false !== strpos($normalized, 'energia')) {
		return 'electricidad';
	}
	if (false !== strpos($normalized, 'residuo')) {
		return 'residuos';
	}
	if (false !== strpos($normalized, 'ambiente limpio') || false !== strpos($normalized, 'limpieza')) {
		return 'limpiezas';
	}
	if (false !== strpos($normalized, 'arbol')) {
		return 'siembra-de-arboles';
	}
	if (false !== strpos($normalized, 'lonchera') || false !== strpos($normalized, 'merienda')) {
		return 'eco-lonchera';
	}
	if (false !== strpos($normalized, 'compost') || false !== strpos($normalized, 'organico')) {
		return 'gestion-de-organicos';
	}
	if (false !== strpos($normalized, 'artistico') || false !== strpos($normalized, 'mural')) {
		return 'artistico-eco-murales';
	}
	if (false !== strpos($normalized, 'eco gira') || 'gira' === $normalized) {
		return 'eco-gira';
	}
	if (false !== strpos($normalized, 'polinizador') || false !== strpos($normalized, 'jardin')) {
		return 'jardines-y-polinizadores';
	}
	if (false !== strpos($normalized, 'huerta')) {
		return 'huerta';
	}
	if (false !== strpos($normalized, 'comod')) {
		return 'comodin';
	}
	if (false !== strpos($normalized, 'emprend')) {
		return 'eco-emprendimiento';
	}
	if (false !== strpos($normalized, 'bienestar animal')) {
		return 'bienestar-animal';
	}
	if (false !== strpos($normalized, 'evento sostenible')) {
		return 'evento-sostenible';
	}

	return sanitize_title($normalized);
}

/**
 * Indica si el reto es requisito en matricula.
 *
 * @param int $reto_id ID del reto.
 * @param int|null $anio Ano.
 * @return bool
 */
function gnf_is_reto_required($reto_id, $anio = null)
{
	$slug    = gnf_get_reto_canonical_slug(get_the_title((int) $reto_id));
	$catalog = gnf_get_reto_catalog($anio);
	if ($slug && isset($catalog[$slug]['required'])) {
		return (bool) $catalog[$slug]['required'];
	}

	return in_array((int) $reto_id, gnf_get_obligatorio_reto_ids(), true);
}

/**
 * Mapa oficial de provincias -> cantones de Costa Rica (84 cantones).
 *
 * @return array<string, string[]>
 */
function gnf_get_cr_province_canton_map()
{
	static $map = null;
	if (null !== $map) {
		return $map;
	}

	$map = array(
		'San Jose' => array(
			'San Jose',
			'Escazu',
			'Desamparados',
			'Puriscal',
			'Tarrazu',
			'Aserri',
			'Mora',
			'Goicoechea',
			'Santa Ana',
			'Alajuelita',
			'Vazquez de Coronado',
			'Acosta',
			'Tibas',
			'Moravia',
			'Montes de Oca',
			'Turrubares',
			'Dota',
			'Curridabat',
			'Perez Zeledon',
			'Leon Cortes Castro',
		),
		'Alajuela' => array(
			'Alajuela',
			'San Ramon',
			'Grecia',
			'San Mateo',
			'Atenas',
			'Naranjo',
			'Palmares',
			'Poas',
			'Orotina',
			'San Carlos',
			'Zarcero',
			'Sarchi',
			'Upala',
			'Los Chiles',
			'Guatuso',
			'Rio Cuarto',
		),
		'Cartago' => array(
			'Cartago',
			'Paraiso',
			'La Union',
			'Jimenez',
			'Turrialba',
			'Alvarado',
			'Oreamuno',
			'El Guarco',
		),
		'Heredia' => array(
			'Heredia',
			'Barva',
			'Santo Domingo',
			'Santa Barbara',
			'San Rafael',
			'San Isidro',
			'Belen',
			'Flores',
			'San Pablo',
			'Sarapiqui',
		),
		'Guanacaste' => array(
			'Liberia',
			'Nicoya',
			'Santa Cruz',
			'Bagaces',
			'Carrillo',
			'Canas',
			'Abangares',
			'Tilaran',
			'Nandayure',
			'La Cruz',
			'Hojancha',
		),
		'Puntarenas' => array(
			'Puntarenas',
			'Esparza',
			'Buenos Aires',
			'Montes de Oro',
			'Osa',
			'Quepos',
			'Golfito',
			'Coto Brus',
			'Parrita',
			'Corredores',
			'Garabito',
			'Monteverde',
			'Puerto Jimenez',
		),
		'Limon' => array(
			'Limon',
			'Pococi',
			'Siquirres',
			'Talamanca',
			'Matina',
			'Guacimo',
		),
	);

	return $map;
}

/**
 * Devuelve provincias soportadas.
 *
 * @return string[]
 */
function gnf_get_cr_provinces()
{
	return array_keys(gnf_get_cr_province_canton_map());
}

/**
 * Devuelve cantones para una provincia.
 *
 * @param string $province Provincia.
 * @return string[]
 */
function gnf_get_cr_cantons_by_province($province)
{
	$map                = gnf_get_cr_province_canton_map();
	$normalized_province = gnf_normalize_geo_name($province);
	foreach ($map as $province_name => $cantons) {
		if (gnf_normalize_geo_name($province_name) === $normalized_province) {
			return $cantons;
		}
	}
	return array();
}

/**
 * Valida que canton pertenezca a la provincia.
 *
 * @param string $province Provincia.
 * @param string $canton CantÃ³n.
 * @return bool
 */
function gnf_is_valid_cr_province_canton($province, $canton)
{
	if ('' === trim((string) $province) || '' === trim((string) $canton)) {
		return false;
	}

	$cantons = gnf_get_cr_cantons_by_province($province);
	if (empty($cantons)) {
		return false;
	}

	$normalized_canton = gnf_normalize_geo_name($canton);
	foreach ($cantons as $candidate) {
		if (gnf_normalize_geo_name($candidate) === $normalized_canton) {
			return true;
		}
	}

	return false;
}

/**
 * Choices estandar para el perfil del centro.
 *
 * @return array<string, array<string,string>>
 */
function gnf_get_centro_profile_choice_sets()
{
	return array(
		'nivel_educativo' => array(
			'preescolar' => 'Preescolar',
			'primaria'   => 'Primaria',
			'secundaria' => 'Secundaria',
		),
		'dependencia' => array(
			'publica'      => 'Publico',
			'privada'      => 'Privado',
			'subvencionada' => 'Subvencionado',
		),
		'jornada' => array(
			'diurno'   => 'Diurno',
			'nocturno' => 'Nocturno',
		),
		'tipologia' => array(
			'tipo_i'   => 'Tipo I (500 o mas estudiantes)',
			'tipo_ii'  => 'Tipo II (300-499)',
			'tipo_iii' => 'Tipo III (100-299)',
			'tipo_iv'  => 'Tipo IV (99 o menos)',
			'tipo_v'   => 'Tipo V (multigrado)',
		),
		'tipo_centro_educativo' => array(
			'unidocente'    => 'Unidocente',
			'direccion_i'   => 'Dirección I',
			'direccion_ii'  => 'Dirección II',
			'direccion_iii' => 'Dirección III',
			'direccion_iv'  => 'Dirección IV',
			'direccion_v'   => 'Dirección V',
		),
		'coordinador_cargo' => array(
			'director'      => 'Director(a)',
			'docente'       => 'Docente',
			'administrativo' => 'Administrativo',
		),
		'ultimo_anio_participacion' => array(
			'2025' => '2025',
			'2024' => '2024',
			'otro' => 'Otro',
		),
		'ultimo_galardon_estrellas' => array(
			'1' => '1 estrella',
			'2' => '2 estrellas',
			'3' => '3 estrellas',
			'4' => '4 estrellas',
			'5' => '5 estrellas',
		),
	);
}

/**
 * Normaliza un valor a una opcion valida del campo.
 *
 * @param string $field Campo logico.
 * @param string $value Valor crudo.
 * @return string
 */
function gnf_normalize_centro_choice($field, $value)
{
	$choices = gnf_get_centro_profile_choice_sets();
	$raw     = trim((string) $value);
	if ('' === $raw) {
		return '';
	}

	$normalized = gnf_normalize_geo_name($raw);
	if ('dependencia' === $field) {
		$alias = array(
			'publica'       => 'publica',
			'publico'       => 'publica',
			'publico(a)'    => 'publica',
			'privada'       => 'privada',
			'privado'       => 'privada',
			'subvencionada' => 'subvencionada',
			'subvencionado' => 'subvencionada',
		);
		if (isset($alias[$normalized])) {
			return $alias[$normalized];
		}
	}

	if ('jornada' === $field) {
		$alias = array(
			'diurno'           => 'diurno',
			'diurna'           => 'diurno',
			'matutino'         => 'diurno',
			'vespertino'       => 'diurno',
			'mixto'            => 'diurno',
			'tiempo completo'  => 'diurno',
			'tiempo_completo'  => 'diurno',
			'nocturno'         => 'nocturno',
			'nocturna'         => 'nocturno',
		);
		if (isset($alias[$normalized])) {
			return $alias[$normalized];
		}
	}

	if ('nivel_educativo' === $field) {
		$alias = array(
			'preescolar' => 'preescolar',
			'primaria'   => 'primaria',
			'secundaria' => 'secundaria',
			'academica'  => 'secundaria',
			'tecnica'    => 'secundaria',
		);
		if (isset($alias[$normalized])) {
			return $alias[$normalized];
		}
	}

	if ('tipologia' === $field) {
		if (preg_match('/tipo\s*[_ -]*i\b/', $normalized)) {
			return 'tipo_i';
		}
		if (preg_match('/tipo\s*[_ -]*ii\b/', $normalized)) {
			return 'tipo_ii';
		}
		if (preg_match('/tipo\s*[_ -]*iii\b/', $normalized)) {
			return 'tipo_iii';
		}
		if (preg_match('/tipo\s*[_ -]*iv\b/', $normalized)) {
			return 'tipo_iv';
		}
		if (preg_match('/tipo\s*[_ -]*v\b/', $normalized) || false !== strpos($normalized, 'multigrado')) {
			return 'tipo_v';
		}
	}

	if (isset($choices[$field]) && is_array($choices[$field])) {
		foreach ($choices[$field] as $key => $label) {
			if ($normalized === gnf_normalize_geo_name($key) || $normalized === gnf_normalize_geo_name($label)) {
				return (string) $key;
			}
		}
	}

	return '';
}

/**
 * Devuelve etiqueta legible para un valor.
 *
 * @param string $field Campo logico.
 * @param string $value Valor.
 * @return string
 */
function gnf_get_centro_choice_label($field, $value)
{
	$choices = gnf_get_centro_profile_choice_sets();
	$key     = gnf_normalize_centro_choice($field, $value);
	if (isset($choices[$field][$key])) {
		return (string) $choices[$field][$key];
	}
	return (string) $value;
}

/**
 * Obtiene aÃ±o de contexto desde request (POST/GET) o fallback.
 *
 * @param int|null $default AÃ±o por defecto opcional.
 * @return int
 */
function gnf_get_context_year($default = null)
{
	$candidates = array(
		$_POST['anio'] ?? null,
		$_GET['anio'] ?? null,
		$_GET['gnf_year'] ?? null,
		$default,
	);

	foreach ($candidates as $candidate) {
		if (null === $candidate || '' === $candidate) {
			continue;
		}
		if (!is_scalar($candidate)) {
			continue;
		}
		return gnf_normalize_year(wp_unslash((string) $candidate));
	}

	return gnf_normalize_year($default);
}

/**
 * Convierte cualquier lista de retos a IDs enteros.
 *
 * @param mixed $reto_ids Lista cruda.
 * @return int[]
 */
function gnf_normalize_reto_ids($reto_ids)
{
	if (!is_array($reto_ids)) {
		return array();
	}

	$normalized = array();
	foreach ($reto_ids as $item) {
		if (is_object($item) && isset($item->ID)) {
			$normalized[] = (int) $item->ID;
		} elseif (is_array($item) && isset($item['ID'])) {
			$normalized[] = (int) $item['ID'];
		} else {
			$normalized[] = (int) $item;
		}
	}

	$normalized = array_values(array_unique(array_filter($normalized)));
	return array_map('intval', $normalized);
}

/**
 * Estructura base de datos anuales de centro.
 *
 * @param int $anio AÃ±o.
 * @return array
 */
function gnf_get_centro_anual_default_row($anio)
{
	$anio = gnf_normalize_year($anio);
	return array(
		'anio'              => $anio,
		'meta_estrellas'    => 0,
		'retos_seleccionados' => array(),
		'comite_estudiantes' => 0,
		'estado_matricula'  => 'no_iniciado',
		'puntaje_total'     => 0,
		'estrella_final'    => 0,
	);
}

/**
 * Sanitiza una fila anual de centro.
 *
 * @param array $row Fila cruda.
 * @param int   $anio AÃ±o.
 * @return array
 */
function gnf_sanitize_centro_anual_row($row, $anio)
{
	$base = gnf_get_centro_anual_default_row($anio);
	$row  = wp_parse_args(is_array($row) ? $row : array(), $base);

	$row['anio']                = gnf_normalize_year($row['anio'] ?? $anio);
	$row['meta_estrellas']      = max(0, min(5, absint($row['meta_estrellas'] ?? 0)));
	$row['retos_seleccionados'] = gnf_normalize_reto_ids($row['retos_seleccionados'] ?? array());
	$row['comite_estudiantes']  = absint($row['comite_estudiantes'] ?? 0);
	$row['estado_matricula']    = sanitize_key((string) ($row['estado_matricula'] ?? 'no_iniciado'));
	$row['puntaje_total']       = max(0, absint($row['puntaje_total'] ?? 0));
	$row['estrella_final']      = max(0, min(5, absint($row['estrella_final'] ?? 0)));

	return $row;
}

/**
 * Obtiene filas anuales ACF del centro.
 *
 * @param int $centro_id Centro educativo.
 * @return array
 */
function gnf_get_centro_anual_rows($centro_id)
{
	if (!$centro_id || !function_exists('get_field')) {
		return array();
	}

	$rows = get_field('centro_datos_anuales', $centro_id);
	return is_array($rows) ? $rows : array();
}

/**
 * Obtiene fila anual especÃ­fica del centro.
 *
 * @param int  $centro_id Centro educativo.
 * @param int  $anio AÃ±o.
 * @param bool $exists Indicador de existencia por referencia.
 * @return array
 */
function gnf_get_centro_anual_row($centro_id, $anio, &$exists = false)
{
	$anio = gnf_normalize_year($anio);
	$rows = gnf_get_centro_anual_rows($centro_id);

	foreach ($rows as $row) {
		if ($anio === absint($row['anio'] ?? 0)) {
			$exists = true;
			return gnf_sanitize_centro_anual_row($row, $anio);
		}
	}

	$exists = false;
	return gnf_get_centro_anual_default_row($anio);
}

/**
 * Persiste datos anuales del centro (solo ACF).
 *
 * @param int   $centro_id Centro educativo.
 * @param int   $anio AÃ±o.
 * @param array $data Datos parciales.
 * @return array Fila anual guardada.
 */
function gnf_set_centro_anual_data($centro_id, $anio, $data)
{
	if (!$centro_id || !is_array($data)) {
		return gnf_get_centro_anual_default_row($anio);
	}

	$anio = gnf_normalize_year($anio);
	$rows = gnf_get_centro_anual_rows($centro_id);
	$idx  = null;

	foreach ($rows as $i => $row) {
		if ($anio === absint($row['anio'] ?? 0)) {
			$idx = $i;
			break;
		}
	}

	$current = (null !== $idx) ? gnf_sanitize_centro_anual_row($rows[$idx], $anio) : gnf_get_centro_anual_default_row($anio);
	foreach ($data as $key => $value) {
		$current[$key] = $value;
	}
	$current = gnf_sanitize_centro_anual_row($current, $anio);

	if (null === $idx) {
		$rows[] = $current;
	} else {
		$rows[$idx] = $current;
	}

	if (function_exists('update_field')) {
		update_field('centro_datos_anuales', array_values($rows), $centro_id);
	}

	return $current;
}

/**
 * Obtiene valor anual de centro desde ACF.
 *
 * @param int         $centro_id Centro educativo.
 * @param string      $field Campo anual.
 * @param int|null    $anio AÃ±o.
 * @param mixed|null  $default Valor por defecto.
 * @return mixed
 */
function gnf_get_centro_anual_field($centro_id, $field, $anio = null, $default = null)
{
	if (!$centro_id) {
		return $default;
	}

	$anio   = gnf_normalize_year($anio);
	$exists = false;
	$row    = gnf_get_centro_anual_row($centro_id, $anio, $exists);

	if ($exists && array_key_exists($field, $row)) {
		return $row[$field];
	}

	return $default;
}

/**
 * Guarda un campo anual de centro.
 *
 * @param int      $centro_id Centro educativo.
 * @param string   $field Campo anual.
 * @param mixed    $value Valor.
 * @param int|null $anio AÃ±o.
 * @return mixed
 */
function gnf_set_centro_anual_field($centro_id, $field, $value, $anio = null)
{
	$anio = gnf_normalize_year($anio);
	$row  = gnf_set_centro_anual_data($centro_id, $anio, array($field => $value));
	return $row[$field] ?? null;
}

/**
 * Atajos de lectura por aÃ±o para el centro.
 */
function gnf_get_centro_retos_seleccionados($centro_id, $anio = null)
{
	$retos = gnf_get_centro_anual_field($centro_id, 'retos_seleccionados', $anio, array());
	if (empty($retos)) {
		$retos = gnf_get_matricula_selected_retos_fallback($centro_id, $anio);
	}
	$retos = gnf_normalize_reto_ids($retos);
	$retos = array_values(array_unique(array_merge(gnf_get_required_reto_ids_for_year($anio), $retos)));
	$retos = gnf_resolve_reto_ids_for_year($retos, $anio);
	return gnf_sort_reto_ids_required_first($retos);
}

function gnf_get_centro_meta_estrellas($centro_id, $anio = null)
{
	return absint(gnf_get_centro_anual_field($centro_id, 'meta_estrellas', $anio, 0));
}

function gnf_get_centro_comite_estudiantes($centro_id, $anio = null)
{
	return absint(gnf_get_centro_anual_field($centro_id, 'comite_estudiantes', $anio, 0));
}

function gnf_get_centro_matricula_estado($centro_id, $anio = null)
{
	return (string) gnf_get_centro_anual_field($centro_id, 'estado_matricula', $anio, 'no_iniciado');
}

function gnf_get_centro_puntaje_total($centro_id, $anio = null)
{
	return absint(gnf_get_centro_anual_field($centro_id, 'puntaje_total', $anio, 0));
}

function gnf_get_centro_estrella_final($centro_id, $anio = null)
{
	return absint(gnf_get_centro_anual_field($centro_id, 'estrella_final', $anio, 0));
}

/**
 * Atajos de escritura por aÃ±o para el centro.
 */
function gnf_set_centro_retos_seleccionados($centro_id, $anio, $reto_ids)
{
	$reto_ids = gnf_normalize_reto_ids($reto_ids);
	$reto_ids = array_values(array_unique(array_merge(gnf_get_required_reto_ids_for_year($anio), $reto_ids)));
	$reto_ids = gnf_resolve_reto_ids_for_year($reto_ids, $anio);
	$reto_ids = gnf_sort_reto_ids_required_first($reto_ids);
	return gnf_set_centro_anual_field($centro_id, 'retos_seleccionados', $reto_ids, $anio);
}

function gnf_set_centro_meta_estrellas($centro_id, $anio, $meta_estrellas)
{
	return gnf_set_centro_anual_field($centro_id, 'meta_estrellas', max(0, min(5, absint($meta_estrellas))), $anio);
}

function gnf_set_centro_comite_estudiantes($centro_id, $anio, $cantidad)
{
	return gnf_set_centro_anual_field($centro_id, 'comite_estudiantes', absint($cantidad), $anio);
}

function gnf_set_centro_matricula_estado($centro_id, $anio, $estado)
{
	return gnf_set_centro_anual_field($centro_id, 'estado_matricula', sanitize_key($estado), $anio);
}

function gnf_set_centro_score($centro_id, $anio, $puntaje_total, $estrella_final)
{
	return gnf_set_centro_anual_data(
		$centro_id,
		$anio,
		array(
			'puntaje_total'  => max(0, absint($puntaje_total)),
			'estrella_final' => max(0, min(5, absint($estrella_final))),
		)
	);
}

/**
 * Verifica rol de usuario.
 */
function gnf_user_has_role($user, $role)
{
	if (! $user instanceof WP_User) {
		return false;
	}
	return in_array($role, (array) $user->roles, true);
}

/**
 * Obtiene la regiÃ³n del usuario (user_meta o ACF).
 */
function gnf_get_user_region($user_id)
{
	$keys = array( 'region', 'gnf_region_id', 'gnf_region' );
	foreach ( $keys as $key ) {
		$region = get_user_meta( $user_id, $key, true );
		if ( '' !== $region && null !== $region ) {
			return absint( $region );
		}
	}

	if ( function_exists( 'get_field' ) ) {
		$region = get_field( 'region', 'user_' . $user_id );
		if ( ! empty( $region ) ) {
			return absint( $region );
		}
	}

	$centro_id = gnf_get_centro_for_docente( $user_id );
	if ( $centro_id ) {
		return gnf_get_centro_region_id( $centro_id );
	}

	return 0;
}

/**
 * Comprueba si el usuario tiene acceso al centro (docente, supervisor regiÃ³n o comitÃ©).
 */
function gnf_user_can_access_centro($user_id, $centro_id)
{
	if (user_can($user_id, 'manage_options')) {
		return true;
	}

	$user = get_userdata($user_id);

	// ComitÃ© BAE puede acceder a todos los centros.
	if (gnf_user_has_role($user, 'comite_bae') || user_can($user_id, 'gnf_view_all_regions')) {
		return true;
	}

	$centro_region = get_post_meta($centro_id, 'region', true);
	if (empty($centro_region)) {
		$terms = wp_get_post_terms($centro_id, 'gn_region', array('fields' => 'ids'));
		$centro_region = $terms ? $terms[0] : '';
	}

	if (gnf_user_has_role($user, 'docente')) {
		$docentes = function_exists( 'get_field' )
			? (array) get_field( 'docentes_asociados', $centro_id )
			: (array) get_post_meta( $centro_id, 'docentes_asociados', true );
		$docentes = array_map( 'absint', $docentes );
		if ( in_array( $user_id, $docentes, true ) ) {
			return true;
		}

		return (int) gnf_get_centro_for_docente( $user_id ) === (int) $centro_id;
	}

	if (gnf_user_has_role($user, 'supervisor')) {
		return (string) gnf_get_user_region($user_id) === (string) $centro_region;
	}

	return false;
}

/**
 * Obtiene la direccion regional asociada a un centro.
 *
 * @param int $centro_id ID del centro.
 * @return int
 */
function gnf_get_centro_region_id( $centro_id ) {
	$centro_id = absint( $centro_id );
	if ( ! $centro_id || 'centro_educativo' !== get_post_type( $centro_id ) ) {
		return 0;
	}

	$region = absint( get_post_meta( $centro_id, 'region', true ) );
	if ( $region ) {
		return $region;
	}

	$terms = wp_get_post_terms( $centro_id, 'gn_region', array( 'fields' => 'ids' ) );
	return ! empty( $terms ) ? absint( $terms[0] ) : 0;
}

/**
 * Sincroniza la relacion canonica docente -> centro y sus metadatos derivados.
 *
 * @param int   $user_id ID del docente.
 * @param int   $centro_id ID del centro.
 * @param array $args Opciones de sincronizacion.
 * @return bool
 */
function gnf_sync_docente_centro_assignment( $user_id, $centro_id, $args = array() ) {
	$user_id   = absint( $user_id );
	$centro_id = absint( $centro_id );
	$args      = wp_parse_args(
		$args,
		array(
			'sync_region'               => true,
			'sync_correo_institucional' => false,
		)
	);

	if ( ! $user_id || ! $centro_id || 'centro_educativo' !== get_post_type( $centro_id ) ) {
		return false;
	}

	update_user_meta( $user_id, 'centro_solicitado', $centro_id );
	update_user_meta( $user_id, 'centro_educativo_id', $centro_id );
	update_user_meta( $user_id, 'gnf_centro_id', $centro_id );

	gnf_attach_docente_to_centro( $user_id, $centro_id );

	if ( ! empty( $args['sync_region'] ) ) {
		$region_id = gnf_get_centro_region_id( $centro_id );
		if ( $region_id ) {
			update_user_meta( $user_id, 'region', $region_id );
			update_user_meta( $user_id, 'gnf_region_id', $region_id );
			update_user_meta( $user_id, 'gnf_region', $region_id );
		}
	}

	if ( ! empty( $args['sync_correo_institucional'] ) ) {
		$user = get_userdata( $user_id );
		if ( $user instanceof WP_User && is_email( $user->user_email ) ) {
			update_post_meta( $centro_id, 'correo_institucional', $user->user_email );
		}
	}

	return true;
}

/**
 * Limpia la asociacion canonica docente -> centro.
 *
 * @param int $user_id ID del docente.
 * @param int $centro_id ID del centro previo.
 * @return void
 */
function gnf_clear_docente_centro_assignment( $user_id, $centro_id = 0 ) {
	$user_id   = absint( $user_id );
	$centro_id = absint( $centro_id );

	if ( ! $user_id ) {
		return;
	}

	if ( $centro_id && 'centro_educativo' === get_post_type( $centro_id ) ) {
		gnf_detach_docente_from_centro( $user_id, $centro_id );
	}

	delete_user_meta( $user_id, 'centro_solicitado' );
	delete_user_meta( $user_id, 'centro_educativo_id' );
	delete_user_meta( $user_id, 'gnf_centro_id' );
}

/**
 * Inserta notificaciÃ³n.
 */
function gnf_insert_notification($user_id, $tipo, $mensaje, $relacion_tipo = '', $relacion_id = 0)
{
	global $wpdb;
	$table = $wpdb->prefix . 'gn_notificaciones';
	$wpdb->insert(
		$table,
		array(
			'user_id'       => $user_id,
			'tipo'          => $tipo,
			'mensaje'       => $mensaje,
			'relacion_tipo' => $relacion_tipo,
			'relacion_id'   => $relacion_id,
			'leido'         => 0,
			'created_at'    => current_time('mysql'),
		),
		array('%d', '%s', '%s', '%s', '%d', '%d', '%s')
	);
}

/**
 * Inserta o refresca una notificación no leída del mismo tipo/relación.
 *
 * Se usa para evitar ruido repetido cuando el mismo evento se emite varias
 * veces antes de que el usuario lo atienda.
 *
 * @param int    $user_id       Usuario destino.
 * @param string $tipo          Tipo de notificación.
 * @param string $mensaje       Mensaje actualizado.
 * @param string $relacion_tipo Relación.
 * @param int    $relacion_id   ID relacionado.
 * @return int ID de la notificación afectada.
 */
function gnf_insert_or_refresh_notification( $user_id, $tipo, $mensaje, $relacion_tipo = '', $relacion_id = 0 ) {
	global $wpdb;

	$table         = $wpdb->prefix . 'gn_notificaciones';
	$notification  = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT id FROM {$table}
			 WHERE user_id = %d
			   AND tipo = %s
			   AND relacion_tipo = %s
			   AND relacion_id = %d
			   AND leido = 0
			 ORDER BY created_at DESC
			 LIMIT 1",
			$user_id,
			$tipo,
			$relacion_tipo,
			$relacion_id
		)
	);

	if ( $notification ) {
		$wpdb->update(
			$table,
			array(
				'mensaje'    => $mensaje,
				'created_at' => current_time( 'mysql' ),
			),
			array( 'id' => (int) $notification->id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return (int) $notification->id;
	}

	gnf_insert_notification( $user_id, $tipo, $mensaje, $relacion_tipo, $relacion_id );
	return (int) $wpdb->insert_id;
}

/**
 * Helper para obtener data de wp_gn_reto_entries por usuario/aÃ±o.
 */
function gnf_get_user_reto_entries($user_id, $anio)
{
	global $wpdb;
	$table = $wpdb->prefix . 'gn_reto_entries';
	return $wpdb->get_results(
		$wpdb->prepare("SELECT * FROM {$table} WHERE user_id = %d AND anio = %d", $user_id, $anio)
	);
}

/**
 * URL del Drive para registros (Registrar y Reducir). Configurable en Guardianes â†’ ConfiguraciÃ³n.
 *
 * @return string URL o vacÃ­o si no estÃ¡ configurado.
 */
function gnf_get_registros_drive_url()
{
	$url = gnf_get_option('registros_drive_url', '');
	return is_string($url) ? trim($url) : '';
}

/**
 * Devuelve los retos publicados de primer nivel que tienen configuraciÃ³n activa
 * para el aÃ±o solicitado.
 *
 * @param int|null $anio AÃ±o.
 * @return WP_Post[]
 */
function gnf_get_available_retos_for_year($anio = null)
{
	$anio  = gnf_normalize_year($anio);
	$retos = get_posts(
		array(
			'post_type'      => 'reto',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'post_parent'    => 0,
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	);

	$selected = array();
	foreach ((array) $retos as $reto) {
		if (! gnf_reto_has_form_for_year($reto->ID, $anio)) {
			continue;
		}
		$selected[] = $reto;
	}

	usort(
		$selected,
		static function ($a, $b) use ($anio) {
			$a_required = gnf_is_reto_required((int) $a->ID, $anio) ? 0 : 1;
			$b_required = gnf_is_reto_required((int) $b->ID, $anio) ? 0 : 1;
			if ($a_required !== $b_required) {
				return $a_required <=> $b_required;
			}
			return strcasecmp(remove_accents((string) $a->post_title), remove_accents((string) $b->post_title));
		}
	);

	return array_values($selected);
}

function gnf_get_required_reto_ids_for_year($anio = null)
{
	$required = array();
	foreach (gnf_get_available_retos_for_year($anio) as $reto) {
		if (gnf_is_reto_required((int) $reto->ID, $anio)) {
			$required[] = (int) $reto->ID;
		}
	}

	return array_values(array_unique($required));
}

function gnf_get_latest_matricula_row($centro_id, $anio = null)
{
	global $wpdb;

	$centro_id = absint($centro_id);
	$anio      = gnf_normalize_year($anio);
	if (! $centro_id) {
		return null;
	}

	$table  = $wpdb->prefix . 'gn_matriculas';
	$exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
	if ($exists !== $table) {
		return null;
	}

	return $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE centro_id = %d AND anio = %d ORDER BY id DESC LIMIT 1",
			$centro_id,
			$anio
		)
	);
}

function gnf_get_matricula_selected_retos_fallback($centro_id, $anio = null)
{
	$row = gnf_get_latest_matricula_row($centro_id, $anio);
	if (! $row) {
		return array();
	}

	$retos = json_decode((string) ($row->retos_seleccionados ?? ''), true);
	if (! is_array($retos) || empty($retos)) {
		$data  = json_decode((string) ($row->data ?? ''), true);
		$retos = is_array($data) ? ($data['bae-retos-seleccionados'] ?? array()) : array();
	}

	return gnf_normalize_reto_ids((array) $retos);
}

function gnf_resolve_reto_ids_for_year($reto_ids, $anio = null)
{
	$anio          = gnf_normalize_year($anio);
	$reto_ids      = gnf_normalize_reto_ids((array) $reto_ids);
	$available     = gnf_get_available_retos_for_year($anio);
	$available_map = array();

	foreach ($available as $reto) {
		$slug = gnf_get_reto_canonical_slug($reto->post_title);
		if ($slug && empty($available_map[$slug])) {
			$available_map[$slug] = (int) $reto->ID;
		}
	}

	$resolved = array();
	foreach ($reto_ids as $reto_id) {
		$reto_id = (int) $reto_id;
		if ($reto_id <= 0) {
			continue;
		}

		$slug = gnf_get_reto_canonical_slug(get_the_title($reto_id));
		if ($slug && ! empty($available_map[$slug])) {
			$resolved[] = (int) $available_map[$slug];
			continue;
		}

		if (gnf_reto_has_form_for_year($reto_id, $anio)) {
			$resolved[] = $reto_id;
		}
	}

	return array_values(array_unique($resolved));
}

function gnf_get_obligatorio_reto_ids()
{
	$query = new WP_Query(array(
		'post_type'      => 'reto',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_query'     => array(
			array(
				'key'   => 'obligatorio_en_matricula',
				'value' => '1',
				'compare' => '=',
			),
		),
	));
	return array_map('intval', $query->posts);
}

/**
 * Expande una lista de IDs de retos: si un ID es de un reto padre (tiene hijos),
 * lo reemplaza por los IDs de sus hijos, para que en el panel se muestren los
 * retos hijos por separado (ej. Compostaje â†’ Valorizables, Limpieza).
 *
 * @param int[] $reto_ids IDs de retos (pueden ser padres o hijos).
 * @return int[]
 */
function gnf_expand_retos_con_hijos($reto_ids)
{
	if (! is_array($reto_ids)) {
		return array();
	}
	$result = array();
	foreach ($reto_ids as $id) {
		$id = (int) $id;
		if ($id <= 0) {
			continue;
		}
		$children = get_posts(array(
			'post_type'      => 'reto',
			'post_status'    => 'publish',
			'post_parent'    => $id,
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'orderby'        => 'menu_order title',
			'order'          => 'ASC',
		));
		if (! empty($children)) {
			$result = array_merge($result, array_map('intval', $children));
		} else {
			$result[] = $id;
		}
	}
	return array_values(array_unique($result));
}

/**
 * Colapsa una lista de IDs de retos a sus raÃ­ces (para pre-llenar el form de matrÃ­cula).
 * Si un ID es de un reto hijo, se devuelve el ID del padre raÃ­z.
 *
 * @param int[] $reto_ids IDs de retos (normalmente expandidos con hijos).
 * @return int[]
 */
function gnf_collapse_retos_a_raiz($reto_ids)
{
	if (! is_array($reto_ids)) {
		return array();
	}
	$roots = array();
	foreach ($reto_ids as $id) {
		$id = (int) $id;
		if ($id <= 0) {
			continue;
		}
		$current = $id;
		while ($current) {
			$post = get_post($current);
			if (! $post || $post->post_type !== 'reto') {
				break;
			}
			if ((int) $post->post_parent === 0) {
				$roots[] = $current;
				break;
			}
			$current = (int) $post->post_parent;
		}
	}
	return array_values(array_unique($roots));
}

/**
 * Encuentra el reto cuyo configuracion_por_anio contiene el form_id dado.
 */
function gnf_get_reto_by_form_id($form_id)
{
	$form_id = absint($form_id);

	$retos = get_posts(array(
		'post_type'      => 'reto',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
	));

	foreach ($retos as $reto) {
		$config = get_field('configuracion_por_anio', $reto->ID);
		if (!empty($config) && is_array($config)) {
			foreach ($config as $row) {
				if (!empty($row['wpforms_id']) && absint($row['wpforms_id']) === $form_id) {
					return $reto;
				}
			}
		}
	}

	return null;
}

/**
 * Obtiene el formulario WPForms para un reto segun el anio.
 * Lee desde configuracion_por_anio; solo devuelve si el aÃ±o estÃ¡ activo.
 *
 * @param int      $reto_id ID del reto.
 * @param int|null $anio    Anio (opcional, por defecto usa el anio activo).
 * @return int ID del formulario WPForms o 0 si no se encuentra.
 */
function gnf_get_reto_form_id_for_year($reto_id, $anio = null)
{
	$row = gnf_get_config_row_for_year($reto_id, $anio);
	if ($row && !empty($row['wpforms_id'])) {
		return absint($row['wpforms_id']);
	}
	return 0;
}

/**
 * Verifica si un reto tiene un formulario activo para el anio dado.
 *
 * @param int      $reto_id ID del reto.
 * @param int|null $anio    Anio.
 * @return bool True si hay un formulario registrado y activo para ese anio.
 */
function gnf_reto_has_form_for_year($reto_id, $anio = null)
{
	return gnf_get_reto_form_id_for_year($reto_id, $anio) > 0;
}

/**
 * Filtra IDs de retos dejando solo los que tienen formulario activo para el anio dado.
 *
 * @param array    $reto_ids IDs de retos.
 * @param int|null $anio     Anio.
 * @return array
 */
function gnf_filter_reto_ids_by_year_form($reto_ids, $anio = null)
{
	$anio     = gnf_normalize_year($anio);
	$reto_ids = gnf_normalize_reto_ids((array) $reto_ids);
	if (empty($reto_ids)) {
		return array();
	}

	$filtered = array();
	foreach ($reto_ids as $reto_id) {
		if (gnf_reto_has_form_for_year($reto_id, $anio)) {
			$filtered[] = (int) $reto_id;
		}
	}

	return array_values(array_unique($filtered));
}

/**
 * Ordena retos con los obligatorios primero y luego alfabetico por titulo.
 *
 * @param array $reto_ids IDs de retos.
 * @return array
 */
function gnf_sort_reto_ids_required_first($reto_ids)
{
	$reto_ids = gnf_normalize_reto_ids((array) $reto_ids);
	if (empty($reto_ids)) {
		return array();
	}

	usort(
		$reto_ids,
		static function ($a, $b) {
			$a_required = gnf_is_reto_required((int) $a) ? 0 : 1;
			$b_required = gnf_is_reto_required((int) $b) ? 0 : 1;
			if ($a_required !== $b_required) {
				return $a_required <=> $b_required;
			}

			$title_a = remove_accents((string) get_the_title((int) $a));
			$title_b = remove_accents((string) get_the_title((int) $b));
			return strcasecmp($title_a, $title_b);
		}
	);

	return array_values(array_unique($reto_ids));
}

/**
 * Obtiene el color de un reto.
 *
 * @param int    $reto_id ID del reto.
 * @param string $default Color fallback.
 * @return string Color hex.
 */
function gnf_get_reto_color($reto_id, $default = '#369484')
{
	$color = get_field('color_del_reto', $reto_id);
	return $color ?: $default;
}

/**
 * Formatea una fila de wp_gn_reto_entries para respuesta API/templates.
 * Centraliza la construcciÃ³n de datos de entry con metadatos del reto.
 *
 * @param object   $entry Fila de wp_gn_reto_entries.
 * @param int|null $anio  AÃ±o (para iconos/puntos per-year).
 * @return array Datos formateados en camelCase.
 */
function gnf_get_wpforms_form_definition( $form_id ) {
	if ( ! function_exists( 'wpforms' ) ) {
		return array();
	}

	$form = wpforms()->form->get( absint( $form_id ) );
	if ( ! $form || empty( $form->post_content ) ) {
		return array();
	}

	$form_data = json_decode( $form->post_content, true );
	return is_array( $form_data ) ? $form_data : array();
}

function gnf_is_wpforms_layout_field( $type ) {
	return in_array(
		(string) $type,
		array( 'html', 'content', 'divider', 'pagebreak', 'section-divider', 'layout', 'hidden' ),
		true
	);
}

function gnf_reto_entry_value_has_content( $value ) {
	if ( is_array( $value ) ) {
		$value = array_filter(
			array_map(
				static function ( $item ) {
					return is_scalar( $item ) ? trim( (string) $item ) : '';
				},
				$value
			),
			static function ( $item ) {
				return '' !== $item;
			}
		);
		return ! empty( $value );
	}

	if ( is_scalar( $value ) ) {
		return '' !== trim( (string) $value );
	}

	return false;
}

function gnf_get_wpforms_choice_label( $field, $value ) {
	$choices = is_array( $field['choices'] ?? null ) ? $field['choices'] : array();
	$value   = (string) $value;

	foreach ( $choices as $choice ) {
		$label = (string) ( $choice['label'] ?? '' );
		$key   = isset( $choice['value'] ) ? (string) $choice['value'] : $label;
		if ( $value === $key || $value === $label ) {
			return $label ?: $value;
		}
	}

	return $value;
}

function gnf_get_wpforms_display_value( $field, $raw_value ) {
	if ( is_array( $raw_value ) ) {
		$labels = array_map(
			static function ( $value ) use ( $field ) {
				return gnf_get_wpforms_choice_label( $field, $value );
			},
			$raw_value
		);
		$labels = array_filter(
			array_map(
				static function ( $value ) {
					return trim( (string) $value );
				},
				$labels
			),
			static function ( $value ) {
				return '' !== $value;
			}
		);
		return implode( ', ', $labels );
	}

	if ( ! is_scalar( $raw_value ) ) {
		return '';
	}

	$value = trim( (string) $raw_value );
	if ( '' === $value ) {
		return '';
	}

	if ( in_array( (string) ( $field['type'] ?? '' ), array( 'radio', 'select', 'checkbox' ), true ) ) {
		return gnf_get_wpforms_choice_label( $field, $value );
	}

	// File-upload fields store JSON file metadata as the raw value.
	// Don't show this as displayValue — the evidence is shown via EvidenceViewer.
	if ( 'file-upload' === (string) ( $field['type'] ?? '' ) ) {
		return '';
	}

	return $value;
}

function gnf_build_reto_entry_responses( $entry, $anio = null ) {
	$form_id = gnf_get_reto_form_id_for_year( (int) $entry->reto_id, $anio );
	if ( ! $form_id ) {
		return array();
	}

	$form_data   = gnf_get_wpforms_form_definition( $form_id );
	$form_fields = is_array( $form_data['fields'] ?? null ) ? $form_data['fields'] : array();
	$field_points = gnf_get_reto_field_points( (int) $entry->reto_id, $anio );
	$entry_data   = ! empty( $entry->data ) ? json_decode( $entry->data, true ) : array();
	$raw_values   = is_array( $entry_data['__raw_values__'] ?? null ) ? $entry_data['__raw_values__'] : array();
	$evidencias   = ! empty( $entry->evidencias ) ? json_decode( $entry->evidencias, true ) : array();
	$responses    = array();

	foreach ( $form_fields as $field ) {
		$field_id = absint( $field['id'] ?? 0 );
		if ( ! $field_id || gnf_is_wpforms_layout_field( $field['type'] ?? '' ) ) {
			continue;
		}

		$field_evidencias = array_values(
			array_filter(
				(array) $evidencias,
				static function ( $evidencia ) use ( $field_id ) {
					return is_array( $evidencia ) && absint( $evidencia['field_id'] ?? 0 ) === $field_id;
				}
			)
		);

		$raw_value       = $raw_values[ $field_id ] ?? null;
		$field_type      = (string) ( $field['type'] ?? 'text' );
		$is_file_field   = in_array( $field_type, array( 'file-upload', 'file' ), true );
		$display_value   = gnf_get_wpforms_display_value( $field, $raw_value );
		$has_value       = gnf_reto_entry_value_has_content( $raw_value ) || ! empty( $field_evidencias );
		$should_include  = $has_value || $is_file_field;
		$points_config  = $field_points[ $field_id ] ?? array();
		$label          = trim( (string) ( $field['label'] ?? $field['name'] ?? '' ) );

		if ( ! $should_include ) {
			continue;
		}

		$responses[] = array(
			'fieldId'      => $field_id,
			'label'        => $label ?: sprintf( 'Campo %d', $field_id ),
			'type'         => $field_type,
			'displayValue' => $display_value,
			'hasValue'     => $has_value,
			'puntos'       => (int) ( $points_config['puntos'] ?? 0 ),
			'evidencias'   => $field_evidencias,
		);
	}

	return $responses;
}

function gnf_format_reto_entry($entry, $anio = null)
{
	$reto    = get_post($entry->reto_id);
	$max_pts = gnf_get_reto_max_points($entry->reto_id, $anio);

	return array(
		'id'              => (int) $entry->id,
		'retoId'          => (int) $entry->reto_id,
		'retoTitulo'      => $reto ? $reto->post_title : '',
		'retoColor'       => $reto ? gnf_get_reto_color($reto->ID) : '',
		'retoIconUrl'     => $reto ? gnf_get_reto_icon_url($reto->ID, 'thumbnail', $anio) : '',
		'centroId'        => (int) $entry->centro_id,
		'userId'          => (int) $entry->user_id,
		'anio'            => (int) $entry->anio,
		'estado'          => $entry->estado,
		'puntaje'         => (int) $entry->puntaje,
		'puntajeMaximo'   => $max_pts,
		'supervisorNotes' => $entry->supervisor_notes ?: '',
		'evidencias'      => $entry->evidencias ? json_decode($entry->evidencias, true) : array(),
		'responses'       => gnf_build_reto_entry_responses( $entry, $anio ),
		'createdAt'       => $entry->created_at,
		'updatedAt'       => $entry->updated_at,
	);
}

/**
 * Obtiene la URL del icono de un reto.
 * Prioridad: icono per-year en configuracion_por_anio > Featured Image.
 *
 * @param int      $reto_id ID del reto.
 * @param string   $size    TamaÃ±o de la imagen (default 'thumbnail').
 * @param int|null $anio    Anio para buscar icono per-year.
 * @return string URL del icono o cadena vacÃ­a.
 */
function gnf_get_reto_icon_url($reto_id, $size = 'thumbnail', $anio = null)
{
	// Check per-year icon first.
	$row = gnf_get_config_row_for_year($reto_id, $anio);
	if ($row && !empty($row['icono'])) {
		$src = wp_get_attachment_image_url($row['icono'], $size);
		if ($src) {
			return $src;
		}
	}

	// Fallback to Featured Image.
	$thumb_id = get_post_thumbnail_id($reto_id);
	if ($thumb_id) {
		$src = wp_get_attachment_image_url($thumb_id, $size);
		return $src ?: '';
	}
	return '';
}


/**
 * Obtiene informacion de los formularios anuales configurados para un reto.
 *
 * @param int $reto_id ID del reto.
 * @return array Array con informacion de formularios keyed by year.
 */
function gnf_get_reto_forms_info($reto_id)
{
	$forms  = array();
	$config = get_field('configuracion_por_anio', $reto_id);
	if (!empty($config) && is_array($config)) {
		foreach ($config as $row) {
			if (!empty($row['wpforms_id']) && !empty($row['anio'])) {
				$forms[$row['anio']] = array(
					'form_id' => absint($row['wpforms_id']),
					'anio'    => absint($row['anio']),
					'activo'  => !empty($row['activo']),
					'notas'   => $row['notas'] ?? '',
				);
			}
		}
	}

	krsort($forms);
	return $forms;
}

/**
 * Obtiene los anios disponibles (activos) para un reto.
 *
 * @param int $reto_id ID del reto.
 * @return array Array de anios disponibles.
 */
function gnf_get_reto_available_years($reto_id)
{
	$years  = array();
	$config = get_field('configuracion_por_anio', $reto_id);
	if (!empty($config) && is_array($config)) {
		foreach ($config as $row) {
			if (!empty($row['anio']) && !empty($row['activo'])) {
				$years[] = absint($row['anio']);
			}
		}
	}

	rsort($years);
	return array_unique($years);
}

/**
 * Obtiene la fila activa de configuracion_por_anio que corresponde exactamente
 * al aÃ±o solicitado.
 * FunciÃ³n interna compartida por los demÃ¡s helpers.
 *
 * @param int      $reto_id ID del reto.
 * @param int|null $anio    Anio (null = aÃ±o activo).
 * @return array|null La fila del repeater o null.
 */
function gnf_get_config_row_for_year($reto_id, $anio = null)
{
	if (null === $anio) {
		$anio = gnf_get_active_year();
	}
	$anio = absint($anio);

	$config = get_field('configuracion_por_anio', $reto_id);
	if (!empty($config) && is_array($config)) {
		foreach ($config as $row) {
			if (empty($row['activo'])) {
				continue;
			}
			if (!empty($row['anio']) && absint($row['anio']) === $anio) {
				return $row;
			}
		}
	}
	return null;
}

/**
 * Obtiene la URL del PDF de un reto para un aÃ±o especÃ­fico.
 *
 * @param int      $reto_id ID del reto.
 * @param int|null $anio    Anio.
 * @return string URL del PDF o cadena vacÃ­a.
 */
function gnf_get_reto_pdf_url($reto_id, $anio = null)
{
	$row = gnf_get_config_row_for_year($reto_id, $anio);
	if ($row && !empty($row['pdf'])) {
		$url = wp_get_attachment_url($row['pdf']);
		return $url ?: '';
	}
	return '';
}

/**
 * Obtiene el mapa de puntos por campo desde configuracion_por_anio.field_points.
 * Retorna array asociativo: field_id => ['puntos' => int, 'tipo' => string, 'label' => string]
 *
 * @param int      $reto_id ID del reto.
 * @param int|null $anio    Anio.
 * @return array Mapa de puntos por campo.
 */
function gnf_get_reto_field_points($reto_id, $anio = null)
{
	$row = gnf_get_config_row_for_year($reto_id, $anio);
	if (!$row || empty($row['field_points']) || !is_array($row['field_points'])) {
		return array();
	}

	$map = array();
	foreach ($row['field_points'] as $fp) {
		if (!isset($fp['field_id'])) {
			continue;
		}
		$fid       = (int) $fp['field_id'];
		$map[$fid] = array(
			'puntos' => isset($fp['puntos']) ? (int) $fp['puntos'] : 0,
			'tipo'   => isset($fp['field_type']) ? $fp['field_type'] : '',
			'label'  => isset($fp['field_label']) ? $fp['field_label'] : '',
		);
	}
	return $map;
}

/**
 * Obtiene centro educativo asociado a un usuario docente.
 */
function gnf_get_centro_for_docente($user_id)
{
	$user_id = absint( $user_id );
	if ( ! $user_id ) {
		return 0;
	}

	$args = array(
		'post_type'      => 'centro_educativo',
		'posts_per_page' => 1,
		'meta_query'     => array(
			'relation' => 'OR',
			array(
				'key'     => 'docentes_asociados',
				'value'   => ':"' . intval($user_id) . '";',
				'compare' => 'LIKE',
			),
			array(
				'key'     => 'docentes_asociados',
				'value'   => 'i:' . intval( $user_id ) . ';',
				'compare' => 'LIKE',
			),
		),
		'fields' => 'ids',
	);
	$query = new WP_Query($args);
	if ($query->have_posts()) {
		$centro_id = (int) $query->posts[0];
		gnf_sync_docente_centro_assignment( $user_id, $centro_id, array( 'sync_correo_institucional' => false ) );
		return $centro_id;
	}

	$meta_candidates = array( 'centro_educativo_id', 'centro_solicitado', 'gnf_centro_id' );
	foreach ( $meta_candidates as $meta_key ) {
		$centro_id = absint( get_user_meta( $user_id, $meta_key, true ) );
		if ( $centro_id && 'centro_educativo' === get_post_type( $centro_id ) ) {
			gnf_sync_docente_centro_assignment( $user_id, $centro_id, array( 'sync_correo_institucional' => false ) );
			return $centro_id;
		}
	}

	global $wpdb;

	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT pm.post_id, pm.meta_value
			 FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE pm.meta_key = %s
			   AND p.post_type = %s
			   AND p.post_status NOT IN ('auto-draft', 'trash')",
			'docentes_asociados',
			'centro_educativo'
		)
	);

	foreach ( (array) $rows as $row ) {
		$docentes = maybe_unserialize( $row->meta_value );
		if ( ! is_array( $docentes ) ) {
			$docentes = '' !== trim( (string) $row->meta_value ) ? array( $row->meta_value ) : array();
		}

		$docentes = array_values( array_filter( array_map( 'absint', (array) $docentes ) ) );
		if ( in_array( $user_id, $docentes, true ) ) {
			$centro_id = (int) $row->post_id;
			gnf_sync_docente_centro_assignment( $user_id, $centro_id, array( 'sync_correo_institucional' => false ) );
			return $centro_id;
		}
	}

	return 0;
}

/**
 * Protege endpoints AJAX bÃ¡sica con nonce.
 */
function gnf_verify_ajax_nonce()
{
	check_ajax_referer('gnf_nonce', 'nonce');
}

/**
 * Estado de docente (activo/pending).
 */
function gnf_get_docente_estado($user_id)
{
	$status = get_user_meta($user_id, 'gnf_docente_status', true);
	if ( ! $status ) {
		$status = get_user_meta( $user_id, 'gnf_docente_estado', true );
	}
	if ($status) {
		return $status;
	}
	$roles = (array) get_userdata($user_id)->roles;
	if (in_array('docente', $roles, true)) {
		return 'activo';
	}
	return 'pendiente';
}

/**
 * Estado de supervisor/comite (activo/pendiente/rechazado).
 *
 * @param int $user_id ID del usuario.
 * @return string
 */
function gnf_get_supervisor_estado( $user_id ) {
	$status = get_user_meta( $user_id, 'gnf_supervisor_status', true );
	if ( ! $status ) {
		$status = get_user_meta( $user_id, 'gnf_supervisor_estado', true );
	}
	if ( $status ) {
		return $status;
	}

	$user = get_userdata( $user_id );
	if ( $user && ( in_array( 'supervisor', (array) $user->roles, true ) || in_array( 'comite_bae', (array) $user->roles, true ) ) ) {
		return 'activo';
	}

	return 'pendiente';
}

/**
 * Asocia un docente al centro si aun no pertenece al arreglo docentes_asociados.
 *
 * @param int $user_id   ID del docente.
 * @param int $centro_id ID del centro.
 * @return void
 */
function gnf_attach_docente_to_centro( $user_id, $centro_id ) {
	$user_id   = absint( $user_id );
	$centro_id = absint( $centro_id );

	if ( ! $user_id || ! $centro_id || 'centro_educativo' !== get_post_type( $centro_id ) ) {
		return;
	}

	$docentes = array();
	if ( function_exists( 'get_field' ) ) {
		$docentes = (array) get_field( 'docentes_asociados', $centro_id );
	} else {
		$docentes = (array) get_post_meta( $centro_id, 'docentes_asociados', true );
	}

	$docentes = array_map( 'absint', $docentes );
	if ( ! in_array( $user_id, $docentes, true ) ) {
		$docentes[] = $user_id;
		if ( function_exists( 'update_field' ) ) {
			update_field( 'docentes_asociados', array_values( $docentes ), $centro_id );
		} else {
			update_post_meta( $centro_id, 'docentes_asociados', array_values( $docentes ) );
		}
	}
}

/**
 * Remueve un docente del arreglo docentes_asociados del centro.
 *
 * @param int $user_id   ID del docente.
 * @param int $centro_id ID del centro.
 * @return void
 */
function gnf_detach_docente_from_centro( $user_id, $centro_id ) {
	$user_id   = absint( $user_id );
	$centro_id = absint( $centro_id );

	if ( ! $user_id || ! $centro_id || 'centro_educativo' !== get_post_type( $centro_id ) ) {
		return;
	}

	$docentes = array();
	if ( function_exists( 'get_field' ) ) {
		$docentes = (array) get_field( 'docentes_asociados', $centro_id );
	} else {
		$docentes = (array) get_post_meta( $centro_id, 'docentes_asociados', true );
	}

	$docentes = array_values(
		array_filter(
			array_map( 'absint', $docentes ),
			static function ( $docente_id ) use ( $user_id ) {
				return $docente_id && $docente_id !== $user_id;
			}
		)
	);

	if ( function_exists( 'update_field' ) ) {
		update_field( 'docentes_asociados', $docentes, $centro_id );
	} else {
		update_post_meta( $centro_id, 'docentes_asociados', $docentes );
	}
}

/**
 * Obtiene IDs de usuarios docentes que tienen tomado un centro.
 *
 * Considera el centro asociado activo, solicitudes pendientes y el arreglo docentes_asociados.
 *
 * @param int $centro_id        ID del centro.
 * @param int $exclude_user_id  Usuario a excluir del chequeo.
 * @return int[]
 */
function gnf_get_claiming_docente_ids_for_centro( $centro_id, $exclude_user_id = 0 ) {
	$centro_id       = absint( $centro_id );
	$exclude_user_id = absint( $exclude_user_id );

	if ( ! $centro_id || 'centro_educativo' !== get_post_type( $centro_id ) ) {
		return array();
	}

	$user_ids = array();
	$meta_keys = array( 'centro_educativo_id', 'centro_solicitado', 'gnf_centro_id' );
	foreach ( $meta_keys as $meta_key ) {
		$users = get_users(
			array(
				'fields'     => 'ids',
				'number'     => -1,
				'meta_key'   => $meta_key,
				'meta_value' => $centro_id,
			)
		);
		if ( ! empty( $users ) ) {
			$user_ids = array_merge( $user_ids, array_map( 'absint', $users ) );
		}
	}

	$asociados = function_exists( 'get_field' )
		? (array) get_field( 'docentes_asociados', $centro_id )
		: (array) get_post_meta( $centro_id, 'docentes_asociados', true );

	$user_ids = array_merge( $user_ids, array_map( 'absint', $asociados ) );
	$user_ids = array_values(
		array_filter(
			array_unique( $user_ids ),
			static function ( $user_id ) use ( $exclude_user_id ) {
				if ( ! $user_id ) {
					return false;
				}
				if ( $exclude_user_id && $user_id === $exclude_user_id ) {
					return false;
				}

				$user = get_userdata( $user_id );
				if ( ! $user instanceof WP_User ) {
					return false;
				}

				if ( in_array( 'docente', (array) $user->roles, true ) ) {
					return true;
				}

				return function_exists( 'gnf_infer_frontend_role_from_meta' ) && 'docente' === gnf_infer_frontend_role_from_meta( $user_id );
			}
		)
	);

	return $user_ids;
}

/**
 * Indica si un centro ya fue tomado por otro usuario docente.
 *
 * @param int $centro_id        ID del centro.
 * @param int $exclude_user_id  Usuario a excluir del chequeo.
 * @return bool
 */
function gnf_is_centro_claimed_by_other_docente( $centro_id, $exclude_user_id = 0 ) {
	return ! empty( gnf_get_claiming_docente_ids_for_centro( $centro_id, $exclude_user_id ) );
}

/**
 * Normaliza el filtro de registro usado en paneles admin.
 *
 * @param string $value Valor recibido.
 * @return string
 */
function gnf_normalize_centro_registration_filter( $value ) {
	$value = sanitize_key( (string) $value );

	if ( in_array( $value, array( 'all', 'registered', 'unregistered' ), true ) ) {
		return $value;
	}

	return 'registered';
}

/**
 * Obtiene IDs de centros que ya tienen al menos un docente asociado o solicitante.
 *
 * @return int[]
 */
function gnf_get_registered_centro_ids() {
	static $registered_ids = null;

	if ( null !== $registered_ids ) {
		return $registered_ids;
	}

	global $wpdb;

	$registered_ids = $wpdb->get_col(
		"SELECT DISTINCT CAST(meta_value AS UNSIGNED) FROM {$wpdb->usermeta}
		 WHERE meta_key IN ('centro_educativo_id','centro_solicitado','gnf_centro_id')
		   AND meta_value IS NOT NULL
		   AND meta_value != ''
		   AND meta_value != '0'"
	);

	$relationship_rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT pm.post_id, pm.meta_value
			 FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE pm.meta_key = %s
			   AND p.post_type = %s
			   AND p.post_status NOT IN ('auto-draft', 'trash')",
			'docentes_asociados',
			'centro_educativo'
		)
	);

	foreach ( (array) $relationship_rows as $row ) {
		$docentes = maybe_unserialize( $row->meta_value );
		if ( ! is_array( $docentes ) ) {
			$docentes = '' !== trim( (string) $row->meta_value ) ? array( $row->meta_value ) : array();
		}

		$docentes = array_values( array_filter( array_map( 'absint', $docentes ) ) );
		if ( ! empty( $docentes ) ) {
			$registered_ids[] = (int) $row->post_id;
		}
	}

	$registered_ids = array_values(
		array_unique(
			array_filter( array_map( 'absint', (array) $registered_ids ) )
		)
	);

	return $registered_ids;
}

/**
 * Filtra una lista de centros segun su estado de registro.
 *
 * @param int[]  $centro_ids   IDs base.
 * @param string $registration Filtro: registered, unregistered, all.
 * @return int[]
 */
function gnf_filter_centro_ids_by_registration( $centro_ids, $registration = 'registered' ) {
	$centro_ids    = array_values( array_filter( array_map( 'absint', (array) $centro_ids ) ) );
	$registration  = gnf_normalize_centro_registration_filter( $registration );

	if ( empty( $centro_ids ) || 'all' === $registration ) {
		return $centro_ids;
	}

	$registered_ids = gnf_get_registered_centro_ids();
	if ( empty( $registered_ids ) ) {
		return 'registered' === $registration ? array() : $centro_ids;
	}

	if ( 'unregistered' === $registration ) {
		return array_values( array_diff( $centro_ids, $registered_ids ) );
	}

	return array_values( array_intersect( $centro_ids, $registered_ids ) );
}

/**
 * Asigna un rol de forma estricta y verifica que quede persistido.
 *
 * @param int    $user_id ID del usuario.
 * @param string $role    Rol a asignar.
 * @param string $context Contexto para logging.
 * @return bool
 */
function gnf_set_user_role_strict( $user_id, $role, $context = '' ) {
	$user_id = absint( $user_id );
	$role    = sanitize_key( (string) $role );

	if ( ! $user_id || '' === $role || ! get_role( $role ) ) {
		return false;
	}

	$user = get_userdata( $user_id );
	if ( ! $user instanceof WP_User ) {
		return false;
	}

	if ( is_multisite() && ! is_user_member_of_blog( $user_id, get_current_blog_id() ) ) {
		$added = add_user_to_blog( get_current_blog_id(), $user_id, $role );
		if ( is_wp_error( $added ) ) {
			error_log( '[GNF role] ' . wp_json_encode( array(
				'context' => (string) $context,
				'user_id' => $user_id,
				'role'    => $role,
				'error'   => $added->get_error_message(),
			) ) );
			return false;
		}
	}

	$user = new WP_User( $user_id );
	$user->set_role( $role );
	clean_user_cache( $user_id );
	$fresh = get_userdata( $user_id );
	$ok    = $fresh instanceof WP_User && in_array( $role, (array) $fresh->roles, true );

	if ( ! $ok ) {
		error_log( '[GNF role] ' . wp_json_encode( array(
			'context' => (string) $context,
			'user_id' => $user_id,
			'role'    => $role,
			'roles'   => $fresh instanceof WP_User ? array_values( (array) $fresh->roles ) : array(),
		) ) );
	}

	return $ok;
}

/**
 * Infiere el rol frontend correcto a partir de metadatos.
 *
 * @param int $user_id ID del usuario.
 * @return string
 */
function gnf_infer_frontend_role_from_meta( $user_id ) {
	$user_id        = absint( $user_id );
	$requested_role = sanitize_key( (string) get_user_meta( $user_id, 'gnf_rol_solicitado', true ) );
	$region_id      = absint( get_user_meta( $user_id, 'region', true ) );
	$centro_id      = absint( get_user_meta( $user_id, 'centro_solicitado', true ) );

	if ( ! $centro_id ) {
		$centro_id = absint( get_user_meta( $user_id, 'centro_educativo_id', true ) );
	}
	if ( ! $centro_id ) {
		$centro_id = absint( get_user_meta( $user_id, 'gnf_centro_id', true ) );
	}

	if ( 'comite_bae' === $requested_role ) {
		return 'comite_bae';
	}

	if (
		'supervisor' === $requested_role
		|| $region_id
		|| metadata_exists( 'user', $user_id, 'gnf_supervisor_status' )
		|| metadata_exists( 'user', $user_id, 'gnf_supervisor_estado' )
	) {
		return 'supervisor';
	}

	if (
		$centro_id
		|| metadata_exists( 'user', $user_id, 'gnf_docente_status' )
		|| metadata_exists( 'user', $user_id, 'gnf_docente_estado' )
	) {
		return 'docente';
	}

	return '';
}

/**
 * Repara usuarios que quedaron como subscriber aunque su metadata indica otro rol.
 *
 * @param WP_User|int $user_or_id Usuario o ID.
 * @param string      $context    Contexto para logging.
 * @return WP_User|null
 */
function gnf_maybe_restore_frontend_role( $user_or_id, $context = '' ) {
	$user = $user_or_id instanceof WP_User ? $user_or_id : get_userdata( absint( $user_or_id ) );
	if ( ! $user instanceof WP_User ) {
		return null;
	}

	$roles = array_values( (array) $user->roles );
	if ( array_intersect( $roles, array( 'docente', 'supervisor', 'comite_bae', 'administrator' ) ) ) {
		return $user;
	}

	if ( ! in_array( 'subscriber', $roles, true ) ) {
		return $user;
	}

	$inferred_role = gnf_infer_frontend_role_from_meta( $user->ID );
	if ( '' === $inferred_role ) {
		return $user;
	}

	if ( gnf_set_user_role_strict( $user->ID, $inferred_role, 'restore_frontend_role:' . $context ) ) {
		wp_set_current_user( $user->ID );
		$restored = get_userdata( $user->ID );
		error_log( '[GNF role] ' . wp_json_encode( array(
			'context'       => (string) $context,
			'user_id'       => (int) $user->ID,
			'from_roles'    => $roles,
			'inferred_role' => $inferred_role,
		) ) );
		return $restored instanceof WP_User ? $restored : $user;
	}

	return $user;
}

/**
 * Corrige en lote usuarios subscriber que realmente eran docentes.
 *
 * @param array $args Argumentos.
 * @return array<string,int>
 */
function gnf_run_bulk_fix_subscriber_docentes( $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'after' => '',
		)
	);

	$query_args = array(
		'role'   => 'subscriber',
		'fields' => 'all',
		'number' => -1,
	);

	if ( '' !== $args['after'] ) {
		$query_args['date_query'] = array(
			array(
				'after'     => (string) $args['after'],
				'inclusive' => true,
				'column'    => 'user_registered',
			),
		);
	}

	$users = get_users( $query_args );

	$result = array(
		'scanned' => 0,
		'updated' => 0,
		'skipped' => 0,
		'failed'  => 0,
	);

	foreach ( (array) $users as $user ) {
		if ( ! $user instanceof WP_User ) {
			continue;
		}

		++$result['scanned'];
		$roles = array_values( (array) $user->roles );
		if ( array_intersect( $roles, array( 'docente', 'supervisor', 'comite_bae', 'administrator' ) ) ) {
			++$result['skipped'];
			continue;
		}

		$inferred = gnf_infer_frontend_role_from_meta( $user->ID );
		if ( '' === $inferred ) {
			++$result['skipped'];
			continue;
		}

		if ( gnf_set_user_role_strict( $user->ID, $inferred, 'bulk_fix_subscriber_docentes' ) ) {
			++$result['updated'];
			continue;
		}

		++$result['failed'];
	}

	return $result;
}

/**
 * Normaliza texto libre para matching de centros.
 *
 * @param string $value Valor original.
 * @return string
 */
function gnf_normalize_centro_match_text( $value ) {
	$value = remove_accents( (string) $value );
	$value = strtolower( $value );
	$value = preg_replace( '/[^a-z0-9]+/', ' ', $value );
	$value = preg_replace( '/\s+/', ' ', (string) $value );
	return trim( (string) $value );
}

/**
 * Determina si un valor puede representar una institucion en texto.
 *
 * @param mixed $value Valor a evaluar.
 * @return bool
 */
function gnf_is_viable_docente_institucion_value( $value ) {
	if ( ! is_scalar( $value ) ) {
		return false;
	}

	$value = trim( (string) $value );
	if ( '' === $value || is_email( $value ) || preg_match( '/^\d+$/', $value ) ) {
		return false;
	}

	return strlen( gnf_normalize_centro_match_text( $value ) ) >= 4;
}

/**
 * Obtiene el nombre legacy de institucion almacenado en el usuario.
 *
 * @param int $user_id ID del usuario.
 * @return string
 */
function gnf_get_docente_institucion_text( $user_id ) {
	$user_id        = absint( $user_id );
	$preferred_keys = array(
		'institucion',
		'institución',
		'institucion_educativa',
		'centro_educativo',
		'centro_educativo_nombre',
		'centro_nombre',
		'nombre_centro_educativo',
		'nombre_del_centro',
		'school_name',
		'institution',
	);

	foreach ( $preferred_keys as $key ) {
		$value = get_user_meta( $user_id, $key, true );
		if ( gnf_is_viable_docente_institucion_value( $value ) ) {
			return trim( (string) $value );
		}
	}

	$all_meta = get_user_meta( $user_id );
	foreach ( (array) $all_meta as $meta_key => $values ) {
		$normalized_key = gnf_normalize_centro_match_text( $meta_key );
		$is_candidate   = false !== strpos( $normalized_key, 'institucion' )
			|| false !== strpos( $normalized_key, 'institution' )
			|| false !== strpos( $normalized_key, 'school' )
			|| ( false !== strpos( $normalized_key, 'centro' ) && false !== strpos( $normalized_key, 'nombre' ) );

		if ( ! $is_candidate ) {
			continue;
		}

		$value = is_array( $values ) ? maybe_unserialize( $values[0] ?? '' ) : maybe_unserialize( $values );
		if ( is_array( $value ) ) {
			$value = $value[0] ?? '';
		}

		if ( gnf_is_viable_docente_institucion_value( $value ) ) {
			return trim( (string) $value );
		}
	}

	return '';
}

/**
 * Obtiene codigo MEP legacy almacenado en el usuario, si existe.
 *
 * @param int $user_id ID del usuario.
 * @return string
 */
function gnf_get_docente_institucion_codigo( $user_id ) {
	$user_id = absint( $user_id );
	$keys    = array(
		'centro_codigo_mep',
		'centro_educativo_codigo_mep',
		'institucion_codigo_mep',
		'institution_code',
	);

	foreach ( $keys as $key ) {
		$value = trim( (string) get_user_meta( $user_id, $key, true ) );
		if ( '' !== $value ) {
			return $value;
		}
	}

	return '';
}

/**
 * Elige un centro candidato usando region como desempate cuando existe.
 *
 * @param int[] $candidate_ids IDs candidatos.
 * @param int   $region_id Direccion regional deseada.
 * @return int
 */
function gnf_pick_best_centro_candidate( $candidate_ids, $region_id = 0 ) {
	$candidate_ids = array_values( array_filter( array_map( 'absint', (array) $candidate_ids ) ) );
	if ( 1 === count( $candidate_ids ) ) {
		return (int) $candidate_ids[0];
	}

	$region_id = absint( $region_id );
	if ( $region_id ) {
		$region_matches = array_values(
			array_filter(
				$candidate_ids,
				static function ( $centro_id ) use ( $region_id ) {
					return $region_id === gnf_get_centro_region_id( $centro_id );
				}
			)
		);

		if ( 1 === count( $region_matches ) ) {
			return (int) $region_matches[0];
		}
	}

	return 0;
}

/**
 * Corrige docentes con institucion legacy en texto y repara su centro asociado.
 *
 * @param array $args Argumentos.
 * @return array<string,int>
 */
function gnf_run_bulk_fix_docente_centros( $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'after' => '',
		)
	);

	$query_args = array(
		'fields' => 'all',
		'number' => -1,
	);

	if ( '' !== $args['after'] ) {
		$query_args['date_query'] = array(
			array(
				'after'     => (string) $args['after'],
				'inclusive' => true,
				'column'    => 'user_registered',
			),
		);
	}

	$users = get_users( $query_args );
	$centros = get_posts(
		array(
			'post_type'      => 'centro_educativo',
			'post_status'    => array( 'publish', 'pending', 'draft' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);

	$centros_by_name = array();
	$centros_by_code = array();
	foreach ( (array) $centros as $centro_id ) {
		$centro_id = absint( $centro_id );
		if ( ! $centro_id ) {
			continue;
		}

		$normalized_name = gnf_normalize_centro_match_text( get_the_title( $centro_id ) );
		if ( '' !== $normalized_name ) {
			$centros_by_name[ $normalized_name ][] = $centro_id;
		}

		$codigo = trim( (string) get_post_meta( $centro_id, 'codigo_mep', true ) );
		if ( '' !== $codigo ) {
			$centros_by_code[ $codigo ][] = $centro_id;
		}
	}

	$result = array(
		'scanned'        => 0,
		'updated'        => 0,
		'skipped'        => 0,
		'failed'         => 0,
		'matched_by_name'=> 0,
		'matched_by_code'=> 0,
		'claimed'        => 0,
		'unmatched'      => 0,
	);

	foreach ( (array) $users as $user ) {
		if ( ! $user instanceof WP_User ) {
			continue;
		}

		$roles         = array_values( (array) $user->roles );
		$primary_roles = array_intersect( $roles, array( 'administrator', 'supervisor', 'comite_bae' ) );
		if ( ! empty( $primary_roles ) ) {
			continue;
		}

		$current_centro = gnf_get_centro_for_docente( $user->ID );
		$inferred_role  = gnf_infer_frontend_role_from_meta( $user->ID );
		$institucion    = gnf_get_docente_institucion_text( $user->ID );
		$codigo_mep     = gnf_get_docente_institucion_codigo( $user->ID );
		$region_id      = gnf_get_user_region( $user->ID );
		$should_scan    = $current_centro
			|| 'docente' === $inferred_role
			|| in_array( 'docente', $roles, true )
			|| '' !== $institucion
			|| '' !== $codigo_mep;

		if ( ! $should_scan ) {
			continue;
		}

		++$result['scanned'];
		$changed = false;

		if ( ! in_array( 'docente', $roles, true ) ) {
			if ( 'docente' === $inferred_role || in_array( 'subscriber', $roles, true ) || '' !== $institucion || '' !== $codigo_mep ) {
				if ( gnf_set_user_role_strict( $user->ID, 'docente', 'bulk_fix_docente_centros' ) ) {
					$changed = true;
				} else {
					++$result['failed'];
					continue;
				}
			}
		}

		if ( $current_centro ) {
			$current_user_centro = absint( get_user_meta( $user->ID, 'centro_solicitado', true ) );
			$current_user_centro_alt = absint( get_user_meta( $user->ID, 'centro_educativo_id', true ) );
			$current_user_centro_legacy = absint( get_user_meta( $user->ID, 'gnf_centro_id', true ) );
			$current_region = absint( get_user_meta( $user->ID, 'region', true ) );
			$current_correo = (string) get_post_meta( $current_centro, 'correo_institucional', true );

			if (
				$current_user_centro !== $current_centro
				|| $current_user_centro_alt !== $current_centro
				|| $current_user_centro_legacy !== $current_centro
				|| ( $region_id && $current_region !== $region_id )
				|| $current_correo !== $user->user_email
			) {
				gnf_sync_docente_centro_assignment( $user->ID, $current_centro, array( 'sync_correo_institucional' => true ) );
				$changed = true;
			}

			if ( $changed ) {
				++$result['updated'];
			} else {
				++$result['skipped'];
			}
			continue;
		}

		$matched_centro = 0;
		$matched_by     = '';
		if ( '' !== $codigo_mep && ! empty( $centros_by_code[ $codigo_mep ] ) ) {
			$matched_centro = gnf_pick_best_centro_candidate( $centros_by_code[ $codigo_mep ], $region_id );
			if ( $matched_centro ) {
				$matched_by = 'code';
			}
		}

		$normalized_name = gnf_normalize_centro_match_text( $institucion );
		if ( ! $matched_centro && '' !== $normalized_name && ! empty( $centros_by_name[ $normalized_name ] ) ) {
			$matched_centro = gnf_pick_best_centro_candidate( $centros_by_name[ $normalized_name ], $region_id );
			if ( $matched_centro ) {
				$matched_by = 'name';
			}
		}

		if ( ! $matched_centro && '' !== $institucion ) {
			$similar = function_exists( 'gnf_find_similar_centro' ) ? gnf_find_similar_centro( $institucion, $codigo_mep, $region_id ) : array();
			$matched_centro = gnf_pick_best_centro_candidate( $similar, $region_id );
			if ( $matched_centro ) {
				$matched_by = '' !== $codigo_mep ? 'code' : 'name';
			}
		}

		if ( ! $matched_centro ) {
			++$result['unmatched'];
			++$result['skipped'];
			continue;
		}

		if ( gnf_is_centro_claimed_by_other_docente( $matched_centro, $user->ID ) ) {
			++$result['claimed'];
			++$result['skipped'];
			continue;
		}

		$ok = gnf_sync_docente_centro_assignment( $user->ID, $matched_centro, array( 'sync_correo_institucional' => true ) );
		if ( ! $ok ) {
			++$result['failed'];
			continue;
		}

		if ( 'code' === $matched_by ) {
			++$result['matched_by_code'];
		} else {
			++$result['matched_by_name'];
		}
		++$result['updated'];
	}

	return $result;
}

/**
 * Marca docente como aprobado.
 */
function gnf_approve_docente($user_id)
{
	gnf_set_user_role_strict( $user_id, 'docente', 'approve_docente' );
	update_user_meta($user_id, 'gnf_docente_status', 'activo');
	update_user_meta($user_id, 'gnf_docente_estado', 'activo');
	gnf_insert_notification($user_id, 'docente_aprobado', 'Tu cuenta fue aprobada.', 'docente', $user_id);
}

/**
 * Marca supervisor o comitÃ© como aprobado sin alterar su rol solicitado.
 *
 * @param int $user_id ID del usuario.
 * @return void
 */
function gnf_approve_supervisor( $user_id ) {
	$requested_role = sanitize_key( (string) get_user_meta( $user_id, 'gnf_rol_solicitado', true ) );
	$role_to_set    = 'comite_bae' === $requested_role ? 'comite_bae' : 'supervisor';
	gnf_set_user_role_strict( $user_id, $role_to_set, 'approve_supervisor' );
	update_user_meta( $user_id, 'gnf_supervisor_status', 'activo' );
	update_user_meta( $user_id, 'gnf_supervisor_estado', 'activo' );
	gnf_insert_notification( $user_id, 'cuenta_aprobada', 'Tu cuenta ha sido aprobada. Ya puedes acceder al sistema.', 'usuario', $user_id );
}

/**
 * Activa docentes legacy que quedaron pendientes aunque el proyecto usa auto-aprobacion.
 *
 * @return array<string,int>
 */
function gnf_run_docente_autoapprove_migration() {
	$result = array(
		'scanned' => 0,
		'updated' => 0,
	);

	$user_ids = get_users(
		array(
			'fields' => 'ids',
			'number' => -1,
		)
	);

	foreach ( (array) $user_ids as $user_id ) {
		$user_id       = absint( $user_id );
		$user          = get_userdata( $user_id );
		$inferred_role = $user_id ? gnf_infer_frontend_role_from_meta( $user_id ) : '';

		if ( ! $user instanceof WP_User ) {
			continue;
		}

		if ( ! in_array( 'docente', (array) $user->roles, true ) && 'docente' !== $inferred_role ) {
			continue;
		}

		++$result['scanned'];

		if ( ! in_array( 'docente', (array) $user->roles, true ) ) {
			gnf_set_user_role_strict( $user_id, 'docente', 'docente_autoapprove_migration' );
		}

		$current_status = (string) get_user_meta( $user_id, 'gnf_docente_status', true );
		$legacy_status  = (string) get_user_meta( $user_id, 'gnf_docente_estado', true );

		if ( 'activo' !== $current_status || 'activo' !== $legacy_status ) {
			update_user_meta( $user_id, 'gnf_docente_status', 'activo' );
			update_user_meta( $user_id, 'gnf_docente_estado', 'activo' );
			++$result['updated'];
		}
	}

	if ( $result['updated'] > 0 && function_exists( 'gnf_clear_admin_stats_cache' ) ) {
		gnf_clear_admin_stats_cache();
	}

	return $result;
}

/**
 * Ejecuta una sola vez la migracion de auto-aprobacion docente.
 *
 * @return void
 */
function gnf_maybe_run_docente_autoapprove_migration() {
	$version = get_option( 'gnf_docente_autoapprove_migration_version', '' );
	if ( '2026-04-docente-autoapprove' === $version ) {
		return;
	}

	gnf_run_docente_autoapprove_migration();
	update_option( 'gnf_docente_autoapprove_migration_version', '2026-04-docente-autoapprove', false );
}
add_action( 'init', 'gnf_maybe_run_docente_autoapprove_migration', 26 );

/**
 * Copia un meta legacy a su clave canonica si la canonica aun no existe.
 *
 * @param int    $user_id     ID del usuario.
 * @param string $legacy_key  Meta legacy.
 * @param string $target_key  Meta canonica.
 * @return bool
 */
function gnf_copy_user_meta_if_missing( $user_id, $legacy_key, $target_key ) {
	$current = get_user_meta( $user_id, $target_key, true );
	if ( '' !== $current && null !== $current ) {
		return false;
	}

	$legacy = get_user_meta( $user_id, $legacy_key, true );
	if ( '' === $legacy || null === $legacy ) {
		return false;
	}

	update_user_meta( $user_id, $target_key, $legacy );
	return true;
}

/**
 * Ejecuta backfill de la alineacion React/ACF una sola vez.
 *
 * @return void
 */
function gnf_maybe_run_alignment_migration() {
	$version = get_option( 'gnf_alignment_migration_version', '' );
	if ( '2026-03-react-yearly-acf' === $version ) {
		return;
	}

	if ( ! function_exists( 'get_field' ) || ! function_exists( 'update_field' ) ) {
		return;
	}

	$reto_ids = get_posts(
		array(
			'post_type'      => 'reto',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);

	foreach ( (array) $reto_ids as $reto_id ) {
		$rows    = get_field( 'configuracion_por_anio', $reto_id );
		$changed = false;
		$rows    = is_array( $rows ) ? $rows : array();

		foreach ( $rows as &$row ) {
			$field_points  = is_array( $row['field_points'] ?? null ) ? $row['field_points'] : array();
			$puntaje_total = 0;
			foreach ( $field_points as $field_point ) {
				$puntaje_total += absint( $field_point['puntos'] ?? 0 );
			}

			if ( (int) ( $row['puntaje_total'] ?? 0 ) !== $puntaje_total ) {
				$row['puntaje_total'] = $puntaje_total;
				$changed              = true;
			}
		}
		unset( $row );

		if ( $changed ) {
			update_field( 'configuracion_por_anio', array_values( $rows ), $reto_id );
		}
	}

	$user_ids = get_users(
		array(
			'fields' => 'ids',
		)
	);

	foreach ( (array) $user_ids as $user_id ) {
		gnf_copy_user_meta_if_missing( $user_id, 'gnf_docente_estado', 'gnf_docente_status' );
		gnf_copy_user_meta_if_missing( $user_id, 'gnf_supervisor_estado', 'gnf_supervisor_status' );
		gnf_copy_user_meta_if_missing( $user_id, 'gnf_centro_id', 'centro_solicitado' );
		gnf_copy_user_meta_if_missing( $user_id, 'gnf_region_id', 'region' );
		gnf_copy_user_meta_if_missing( $user_id, 'gnf_region', 'region' );
	}

	$terms = get_terms(
		array(
			'taxonomy'   => 'gn_region',
			'hide_empty' => false,
		)
	);

	if ( ! is_wp_error( $terms ) ) {
		foreach ( $terms as $term ) {
			$current = get_term_meta( $term->term_id, 'gnf_dre_activa', true );
			if ( '' !== $current && null !== $current ) {
				continue;
			}

			$legacy = get_term_meta( $term->term_id, 'gnf_enabled', true );
			if ( '' !== $legacy && null !== $legacy ) {
				update_term_meta( $term->term_id, 'gnf_dre_activa', $legacy );
			}
		}
	}

	update_option( 'gnf_alignment_migration_version', '2026-03-react-yearly-acf', false );
}
add_action( 'init', 'gnf_maybe_run_alignment_migration', 25 );

/**
 * Crea notificaciones a admins.
 */
function gnf_notify_admins($tipo, $mensaje, $relacion_tipo = '', $relacion_id = 0)
{
	$admins = get_users(array('role' => 'administrator'));
	foreach ($admins as $admin) {
		gnf_insert_notification($admin->ID, $tipo, $mensaje, $relacion_tipo, $relacion_id);
	}
}

/**
 * Solicitar correcciÃ³n (ticket) y notificar.
 */
function gnf_request_correction($entry_id, $mensaje, $supervisor_id)
{
	global $wpdb;
	$table = $wpdb->prefix . 'gn_reto_entries';
	$entry = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $entry_id));
	if (! $entry) {
		return;
	}

	$wpdb->update(
		$table,
		array(
			'estado'          => 'correccion',
			'supervisor_notes' => $mensaje,
			'updated_at'      => current_time('mysql'),
		),
		array('id' => $entry_id),
		array('%s', '%s', '%s'),
		array('%d')
	);

	gnf_insert_notification($entry->user_id, 'correccion', $mensaje, 'reto_entry', $entry_id);
	gnf_recalcular_puntaje_centro($entry->centro_id, (int) $entry->anio);
	gnf_clear_supervisor_cache();
}

/**
 * Limpia cache de panel supervisor.
 */
function gnf_clear_supervisor_cache()
{
	global $wpdb;
	$like = $wpdb->esc_like('_transient_gnf_sup_entries_') . '%';
	$rows = $wpdb->get_col($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like));
	if ($rows) {
		foreach ($rows as $row) {
			$key = str_replace('_transient_', '', $row);
			delete_transient($key);
		}
	}
}

/**
 * Renderiza bloque de login/registro.
 */
function gnf_render_auth_block($args = array())
{
	$args = wp_parse_args(
		$args,
		array(
			'title'          => __('Acceso', 'guardianes-formularios'),
			'description'    => __('Inicia sesion para continuar.', 'guardianes-formularios'),
			'show_register'  => true,
			'redirect'       => esc_url_raw(home_url(add_query_arg(array()))),
		)
	);

	// Obtener regiones para el selector.
	$regiones = get_terms(array(
		'taxonomy'   => 'gn_region',
		'hide_empty' => false,
		'orderby'    => 'name',
		'order'      => 'ASC',
	));

	ob_start();
	$msg_error  = isset($_GET['gnf_err']) ? sanitize_text_field(wp_unslash($_GET['gnf_err'])) : '';
	$msg_notice = isset($_GET['gnf_msg']) ? sanitize_text_field(wp_unslash($_GET['gnf_msg'])) : '';
?>
	<div class="gnf-auth gnf-auth--wide">
		<div class="gnf-auth__card gnf-auth__card--wide">
			<div class="gnf-auth__logo">
				<img src="<?php echo esc_url(GNF_LOGO_URL); ?>" alt="Guardianes" style="background: #fff; border-radius: 4px;" />
			</div>
			<h2><?php echo esc_html($args['title']); ?></h2>
			<p class="gnf-muted"><?php echo esc_html($args['description']); ?></p>

			<div class="gnf-auth__tabs">
				<button type="button" class="gnf-btn gnf-auth__tab is-active" data-tab="login"><?php esc_html_e('Iniciar sesiÃ³n', 'guardianes-formularios'); ?></button>
				<?php if ($args['show_register']) : ?>
					<button type="button" class="gnf-btn gnf-btn--ghost gnf-auth__tab" data-tab="register"><?php esc_html_e('Registrarme como docente', 'guardianes-formularios'); ?></button>
				<?php endif; ?>
			</div>

			<?php if ($msg_error) : ?>
				<div class="gnf-alert gnf-alert--error"><?php echo esc_html($msg_error); ?></div>
			<?php endif; ?>
			<?php if ($msg_notice) : ?>
				<div class="gnf-alert gnf-alert--info"><?php echo esc_html($msg_notice); ?></div>
			<?php endif; ?>

			<div class="gnf-auth__panels">
				<div class="gnf-auth__panel" data-panel="login" style="display:block;">
					<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
						<?php wp_nonce_field('gnf_auth_login', 'gnf_nonce'); ?>
						<input type="hidden" name="action" value="gnf_auth_login" />
						<input type="hidden" name="redirect" value="<?php echo esc_url($args['redirect']); ?>" />
						<label><?php esc_html_e('Correo o usuario', 'guardianes-formularios'); ?>
							<input type="text" name="user_login" required />
						</label>
						<label><?php esc_html_e('ContraseÃ±a', 'guardianes-formularios'); ?>
							<input type="password" name="user_pass" required />
						</label>
						<button class="gnf-btn" type="submit"><?php esc_html_e('Ingresar', 'guardianes-formularios'); ?></button>
						<p class="gnf-auth__forgot">
							<a href="<?php echo esc_url(wp_lostpassword_url($args['redirect'])); ?>"><?php esc_html_e('Â¿Olvidaste tu contraseÃ±a?', 'guardianes-formularios'); ?></a>
						</p>
					</form>
				</div>

				<?php if ($args['show_register']) : ?>
					<div class="gnf-auth__panel" data-panel="register" style="display:none;">
						<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="gnf-register-form">
							<?php wp_nonce_field('gnf_docente_register', 'gnf_nonce'); ?>
							<input type="hidden" name="action" value="gnf_docente_register" />
							<input type="hidden" name="redirect" value="<?php echo esc_url(add_query_arg('tab', 'matricula', $args['redirect'])); ?>" />

							<!-- SecciÃ³n: Datos Personales (2 columnas) -->
							<div class="gnf-register-section">
								<h3 class="gnf-register-section__title">
									<span class="gnf-register-section__icon">ðŸ‘¤</span>
									<?php esc_html_e('Datos personales', 'guardianes-formularios'); ?>
								</h3>
								<div class="gnf-register-grid">
									<label class="gnf-register-field">
										<span class="gnf-register-field__label"><?php esc_html_e('Nombre completo', 'guardianes-formularios'); ?> <span class="required">*</span></span>
										<input type="text" name="display_name" required class="gnf-register-field__input" placeholder="Ej: MarÃ­a GarcÃ­a LÃ³pez" />
									</label>
									<label class="gnf-register-field">
										<span class="gnf-register-field__label"><?php esc_html_e('Correo electrÃ³nico', 'guardianes-formularios'); ?> <span class="required">*</span></span>
										<input type="email" name="user_email" required class="gnf-register-field__input" placeholder="docente@ejemplo.com" />
									</label>
									<label class="gnf-register-field">
										<span class="gnf-register-field__label"><?php esc_html_e('IdentificaciÃ³n (cÃ©dula)', 'guardianes-formularios'); ?> <span class="required">*</span></span>
										<input type="text" name="identificacion" required class="gnf-register-field__input" placeholder="X-XXXX-XXXX" />
									</label>
									<label class="gnf-register-field">
										<span class="gnf-register-field__label"><?php esc_html_e('TelÃ©fono', 'guardianes-formularios'); ?> <span class="required">*</span></span>
										<input type="tel" name="telefono" required class="gnf-register-field__input" placeholder="+506 XXXX-XXXX" />
									</label>
									<label class="gnf-register-field">
										<span class="gnf-register-field__label"><?php esc_html_e('Cargo', 'guardianes-formularios'); ?> <span class="required">*</span></span>
										<input type="text" name="cargo" required class="gnf-register-field__input" placeholder="Ej: Docente de ciencias" />
									</label>
									<label class="gnf-register-field">
										<span class="gnf-register-field__label"><?php esc_html_e('ContraseÃ±a', 'guardianes-formularios'); ?> <span class="required">*</span></span>
										<input type="password" name="user_pass" required minlength="8" class="gnf-register-field__input" placeholder="MÃ­nimo 8 caracteres" />
									</label>
								</div>
							</div>

							<!-- SecciÃ³n: Centro Educativo -->
							<div class="gnf-register-section">
								<h3 class="gnf-register-section__title">
									<span class="gnf-register-section__icon">ðŸ«</span>
									<?php esc_html_e('Centro educativo', 'guardianes-formularios'); ?>
								</h3>

								<!-- Buscar Centro Existente -->
								<div class="gnf-centro-panel gnf-centro-panel--existente">
									<input type="hidden" name="centro_id" id="gnf-centro-id-hidden" value="" />
									<label class="gnf-register-field gnf-register-field--full">
										<span class="gnf-register-field__label"><?php esc_html_e('Buscar centro educativo', 'guardianes-formularios'); ?></span>
										<div class="gnf-centro-search-wrapper">
											<input type="text"
												id="gnf-centro-search"
												class="gnf-register-field__input gnf-centro-search-input"
												placeholder="<?php esc_attr_e('Escriba el nombre o cÃ³digo MEP del centro...', 'guardianes-formularios'); ?>"
												autocomplete="off" />
											<div class="gnf-centro-search-icon">
												<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
													<circle cx="11" cy="11" r="8"></circle>
													<path d="m21 21-4.35-4.35"></path>
												</svg>
											</div>
											<div id="gnf-centro-results" class="gnf-centro-results"></div>
										</div>
										<small class="gnf-register-field__hint"><?php esc_html_e('Escriba al menos 2 caracteres para buscar', 'guardianes-formularios'); ?></small>
									</label>

									<!-- Preview del centro seleccionado -->
									<div id="gnf-centro-preview" class="gnf-centro-preview" style="display:none;">
										<div class="gnf-centro-preview__header">
											<span class="gnf-centro-preview__icon">âœ…</span>
											<span class="gnf-centro-preview__title"><?php esc_html_e('Centro seleccionado', 'guardianes-formularios'); ?></span>
											<button type="button" id="gnf-centro-clear" class="gnf-centro-preview__clear" title="<?php esc_attr_e('Cambiar centro', 'guardianes-formularios'); ?>">âœ•</button>
										</div>
										<div class="gnf-centro-preview__body">
											<div class="gnf-centro-preview__name" id="gnf-centro-preview-name"></div>
											<div class="gnf-centro-preview__meta">
												<span id="gnf-centro-preview-codigo"></span>
												<span id="gnf-centro-preview-region"></span>
											</div>
										</div>
									</div>

									<p class="gnf-register-note">
										<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
											<circle cx="12" cy="12" r="10"></circle>
											<path d="M12 16v-4"></path>
											<path d="M12 8h.01"></path>
										</svg>
										<?php esc_html_e('Al seleccionar un centro, su solicitud quedarÃ¡ pendiente de aprobaciÃ³n por el docente actual o administrador.', 'guardianes-formularios'); ?>
									</p>
								</div>

							</div>

							<!-- BotÃ³n Submit -->
							<div class="gnf-register-actions">
								<button class="gnf-btn gnf-btn--lg gnf-btn--full" type="submit">
									<?php esc_html_e('Crear mi cuenta de docente', 'guardianes-formularios'); ?>
								</button>
								<p class="gnf-register-disclaimer">
									<?php esc_html_e('Al registrarte, tu cuenta quedarÃ¡ pendiente de aprobaciÃ³n.', 'guardianes-formularios'); ?>
								</p>
							</div>
						</form>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<style>
		/* ===== Registro Docente Mejorado ===== */
		/* Sobrescribir estilos base con mayor especificidad */
		.gnf-auth--wide {
			padding: var(--gnf-space-6);
		}

		.gnf-auth--wide .gnf-auth__card--wide {
			max-width: 720px;
		}

		/* IMPORTANTE: Sobrescribir el flex-direction del formulario base */
		.gnf-auth__panel form.gnf-register-form {
			display: block !important;
			flex-direction: unset !important;
			gap: 0 !important;
		}

		.gnf-auth__panel form.gnf-register-form label {
			display: block !important;
			flex-direction: unset !important;
		}

		.gnf-auth__logo {
			text-align: center;
			margin-bottom: var(--gnf-space-4);
		}

		.gnf-auth__logo img {
			height: 50px;
			width: auto;
		}

		/* Secciones del formulario */
		.gnf-register-form .gnf-register-section {
			margin-bottom: var(--gnf-space-6) !important;
			padding-bottom: var(--gnf-space-5) !important;
			border-bottom: 1px solid var(--gnf-gray-200) !important;
		}

		.gnf-register-form .gnf-register-section:last-of-type {
			border-bottom: none !important;
		}

		.gnf-register-form .gnf-register-section__title {
			display: flex !important;
			align-items: center !important;
			gap: var(--gnf-space-2) !important;
			font-size: 1.1rem !important;
			font-weight: 600 !important;
			color: var(--gnf-gray-800) !important;
			margin: 0 0 var(--gnf-space-4) !important;
		}

		.gnf-register-form .gnf-register-section__icon {
			font-size: 1.25rem !important;
		}

		/* Grid 2 columnas - IMPORTANTE: mayor especificidad */
		.gnf-register-form .gnf-register-grid {
			display: grid !important;
			grid-template-columns: repeat(2, 1fr) !important;
			gap: var(--gnf-space-4) !important;
		}

		@media (max-width: 600px) {
			.gnf-register-form .gnf-register-grid {
				grid-template-columns: 1fr !important;
			}
		}

		/* Campos del formulario - IMPORTANTE: mayor especificidad */
		.gnf-register-form .gnf-register-field {
			display: flex !important;
			flex-direction: column !important;
			gap: var(--gnf-space-1) !important;
			margin-bottom: 0 !important;
		}

		.gnf-register-form .gnf-register-field--full {
			grid-column: 1 / -1 !important;
		}

		.gnf-register-form .gnf-register-field__label {
			font-weight: 500 !important;
			font-size: 0.9rem !important;
			color: var(--gnf-gray-700) !important;
		}

		.gnf-register-field__label .required {
			color: var(--gnf-coral);
		}

		.gnf-register-form .gnf-register-field__input,
		.gnf-register-form .gnf-register-field__select,
		.gnf-register-form .gnf-register-field__textarea {
			width: 100% !important;
			padding: var(--gnf-space-3) !important;
			border: 1px solid var(--gnf-gray-300) !important;
			border-radius: var(--gnf-radius-sm) !important;
			font-size: 0.95rem !important;
			transition: border-color 0.2s, box-shadow 0.2s !important;
			background: var(--gnf-white) !important;
		}

		.gnf-register-form .gnf-register-field__input:focus,
		.gnf-register-form .gnf-register-field__select:focus,
		.gnf-register-form .gnf-register-field__textarea:focus {
			outline: none !important;
			border-color: var(--gnf-ocean) !important;
			box-shadow: 0 0 0 3px rgba(30, 95, 138, 0.1) !important;
		}

		.gnf-register-field__hint {
			font-size: 0.8rem;
			color: var(--gnf-gray-500);
		}

		/* Selector de Centro (cards) */
		.gnf-register-form .gnf-centro-selector {
			display: grid !important;
			grid-template-columns: repeat(2, 1fr) !important;
			gap: var(--gnf-space-3) !important;
			margin-bottom: var(--gnf-space-4) !important;
		}

		@media (max-width: 500px) {
			.gnf-register-form .gnf-centro-selector {
				grid-template-columns: 1fr !important;
			}
		}

		.gnf-centro-option {
			cursor: pointer;
		}

		.gnf-centro-option input[type="radio"] {
			position: absolute;
			opacity: 0;
			pointer-events: none;
		}

		.gnf-centro-option__content {
			display: flex;
			align-items: flex-start;
			gap: var(--gnf-space-3);
			padding: var(--gnf-space-4);
			border: 2px solid var(--gnf-gray-200);
			border-radius: var(--gnf-radius);
			background: var(--gnf-white);
			transition: all 0.2s ease;
		}

		.gnf-centro-option:hover .gnf-centro-option__content {
			border-color: var(--gnf-gray-300);
			background: var(--gnf-gray-50);
		}

		.gnf-centro-option--active .gnf-centro-option__content,
		.gnf-centro-option input:checked+.gnf-centro-option__content {
			border-color: var(--gnf-ocean);
			background: rgba(30, 95, 138, 0.05);
			box-shadow: 0 0 0 3px rgba(30, 95, 138, 0.1);
		}

		.gnf-centro-option__icon {
			font-size: 1.5rem;
			flex-shrink: 0;
			width: 40px;
			height: 40px;
			display: flex;
			align-items: center;
			justify-content: center;
			background: var(--gnf-sky);
			border-radius: var(--gnf-radius-sm);
		}

		.gnf-centro-option__text {
			display: flex;
			flex-direction: column;
			gap: 2px;
		}

		.gnf-centro-option__text strong {
			font-size: 0.95rem;
			color: var(--gnf-gray-800);
		}

		.gnf-centro-option__text span {
			font-size: 0.8rem;
			color: var(--gnf-gray-500);
		}

		/* Paneles de Centro */
		.gnf-centro-panel {
			animation: fadeIn 0.3s ease;
		}

		@keyframes fadeIn {
			from {
				opacity: 0;
				transform: translateY(-10px);
			}

			to {
				opacity: 1;
				transform: translateY(0);
			}
		}

		/* Buscador de Centro */
		.gnf-centro-search-wrapper {
			position: relative;
		}

		/* Higher specificity needed to override .gnf-register-form .gnf-register-field__input { padding: X !important } */
		.gnf-register-form .gnf-centro-search-input,
		.gnf-centro-search-input {
			padding-left: 44px !important;
		}
		.gnf-mat-centro-search-input {
			padding-left: 44px;
		}

		.gnf-centro-search-icon {
			position: absolute;
			left: 14px;
			top: 50%;
			transform: translateY(-50%);
			color: var(--gnf-gray-400);
			pointer-events: none;
		}

		.gnf-centro-results {
			position: absolute;
			top: 100%;
			left: 0;
			right: 0;
			background: var(--gnf-white);
			border: 1px solid var(--gnf-gray-200);
			border-radius: var(--gnf-radius-sm);
			box-shadow: var(--gnf-shadow-lg);
			max-height: 280px;
			overflow-y: auto;
			z-index: 100;
			display: none;
		}

		.gnf-centro-results.is-visible {
			display: block;
		}

		.gnf-centro-result-item {
			padding: var(--gnf-space-3) var(--gnf-space-4);
			cursor: pointer;
			border-bottom: 1px solid var(--gnf-gray-100);
			transition: background 0.15s;
		}

		.gnf-centro-result-item:last-child {
			border-bottom: none;
		}

		.gnf-centro-result-item:hover {
			background: var(--gnf-sky);
		}

		.gnf-centro-result-item__name {
			font-weight: 500;
			color: var(--gnf-gray-800);
			margin-bottom: 2px;
		}

		.gnf-centro-result-item__meta {
			font-size: 0.8rem;
			color: var(--gnf-gray-500);
		}

		.gnf-centro-result-item__codigo {
			background: var(--gnf-gray-100);
			padding: 2px 6px;
			border-radius: 4px;
			font-family: monospace;
			font-size: 0.75rem;
			margin-right: 8px;
		}

		.gnf-centro-results__empty,
		.gnf-centro-results__loading {
			padding: var(--gnf-space-4);
			text-align: center;
			color: var(--gnf-gray-500);
		}

		/* Preview del Centro Seleccionado */
		.gnf-centro-preview {
			background: var(--gnf-sky);
			border: 1px solid var(--gnf-ocean-light);
			border-radius: var(--gnf-radius);
			overflow: hidden;
			margin-top: var(--gnf-space-3);
		}

		.gnf-centro-preview__header {
			display: flex;
			align-items: center;
			gap: var(--gnf-space-2);
			padding: var(--gnf-space-2) var(--gnf-space-3);
			background: rgba(30, 95, 138, 0.1);
			font-size: 0.8rem;
			color: var(--gnf-ocean);
			font-weight: 500;
		}

		.gnf-centro-preview__clear {
			margin-left: auto;
			background: none;
			border: none;
			color: var(--gnf-gray-500);
			cursor: pointer;
			font-size: 1rem;
			padding: 4px 8px;
			border-radius: 4px;
			transition: all 0.15s;
		}

		.gnf-centro-preview__clear:hover {
			background: rgba(0, 0, 0, 0.1);
			color: var(--gnf-gray-700);
		}

		.gnf-centro-preview__body {
			padding: var(--gnf-space-3);
		}

		.gnf-centro-preview__name {
			font-weight: 600;
			color: var(--gnf-gray-800);
			margin-bottom: 4px;
		}

		.gnf-centro-preview__meta {
			display: flex;
			gap: var(--gnf-space-3);
			font-size: 0.85rem;
			color: var(--gnf-gray-600);
		}

		/* Notas y disclaimers */
		.gnf-register-note {
			display: flex;
			align-items: flex-start;
			gap: var(--gnf-space-2);
			margin-top: var(--gnf-space-4);
			padding: var(--gnf-space-3);
			background: var(--gnf-sun-light);
			border-radius: var(--gnf-radius-sm);
			font-size: 0.85rem;
			color: #92400e;
		}

		.gnf-register-note svg {
			flex-shrink: 0;
			margin-top: 2px;
		}

		/* Acciones */
		.gnf-register-actions {
			margin-top: var(--gnf-space-6);
			text-align: center;
		}

		.gnf-btn--full {
			width: 100%;
		}

		.gnf-btn--lg {
			padding: var(--gnf-space-4) var(--gnf-space-6);
			font-size: 1rem;
		}

		.gnf-register-disclaimer {
			margin-top: var(--gnf-space-3);
			font-size: 0.85rem;
			color: var(--gnf-gray-500);
		}
	</style>

	<script>
		document.addEventListener('DOMContentLoaded', function() {
			// Buscador de centros
			const searchInput = document.getElementById('gnf-centro-search');
			const resultsContainer = document.getElementById('gnf-centro-results');
			const centroIdHidden = document.getElementById('gnf-centro-id-hidden');
			const centroPreview = document.getElementById('gnf-centro-preview');
			const centroClear = document.getElementById('gnf-centro-clear');

			if (searchInput && resultsContainer) {
				let debounceTimer;

				searchInput.addEventListener('input', function() {
					const term = this.value.trim();
					clearTimeout(debounceTimer);

					if (term.length < 2) {
						resultsContainer.classList.remove('is-visible');
						return;
					}

					resultsContainer.innerHTML = '<div class="gnf-centro-results__loading">Buscando...</div>';
					resultsContainer.classList.add('is-visible');

					debounceTimer = setTimeout(function() {
						const ajaxUrl = (typeof gnfData !== 'undefined' && gnfData.ajaxUrl) ? gnfData.ajaxUrl : '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';

						fetch(ajaxUrl + '?action=gnf_search_centros&term=' + encodeURIComponent(term))
							.then(res => res.json())
							.then(data => {
								if (!data || !data.length) {
									resultsContainer.innerHTML = '<div class="gnf-centro-results__empty">No se encontraron centros con ese nombre o cÃ³digo</div>';
									return;
								}

								resultsContainer.innerHTML = data.map(centro => {
									const codigo = centro.codigo_mep ? '<span class="gnf-centro-result-item__codigo">' + centro.codigo_mep + '</span>' : '';
									const region = centro.region_name || '';
									return '<div class="gnf-centro-result-item" data-id="' + centro.id + '" data-nombre="' + encodeURIComponent(centro.value) + '" data-codigo="' + (centro.codigo_mep || '') + '" data-region="' + region + '">' +
										'<div class="gnf-centro-result-item__name">' + centro.value + '</div>' +
										'<div class="gnf-centro-result-item__meta">' + codigo + region + '</div>' +
										'</div>';
								}).join('');

								// Click en resultado
								resultsContainer.querySelectorAll('.gnf-centro-result-item').forEach(item => {
									item.addEventListener('click', function() {
										selectCentro(
											this.dataset.id,
											decodeURIComponent(this.dataset.nombre),
											this.dataset.codigo,
											this.dataset.region
										);
									});
								});
							})
							.catch(err => {
								console.error('Error buscando centros:', err);
								resultsContainer.innerHTML = '<div class="gnf-centro-results__empty">Error al buscar. Intente de nuevo.</div>';
							});
					}, 300);
				});

				// Cerrar resultados al hacer clic fuera
				document.addEventListener('click', function(e) {
					if (!searchInput.contains(e.target) && !resultsContainer.contains(e.target)) {
						resultsContainer.classList.remove('is-visible');
					}
				});

				// Seleccionar centro
				function selectCentro(id, nombre, codigo, region) {
					centroIdHidden.value = id;
					searchInput.value = '';
					resultsContainer.classList.remove('is-visible');

					// Mostrar preview
					document.getElementById('gnf-centro-preview-name').textContent = nombre;
					document.getElementById('gnf-centro-preview-codigo').textContent = codigo ? 'CÃ³digo: ' + codigo : '';
					document.getElementById('gnf-centro-preview-region').textContent = region || '';
					centroPreview.style.display = 'block';
					searchInput.closest('.gnf-register-field').style.display = 'none';
				}

				// Limpiar selecciÃ³n
				if (centroClear) {
					centroClear.addEventListener('click', function() {
						centroIdHidden.value = '';
						centroPreview.style.display = 'none';
						searchInput.closest('.gnf-register-field').style.display = 'block';
						searchInput.focus();
					});
				}
			}
		});
	</script>
<?php
	return ob_get_clean();
}

/**
 * Busca centros similares por nombre/codigo/region.
 */
function gnf_find_similar_centro($nombre = '', $codigo = '', $region_id = 0)
{
	$args = array(
		'post_type'      => 'centro_educativo',
		'post_status'    => array('publish', 'pending', 'draft'),
		'posts_per_page' => 5,
		's'              => $nombre,
		'fields'         => 'ids',
	);
	if ($codigo) {
		$args['meta_query'][] = array(
			'key'   => 'codigo_mep',
			'value' => $codigo,
		);
	}
	$query = new WP_Query($args);
	$ids   = $query->posts;
	if ($region_id) {
		$ids = array_filter(
			$ids,
			function ($id) use ($region_id) {
				return (int) get_post_meta($id, 'region', true) === (int) $region_id;
			}
		);
	}
	return $ids;
}

/**
 * Mapeo de tipos de evidencia a extensiones permitidas.
 */
function gnf_allowed_extensions_for_tipo($tipo)
{
	$map = array(
		'foto'  => array('jpg', 'jpeg', 'png', 'gif'),
		'pdf'   => array('pdf'),
		'video' => array('mp4', 'mov', 'avi'),
	);
	return $map[$tipo] ?? array();
}

/**
 * Obtiene tipos permitidos de un reto (multi).
 */
function gnf_get_reto_allowed_tipos($reto_id)
{
	$tipos = get_field('tipos_evidencia_permitidos', $reto_id);
	return array_filter((array) $tipos);
}

/**
 * Busca supervisores de una regiÃ³n.
 */
function gnf_get_supervisores_by_region($region_id)
{
	$args = array(
		'role'   => 'supervisor',
		'number' => 200,
		'meta_query' => array(
			array(
				'key'   => 'region',
				'value' => $region_id,
			),
		),
	);
	$users = get_users($args);
	return $users;
}

/**
 * Computes the display status of a reto entry based on its individual evidence states.
 *
 * @param object|array $entry Entry row or array with 'evidencias' JSON field.
 * @return array { 'status' => string, 'badge' => string, 'label' => string,
 *                 'aprobadas' => int, 'rechazadas' => int, 'pendientes' => int, 'total' => int }
 */
function gnf_get_reto_entry_computed_status( $entry ) {
	$evidencias_raw = is_object( $entry ) ? ( $entry->evidencias ?? '[]' ) : ( $entry['evidencias'] ?? '[]' );
	$evidencias     = json_decode( $evidencias_raw, true );

	if ( ! is_array( $evidencias ) ) {
		$evidencias = array();
	}

	$aprobadas  = 0;
	$rechazadas = 0;
	$pendientes = 0;
	$total      = 0;

	foreach ( $evidencias as $ev ) {
		if ( ! empty( $ev['replaced'] ) ) {
			continue;
		}
		if ( null === ( $ev['puntos'] ?? null ) ) {
			continue;
		}
		$total++;
		$estado = $ev['estado'] ?? 'pendiente';
		if ( 'aprobada' === $estado ) {
			$aprobadas++;
		} elseif ( 'rechazada' === $estado ) {
			$rechazadas++;
		} else {
			$pendientes++;
		}
	}

	if ( 0 === $total ) {
		return array(
			'status'     => 'sin_evidencias',
			'badge'      => 'default',
			'label'      => 'Sin evidencias',
			'aprobadas'  => 0,
			'rechazadas' => 0,
			'pendientes' => 0,
			'total'      => 0,
		);
	}

	if ( $aprobadas === $total ) {
		$status = 'completo';
		$badge  = 'forest';
		$label  = 'Completo';
	} elseif ( $rechazadas > 0 ) {
		$status = 'requiere_atencion';
		$badge  = 'coral';
		$label  = 'Requiere atención';
	} else {
		$status = 'en_progreso';
		$badge  = 'sun';
		$label  = 'En progreso';
	}

	return array(
		'status'     => $status,
		'badge'      => $badge,
		'label'      => $label,
		'aprobadas'  => $aprobadas,
		'rechazadas' => $rechazadas,
		'pendientes' => $pendientes,
		'total'      => $total,
	);
}

/**
 * Obtiene notificaciones para un supervisor (enviados a revisiÃ³n, aprobados, etc.).
 *
 * @param int $user_id ID del supervisor.
 * @param int $limit   LÃ­mite de notificaciones.
 * @return array Lista de notificaciones con datos enriquecidos.
 */
function gnf_get_supervisor_notificaciones( $user_id, $limit = 50 ) {
	global $wpdb;
	$table = $wpdb->prefix . 'gn_notificaciones';
	$items = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
			$user_id,
			$limit
		)
	);

	$result = array();
	foreach ( (array) $items as $item ) {
		$link          = '';
		$evidence_data = null;

		if ( 'reto_entry' === $item->relacion_tipo && $item->relacion_id ) {
			$entry = $wpdb->get_row( $wpdb->prepare(
				"SELECT centro_id, reto_id, anio, evidencias FROM {$wpdb->prefix}gn_reto_entries WHERE id = %d",
				$item->relacion_id
			) );
			if ( $entry ) {
				$link = add_query_arg( array(
					'centro_id' => $entry->centro_id,
					'gnf_year'  => $entry->anio,
				), home_url( '/panel-supervisor/' ) );

				if ( in_array( $item->tipo, array( 'evidencia_subida', 'evidencia_resubida' ), true ) ) {
					$evs = json_decode( $entry->evidencias ?? '[]', true );
					$evidence_data = array(
						'entry_id'    => (int) $item->relacion_id,
						'centro_name' => get_the_title( $entry->centro_id ),
						'reto_name'   => get_the_title( $entry->reto_id ),
						'evidencias'  => array(),
					);
					foreach ( (array) $evs as $idx => $ev ) {
						if ( ! empty( $ev['replaced'] ) ) {
							continue;
						}
						$ev_nombre = $ev['nombre'] ?? '';
						if ( $ev_nombre && false !== strpos( $item->mensaje, $ev_nombre ) ) {
							$evidence_data['evidencias'][] = array(
								'index'              => $idx,
								'nombre'             => $ev_nombre,
								'tipo'               => $ev['tipo'] ?? 'archivo',
								'ruta'               => $ev['ruta'] ?? '',
								'puntos'             => $ev['puntos'] ?? null,
								'estado'             => $ev['estado'] ?? null,
								'supervisor_comment' => $ev['supervisor_comment'] ?? null,
								'reviewed_by'        => $ev['reviewed_by'] ?? null,
								'reviewed_at'        => $ev['reviewed_at'] ?? null,
							);
						}
					}
				}
			}
		} elseif ( 'centro' === $item->relacion_tipo && $item->relacion_id ) {
			$link = add_query_arg( array(
				'centro_id' => $item->relacion_id,
				'gnf_year'  => gnf_get_active_year(),
			), home_url( '/panel-supervisor/' ) );
		}

		$result[] = array(
			'id'             => $item->id,
			'tipo'           => $item->tipo,
			'mensaje'        => $item->mensaje,
			'relacion_tipo'  => $item->relacion_tipo,
			'relacion_id'    => $item->relacion_id,
			'leido'          => (bool) $item->leido,
			'created_at'     => $item->created_at,
			'link'           => $link,
			'evidence_data'  => $evidence_data,
		);
	}
	return $result;
}

/**
 * Construye el contexto enriquecido de una notificación para React.
 *
 * @param object $item    Fila de notificación.
 * @param int    $user_id Usuario actual.
 * @return array<string,mixed>
 */
function gnf_build_notification_context( $item, $user_id ) {
	global $wpdb;

	$context = array(
		'actionTarget' => null,
		'actionLabel'  => '',
		'canReview'    => false,
		'entryId'      => 0,
		'retoId'       => 0,
		'retoTitulo'   => '',
		'centroId'     => 0,
		'centroNombre' => '',
		'regionName'   => '',
		'circuito'     => '',
		'year'         => 0,
		'entryStatus'  => '',
		'hasRejectedEvidence' => false,
	);

	if ( empty( $item->relacion_tipo ) || empty( $item->relacion_id ) ) {
		return $context;
	}

	$current_user = wp_get_current_user();
	$is_docente   = gnf_user_has_role( $current_user, 'docente' );

	if ( 'reto_entry' === $item->relacion_tipo ) {
		$table = $wpdb->prefix . 'gn_reto_entries';
		$entry = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, centro_id, reto_id, anio, estado, evidencias
				 FROM {$table}
				 WHERE id = %d",
				$item->relacion_id
			)
		);

		if ( ! $entry ) {
			return $context;
		}

		$centro     = get_post( (int) $entry->centro_id );
		$reto       = get_post( (int) $entry->reto_id );
		$region     = function_exists( 'gnf_rest_get_centro_region_term' ) ? gnf_rest_get_centro_region_term( (int) $entry->centro_id ) : null;
		$evidencias = ! empty( $entry->evidencias ) ? json_decode( $entry->evidencias, true ) : array();

		foreach ( (array) $evidencias as $evidencia ) {
			if ( ! empty( $evidencia['replaced'] ) ) {
				continue;
			}
			if ( 'rechazada' === ( $evidencia['estado'] ?? '' ) ) {
				$context['hasRejectedEvidence'] = true;
				break;
			}
		}

		$context['entryId']      = (int) $entry->id;
		$context['retoId']       = (int) $entry->reto_id;
		$context['retoTitulo']   = $reto ? (string) $reto->post_title : '';
		$context['centroId']     = (int) $entry->centro_id;
		$context['centroNombre'] = $centro ? (string) $centro->post_title : '';
		$context['regionName']   = ( $region && ! is_wp_error( $region ) ) ? (string) $region->name : '';
		$context['circuito']     = (string) get_post_meta( (int) $entry->centro_id, 'circuito', true );
		$context['year']         = (int) $entry->anio;
		$context['entryStatus']  = (string) $entry->estado;
		$context['canReview']    = ! $is_docente && 'enviado' === $entry->estado && gnf_user_can_access_centro( $user_id, (int) $entry->centro_id );

		if ( $is_docente ) {
			$context['actionTarget'] = array(
				'page'   => 'formularios',
				'params' => array(
					'reto_id' => (string) $entry->reto_id,
				),
			);
			$context['actionLabel'] = 'Abrir reto';
		} else {
			$context['actionTarget'] = array(
				'page'   => 'centro',
				'params' => array(
					'centro_id' => (string) $entry->centro_id,
				),
			);
			$context['actionLabel'] = $context['canReview'] ? 'Revisar reto' : 'Abrir centro';
		}

		return $context;
	}

	if ( in_array( $item->relacion_tipo, array( 'centro', 'evidencia' ), true ) ) {
		$centro_id = (int) $item->relacion_id;
		$centro    = get_post( $centro_id );
		$region    = function_exists( 'gnf_rest_get_centro_region_term' ) ? gnf_rest_get_centro_region_term( $centro_id ) : null;

		$context['centroId']     = $centro_id;
		$context['centroNombre'] = $centro ? (string) $centro->post_title : '';
		$context['regionName']   = ( $region && ! is_wp_error( $region ) ) ? (string) $region->name : '';
		$context['circuito']     = (string) get_post_meta( $centro_id, 'circuito', true );
		$context['year']         = function_exists( 'gnf_get_active_year' ) ? (int) gnf_get_active_year() : (int) gmdate( 'Y' );

		if ( $is_docente ) {
			$context['actionTarget'] = array(
				'page' => 'resumen',
			);
			$context['actionLabel'] = 'Ver resumen';
		} else {
			$context['actionTarget'] = array(
				'page'   => 'centro',
				'params' => array(
					'centro_id' => (string) $centro_id,
				),
			);
			$context['actionLabel'] = 'Abrir centro';
		}
	}

	return $context;
}

/**
 * Obtiene IDs de centros que tienen matrÃ­cula activa para un aÃ±o.
 * Se usa para filtrar paneles de revisiÃ³n (supervisor, comitÃ©, admin).
 *
 * @param int $anio AÃ±o.
 * @return int[] Centro IDs.
 */
function gnf_get_centros_with_matricula( $anio ) {
	global $wpdb;
	$table = $wpdb->prefix . 'gn_matriculas';
	$anio  = gnf_normalize_year( $anio );
	$ids   = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT DISTINCT centro_id FROM {$table} WHERE anio = %d",
			$anio
		)
	);
	return array_map( 'absint', (array) $ids );
}

/**
 * Reaplica retos requisito en centros con matrícula activa para un año.
 *
 * @param int $anio Año.
 * @return array{centros:int,actualizados:int}
 */
function gnf_backfill_required_retos_for_active_centros( $anio ) {
	$anio         = gnf_normalize_year( $anio );
	$centro_ids   = gnf_get_centros_with_matricula( $anio );
	$actualizados = 0;

	foreach ( $centro_ids as $centro_id ) {
		$antes = gnf_get_centro_retos_seleccionados( $centro_id, $anio );
		gnf_set_centro_retos_seleccionados( $centro_id, $anio, $antes );
		$despues = gnf_get_centro_retos_seleccionados( $centro_id, $anio );

		if ( $antes !== $despues ) {
			$actualizados++;
		}
	}

	return array(
		'centros'      => count( $centro_ids ),
		'actualizados' => $actualizados,
	);
}

/**
 * Recupera lista de retos aprobados para cache.
 */
function gnf_get_aprobados_por_centro_cached($centro_id, $anio)
{
	$key = 'gnf_aprobados_' . $centro_id . '_' . $anio;
	$cached = get_transient($key);
	if (false !== $cached) {
		return $cached;
	}
	global $wpdb;
	$table = $wpdb->prefix . 'gn_reto_entries';
	$data  = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT reto_id, puntaje FROM {$table} WHERE centro_id = %d AND anio = %d AND estado = %s",
			$centro_id,
			$anio,
			'aprobado'
		)
	);
	set_transient($key, $data, DAY_IN_SECONDS);
	return $data;
}

/**
 * Estados vÃ¡lidos de un reto entry.
 */
function gnf_get_valid_estados()
{
	return array(
		'no_iniciado',
		'en_progreso',
		'completo',
		'enviado',
		'aprobado',
		'correccion',
	);
}

/**
 * Etiqueta legible para un estado.
 */
function gnf_get_estado_label($estado)
{
	$labels = array(
		'no_iniciado' => 'No iniciado',
		'en_progreso' => 'En progreso',
		'completo'    => 'Completo',
		'enviado'     => 'Enviado',
		'aprobado'    => 'Aprobado',
		'correccion'  => 'CorrecciÃ³n',
	);
	return $labels[$estado] ?? ucwords(str_replace('_', ' ', $estado));
}


/**
 * Actualiza el estado de un reto entry.
 */
function gnf_update_entry_estado($entry_id, $nuevo_estado)
{
	if (!in_array($nuevo_estado, gnf_get_valid_estados(), true)) {
		return false;
	}

	global $wpdb;
	$table = $wpdb->prefix . 'gn_reto_entries';

	return $wpdb->update(
		$table,
		array(
			'estado'     => $nuevo_estado,
			'updated_at' => current_time('mysql'),
		),
		array('id' => $entry_id),
		array('%s', '%s'),
		array('%d')
	);
}

/**
 * Obtiene todas las entries de un centro para un aÃ±o.
 */
function gnf_get_centro_entries($centro_id, $anio)
{
	global $wpdb;
	$table = $wpdb->prefix . 'gn_reto_entries';

	return $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE centro_id = %d AND anio = %d ORDER BY reto_id ASC",
			$centro_id,
			$anio
		)
	);
}

/**
 * Verifica si todos los retos matriculados estÃ¡n completos o aprobados.
 */
function gnf_are_all_retos_complete($centro_id, $anio)
{
	$retos_seleccionados = gnf_get_centro_retos_seleccionados($centro_id, $anio);
	if (empty($retos_seleccionados) || !is_array($retos_seleccionados)) {
		return false;
	}

	global $wpdb;
	$table = $wpdb->prefix . 'gn_reto_entries';

	foreach ($retos_seleccionados as $reto_id) {
		$entry = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE centro_id = %d AND reto_id = %d AND anio = %d",
				$centro_id,
				$reto_id,
				$anio
			)
		);

		if (!$entry) {
			return false;
		}

		if (!in_array($entry->estado, array('completo', 'enviado', 'aprobado'), true)) {
			return false;
		}
	}

	return true;
}

/**
 * Calcula puntos potenciales (si se completan todos los retos pendientes).
 */
function gnf_get_puntos_potenciales($centro_id, $anio)
{
	$retos_seleccionados = gnf_get_centro_retos_seleccionados($centro_id, $anio);
	if (empty($retos_seleccionados) || !is_array($retos_seleccionados)) {
		return 0;
	}

	$potencial = 0;
	foreach ($retos_seleccionados as $reto_id) {
		$potencial += gnf_get_reto_max_points($reto_id, $anio);
	}

	return $potencial;
}
