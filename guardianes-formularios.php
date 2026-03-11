<?php

/**
 * Plugin Name: Guardianes Formularios
 * Description: Plataforma de formularios Guardianes (matricula nativa, retos con WPForms, puntajes y paneles).
 * Author: Equipo Guardianes
 * Version: 1.0.0
 * Text Domain: guardianes-formularios
 * Domain Path: /languages
 */

if (! defined('ABSPATH')) {
	exit;
}

define('GNF_VERSION', '1.2.3');
define('GNF_PATH', plugin_dir_path(__FILE__));
define('GNF_URL', plugin_dir_url(__FILE__));

/**
 * Carga todos los archivos necesarios.
 */
require_once 'includes/helpers.php';
require_once 'includes/migrations/center-annual-data.php';
require_once 'includes/impersonate.php';
require_once 'includes/roles.php';
require_once 'includes/tables.php';
require_once 'includes/cpts.php';
require_once 'includes/acf-fields.php';
require_once 'includes/puntajes.php';
require_once 'includes/autoevaluacion.php';
require_once 'includes/evidencias.php';
require_once 'includes/wpforms-hooks.php';
require_once 'includes/matricula.php';
require_once 'includes/ajax-centros.php';
require_once 'includes/docente-panel.php';
require_once 'includes/supervisor-panel.php';
require_once 'includes/admin-panel.php';
require_once 'includes/comite-panel.php';
require_once 'includes/wizard.php';
require_once 'includes/admin-menu.php';
require_once 'includes/admin-users.php';
require_once 'includes/reports.php';
require_once 'includes/shortcodes.php';
require_once 'includes/registros.php';
require_once 'includes/react-loader.php';
require_once 'includes/rest-api.php';

/**
 * Carga textdomain.
 */
