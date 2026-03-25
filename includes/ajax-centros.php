<?php
/**
 * AJAX para busqueda de centros educativos.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Busca centros educativos por nombre o codigo MEP.
 */
function gnf_ajax_search_centros() {
	$term = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';

	if ( strlen( $term ) < 2 ) {
		wp_send_json( array() );
	}

	global $wpdb;

	// Buscar por titulo o codigo MEP.
	$like_term = '%' . $wpdb->esc_like( $term ) . '%';

	$results = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT p.ID, p.post_title, 
					pm_codigo.meta_value as codigo_mep,
					pm_region.meta_value as region,
					pm_circuito.meta_value as circuito,
					pm_canton.meta_value as canton,
					pm_provincia.meta_value as provincia,
					pm_distrito.meta_value as distrito,
					pm_poblado.meta_value as poblado,
					pm_dependencia.meta_value as dependencia,
					pm_zona.meta_value as zona,
					pm_telefono.meta_value as telefono
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm_codigo ON p.ID = pm_codigo.post_id AND pm_codigo.meta_key = 'codigo_mep'
			LEFT JOIN {$wpdb->postmeta} pm_region ON p.ID = pm_region.post_id AND pm_region.meta_key = 'region'
			LEFT JOIN {$wpdb->postmeta} pm_circuito ON p.ID = pm_circuito.post_id AND pm_circuito.meta_key = 'circuito'
			LEFT JOIN {$wpdb->postmeta} pm_canton ON p.ID = pm_canton.post_id AND pm_canton.meta_key = 'canton'
			LEFT JOIN {$wpdb->postmeta} pm_provincia ON p.ID = pm_provincia.post_id AND pm_provincia.meta_key = 'provincia'
			LEFT JOIN {$wpdb->postmeta} pm_distrito ON p.ID = pm_distrito.post_id AND pm_distrito.meta_key = 'distrito'
			LEFT JOIN {$wpdb->postmeta} pm_poblado ON p.ID = pm_poblado.post_id AND pm_poblado.meta_key = 'poblado'
			LEFT JOIN {$wpdb->postmeta} pm_dependencia ON p.ID = pm_dependencia.post_id AND pm_dependencia.meta_key = 'dependencia'
			LEFT JOIN {$wpdb->postmeta} pm_zona ON p.ID = pm_zona.post_id AND pm_zona.meta_key = 'zona'
			LEFT JOIN {$wpdb->postmeta} pm_telefono ON p.ID = pm_telefono.post_id AND pm_telefono.meta_key = 'telefono'
			WHERE p.post_type = 'centro_educativo'
			AND p.post_status IN ('publish', 'pending')
			AND (p.post_title LIKE %s OR pm_codigo.meta_value LIKE %s)
			ORDER BY p.post_title ASC",
			$like_term,
			$like_term
		)
	);

	$output = array();
	foreach ( $results as $row ) {
		// Filtrar por DRE activa: solo mostrar centros cuya región esté activa.
		if ( $row->region ) {
			$dre_activa = get_term_meta( (int) $row->region, 'gnf_dre_activa', true );
			if ( '' !== $dre_activa && ! $dre_activa ) {
				continue; // DRE desactivada, saltar este centro.
			}
		}

		// Obtener nombre de la region.
		$region_name = '';
		if ( $row->region ) {
			$term_obj = get_term( $row->region, 'gn_region' );
			if ( $term_obj && ! is_wp_error( $term_obj ) ) {
				$region_name = $term_obj->name;
			}
		}

		$output[] = array(
			'id'          => $row->ID,
			'value'       => $row->post_title,
			'label'       => $row->post_title . ( $row->codigo_mep ? ' (' . $row->codigo_mep . ')' : '' ),
			'codigo_mep'  => $row->codigo_mep ?: '',
			'region'      => $row->region ?: '',
			'region_name' => $region_name,
			'circuito'    => $row->circuito ?: '',
			'canton'      => $row->canton ?: '',
			'provincia'   => $row->provincia ?: '',
			'distrito'    => $row->distrito ?: '',
			'poblado'     => $row->poblado ?: '',
			'dependencia' => $row->dependencia ?: '',
			'zona'        => $row->zona ?: '',
			'telefono'    => $row->telefono ?: '',
		);
	}

	wp_send_json( $output );
}
add_action( 'wp_ajax_gnf_search_centros', 'gnf_ajax_search_centros' );
add_action( 'wp_ajax_nopriv_gnf_search_centros', 'gnf_ajax_search_centros' );

/**
 * Obtiene todos los centros para selector (cuando hay pocos).
 */
function gnf_ajax_get_all_centros() {
	$centros = get_posts(
		array(
			'post_type'      => 'centro_educativo',
			'post_status'    => array( 'publish', 'pending' ),
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	);

	$output = array();
	foreach ( $centros as $centro ) {
		$codigo_mep = get_post_meta( $centro->ID, 'codigo_mep', true );
		$region     = get_post_meta( $centro->ID, 'region', true );
		$circuito   = get_post_meta( $centro->ID, 'circuito', true );

		$region_name = '';
		if ( $region ) {
			$term_obj = get_term( $region, 'gn_region' );
			if ( $term_obj && ! is_wp_error( $term_obj ) ) {
				$region_name = $term_obj->name;
			}
		}

		$output[] = array(
			'id'          => $centro->ID,
			'nombre'      => $centro->post_title,
			'codigo_mep'  => $codigo_mep,
			'region'      => $region,
			'region_name' => $region_name,
			'circuito'    => $circuito,
		);
	}

	wp_send_json_success( $output );
}
add_action( 'wp_ajax_gnf_get_all_centros', 'gnf_ajax_get_all_centros' );
add_action( 'wp_ajax_nopriv_gnf_get_all_centros', 'gnf_ajax_get_all_centros' );

