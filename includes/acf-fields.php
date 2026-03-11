<?php

/**
 * Declaracion de campos ACF (local JSON via PHP).
 */

if (! defined('ABSPATH')) {
	exit;
}

function gnf_register_acf_fields()
{
	if (! function_exists('acf_add_local_field_group')) {
		return;
	}

	acf_add_options_page(
		array(
			'page_title'  => 'Formularios Bandera Azul - Configuracion',
			'menu_title'  => 'Formularios Bandera Azul',
			'menu_slug'   => 'guardianes-config',
			'parent_slug' => 'gnf-admin',
			'capability'  => 'manage_options',
			'redirect'    => false,
		)
	);

	acf_add_local_field_group(
		array(
			'key'    => 'group_gnf_centro',
			'title'  => 'Centro Educativo',
			'fields' => array(
				array(
					'key'          => 'field_gnf_codigo_mep',
					'label'        => 'Código MEP',
					'name'         => 'codigo_mep',
					'type'         => 'text',
					'required'     => 0,
					'instructions' => 'Código del Ministerio de Educación Pública. Opcional para centros privados.',
				),
				array(
					'key'           => 'field_gnf_region',
					'label'         => 'Dirección Regional',
					'name'          => 'region',
					'type'          => 'taxonomy',
					'taxonomy'      => 'gn_region',
					'field_type'    => 'select',
					'return_format' => 'id',
					'add_term'      => false,
					'required'      => 1,
					'allow_null'    => 0,
					'instructions'  => 'Seleccione la Dirección Regional de Educación.',
				),
				array(
					'key'          => 'field_gnf_circuito',
					'label'        => 'Circuito',
					'name'         => 'circuito',
					'type'         => 'text',
					'instructions' => 'Circuito educativo al que pertenece el centro.',
				),
				array(
					'key'   => 'field_gnf_direccion',
					'label' => 'Direccion',
					'name'  => 'direccion',
					'type'  => 'textarea',
					'rows'  => 3,
				),
				array(
					'key'   => 'field_gnf_provincia',
					'label' => 'Provincia',
					'name'  => 'provincia',
					'type'  => 'text',
				),
				array(
					'key'   => 'field_gnf_canton',
					'label' => 'Cantón',
					'name'  => 'canton',
					'type'  => 'text',
				),
				array(
					'key'   => 'field_gnf_distrito',
					'label' => 'Distrito',
					'name'  => 'distrito',
					'type'  => 'text',
				),
				array(
					'key'   => 'field_gnf_poblado',
					'label' => 'Poblado',
					'name'  => 'poblado',
					'type'  => 'text',
				),
				array(
					'key'           => 'field_gnf_dependencia',
					'label'         => 'Dependencia',
					'name'          => 'dependencia',
					'type'          => 'select',
					'choices'       => array(
						'publica'      => 'Publico',
						'privada'      => 'Privado',
						'subvencionada' => 'Subvencionado',
					),
					'allow_null'    => 1,
					'instructions'  => 'Tipo de dependencia del centro educativo.',
				),
				array(
					'key'           => 'field_gnf_zona',
					'label'         => 'Zona',
					'name'          => 'zona',
					'type'          => 'select',
					'choices'       => array(
						'urbana' => 'Urbana',
						'rural'  => 'Rural',
					),
					'allow_null'    => 1,
				),
				array(
					'key'   => 'field_gnf_telefono',
					'label' => 'Teléfono',
					'name'  => 'telefono',
					'type'  => 'text',
				),
				array(
					'key'   => 'field_gnf_telefono2',
					'label' => 'Teléfono 2',
					'name'  => 'telefono2',
					'type'  => 'text',
				),
				array(
					'key'           => 'field_gnf_nivel_educativo',
					'label'         => 'Nivel educativo',
					'name'          => 'nivel_educativo',
					'type'          => 'select',
					'choices'       => array(
						'preescolar' => 'Preescolar',
						'primaria'   => 'Primaria',
						'secundaria' => 'Secundaria',
					),
					'allow_null'    => 1,
				),
				array(
					'key'           => 'field_gnf_jornada',
					'label'         => 'Jornada',
					'name'          => 'jornada',
					'type'          => 'select',
					'choices'       => array(
						'diurno'   => 'Diurno',
						'nocturno' => 'Nocturno',
					),
					'allow_null'    => 1,
				),
				array(
					'key'           => 'field_gnf_tipologia',
					'label'         => 'Tipologia segun matricula',
					'name'          => 'tipologia',
					'type'          => 'select',
					'choices'       => array(
						'tipo_i'   => 'Tipo I (500 o mas estudiantes)',
						'tipo_ii'  => 'Tipo II (300-499)',
						'tipo_iii' => 'Tipo III (100-299)',
						'tipo_iv'  => 'Tipo IV (99 o menos)',
						'tipo_v'   => 'Tipo V (multigrado)',
					),
					'allow_null'    => 1,
				),
				array(
					'key'   => 'field_gnf_correo_institucional',
					'label' => 'Correo institucional',
					'name'  => 'correo_institucional',
					'type'  => 'email',
				),
				array(
					'key'   => 'field_gnf_total_estudiantes',
					'label' => 'Cantidad total de estudiantes',
					'name'  => 'total_estudiantes',
					'type'  => 'number',
					'min'   => 0,
				),
				array(
					'key'   => 'field_gnf_estudiantes_hombres',
					'label' => 'Cantidad de hombres',
					'name'  => 'estudiantes_hombres',
					'type'  => 'number',
					'min'   => 0,
				),
				array(
					'key'   => 'field_gnf_estudiantes_mujeres',
					'label' => 'Cantidad de mujeres',
					'name'  => 'estudiantes_mujeres',
					'type'  => 'number',
					'min'   => 0,
				),
				array(
					'key'   => 'field_gnf_estudiantes_migrantes',
					'label' => 'Estudiantes en condicion de migrantes',
					'name'  => 'estudiantes_migrantes',
					'type'  => 'number',
					'min'   => 0,
				),
				array(
					'key'           => 'field_gnf_ultimo_galardon_estrellas',
					'label'         => 'Ultimo galardon logrado',
					'name'          => 'ultimo_galardon_estrellas',
					'type'          => 'select',
					'choices'       => array(
						'1' => '1 estrella',
						'2' => '2 estrellas',
						'3' => '3 estrellas',
						'4' => '4 estrellas',
						'5' => '5 estrellas',
					),
					'default_value' => '1',
					'allow_null'    => 0,
				),
				array(
					'key'           => 'field_gnf_ultimo_anio_participacion',
					'label'         => 'Ultimo ano de participacion',
					'name'          => 'ultimo_anio_participacion',
					'type'          => 'select',
					'choices'       => array(
						'2025' => '2025',
						'2024' => '2024',
						'otro' => 'Otro',
					),
					'default_value' => '2025',
					'allow_null'    => 0,
				),
				array(
					'key'   => 'field_gnf_ultimo_anio_participacion_otro',
					'label' => 'Ultimo ano de participacion (otro)',
					'name'  => 'ultimo_anio_participacion_otro',
					'type'  => 'number',
					'min'   => 1900,
					'max'   => 2100,
				),
				array(
					'key'           => 'field_gnf_coordinador_pbae_cargo',
					'label'         => 'Cargo coordinador(a) PBAE',
					'name'          => 'coordinador_pbae_cargo',
					'type'          => 'select',
					'choices'       => array(
						'director'      => 'Director(a)',
						'docente'       => 'Docente',
						'administrativo' => 'Administrativo',
					),
					'allow_null'    => 1,
				),
				array(
					'key'   => 'field_gnf_coordinador_pbae_nombre',
					'label' => 'Nombre coordinador(a) PBAE',
					'name'  => 'coordinador_pbae_nombre',
					'type'  => 'text',
				),
				array(
					'key'   => 'field_gnf_coordinador_pbae_celular',
					'label' => 'Numero de tel. celular coordinador(a)',
					'name'  => 'coordinador_pbae_celular',
					'type'  => 'text',
				),
				array(
					'key'           => 'field_gnf_estado_centro',
					'label'         => 'Estado del centro',
					'name'          => 'estado_centro',
					'type'          => 'select',
					'choices'       => array(
						'activo'                      => 'Activo',
						'pendiente_de_revision_admin' => 'Pendiente de revision admin',
					),
					'default_value' => 'pendiente_de_revision_admin',
				),
				array(
					'key'          => 'field_gnf_centro_datos_anuales',
					'label'        => 'Datos anuales de participación',
					'name'         => 'centro_datos_anuales',
					'type'         => 'repeater',
					'layout'       => 'table',
					'button_label' => 'Agregar año',
					'instructions' => 'Modelo anual del centro: matrícula, retos, puntajes y estrellas por período.',
					'sub_fields'   => array(
						array(
							'key'           => 'field_gnf_centro_anual_anio',
							'label'         => 'Año',
							'name'          => 'anio',
							'type'          => 'number',
							'required'      => 1,
							'default_value' => gmdate( 'Y' ),
							'min'           => 2020,
							'max'           => 2050,
							'wrapper'       => array( 'width' => 10 ),
						),
						array(
							'key'           => 'field_gnf_centro_anual_estado_matricula',
							'label'         => 'Estado matrícula',
							'name'          => 'estado_matricula',
							'type'          => 'select',
							'choices'       => array(
								'no_iniciado' => 'No iniciado',
								'pendiente'   => 'Pendiente',
								'aprobada'    => 'Aprobada',
								'cerrada'     => 'Cerrada',
							),
							'default_value' => 'no_iniciado',
							'allow_null'    => 0,
							'wrapper'       => array( 'width' => 14 ),
						),
						array(
							'key'           => 'field_gnf_centro_anual_meta_estrellas',
							'label'         => 'Meta estrellas',
							'name'          => 'meta_estrellas',
							'type'          => 'number',
							'default_value' => 0,
							'min'           => 0,
							'max'           => 5,
							'wrapper'       => array( 'width' => 10 ),
						),
						array(
							'key'           => 'field_gnf_centro_anual_comite_estudiantes',
							'label'         => 'Comité estudiantes',
							'name'          => 'comite_estudiantes',
							'type'          => 'number',
							'default_value' => 0,
							'min'           => 0,
							'wrapper'       => array( 'width' => 12 ),
						),
						array(
							'key'           => 'field_gnf_centro_anual_retos',
							'label'         => 'Retos seleccionados',
							'name'          => 'retos_seleccionados',
							'type'          => 'relationship',
							'post_type'     => array( 'reto' ),
							'return_format' => 'id',
							'filters'       => array( 'search' ),
							'wrapper'       => array( 'width' => 24 ),
						),
						array(
							'key'           => 'field_gnf_centro_anual_puntaje_total',
							'label'         => 'Puntaje',
							'name'          => 'puntaje_total',
							'type'          => 'number',
							'default_value' => 0,
							'min'           => 0,
							'wrapper'       => array( 'width' => 10 ),
						),
						array(
							'key'           => 'field_gnf_centro_anual_estrella_final',
							'label'         => 'Estrella final',
							'name'          => 'estrella_final',
							'type'          => 'select',
							'choices'       => array(
								0 => '0',
								1 => '1',
								2 => '2',
								3 => '3',
								4 => '4',
								5 => '5',
							),
							'default_value' => 0,
							'wrapper'       => array( 'width' => 10 ),
						),
					),
				),
				array(
					'key'           => 'field_gnf_docentes_asociados',
					'label'         => 'Docentes asociados',
					'name'          => 'docentes_asociados',
					'type'          => 'user',
					'role'          => array('docente'),
					'return_format' => 'id',
					'multiple'      => 1,
				),
			),
			'location' => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'centro_educativo',
					),
				),
			),
		)
	);

	acf_add_local_field_group(
		array(
			'key'    => 'group_gnf_reto',
			'title'  => 'Reto',
			'fields' => array(
				array(
					'key'   => 'field_gnf_descripcion',
					'label' => 'Descripcion',
					'name'  => 'descripcion',
					'type'  => 'textarea',
					'rows'  => 4,
				),
				array(
					'key'   => 'field_gnf_color_reto',
					'label' => 'Color del reto',
					'name'  => 'color_del_reto',
					'type'  => 'color_picker',
					'default_value' => '#369484',
				),
				array(
					'key'          => 'field_gnf_config_por_anio',
					'label'        => 'Configuración por Año',
					'name'         => 'configuracion_por_anio',
					'type'         => 'repeater',
					'layout'       => 'block',
					'button_label' => 'Agregar año',
					'instructions' => 'Configure ícono, PDF, formulario y puntaje por campo para cada año.',
					'sub_fields'   => array(
						array(
							'key'           => 'field_gnf_cpa_anio',
							'label'         => 'Año',
							'name'          => 'anio',
							'type'          => 'number',
							'required'      => 1,
							'default_value' => gmdate('Y'),
							'min'           => 2020,
							'max'           => 2050,
							'wrapper'       => array('width' => 20),
						),
						array(
							'key'           => 'field_gnf_cpa_activo',
							'label'         => 'Activo',
							'name'          => 'activo',
							'type'          => 'true_false',
							'ui'            => 1,
							'default_value' => 1,
							'wrapper'       => array('width' => 15),
						),
						array(
							'key'          => 'field_gnf_cpa_notas',
							'label'        => 'Notas',
							'name'         => 'notas',
							'type'         => 'text',
							'placeholder'  => 'Ej: Versión actualizada 2026',
							'wrapper'      => array('width' => 65),
						),
						// --- Tab: Recursos ---
						array(
							'key'       => 'field_gnf_cpa_tab_recursos',
							'label'     => 'Recursos',
							'type'      => 'tab',
							'placement' => 'top',
						),
						array(
							'key'           => 'field_gnf_cpa_icono',
							'label'         => 'Ícono del año',
							'name'          => 'icono',
							'type'          => 'image',
							'return_format' => 'id',
							'preview_size'  => 'thumbnail',
							'library'       => 'all',
							'instructions'  => 'Ícono específico para este año. Si no se define, se usa la imagen destacada del reto.',
						),
						array(
							'key'           => 'field_gnf_cpa_pdf',
							'label'         => 'PDF del reto',
							'name'          => 'pdf',
							'type'          => 'file',
							'return_format' => 'id',
							'library'       => 'all',
							'mime_types'    => 'pdf',
							'instructions'  => 'Documento PDF con lineamientos del reto para este año.',
						),
						array(
							'key'           => 'field_gnf_cpa_wpforms_id',
							'label'         => 'Formulario WPForms',
							'name'          => 'wpforms_id',
							'type'          => 'post_object',
							'post_type'     => array('wpforms'),
							'return_format' => 'id',
							'ui'            => 1,
							'allow_null'    => 1,
						),
						// --- Tab: Puntaje ---
						array(
							'key'       => 'field_gnf_cpa_tab_puntaje',
							'label'     => 'Puntaje',
							'type'      => 'tab',
							'placement' => 'top',
						),
						array(
							'key'          => 'field_gnf_cpa_field_points',
							'label'        => 'Puntos por campo',
							'name'         => 'field_points',
							'type'         => 'repeater',
							'layout'       => 'table',
							'button_label' => 'Agregar campo',
							'instructions' => 'Se auto-completa al seleccionar un formulario. Asigne puntos a cada campo.',
							'sub_fields'   => array(
								array(
									'key'      => 'field_gnf_cpa_fp_field_id',
									'label'    => 'Field ID',
									'name'     => 'field_id',
									'type'     => 'number',
									'readonly' => 1,
									'wrapper'  => array('width' => 15),
								),
								array(
									'key'      => 'field_gnf_cpa_fp_field_label',
									'label'    => 'Campo',
									'name'     => 'field_label',
									'type'     => 'text',
									'readonly' => 1,
									'wrapper'  => array('width' => 40),
								),
								array(
									'key'      => 'field_gnf_cpa_fp_field_type',
									'label'    => 'Tipo',
									'name'     => 'field_type',
									'type'     => 'text',
									'readonly' => 1,
									'wrapper'  => array('width' => 20),
								),
								array(
									'key'           => 'field_gnf_cpa_fp_puntos',
									'label'         => 'Puntos',
									'name'          => 'puntos',
									'type'          => 'number',
									'min'           => 0,
									'default_value' => 0,
									'wrapper'       => array('width' => 25),
								),
							),
						),
						array(
							'key'           => 'field_gnf_cpa_puntaje_total',
							'label'         => 'Puntaje total',
							'name'          => 'puntaje_total',
							'type'          => 'number',
							'readonly'      => 1,
							'default_value' => 0,
							'instructions'  => 'Suma automática de puntos (calculado por JavaScript).',
						),
					),
				),
				array(
					'key'          => 'field_gnf_tipos_evidencia',
					'label'        => 'Tipos de evidencia permitidos',
					'name'         => 'tipos_evidencia_permitidos',
					'type'         => 'select',
					'choices'      => array(
						'foto'  => 'Foto',
						'pdf'   => 'PDF',
						'video' => 'Video',
					),
					'default_value' => array('foto', 'pdf', 'video'),
					'multiple'      => 1,
					'ui'            => 1,
					'return_format' => 'value',
				),
				array(
					'key'           => 'field_gnf_obligatorio_matricula',
					'label'         => 'Obligatorio en matrícula',
					'name'          => 'obligatorio_en_matricula',
					'type'          => 'true_false',
					'ui'            => 1,
					'default_value' => 0,
					'instructions'  => 'Si está activo, este reto se incluye automáticamente en toda matrícula (Agua, Energía, Residuos). El docente no puede desmarcarlo.',
				),
			),
			'location' => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'reto',
					),
				),
			),
		)
	);

	acf_add_local_field_group(
		array(
			'key'    => 'group_gnf_config',
			'title'  => 'Guardianes - Configuracion',
			'fields' => array(
				array(
					'key'           => 'field_gnf_anio_actual',
					'label'         => 'Año activo',
					'name'          => 'anio_actual',
					'type'          => 'number',
					'default_value' => gmdate('Y'),
					'instructions'  => 'Define el año fiscal activo (ej. 2025). Esto filtra los retos, matrículas y reportes para mostrar solo la información correspondiente a este periodo.',
				),
				array(
					'key'           => 'field_gnf_registros_drive_url',
					'label'         => 'Enlace Drive - Registros (Registrar y Reducir)',
					'name'          => 'registros_drive_url',
					'type'          => 'url',
					'placeholder'   => 'https://drive.google.com/...',
					'instructions'  => 'URL a la carpeta o tabla de Excel en Drive para que los centros suban/consulten registros de agua, energía y residuos. Se muestra en el panel docente (pestaña Matrícula). Corto plazo hasta implementar el formulario en plataforma.',
				),
				array(
					'key'   => 'field_gnf_rangos_group',
					'label' => 'Rangos de Estrellas',
					'type'  => 'group',
					'name'  => 'rangos_estrella',
					'instructions' => 'Configure los rangos de puntaje necesarios para alcanzar cada nivel de estrella (1 a 5). El sistema evaluará el puntaje total del centro y asignará la estrella correspondiente.',
					'sub_fields' => gnf_build_star_range_fields(),
				),
			),
			'location' => array(
				array(
					array(
						'param'    => 'options_page',
						'operator' => '==',
						'value'    => 'guardianes-config',
					),
				),
			),
		)
	);

	gnf_register_matricula_acf_group();
}
add_action('acf/init', 'gnf_register_acf_fields');

