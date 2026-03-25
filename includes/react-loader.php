<?php
/**
 * React Panel Loader.
 *
 * Reads the Vite manifest, enqueues hashed JS/CSS assets, injects
 * window.__GNF_{PANEL}__ data, and returns a mount-point <div>.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Check if Vite dev server is running (for HMR during development).
 */
function gnf_is_vite_dev() {
	return defined( 'GNF_VITE_DEV' ) && GNF_VITE_DEV;
}

/**
 * Read and cache the Vite manifest.json.
 *
 * @return array|false Parsed manifest or false on failure.
 */
function gnf_get_vite_manifest() {
	static $manifest = null;
	if ( $manifest !== null ) {
		return $manifest;
	}

	$manifest_path = GNF_PATH . 'assets/dist/.vite/manifest.json';
	if ( ! file_exists( $manifest_path ) ) {
		return false;
	}

	$json     = file_get_contents( $manifest_path );
	$manifest = json_decode( $json, true );
	return is_array( $manifest ) ? $manifest : false;
}

/**
 * Decode a WPForms form post into form_data format.
 *
 * @param int $form_id WPForms post ID.
 * @return array|null
 */
function gnf_get_wpforms_form_data( $form_id ) {
	if ( ! function_exists( 'wpforms' ) ) {
		return null;
	}

	$form_id = absint( $form_id );
	if ( ! $form_id ) {
		return null;
	}

	$form_post = wpforms()->form->get( $form_id );
	if ( ! $form_post || empty( $form_post->post_content ) ) {
		return null;
	}

	$form_data = json_decode( $form_post->post_content, true );
	if ( ! is_array( $form_data ) ) {
		return null;
	}

	$form_data['id'] = $form_id;
	return $form_data;
}

/**
 * Collect all WPForms used by the current docente panel year.
 *
 * @param int $user_id User ID.
 * @param int $anio    Active year.
 * @return array<int, array>
 */
function gnf_collect_docente_wpforms_form_data( $user_id, $anio ) {
	if ( ! function_exists( 'gnf_get_centro_for_docente' ) || ! function_exists( 'gnf_get_centro_retos_seleccionados' ) ) {
		return array();
	}

	$centro_id = (int) gnf_get_centro_for_docente( $user_id );
	if ( ! $centro_id ) {
		return array();
	}

	$reto_ids = gnf_get_centro_retos_seleccionados( $centro_id, $anio );
	if ( empty( $reto_ids ) ) {
		return array();
	}

	$forms = array();
	foreach ( $reto_ids as $reto_id ) {
		$form_id = function_exists( 'gnf_get_reto_form_id_for_year' ) ? (int) gnf_get_reto_form_id_for_year( $reto_id, $anio ) : 0;
		if ( ! $form_id || isset( $forms[ $form_id ] ) ) {
			continue;
		}

		$form_data = gnf_get_wpforms_form_data( $form_id );
		if ( $form_data ) {
			$forms[ $form_id ] = $form_data;
		}
	}

	return $forms;
}

/**
 * Prime WPForms frontend assets/settings for React-rendered forms.
 *
 * WPForms normally enqueues its JS and conditional-logic settings when the
 * shortcode renders in the page request. Our React panel fetches the form
 * HTML later through REST, so we need to preload the frontend runtime here.
 *
 * @param array<int, array> $forms Form data keyed by form ID.
 * @return string
 */
