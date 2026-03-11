<?php

/**
 * Menu agrupado "Formularios Bandera Azul".
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Capability del menú.
 */
function gnf_menu_capability()
{

	if (current_user_can('manage_options')) {
		return 'manage_options';
	}

	if (current_user_can('manage_network_options')) {
		return 'manage_network_options';
	}

	if (current_user_can('manage_guardianes')) {
		return 'manage_guardianes';
	}

	if (current_user_can('manage_translations')) {
		return 'manage_translations';
	}

	return 'manage_options';
}

/**
 * Menú principal + submenús.
 */
function gnf_register_admin_menu()
{

	$cap = gnf_menu_capability();

	add_menu_page(
		__('Formularios Bandera Azul', 'guardianes-formularios'),
		__('Formularios Bandera Azul', 'guardianes-formularios'),
		$cap,
		'gnf-admin',
		'gnf_render_admin_dashboard',
		'dashicons-forms',
		3
	);

	add_submenu_page(
		'gnf-admin',
		__('Panel', 'guardianes-formularios'),
		__('Panel', 'guardianes-formularios'),
		$cap,
		'gnf-admin',
		'gnf_render_admin_dashboard'
	);

	add_submenu_page(
		'gnf-admin',
		__('Centros Educativos', 'guardianes-formularios'),
		__('Centros educativos', 'guardianes-formularios'),
		$cap,
		'edit.php?post_type=centro_educativo'
	);

	add_submenu_page(
		'gnf-admin',
		__('Retos', 'guardianes-formularios'),
		__('Retos', 'guardianes-formularios'),
		$cap,
		'edit.php?post_type=reto'
	);

	add_submenu_page(
		'gnf-admin',
		__('Direcciones Regionales', 'guardianes-formularios'),
		__('Direcciones Regionales', 'guardianes-formularios'),
		$cap,
		'edit-tags.php?taxonomy=gn_region'
	);

	add_submenu_page(
		'gnf-admin',
		__('Usuarios', 'guardianes-formularios'),
		__('Usuarios', 'guardianes-formularios'),
		$cap,
		'gnf-usuarios',
		'gnf_render_admin_users'
	);

	add_submenu_page(
		'gnf-admin',
		__('Editar Usuario', 'guardianes-formularios'),
		__('Editar Usuario', 'guardianes-formularios'),
		$cap,
		'gnf-usuario-editar',
		'gnf_render_admin_user_edit'
	);

	// Ocultar submenú de edición de usuario (solo accesible por enlace directo).
	remove_submenu_page('gnf-admin', 'gnf-usuario-editar');
}
add_action('admin_menu', 'gnf_register_admin_menu', 2);

/**
 * Resalta el menú correcto cuando estamos en la taxonomía de regiones.
 */
function gnf_highlight_region_menu($parent_file)
{
	global $current_screen;
	if ($current_screen->taxonomy === 'gn_region') {
		return 'gnf-admin';
	}
	return $parent_file;
}
add_filter('parent_file', 'gnf_highlight_region_menu');


/**
 * Vista del panel raiz con resumen y accesos.
 */
function gnf_render_admin_dashboard()
{
	global $wpdb;

	// Obtener año seleccionado o usar el activo.
	$anio_activo   = function_exists('gnf_get_active_year') ? gnf_get_active_year() : gmdate('Y');
	$anio          = isset($_GET['gnf_year']) ? absint($_GET['gnf_year']) : $anio_activo;

	// Obtener años disponibles.
	$table_entries = $wpdb->prefix . 'gn_reto_entries';
	$years_available = $wpdb->get_col("SELECT DISTINCT anio FROM {$table_entries} ORDER BY anio DESC");
	if (empty($years_available)) {
		$years_available = array($anio_activo);
	}
	if (!in_array($anio_activo, $years_available)) {
		array_unshift($years_available, $anio_activo);
	}

	$centros_total = (int) $wpdb->get_var("SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type='centro_educativo' AND post_status='publish'");
	$retos_total   = (int) $wpdb->get_var("SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type='reto' AND post_status='publish'");
	$regiones_total = (int) wp_count_terms(array('taxonomy' => 'gn_region', 'hide_empty' => false));

	$stats         = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT estado, COUNT(*) as total FROM {$table_entries} WHERE anio = %d GROUP BY estado",
			$anio
		),
		OBJECT_K
	);
	$flagged = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$table_entries} WHERE anio = %d AND evidencias LIKE %s",
			$anio,
			'%\"requires_year_validation\"%'
		)
	);
	$aprobados = isset($stats['aprobado']) ? (int) $stats['aprobado']->total : 0;
	$enviados  = isset($stats['enviado']) ? (int) $stats['enviado']->total : 0;
	$correcc   = isset($stats['correccion']) ? (int) $stats['correccion']->total : 0;

	$pend_docs = get_users(
		array(
			'role'       => 'docente',
			'number'     => 50,
			'meta_key'   => 'gnf_docente_status',
			'meta_value' => 'pendiente',
		)
	);
	$pend_centros = get_posts(
		array(
			'post_type'      => 'centro_educativo',
			'post_status'    => array('pending'),
			'posts_per_page' => 50,
		)
	);

