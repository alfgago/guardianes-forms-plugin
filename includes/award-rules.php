<?php
/**
 * Reglas 2026 para estrellas y reconocimientos PBAE.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normaliza texto para comparaciones de reglas sin depender de WordPress.
 */
function gnf_award_normalize_text( $value ) {
	$value = trim( (string) $value );
	if ( function_exists( 'remove_accents' ) ) {
		$value = remove_accents( $value );
	} else {
		$value = strtr( $value, array( 'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n', 'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N' ) );
	}
	if ( ! function_exists( 'remove_accents' ) && function_exists( 'iconv' ) ) {
		$ascii = iconv( 'UTF-8', 'ASCII//TRANSLIT//IGNORE', $value );
		$value = false !== $ascii ? $ascii : $value;
	}
	$value = strtolower( $value );
	return trim( (string) preg_replace( '/\s+/', ' ', $value ) );
}

/**
 * Selecciona la tabla base. La tipologia prevalece; tipo de centro es respaldo.
 */
function gnf_award_rubric_key( $tipologia, $tipo_centro = '' ) {
	$tipologia   = str_replace( array( '_', '-' ), ' ', gnf_award_normalize_text( $tipologia ) );
	$tipo_centro = str_replace( array( '_', '-' ), ' ', gnf_award_normalize_text( $tipo_centro ) );

	if ( preg_match( '/^(?:tipo\s+)?(?:iv|v|4|5)$/', $tipologia ) ) {
		return 'small';
	}
	if ( preg_match( '/^(?:tipo\s+)?(?:i|ii|iii|1|2|3)$/', $tipologia ) ) {
		return 'general';
	}

	return in_array( $tipo_centro, array( 'unidocente', 'direccion i', 'direccion 1' ), true ) ? 'small' : 'general';
}

function gnf_award_is_small_center_type( $tipo_centro ) {
	$tipo_centro = str_replace( array( '_', '-' ), ' ', gnf_award_normalize_text( $tipo_centro ) );
	return in_array( $tipo_centro, array( 'unidocente', 'direccion i', 'direccion 1' ), true );
}

function gnf_award_field_definition( $key, $reto, $patterns ) {
	return array(
		'key'      => (string) $key,
		'reto'     => (string) $reto,
		'patterns' => array_values( (array) $patterns ),
	);
}

/**
 * Claves persistentes de los campos utilizados por reconocimientos especiales.
 */
function gnf_get_award_field_definitions() {
	return array(
		gnf_award_field_definition( 'huerta_consumo', 'huerta', array( 'consumo de productos de la huerta' ) ),
		gnf_award_field_definition( 'organicos', 'gestion-de-organicos', array( 'aprovechan los residuos organicos' ) ),
		gnf_award_field_definition( 'residuos_taller', 'residuos', array( 'taller teorico-practico' ) ),
		gnf_award_field_definition( 'residuos_estacion', 'residuos', array( 'habitos de separacion' ) ),
		gnf_award_field_definition( 'residuos_registro_valorizables', 'residuos', array( 'cuantifican la recoleccion de material valorizable' ) ),
		gnf_award_field_definition( 'residuos_registro_no_valorizables', 'residuos', array( 'cuantifican los residuos no valorizables' ) ),
		gnf_award_field_definition( 'bienestar_capacitacion', 'bienestar-animal', array( 'capacitacion sobre bienestar animal' ) ),
		gnf_award_field_definition( 'bienestar_protocolo', 'bienestar-animal', array( 'cuentan con protocolo' ) ),
		gnf_award_field_definition( 'bienestar_implementacion', 'bienestar-animal', array( 'implementaron el protocolo' ) ),
	);
}