/**
 * Registra el grupo ACF de matrícula frontend desde JSON versionado.
 */
function gnf_register_matricula_acf_group()
{
	$json_path = GNF_PATH . 'seeders/acf-matricula-form-group.json';
	if (! file_exists($json_path)) {
		return;
	}

	$content = file_get_contents($json_path);
	if (! is_string($content) || '' === $content) {
		return;
	}

	$groups = json_decode($content, true);
	if (! is_array($groups)) {
		return;
	}

	foreach ($groups as $group) {
		if (is_array($group) && ! empty($group['key'])) {
			acf_add_local_field_group($group);
		}
	}
}

/**
 * Construye subcampos de rangos de estrella 1-5.
 */
function gnf_build_star_range_fields()
{
	$fields = array();
	for ($i = 1; $i <= 5; $i++) {
		$fields[] = array(
			'key'           => 'field_gnf_rango_' . $i . '_min',
			'label'         => 'Estrella ' . $i . ' - Mínimo',
			'name'          => 'rango_estrella_' . $i . '_min',
			'type'          => 'number',
			'default_value' => 0,
			'wrapper'       => array('width' => 50),
			'instructions'  => 'Puntaje mínimo requerido para alcanzar ' . $i . ' estrella(s).',
		);
		$fields[] = array(
			'key'           => 'field_gnf_rango_' . $i . '_max',
			'label'         => 'Estrella ' . $i . ' - Máximo',
			'name'          => 'rango_estrella_' . $i . '_max',
			'type'          => 'number',
			'default_value' => 0,
			'wrapper'       => array('width' => 50),
			'instructions'  => 'Puntaje máximo para este nivel. Use 0 para indicar "sin límite" (recomendado para la estrella 5).',
		);
	}
	return $fields;
}

