<?php
/**
 * Catalogo y agregacion de indicadores de impacto.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function gnf_impact_metric( $key, $title, $source, $operation, $unit = 'centros', $reto = '', $field = '', $public = false ) {
	return array(
		'key'       => (string) $key,
		'title'     => (string) $title,
		'source'    => (string) $source,
		'operation' => (string) $operation,
		'unit'      => (string) $unit,
		'reto'      => (string) $reto,
		'field'     => (string) $field,
		'public'    => (bool) $public,
	);
}

/**
 * Catalogo derivado de DATOS DE RESULTADOS BAE 2026.
 */
function gnf_get_impact_metric_catalog( $anio = 2026 ) {
	$anio = (int) $anio;
	if ( 2026 !== $anio ) {
		return array();
	}

	$metrics = array(
		gnf_impact_metric( 'inscripciones', 'Total de inscripciones', 'center', 'count', 'centros', '', '', true ),
		gnf_impact_metric( 'centros_pequenos', 'Centros unidocentes y Direccion I participantes', 'center', 'count_small', 'centros' ),
		gnf_impact_metric( 'estudiantes_migrantes', 'Estudiantes en condicion de migrantes', 'center', 'sum_migrants', 'estudiantes', '', '', true ),
		gnf_impact_metric( 'centros_con_evidencias', 'Centros inscritos con evidencias', 'entry', 'count_any', 'centros', '', '', true ),
		gnf_impact_metric( 'agua_centros', 'Centros realizando Reto Agua', 'entry', 'count', 'centros', 'agua', '', true ),
		gnf_impact_metric( 'agua_captacion', 'Centros captando agua de lluvia', 'response', 'count_yes', 'centros', 'agua', 'agua_captacion', true ),
		gnf_impact_metric( 'agua_riego', 'Centros con riego ahorrativo', 'response', 'count_yes', 'centros', 'agua', 'agua_riego' ),
		gnf_impact_metric( 'agua_registro_consumo', 'Centros registrando consumo de agua', 'response', 'count_yes', 'centros', 'agua', 'agua_registro_consumo' ),
		gnf_impact_metric( 'energia_centros', 'Centros realizando Reto Energia', 'entry', 'count', 'centros', 'electricidad', '', true ),
		gnf_impact_metric( 'energia_paneles_solares', 'Centros con paneles solares', 'response', 'count_yes', 'centros', 'electricidad', 'energia_paneles_solares', true ),
		gnf_impact_metric( 'energia_registro_consumo', 'Centros registrando consumo de energia', 'response', 'count_yes', 'centros', 'electricidad', 'energia_registro_consumo' ),
		gnf_impact_metric( 'residuos_centros', 'Centros realizando Reto Residuos', 'entry', 'count', 'centros', 'residuos', '', true ),
		gnf_impact_metric( 'residuos_estaciones', 'Centros con estaciones de separacion', 'response', 'count_yes', 'centros', 'residuos', 'residuos_estaciones', true ),
		gnf_impact_metric( 'residuos_registro_no_valorizables', 'Centros registrando residuos no valorizables', 'response', 'count_yes', 'centros', 'residuos', 'residuos_registro_no_valorizables' ),
		gnf_impact_metric( 'residuos_kg_valorizables', 'Residuos valorizables recolectados', 'response', 'sum', 'kg', 'residuos', 'residuos_kg_valorizables', true ),
		gnf_impact_metric( 'organicos_centros', 'Centros realizando Gestion de Organicos', 'entry', 'count', 'centros', 'gestion-de-organicos' ),
		gnf_impact_metric( 'organicos_compostaje', 'Centros aprovechando residuos organicos', 'response', 'count_yes', 'centros', 'gestion-de-organicos', 'organicos_compostaje', true ),
		gnf_impact_metric( 'limpiezas_centros', 'Centros realizando Reto Limpiezas', 'entry', 'count', 'centros', 'limpiezas' ),
		gnf_impact_metric( 'limpiezas_total', 'Limpiezas realizadas', 'response', 'sum', 'limpiezas', 'limpiezas', 'limpiezas_total', true ),
		gnf_impact_metric( 'limpiezas_bolsas', 'Bolsas recolectadas durante limpiezas', 'response', 'sum', 'bolsas', 'limpiezas', 'limpiezas_bolsas', true ),
		gnf_impact_metric( 'loncheras_centros', 'Centros realizando Eco Loncheras', 'entry', 'count', 'centros', 'eco-lonchera' ),
		gnf_impact_metric( 'jardines_centros', 'Centros realizando Jardines para Polinizadores', 'entry', 'count', 'centros', 'jardines-y-polinizadores' ),
		gnf_impact_metric( 'jardines_areas', 'Jardines para polinizadores', 'response', 'sum', 'jardines', 'jardines-y-polinizadores', 'jardines_areas', true ),
		gnf_impact_metric( 'jardines_inaturalist', 'Centros usando iNaturalist', 'response', 'count_yes', 'centros', 'jardines-y-polinizadores', 'jardines_inaturalist' ),
		gnf_impact_metric( 'jardines_refugios', 'Meliponarios, colmenas u hoteles de insectos', 'response', 'sum', 'estructuras', 'jardines-y-polinizadores', 'jardines_refugios' ),
		gnf_impact_metric( 'huerta_centros', 'Centros realizando Reto Huerta', 'entry', 'count', 'centros', 'huerta' ),
		gnf_impact_metric( 'huerta_actividades', 'Centros con actividades en la huerta', 'response', 'count_yes', 'centros', 'huerta', 'huerta_actividades', true ),
		gnf_impact_metric( 'eco_gira_centros', 'Centros realizando Eco Gira', 'entry', 'count', 'centros', 'eco-gira' ),
		gnf_impact_metric( 'eco_giras_total', 'Eco giras realizadas', 'response', 'sum', 'giras', 'eco-gira', 'eco_giras_total', true ),
		gnf_impact_metric( 'eco_giras_estudiantes', 'Estudiantes visitando areas naturales', 'response', 'sum', 'estudiantes', 'eco-gira', 'eco_giras_estudiantes', true ),
		gnf_impact_metric( 'emprendimiento_centros', 'Centros realizando Eco Emprendimiento', 'entry', 'count', 'centros', 'eco-emprendimiento' ),
		gnf_impact_metric( 'emprendimiento_ingresos', 'Ingresos de eco emprendimientos', 'response', 'sum', 'colones', 'eco-emprendimiento', 'emprendimiento_ingresos', true ),
		gnf_impact_metric( 'arboles_centros', 'Centros realizando Siembra de Arboles', 'entry', 'count', 'centros', 'siembra-de-arboles' ),
		gnf_impact_metric( 'arboles_siembras', 'Siembras de arboles realizadas', 'response', 'sum', 'siembras', 'siembra-de-arboles', 'arboles_siembras' ),
		gnf_impact_metric( 'arboles_estudiantes', 'Estudiantes sembrando arboles', 'response', 'sum', 'estudiantes', 'siembra-de-arboles', 'arboles_estudiantes', true ),
		gnf_impact_metric( 'arboles_plantados', 'Arboles plantados', 'response', 'sum', 'arboles', 'siembra-de-arboles', 'arboles_plantados', true ),
		gnf_impact_metric( 'eco_clubes_centros', 'Centros realizando Eco Clubes', 'entry', 'count', 'centros', 'eco-clubes' ),
		gnf_impact_metric( 'murales_centros', 'Centros realizando Eco Murales', 'entry', 'count', 'centros', 'artistico-eco-murales' ),
		gnf_impact_metric( 'eventos_centros', 'Centros realizando Evento Sostenible', 'entry', 'count', 'centros', 'evento-sostenible' ),
		gnf_impact_metric( 'bienestar_centros', 'Centros realizando Bienestar Animal', 'entry', 'count', 'centros', 'bienestar-animal' ),
		gnf_impact_metric( 'comodin_centros', 'Centros realizando Reto Comodin', 'entry', 'count', 'centros', 'comodin' ),
	);

	$result = array();
	foreach ( $metrics as $metric ) {
		$result[ $metric['key'] ] = $metric;
	}
	return $result;
}

