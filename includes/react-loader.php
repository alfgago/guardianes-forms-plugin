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
					GNF_VERSION
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
						wp_register_script( $chunk_handle, $dist_url . $chunk['file'], array(), GNF_VERSION, true );
						// Enqueue chunk CSS if any.
						if ( ! empty( $chunk['css'] ) ) {
							foreach ( $chunk['css'] as $ci => $chunk_css ) {
								wp_enqueue_style(
									$chunk_handle . '-css-' . $ci,
									$dist_url . $chunk_css,
									array(),
									GNF_VERSION
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
			GNF_VERSION,
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

	return '<div id="' . esc_attr( $root_id ) . '"></div>';
}