function gnf_enqueue_wpforms_runtime_for_forms( $forms ) {
	if ( empty( $forms ) || ! function_exists( 'wpforms' ) ) {
		return '';
	}

	$frontend = wpforms()->frontend ?? null;
	if ( ! $frontend ) {
		return '';
	}

	if ( ! isset( $frontend->forms ) || ! is_array( $frontend->forms ) ) {
		$frontend->forms = array();
	}

	foreach ( $forms as $form_id => $form_data ) {
		$frontend->forms[ (int) $form_id ] = $form_data;
	}

	if ( method_exists( $frontend, 'assets_css' ) ) {
		$frontend->assets_css();
	}
	if ( method_exists( $frontend, 'assets_js' ) ) {
		$frontend->assets_js();
	}

	if ( method_exists( $frontend, 'get_strings' ) ) {
		$settings_handle = wp_script_is( 'wpforms', 'registered' ) ? 'wpforms' : 'gnf-wpforms-runtime';
		if ( 'gnf-wpforms-runtime' === $settings_handle ) {
			wp_register_script( 'gnf-wpforms-runtime', false, array(), GNF_VERSION, true );
			wp_enqueue_script( 'gnf-wpforms-runtime' );
		}

		wp_add_inline_script( $settings_handle, 'var wpforms_settings = ' . wp_json_encode( $frontend->get_strings() ) . ';', 'before' );
	}

	$runtime_markup = '';

	foreach ( array( 'assets_header', 'assets_footer' ) as $method ) {
		if ( ! method_exists( $frontend, $method ) ) {
			continue;
		}

		ob_start();
		$frontend->{$method}();
		$runtime_markup .= (string) ob_get_clean();
	}

	return $runtime_markup;
}

/**
 * Build the full centros payload for PHP-side injection.
 *
 * Returns all active-region centros in a single batch query so the React auth
 * panel can filter client-side without any REST calls.
 *
 * @return array
 */
function gnf_build_centros_payload() {
	global $wpdb;

	// Batch: get all claimed centro IDs from usermeta.
	$claimed_ids = $wpdb->get_col(
		"SELECT DISTINCT meta_value FROM {$wpdb->usermeta}
		 WHERE meta_key IN ('centro_educativo_id','centro_solicitado','gnf_centro_id')
		 AND meta_value != '0' AND meta_value != '' AND meta_value IS NOT NULL"
	);
	$claimed_set = array_flip( array_map( 'intval', (array) $claimed_ids ) );

	// Get active region term IDs (skip if gnf_dre_activa === '0').
	$all_regions = get_terms( array( 'taxonomy' => 'gn_region', 'hide_empty' => false ) );
	if ( is_wp_error( $all_regions ) || empty( $all_regions ) ) {
		return array();
	}
	$region_names = array();
	$active_ids   = array();
	foreach ( (array) $all_regions as $term ) {
		$active = get_term_meta( $term->term_id, 'gnf_dre_activa', true );
		if ( '0' === $active ) {
			continue;
		}
		$region_names[ $term->term_id ] = $term->name;
		$active_ids[]                    = (int) $term->term_id;
	}
	if ( empty( $active_ids ) ) {
		return array();
	}

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
	$ids_sql = implode( ',', $active_ids );
	$rows    = $wpdb->get_results(
		"SELECT p.ID, p.post_title,
		        MIN(tt.term_id)      AS region_id,
		        MIN(pm_c.meta_value) AS codigo_mep,
		        MIN(pm_e.meta_value) AS correo
		 FROM {$wpdb->posts} p
		 INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
		 INNER JOIN {$wpdb->term_taxonomy} tt
		         ON tr.term_taxonomy_id = tt.term_taxonomy_id
		        AND tt.taxonomy = 'gn_region'
		        AND tt.term_id IN ({$ids_sql})
		 LEFT JOIN {$wpdb->postmeta} pm_c
		        ON p.ID = pm_c.post_id AND pm_c.meta_key = 'codigo_mep'
		 LEFT JOIN {$wpdb->postmeta} pm_e
		        ON p.ID = pm_e.post_id AND pm_e.meta_key = 'correo_institucional'
		 WHERE p.post_type = 'centro_educativo'
		   AND p.post_status IN ('publish','pending')
		 GROUP BY p.ID, p.post_title
		 ORDER BY p.post_title ASC"
	);
	// phpcs:enable

	$out = array();
	foreach ( (array) $rows as $row ) {
		$id      = (int) $row->ID;
		$rid     = (int) $row->region_id;
		$claimed = isset( $claimed_set[ $id ] );
		$out[]   = array(
			'id'                  => $id,
			'nombre'              => $row->post_title,
			'codigoMep'           => $row->codigo_mep ?: '',
			'regionId'            => $rid,
			'regionName'          => $region_names[ $rid ] ?? '',
			'claimed'             => $claimed,
			'correoInstitucional' => $claimed ? ( $row->correo ?: '' ) : '',
		);
	}

	return $out;
}

