<?php

/**
 * Helpers genéricos.
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Obtiene valor desde ACF Options o opción normal.
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
 * Año activo configurado.
 */
function gnf_get_active_year()
{
	$year = gnf_get_option('anio_actual', gmdate('Y'));
	return absint($year);
}

/**
 * Normaliza un año válido de la plataforma.
 *
 * @param int|string|null $anio Año solicitado.
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
 * @param string $canton Cantón.
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
 * Obtiene año de contexto desde request (POST/GET) o fallback.
 *
 * @param int|null $default Año por defecto opcional.
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
 * @param int $anio Año.
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
 * @param int   $anio Año.
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
 * Obtiene fila anual específica del centro.
 *
 * @param int  $centro_id Centro educativo.
 * @param int  $anio Año.
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
 * @param int   $anio Año.
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
 * @param int|null    $anio Año.
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
 * @param int|null $anio Año.
 * @return mixed
 */
function gnf_set_centro_anual_field($centro_id, $field, $value, $anio = null)
{
	$anio = gnf_normalize_year($anio);
	$row  = gnf_set_centro_anual_data($centro_id, $anio, array($field => $value));
	return $row[$field] ?? null;
}

/**
 * Atajos de lectura por año para el centro.
 */
function gnf_get_centro_retos_seleccionados($centro_id, $anio = null)
{
	$retos = gnf_get_centro_anual_field($centro_id, 'retos_seleccionados', $anio, array());
	$retos = gnf_normalize_reto_ids($retos);
	$retos = gnf_filter_reto_ids_by_year_form($retos, $anio);
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
 * Atajos de escritura por año para el centro.
 */
function gnf_set_centro_retos_seleccionados($centro_id, $anio, $reto_ids)
{
	$reto_ids = gnf_normalize_reto_ids($reto_ids);
	$reto_ids = gnf_filter_reto_ids_by_year_form($reto_ids, $anio);
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
 * Obtiene la región del usuario (user_meta o ACF).
 */
function gnf_get_user_region($user_id)
{
	$region = get_user_meta($user_id, 'region', true);
	if (empty($region) && function_exists('get_field')) {
		$region = get_field('region', 'user_' . $user_id);
	}
	return $region;
}

/**
 * Comprueba si el usuario tiene acceso al centro (docente, supervisor región o comité).
 */
function gnf_user_can_access_centro($user_id, $centro_id)
{
	if (user_can($user_id, 'manage_options')) {
		return true;
	}

	$user = get_userdata($user_id);

	// Comité BAE puede acceder a todos los centros.
	if (gnf_user_has_role($user, 'comite_bae') || user_can($user_id, 'gnf_view_all_regions')) {
		return true;
	}

	$centro_region = get_post_meta($centro_id, 'region', true);
	if (empty($centro_region)) {
		$terms = wp_get_post_terms($centro_id, 'gn_region', array('fields' => 'ids'));
		$centro_region = $terms ? $terms[0] : '';
	}

	if (gnf_user_has_role($user, 'docente')) {
		$docentes = (array) get_field('docentes_asociados', $centro_id);
		return in_array($user_id, $docentes, true);
	}

	if (gnf_user_has_role($user, 'supervisor')) {
		return (string) gnf_get_user_region($user_id) === (string) $centro_region;
	}

	return false;
}

/**
 * Inserta notificación.
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
 * Helper para obtener data de wp_gn_reto_entries por usuario/año.
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
 * URL del Drive para registros (Registrar y Reducir). Configurable en Guardianes → Configuración.
 *
 * @return string URL o vacío si no está configurado.
 */
function gnf_get_registros_drive_url()
{
	$url = gnf_get_option('registros_drive_url', '');
	return is_string($url) ? trim($url) : '';
}

/**
 * Devuelve los IDs de los retos marcados como obligatorios en matrícula (Agua, Energía, Residuos).
 * Se usan para pre-seleccionarlos en el formulario y asegurarlos al guardar.
 *
 * @return int[]
 */
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
 * retos hijos por separado (ej. Compostaje → Valorizables, Limpieza).
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
 * Colapsa una lista de IDs de retos a sus raíces (para pre-llenar el form de matrícula).
 * Si un ID es de un reto hijo, se devuelve el ID del padre raíz.
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
 * Lee desde configuracion_por_anio; solo devuelve si el año está activo.
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

	$required = array_flip(gnf_collapse_retos_a_raiz(gnf_get_obligatorio_reto_ids()));
	usort(
		$reto_ids,
		static function ($a, $b) use ($required) {
			$a_required = isset($required[(int) $a]) ? 0 : 1;
			$b_required = isset($required[(int) $b]) ? 0 : 1;
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
 * Centraliza la construcción de datos de entry con metadatos del reto.
 *
 * @param object   $entry Fila de wp_gn_reto_entries.
 * @param int|null $anio  Año (para iconos/puntos per-year).
 * @return array Datos formateados en camelCase.
 */
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
		'createdAt'       => $entry->created_at,
		'updatedAt'       => $entry->updated_at,
	);
}

/**
 * Obtiene la URL del icono de un reto.
 * Prioridad: icono per-year en configuracion_por_anio > Featured Image.
 *
 * @param int      $reto_id ID del reto.
 * @param string   $size    Tamaño de la imagen (default 'thumbnail').
 * @param int|null $anio    Anio para buscar icono per-year.
 * @return string URL del icono o cadena vacía.
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
 * Obtiene la fila de configuracion_por_anio que corresponde a un año activo.
 * Función interna compartida por los demás helpers.
 *
 * @param int      $reto_id ID del reto.
 * @param int|null $anio    Anio (null = año activo).
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
			if (!empty($row['anio']) && absint($row['anio']) === $anio && !empty($row['activo'])) {
				return $row;
			}
		}
	}
	return null;
}

/**
 * Obtiene la URL del PDF de un reto para un año específico.
 *
 * @param int      $reto_id ID del reto.
 * @param int|null $anio    Anio.
 * @return string URL del PDF o cadena vacía.
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
	$args  = array(
		'post_type'      => 'centro_educativo',
		'posts_per_page' => 1,
		'meta_query'     => array(
			array(
				'key'     => 'docentes_asociados',
				'value'   => ':"' . intval($user_id) . '";',
				'compare' => 'LIKE',
			),
		),
		'fields' => 'ids',
	);
	$query = new WP_Query($args);
	if ($query->have_posts()) {
		return (int) $query->posts[0];
	}
	return 0;
}

/**
 * Protege endpoints AJAX básica con nonce.
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
 * Marca docente como aprobado.
 */
function gnf_approve_docente($user_id)
{
	$user = new WP_User($user_id);
	$user->set_role('docente');
	update_user_meta($user_id, 'gnf_docente_status', 'activo');
	gnf_insert_notification($user_id, 'docente_aprobado', 'Tu cuenta fue aprobada.', 'docente', $user_id);
}

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
 * Solicitar corrección (ticket) y notificar.
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
				<img src="<?php echo esc_url(GNF_URL . 'assets/logo-guardiana.png'); ?>" alt="Guardianes" />
			</div>
			<h2><?php echo esc_html($args['title']); ?></h2>
			<p class="gnf-muted"><?php echo esc_html($args['description']); ?></p>

			<div class="gnf-auth__tabs">
				<button type="button" class="gnf-btn gnf-auth__tab is-active" data-tab="login"><?php esc_html_e('Iniciar sesión', 'guardianes-formularios'); ?></button>
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
						<label><?php esc_html_e('Contraseña', 'guardianes-formularios'); ?>
							<input type="password" name="user_pass" required />
						</label>
						<button class="gnf-btn" type="submit"><?php esc_html_e('Ingresar', 'guardianes-formularios'); ?></button>
						<p class="gnf-auth__forgot">
							<a href="<?php echo esc_url(wp_lostpassword_url($args['redirect'])); ?>"><?php esc_html_e('¿Olvidaste tu contraseña?', 'guardianes-formularios'); ?></a>
						</p>
					</form>
				</div>

				<?php if ($args['show_register']) : ?>
					<div class="gnf-auth__panel" data-panel="register" style="display:none;">
						<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="gnf-register-form">
							<?php wp_nonce_field('gnf_docente_register', 'gnf_nonce'); ?>
							<input type="hidden" name="action" value="gnf_docente_register" />
							<input type="hidden" name="redirect" value="<?php echo esc_url(add_query_arg('tab', 'matricula', $args['redirect'])); ?>" />

							<!-- Sección: Datos Personales (2 columnas) -->
							<div class="gnf-register-section">
								<h3 class="gnf-register-section__title">
									<span class="gnf-register-section__icon">👤</span>
									<?php esc_html_e('Datos personales', 'guardianes-formularios'); ?>
								</h3>
								<div class="gnf-register-grid">
									<label class="gnf-register-field">
										<span class="gnf-register-field__label"><?php esc_html_e('Nombre completo', 'guardianes-formularios'); ?> <span class="required">*</span></span>
										<input type="text" name="display_name" required class="gnf-register-field__input" placeholder="Ej: María García López" />
									</label>
									<label class="gnf-register-field">
										<span class="gnf-register-field__label"><?php esc_html_e('Correo electrónico', 'guardianes-formularios'); ?> <span class="required">*</span></span>
										<input type="email" name="user_email" required class="gnf-register-field__input" placeholder="docente@ejemplo.com" />
									</label>
									<label class="gnf-register-field">
										<span class="gnf-register-field__label"><?php esc_html_e('Identificación (cédula)', 'guardianes-formularios'); ?> <span class="required">*</span></span>
										<input type="text" name="identificacion" required class="gnf-register-field__input" placeholder="X-XXXX-XXXX" />
									</label>
									<label class="gnf-register-field">
										<span class="gnf-register-field__label"><?php esc_html_e('Teléfono', 'guardianes-formularios'); ?> <span class="required">*</span></span>
										<input type="tel" name="telefono" required class="gnf-register-field__input" placeholder="+506 XXXX-XXXX" />
									</label>
									<label class="gnf-register-field">
										<span class="gnf-register-field__label"><?php esc_html_e('Cargo', 'guardianes-formularios'); ?> <span class="required">*</span></span>
										<input type="text" name="cargo" required class="gnf-register-field__input" placeholder="Ej: Docente de ciencias" />
									</label>
									<label class="gnf-register-field">
										<span class="gnf-register-field__label"><?php esc_html_e('Contraseña', 'guardianes-formularios'); ?> <span class="required">*</span></span>
										<input type="password" name="user_pass" required minlength="8" class="gnf-register-field__input" placeholder="Mínimo 8 caracteres" />
									</label>
								</div>
							</div>

							<!-- Sección: Centro Educativo -->
							<div class="gnf-register-section">
								<h3 class="gnf-register-section__title">
									<span class="gnf-register-section__icon">🏫</span>
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
												placeholder="<?php esc_attr_e('Escriba el nombre o código MEP del centro...', 'guardianes-formularios'); ?>"
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
											<span class="gnf-centro-preview__icon">✅</span>
											<span class="gnf-centro-preview__title"><?php esc_html_e('Centro seleccionado', 'guardianes-formularios'); ?></span>
											<button type="button" id="gnf-centro-clear" class="gnf-centro-preview__clear" title="<?php esc_attr_e('Cambiar centro', 'guardianes-formularios'); ?>">✕</button>
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
										<?php esc_html_e('Al seleccionar un centro, su solicitud quedará pendiente de aprobación por el docente actual o administrador.', 'guardianes-formularios'); ?>
									</p>
								</div>

							</div>

							<!-- Botón Submit -->
							<div class="gnf-register-actions">
								<button class="gnf-btn gnf-btn--lg gnf-btn--full" type="submit">
									<?php esc_html_e('Crear mi cuenta de docente', 'guardianes-formularios'); ?>
								</button>
								<p class="gnf-register-disclaimer">
									<?php esc_html_e('Al registrarte, tu cuenta quedará pendiente de aprobación.', 'guardianes-formularios'); ?>
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
									resultsContainer.innerHTML = '<div class="gnf-centro-results__empty">No se encontraron centros con ese nombre o código</div>';
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
					document.getElementById('gnf-centro-preview-codigo').textContent = codigo ? 'Código: ' + codigo : '';
					document.getElementById('gnf-centro-preview-region').textContent = region || '';
					centroPreview.style.display = 'block';
					searchInput.closest('.gnf-register-field').style.display = 'none';
				}

				// Limpiar selección
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
 * Busca supervisores de una región.
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
 * Obtiene notificaciones para un supervisor (enviados a revisión, aprobados, etc.).
 *
 * @param int $user_id ID del supervisor.
 * @param int $limit   Límite de notificaciones.
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
		$link = '';
		if ( 'reto_entry' === $item->relacion_tipo && $item->relacion_id ) {
			$entry = $wpdb->get_row( $wpdb->prepare(
				"SELECT centro_id, anio FROM {$wpdb->prefix}gn_reto_entries WHERE id = %d",
				$item->relacion_id
			) );
			if ( $entry ) {
				$link = add_query_arg( array(
					'centro_id' => $entry->centro_id,
					'gnf_year'  => $entry->anio,
				), home_url( '/panel-supervisor/' ) );
			}
		} elseif ( 'centro' === $item->relacion_tipo && $item->relacion_id ) {
			$link = add_query_arg( array(
				'centro_id' => $item->relacion_id,
				'gnf_year'  => gnf_get_active_year(),
			), home_url( '/panel-supervisor/' ) );
		}

		$result[] = array(
			'id'            => $item->id,
			'tipo'          => $item->tipo,
			'mensaje'       => $item->mensaje,
			'relacion_tipo' => $item->relacion_tipo,
			'relacion_id'   => $item->relacion_id,
			'leido'         => (bool) $item->leido,
			'created_at'    => $item->created_at,
			'link'          => $link,
		);
	}
	return $result;
}

/**
 * Obtiene IDs de centros que tienen matrícula activa para un año.
 * Se usa para filtrar paneles de revisión (supervisor, comité, admin).
 *
 * @param int $anio Año.
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
 * Estados válidos de un reto entry.
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
		'correccion'  => 'Corrección',
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
 * Obtiene todas las entries de un centro para un año.
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
 * Verifica si todos los retos matriculados están completos o aprobados.
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