function gnf_impact_field_definition( $key, $reto, $label, $patterns, $create = false, $type = 'number' ) {
	return array(
		'key'      => (string) $key,
		'reto'     => (string) $reto,
		'label'    => (string) $label,
		'patterns' => array_values( (array) $patterns ),
		'create'   => (bool) $create,
		'type'     => (string) $type,
	);
}

/**
 * Campos que alimentan indicadores. `create` indica que no existia en 2026.
 */
function gnf_get_impact_field_definitions( $anio = 2026 ) {
	if ( 2026 !== (int) $anio ) {
		return array();
	}
	return array(
		gnf_impact_field_definition( 'agua_captacion', 'agua', '¿Cuentan con un sistema permanente de captacion de agua llovida?', array( 'sistema permanente de captacion de agua' ) ),
		gnf_impact_field_definition( 'agua_riego', 'agua', '¿Cuentan con un sistema de riego ahorrativo?', array( 'sistema de riego ahorrativo' ) ),
		gnf_impact_field_definition( 'agua_registro_consumo', 'agua', '¿Cuentan con el consumo de agua de enero a noviembre del año pasado?', array( 'consumo de agua de enero a noviembre' ) ),
		gnf_impact_field_definition( 'energia_paneles_solares', 'electricidad', '¿Cuentan con paneles solares?', array( 'cuentan con paneles solares' ), true, 'radio' ),
		gnf_impact_field_definition( 'energia_registro_consumo', 'electricidad', '¿Cuentan con el consumo de energia de enero a noviembre del año pasado?', array( 'consumo de energia de enero a noviembre' ) ),
		gnf_impact_field_definition( 'residuos_estaciones', 'residuos', '¿Fomentan habitos de separacion de los residuos solidos?', array( 'habitos de separacion de los residuos' ) ),
		gnf_impact_field_definition( 'residuos_registro_no_valorizables', 'residuos', '¿Cuantifican los residuos no valorizables?', array( 'cuantifican los residuos no valorizables' ) ),
		gnf_impact_field_definition( 'residuos_kg_valorizables', 'residuos', 'Total de kilogramos de residuos valorizables recolectados y entregados', array( 'total de kilogramos de residuos valorizables' ), true ),
		gnf_impact_field_definition( 'organicos_compostaje', 'gestion-de-organicos', '¿Aprovechan los residuos organicos?', array( 'aprovechan los residuos organicos' ) ),
		gnf_impact_field_definition( 'limpiezas_total', 'limpiezas', '¿Cuantas limpiezas realizaron en total?', array( 'cuantas limpiezas realizaron en total' ), true ),
		gnf_impact_field_definition( 'limpiezas_bolsas', 'limpiezas', '¿Cuantas bolsas de residuos recolectaron durante las limpiezas?', array( 'cuantas bolsas de residuos recolectaron' ), true ),
		gnf_impact_field_definition( 'jardines_areas', 'jardines-y-polinizadores', '¿Con cuantas areas distintas de jardines amigables con polinizadores cuenta el centro?', array( 'cuantas areas distintas de jardines' ), true ),
		gnf_impact_field_definition( 'jardines_inaturalist', 'jardines-y-polinizadores', '¿Utilizaron iNaturalist para monitorear polinizadores?', array( 'utilizaron inaturalist' ), true, 'radio' ),
		gnf_impact_field_definition( 'jardines_refugios', 'jardines-y-polinizadores', '¿Cuantas casas para abejas, meliponarios, colmenas u hoteles de insectos tienen?', array( 'cuantas casas para abejas' ), true ),
		gnf_impact_field_definition( 'huerta_actividades', 'huerta', '¿Realizaron actividades en la huerta con estudiantes?', array( 'realizaron actividades en la huerta con estudiantes' ) ),
		gnf_impact_field_definition( 'eco_giras_total', 'eco-gira', 'Cantidad total de eco giras realizadas', array( 'cantidad total de eco giras realizadas' ), true ),
		gnf_impact_field_definition( 'eco_giras_estudiantes', 'eco-gira', 'Cantidad total de estudiantes que asistieron a las eco giras', array( 'cantidad de estudiantes' ) ),
		gnf_impact_field_definition( 'emprendimiento_ingresos', 'eco-emprendimiento', 'Monto total generado por el eco emprendimiento', array( 'monto total ganado', 'monto de dinero generado' ) ),
		gnf_impact_field_definition( 'arboles_siembras', 'siembra-de-arboles', '¿En cuantas siembras participaron?', array( 'en cuantas siembras participaron' ), true ),
		gnf_impact_field_definition( 'arboles_estudiantes', 'siembra-de-arboles', '¿Cuantos estudiantes participaron?', array( 'cuantos estudiantes participaron' ) ),
		gnf_impact_field_definition( 'arboles_plantados', 'siembra-de-arboles', '¿Cuantos arboles se plantaron en total?', array( 'cuantos arboles se plantaron' ), true ),
	);
}