/**
 * Render a React panel.
 *
 * 1. Enqueues the correct Vite-built assets (or dev server in dev mode).
 * 2. Injects window.__GNF_{PANEL}__ with REST URL, nonce, user info, etc.
 * 3. Returns the mount-point <div id="gnf-{panel}-root"></div>.
 *
 * @param string $panel Panel key: auth, docente, supervisor, admin, comite.
 * @param array  $data  Panel-specific data to inject.
 * @return string HTML output.
 */
function gnf_render_react_panel( $panel, $data = array() ) {
	$panel_key = sanitize_key( $panel );
	$root_id   = 'gnf-' . $panel_key . '-root';

	// ── Build base init data ────────────────────────────────────────
	$user      = wp_get_current_user();
	$user_data = null;
	if ( $user->ID ) {
		$user_data = array(
			'id'    => $user->ID,
			'name'  => $user->display_name,
			'email' => $user->user_email,
			'roles' => array_values( $user->roles ),
		);

		// Add role-specific fields.
		if ( function_exists( 'gnf_get_centro_for_docente' ) && gnf_user_has_role( $user, 'docente' ) ) {
			$user_data['centroId'] = gnf_get_centro_for_docente( $user->ID );
			$user_data['estado']   = gnf_get_docente_estado( $user->ID );
		}
		if ( function_exists( 'gnf_get_user_region' ) && ( gnf_user_has_role( $user, 'supervisor' ) || gnf_user_has_role( $user, 'comite_bae' ) ) ) {
			$user_data['regionId'] = gnf_get_user_region( $user->ID );
		}
	}

	$active_year = function_exists( 'gnf_get_active_year' ) ? gnf_get_active_year() : (int) gmdate( 'Y' );
	$wpforms_runtime_markup = '';

	if ( 'docente' === $panel_key && $user->ID && function_exists( 'gnf_user_has_role' ) && gnf_user_has_role( $user, 'docente' ) ) {
		$wpforms_runtime_markup = gnf_enqueue_wpforms_runtime_for_forms( gnf_collect_docente_wpforms_form_data( $user->ID, $active_year ) );
	}

	$init_data = array_merge(
		array(
			'restUrl'   => esc_url_raw( rest_url( 'gnf/v1' ) ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'anio'      => $active_year,
			'pluginUrl' => GNF_URL,
			'logoUrl'   => defined( 'GNF_APP_LOGO_URL' ) ? GNF_APP_LOGO_URL : GNF_LOGO_URL,
			'authLogoUrl' => defined( 'GNF_AUTH_LOGO_URL' ) ? GNF_AUTH_LOGO_URL : ( defined( 'GNF_APP_LOGO_URL' ) ? GNF_APP_LOGO_URL : GNF_LOGO_URL ),
			'user'      => $user_data,
		),
		$data
	);

	// Inject all centros into the auth panel so React filters purely client-side.
	if ( 'auth' === $panel_key ) {
		$init_data['centros'] = gnf_build_centros_payload();
	}

	$js_var = '__GNF_' . strtoupper( $panel_key ) . '__';

	// ── Enqueue assets ──────────────────────────────────────────────
	if ( gnf_is_vite_dev() ) {
		// Development: load from Vite dev server.
		$dev_origin = defined( 'GNF_VITE_DEV_URL' ) ? GNF_VITE_DEV_URL : 'http://localhost:5173';

		wp_enqueue_script(
			'gnf-vite-client',
			$dev_origin . '/@vite/client',
			array(),
			null,
			true
		);

		wp_enqueue_script(
			'gnf-react-' . $panel_key,
			$dev_origin . '/src/entries/' . $panel_key . '.tsx',
			array( 'gnf-vite-client' ),
			null,
			true
		);

		// Add type="module" to both scripts.
		add_filter(
			'script_loader_tag',
			function ( $tag, $handle ) use ( $panel_key ) {
				if ( in_array( $handle, array( 'gnf-vite-client', 'gnf-react-' . $panel_key ), true ) ) {
					return str_replace( '<script ', '<script type="module" ', $tag );
				}
				return $tag;
			},
			10,
			2
		);
	} else {
		// Production: read from Vite manifest.
		$manifest = gnf_get_vite_manifest();
		if ( ! $manifest ) {
			return '<div id="' . esc_attr( $root_id ) . '"><p style="color:red;">Error: React build not found. Run <code>cd app && npm run build</code>.</p></div>';
		}

		$entry_key = 'src/entries/' . $panel_key . '.tsx';
		if ( ! isset( $manifest[ $entry_key ] ) ) {
			return '<div id="' . esc_attr( $root_id ) . '"><p style="color:red;">Error: Entry "' . esc_html( $entry_key ) . '" not found in manifest.</p></div>';
		}

		$entry    = $manifest[ $entry_key ];
		$dist_url = GNF_URL . 'assets/dist/';

		// Enqueue CSS files.
		if ( ! empty( $entry['css'] ) ) {
			foreach ( $entry['css'] as $i => $css_file ) {
				wp_enqueue_style(
					'gnf-react-' . $panel_key . '-css-' . $i,
					$dist_url . $css_file,
					array(),
					function_exists( 'gnf_asset_version' ) ? gnf_asset_version( 'assets/dist/' . $css_file ) : GNF_VERSION
				);
			}
		}

		// Enqueue shared chunks first (react, query).
		$deps = array();
		if ( ! empty( $entry['imports'] ) ) {
			foreach ( $entry['imports'] as $import_key ) {
				if ( isset( $manifest[ $import_key ] ) ) {
					$chunk      = $manifest[ $import_key ];
					$chunk_handle = 'gnf-chunk-' . sanitize_key( basename( $chunk['file'], '.js' ) );
					if ( ! wp_script_is( $chunk_handle, 'registered' ) ) {
						wp_register_script( $chunk_handle, $dist_url . $chunk['file'], array(), function_exists( 'gnf_asset_version' ) ? gnf_asset_version( 'assets/dist/' . $chunk['file'] ) : GNF_VERSION, true );
						// Enqueue chunk CSS if any.
						if ( ! empty( $chunk['css'] ) ) {
							foreach ( $chunk['css'] as $ci => $chunk_css ) {
								wp_enqueue_style(
									$chunk_handle . '-css-' . $ci,
									$dist_url . $chunk_css,
									array(),
									function_exists( 'gnf_asset_version' ) ? gnf_asset_version( 'assets/dist/' . $chunk_css ) : GNF_VERSION
								);
							}
						}
					}
					wp_enqueue_script( $chunk_handle );
					$deps[] = $chunk_handle;
				}
			}
		}

		// Enqueue main entry.
		wp_enqueue_script(
			'gnf-react-' . $panel_key,
			$dist_url . $entry['file'],
			$deps,
			function_exists( 'gnf_asset_version' ) ? gnf_asset_version( 'assets/dist/' . $entry['file'] ) : GNF_VERSION,
			true
		);

		// Add type="module" to all our scripts.
		add_filter(
			'script_loader_tag',
			function ( $tag, $handle ) use ( $panel_key, $deps ) {
				$our_handles = array_merge( array( 'gnf-react-' . $panel_key ), $deps );
				if ( in_array( $handle, $our_handles, true ) ) {
					return str_replace( '<script ', '<script type="module" ', $tag );
				}
				return $tag;
			},
			10,
			2
		);
	}

	// ── Inline init data ────────────────────────────────────────────
	wp_add_inline_script(
		'gnf-react-' . $panel_key,
		'window.' . $js_var . ' = ' . wp_json_encode( $init_data ) . ';',
		'before'
	);

	// ── Hide WP theme chrome ────────────────────────────────────────
	$hide_css = '
		.footer.elementor.elementor-location-footer { display: none; }
		footer#colophon { display: none !important; }
		.subheader { display: none !important; }
		header#masthead { display: none !important; }
		div#wpadminbar { display: none !important; }
		html { margin: 0 !important; }
		.ast-single-post.ast-page-builder-template .site-main > article,
		.woocommerce.ast-page-builder-template .site-main { padding: 0 !important; }
	';
	wp_register_style( 'gnf-react-hide-theme', false );
	wp_enqueue_style( 'gnf-react-hide-theme' );
	wp_add_inline_style( 'gnf-react-hide-theme', $hide_css );

	return $wpforms_runtime_markup . '<div id="' . esc_attr( $root_id ) . '"></div>';
}