function gnf_load_textdomain()
{
	load_plugin_textdomain('guardianes-formularios', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('plugins_loaded', 'gnf_load_textdomain', 1);

/**
 * Activación: crea roles, tablas y flush rewrite.
 */
function gnf_activate()
{

	gnf_register_roles();
	gnf_add_custom_caps();

	if (function_exists('gnf_create_tables')) {
		gnf_create_tables();
	}
	if (function_exists('gnf_register_cpts')) {
		// gnf_register_cpts(); //Commented, was already being executed in init hook
	}
	flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'gnf_activate');

/**
 * Deactivación: sólo flush (no borrar datos).
 */
function gnf_deactivate()
{
	flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'gnf_deactivate');

/**
 * Encola assets sólo en páginas que usan nuestros shortcodes.
 */
function gnf_enqueue_assets()
{
	if (! is_singular() && ! is_page()) {
		return;
	}

	global $post;
	if (empty($post->post_content)) {
		return;
	}

	$shortcodes = array(
		'gn_docente_panel',
		'gn_supervisor_panel',
		'gn_admin_panel',
		'gn_comite_panel',
		'gn_notificaciones',
		'gn_wizard',
		'gn_registro_supervisor',
		'mock_docente',
		'mock_supervisor',
	);
	$found      = false;
	$has_wizard = false;

	foreach ($shortcodes as $shortcode) {
		if (has_shortcode($post->post_content, $shortcode)) {
			$found = true;
			if ('gn_wizard' === $shortcode) {
				$has_wizard = true;
			}
		}
	}

	if (! $found) {
		return;
	}

	wp_enqueue_style('gnf-styles', GNF_URL . 'assets/css/guardianes.css', array(), GNF_VERSION);

	// Ocultar header, subheader y barra de admin en pantallas de shortcodes del plugin.
	$gnf_hide_theme_ui = '
		.footer.elementor.elementor-location-footer { display: none; }
		.subheader { display: none !important; }
		header#masthead { display: none !important; }
		div#wpadminbar { display: none !important; }
		html { margin: 0 !important; }
		.ast-single-post.ast-page-builder-template .site-main > article,
		.woocommerce.ast-page-builder-template .site-main { padding: 0 !important; }
	';
	wp_add_inline_style('gnf-styles', $gnf_hide_theme_ui);

	wp_enqueue_script('gnf-scripts', GNF_URL . 'assets/js/guardianes.js', array('jquery'), GNF_VERSION, true);

	// Cargar wizard.js si se usa el shortcode del wizard.
	if ($has_wizard) {
		wp_enqueue_script('gnf-wizard', GNF_URL . 'assets/js/wizard.js', array('jquery', 'gnf-scripts'), GNF_VERSION, true);
	}

	wp_localize_script(
		'gnf-scripts',
		'gnfData',
		array(
			'ajaxUrl' => admin_url('admin-ajax.php'),
			'nonce'   => wp_create_nonce('gnf_nonce'),
			'anio'    => function_exists('gnf_get_active_year') ? gnf_get_active_year() : gmdate('Y'),
		)
	);
}
add_action('wp_enqueue_scripts', 'gnf_enqueue_assets');

/**
 * Enqueue admin JS for ACF field-points auto-population on reto edit screens.
 */
function gnf_admin_enqueue_assets($hook)
{
	if (! in_array($hook, array('post.php', 'post-new.php'), true)) {
		return;
	}
	$screen = get_current_screen();
	if (! $screen || 'reto' !== $screen->post_type) {
		return;
	}

	wp_enqueue_script(
		'gnf-acf-field-points',
		GNF_URL . 'assets/js/acf-field-points.js',
		array('jquery', 'acf-input'),
		GNF_VERSION,
		true
	);
	wp_localize_script('gnf-acf-field-points', 'gnfAcfFieldPoints', array(
		'ajaxUrl' => admin_url('admin-ajax.php'),
		'nonce'   => wp_create_nonce('gnf_acf_field_points'),
	));
}
add_action('admin_enqueue_scripts', 'gnf_admin_enqueue_assets');

/**
 * Carga templates personalizados para shortcodes.
 */
function gnf_locate_template($template, $slug)
{
	$custom = GNF_PATH . 'templates/' . $slug . '.php';
	if (file_exists($custom)) {
		return $custom;
	}
	return $template;
}

/* ──────────────────────────────────────────────────────────────────
 *  Reiniciar BD de Guardianes Forms — botón en options-general.php
 * ────────────────────────────────────────────────────────────────── */

/**
 * Registra la sección y el botón en Ajustes → General.
 */
function gnf_register_reset_settings_section()
{
	add_settings_section(
		'gnf_reset_section',
		'Guardianes Formularios — Reinicio de BD',
		'gnf_render_reset_section',
		'general'
	);
}
add_action('admin_init', 'gnf_register_reset_settings_section');

/**
 * Renderiza el botón de reinicio y la lista de usuarios de prueba.
 */
function gnf_render_reset_section()
{
	$nonce       = wp_create_nonce('gnf_reset_db_nonce');
	$url         = admin_url('admin-post.php?action=gnf_reset_db&_wpnonce=' . $nonce);
?>
	<div style="background:#fff8f0;border:1px solid #f59e0b;border-radius:8px;padding:20px;max-width:720px;">
		<p style="margin:0 0 12px;color:#92400e;">
			<strong>Esto eliminará y recreará:</strong> tablas custom, campos ACF, formularios WPForms, retos, centros,
			matrículas, entradas de retos, notificaciones, regiones y los usuarios de prueba listados abajo.<br>
			<strong>NO se eliminarán:</strong> páginas, posts, ni usuarios que no hayan sido creados por los seeders.
		</p>
		<p style="margin:0 0 12px;color:#92400e;">
			Se configurarán automáticamente: <strong>Matrícula nativa (sin WPForms)</strong>, <strong>Año activo = 2026</strong>,
			y <strong>Rangos de Estrellas</strong>.
		</p>

		<h4 style="margin:16px 0 8px;">Usuarios demo (serán eliminados y recreados):</h4>
		<table class="widefat striped" style="max-width:600px;">
			<thead>
				<tr>
					<th>Rol</th>
					<th>Nombre</th>
					<th>Email</th>
					<th>Contraseña</th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><code>docente</code></td>
					<td>Demo Docente</td>
					<td>demo.docente@movimientoguardianes.org</td>
					<td>demo123</td>
				</tr>
				<tr>
					<td><code>supervisor</code></td>
					<td>Demo Supervisor</td>
					<td>demo.supervisor@movimientoguardianes.org</td>
					<td>demo123</td>
				</tr>
				<tr>
					<td><code>comite_bae</code></td>
					<td>Demo Comite BAE</td>
					<td>demo.comite@movimientoguardianes.org</td>
					<td>demo123</td>
				</tr>
				<tr>
					<td><code>administrator</code></td>
					<td>Demo Administrador</td>
					<td>demo.admin@movimientoguardianes.org</td>
					<td>demo123</td>
				</tr>
			</tbody>
		</table>
		<p style="font-size:12px;color:#64748b;margin:8px 0 0;">
			Solo los usuarios marcados con <code>gnf_seeded=1</code> serán eliminados. Los demás quedan intactos.
		</p>

		<div style="margin-top:20px;">
			<a id="gnf-reset-btn"
				href="<?php echo esc_url($url); ?>"
				class="button button-primary"
				style="background:#dc2626;border-color:#b91c1c;color:#fff;font-weight:600;padding:4px 20px;"
				onclick="return confirm('⚠️ ¿Estás seguro?\n\nEsto va a ELIMINAR todas las tablas, formularios, retos, centros, regiones y usuarios de prueba, y luego los recreará desde cero.\n\nEsta acción no se puede deshacer.');">
				Reiniciar BD de Guardianes Forms
			</a>
		</div>
	</div>
<?php
}

/**
 * Procesa el reinicio: drop + recreate tables, reimport ACF, reimport forms, reseed, configure.
 */
function gnf_handle_reset_db()
{
	if (! current_user_can('manage_options')) {
		wp_die('Sin permisos.');
	}
	check_admin_referer('gnf_reset_db_nonce');

	// Capturar toda la salida para mostrarla al final.
	ob_start();

	echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Reinicio BD Guardianes</title>';
	echo '<style>
		body { font-family: "League Spartan", -apple-system, BlinkMacSystemFont, sans-serif; padding: 20px; background: #1a1a2e; color: #e2e8f0; }
		.gnf-reset-container { background: #16213e; padding: 28px; border-radius: 12px; max-width: 900px; margin: 0 auto; }
		h1 { color: #f87171; margin-bottom: 24px; }
		h2 { color: #60a5fa; margin: 24px 0 12px; border-bottom: 1px solid #334155; padding-bottom: 8px; }
		.ok  { color: #4ade80; }
		.wrn { color: #fbbf24; }
		.err { color: #f87171; }
		.info { color: #94a3b8; }
		.step { margin: 4px 0 4px 16px; }
		a.btn { display: inline-block; padding: 10px 20px; margin: 8px 4px; border-radius: 8px; text-decoration: none; font-weight: 600; }
		a.btn-back { background: #3b82f6; color: #fff; }
		a.btn-admin { background: #8b5cf6; color: #fff; }
		table.users { border-collapse: collapse; margin: 12px 0; }
		table.users th, table.users td { border: 1px solid #334155; padding: 6px 12px; text-align: left; }
		table.users th { background: #1e3a5f; color: #93c5fd; }
		table.users td { color: #e2e8f0; }
	</style></head><body>';
	echo '<div class="gnf-reset-container">';
	echo '<h1>Reinicio BD — Guardianes Formularios</h1>';

	// ─── PASO 1: LIMPIAR ─────────────────────────────────────────
	echo '<h2>Paso 1: Limpieza de datos existentes</h2>';

	require_once GNF_PATH . 'seeders/seed-all.php';
	gnf_cleanup_all_seeded_data(false);

	// ─── PASO 2: DROP + RECREATE TABLES ──────────────────────────
	echo '<h2>Paso 2: Drop y recrear tablas custom</h2>';

	global $wpdb;
	$tables = array(
		$wpdb->prefix . 'gn_reto_entries',
		$wpdb->prefix . 'gn_notificaciones',
		$wpdb->prefix . 'gn_matriculas',
	);
	foreach ($tables as $t) {
		$wpdb->query("DROP TABLE IF EXISTS {$t}"); // phpcs:ignore
		echo '<div class="step ok">✓ Tabla eliminada: ' . esc_html($t) . '</div>';
	}

	gnf_create_tables();
	echo '<div class="step ok">✓ Tablas recreadas con schema actual</div>';

	// ─── PASO 3: RE-IMPORT ACF FIELDS ────────────────────────────
	echo '<h2>Paso 3: Re-importar campos ACF</h2>';

	if (function_exists('acf_add_local_field_group')) {
		// ACF fields are registered on init via gnf_register_acf_fields().
		// Calling it directly ensures they're fresh.
		gnf_register_acf_fields();
		echo '<div class="step ok">✓ Campos ACF registrados</div>';
	} else {
		echo '<div class="step wrn">⚠ ACF Pro no activo, campos no re-registrados</div>';
	}

	// ─── PASO 4: RE-SEED ─────────────────────────────────────────
	echo '<h2>Paso 4: Ejecutar seeders (retos, matrícula, datos de ejemplo)</h2>';

	gnf_run_all_seeders(false);

	// ─── PASO 5: IMPORTAR CENTROS MEP ────────────────────────────
	echo '<h2>Paso 5: Importar centros educativos (CSV MEP)</h2>';

	$csv_path = file_exists( GNF_PATH . 'seeders/escuelas-mep.csv' )
		? GNF_PATH . 'seeders/escuelas-mep.csv'
		: GNF_PATH . 'seeders/centros-mep.csv';
	if ( file_exists( $csv_path ) ) {
		require_once GNF_PATH . 'seeders/seed-centros-mep.php';
		@set_time_limit( 300 ); // CSV puede ser grande.
		$csv_stats = gnf_import_centros_from_csv( $csv_path, false );
		echo '<div class="step ok">✓ Centros importados desde centros-mep.csv</div>';
		echo '<div class="step info">&nbsp; • Creados: ' . intval( $csv_stats['created'] ) . '</div>';
		echo '<div class="step info">&nbsp; • Actualizados: ' . intval( $csv_stats['updated'] ) . '</div>';
		echo '<div class="step info">&nbsp; • Omitidos/duplicados: ' . intval( $csv_stats['skipped'] ) . '</div>';
		if ( ! empty( $csv_stats['errors'] ) ) {
			echo '<div class="step wrn">⚠ Errores: ' . esc_html( implode( '; ', array_slice( $csv_stats['errors'], 0, 3 ) ) ) . '</div>';
		}
	} else {
		echo '<div class="step wrn">⚠ No se encontró centros-mep.csv en seeders/. Agrega el CSV del MEP para importar centros reales.</div>';
	}

	// ─── PASO 5: VALIDACIÓN MULTI-AÑO ─────────────────────────────
	echo '<h2>Paso 5: Validación multi-año</h2>';
	if ( function_exists( 'gnf_validate_multi_year_consistency' ) ) {
		$validation = gnf_validate_multi_year_consistency(
			array( 2025, 2026 ),
			array(
				'logger' => function( $message ) {
					echo '<div class="step info">' . esc_html( $message ) . '</div>';
				},
			)
		);
		echo '<div class="step ok">✓ Validación multi-año ejecutada</div>';
		if ( ! empty( $validation['years'] ) ) {
			foreach ( $validation['years'] as $year_row ) {
				echo '<div class="step info">&nbsp; • Año ' . intval( $year_row['year'] ) . ': centros con actividad=' . intval( $year_row['centers_with_activity'] ?? 0 ) . ', desajuste puntaje=' . intval( $year_row['puntaje_mismatch'] ?? 0 ) . '</div>';
			}
		}
	} else {
		echo '<div class="step wrn">⚠ Validador multi-año no disponible.</div>';
	}

	// ─── PASO 6: AUTO-CONFIGURE ──────────────────────────────────
	echo '<h2>Paso 6: Configuración automática</h2>';

	// 5a. Año activo = 2026
	if (function_exists('update_field')) {
		update_field('anio_actual', 2026, 'option');
	}
	update_option('options_anio_actual', 2026);
	echo '<div class="step ok">✓ Año activo configurado: <strong>2026</strong></div>';

	// 5b. Matrícula frontend nativa (sin WPForms).
	echo '<div class="step ok">✓ Matrícula frontend configurada con flujo nativo (ACF + handler propio)</div>';

	// 5c. Rangos de Estrellas (basados en puntaje máximo total ~100pts)
	$rangos = array(
		1 => array('min' => 1,  'max' => 20),
		2 => array('min' => 21, 'max' => 40),
		3 => array('min' => 41, 'max' => 60),
		4 => array('min' => 61, 'max' => 80),
		5 => array('min' => 81, 'max' => 0),  // 0 = sin límite
	);

	foreach ($rangos as $i => $rango) {
		if (function_exists('update_field')) {
			update_field('rango_estrella_' . $i . '_min', $rango['min'], 'option');
			update_field('rango_estrella_' . $i . '_max', $rango['max'], 'option');
		}
		update_option('options_rango_estrella_' . $i . '_min', $rango['min']);
		update_option('options_rango_estrella_' . $i . '_max', $rango['max']);
	}
	echo '<div class="step ok">✓ Rangos de Estrellas configurados:</div>';
	echo '<div class="step info">&nbsp; ★ 1-20pts &nbsp; ★★ 21-40pts &nbsp; ★★★ 41-60pts &nbsp; ★★★★ 61-80pts &nbsp; ★★★★★ 81+pts</div>';

	// ─── RESUMEN DE USUARIOS ─────────────────────────────────────
	echo '<h2>Usuarios de prueba creados</h2>';
	echo '<table class="users">';
	echo '<tr><th>Rol</th><th>Nombre</th><th>Email</th><th>Contraseña</th></tr>';

	$seeded_users = get_users(array(
		'meta_key'   => 'gnf_seeded',
		'meta_value' => '1',
	));

	if (! empty($seeded_users)) {
		foreach ($seeded_users as $u) {
			$roles = implode(', ', $u->roles);
			echo '<tr>';
			echo '<td><code>' . esc_html($roles) . '</code></td>';
			echo '<td>' . esc_html($u->display_name) . '</td>';
			echo '<td>' . esc_html($u->user_email) . '</td>';
			echo '<td>demo123</td>';
			echo '</tr>';
		}
	} else {
		echo '<tr><td colspan="4" class="info">No se crearon usuarios de prueba</td></tr>';
	}
	echo '</table>';

	// ─── LINKS ───────────────────────────────────────────────────
	echo '<div style="margin-top: 28px; padding-top: 20px; border-top: 1px solid #334155;">';
	echo '<a class="btn btn-back" href="' . esc_url(admin_url('options-general.php')) . '">← Volver a Ajustes</a>';
	echo '<a class="btn btn-admin" href="' . esc_url(admin_url('admin.php?page=gnf-admin')) . '">Panel Guardianes</a>';
	echo '</div>';

	echo '</div></body></html>';
	exit;
}
add_action('admin_post_gnf_reset_db', 'gnf_handle_reset_db');