?>
	<div class="wrap gnf-admin-wrap">
		<style>
			.gnf-admin-wrap {
				max-width: 1400px;
			}

			.gnf-admin-header {
				display: flex;
				justify-content: space-between;
				align-items: center;
				margin-bottom: 24px;
				padding: 20px 24px;
				background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 100%);
				border-radius: 12px;
				color: #fff;
			}

			.gnf-admin-header h1 {
				color: #fff;
				margin: 0;
				font-size: 24px;
			}

			.gnf-year-selector {
				display: flex;
				align-items: center;
				gap: 12px;
			}

			.gnf-year-selector label {
				color: rgba(255, 255, 255, 0.8);
				font-size: 14px;
			}

			.gnf-year-selector select {
				padding: 8px 16px;
				border-radius: 6px;
				border: 2px solid rgba(255, 255, 255, 0.3);
				background: rgba(255, 255, 255, 0.1);
				color: #fff;
				font-size: 14px;
				font-weight: 600;
				cursor: pointer;
			}

			.gnf-year-selector select option {
				color: #333;
			}

			.gnf-year-badge {
				display: inline-block;
				background: #4ade80;
				color: #000;
				padding: 4px 12px;
				border-radius: 20px;
				font-size: 12px;
				font-weight: 600;
			}

			.gnf-cards {
				display: grid;
				grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
				gap: 20px;
				margin: 24px 0;
			}

			.gnf-card {
				border: none;
				border-radius: 12px;
				padding: 24px;
				background: #fff;
				box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
				transition: transform 0.2s, box-shadow 0.2s;
			}

			.gnf-card:hover {
				transform: translateY(-2px);
				box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
			}

			.gnf-card h3 {
				margin: 0 0 8px 0;
				font-size: 18px;
				color: #1e3a5f;
			}

			.gnf-card p {
				color: #64748b;
				font-size: 14px;
				margin: 0 0 16px 0;
			}

			.gnf-btn-wide {
				display: inline-block;
				padding: 12px 20px;
				background: linear-gradient(135deg, #369484 0%, #2d7a6d 100%);
				color: #fff;
				text-decoration: none;
				border-radius: 8px;
				font-weight: 600;
				font-size: 14px;
				transition: opacity 0.2s;
			}

			.gnf-btn-wide:hover {
				opacity: 0.9;
				color: #fff;
			}

			.gnf-stats {
				display: grid;
				grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
				gap: 16px;
				margin: 24px 0;
			}

			.gnf-stat {
				padding: 20px;
				border-radius: 12px;
				background: #fff;
				box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
				text-align: center;
			}

			.gnf-stat strong {
				display: block;
				font-size: 32px;
				color: #1e3a5f;
				margin-bottom: 4px;
			}

			.gnf-stat span {
				color: #64748b;
				font-size: 13px;
			}

			.gnf-stat--success {
				border-left: 4px solid #22c55e;
			}

			.gnf-stat--warning {
				border-left: 4px solid #f59e0b;
			}

			.gnf-stat--error {
				border-left: 4px solid #ef4444;
			}

			.gnf-stat--info {
				border-left: 4px solid #3b82f6;
			}

			.gnf-section-title {
				font-size: 20px;
				color: #1e3a5f;
				margin: 32px 0 16px;
				padding-bottom: 8px;
				border-bottom: 2px solid #e5e7eb;
			}

			.gnf-table {
				background: #fff;
				border-radius: 12px;
				overflow: hidden;
				box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
			}

			.gnf-table th {
				background: #f8fafc;
				font-weight: 600;
				color: #475569;
				text-transform: uppercase;
				font-size: 11px;
				letter-spacing: 0.5px;
			}

			.gnf-btn {
				display: inline-block;
				padding: 8px 16px;
				background: #369484;
				color: #fff;
				border: none;
				border-radius: 6px;
				font-size: 13px;
				font-weight: 500;
				cursor: pointer;
				text-decoration: none;
			}

			.gnf-btn--ghost {
				background: transparent;
				border: 1px solid #e5e7eb;
				color: #64748b;
			}

			.gnf-btn--ghost:hover {
				border-color: #369484;
				color: #369484;
			}

			.gnf-filter-bar {
				display: flex;
				gap: 12px;
				align-items: center;
				margin-bottom: 20px;
				padding: 16px 20px;
				background: #fff;
				border-radius: 12px;
				box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
			}

			.gnf-filter-bar select {
				padding: 10px 16px;
				border: 1px solid #e5e7eb;
				border-radius: 8px;
				font-size: 14px;
			}
		</style>

		<div class="gnf-admin-header">
			<div>
				<h1>🏆 Formularios Bandera Azul Ecológica</h1>
			</div>
			<div class="gnf-year-selector">
				<label>Visualizando año:</label>
				<select onchange="location.href='<?php echo esc_url(admin_url('admin.php?page=gnf-admin')); ?>&gnf_year=' + this.value">
					<?php foreach ($years_available as $y) : ?>
						<option value="<?php echo esc_attr($y); ?>" <?php selected($anio, $y); ?>>
							<?php echo esc_html($y); ?><?php echo $y == $anio_activo ? ' (Activo)' : ''; ?>
						</option>
					<?php endforeach; ?>
				</select>
				<?php if ($anio == $anio_activo) : ?>
					<span class="gnf-year-badge">Año Activo</span>
				<?php endif; ?>
			</div>
		</div>

		<div class="gnf-stats">
			<div class="gnf-stat gnf-stat--info">
				<strong><?php echo esc_html($centros_total); ?></strong>
				<span>Centros Educativos</span>
			</div>
			<div class="gnf-stat gnf-stat--info">
				<strong><?php echo esc_html($retos_total); ?></strong>
				<span>Eco Retos Activos</span>
			</div>
			<div class="gnf-stat gnf-stat--info">
				<strong><?php echo esc_html($regiones_total); ?></strong>
				<span>Direcciones Regionales</span>
			</div>
			<div class="gnf-stat gnf-stat--success">
				<strong><?php echo esc_html($aprobados); ?></strong>
				<span>Aprobados (<?php echo esc_html($anio); ?>)</span>
			</div>
			<div class="gnf-stat gnf-stat--warning">
				<strong><?php echo esc_html($enviados); ?></strong>
				<span>Pendientes Revisión</span>
			</div>
			<div class="gnf-stat gnf-stat--error">
				<strong><?php echo esc_html($correcc); ?></strong>
				<span>En Corrección</span>
			</div>
		</div>

		<div class="gnf-cards">
			<div class="gnf-card">
				<h3>🏫 Centros Educativos</h3>
				<p>Gestiona códigos MEP, direcciones regionales y docentes asociados.</p>
				<a class="gnf-btn-wide" href="<?php echo esc_url(admin_url('edit.php?post_type=centro_educativo')); ?>">Gestionar Centros</a>
			</div>
			<div class="gnf-card">
				<h3>🎯 Eco Retos</h3>
				<p>Configura puntajes, formularios WPForms, checklist e iconos.</p>
				<a class="gnf-btn-wide" href="<?php echo esc_url(admin_url('edit.php?post_type=reto')); ?>">Gestionar Retos</a>
			</div>
			<div class="gnf-card">
				<h3>👥 Usuarios</h3>
				<p>Gestiona docentes y supervisores, aprueba solicitudes pendientes.</p>
				<a class="gnf-btn-wide" href="<?php echo esc_url(admin_url('admin.php?page=gnf-usuarios')); ?>">Gestionar Usuarios</a>
			</div>
			<div class="gnf-card">
				<h3>📍 Direcciones Regionales</h3>
				<p>Administra las direcciones regionales de educación del MEP.</p>
				<a class="gnf-btn-wide" href="<?php echo esc_url(admin_url('edit-tags.php?taxonomy=gn_region')); ?>">Gestionar Regiones</a>
			</div>
			<div class="gnf-card">
				<h3>⚙️ Configuración</h3>
				<p>IDs de formularios, año activo y rangos de estrellas.</p>
				<a class="gnf-btn-wide" href="<?php echo esc_url(admin_url('admin.php?page=guardianes-config')); ?>">Ir a Configuración</a>
			</div>
		</div>

		<h2 class="gnf-section-title">📊 Reportes - Año <?php echo esc_html($anio); ?></h2>

		<?php
		$filter_region = isset($_GET['report_region']) ? absint($_GET['report_region']) : '';
		?>

		<div class="gnf-filter-bar">
			<form method="get" style="display: flex; gap: 12px; align-items: center;">
				<input type="hidden" name="page" value="gnf-admin" />
				<input type="hidden" name="gnf_year" value="<?php echo esc_attr($anio); ?>" />
				<select name="report_region">
					<option value="">Todas las Direcciones Regionales</option>
					<?php
					$regions = get_terms(array('taxonomy' => 'gn_region', 'hide_empty' => false));
					if (!is_wp_error($regions) && !empty($regions)) {
						foreach ($regions as $r) {
							printf('<option value="%d" %s>%s</option>', $r->term_id, selected($filter_region, $r->term_id, false), esc_html($r->name));
						}
					}
					?>
				</select>
				<button type="submit" class="gnf-btn">Filtrar</button>
				<a href="<?php echo esc_url(admin_url('admin-post.php?action=gnf_export_csv&year=' . $anio . ($filter_region ? '&region=' . $filter_region : ''))); ?>" class="gnf-btn">📥 Exportar CSV</a>
			</form>
		</div>

		<table class="gnf-table widefat striped">
			<thead>
				<tr>
					<th>Centro Educativo</th>
					<th>Dirección Regional</th>
					<th>Eco Reto</th>
					<th>Puntaje</th>
					<th>Estado</th>
					<th>Año</th>
				</tr>
			</thead>
			<tbody>
				<?php
				if (function_exists('gnf_get_report_data')) {
					$report_items = gnf_get_report_data($filter_region, $anio);
				} else {
					$report_items = array();
				}

				if ($report_items) :
					foreach ($report_items as $item) : ?>
						<tr>
							<td><?php echo esc_html($item->centro); ?></td>
							<td><?php echo esc_html($item->region); ?></td>
							<td><?php echo esc_html($item->reto_id); ?></td>
							<td><?php echo esc_html($item->puntaje); ?></td>
							<td><?php echo esc_html($item->estado); ?></td>
							<td><?php echo esc_html($item->anio); ?></td>
						</tr>
					<?php endforeach;
				else : ?>
					<tr>
						<td colspan="6"><?php esc_html_e('Sin resultados.', 'guardianes-formularios'); ?></td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>

		<br /><br />

		<?php if (current_user_can('manage_options')) : ?>
			<h2><?php echo esc_html(__('Pendientes', 'guardianes-formularios')); ?></h2>
			<h3><?php echo esc_html(__('Docentes pendientes', 'guardianes-formularios')); ?></h3>
			<table class="gnf-table">
				<tr>
					<th><?php echo esc_html(__('Nombre', 'guardianes-formularios')); ?></th>
					<th><?php echo esc_html(__('Email', 'guardianes-formularios')); ?></th>
					<th><?php echo esc_html(__('Centro solicitado', 'guardianes-formularios')); ?></th>
					<th><?php echo esc_html(__('Acciones', 'guardianes-formularios')); ?></th>
				</tr>
				<?php if ($pend_docs) : foreach ($pend_docs as $doc) :
						$centro = (int) get_user_meta($doc->ID, 'centro_solicitado', true);
				?>
						<tr>
							<td><?php echo esc_html($doc->display_name); ?></td>
							<td><?php echo esc_html($doc->user_email); ?></td>
							<td><?php echo $centro ? esc_html(get_the_title($centro) . ' (ID ' . $centro . ')') : 'N/D'; ?></td>
							<td>
								<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
									<?php wp_nonce_field('gnf_admin_action', 'gnf_nonce'); ?>
									<input type="hidden" name="action" value="gnf_aprobar_docente" />
									<input type="hidden" name="user_id" value="<?php echo esc_attr($doc->ID); ?>" />
									<input type="hidden" name="centro_id" value="<?php echo esc_attr($centro); ?>" />
									<button class="gnf-btn" type="submit"><?php echo esc_html(__('Aprobar', 'guardianes-formularios')); ?></button>
								</form>
								<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
									<?php wp_nonce_field('gnf_admin_action', 'gnf_nonce'); ?>
									<input type="hidden" name="action" value="gnf_rechazar_docente" />
									<input type="hidden" name="user_id" value="<?php echo esc_attr($doc->ID); ?>" />
									<input type="hidden" name="centro_id" value="<?php echo esc_attr($centro); ?>" />
									<button class="gnf-btn gnf-btn--ghost" type="submit"><?php echo esc_html(__('Rechazar', 'guardianes-formularios')); ?></button>
								</form>
							</td>
						</tr>
					<?php endforeach;
				else : ?>
					<tr>
						<td colspan="4"><?php echo esc_html(__('Sin docentes pendientes.', 'guardianes-formularios')); ?></td>
					</tr>
				<?php endif; ?>
			</table>

			<h3><?php echo esc_html(__('Centros pendientes', 'guardianes-formularios')); ?></h3>
			<table class="gnf-table">
				<tr>
					<th><?php echo esc_html(__('Centro', 'guardianes-formularios')); ?></th>
					<th><?php echo esc_html(__('Estado', 'guardianes-formularios')); ?></th>
					<th><?php echo esc_html(__('Acciones', 'guardianes-formularios')); ?></th>
				</tr>
				<?php if ($pend_centros) : foreach ($pend_centros as $c) :
						$solicitante = get_users(array('meta_key' => 'centro_solicitado', 'meta_value' => $c->ID, 'number' => 1));
						$user_id = $solicitante ? $solicitante[0]->ID : 0;
				?>
						<tr>
							<td><?php echo esc_html($c->post_title); ?></td>
							<td><?php echo esc_html(get_post_meta($c->ID, 'estado_centro', true) ?: 'pendiente'); ?></td>
							<td>
								<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
									<?php wp_nonce_field('gnf_admin_action', 'gnf_nonce'); ?>
									<input type="hidden" name="action" value="gnf_aprobar_centro" />
									<input type="hidden" name="centro_id" value="<?php echo esc_attr($c->ID); ?>" />
									<input type="hidden" name="user_id" value="<?php echo esc_attr($user_id); ?>" />
									<button class="gnf-btn" type="submit"><?php echo esc_html(__('Aprobar', 'guardianes-formularios')); ?></button>
								</form>
								<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
									<?php wp_nonce_field('gnf_admin_action', 'gnf_nonce'); ?>
									<input type="hidden" name="action" value="gnf_rechazar_centro" />
									<input type="hidden" name="centro_id" value="<?php echo esc_attr($c->ID); ?>" />
									<input type="hidden" name="user_id" value="<?php echo esc_attr($user_id); ?>" />
									<button class="gnf-btn gnf-btn--ghost" type="submit"><?php echo esc_html(__('Rechazar', 'guardianes-formularios')); ?></button>
								</form>
							</td>
						</tr>
					<?php endforeach;
				else : ?>
					<tr>
						<td colspan="3"><?php echo esc_html(__('Sin centros pendientes.', 'guardianes-formularios')); ?></td>
					</tr>
				<?php endif; ?>
			</table>
		<?php endif; ?>
	</div>
<?php
}