function gnf_impact_normalize_label( $value ) {
	if ( function_exists( 'gnf_award_normalize_text' ) ) {
		return gnf_award_normalize_text( $value );
	}
	$value = trim( (string) $value );
	$value = strtr( $value, array( 'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n', 'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N' ) );
	if ( function_exists( 'iconv' ) ) {
		$ascii = iconv( 'UTF-8', 'ASCII//TRANSLIT//IGNORE', $value );
		$value = false !== $ascii ? $ascii : $value;
	}
	return strtolower( trim( (string) preg_replace( '/\s+/', ' ', $value ) ) );
}

function gnf_impact_definition_matches_label( $definition, $label ) {
	$label = gnf_impact_normalize_label( $label );
	foreach ( (array) ( $definition['patterns'] ?? array() ) as $pattern ) {
		if ( false !== strpos( $label, gnf_impact_normalize_label( $pattern ) ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Anota y agrega campos sin cambiar IDs existentes. Funcion pura y testeable.
 */
function gnf_prepare_impact_form_fields( $fields, $definitions ) {
	$fields  = is_array( $fields ) ? $fields : array();
	$changed = false;
	$found   = array();

	foreach ( $fields as $index => &$field ) {
		$key = (string) ( $field['gnf_metric_key'] ?? '' );
		if ( $key ) {
			$found[ $key ] = true;
			continue;
		}
		foreach ( (array) $definitions as $definition ) {
			if ( gnf_impact_definition_matches_label( $definition, $field['label'] ?? '' ) ) {
				$field['gnf_metric_key'] = $definition['key'];
				$found[ $definition['key'] ] = true;
				$changed = true;
				break;
			}
		}
	}
	unset( $field );

	$next_id = empty( $fields ) ? 1 : max( array_map( 'intval', array_keys( $fields ) ) ) + 1;
	foreach ( (array) $definitions as $definition ) {
		if ( empty( $definition['create'] ) || ! empty( $found[ $definition['key'] ] ) ) {
			continue;
		}
		$field = array(
			'id'             => $next_id,
			'type'           => 'radio' === $definition['type'] ? 'radio' : 'number',
			'label'          => $definition['label'],
			'required'       => '0',
			'size'           => 'large',
			'gnf_metric_key' => $definition['key'],
		);
		if ( 'radio' === $definition['type'] ) {
			$field['choices'] = array(
				1 => array( 'label' => 'Sí', 'value' => 'si' ),
				2 => array( 'label' => 'No', 'value' => 'no' ),
			);
		}
		$fields[ $next_id ] = $field;
		$found[ $definition['key'] ] = true;
		$next_id++;
		$changed = true;
	}

	return array( 'fields' => $fields, 'changed' => $changed );
}

/**
 * Migra formularios publicados una sola vez, conservando post e IDs de campos.
 */
function gnf_ensure_impact_fields_for_year( $anio = 2026 ) {
	$anio = (int) $anio;
	if ( 2026 !== $anio || ! function_exists( 'wpforms' ) || ! function_exists( 'gnf_get_available_retos_for_year' ) ) {
		return false;
	}
	$schema_key = 'gnf_impact_fields_schema_' . $anio;
	$definitions = gnf_get_impact_field_definitions( $anio );
	$by_reto     = array();
	$award_by_reto = array();
	foreach ( $definitions as $definition ) {
		$by_reto[ $definition['reto'] ][] = $definition;
	}
	if ( function_exists( 'gnf_get_award_field_definitions' ) ) {
		foreach ( gnf_get_award_field_definitions() as $definition ) {
			$award_by_reto[ $definition['reto'] ][] = $definition;
		}
	}
	$processed = 0;
	foreach ( gnf_get_available_retos_for_year( $anio ) as $reto ) {
		$slug = gnf_get_reto_canonical_slug( $reto->post_title );
		if ( empty( $by_reto[ $slug ] ) && empty( $award_by_reto[ $slug ] ) ) {
			continue;
		}
		$form_id   = gnf_get_reto_form_id_for_year( $reto->ID, $anio );
		$form_post = $form_id ? get_post( $form_id ) : null;
		$form_data = $form_post ? json_decode( (string) $form_post->post_content, true ) : null;
		if ( ! is_array( $form_data ) ) {
			continue;
		}
		$prepared       = gnf_prepare_impact_form_fields( $form_data['fields'] ?? array(), $by_reto[ $slug ] ?? array() );
		$award_prepared = function_exists( 'gnf_prepare_award_form_fields' )
			? gnf_prepare_award_form_fields( $prepared['fields'], $award_by_reto[ $slug ] ?? array() )
			: array( 'fields' => $prepared['fields'], 'changed' => false );
		if ( $prepared['changed'] || $award_prepared['changed'] ) {
			$form_data['fields']   = $award_prepared['fields'];
			$form_data['field_id'] = max( array_map( 'intval', array_keys( $prepared['fields'] ) ) ) + 1;
			wp_update_post(
				array(
					'ID'           => $form_id,
					'post_content' => wp_json_encode( $form_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
				)
			);
		}
		$processed++;
	}
	if ( $processed > 0 ) {
		update_option( $schema_key, 1, false );
		return true;
	}
	return false;
}

/**
 * Genera CSS acotado para ocultar campos cuantificables fuera del rollout.
 *
 * @param int   $form_id   Formulario WPForms.
 * @param int[] $field_ids Campos nuevos.
 * @return string
 */
function gnf_impact_field_visibility_css( $form_id, $field_ids ) {
	$form_id   = abs( (int) $form_id );
	$field_ids = array_values( array_unique( array_filter( array_map( 'intval', (array) $field_ids ) ) ) );
	if ( ! $form_id || empty( $field_ids ) ) {
		return '';
	}
	$selectors = array_map(
		static function ( $field_id ) use ( $form_id ) {
			return '#wpforms-' . $form_id . '-field_' . abs( (int) $field_id ) . '-container';
		},
		$field_ids
	);
	return '<style data-gnf-impact-rollout>' . implode( ',', $selectors ) . '{display:none!important}</style>';
}

/**
 * Obtiene IDs de campos creados especificamente para indicadores.
 *
 * @param int $form_id Formulario.
 * @param int $reto_id Reto.
 * @param int $anio    Año.
 * @return int[]
 */
function gnf_get_created_impact_field_ids( $form_id, $reto_id, $anio ) {
	$form_data = function_exists( 'gnf_get_wpforms_form_definition' ) ? gnf_get_wpforms_form_definition( $form_id ) : array();
	$fields    = is_array( $form_data['fields'] ?? null ) ? $form_data['fields'] : array();
	$slug      = function_exists( 'gnf_get_reto_canonical_slug' ) ? gnf_get_reto_canonical_slug( get_the_title( $reto_id ) ) : '';
	$created   = array();
	foreach ( gnf_impact_definitions_for_reto( $slug, $anio ) as $definition ) {
		if ( ! empty( $definition['create'] ) ) {
			$created[ $definition['key'] ] = true;
		}
	}
	$ids = array();
	foreach ( $fields as $field ) {
		if ( ! empty( $created[ $field['gnf_metric_key'] ?? '' ] ) ) {
			$ids[] = absint( $field['id'] ?? 0 );
		}
	}
	return array_values( array_filter( $ids ) );
}

/**
 * Procesa la preparacion manual e idempotente de formularios.
 */
function gnf_handle_prepare_impact_fields() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'No tienes permisos para preparar los formularios.', 'Acceso denegado', array( 'response' => 403 ) );
	}
	check_admin_referer( 'gnf_prepare_impact_fields_2026' );
	$prepared = gnf_ensure_impact_fields_for_year( 2026 );
	$url      = add_query_arg(
		array(
			'page'                     => 'guardianes-config',
			'gnf_impact_fields_result' => $prepared ? 'success' : 'unavailable',
		),
		admin_url( 'admin.php' )
	);
	wp_safe_redirect( $url );
	exit;
}

/**
 * Muestra el estado y la accion manual solo en Configuracion.
 */
function gnf_render_impact_fields_admin_notice() {
	if ( ! current_user_can( 'manage_options' ) || 'guardianes-config' !== ( $_GET['page'] ?? '' ) ) {
		return;
	}
	$result   = sanitize_key( (string) ( $_GET['gnf_impact_fields_result'] ?? '' ) );
	$prepared = (bool) get_option( 'gnf_impact_fields_schema_2026', false );
	if ( 'success' === $result ) {
		echo '<div class="notice notice-success is-dismissible"><p>Los formularios 2026 quedaron preparados para recopilar indicadores de impacto.</p></div>';
	} elseif ( 'unavailable' === $result ) {
		echo '<div class="notice notice-error is-dismissible"><p>No se encontraron formularios 2026 que pudieran prepararse.</p></div>';
	}
	?>
	<div class="notice notice-info">
		<p><strong>Campos de indicadores 2026:</strong> <?php echo esc_html( $prepared ? 'preparados' : 'pendientes de preparación' ); ?>.</p>
		<p>Esta acción conserva los campos existentes y agrega únicamente las cantidades faltantes. Los campos nuevos estarán disponibles para todos los centros.</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="gnf_prepare_impact_fields">
			<?php wp_nonce_field( 'gnf_prepare_impact_fields_2026' ); ?>
			<?php submit_button( $prepared ? 'Comprobar campos nuevamente' : 'Preparar campos de impacto 2026', 'secondary', 'submit', false ); ?>
		</form>
		<p></p>
	</div>
	<?php
}

if ( function_exists( 'add_action' ) ) {
	add_action( 'admin_post_gnf_prepare_impact_fields', 'gnf_handle_prepare_impact_fields' );
	add_action( 'admin_notices', 'gnf_render_impact_fields_admin_notice' );
}

function gnf_impact_numeric_value( $value ) {
	if ( is_array( $value ) ) {
		$value = reset( $value );
	}
	$value = trim( (string) $value );
	if ( '' === $value ) {
		return 0.0;
	}
	$value = str_replace( array( '₡', '$', ' ', "\xc2\xa0" ), '', $value );
	if ( false !== strpos( $value, ',' ) && false === strpos( $value, '.' ) ) {
		$value = str_replace( ',', '.', $value );
	} else {
		$value = str_replace( ',', '', $value );
	}
	return is_numeric( $value ) ? (float) $value : 0.0;
}

function gnf_impact_value_is_yes( $value ) {
	$value = function_exists( 'gnf_award_normalize_text' ) ? gnf_award_normalize_text( $value ) : strtolower( trim( (string) $value ) );
	return in_array( $value, array( 'si', 'yes', '1', 'true' ), true );
}

function gnf_impact_entry_qualifies( $entry, $mode ) {
	return 'approved' === $mode ? ! empty( $entry['approved'] ) : ! empty( $entry['active'] );
}

function gnf_impact_metric_value_for_record( $record, $metric, $mode ) {
	$operation = $metric['operation'];
	if ( 'count' === $operation && 'center' === $metric['source'] ) {
		return 1.0;
	}
	if ( 'count_small' === $operation ) {
		$is_small = function_exists( 'gnf_award_is_small_center_type' )
			? gnf_award_is_small_center_type( $record['tipoCentro'] ?? '' )
			: in_array( str_replace( '_', ' ', gnf_impact_normalize_label( $record['tipoCentro'] ?? '' ) ), array( 'unidocente', 'direccion i', 'direccion 1' ), true );
		return $is_small ? 1.0 : 0.0;
	}
	if ( 'sum_migrants' === $operation ) {
		return gnf_impact_numeric_value( $record['migrantes'] ?? 0 );
	}

	$entries = is_array( $record['entries'] ?? null ) ? $record['entries'] : array();
	if ( 'count_any' === $operation ) {
		foreach ( $entries as $entry ) {
			if ( gnf_impact_entry_qualifies( $entry, $mode ) ) {
				return 1.0;
			}
		}
		return 0.0;
	}

	$reto = (string) ( $metric['reto'] ?? '' );
	if ( ! isset( $entries[ $reto ] ) || ! gnf_impact_entry_qualifies( $entries[ $reto ], $mode ) ) {
		return 0.0;
	}
	if ( 'count' === $operation ) {
		return 1.0;
	}

	$field = (string) ( $metric['field'] ?? '' );
	$value = $entries[ $reto ]['responses'][ $field ] ?? null;
	if ( 'count_yes' === $operation ) {
		return gnf_impact_value_is_yes( $value ) ? 1.0 : 0.0;
	}
	return 'sum' === $operation ? gnf_impact_numeric_value( $value ) : 0.0;
}

function gnf_impact_empty_scope( $id, $label, $catalog, $extra = array() ) {
	$values = array();
	foreach ( $catalog as $key => $metric ) {
		$values[ $key ] = 0;
	}
	return array_merge(
		array(
			'id'     => (string) $id,
			'label'  => (string) $label,
			'values' => $values,
		),
		$extra
	);
}

function gnf_impact_add_record_to_scope( &$scope, $record, $catalog, $mode ) {
	foreach ( $catalog as $key => $metric ) {
		$scope['values'][ $key ] += gnf_impact_metric_value_for_record( $record, $metric, $mode );
	}
}

function gnf_impact_normalize_scope_values( &$scope, $catalog ) {
	foreach ( $scope['values'] as $key => $value ) {
		$operation = (string) ( $catalog[ $key ]['operation'] ?? '' );
		$scope['values'][ $key ] = 'sum' === $operation ? (float) round( $value, 2 ) : (int) round( $value );
	}
}

/**
 * Agrega registros canonicos por total, DRE y circuito.
 */
function gnf_aggregate_impact_records( $records, $catalog, $mode = 'active' ) {
	$mode     = 'approved' === $mode ? 'approved' : 'active';
	$catalog  = is_array( $catalog ) ? $catalog : array();
	$total    = gnf_impact_empty_scope( 'total', 'Total general', $catalog );
	$regions  = array();
	$circuits = array();
	$seen     = array();

	foreach ( (array) $records as $record ) {
		$centro_id = (int) ( $record['id'] ?? 0 );
		if ( ! $centro_id || isset( $seen[ $centro_id ] ) ) {
			continue;
		}
		$seen[ $centro_id ] = true;
		$region_id          = (string) ( (int) ( $record['regionId'] ?? 0 ) );
		$region_label       = (string) ( $record['regionName'] ?? 'Sin DRE' );
		$circuito           = trim( (string) ( $record['circuito'] ?? '' ) );
		$circuit_key        = $region_id . '|' . ( $circuito ?: 'sin-circuito' );

		if ( ! isset( $regions[ $region_id ] ) ) {
			$regions[ $region_id ] = gnf_impact_empty_scope( $region_id, $region_label ?: 'Sin DRE', $catalog );
		}
		if ( ! isset( $circuits[ $circuit_key ] ) ) {
			$circuits[ $circuit_key ] = gnf_impact_empty_scope(
				$circuit_key,
				$circuito ? 'Circuito ' . $circuito : 'Sin circuito',
				$catalog,
				array( 'regionId' => (int) $region_id, 'regionName' => $region_label, 'circuito' => $circuito )
			);
		}

		gnf_impact_add_record_to_scope( $total, $record, $catalog, $mode );
		gnf_impact_add_record_to_scope( $regions[ $region_id ], $record, $catalog, $mode );
		gnf_impact_add_record_to_scope( $circuits[ $circuit_key ], $record, $catalog, $mode );
	}

	gnf_impact_normalize_scope_values( $total, $catalog );
	foreach ( $regions as &$region_scope ) {
		gnf_impact_normalize_scope_values( $region_scope, $catalog );
	}
	unset( $region_scope );
	foreach ( $circuits as &$circuit_scope ) {
		gnf_impact_normalize_scope_values( $circuit_scope, $catalog );
	}
	unset( $circuit_scope );

	return array(
		'mode'     => $mode,
		'total'    => $total,
		'regions'  => $regions,
		'circuits' => $circuits,
	);
}

/**
 * Limita registros al grupo piloto cuando corresponde.
 *
 * @param array  $records   Registros por centro.
 * @param string $mode      Modo de rollout.
 * @param int[]  $pilot_ids Centros permitidos.
 * @return array
 */
function gnf_filter_impact_records_for_rollout( $records, $mode, $pilot_ids ) {
	if ( 'pilot' !== (string) $mode ) {
		return array_values( (array) $records );
	}
	$allowed = array_fill_keys( array_map( 'intval', (array) $pilot_ids ), true );
	return array_values(
		array_filter(
			(array) $records,
			static function ( $record ) use ( $allowed ) {
				return ! empty( $allowed[ (int) ( $record['id'] ?? 0 ) ] );
			}
		)
	);
}

function gnf_impact_reto_map( $anio ) {
	$map = array();
	if ( ! function_exists( 'gnf_get_available_retos_for_year' ) ) {
		return $map;
	}
	foreach ( gnf_get_available_retos_for_year( $anio ) as $reto ) {
		$slug = gnf_get_reto_canonical_slug( $reto->post_title );
		if ( $slug && empty( $map[ $slug ] ) ) {
			$map[ $slug ] = (int) $reto->ID;
		}
	}
	return $map;
}

function gnf_impact_definitions_for_reto( $reto_slug, $anio ) {
	return array_values(
		array_filter(
			gnf_get_impact_field_definitions( $anio ),
			static function ( $definition ) use ( $reto_slug ) {
				return $reto_slug === $definition['reto'];
			}
		)
	);
}

function gnf_impact_field_key_map( $reto_id, $reto_slug, $anio ) {
	$form_id   = function_exists( 'gnf_get_reto_form_id_for_year' ) ? gnf_get_reto_form_id_for_year( $reto_id, $anio ) : 0;
	$form_data = $form_id && function_exists( 'gnf_get_wpforms_form_definition' ) ? gnf_get_wpforms_form_definition( $form_id ) : array();
	$fields    = is_array( $form_data['fields'] ?? null ) ? $form_data['fields'] : array();
	$defs      = gnf_impact_definitions_for_reto( $reto_slug, $anio );
	$map       = array();
	foreach ( $fields as $field ) {
		$field_id = (int) ( $field['id'] ?? 0 );
		$key      = (string) ( $field['gnf_metric_key'] ?? '' );
		if ( ! $key ) {
			foreach ( $defs as $definition ) {
				if ( gnf_impact_definition_matches_label( $definition, $field['label'] ?? '' ) ) {
					$key = $definition['key'];
					break;
				}
			}
		}
		if ( $field_id && $key ) {
			$map[ $field_id ] = array( 'key' => $key, 'field' => $field );
		}
	}
	return $map;
}

function gnf_build_impact_entry_record( $entry, $anio ) {
	$reto_title = get_the_title( (int) $entry->reto_id );
	$reto_slug  = gnf_get_reto_canonical_slug( $reto_title );
	$data       = json_decode( (string) ( $entry->data ?? '' ), true );
	$raw_values = is_array( $data['__raw_values__'] ?? null ) ? $data['__raw_values__'] : array();
	$evidences  = json_decode( (string) ( $entry->evidencias ?? '' ), true );
	$evidences  = function_exists( 'gnf_enrich_evidencias' ) ? gnf_enrich_evidencias( (array) $evidences, (int) $entry->reto_id, $anio ) : (array) $evidences;
	$active     = false;
	$approved   = 'aprobado' === (string) ( $entry->estado ?? '' );
	foreach ( $evidences as $evidence ) {
		if ( ! is_array( $evidence ) || ! empty( $evidence['replaced'] ) ) {
			continue;
		}
		$state = (string) ( $evidence['estado'] ?? 'pendiente' );
		if ( 'rechazada' !== $state ) {
			$active = true;
		}
	}

	$responses = array();
	foreach ( gnf_impact_field_key_map( (int) $entry->reto_id, $reto_slug, $anio ) as $field_id => $field_info ) {
		$value = $raw_values[ $field_id ] ?? ( $data['__fields__'][ $field_id ] ?? null );
		if ( function_exists( 'gnf_get_wpforms_display_value' ) ) {
			$value = gnf_get_wpforms_display_value( $field_info['field'], $value );
		}
		$responses[ $field_info['key'] ] = $value;
	}

	return array(
		'reto'      => $reto_slug,
		'active'    => $active,
		'approved'  => $approved,
		'responses' => $responses,
	);
}

function gnf_merge_impact_entry_record( $current, $incoming ) {
	if ( empty( $current ) ) {
		return $incoming;
	}
	$current['active']    = ! empty( $current['active'] ) || ! empty( $incoming['active'] );
	$current['approved']  = ! empty( $current['approved'] ) || ! empty( $incoming['approved'] );
	$current['responses'] = array_replace( (array) ( $current['responses'] ?? array() ), (array) ( $incoming['responses'] ?? array() ) );
	return $current;
}

/**
 * Construye registros canonicos en lotes de 200 centros matriculados.
 */
function gnf_get_impact_center_records( $anio ) {
	global $wpdb;
	$records    = array();
	$centro_ids = function_exists( 'gnf_get_centros_with_matricula' ) ? gnf_get_centros_with_matricula( $anio ) : array();
	$centro_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $centro_ids ) ) ) );
	$table      = $wpdb->prefix . 'gn_reto_entries';

	foreach ( array_chunk( $centro_ids, 200 ) as $batch_ids ) {
		$posts = get_posts(
			array(
				'post_type'      => 'centro_educativo',
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'post__in'       => $batch_ids,
				'orderby'        => 'post__in',
			)
		);
		$published_ids = array_values( array_map( 'absint', wp_list_pluck( $posts, 'ID' ) ) );
		if ( empty( $published_ids ) ) {
			continue;
		}
		$batch = function_exists( 'gnf_build_centros_export_batch_maps' ) ? gnf_build_centros_export_batch_maps( $published_ids, $anio ) : array();
		foreach ( $published_ids as $centro_id ) {
			$profile = function_exists( 'gnf_build_centro_export_record' ) ? gnf_build_centro_export_record( $centro_id, $anio, $batch ) : array();
			$records[ $centro_id ] = array(
				'id'          => $centro_id,
				'regionId'    => (int) ( $profile['region_id'] ?? 0 ),
				'regionName'  => (string) ( $profile['region_name'] ?? '' ),
				'circuito'    => (string) ( $profile['circuito'] ?? '' ),
				'tipoCentro'  => (string) ( $profile['tipo_centro'] ?? '' ),
				'tipologia'   => (string) ( $profile['tipologia'] ?? '' ),
				'migrantes'   => (int) ( $profile['estudiantes_migrantes'] ?? 0 ),
				'entries'     => array(),
			);
		}

		$placeholders = implode( ',', array_fill( 0, count( $published_ids ), '%d' ) );
		$entries      = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE anio = %d AND centro_id IN ({$placeholders}) ORDER BY id ASC",
				array_merge( array( (int) $anio ), $published_ids )
			)
		);
		foreach ( (array) $entries as $entry ) {
			$centro_id    = (int) $entry->centro_id;
			$entry_record = gnf_build_impact_entry_record( $entry, $anio );
			$slug         = $entry_record['reto'];
			if ( $slug && isset( $records[ $centro_id ] ) ) {
				$records[ $centro_id ]['entries'][ $slug ] = gnf_merge_impact_entry_record( $records[ $centro_id ]['entries'][ $slug ] ?? array(), $entry_record );
			}
		}
	}
	$mode      = function_exists( 'gnf_get_feature_rollout_mode' ) ? gnf_get_feature_rollout_mode( 'impact' ) : 'off';
	$pilot_ids = function_exists( 'gnf_get_pilot_center_ids' ) ? gnf_get_pilot_center_ids() : array();
	return gnf_filter_impact_records_for_rollout( array_values( $records ), $mode, $pilot_ids );
}