function gnf_award_definition_matches_label( $definition, $label ) {
	$label = gnf_award_normalize_text( $label );
	foreach ( (array) ( $definition['patterns'] ?? array() ) as $pattern ) {
		if ( false !== strpos( $label, gnf_award_normalize_text( $pattern ) ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Anota preguntas existentes sin cambiar sus IDs ni contenido visible.
 */
function gnf_prepare_award_form_fields( $fields, $definitions ) {
	$fields  = is_array( $fields ) ? $fields : array();
	$changed = false;
	foreach ( $fields as &$field ) {
		if ( ! empty( $field['gnf_award_key'] ) ) {
			continue;
		}
		foreach ( (array) $definitions as $definition ) {
			if ( gnf_award_definition_matches_label( $definition, $field['label'] ?? '' ) ) {
				$field['gnf_award_key'] = $definition['key'];
				$changed = true;
				break;
			}
		}
	}
	unset( $field );
	return array( 'fields' => $fields, 'changed' => $changed );
}

function gnf_award_thresholds( $rubric_key ) {
	return 'small' === $rubric_key
		? array( 1 => 60, 2 => 90, 3 => 120, 4 => 145, 5 => 180 )
		: array( 1 => 105, 2 => 120, 3 => 180, 4 => 240, 5 => 275 );
}

function gnf_award_requirement( $key, $label, $met ) {
	return array(
		'key'   => (string) $key,
		'label' => (string) $label,
		'met'   => (bool) $met,
	);
}

function gnf_award_result_item( $key, $label, $requirements ) {
	$missing = array_values(
		array_map(
			static function ( $requirement ) {
				return $requirement['label'];
			},
			array_filter(
				(array) $requirements,
				static function ( $requirement ) {
					return empty( $requirement['met'] );
				}
			)
		)
	);

	return array(
		'key'          => (string) $key,
		'label'        => (string) $label,
		'achieved'     => empty( $missing ),
		'requirements' => array_values( (array) $requirements ),
		'missing'      => $missing,
	);
}

/**
 * Evalua una instantanea ya normalizada. No lee WordPress ni base de datos.
 */
function gnf_evaluate_award( $input ) {
	$input       = is_array( $input ) ? $input : array();
	$score       = max( 0, (int) ( $input['score'] ?? 0 ) );
	$rubric_key  = gnf_award_rubric_key( $input['tipologia'] ?? '', $input['tipo_centro'] ?? '' );
	$thresholds  = gnf_award_thresholds( $rubric_key );
	$required    = is_array( $input['required'] ?? null ) ? $input['required'] : array();
	$criteria    = is_array( $input['criteria'] ?? null ) ? $input['criteria'] : array();
	$small_type  = gnf_award_is_small_center_type( $input['tipo_centro'] ?? '' );
	$score_stars = 0;

	foreach ( $thresholds as $stars => $minimum ) {
		if ( $score >= $minimum ) {
			$score_stars = (int) $stars;
		}
	}

	$missing_required = array();
	foreach ( array( 'agua', 'electricidad', 'residuos' ) as $required_key ) {
		if ( empty( $required[ $required_key ] ) ) {
			$missing_required[] = $required_key;
		}
	}
	$base_eligible = empty( $missing_required );
	$stars         = $base_eligible ? $score_stars : 0;

	$excellence_requirements = array(
		gnf_award_requirement( 'cinco_estrellas', 'Alcanzar cinco estrellas', 5 === $stars ),
	);
	if ( $small_type ) {
		$excellence_requirements[] = gnf_award_requirement( 'eco_gira', 'Completar Reto Eco Gira', ! empty( $criteria['eco_gira'] ) );
		$excellence_requirements[] = gnf_award_requirement( 'eco_emprendimiento', 'Completar Reto Eco Emprendimiento', ! empty( $criteria['eco_emprendimiento'] ) );
	} else {
		$excellence_requirements[] = gnf_award_requirement( 'setenta_adicionales', 'Obtener 70 puntos adicionales', $score >= ( $thresholds[5] + 70 ) );
	}

	$food_requirements = array(
		gnf_award_requirement( 'requisitos_base', 'Cumplir Agua, Energía y Residuos', $base_eligible ),
		gnf_award_requirement( 'huerta_consumo', 'Consumir productos de la huerta en el comedor', ! empty( $criteria['huerta_consumo'] ) ),
		gnf_award_requirement( 'organicos', 'Gestionar residuos organicos', ! empty( $criteria['organicos'] ) ),
	);
	if ( ! $small_type ) {
		$food_requirements[] = gnf_award_requirement( 'eco_lonchera', 'Completar Reto Eco Lonchera', ! empty( $criteria['eco_lonchera'] ) );
	}

	$waste_requirements = array(
		gnf_award_requirement( 'requisitos_base', 'Cumplir Agua, Energía y Residuos', $base_eligible ),
		gnf_award_requirement( 'residuos_taller', 'Realizar taller de gestion de residuos', ! empty( $criteria['residuos_taller'] ) ),
		gnf_award_requirement( 'residuos_estacion', 'Implementar estaciones de separacion', ! empty( $criteria['residuos_estacion'] ) ),
		gnf_award_requirement( 'organicos', 'Gestionar residuos organicos', ! empty( $criteria['organicos'] ) ),
	);
	if ( ! $small_type ) {
		$waste_requirements[] = gnf_award_requirement( 'registro_valorizables', 'Registrar residuos valorizables', ! empty( $criteria['residuos_registro_valorizables'] ) );
		$waste_requirements[] = gnf_award_requirement( 'registro_no_valorizables', 'Registrar residuos no valorizables', ! empty( $criteria['residuos_registro_no_valorizables'] ) );
	}

	$animal_requirements = array(
		gnf_award_requirement( 'requisitos_base', 'Cumplir Agua, Energía y Residuos', $base_eligible ),
		gnf_award_requirement( 'bienestar_capacitacion', 'Realizar capacitacion de bienestar animal', ! empty( $criteria['bienestar_capacitacion'] ) ),
		gnf_award_requirement( 'bienestar_protocolo', 'Contar con protocolo de bienestar animal', ! empty( $criteria['bienestar_protocolo'] ) ),
		gnf_award_requirement( 'bienestar_implementacion', 'Implementar el protocolo', ! empty( $criteria['bienestar_implementacion'] ) ),
	);

	return array(
		'rubricKey'      => $rubric_key,
		'rubricLabel'    => 'small' === $rubric_key ? 'Tipo IV/V' : 'Tipo I/II/III',
		'thresholds'     => $thresholds,
		'score'          => $score,
		'scoreStars'     => $score_stars,
		'stars'          => $stars,
		'baseEligible'   => $base_eligible,
		'missingRequired'=> $missing_required,
		'awards'         => array(
			'excelencia_general' => gnf_award_result_item( 'excelencia_general', 'Excelencia general', $excellence_requirements ),
			'dorada_turquesa'    => gnf_award_result_item( 'dorada_turquesa', 'Estrella Dorada / Turquesa', $food_requirements ),
			'plata_residuos'     => gnf_award_result_item( 'plata_residuos', 'Estrella Plata - Residuos', $waste_requirements ),
			'naranja_bienestar'  => gnf_award_result_item( 'naranja_bienestar', 'Estrella Naranja - Bienestar animal', $animal_requirements ),
		),
	);
}

function gnf_award_value_is_yes( $value ) {
	$value = gnf_award_normalize_text( $value );
	return in_array( $value, array( 'si', 'yes', '1', 'true' ), true );
}

function gnf_award_evidence_qualifies( $evidence, $mode ) {
	if ( ! is_array( $evidence ) || ! empty( $evidence['replaced'] ) ) {
		return false;
	}
	$state = (string) ( $evidence['estado'] ?? 'pendiente' );
	return 'validated' === $mode ? 'aprobada' === $state : 'rechazada' !== $state;
}

function gnf_award_entry_has_evidence( $entry, $mode, $label_contains = '' ) {
	$responses = function_exists( 'gnf_build_reto_entry_responses' ) ? gnf_build_reto_entry_responses( $entry, (int) $entry->anio ) : array();
	$needle    = gnf_award_normalize_text( $label_contains );
	foreach ( $responses as $response ) {
		if ( $needle && false === strpos( gnf_award_normalize_text( $response['label'] ?? '' ), $needle ) ) {
			continue;
		}
		foreach ( (array) ( $response['evidencias'] ?? array() ) as $evidence ) {
			if ( gnf_award_evidence_qualifies( $evidence, $mode ) ) {
				return true;
			}
		}
	}
	return false;
}

function gnf_award_entry_response_is_yes( $entry, $label_contains ) {
	$responses = function_exists( 'gnf_build_reto_entry_responses' ) ? gnf_build_reto_entry_responses( $entry, (int) $entry->anio ) : array();
	$needle    = gnf_award_normalize_text( $label_contains );
	foreach ( $responses as $response ) {
		if ( false !== strpos( gnf_award_normalize_text( $response['label'] ?? '' ), $needle ) && gnf_award_value_is_yes( $response['displayValue'] ?? '' ) ) {
			return true;
		}
	}
	return false;
}

function gnf_award_field_key_map( $entry ) {
	$form_id   = function_exists( 'gnf_get_reto_form_id_for_year' ) ? gnf_get_reto_form_id_for_year( (int) $entry->reto_id, (int) $entry->anio ) : 0;
	$form_data = $form_id && function_exists( 'gnf_get_wpforms_form_definition' ) ? gnf_get_wpforms_form_definition( $form_id ) : array();
	$fields    = is_array( $form_data['fields'] ?? null ) ? $form_data['fields'] : array();
	$slug      = function_exists( 'gnf_get_reto_canonical_slug' ) ? gnf_get_reto_canonical_slug( get_the_title( (int) $entry->reto_id ) ) : '';
	$defs      = array_values(
		array_filter(
			gnf_get_award_field_definitions(),
			static function ( $definition ) use ( $slug ) {
				return $slug === $definition['reto'];
			}
		)
	);
	$map = array();
	foreach ( $fields as $field ) {
		$field_id = absint( $field['id'] ?? 0 );
		$key      = sanitize_key( (string) ( $field['gnf_award_key'] ?? '' ) );
		if ( ! $key ) {
			foreach ( $defs as $definition ) {
				if ( gnf_award_definition_matches_label( $definition, $field['label'] ?? '' ) ) {
					$key = $definition['key'];
					break;
				}
			}
		}
		if ( $field_id && $key ) {
			$map[ $field_id ] = $key;
		}
	}
	return $map;
}

function gnf_award_find_response_by_key( $entry, $key, $legacy_label = '' ) {
	$responses = function_exists( 'gnf_build_reto_entry_responses' ) ? gnf_build_reto_entry_responses( $entry, (int) $entry->anio ) : array();
	$field_map = gnf_award_field_key_map( $entry );
	$legacy    = gnf_award_normalize_text( $legacy_label );
	foreach ( $responses as $response ) {
		$field_id = absint( $response['fieldId'] ?? 0 );
		if ( ( $field_id && ( $field_map[ $field_id ] ?? '' ) === $key ) || ( $legacy && false !== strpos( gnf_award_normalize_text( $response['label'] ?? '' ), $legacy ) ) ) {
			return $response;
		}
	}
	return array();
}

function gnf_award_entry_response_is_yes_for_key( $entry, $key, $legacy_label = '' ) {
	$response = gnf_award_find_response_by_key( $entry, $key, $legacy_label );
	return $response && gnf_award_value_is_yes( $response['displayValue'] ?? '' );
}

function gnf_award_entry_has_evidence_for_key( $entry, $mode, $key, $legacy_label = '' ) {
	$response = gnf_award_find_response_by_key( $entry, $key, $legacy_label );
	foreach ( (array) ( $response['evidencias'] ?? array() ) as $evidence ) {
		if ( gnf_award_evidence_qualifies( $evidence, $mode ) ) {
			return true;
		}
	}
	return false;
}

function gnf_award_validated_entry_score( $entry ) {
	if ( ! function_exists( 'gnf_get_reto_field_points' ) ) {
		return 0;
	}
	$data         = json_decode( (string) ( $entry->data ?? '' ), true );
	$values       = is_array( $data['__raw_values__'] ?? null ) ? $data['__raw_values__'] : array();
	$evidences    = json_decode( (string) ( $entry->evidencias ?? '' ), true );
	$evidences    = is_array( $evidences ) ? $evidences : array();
	$evidences    = function_exists( 'gnf_enrich_evidencias' ) ? gnf_enrich_evidencias( $evidences, (int) $entry->reto_id, (int) $entry->anio ) : $evidences;
	$field_points = gnf_get_reto_field_points( (int) $entry->reto_id, (int) $entry->anio );
	$file_fields  = array();
	foreach ( $evidences as $evidence ) {
		$file_fields[ (int) ( $evidence['field_id'] ?? 0 ) ] = true;
	}
	$total        = 0;
	foreach ( $field_points as $field_id => $config ) {
		$is_file = in_array( (string) ( $config['tipo'] ?? '' ), array( 'file', 'file-upload' ), true ) || ! empty( $file_fields[ (int) $field_id ] );
		if ( $is_file ) {
			foreach ( (array) $evidences as $evidence ) {
				if ( (int) ( $evidence['field_id'] ?? 0 ) === (int) $field_id && gnf_award_evidence_qualifies( $evidence, 'validated' ) ) {
					$total += absint( $config['puntos'] ?? 0 );
					break;
				}
			}
		} elseif ( function_exists( 'gnf_reto_entry_value_has_content' ) && gnf_reto_entry_value_has_content( $values[ $field_id ] ?? null ) ) {
			$total += absint( $config['puntos'] ?? 0 );
		}
	}
	return $total;
}

/**
 * Construye y persiste el resultado vigente de un centro.
 */
function gnf_award_required_fields_met( $fields, $evidences, $mode ) {
	$required = array();
	foreach ( (array) $fields as $field ) {
		$label = gnf_award_normalize_text( $field['label'] ?? '' );
		if ( isset( $field['id'] ) && 'file-upload' === ( $field['type'] ?? '' )
			&& preg_match( '/\brequisito\b/', $label )
			&& false === strpos( $label, 'si lo tienen' ) && false === strpos( $label, 'opcional' ) ) {
			$required[ (int) $field['id'] ] = false;
		}
	}
	if ( ! $required ) {
		return false;
	}
	foreach ( (array) $evidences as $evidence ) {
		if ( ! is_array( $evidence ) || ! isset( $evidence['field_id'] ) ) {
			continue;
		}
		$id = (int) $evidence['field_id'];
		if ( array_key_exists( $id, $required ) && gnf_award_evidence_qualifies( $evidence, $mode ) ) {
			$required[ $id ] = true;
		}
	}
	return ! in_array( false, $required, true );
}

function gnf_get_center_award_result( $centro_id, $anio, $mode = 'projected' ) {
	global $wpdb;
	$centro_id = absint( $centro_id );
	$anio      = function_exists( 'gnf_normalize_year' ) ? gnf_normalize_year( $anio ) : absint( $anio );
	$mode      = 'validated' === $mode ? 'validated' : 'projected';
	$table     = $wpdb->prefix . 'gn_reto_entries';
	$entries   = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE centro_id = %d AND anio = %d", $centro_id, $anio ) );
	$by_slug   = array();
	$score     = 0;

	foreach ( (array) $entries as $entry ) {
		$title = get_the_title( (int) $entry->reto_id );
		$slug  = function_exists( 'gnf_get_reto_canonical_slug' ) ? gnf_get_reto_canonical_slug( $title ) : '';
		if ( $slug ) {
			$by_slug[ $slug ] = $entry;
		}
		$score += 'validated' === $mode ? gnf_award_validated_entry_score( $entry ) : absint( $entry->puntaje ?? 0 );
	}

	$has_entry = static function ( $slug ) use ( $by_slug, $mode ) {
		return isset( $by_slug[ $slug ] ) && gnf_award_entry_has_evidence( $by_slug[ $slug ], $mode );
	};
	$response_yes = static function ( $slug, $key, $label ) use ( $by_slug, $mode ) {
		return isset( $by_slug[ $slug ] )
			&& gnf_award_entry_response_is_yes_for_key( $by_slug[ $slug ], $key, $label )
			&& gnf_award_entry_has_evidence( $by_slug[ $slug ], $mode );
	};

	$required = array(
		'agua'         => $has_entry( 'agua' ),
		'electricidad' => $has_entry( 'electricidad' ),
		'residuos'     => $has_entry( 'residuos' ),
	);
	foreach ( $required as $slug => $met ) {
		if ( ! isset( $by_slug[ $slug ] ) ) {
			continue;
		}
		$base_entry = $by_slug[ $slug ];
		$form_id = gnf_get_reto_form_id_for_year( (int) $base_entry->reto_id, $anio );
		$form = gnf_get_wpforms_form_definition( $form_id );
		$evidences = json_decode( (string) $base_entry->evidencias, true );
		$evidences = gnf_enrich_evidencias( (array) $evidences, (int) $base_entry->reto_id, $anio );
		$required[ $slug ] = gnf_award_required_fields_met( $form['fields'] ?? array(), $evidences, $mode );
	}
	$criteria = array(
		'eco_gira'                        => $has_entry( 'eco-gira' ),
		'eco_emprendimiento'              => $has_entry( 'eco-emprendimiento' ),
		'huerta_consumo'                  => isset( $by_slug['huerta'] ) && gnf_award_entry_has_evidence_for_key( $by_slug['huerta'], $mode, 'huerta_consumo', 'consumo de productos' ),
		'organicos'                       => $response_yes( 'gestion-de-organicos', 'organicos', 'aprovechan los residuos organicos' ),
		'eco_lonchera'                    => $has_entry( 'eco-lonchera' ),
		'residuos_taller'                 => $response_yes( 'residuos', 'residuos_taller', 'taller teorico-practico' ),
		'residuos_estacion'               => $response_yes( 'residuos', 'residuos_estacion', 'habitos de separacion' ),
		'residuos_registro_valorizables' => $response_yes( 'residuos', 'residuos_registro_valorizables', 'cuantifican la recoleccion de material valorizable' ),
		'residuos_registro_no_valorizables' => $response_yes( 'residuos', 'residuos_registro_no_valorizables', 'cuantifican los residuos no valorizables' ),
		'bienestar_capacitacion'          => $response_yes( 'bienestar-animal', 'bienestar_capacitacion', 'capacitacion sobre bienestar animal' ),
		'bienestar_protocolo'             => $response_yes( 'bienestar-animal', 'bienestar_protocolo', 'cuentan con protocolo' ),
		'bienestar_implementacion'        => $response_yes( 'bienestar-animal', 'bienestar_implementacion', 'implementaron el protocolo' ),
	);

	$tipologia   = function_exists( 'get_field' ) ? get_field( 'tipologia', $centro_id ) : '';
	$tipo_centro = function_exists( 'get_field' ) ? get_field( 'tipo_centro_educativo', $centro_id ) : '';
	$tipologia   = $tipologia ?: get_post_meta( $centro_id, 'tipologia', true );
	$tipo_centro = $tipo_centro ?: get_post_meta( $centro_id, 'tipo_centro_educativo', true );
	if ( '' === trim( (string) $tipologia ) || '' === trim( (string) $tipo_centro ) ) {
		$matricula_table = $wpdb->prefix . 'gn_matriculas';
		$matricula_json  = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT data FROM {$matricula_table} WHERE centro_id = %d AND anio = %d ORDER BY id DESC LIMIT 1",
				$centro_id,
				$anio
			)
		);
		$matricula_data = $matricula_json ? json_decode( (string) $matricula_json, true ) : array();
		if ( is_array( $matricula_data ) ) {
			$tipologia   = $tipologia ?: ( $matricula_data['centro-tipologia'] ?? $matricula_data['centro_tipologia'] ?? '' );
			$tipo_centro = $tipo_centro ?: ( $matricula_data['centro-tipo-centro-educativo'] ?? $matricula_data['centro_tipo_centro_educativo'] ?? '' );
		}
	}

	$result = gnf_evaluate_award(
		array(
			'score'       => $score,
			'tipologia'   => $tipologia,
			'tipo_centro' => $tipo_centro,
			'required'    => $required,
			'criteria'    => $criteria,
		)
	);
	$result['mode']         = $mode;
	$result['generatedAt']  = current_time( 'mysql' );
	$result['centroId']     = $centro_id;
	$result['year']         = $anio;
	update_post_meta( $centro_id, '_gnf_award_' . $anio . '_' . $mode, $result );

	return $result;
}

function gnf_get_stored_center_award_result( $centro_id, $anio, $mode = 'projected' ) {
	$result = get_post_meta( absint( $centro_id ), '_gnf_award_' . absint( $anio ) . '_' . ( 'validated' === $mode ? 'validated' : 'projected' ), true );
	return is_array( $result ) ? $result : array();
}

function gnf_award_result_fingerprint( $result ) {
	unset( $result['generatedAt'] );
	return hash( 'sha256', json_encode( $result ) );
}

/** Assigned results remain separate from the dynamically calculated score. */
function gnf_get_assigned_center_award( $centro_id, $anio ) {
	$key = '_gnf_award_assigned_' . (int) $anio;
	$assigned = get_post_meta( $centro_id, $key, true );
	if ( ! is_array( $assigned ) || empty( $assigned['result'] ) ) {
		return array();
	}
	$current = gnf_get_center_award_result( $centro_id, $anio, 'validated' );
	if ( gnf_award_result_fingerprint( $current ) !== gnf_award_result_fingerprint( $assigned['result'] ) ) {
		delete_post_meta( $centro_id, $key );
		gnf_log_audit_event( 'award_assignment_invalidated', array( 'centro_id' => $centro_id, 'anio' => $anio, 'message' => 'El resultado validado cambió; requiere nueva asignación.' ) );
		return array();
	}
	return $assigned;
}

function gnf_rest_admin_assign_award( WP_REST_Request $request ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return new WP_Error( 'forbidden', 'No tienes permisos para asignar galardones.', array( 'status' => 403 ) );
	}
	$centro_id = absint( $request->get_param( 'id' ) );
	$anio = gnf_normalize_year( $request->get_param( 'year' ) );
	$action = (string) $request->get_param( 'action' );
	if ( 'centro_educativo' !== get_post_type( $centro_id ) || ! in_array( $action, array( 'assign', 'revoke' ), true ) ) {
		return new WP_Error( 'invalid_award_request', 'Centro o acción inválida.', array( 'status' => 400 ) );
	}
	$key = '_gnf_award_assigned_' . $anio;
	if ( 'revoke' === $action ) {
		delete_post_meta( $centro_id, $key );
		$assigned = null;
	} else {
		if ( 2026 !== $anio ) {
			return new WP_Error( 'unsupported_rubric', 'La rúbrica disponible corresponde a 2026.', array( 'status' => 400 ) );
		}
		$result = gnf_get_center_award_result( $centro_id, $anio, 'validated' );
		if ( empty( $result['baseEligible'] ) || empty( $result['stars'] ) ) {
			return new WP_Error( 'award_not_eligible', 'El centro aún no cumple los requisitos y puntos validados para un galardón.', array( 'status' => 400 ) );
		}
		$assigned = array( 'result' => $result, 'assignedAt' => current_time( 'mysql' ), 'assignedBy' => get_current_user_id() );
		if ( ! update_post_meta( $centro_id, $key, $assigned ) && get_post_meta( $centro_id, $key, true ) !== $assigned ) {
			return new WP_Error( 'award_save_failed', 'No se pudo guardar el galardón.', array( 'status' => 500 ) );
		}
	}
	gnf_log_audit_event( 'assign' === $action ? 'admin_assign_award' : 'admin_revoke_award', array(
		'actor_user_id' => get_current_user_id(), 'centro_id' => $centro_id, 'anio' => $anio, 'panel' => 'admin',
		'message' => 'assign' === $action ? 'Galardón validado asignado.' : 'Asignación de galardón retirada.',
	) );
	return array( 'success' => true, 'assignedAward' => $assigned );
}

/**
 * Devuelve ambos estados del galardon cuando el rollout permite mostrarlos.
 */
function gnf_get_center_award_bundle( $centro_id, $anio, $recalculate = true, $allow_admin_preview = true ) {
	if ( function_exists( 'gnf_feature_is_enabled_for_center' ) && ! gnf_feature_is_enabled_for_center( 'awards', $centro_id, $allow_admin_preview ) ) {
		return array();
	}
	$getter = $recalculate ? 'gnf_get_center_award_result' : 'gnf_get_stored_center_award_result';
	return array(
		'projected'  => $getter( $centro_id, $anio, 'projected' ),
		'validated'  => $getter( $centro_id, $anio, 'validated' ),
		'assigned'   => gnf_get_assigned_center_award( $centro_id, $anio ) ?: null,
		'rollout'    => function_exists( 'gnf_get_feature_rollout_summary' ) ? gnf_get_feature_rollout_summary( 'awards' ) : array(),
		'ruleVersion'=> '2026.1',
	);
}
