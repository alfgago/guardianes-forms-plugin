<?php
/**
 * Matricula frontend (ACF-managed schema, native submit handler).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Obtiene datos prellenados para el formulario de matricula.
 *
 * @param int $user_id  ID del usuario docente.
 * @param int $centro_id ID del centro asociado.
 * @param int $anio Año activo.
 * @return array
 */
function gnf_get_matricula_prefill_data( $user_id, $centro_id, $anio ) {
	global $wpdb;

	$user           = get_user_by( 'id', $user_id );
	$table          = $wpdb->prefix . 'gn_matriculas';
	$matricula_row  = null;
	$matricula_data = array();
	$retos_raw      = array();
	$retos_selected = array();
	$meta_estrellas = 0;
	$comite_est     = 0;

	if ( $centro_id ) {
		$matricula_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE centro_id = %d AND anio = %d ORDER BY id DESC LIMIT 1",
				$centro_id,
				$anio
			)
		);
	}

	if ( $matricula_row && ! empty( $matricula_row->data ) ) {
		$decoded = json_decode( $matricula_row->data, true );
		if ( is_array( $decoded ) ) {
			$matricula_data = $decoded;
		}
	}

	$get_data = static function ( $key, $default = null ) use ( $matricula_data ) {
		return array_key_exists( $key, $matricula_data ) ? $matricula_data[ $key ] : $default;
	};

	if ( $centro_id ) {
		$retos_raw      = gnf_get_centro_retos_seleccionados( $centro_id, $anio );
		$meta_estrellas = gnf_get_centro_meta_estrellas( $centro_id, $anio );
		$comite_est     = gnf_get_centro_comite_estudiantes( $centro_id, $anio );
	}

	if ( ! is_array( $retos_raw ) ) {
		$retos_raw = $get_data( 'bae-retos-seleccionados', array() );
	}
	if ( ! is_array( $retos_raw ) ) {
		$retos_raw = array();
	}

	$retos_selected = gnf_collapse_retos_a_raiz( array_map( 'intval', $retos_raw ) );
	$retos_selected = gnf_filter_reto_ids_by_year_form( $retos_selected, $anio );
	$retos_selected = gnf_sort_reto_ids_required_first( $retos_selected );

	if ( ! $meta_estrellas && $matricula_row ) {
		$meta_estrellas = (int) $matricula_row->meta_estrellas;
	}
	if ( ! $comite_est ) {
		$comite_est = (int) $get_data( 'bae-comite-estudiantes', 0 );
	}

	$docente_cargo    = get_user_meta( $user_id, 'docente_cargo', true );
	$docente_telefono = get_user_meta( $user_id, 'docente_telefono', true );
	if ( ! $docente_cargo ) {
		$docente_cargo = get_user_meta( $user_id, 'gnf_cargo', true );
	}
	if ( ! $docente_telefono ) {
		$docente_telefono = get_user_meta( $user_id, 'gnf_telefono', true );
	}

	$region_meta = $centro_id ? get_post_meta( $centro_id, 'region', true ) : '';
	if ( ! $region_meta && $centro_id ) {
		$terms       = wp_get_post_terms( $centro_id, 'gn_region', array( 'fields' => 'ids' ) );
		$region_meta = ! empty( $terms ) ? (int) $terms[0] : '';
	}

	$centro_nivel_educativo = $centro_id ? (string) get_post_meta( $centro_id, 'nivel_educativo', true ) : '';
	if ( ! $centro_nivel_educativo && $centro_id ) {
		$centro_nivel_educativo = (string) get_post_meta( $centro_id, 'modalidad', true );
	}
	$centro_nivel_educativo = gnf_normalize_centro_choice( 'nivel_educativo', $centro_nivel_educativo );

	$centro_dependencia = $centro_id ? (string) get_post_meta( $centro_id, 'dependencia', true ) : '';
	$centro_dependencia = gnf_normalize_centro_choice( 'dependencia', $centro_dependencia );

	$centro_jornada = $centro_id ? (string) get_post_meta( $centro_id, 'jornada', true ) : '';
	if ( ! $centro_jornada && $centro_id ) {
		$centro_jornada = (string) get_post_meta( $centro_id, 'horario', true );
	}
	$centro_jornada = gnf_normalize_centro_choice( 'jornada', $centro_jornada );

	$centro_tipologia = $centro_id ? (string) get_post_meta( $centro_id, 'tipologia', true ) : '';
	$centro_tipologia = gnf_normalize_centro_choice( 'tipologia', $centro_tipologia );

	$centro_ultimo_anio_participacion = $centro_id ? (string) get_post_meta( $centro_id, 'ultimo_anio_participacion', true ) : '';
	if ( ! in_array( $centro_ultimo_anio_participacion, array( '2025', '2024', 'otro' ), true ) ) {
		$centro_ultimo_anio_participacion = '2025';
	}

	$prefill = array(
		'centro_existe'                 => $centro_id ? 'Sí' : 'No',
		'centro_id_existente'           => $centro_id,
		'centro_nombre'                 => $centro_id ? get_the_title( $centro_id ) : '',
		'centro_codigo_mep'             => $centro_id ? (string) get_post_meta( $centro_id, 'codigo_mep', true ) : '',
		'centro_correo_institucional'   => $user ? (string) $user->user_email : '',
		'centro_telefono'               => $centro_id ? (string) get_post_meta( $centro_id, 'telefono', true ) : '',
		'centro_nivel_educativo'        => (string) $centro_nivel_educativo,
		'centro_dependencia'            => (string) $centro_dependencia,
		'centro_jornada'                => (string) $centro_jornada,
		'centro_tipologia'              => (string) $centro_tipologia,
		'centro_tipo_centro_educativo'  => $centro_id ? gnf_normalize_centro_choice( 'tipo_centro_educativo', (string) get_post_meta( $centro_id, 'tipo_centro_educativo', true ) ) : '',
		'centro_region'                 => $region_meta ? (int) $region_meta : '',
		'centro_circuito'               => $centro_id ? (string) get_post_meta( $centro_id, 'circuito', true ) : '',
		'centro_provincia'              => $centro_id ? (string) get_post_meta( $centro_id, 'provincia', true ) : '',
		'centro_canton'                 => $centro_id ? (string) get_post_meta( $centro_id, 'canton', true ) : '',
		'centro_codigo_presupuestario'  => $centro_id ? (string) get_post_meta( $centro_id, 'codigo_presupuestario', true ) : '',
		'centro_direccion'              => $centro_id ? (string) get_post_meta( $centro_id, 'direccion', true ) : '',
		'centro_total_estudiantes'      => $centro_id ? (int) get_post_meta( $centro_id, 'total_estudiantes', true ) : 0,
		'centro_estudiantes_hombres'    => $centro_id ? (int) get_post_meta( $centro_id, 'estudiantes_hombres', true ) : 0,
		'centro_estudiantes_mujeres'    => $centro_id ? (int) get_post_meta( $centro_id, 'estudiantes_mujeres', true ) : 0,
		'centro_estudiantes_migrantes'  => $centro_id ? (int) get_post_meta( $centro_id, 'estudiantes_migrantes', true ) : 0,
		'centro_ultimo_galardon_estrellas' => $centro_id ? (string) get_post_meta( $centro_id, 'ultimo_galardon_estrellas', true ) : '1',
		'centro_ultimo_anio_participacion' => (string) $centro_ultimo_anio_participacion,
		'centro_ultimo_anio_participacion_otro' => $centro_id ? (string) get_post_meta( $centro_id, 'ultimo_anio_participacion_otro', true ) : '',
		'coordinador_cargo'             => $centro_id ? (string) get_post_meta( $centro_id, 'coordinador_pbae_cargo', true ) : '',
		'coordinador_nombre'            => $centro_id ? (string) get_post_meta( $centro_id, 'coordinador_pbae_nombre', true ) : '',
		'coordinador_celular'           => $centro_id ? (string) get_post_meta( $centro_id, 'coordinador_pbae_celular', true ) : '',
		'docente_nombre'                => $user ? (string) $user->display_name : '',
		'docente_cargo'                 => (string) $docente_cargo,
		'docente_telefono'              => (string) $docente_telefono,
		'docente_email'                 => $user ? (string) $user->user_email : '',
		'docente_email_confirm'         => $user ? (string) $user->user_email : '',
		'docente_confirmaciones'        => array(),
		'bae_comite_estudiantes'        => $comite_est,
		'bae_inscripcion_anterior'      => (string) $get_data( 'bae-inscripcion-anterior', 'No' ),
		'bae_meta_estrellas'            => $meta_estrellas ? $meta_estrellas . ' estrella' . ( $meta_estrellas > 1 ? 's' : '' ) : '1 estrella',
		'bae_retos_seleccionados'       => $retos_selected,
	);

	// Si hay data de matricula guardada, priorizar sobre metas de centro para campos editables.
	$map = array(
		'centro_nombre'                    => array( 'centro-nombre' ),
		'centro_codigo_mep'                => array( 'centro-codigo-mep' ),
		'centro_correo_institucional'      => array( 'centro-correo-institucional' ),
		'centro_telefono'                  => array( 'centro-telefono' ),
		'centro_nivel_educativo'           => array( 'centro-nivel-educativo', 'centro-modalidad' ),
		'centro_dependencia'               => array( 'centro-dependencia' ),
		'centro_jornada'                   => array( 'centro-jornada', 'centro-horario' ),
		'centro_tipologia'                 => array( 'centro-tipologia' ),
		'centro_tipo_centro_educativo'     => array( 'centro-tipo-centro-educativo' ),
		'centro_region'                    => array( 'centro-region' ),
		'centro_circuito'                  => array( 'centro-circuito' ),
		'centro_provincia'                 => array( 'centro-provincia' ),
		'centro_canton'                    => array( 'centro-canton' ),
		'centro_codigo_presupuestario'     => array( 'centro-codigo-presupuestario' ),
		'centro_direccion'                 => array( 'centro-direccion' ),
		'centro_total_estudiantes'         => array( 'centro-total-estudiantes' ),
		'centro_estudiantes_hombres'       => array( 'centro-estudiantes-hombres' ),
		'centro_estudiantes_mujeres'       => array( 'centro-estudiantes-mujeres' ),
		'centro_estudiantes_migrantes'     => array( 'centro-estudiantes-migrantes' ),
		'centro_ultimo_galardon_estrellas' => array( 'centro-ultimo-galardon-estrellas' ),
		'centro_ultimo_anio_participacion' => array( 'centro-ultimo-anio-participacion' ),
		'centro_ultimo_anio_participacion_otro' => array( 'centro-ultimo-anio-participacion-otro' ),
		'coordinador_cargo'                => array( 'coordinador-cargo' ),
		'coordinador_nombre'               => array( 'coordinador-nombre' ),
		'coordinador_celular'              => array( 'coordinador-celular' ),
		'docente_nombre'                   => array( 'docente-nombre' ),
		'docente_cargo'                    => array( 'docente-cargo' ),
		'docente_telefono'                 => array( 'docente-telefono' ),
		'docente_email'                    => array( 'docente-email' ),
		'docente_email_confirm'            => array( 'docente-email-confirm' ),
		'bae_comite_estudiantes'           => array( 'bae-comite-estudiantes' ),
	);
	foreach ( $map as $target => $sources ) {
		foreach ( (array) $sources as $source ) {
			$val = $get_data( $source, null );
			if ( null !== $val && '' !== $val ) {
				$prefill[ $target ] = $val;
				break;
			}
		}
	}

	if ( $user && is_email( $user->user_email ) ) {
		$prefill['centro_correo_institucional'] = (string) $user->user_email;
		$prefill['docente_email']               = (string) $user->user_email;
		$prefill['docente_email_confirm']       = (string) $user->user_email;
	}

	$prefill['centro_nivel_educativo'] = gnf_normalize_centro_choice( 'nivel_educativo', $prefill['centro_nivel_educativo'] );
	$prefill['centro_dependencia']     = gnf_normalize_centro_choice( 'dependencia', $prefill['centro_dependencia'] );
	$prefill['centro_jornada']         = gnf_normalize_centro_choice( 'jornada', $prefill['centro_jornada'] );
	$prefill['centro_tipologia']       = gnf_normalize_centro_choice( 'tipologia', $prefill['centro_tipologia'] );
	$prefill['centro_tipo_centro_educativo'] = gnf_normalize_centro_choice( 'tipo_centro_educativo', $prefill['centro_tipo_centro_educativo'] );
	$prefill['coordinador_cargo']      = gnf_normalize_centro_choice( 'coordinador_cargo', $prefill['coordinador_cargo'] );
	if ( ! in_array( (string) $prefill['centro_ultimo_anio_participacion'], array( '2025', '2024', 'otro' ), true ) ) {
		$prefill['centro_ultimo_anio_participacion'] = '2025';
	}
	$prefill['centro_ultimo_galardon_estrellas'] = (string) max( 1, min( 5, absint( $prefill['centro_ultimo_galardon_estrellas'] ?: 1 ) ) );

	return $prefill;
}