function gnf_impact_metric_is_available( $metric, $anio, $reto_map = null ) {
	if ( 'center' === $metric['source'] || 'count_any' === $metric['operation'] ) {
		return true;
	}
	$reto_map = is_array( $reto_map ) ? $reto_map : gnf_impact_reto_map( $anio );
	$slug     = (string) ( $metric['reto'] ?? '' );
	if ( empty( $reto_map[ $slug ] ) ) {
		return false;
	}
	if ( 'response' !== $metric['source'] ) {
		return true;
	}
	$field_map = gnf_impact_field_key_map( $reto_map[ $slug ], $slug, $anio );
	foreach ( $field_map as $field ) {
		if ( $field['key'] === $metric['field'] ) {
			return true;
		}
	}
	return false;
}

function gnf_clear_impact_cache( $anio = 2026 ) {
	if ( function_exists( 'delete_transient' ) ) {
		delete_transient( 'gnf_impact_' . (int) $anio . '_active_v1' );
		delete_transient( 'gnf_impact_' . (int) $anio . '_approved_v1' );
		$scope = function_exists( 'gnf_get_feature_rollout_mode' ) ? gnf_get_feature_rollout_mode( 'impact' ) : 'off';
		$ids   = function_exists( 'gnf_get_pilot_center_ids' ) ? gnf_get_pilot_center_ids() : array();
		$hash  = substr( md5( $scope . ':' . implode( ',', $ids ) ), 0, 10 );
		delete_transient( 'gnf_impact_' . (int) $anio . '_active_v2_' . $hash );
		delete_transient( 'gnf_impact_' . (int) $anio . '_approved_v2_' . $hash );
	}
}