/**
 * AJAX handler: return WPForms field definitions for a given form ID.
 * Used by acf-field-points.js to auto-populate the field_points sub-repeater.
 */
function gnf_ajax_fetch_form_fields() {
	check_ajax_referer( 'gnf_acf_field_points', 'nonce' );

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( 'Unauthorized', 403 );
	}

	$form_id = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
	if ( ! $form_id ) {
		wp_send_json_error( 'Missing form_id' );
	}

	$form = wpforms()->form->get( $form_id );
	if ( ! $form ) {
		wp_send_json_error( 'Form not found' );
	}

	$content = json_decode( $form->post_content, true );
	if ( empty( $content['fields'] ) ) {
		wp_send_json_success( array() );
	}

	$skip_types = array( 'divider', 'html', 'pagebreak', 'section', 'layout', 'content' );
	$fields     = array();

	foreach ( $content['fields'] as $field ) {
		$type = isset( $field['type'] ) ? $field['type'] : '';
		if ( in_array( $type, $skip_types, true ) ) {
			continue;
		}
		$fields[] = array(
			'field_id' => (int) $field['id'],
			'label'    => isset( $field['label'] ) ? $field['label'] : 'Campo ' . $field['id'],
			'type'     => $type,
		);
	}

	wp_send_json_success( $fields );
}
add_action( 'wp_ajax_gnf_fetch_form_fields', 'gnf_ajax_fetch_form_fields' );