/**
 * Renderiza el formulario de matricula nativo.
 *
 * @param array $args Argumentos de render.
 * @return string
 */
function gnf_render_matricula_form( $args = array() ) {
	if ( ! is_user_logged_in() ) {
		return '<p>Debes iniciar sesión para completar la matrícula.</p>';
	}

	$user_id  = get_current_user_id();
	$anio     = isset( $args['anio'] ) ? absint( $args['anio'] ) : gnf_get_context_year( gnf_get_active_year() );
	$anio     = gnf_normalize_year( $anio );
	$centro_id = isset( $args['centro_id'] ) ? absint( $args['centro_id'] ) : gnf_get_centro_for_docente( $user_id );

	if ( ! $centro_id ) {
		$centro_id = (int) get_user_meta( $user_id, 'centro_solicitado', true );
	}

	$prefill      = gnf_get_matricula_prefill_data( $user_id, $centro_id, $anio );
	$required_roots = gnf_collapse_retos_a_raiz( gnf_get_obligatorio_reto_ids() );
	$choices      = gnf_get_centro_profile_choice_sets();
	$provincias   = gnf_get_cr_provinces();
	$cantones_map = gnf_get_cr_province_canton_map();
	$section      = isset( $args['section'] ) ? sanitize_key( (string) $args['section'] ) : 'full';
	if ( ! in_array( $section, array( 'full', 'centro', 'retos' ), true ) ) {
		$section = 'full';
	}
	$show_center_sections = in_array( $section, array( 'full', 'centro' ), true );
	$show_retos_section   = in_array( $section, array( 'full', 'retos' ), true );

	$retos_q = new WP_Query(
		array(
			'post_type'      => 'reto',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'post_parent'    => 0,
			'orderby'        => 'menu_order title',
			'order'          => 'ASC',
		)
	);

	// Filtrar retos que tengan formulario explícitamente registrado para el año activo.
	if ( $retos_q->have_posts() && function_exists( 'gnf_reto_has_form_for_year' ) ) {
		$filtered_posts = array();
		foreach ( $retos_q->posts as $reto_post ) {
			if ( gnf_reto_has_form_for_year( $reto_post->ID, $anio ) ) {
				$filtered_posts[] = $reto_post;
			}
		}
		$retos_q->posts      = $filtered_posts;
		$retos_q->post_count = count( $filtered_posts );
	}
	if ( ! empty( $retos_q->posts ) ) {
		usort(
			$retos_q->posts,
			static function ( $a, $b ) use ( $required_roots ) {
				$a_required = in_array( (int) $a->ID, $required_roots, true ) ? 0 : 1;
				$b_required = in_array( (int) $b->ID, $required_roots, true ) ? 0 : 1;
				if ( $a_required !== $b_required ) {
					return $a_required <=> $b_required;
				}
				$title_a = remove_accents( (string) $a->post_title );
				$title_b = remove_accents( (string) $b->post_title );
				return strcasecmp( $title_a, $title_b );
			}
		);
	}

	$regiones = get_terms(
		array(
			'taxonomy'   => 'gn_region',
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		)
	);

	$current_url = esc_url_raw( remove_query_arg( array( 'gnf_msg', 'gnf_err' ) ) );

	// Lookup region name for prefill summary.
	$prefill_region_name = '';
	if ( $prefill['centro_region'] ) {
		$_rt = get_term( (int) $prefill['centro_region'], 'gn_region' );
		$prefill_region_name = ( $_rt && ! is_wp_error( $_rt ) ) ? $_rt->name : '';
	}
	$prefill_codigo_mep = $prefill['centro_codigo_mep'];

	ob_start();
	// SVG helpers.
	$svg_building = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01M16 6h.01M12 6h.01M12 10h.01M8 10h.01M16 10h.01"/></svg>';
	$svg_user     = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>';
	$svg_flag     = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>';
	$svg_target   = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>';
	$svg_check    = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>';
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gnf-matricula-form gnf-matricula-form--<?php echo esc_attr( $section ); ?>">
		<?php wp_nonce_field( 'gnf_submit_matricula', 'gnf_matricula_nonce' ); ?>
		<input type="hidden" name="action" value="gnf_submit_matricula" />
		<input type="hidden" name="redirect" value="<?php echo esc_attr( $current_url ); ?>" />
		<input type="hidden" name="anio" value="<?php echo esc_attr( $anio ); ?>" />
		<input type="hidden" name="gnf_form_section" value="<?php echo esc_attr( $section ); ?>" />

		<?php if ( ! $show_center_sections ) : ?>
			<input type="hidden" name="centro_existe" value="<?php echo esc_attr( (string) $prefill['centro_existe'] ); ?>" />
			<input type="hidden" name="centro_id_existente" value="<?php echo esc_attr( (int) $prefill['centro_id_existente'] ); ?>" />
			<input type="hidden" name="centro_nombre" value="<?php echo esc_attr( (string) $prefill['centro_nombre'] ); ?>" />
			<input type="hidden" name="centro_codigo_mep" value="<?php echo esc_attr( (string) $prefill['centro_codigo_mep'] ); ?>" />
			<input type="hidden" name="centro_correo_institucional" value="<?php echo esc_attr( (string) $prefill['centro_correo_institucional'] ); ?>" />
			<input type="hidden" name="centro_telefono" value="<?php echo esc_attr( (string) $prefill['centro_telefono'] ); ?>" />
			<input type="hidden" name="centro_nivel_educativo" value="<?php echo esc_attr( (string) $prefill['centro_nivel_educativo'] ); ?>" />
			<input type="hidden" name="centro_dependencia" value="<?php echo esc_attr( (string) $prefill['centro_dependencia'] ); ?>" />
			<input type="hidden" name="centro_jornada" value="<?php echo esc_attr( (string) $prefill['centro_jornada'] ); ?>" />
			<input type="hidden" name="centro_tipologia" value="<?php echo esc_attr( (string) $prefill['centro_tipologia'] ); ?>" />
			<input type="hidden" name="centro_tipo_centro_educativo" value="<?php echo esc_attr( (string) $prefill['centro_tipo_centro_educativo'] ); ?>" />
			<input type="hidden" name="centro_region" value="<?php echo esc_attr( (int) $prefill['centro_region'] ); ?>" />
			<input type="hidden" name="centro_circuito" value="<?php echo esc_attr( (string) $prefill['centro_circuito'] ); ?>" />
			<input type="hidden" name="centro_provincia" value="<?php echo esc_attr( (string) $prefill['centro_provincia'] ); ?>" />
			<input type="hidden" name="centro_canton" value="<?php echo esc_attr( (string) $prefill['centro_canton'] ); ?>" />
			<input type="hidden" name="centro_codigo_presupuestario" value="<?php echo esc_attr( (string) $prefill['centro_codigo_presupuestario'] ); ?>" />
			<input type="hidden" name="centro_direccion" value="<?php echo esc_attr( (string) $prefill['centro_direccion'] ); ?>" />
			<input type="hidden" name="centro_total_estudiantes" value="<?php echo esc_attr( (int) $prefill['centro_total_estudiantes'] ); ?>" />
			<input type="hidden" name="centro_estudiantes_hombres" value="<?php echo esc_attr( (int) $prefill['centro_estudiantes_hombres'] ); ?>" />
			<input type="hidden" name="centro_estudiantes_mujeres" value="<?php echo esc_attr( (int) $prefill['centro_estudiantes_mujeres'] ); ?>" />
			<input type="hidden" name="centro_estudiantes_migrantes" value="<?php echo esc_attr( (int) $prefill['centro_estudiantes_migrantes'] ); ?>" />
			<input type="hidden" name="centro_ultimo_galardon_estrellas" value="<?php echo esc_attr( (string) $prefill['centro_ultimo_galardon_estrellas'] ); ?>" />
			<input type="hidden" name="centro_ultimo_anio_participacion" value="<?php echo esc_attr( (string) $prefill['centro_ultimo_anio_participacion'] ); ?>" />
			<input type="hidden" name="centro_ultimo_anio_participacion_otro" value="<?php echo esc_attr( (string) $prefill['centro_ultimo_anio_participacion_otro'] ); ?>" />
			<input type="hidden" name="coordinador_cargo" value="<?php echo esc_attr( (string) $prefill['coordinador_cargo'] ); ?>" />
			<input type="hidden" name="coordinador_nombre" value="<?php echo esc_attr( (string) $prefill['coordinador_nombre'] ); ?>" />
			<input type="hidden" name="coordinador_celular" value="<?php echo esc_attr( (string) $prefill['coordinador_celular'] ); ?>" />
			<input type="hidden" name="docente_nombre" value="<?php echo esc_attr( (string) $prefill['docente_nombre'] ); ?>" />
			<input type="hidden" name="docente_cargo" value="<?php echo esc_attr( (string) $prefill['docente_cargo'] ); ?>" />
			<input type="hidden" name="docente_telefono" value="<?php echo esc_attr( (string) $prefill['docente_telefono'] ); ?>" />
			<input type="hidden" name="docente_email" value="<?php echo esc_attr( (string) $prefill['docente_email'] ); ?>" />
			<input type="hidden" name="docente_email_confirm" value="<?php echo esc_attr( (string) $prefill['docente_email_confirm'] ); ?>" />
			<input type="hidden" name="bae_comite_estudiantes" value="<?php echo esc_attr( (int) $prefill['bae_comite_estudiantes'] ); ?>" />
			<input type="hidden" name="bae_inscripcion_anterior" value="<?php echo esc_attr( (string) $prefill['bae_inscripcion_anterior'] ); ?>" />
		<?php endif; ?>

		<?php if ( ! $show_retos_section ) : ?>
			<input type="hidden" name="bae_meta_estrellas" value="<?php echo esc_attr( (string) $prefill['bae_meta_estrellas'] ); ?>" />
			<?php foreach ( (array) $prefill['bae_retos_seleccionados'] as $reto_prefill_id ) : ?>
				<input type="hidden" name="bae_retos_seleccionados[]" value="<?php echo esc_attr( (int) $reto_prefill_id ); ?>" />
			<?php endforeach; ?>
		<?php endif; ?>

		<?php if ( $show_center_sections ) : ?>
		<!-- ── SECCIÓN 1: Centro Educativo ─────────────────── -->
		<div class="gnf-mf-section">
			<div class="gnf-mf-section__header">
				<div class="gnf-mf-section__icon gnf-mf-section__icon--ocean"><?php echo $svg_building; // phpcs:ignore ?></div>
				<div>
					<p class="gnf-mf-section__title">Centro Educativo</p>
					<p class="gnf-mf-section__subtitle">Información del centro que inscribirá la Bandera Azul</p>
				</div>
			</div>
			<div class="gnf-mf-section__body">

				<?php if ( $centro_id ) : ?>
					<!-- Centro ya asociado: mostrar resumen bloqueado -->
					<input type="hidden" name="centro_existe" value="Sí" />
					<input type="hidden" name="centro_id_existente" value="<?php echo esc_attr( $centro_id ); ?>" />
					<div class="gnf-centro-locked-summary">
						<div class="gnf-centro-locked-summary__icon"><?php echo $svg_building; // phpcs:ignore ?></div>
						<div>
							<div class="gnf-centro-locked-summary__name"><?php echo esc_html( get_the_title( $centro_id ) ); ?></div>
							<div class="gnf-centro-locked-summary__meta">
								<?php if ( $prefill_codigo_mep ) : ?>
									<span>Código MEP: <strong><?php echo esc_html( $prefill_codigo_mep ); ?></strong></span>
								<?php endif; ?>
								<?php if ( $prefill_region_name ) : ?>
									&nbsp;·&nbsp; <span><?php echo esc_html( $prefill_region_name ); ?></span>
								<?php endif; ?>
							</div>
						</div>
					</div>

				<?php else : ?>
					<!-- Sin centro: selector ¿existe? con búsqueda o nuevo -->
					<div class="gnf-mf-field" style="margin-bottom:var(--gnf-space-4);">
						<label class="gnf-mf-label">¿Tu centro educativo ya existe en el sistema? <span class="req">*</span></label>
						<select name="centro_existe" class="gnf-mf-select" id="gnf-mat-centro-existe" style="max-width:280px;">
							<option value="Sí" <?php selected( $prefill['centro_existe'], 'Sí' ); ?>>Sí, ya está registrado</option>
							<option value="No" <?php selected( $prefill['centro_existe'], 'No' ); ?>>No, voy a registrarlo</option>
						</select>
					</div>

					<!-- Si existe: buscador AJAX -->
					<div id="gnf-mat-existente-wrap" style="<?php echo 'Sí' === $prefill['centro_existe'] ? '' : 'display:none;'; ?>">
						<input type="hidden" name="centro_id_existente" id="gnf-mat-centro-id" value="<?php echo esc_attr( (int) $prefill['centro_id_existente'] ); ?>" />
						<div class="gnf-mf-field">
							<label class="gnf-mf-label">Buscar centro educativo <span class="req">*</span></label>
							<div class="gnf-centro-search-wrapper" style="max-width:480px;">
								<input type="text" id="gnf-mat-centro-search" class="gnf-mf-input gnf-mat-centro-search-input"
									placeholder="Escriba el nombre o código MEP…" autocomplete="off" />
								<div class="gnf-centro-search-icon">
									<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
								</div>
								<div id="gnf-mat-centro-results" class="gnf-centro-results"></div>
							</div>
							<p class="gnf-mf-hint">Escribe al menos 2 caracteres para buscar</p>
						</div>
						<div id="gnf-mat-centro-preview" class="gnf-centro-preview" style="display:none;margin-top:12px;max-width:480px;">
							<div class="gnf-centro-preview__header">
								<span class="gnf-centro-preview__icon">✅</span>
								<span class="gnf-centro-preview__title">Centro seleccionado</span>
								<button type="button" id="gnf-mat-centro-clear" class="gnf-centro-preview__clear" title="Cambiar">✕</button>
							</div>
							<div class="gnf-centro-preview__body">
								<div class="gnf-centro-preview__name" id="gnf-mat-preview-name"></div>
								<div class="gnf-centro-preview__meta">
									<span id="gnf-mat-preview-codigo"></span>
									<span id="gnf-mat-preview-region"></span>
								</div>
							</div>
						</div>
					</div>

					<!-- Si es nuevo: grilla de campos -->
					<div id="gnf-mat-nuevo-wrap" style="<?php echo 'No' === $prefill['centro_existe'] ? '' : 'display:none;'; ?>">
						<div class="gnf-mf-grid" style="margin-top:var(--gnf-space-2);">
							<div class="gnf-mf-field gnf-mf-field--full">
								<label class="gnf-mf-label">Nombre del Centro <span class="req">*</span></label>
								<input type="text" name="centro_nombre" class="gnf-mf-input" value="<?php echo esc_attr( $prefill['centro_nombre'] ); ?>" />
							</div>
							<div class="gnf-mf-field">
								<label class="gnf-mf-label">Código MEP <span class="req">*</span></label>
								<input type="text" name="centro_codigo_mep" class="gnf-mf-input" value="<?php echo esc_attr( $prefill['centro_codigo_mep'] ); ?>" placeholder="Ej. 1234" />
							</div>
							<div class="gnf-mf-field">
								<label class="gnf-mf-label">Correo institucional <span class="req">*</span></label>
								<input type="email" name="centro_correo_institucional" class="gnf-mf-input" value="<?php echo esc_attr( $prefill['centro_correo_institucional'] ); ?>" placeholder="centro@dominio.ed.cr" readonly />
							</div>
							<div class="gnf-mf-field">
								<label class="gnf-mf-label">Teléfono del Centro <span class="req">*</span></label>
								<input type="text" name="centro_telefono" class="gnf-mf-input" value="<?php echo esc_attr( $prefill['centro_telefono'] ); ?>" placeholder="2200-0000" />
							</div>
							<div class="gnf-mf-field">
								<label class="gnf-mf-label">Dirección Regional <span class="req">*</span></label>
								<select name="centro_region" class="gnf-mf-select">
									<option value="">— Seleccione —</option>
									<?php if ( ! is_wp_error( $regiones ) ) : ?>
										<?php foreach ( $regiones as $region ) : ?>
											<option value="<?php echo esc_attr( $region->term_id ); ?>" <?php selected( (int) $prefill['centro_region'], (int) $region->term_id ); ?>><?php echo esc_html( $region->name ); ?></option>
										<?php endforeach; ?>
									<?php endif; ?>
								</select>
							</div>
							<div class="gnf-mf-field">
								<label class="gnf-mf-label">Circuito Educativo <span class="req">*</span></label>
								<input type="text" name="centro_circuito" class="gnf-mf-input" value="<?php echo esc_attr( $prefill['centro_circuito'] ); ?>" placeholder="Ej. 01-02" />
							</div>
							<div class="gnf-mf-field">
								<label class="gnf-mf-label">Provincia <span class="req">*</span></label>
								<select id="gnf-centro-provincia" name="centro_provincia" class="gnf-mf-select">
									<option value="">— Seleccione —</option>
									<?php foreach ( $provincias as $provincia ) : ?>
										<option value="<?php echo esc_attr( $provincia ); ?>" <?php selected( $prefill['centro_provincia'], $provincia ); ?>><?php echo esc_html( $provincia ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="gnf-mf-field">
								<label class="gnf-mf-label">Cantón <span class="req">*</span></label>
								<select id="gnf-centro-canton" name="centro_canton" class="gnf-mf-select" data-selected="<?php echo esc_attr( $prefill['centro_canton'] ); ?>">
									<option value="">— Seleccione —</option>
									<?php foreach ( gnf_get_cr_cantons_by_province( $prefill['centro_provincia'] ) as $canton ) : ?>
										<option value="<?php echo esc_attr( $canton ); ?>" <?php selected( $prefill['centro_canton'], $canton ); ?>><?php echo esc_html( $canton ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="gnf-mf-field">
								<label class="gnf-mf-label">Código Presupuestario <small style="font-weight:400;">(públicos)</small></label>
								<input type="text" name="centro_codigo_presupuestario" class="gnf-mf-input" value="<?php echo esc_attr( $prefill['centro_codigo_presupuestario'] ); ?>" />
							</div>
							<div class="gnf-mf-field gnf-mf-field--full">
								<label class="gnf-mf-label">Dirección exacta <span class="req">*</span></label>
								<textarea name="centro_direccion" rows="2" class="gnf-mf-textarea"><?php echo esc_textarea( $prefill['centro_direccion'] ); ?></textarea>
							</div>
						</div>
					</div>
				<?php endif; ?>

			</div>
		</div>

		<!-- ── SECCIÓN 2a: Perfil Institucional ── -->
		<div class="gnf-mf-section">
			<div class="gnf-mf-section__header">
				<div class="gnf-mf-section__icon gnf-mf-section__icon--forest"><?php echo $svg_user; // phpcs:ignore ?></div>
				<div>
					<p class="gnf-mf-section__title">Perfil Institucional</p>
					<p class="gnf-mf-section__subtitle">Clasificación del centro y población estudiantil</p>
				</div>
			</div>
			<div class="gnf-mf-section__body">
				<input type="hidden" name="centro_tipologia" value="<?php echo esc_attr( $prefill['centro_tipologia'] ); ?>" />
				<div class="gnf-mf-grid gnf-mf-grid--4">
					<div class="gnf-mf-field">
						<label class="gnf-mf-label">Nivel educativo <span class="req">*</span></label>
						<select name="centro_nivel_educativo" class="gnf-mf-select">
							<option value="">— Seleccione —</option>
							<?php foreach ( $choices['nivel_educativo'] as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $prefill['centro_nivel_educativo'], $key ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="gnf-mf-field">
						<label class="gnf-mf-label">Dependencia <span class="req">*</span></label>
						<select name="centro_dependencia" class="gnf-mf-select">
							<option value="">— Seleccione —</option>
							<?php foreach ( $choices['dependencia'] as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $prefill['centro_dependencia'], $key ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="gnf-mf-field">
						<label class="gnf-mf-label">Jornada <span class="req">*</span></label>
						<select name="centro_jornada" class="gnf-mf-select">
							<option value="">— Seleccione —</option>
							<?php foreach ( $choices['jornada'] as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $prefill['centro_jornada'], $key ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="gnf-mf-field">
						<label class="gnf-mf-label">Tipo de Centro Educativo</label>
						<select name="centro_tipo_centro_educativo" class="gnf-mf-select">
							<option value="">— Seleccione —</option>
							<?php foreach ( $choices['tipo_centro_educativo'] as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $prefill['centro_tipo_centro_educativo'], $key ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="gnf-mf-field">
						<label class="gnf-mf-label">Total de estudiantes <span class="req">*</span></label>
						<input type="number" min="0" name="centro_total_estudiantes" class="gnf-mf-input" value="<?php echo esc_attr( (int) $prefill['centro_total_estudiantes'] ); ?>" />
					</div>
					<div class="gnf-mf-field">
						<label class="gnf-mf-label">Hombres <span class="req">*</span></label>
						<input type="number" min="0" name="centro_estudiantes_hombres" class="gnf-mf-input" value="<?php echo esc_attr( (int) $prefill['centro_estudiantes_hombres'] ); ?>" />
					</div>
					<div class="gnf-mf-field">
						<label class="gnf-mf-label">Mujeres <span class="req">*</span></label>
						<input type="number" min="0" name="centro_estudiantes_mujeres" class="gnf-mf-input" value="<?php echo esc_attr( (int) $prefill['centro_estudiantes_mujeres'] ); ?>" />
					</div>
					<div class="gnf-mf-field">
						<label class="gnf-mf-label">Estudiantes migrantes</label>
						<input type="number" min="0" name="centro_estudiantes_migrantes" class="gnf-mf-input" value="<?php echo esc_attr( (int) $prefill['centro_estudiantes_migrantes'] ); ?>" />
					</div>
				</div>
			</div>
		</div>

		<!-- ── SECCIÓN 2b: Participación y Coordinación ── -->
		<div class="gnf-mf-section">
			<div class="gnf-mf-section__header">
				<div class="gnf-mf-section__icon gnf-mf-section__icon--forest"><?php echo $svg_user; // phpcs:ignore ?></div>
				<div>
					<p class="gnf-mf-section__title">Participación y Coordinación</p>
					<p class="gnf-mf-section__subtitle">Historial PBAE y persona coordinadora</p>
				</div>
			</div>
			<div class="gnf-mf-section__body">
				<div class="gnf-mf-grid gnf-mf-grid--4">
					<div class="gnf-mf-field">
						<label class="gnf-mf-label">Último galardón logrado <span class="req">*</span></label>
						<select name="centro_ultimo_galardon_estrellas" class="gnf-mf-select">
							<?php foreach ( $choices['ultimo_galardon_estrellas'] as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( (string) $prefill['centro_ultimo_galardon_estrellas'], (string) $key ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="gnf-mf-field">
						<label class="gnf-mf-label">Último año de participación <span class="req">*</span></label>
						<select id="gnf-centro-ultimo-anio" name="centro_ultimo_anio_participacion" class="gnf-mf-select">
							<?php foreach ( $choices['ultimo_anio_participacion'] as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( (string) $prefill['centro_ultimo_anio_participacion'], (string) $key ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="gnf-mf-field" id="gnf-centro-ultimo-anio-otro-wrap" style="<?php echo 'otro' === (string) $prefill['centro_ultimo_anio_participacion'] ? '' : 'display:none;'; ?>">
						<label class="gnf-mf-label">Especifica el año</label>
						<input type="number" min="1900" max="<?php echo esc_attr( gmdate( 'Y' ) ); ?>" name="centro_ultimo_anio_participacion_otro" class="gnf-mf-input" value="<?php echo esc_attr( $prefill['centro_ultimo_anio_participacion_otro'] ); ?>" />
					</div>
					<div class="gnf-mf-field">
						<label class="gnf-mf-label">Cargo coordinador(a) PBAE <span class="req">*</span></label>
						<select id="gnf-coordinador-cargo" name="coordinador_cargo" class="gnf-mf-select">
							<option value="">— Seleccione —</option>
							<?php foreach ( $choices['coordinador_cargo'] as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( (string) $prefill['coordinador_cargo'], (string) $key ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="gnf-mf-field" id="gnf-coordinador-nombre-wrap" style="<?php echo 'director' === (string) $prefill['coordinador_cargo'] ? 'display:none;' : ''; ?>">
						<label class="gnf-mf-label">Nombre del coordinador(a)</label>
						<input type="text" id="gnf-coordinador-nombre" name="coordinador_nombre" class="gnf-mf-input" value="<?php echo esc_attr( $prefill['coordinador_nombre'] ); ?>" />
					</div>
					<div class="gnf-mf-field">
						<label class="gnf-mf-label">Celular coordinador(a) <span class="req">*</span></label>
						<input type="text" name="coordinador_celular" class="gnf-mf-input" value="<?php echo esc_attr( $prefill['coordinador_celular'] ); ?>" placeholder="8888-0000" />
					</div>
				</div>
			</div>
		</div>

		<!-- ── SECCIÓN 2: Datos del Inscriptor ─────────────── -->
		<div class="gnf-mf-section">
			<div class="gnf-mf-section__header">
				<div class="gnf-mf-section__icon gnf-mf-section__icon--forest"><?php echo $svg_user; // phpcs:ignore ?></div>
				<div>
					<p class="gnf-mf-section__title">Datos de quien inscribe</p>
					<p class="gnf-mf-section__subtitle">Docente, director o encargado autorizado</p>
				</div>
			</div>
			<div class="gnf-mf-section__body">
				<div class="gnf-mf-grid">
						<div class="gnf-mf-field">
							<label class="gnf-mf-label">Nombre completo <span class="req">*</span></label>
							<input type="text" id="gnf-docente-nombre" name="docente_nombre" class="gnf-mf-input" value="<?php echo esc_attr( $prefill['docente_nombre'] ); ?>" required />
						</div>
					<div class="gnf-mf-field">
						<label class="gnf-mf-label">Cargo en el Centro <span class="req">*</span></label>
						<input type="text" name="docente_cargo" class="gnf-mf-input" value="<?php echo esc_attr( $prefill['docente_cargo'] ); ?>" required placeholder="Ej. Docente de Ciencias" />
					</div>
					<div class="gnf-mf-field">
						<label class="gnf-mf-label">Teléfono personal <span class="req">*</span></label>
						<input type="text" name="docente_telefono" class="gnf-mf-input" value="<?php echo esc_attr( $prefill['docente_telefono'] ); ?>" required placeholder="8888-0000" />
					</div>
					<div class="gnf-mf-field">
						<label class="gnf-mf-label">Correo electrónico <span class="req">*</span></label>
						<input type="email" name="docente_email" class="gnf-mf-input" value="<?php echo esc_attr( $prefill['docente_email'] ); ?>" required />
					</div>
					<div class="gnf-mf-field gnf-mf-field--full">
						<label class="gnf-mf-label">Confirmar correo electrónico</label>
						<input type="email" name="docente_email_confirm" class="gnf-mf-input" value="<?php echo esc_attr( $prefill['docente_email_confirm'] ); ?>" style="max-width:360px;" />
					</div>
				</div>
				<div class="gnf-mf-check-group" style="margin-top:var(--gnf-space-4);">
					<label class="gnf-mf-check-row">
						<input type="checkbox" name="docente_confirmaciones[]" value="autorizado" checked />
						Confirmo que soy docente, director o encargado autorizado para inscribir este centro en el programa.
					</label>
					<label class="gnf-mf-check-row">
						<input type="checkbox" name="docente_confirmaciones[]" value="terminos" checked />
						Acepto los términos y condiciones del programa Bandera Azul Ecológica.
					</label>
				</div>
			</div>
		</div>

		<!-- ── SECCIÓN 3: Datos Bandera Azul ───────────────── -->
		<div class="gnf-mf-section">
			<div class="gnf-mf-section__header">
				<div class="gnf-mf-section__icon gnf-mf-section__icon--sun"><?php echo $svg_flag; // phpcs:ignore ?></div>
				<div>
					<p class="gnf-mf-section__title">Datos Bandera Azul Ecológica</p>
					<p class="gnf-mf-section__subtitle">Información del programa para el año <?php echo esc_html( $anio ); ?></p>
				</div>
			</div>
			<div class="gnf-mf-section__body">
				<div class="gnf-mf-grid">
					<div class="gnf-mf-field">
						<label class="gnf-mf-label">Estudiantes en el Comité BAE <span class="req">*</span></label>
						<input type="number" min="1" step="1" name="bae_comite_estudiantes"
							class="gnf-mf-input"
							value="<?php echo esc_attr( max( 1, (int) $prefill['bae_comite_estudiantes'] ) ); ?>"
							required />
						<p class="gnf-mf-hint">Número de estudiantes que integrarán el Comité BAE</p>
					</div>
					<div class="gnf-mf-field">
						<label class="gnf-mf-label">¿Participaron en Bandera Azul anteriormente? <span class="req">*</span></label>
						<div style="display:flex;gap:var(--gnf-space-3);margin-top:4px;">
							<label style="display:flex;align-items:center;gap:6px;font-weight:500;cursor:pointer;">
								<input type="radio" name="bae_inscripcion_anterior" value="Sí" <?php checked( $prefill['bae_inscripcion_anterior'], 'Sí' ); ?> style="accent-color:var(--gnf-forest);width:16px;height:16px;" /> Sí
							</label>
							<label style="display:flex;align-items:center;gap:6px;font-weight:500;cursor:pointer;">
								<input type="radio" name="bae_inscripcion_anterior" value="No" <?php checked( $prefill['bae_inscripcion_anterior'], 'No' ); ?> style="accent-color:var(--gnf-forest);width:16px;height:16px;" /> No
							</label>
						</div>
					</div>
				</div>
			</div>
		</div>

		<?php endif; ?>
		<?php if ( $show_retos_section ) : ?>
		<!-- ── SECCIÓN 4: Meta + Eco Retos ─────────────────── -->
		<div class="gnf-mf-section">
			<div class="gnf-mf-section__header">
				<div class="gnf-mf-section__icon gnf-mf-section__icon--coral"><?php echo $svg_target; // phpcs:ignore ?></div>
				<div>
					<p class="gnf-mf-section__title">Matrícula de Eco Retos</p>
					<p class="gnf-mf-section__subtitle">Selecciona tu meta de estrellas y los retos que realizarás</p>
				</div>
			</div>
			<div class="gnf-mf-section__body">
				<!-- Meta de estrellas -->
				<div class="gnf-mf-field" style="margin-bottom:var(--gnf-space-6);">
					<label class="gnf-mf-label">¿Cuántas estrellas se proponen alcanzar este año? <span class="req">*</span></label>
					<div class="gnf-star-selector" style="margin-top:var(--gnf-space-2);">
						<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
							<?php
							$star_val   = $i . ' estrella' . ( $i > 1 ? 's' : '' );
							$star_label = str_repeat( '★', $i ) . str_repeat( '☆', 5 - $i );
							?>
							<label class="gnf-star-option">
								<input type="radio" name="bae_meta_estrellas"
									value="<?php echo esc_attr( $star_val ); ?>"
									<?php checked( $prefill['bae_meta_estrellas'], $star_val ); ?> />
								<span class="gnf-star-option__label">
									<span style="color:var(--gnf-sun);letter-spacing:1px;"><?php echo esc_html( $star_label ); ?></span>
									<span style="font-size:0.85em;"><?php echo esc_html( $i ); ?></span>
								</span>
							</label>
						<?php endfor; ?>
					</div>
				</div>

				<!-- Selección de retos -->
				<div class="gnf-mf-field">
					<label class="gnf-mf-label" style="margin-bottom:var(--gnf-space-3);">
						Selecciona los eco retos que realizarán durante el año <span class="req">*</span>
					</label>
					<?php if ( $retos_q->have_posts() ) : ?>
						<div class="gnf-reto-selector-grid">
							<?php
							while ( $retos_q->have_posts() ) :
								$retos_q->the_post();
							$reto_id     = get_the_ID();
							$is_required = in_array( $reto_id, $required_roots, true );
							$checked     = $is_required || in_array( $reto_id, (array) $prefill['bae_retos_seleccionados'], true );
							$anio_mat = isset($anio) ? $anio : gnf_get_active_year();
							$puntaje_max = gnf_get_reto_max_points( $reto_id, $anio_mat );
							$reto_icon   = gnf_get_reto_icon_url( $reto_id );
								?>
								<label class="gnf-reto-selector-card<?php echo $is_required ? ' gnf-reto-selector-card--required' : ''; ?>"
									<?php if ( $is_required ) : ?>title="Este eco reto es obligatorio y no se puede desmarcar"<?php endif; ?>>
									<input type="checkbox"
										name="bae_retos_seleccionados[]"
										value="<?php echo esc_attr( $reto_id ); ?>"
										<?php checked( $checked ); ?>
										<?php disabled( $is_required ); ?> />
									<?php if ( $is_required ) : ?>
										<input type="hidden" name="bae_retos_seleccionados[]" value="<?php echo esc_attr( $reto_id ); ?>" />
									<?php endif; ?>
									<span class="gnf-reto-selector-card__inner">
										<?php if ( $is_required ) : ?>
											<span class="gnf-reto-card-required-ribbon">Requisitos</span>
										<?php endif; ?>
										<?php if ( $reto_icon ) : ?>
											<span class="gnf-reto-card-icon">
												<img src="<?php echo esc_url( $reto_icon ); ?>" alt="" />
											</span>
										<?php endif; ?>
										<span style="display:flex;align-items:center;gap:8px;">
											<span class="gnf-reto-card-check"><?php echo $svg_check; // phpcs:ignore ?></span>
											<span class="gnf-reto-card-name"><?php the_title(); ?></span>
										</span>
										<span class="gnf-reto-card-pts"><?php echo esc_html( $puntaje_max ); ?> puntos</span>
									</span>
								</label>
							<?php endwhile; ?>
							<?php wp_reset_postdata(); ?>
						</div>
					<?php else : ?>
						<p class="gnf-muted">No hay retos publicados aún.</p>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php endif; ?>

		<!-- ── ENVÍO ───────────────────────────────────────── -->
		<div class="gnf-mf-submit">
			<button type="submit" class="gnf-btn gnf-btn--lg">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v14a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
				<?php if ( 'retos' === $section ) : ?>
					Guardar matrícula de eco retos <?php echo esc_html( $anio ); ?>
				<?php elseif ( 'centro' === $section ) : ?>
					Guardar datos del centro <?php echo esc_html( $anio ); ?>
				<?php else : ?>
					Guardar matrícula <?php echo esc_html( $anio ); ?>
				<?php endif; ?>
			</button>
			<p class="gnf-muted" style="margin:0;">Puedes editar tu matrícula en cualquier momento antes del cierre del año.</p>
		</div>
	</form>

	<script>
	(function(){
		var provinciaCantones = <?php echo wp_json_encode( $cantones_map, JSON_UNESCAPED_UNICODE ); ?>;
		function normalizeValue(value) {
			return (value || '').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
		}
		function getCantonesByProvincia(provincia) {
			var normalized = normalizeValue(provincia);
			for (var provinceName in provinciaCantones) {
				if (normalizeValue(provinceName) === normalized) {
					return provinciaCantones[provinceName] || [];
				}
			}
			return [];
		}
		function fillCantones(provinciaSelect, cantonSelect, selected) {
			if (!provinciaSelect || !cantonSelect) return;
			var cantones = getCantonesByProvincia(provinciaSelect.value);
			var current = selected || cantonSelect.getAttribute('data-selected') || '';
			cantonSelect.innerHTML = '<option value="">— Seleccione —</option>';
			cantones.forEach(function(canton) {
				var option = document.createElement('option');
				option.value = canton;
				option.textContent = canton;
				if (normalizeValue(current) === normalizeValue(canton)) {
					option.selected = true;
				}
				cantonSelect.appendChild(option);
			});
		}

		// Toggle existente / nuevo
		var selExiste = document.getElementById('gnf-mat-centro-existe');
		if (selExiste) {
			var wrapEx = document.getElementById('gnf-mat-existente-wrap');
			var wrapNw = document.getElementById('gnf-mat-nuevo-wrap');
			function toggleCentro() {
				var isSi = selExiste.value === 'Sí';
				if (wrapEx) wrapEx.style.display = isSi ? '' : 'none';
				if (wrapNw) wrapNw.style.display = isSi ? 'none' : '';
			}
			selExiste.addEventListener('change', toggleCentro);
			toggleCentro();
		}

		var provinciaSelect = document.getElementById('gnf-centro-provincia');
		var cantonSelect = document.getElementById('gnf-centro-canton');
		if (provinciaSelect && cantonSelect) {
			fillCantones(provinciaSelect, cantonSelect, cantonSelect.value || cantonSelect.getAttribute('data-selected'));
			provinciaSelect.addEventListener('change', function() {
				fillCantones(provinciaSelect, cantonSelect, '');
			});
		}

		var anioParticipacionSelect = document.getElementById('gnf-centro-ultimo-anio');
		var anioOtroWrap = document.getElementById('gnf-centro-ultimo-anio-otro-wrap');
		if (anioParticipacionSelect && anioOtroWrap) {
			var toggleAnioOtro = function() {
				anioOtroWrap.style.display = anioParticipacionSelect.value === 'otro' ? '' : 'none';
			};
			anioParticipacionSelect.addEventListener('change', toggleAnioOtro);
			toggleAnioOtro();
		}

		var coordinadorCargo = document.getElementById('gnf-coordinador-cargo');
		var coordinadorNombreWrap = document.getElementById('gnf-coordinador-nombre-wrap');
		var coordinadorNombreInput = document.getElementById('gnf-coordinador-nombre');
		var docenteNombreInput = document.getElementById('gnf-docente-nombre');
		if (coordinadorCargo && coordinadorNombreWrap) {
			var toggleCoordinadorNombre = function() {
				var director = coordinadorCargo.value === 'director';
				coordinadorNombreWrap.style.display = director ? 'none' : '';
				if (director && coordinadorNombreInput && docenteNombreInput) {
					coordinadorNombreInput.value = docenteNombreInput.value || '';
				}
			};
			coordinadorCargo.addEventListener('change', toggleCoordinadorNombre);
			if (docenteNombreInput) {
				docenteNombreInput.addEventListener('input', function() {
					if (coordinadorCargo.value === 'director' && coordinadorNombreInput) {
						coordinadorNombreInput.value = docenteNombreInput.value || '';
					}
				});
			}
			toggleCoordinadorNombre();
		}

		// AJAX buscador de centros en matrícula
		var searchInput  = document.getElementById('gnf-mat-centro-search');
		var resultsBox   = document.getElementById('gnf-mat-centro-results');
		var hiddenId     = document.getElementById('gnf-mat-centro-id');
		var previewBox   = document.getElementById('gnf-mat-centro-preview');
		var clearBtn     = document.getElementById('gnf-mat-centro-clear');

		if (searchInput && resultsBox && hiddenId) {
			var timer;
			var ajaxUrl = (typeof gnfData !== 'undefined' && gnfData.ajaxUrl)
				? gnfData.ajaxUrl
				: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';

			searchInput.addEventListener('input', function() {
				var term = this.value.trim();
				clearTimeout(timer);
				if (term.length < 2) { resultsBox.classList.remove('is-visible'); return; }
				resultsBox.innerHTML = '<div class="gnf-centro-results__loading">Buscando…</div>';
				resultsBox.classList.add('is-visible');
				timer = setTimeout(function() {
					fetch(ajaxUrl + '?action=gnf_search_centros&term=' + encodeURIComponent(term))
						.then(function(r){ return r.json(); })
						.then(function(data) {
							if (!data || !data.length) {
								resultsBox.innerHTML = '<div class="gnf-centro-results__empty">No se encontraron centros</div>';
								return;
							}
							resultsBox.innerHTML = data.map(function(c){
								var cod = c.codigo_mep ? '<span class="gnf-centro-result-item__codigo">' + c.codigo_mep + '</span>' : '';
								return '<div class="gnf-centro-result-item" data-id="' + c.id + '" data-nombre="' + encodeURIComponent(c.value) + '" data-codigo="' + (c.codigo_mep||'') + '" data-region="' + (c.region_name||'') + '">' +
									'<div class="gnf-centro-result-item__name">' + c.value + '</div>' +
									'<div class="gnf-centro-result-item__meta">' + cod + (c.region_name||'') + '</div>' +
									'</div>';
							}).join('');
							resultsBox.querySelectorAll('.gnf-centro-result-item').forEach(function(item){
								item.addEventListener('click', function(){
									selectCentro(this.dataset.id, decodeURIComponent(this.dataset.nombre), this.dataset.codigo, this.dataset.region);
								});
							});
						})
						.catch(function(){ resultsBox.innerHTML = '<div class="gnf-centro-results__empty">Error al buscar. Inténtelo de nuevo.</div>'; });
				}, 300);
			});

			document.addEventListener('click', function(e){
				if (!searchInput.contains(e.target) && !resultsBox.contains(e.target)) resultsBox.classList.remove('is-visible');
			});

			function selectCentro(id, nombre, codigo, region) {
				hiddenId.value = id;
				searchInput.value = '';
				resultsBox.classList.remove('is-visible');
				if (previewBox) {
					document.getElementById('gnf-mat-preview-name').textContent = nombre;
					document.getElementById('gnf-mat-preview-codigo').textContent = codigo ? 'Código: ' + codigo : '';
					document.getElementById('gnf-mat-preview-region').textContent = region || '';
					previewBox.style.display = 'block';
					searchInput.closest('.gnf-mf-field').style.display = 'none';
				}
			}

			if (clearBtn) {
				clearBtn.addEventListener('click', function(){
					hiddenId.value = '';
					if (previewBox) previewBox.style.display = 'none';
					searchInput.closest('.gnf-mf-field').style.display = '';
					searchInput.focus();
				});
			}

			// Si ya hay un centro seleccionado, mostrar preview
			if (hiddenId.value && parseInt(hiddenId.value) > 0) {
				var existingName = searchInput ? searchInput.getAttribute('data-prefill-name') : '';
				// mostramos el preview usando los datos del elemento oculto
				// El servidor no pasa el nombre aquí, así que no hay más que hacer sin otro field
			}
		}
	})();
	</script>
	<?php

	return ob_get_clean();
}

/**
 * Procesa envío del formulario de matricula nativo.
 */
function gnf_handle_submit_matricula() {
	if ( ! is_user_logged_in() ) {
		wp_die( 'No autorizado.' );
	}

	check_admin_referer( 'gnf_submit_matricula', 'gnf_matricula_nonce' );

	$user_id  = get_current_user_id();
	$redirect = ! empty( $_POST['redirect'] ) ? esc_url_raw( wp_unslash( $_POST['redirect'] ) ) : home_url();

	$centro_existe = sanitize_text_field( wp_unslash( $_POST['centro_existe'] ?? 'Sí' ) );
	$centro_existe = ( 'No' === $centro_existe ) ? 'No' : 'Sí';
	$anio_context  = gnf_normalize_year( absint( $_POST['anio'] ?? 0 ) );

	$retos = array_map( 'absint', (array) ( $_POST['bae_retos_seleccionados'] ?? array() ) );
	$retos = array_filter( $retos );

	$required_roots = gnf_collapse_retos_a_raiz( gnf_get_obligatorio_reto_ids() );
	$retos          = array_values( array_unique( array_merge( $required_roots, $retos ) ) );
	$retos          = gnf_filter_reto_ids_by_year_form( $retos, $anio_context );
	$retos          = gnf_sort_reto_ids_required_first( $retos );

	$docente_email   = sanitize_email( wp_unslash( $_POST['docente_email'] ?? '' ) );
	$email_confirm   = sanitize_email( wp_unslash( $_POST['docente_email_confirm'] ?? '' ) );
	$selected_centro = absint( $_POST['centro_id_existente'] ?? 0 );

	if ( empty( $retos ) ) {
		wp_safe_redirect( add_query_arg( 'gnf_err', rawurlencode( 'Debes seleccionar al menos un reto.' ), $redirect ) );
		exit;
	}

	if ( $email_confirm && strtolower( $docente_email ) !== strtolower( $email_confirm ) ) {
		wp_safe_redirect( add_query_arg( 'gnf_err', rawurlencode( 'La confirmación de correo no coincide.' ), $redirect ) );
		exit;
	}

	// Validar acceso de centro en matrícula "existente" para evitar cambios arbitrarios.
	if ( 'Sí' === $centro_existe ) {
		$centro_asociado  = gnf_get_centro_for_docente( $user_id );
		$centro_solicitado = (int) get_user_meta( $user_id, 'centro_solicitado', true );
		$is_valid_centro = false;
		if ( $selected_centro > 0 ) {
			// Caso onboarding libre: si no tiene centro asociado/solicitado aún, permitir selección válida.
			if ( ! $centro_asociado && ! $centro_solicitado && 'centro_educativo' === get_post_type( $selected_centro ) ) {
				$is_valid_centro = true;
			}
			if ( $selected_centro === (int) $centro_asociado || $selected_centro === $centro_solicitado ) {
				$is_valid_centro = true;
			}
			if ( ! $is_valid_centro && current_user_can( 'manage_options' ) ) {
				$is_valid_centro = true;
			}
		}
		if ( ! $is_valid_centro ) {
			wp_safe_redirect( add_query_arg( 'gnf_err', rawurlencode( 'Centro educativo inválido para tu cuenta.' ), $redirect ) );
			exit;
		}
	}

	$ultimo_anio_participacion = sanitize_text_field( wp_unslash( $_POST['centro_ultimo_anio_participacion'] ?? '2025' ) );
	if ( ! in_array( $ultimo_anio_participacion, array( '2025', '2024', 'otro' ), true ) ) {
		$ultimo_anio_participacion = '2025';
	}

	$normalized = array(
		'centro-existe'                 => $centro_existe,
		'centro-id-existente'           => $selected_centro,
		'centro-nombre'                 => sanitize_text_field( wp_unslash( $_POST['centro_nombre'] ?? '' ) ),
		'centro-codigo-mep'             => sanitize_text_field( wp_unslash( $_POST['centro_codigo_mep'] ?? '' ) ),
		'centro-correo-institucional'   => sanitize_email( wp_unslash( $_POST['centro_correo_institucional'] ?? '' ) ),
		'centro-telefono'               => sanitize_text_field( wp_unslash( $_POST['centro_telefono'] ?? '' ) ),
		'centro-nivel-educativo'        => gnf_normalize_centro_choice( 'nivel_educativo', sanitize_text_field( wp_unslash( $_POST['centro_nivel_educativo'] ?? '' ) ) ),
		'centro-dependencia'            => gnf_normalize_centro_choice( 'dependencia', sanitize_text_field( wp_unslash( $_POST['centro_dependencia'] ?? '' ) ) ),
		'centro-jornada'                => gnf_normalize_centro_choice( 'jornada', sanitize_text_field( wp_unslash( $_POST['centro_jornada'] ?? '' ) ) ),
		'centro-tipologia'              => gnf_normalize_centro_choice( 'tipologia', sanitize_text_field( wp_unslash( $_POST['centro_tipologia'] ?? '' ) ) ),
		'centro-tipo-centro-educativo'  => gnf_normalize_centro_choice( 'tipo_centro_educativo', sanitize_text_field( wp_unslash( $_POST['centro_tipo_centro_educativo'] ?? '' ) ) ),
		'centro-region'                 => absint( $_POST['centro_region'] ?? 0 ),
		'centro-circuito'               => sanitize_text_field( wp_unslash( $_POST['centro_circuito'] ?? '' ) ),
		'centro-provincia'              => sanitize_text_field( wp_unslash( $_POST['centro_provincia'] ?? '' ) ),
		'centro-canton'                 => sanitize_text_field( wp_unslash( $_POST['centro_canton'] ?? '' ) ),
		'centro-codigo-presupuestario'  => sanitize_text_field( wp_unslash( $_POST['centro_codigo_presupuestario'] ?? '' ) ),
		'centro-direccion'              => sanitize_textarea_field( wp_unslash( $_POST['centro_direccion'] ?? '' ) ),
		'centro-total-estudiantes'      => absint( $_POST['centro_total_estudiantes'] ?? 0 ),
		'centro-estudiantes-hombres'    => absint( $_POST['centro_estudiantes_hombres'] ?? 0 ),
		'centro-estudiantes-mujeres'    => absint( $_POST['centro_estudiantes_mujeres'] ?? 0 ),
		'centro-estudiantes-migrantes'  => absint( $_POST['centro_estudiantes_migrantes'] ?? 0 ),
		'centro-ultimo-galardon-estrellas' => (string) max( 1, min( 5, absint( $_POST['centro_ultimo_galardon_estrellas'] ?? 1 ) ) ),
		'centro-ultimo-anio-participacion' => $ultimo_anio_participacion,
		'centro-ultimo-anio-participacion-otro' => absint( $_POST['centro_ultimo_anio_participacion_otro'] ?? 0 ),
		'coordinador-cargo'             => gnf_normalize_centro_choice( 'coordinador_cargo', sanitize_text_field( wp_unslash( $_POST['coordinador_cargo'] ?? '' ) ) ),
		'coordinador-nombre'            => sanitize_text_field( wp_unslash( $_POST['coordinador_nombre'] ?? '' ) ),
		'coordinador-celular'           => sanitize_text_field( wp_unslash( $_POST['coordinador_celular'] ?? '' ) ),
		'docente-nombre'                => sanitize_text_field( wp_unslash( $_POST['docente_nombre'] ?? '' ) ),
		'docente-cargo'                 => sanitize_text_field( wp_unslash( $_POST['docente_cargo'] ?? '' ) ),
		'docente-telefono'              => sanitize_text_field( wp_unslash( $_POST['docente_telefono'] ?? '' ) ),
		'docente-email'                 => $docente_email,
		'docente-email-confirm'         => $email_confirm,
		'docente-confirmaciones'        => array_map( 'sanitize_text_field', (array) ( $_POST['docente_confirmaciones'] ?? array() ) ),
		'anio'                          => $anio_context,
		'bae-comite-estudiantes'        => absint( $_POST['bae_comite_estudiantes'] ?? 0 ),
		'bae-inscripcion-anterior'      => sanitize_text_field( wp_unslash( $_POST['bae_inscripcion_anterior'] ?? 'No' ) ),
		'bae-meta-estrellas'            => sanitize_text_field( wp_unslash( $_POST['bae_meta_estrellas'] ?? '1 estrella' ) ),
		'bae-retos-seleccionados'       => $retos,
	);
	$normalized['centro-modalidad'] = $normalized['centro-nivel-educativo'];
	$normalized['centro-horario']   = $normalized['centro-jornada'];

	if ( 'Sí' === $centro_existe && empty( $normalized['centro-id-existente'] ) ) {
		wp_safe_redirect( add_query_arg( 'gnf_err', rawurlencode( 'Debes seleccionar un centro existente.' ), $redirect ) );
		exit;
	}

	if ( 'Sí' === $centro_existe && $selected_centro ) {
		if ( empty( $normalized['centro-provincia'] ) ) {
			$normalized['centro-provincia'] = (string) get_post_meta( $selected_centro, 'provincia', true );
		}
		if ( empty( $normalized['centro-canton'] ) ) {
			$normalized['centro-canton'] = (string) get_post_meta( $selected_centro, 'canton', true );
		}
		if ( empty( $normalized['centro-region'] ) ) {
			$normalized['centro-region'] = absint( get_post_meta( $selected_centro, 'region', true ) );
		}
		if ( empty( $normalized['centro-circuito'] ) ) {
			$normalized['centro-circuito'] = (string) get_post_meta( $selected_centro, 'circuito', true );
		}
		if ( empty( $normalized['centro-nombre'] ) ) {
			$normalized['centro-nombre'] = (string) get_the_title( $selected_centro );
		}
		if ( empty( $normalized['centro-codigo-mep'] ) ) {
			$normalized['centro-codigo-mep'] = (string) get_post_meta( $selected_centro, 'codigo_mep', true );
		}
		if ( empty( $normalized['centro-correo-institucional'] ) ) {
			$normalized['centro-correo-institucional'] = (string) get_post_meta( $selected_centro, 'correo_institucional', true );
		}
		if ( empty( $normalized['centro-telefono'] ) ) {
			$normalized['centro-telefono'] = (string) get_post_meta( $selected_centro, 'telefono', true );
		}
		if ( empty( $normalized['centro-nivel-educativo'] ) ) {
			$normalized['centro-nivel-educativo'] = gnf_normalize_centro_choice( 'nivel_educativo', (string) get_post_meta( $selected_centro, 'nivel_educativo', true ) );
		}
		if ( empty( $normalized['centro-dependencia'] ) ) {
			$normalized['centro-dependencia'] = gnf_normalize_centro_choice( 'dependencia', (string) get_post_meta( $selected_centro, 'dependencia', true ) );
		}
		if ( empty( $normalized['centro-jornada'] ) ) {
			$normalized['centro-jornada'] = gnf_normalize_centro_choice( 'jornada', (string) get_post_meta( $selected_centro, 'jornada', true ) );
		}
		if ( empty( $normalized['centro-tipologia'] ) ) {
			$normalized['centro-tipologia'] = gnf_normalize_centro_choice( 'tipologia', (string) get_post_meta( $selected_centro, 'tipologia', true ) );
		}
	}

	if ( 'director' === $normalized['coordinador-cargo'] ) {
		$normalized['coordinador-nombre'] = (string) $normalized['docente-nombre'];
	}

	if ( 'No' === $centro_existe ) {
		$required = array(
			'centro-nombre',
			'centro-codigo-mep',
			'centro-correo-institucional',
			'centro-telefono',
			'centro-region',
			'centro-circuito',
			'centro-provincia',
			'centro-canton',
			'centro-direccion',
		);
		foreach ( $required as $req_key ) {
			if ( empty( $normalized[ $req_key ] ) ) {
				wp_safe_redirect( add_query_arg( 'gnf_err', rawurlencode( 'Completa todos los campos obligatorios del centro.' ), $redirect ) );
				exit;
				}
			}
		}

	$required_profile = array(
		'centro-nivel-educativo',
		'centro-dependencia',
		'centro-jornada',
		'centro-tipologia',
		'coordinador-cargo',
		'coordinador-celular',
	);
	foreach ( $required_profile as $req_key ) {
		if ( empty( $normalized[ $req_key ] ) ) {
			wp_safe_redirect( add_query_arg( 'gnf_err', rawurlencode( 'Completa todos los campos obligatorios del perfil del centro.' ), $redirect ) );
			exit;
		}
	}

	if ( ! gnf_is_valid_cr_province_canton( $normalized['centro-provincia'], $normalized['centro-canton'] ) ) {
		wp_safe_redirect( add_query_arg( 'gnf_err', rawurlencode( 'El cantón seleccionado no pertenece a la provincia elegida.' ), $redirect ) );
		exit;
	}

	if ( 'otro' === $normalized['centro-ultimo-anio-participacion'] && empty( $normalized['centro-ultimo-anio-participacion-otro'] ) ) {
		wp_safe_redirect( add_query_arg( 'gnf_err', rawurlencode( 'Debes indicar el último año de participación cuando eliges \"otro\".' ), $redirect ) );
		exit;
	}

	if ( 'director' !== $normalized['coordinador-cargo'] && empty( $normalized['coordinador-nombre'] ) ) {
		wp_safe_redirect( add_query_arg( 'gnf_err', rawurlencode( 'Debes indicar el nombre del coordinador(a) PBAE.' ), $redirect ) );
		exit;
	}

	$total = (int) $normalized['centro-total-estudiantes'];
	$h     = (int) $normalized['centro-estudiantes-hombres'];
	$m     = (int) $normalized['centro-estudiantes-mujeres'];
	if ( $h + $m > $total && $total > 0 ) {
		wp_safe_redirect( add_query_arg( 'gnf_err', rawurlencode( 'La suma de hombres y mujeres no puede superar el total de estudiantes.' ), $redirect ) );
		exit;
	}

	gnf_handle_matricula_submission(
		$normalized,
		0,
		array(
			'id'      => 0,
			'engine'  => 'acf',
			'source'  => 'frontend_matricula',
		)
	);

	$redirect = add_query_arg( 'tab', 'matricula', $redirect );
	$redirect = add_query_arg( 'gnf_msg', rawurlencode( 'Matrícula guardada correctamente.' ), $redirect );

	wp_safe_redirect( $redirect );
	exit;
}
add_action( 'admin_post_gnf_submit_matricula', 'gnf_handle_submit_matricula' );