function gnf_build_impact_report( $anio, $mode = 'active', $force = false ) {
	$anio      = function_exists( 'gnf_normalize_year' ) ? gnf_normalize_year( $anio ) : (int) $anio;
	$mode      = 'approved' === $mode ? 'approved' : 'active';
	$scope      = function_exists( 'gnf_get_feature_rollout_mode' ) ? gnf_get_feature_rollout_mode( 'impact' ) : 'off';
	$pilot_ids  = function_exists( 'gnf_get_pilot_center_ids' ) ? gnf_get_pilot_center_ids() : array();
	$scope_hash = substr( md5( $scope . ':' . implode( ',', $pilot_ids ) ), 0, 10 );
	$cache_key  = 'gnf_impact_' . $anio . '_' . $mode . '_v2_' . $scope_hash;
	if ( ! $force && function_exists( 'get_transient' ) ) {
		$cached = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
	}
	$catalog  = gnf_get_impact_metric_catalog( $anio );
	$report   = gnf_aggregate_impact_records( gnf_get_impact_center_records( $anio ), $catalog, $mode );
	$reto_map = gnf_impact_reto_map( $anio );
	$items    = array();
	foreach ( $catalog as $metric ) {
		$metric['available'] = gnf_impact_metric_is_available( $metric, $anio, $reto_map );
		$items[] = $metric;
	}
	$report['year']        = $anio;
	$report['catalog']     = $items;
	$report['generatedAt'] = function_exists( 'current_time' ) ? current_time( 'mysql' ) : gmdate( 'Y-m-d H:i:s' );
	$report['rollout']     = function_exists( 'gnf_get_feature_rollout_summary' ) ? gnf_get_feature_rollout_summary( 'impact' ) : array( 'mode' => 'off', 'label' => 'Vista previa administrativa', 'pilotCenterCount' => 0 );
	if ( function_exists( 'set_transient' ) ) {
		set_transient( $cache_key, $report, 5 * MINUTE_IN_SECONDS );
	}
	return $report;
}

function gnf_get_public_impact_metric_keys( $anio = 2026 ) {
	$value = function_exists( 'get_field' ) ? get_field( 'public_impact_metrics', 'option' ) : get_option( 'options_public_impact_metrics', array() );
	$keys  = is_array( $value ) ? $value : preg_split( '/[\s,;]+/', (string) $value );
	$keys  = array_values( array_filter( array_map( 'sanitize_key', (array) $keys ) ) );
	return $keys;
}

function gnf_get_public_impact_metric_choices() {
	$choices = array();
	foreach ( gnf_get_impact_metric_catalog( 2026 ) as $key => $metric ) {
		$choices[ $key ] = $metric['title'];
	}
	return $choices;
}

function gnf_filter_impact_report_metrics( $report, $keys ) {
	$keys = array_fill_keys( array_values( (array) $keys ), true );
	$report['catalog'] = array_values(
		array_filter(
			(array) ( $report['catalog'] ?? array() ),
			static function ( $metric ) use ( $keys ) {
				return isset( $keys[ $metric['key'] ?? '' ] );
			}
		)
	);
	foreach ( array( 'total' ) as $scope_key ) {
		$report[ $scope_key ]['values'] = array_intersect_key( (array) ( $report[ $scope_key ]['values'] ?? array() ), $keys );
	}
	foreach ( array( 'regions', 'circuits' ) as $collection_key ) {
		foreach ( (array) ( $report[ $collection_key ] ?? array() ) as $id => $scope ) {
			$report[ $collection_key ][ $id ]['values'] = array_intersect_key( (array) ( $scope['values'] ?? array() ), $keys );
		}
	}
	return $report;
}

function gnf_rest_admin_impact( $request ) {
	$anio = function_exists( 'gnf_normalize_year' ) ? gnf_normalize_year( $request->get_param( 'year' ) ) : (int) $request->get_param( 'year' );
	return gnf_build_impact_report( $anio, 'active' );
}

function gnf_rest_public_impact( $request ) {
	$anio   = function_exists( 'gnf_normalize_year' ) ? gnf_normalize_year( $request->get_param( 'year' ) ) : (int) $request->get_param( 'year' );
	if ( 'all' !== gnf_get_feature_rollout_mode( 'impact' ) ) {
		return array( 'year' => $anio, 'mode' => 'approved', 'catalog' => array(), 'regions' => array(), 'circuits' => array() );
	}
	$report = gnf_build_impact_report( $anio, 'approved' );
	return gnf_filter_impact_report_metrics( $report, gnf_get_public_impact_metric_keys( $anio ) );
}

function gnf_render_impact_shortcode( $atts = array() ) {
	if ( 'all' !== gnf_get_feature_rollout_mode( 'impact' ) ) {
		return '';
	}
	$atts = shortcode_atts( array( 'year' => gnf_get_active_year(), 'metrics' => '' ), $atts, 'gnf_indicadores_impacto' );
	$anio = gnf_normalize_year( $atts['year'] );
	$keys = trim( (string) $atts['metrics'] )
		? array_values( array_filter( array_map( 'sanitize_key', preg_split( '/[\s,;]+/', (string) $atts['metrics'] ) ) ) )
		: gnf_get_public_impact_metric_keys( $anio );
	$data = gnf_filter_impact_report_metrics( gnf_build_impact_report( $anio, 'approved' ), $keys );
	if ( empty( $data['catalog'] ) ) {
		return '';
	}
	ob_start();
	?>
	<section class="gnf-impact-public" aria-label="Indicadores de impacto Guardianes">
		<style>
			.gnf-impact-public{font-family:inherit;margin:24px auto;max-width:1180px}.gnf-impact-public__grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:14px}.gnf-impact-public__item{background:#fff;border:1px solid #dce4df;border-left:4px solid #16866f;padding:18px}.gnf-impact-public__value{display:block;color:#173f35;font-size:2rem;font-weight:750;line-height:1.1}.gnf-impact-public__label{display:block;color:#415a53;font-size:.95rem;margin-top:7px}.gnf-impact-public__year{color:#60736e;font-size:.85rem;margin-top:12px}
		</style>
		<div class="gnf-impact-public__grid">
			<?php foreach ( $data['catalog'] as $metric ) : ?>
				<?php if ( empty( $metric['available'] ) ) { continue; } ?>
				<div class="gnf-impact-public__item">
					<span class="gnf-impact-public__value"><?php echo esc_html( number_format_i18n( $data['total']['values'][ $metric['key'] ] ?? 0, 'sum' === $metric['operation'] ? 1 : 0 ) ); ?></span>
					<span class="gnf-impact-public__label"><?php echo esc_html( $metric['title'] ); ?></span>
				</div>
			<?php endforeach; ?>
		</div>
		<div class="gnf-impact-public__year"><?php echo esc_html( 'Datos validados ' . $anio ); ?></div>
	</section>
	<?php
	return ob_get_clean();
}
