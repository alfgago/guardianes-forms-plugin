<?php

/**
 * Página de administración de Usuarios (Supervisores y Docentes).
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Renderiza la página de gestión de usuarios.
 */
function gnf_render_admin_users()
{
	// Procesar acciones si hay alguna.
	gnf_process_user_actions();

	// Obtener filtros.
	$role_filter   = isset($_GET['role']) ? sanitize_text_field($_GET['role']) : '';
	$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
	$region_filter = isset($_GET['region']) ? absint($_GET['region']) : '';
	$search_query  = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

	// Paginación.
	$per_page     = 20;
	$current_page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
	$offset       = ($current_page - 1) * $per_page;

	// Construir query de usuarios.
	$args = array(
		'role__in' => array('docente', 'supervisor'),
		'number'   => $per_page,
		'offset'   => $offset,
		'orderby'  => 'registered',
		'order'    => 'DESC',
	);

	// Filtro de rol específico.
	if ($role_filter && in_array($role_filter, array('docente', 'supervisor'), true)) {
		$args['role'] = $role_filter;
		unset($args['role__in']);
	}

	// Filtro de estado.
	if ($status_filter) {
		$args['meta_query'][] = array(
			'key'     => 'gnf_docente_status',
			'value'   => $status_filter,
			'compare' => '=',
		);
	}

	// Filtro de región.
	if ($region_filter) {
		$args['meta_query'][] = array(
			'key'     => 'region',
			'value'   => $region_filter,
			'compare' => '=',
		);
	}

	// Búsqueda.
	if ($search_query) {
		$args['search']         = '*' . $search_query . '*';
		$args['search_columns'] = array('user_login', 'user_email', 'display_name');
	}

	$users_query = new WP_User_Query($args);
	$users       = $users_query->get_results();
	$total_users = $users_query->get_total();
	$total_pages = ceil($total_users / $per_page);

	// Estadísticas rápidas.
	$docente_user_ids = get_users(array('role' => 'docente', 'fields' => 'ID'));
	$centros_registrados = array();
	$centros_pendientes  = array();

	foreach ($docente_user_ids as $docente_user_id) {
		$centro_id = absint( gnf_get_centro_for_docente( $docente_user_id ) );
		$key = $centro_id ? 'centro_' . $centro_id : 'user_' . $docente_user_id;
		$centros_registrados[$key] = true;

		$docente_status = get_user_meta($docente_user_id, 'gnf_docente_status', true) ?: 'activo';
		if ('pendiente' === $docente_status) {
			$centros_pendientes[$key] = true;
		}
	}

	$total_centros_registrados = count($centros_registrados);
	$total_supervisors = count(get_users(array('role' => 'supervisor', 'fields' => 'ID')));
	$pending_centros  = count($centros_pendientes);
	$pending_supervisors = count(get_users(array(
		'role'       => 'supervisor',
		'meta_key'   => 'gnf_supervisor_status',
		'meta_value' => 'pendiente',
		'fields'     => 'ID',
	)));

	// Obtener regiones para filtro.
	$regions = get_terms(array('taxonomy' => 'gn_region', 'hide_empty' => false));

?>
	<div class="wrap gnf-users-wrap">
		<style>
			.gnf-users-wrap {
				max-width: 1400px;
			}

			.gnf-users-header {
				display: flex;
				justify-content: space-between;
				align-items: center;
				margin-bottom: 24px;
				padding: 20px 24px;
				background: linear-gradient(135deg, #2d5a87 0%, #1e3a5f 100%);
				border-radius: 12px;
				color: #fff;
			}

			.gnf-users-header h1 {
				color: #fff;
				margin: 0;
				font-size: 24px;
				display: flex;
				align-items: center;
				gap: 12px;
			}

			.gnf-quick-stats {
				display: grid;
				grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
				gap: 16px;
				margin-bottom: 24px;
			}

			.gnf-stat-card {
				background: #fff;
				border-radius: 12px;
				padding: 20px;
				box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
				text-align: center;
			}

			.gnf-stat-card strong {
				display: block;
				font-size: 28px;
				color: #1e3a5f;
				margin-bottom: 4px;
			}

			.gnf-stat-card span {
				color: #64748b;
				font-size: 13px;
			}

			.gnf-stat-card--docente {
				border-top: 4px solid #3b82f6;
			}

			.gnf-stat-card--supervisor {
				border-top: 4px solid #8b5cf6;
			}

			.gnf-stat-card--pending {
				border-top: 4px solid #f59e0b;
			}

			.gnf-filters {
				background: #fff;
				border-radius: 12px;
				padding: 16px 20px;
				margin-bottom: 20px;
				box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
				display: flex;
				gap: 12px;
				align-items: center;
				flex-wrap: wrap;
			}

			.gnf-filters select,
			.gnf-filters input[type="text"] {
				padding: 10px 16px;
				border: 1px solid #e5e7eb;
				border-radius: 8px;
				font-size: 14px;
				min-width: 160px;
			}

			.gnf-filters input[type="text"] {
				min-width: 220px;
			}

			.gnf-btn {
				display: inline-flex;
				align-items: center;
				gap: 6px;
				padding: 10px 18px;
				background: #369484;
				color: #fff;
				border: none;
				border-radius: 8px;
				font-size: 13px;
				font-weight: 500;
				cursor: pointer;
				text-decoration: none;
				transition: opacity 0.2s;
			}

			.gnf-btn:hover {
				opacity: 0.9;
				color: #fff;
			}

			.gnf-btn--secondary {
				background: #e5e7eb;
				color: #475569;
			}

			.gnf-btn--secondary:hover {
				background: #d1d5db;
				color: #1e293b;
			}

			.gnf-btn--success {
				background: #22c55e;
			}

			.gnf-btn--danger {
				background: #ef4444;
			}

			.gnf-btn--small {
				padding: 6px 12px;
				font-size: 12px;
			}

			.gnf-users-table {
				background: #fff;
				border-radius: 12px;
				overflow: hidden;
				box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
			}

			.gnf-users-table table {
				width: 100%;
				border-collapse: collapse;
			}

			.gnf-users-table th {
				background: #f8fafc;
				font-weight: 600;
				color: #475569;
				text-transform: uppercase;
				font-size: 11px;
				letter-spacing: 0.5px;
				padding: 14px 16px;
				text-align: left;
				border-bottom: 1px solid #e5e7eb;
			}

			.gnf-users-table td {
				padding: 14px 16px;
				border-bottom: 1px solid #f1f5f9;
				vertical-align: middle;
			}

			.gnf-users-table tbody tr:hover {
				background: #f8fafc;
			}

			.gnf-user-info {
				display: flex;
				align-items: center;
				gap: 12px;
			}

			.gnf-user-avatar {
				width: 40px;
				height: 40px;
				border-radius: 50%;
				background: #e5e7eb;
			}

			.gnf-user-name {
				font-weight: 600;
				color: #1e293b;
			}

			.gnf-user-email {
				font-size: 12px;
				color: #64748b;
			}

			.gnf-badge {
				display: inline-block;
				padding: 4px 10px;
				border-radius: 20px;
				font-size: 11px;
				font-weight: 600;
				text-transform: uppercase;
			}

			.gnf-badge--docente {
				background: #dbeafe;
				color: #1d4ed8;
			}

			.gnf-badge--supervisor {
				background: #ede9fe;
				color: #7c3aed;
			}

			.gnf-badge--activo {
				background: #dcfce7;
				color: #16a34a;
			}

			.gnf-badge--pendiente {
				background: #fef3c7;
				color: #d97706;
			}

			.gnf-badge--rechazado {
				background: #fee2e2;
				color: #dc2626;
			}

			.gnf-actions {
				display: flex;
				gap: 8px;
			}

			.gnf-pagination {
				display: flex;
				justify-content: space-between;
				align-items: center;
				padding: 16px 20px;
				background: #fff;
				border-radius: 12px;
				margin-top: 20px;
				box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
			}

			.gnf-pagination-info {
				color: #64748b;
				font-size: 14px;
			}

			.gnf-pagination-links {
				display: flex;
				gap: 8px;
			}

			.gnf-pagination-links a,
			.gnf-pagination-links span {
				display: inline-flex;
				align-items: center;
				justify-content: center;
				min-width: 36px;
				height: 36px;
				padding: 0 12px;
				border-radius: 8px;
				font-size: 14px;
				text-decoration: none;
				color: #475569;
				background: #f1f5f9;
				transition: all 0.2s;
			}

			.gnf-pagination-links a:hover {
				background: #369484;
				color: #fff;
			}

			.gnf-pagination-links span.current {
				background: #369484;
				color: #fff;
			}

			.gnf-empty-state {
				text-align: center;
				padding: 60px 20px;
				color: #64748b;
			}

			.gnf-empty-state svg {
				width: 64px;
				height: 64px;
				margin-bottom: 16px;
				opacity: 0.5;
			}

			.gnf-modal-overlay {
				display: none;
				position: fixed;
				top: 0;
				left: 0;
				right: 0;
				bottom: 0;
				background: rgba(0, 0, 0, 0.5);
				z-index: 100000;
				align-items: center;
				justify-content: center;
			}

			.gnf-modal-overlay.active {
				display: flex;
			}

			.gnf-modal {
				background: #fff;
				border-radius: 16px;
				padding: 24px;
				max-width: 500px;
				width: 90%;
				max-height: 80vh;
				overflow-y: auto;
			}

			.gnf-modal h3 {
				margin: 0 0 16px 0;
				color: #1e293b;
			}

			.gnf-modal-actions {
				display: flex;
				gap: 12px;
				justify-content: flex-end;
				margin-top: 20px;
			}

			.gnf-form-group {
				margin-bottom: 16px;
			}

			.gnf-form-group label {
				display: block;
				margin-bottom: 6px;
				font-weight: 500;
				color: #374151;
			}

			.gnf-form-group select,
			.gnf-form-group input,
			.gnf-form-group textarea {
				width: 100%;
				padding: 10px 14px;
				border: 1px solid #e5e7eb;
				border-radius: 8px;
				font-size: 14px;
			}
		</style>

		<div class="gnf-users-header">
			<h1>👥 Gestión de Usuarios</h1>
			<a href="<?php echo esc_url(admin_url('admin.php?page=gnf-admin')); ?>" class="gnf-btn gnf-btn--secondary">
				← Volver al Panel
			</a>
		</div>

		<div class="gnf-quick-stats">
			<div class="gnf-stat-card gnf-stat-card--docente">
				<strong><?php echo esc_html($total_centros_registrados); ?></strong>
				<span>Centros Registrados</span>
			</div>
			<div class="gnf-stat-card gnf-stat-card--supervisor">
				<strong><?php echo esc_html($total_supervisors); ?></strong>
				<span>Supervisores</span>
			</div>
			<div class="gnf-stat-card gnf-stat-card--pending">
				<strong><?php echo esc_html($pending_centros); ?></strong>
				<span>Centros Pendientes</span>
			</div>
			<div class="gnf-stat-card gnf-stat-card--pending">
				<strong><?php echo esc_html($pending_supervisors); ?></strong>
				<span>Supervisores Pendientes</span>
			</div>
		</div>

		<form class="gnf-filters" method="get">
			<input type="hidden" name="page" value="gnf-usuarios" />

			<select name="role">
				<option value="">Todos los roles</option>
				<option value="docente" <?php selected($role_filter, 'docente'); ?>>Centros educativos</option>
				<option value="supervisor" <?php selected($role_filter, 'supervisor'); ?>>Supervisores</option>
			</select>

			<select name="status">
				<option value="">Todos los estados</option>
				<option value="activo" <?php selected($status_filter, 'activo'); ?>>Activo</option>
				<option value="pendiente" <?php selected($status_filter, 'pendiente'); ?>>Pendiente</option>
				<option value="rechazado" <?php selected($status_filter, 'rechazado'); ?>>Rechazado</option>
			</select>

			<select name="region">
				<option value="">Todas las regiones</option>
				<?php if (!is_wp_error($regions) && !empty($regions)) : ?>
					<?php foreach ($regions as $region) : ?>
						<option value="<?php echo esc_attr($region->term_id); ?>" <?php selected($region_filter, $region->term_id); ?>>
							<?php echo esc_html($region->name); ?>
						</option>
					<?php endforeach; ?>
				<?php endif; ?>
			</select>

			<input type="text" name="s" placeholder="Buscar por nombre o email..." value="<?php echo esc_attr($search_query); ?>" />

			<button type="submit" class="gnf-btn">🔍 Filtrar</button>

			<?php if ($role_filter || $status_filter || $region_filter || $search_query) : ?>
				<a href="<?php echo esc_url(admin_url('admin.php?page=gnf-usuarios')); ?>" class="gnf-btn gnf-btn--secondary">
					✕ Limpiar filtros
				</a>
			<?php endif; ?>
		</form>

		<div class="gnf-users-table">
			<?php if ($users) : ?>
				<table>
					<thead>
						<tr>
							<th>Usuario</th>
							<th>Rol</th>
							<th>Estado</th>
							<th>Región</th>
							<th>Centro Educativo</th>
							<th>Registro</th>
							<th>Acciones</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($users as $user) :
							$user_role   = in_array('supervisor', $user->roles) ? 'supervisor' : 'docente';
							$status_key  = $user_role === 'supervisor' ? 'gnf_supervisor_status' : 'gnf_docente_status';
							$user_status = get_user_meta($user->ID, $status_key, true) ?: 'activo';
							$user_region = get_user_meta($user->ID, 'region', true);
							$region_name = '';
							if ($user_region) {
								$term = get_term($user_region, 'gn_region');
								$region_name = $term && !is_wp_error($term) ? $term->name : '';
							}

							// Obtener centro asociado para docentes.
							$centro_info = '';
							if ($user_role === 'docente') {
								$centro_id = gnf_get_centro_for_docente( $user->ID );
								if ($centro_id) {
									$centro_info = get_the_title($centro_id);
								}
							}
						?>
							<tr>
								<td>
									<div class="gnf-user-info">
										<?php echo get_avatar($user->ID, 40, '', '', array('class' => 'gnf-user-avatar')); ?>
										<div>
											<div class="gnf-user-name"><?php echo esc_html($user->display_name); ?></div>
											<div class="gnf-user-email"><?php echo esc_html($user->user_email); ?></div>
										</div>
									</div>
								</td>
								<td>
									<span class="gnf-badge gnf-badge--<?php echo esc_attr($user_role); ?>">
										<?php echo esc_html(ucfirst($user_role)); ?>
									</span>
								</td>
								<td>
									<span class="gnf-badge gnf-badge--<?php echo esc_attr($user_status); ?>">
										<?php echo esc_html(ucfirst($user_status)); ?>
									</span>
								</td>
								<td><?php echo esc_html($region_name ?: '—'); ?></td>
								<td><?php echo esc_html($centro_info ?: '—'); ?></td>
								<td><?php echo esc_html(date_i18n('d/m/Y', strtotime($user->user_registered))); ?></td>
								<td>
									<div class="gnf-actions">
										<?php if ($user_status === 'pendiente') : ?>
											<form method="post" style="display: inline;">
												<?php wp_nonce_field('gnf_user_action', 'gnf_user_nonce'); ?>
												<input type="hidden" name="gnf_action" value="aprobar" />
												<input type="hidden" name="user_id" value="<?php echo esc_attr($user->ID); ?>" />
												<input type="hidden" name="user_role" value="<?php echo esc_attr($user_role); ?>" />
												<button type="submit" class="gnf-btn gnf-btn--success gnf-btn--small">✓ Aprobar</button>
											</form>
											<form method="post" style="display: inline;">
												<?php wp_nonce_field('gnf_user_action', 'gnf_user_nonce'); ?>
												<input type="hidden" name="gnf_action" value="rechazar" />
												<input type="hidden" name="user_id" value="<?php echo esc_attr($user->ID); ?>" />
												<input type="hidden" name="user_role" value="<?php echo esc_attr($user_role); ?>" />
												<button type="submit" class="gnf-btn gnf-btn--danger gnf-btn--small">✕ Rechazar</button>
											</form>
										<?php endif; ?>
										<a href="<?php echo esc_url(admin_url('admin.php?page=gnf-usuario-editar&user_id=' . $user->ID)); ?>" class="gnf-btn gnf-btn--secondary gnf-btn--small">
											✎ Editar
										</a>
									</div>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<div class="gnf-empty-state">
					<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
						<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
					</svg>
					<p>No se encontraron usuarios con los filtros seleccionados.</p>
				</div>
			<?php endif; ?>
		</div>

		<?php if ($total_pages > 1) : ?>
			<div class="gnf-pagination">
				<div class="gnf-pagination-info">
					Mostrando <?php echo esc_html($offset + 1); ?>-<?php echo esc_html(min($offset + $per_page, $total_users)); ?> de <?php echo esc_html($total_users); ?> usuarios
				</div>
				<div class="gnf-pagination-links">
					<?php
					$base_url = admin_url('admin.php?page=gnf-usuarios');
					$query_args = array_filter(array(
						'role'   => $role_filter,
						'status' => $status_filter,
						'region' => $region_filter,
						's'      => $search_query,
					));

					if ($current_page > 1) :
						$prev_url = add_query_arg(array_merge($query_args, array('paged' => $current_page - 1)), $base_url);
					?>
						<a href="<?php echo esc_url($prev_url); ?>">← Anterior</a>
					<?php endif; ?>

					<?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++) :
						$page_url = add_query_arg(array_merge($query_args, array('paged' => $i)), $base_url);
						if ($i === $current_page) : ?>
							<span class="current"><?php echo esc_html($i); ?></span>
						<?php else : ?>
							<a href="<?php echo esc_url($page_url); ?>"><?php echo esc_html($i); ?></a>
						<?php endif;
					endfor; ?>

					<?php if ($current_page < $total_pages) :
						$next_url = add_query_arg(array_merge($query_args, array('paged' => $current_page + 1)), $base_url);
					?>
						<a href="<?php echo esc_url($next_url); ?>">Siguiente →</a>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>
	</div>
<?php
}

/**
 * Procesa las acciones de usuarios (aprobar, rechazar).
 */
function gnf_process_user_actions()
{
	if (!isset($_POST['gnf_action']) || !isset($_POST['gnf_user_nonce'])) {
		return;
	}

	if (!wp_verify_nonce($_POST['gnf_user_nonce'], 'gnf_user_action')) {
		wp_die('Acción no autorizada.');
	}

	if (!current_user_can('manage_options') && !current_user_can('approve_guardianes_docentes')) {
		wp_die('No tienes permisos para realizar esta acción.');
	}

	$action    = sanitize_text_field($_POST['gnf_action']);
	$user_id   = absint($_POST['user_id']);
	$user_role = sanitize_text_field($_POST['user_role']);

	if (!$user_id) {
		return;
	}

	$status_key = $user_role === 'supervisor' ? 'gnf_supervisor_status' : 'gnf_docente_status';

	switch ($action) {
		case 'aprobar':
			update_user_meta($user_id, $status_key, 'activo');
			gnf_insert_notification($user_id, 'cuenta_aprobada', 'Tu cuenta ha sido aprobada. Ya puedes acceder al sistema.', 'usuario', $user_id);

			// Para docentes, aprobar también el centro si es necesario.
			if ($user_role === 'docente') {
				$centro_id = gnf_get_centro_for_docente( $user_id );
				if ($centro_id) {
					gnf_sync_docente_centro_assignment( $user_id, $centro_id, array( 'sync_correo_institucional' => true ) );
				}
			}

			add_action('admin_notices', function () {
				echo '<div class="notice notice-success is-dismissible"><p>Usuario aprobado correctamente.</p></div>';
			});
			break;

		case 'rechazar':
			update_user_meta($user_id, $status_key, 'rechazado');
			gnf_insert_notification($user_id, 'cuenta_rechazada', 'Tu solicitud de cuenta ha sido rechazada. Contacta al administrador para más información.', 'usuario', $user_id);

			add_action('admin_notices', function () {
				echo '<div class="notice notice-warning is-dismissible"><p>Usuario rechazado.</p></div>';
			});
			break;
	}
}

/**
 * Renderiza la página de edición de usuario.
 */
function gnf_render_admin_user_edit()
{
	if ( ! current_user_can( gnf_menu_capability() ) ) {
		wp_die( esc_html__( 'No tienes permisos para acceder a esta página.', 'guardianes-formularios' ), 403 );
	}

	$user_id = isset($_GET['user_id']) ? absint($_GET['user_id']) : 0;

	if (!$user_id) {
		wp_die('Usuario no especificado.');
	}

	$user = get_userdata($user_id);
	if (!$user) {
		wp_die('Usuario no encontrado.');
	}

	// Procesar guardado.
	if (isset($_POST['gnf_save_user']) && wp_verify_nonce($_POST['gnf_user_edit_nonce'], 'gnf_user_edit')) {
		$new_status = sanitize_text_field($_POST['user_status']);
		$new_region = absint($_POST['user_region']);
		$user_role  = in_array('supervisor', $user->roles) ? 'supervisor' : 'docente';
		$status_key = $user_role === 'supervisor' ? 'gnf_supervisor_status' : 'gnf_docente_status';

		update_user_meta($user_id, $status_key, $new_status);
		update_user_meta($user_id, 'region', $new_region);
		update_user_meta($user_id, 'gnf_region_id', $new_region);
		update_user_meta($user_id, 'gnf_region', $new_region);

		// Actualizar display name si se proporciona.
		if (!empty($_POST['display_name'])) {
			wp_update_user(array(
				'ID'           => $user_id,
				'display_name' => sanitize_text_field($_POST['display_name']),
			));
		}

		// Actualizar centro asociado para docentes.
		if ($user_role === 'docente') {
			$prev_centro_id = gnf_get_centro_for_docente( $user_id );
			$centro_id      = absint($_POST['centro_asociado'] ?? 0);
			if ( ! $centro_id ) {
				gnf_clear_docente_centro_assignment( $user_id, $prev_centro_id );
			}

			// Añadir a docentes asociados del centro.
			if ( $centro_id ) {
				gnf_sync_docente_centro_assignment( $user_id, $centro_id, array( 'sync_correo_institucional' => true ) );
			}
		}

		// Refrescar datos.
		$user = get_userdata($user_id);

		echo '<div class="notice notice-success is-dismissible"><p>Usuario actualizado correctamente.</p></div>';
	}

	$user_role   = in_array('supervisor', $user->roles) ? 'supervisor' : 'docente';
	$status_key  = $user_role === 'supervisor' ? 'gnf_supervisor_status' : 'gnf_docente_status';
	$user_status = get_user_meta($user_id, $status_key, true) ?: 'activo';
	$user_region = gnf_get_user_region( $user_id );
	$centro_id   = gnf_get_centro_for_docente( $user_id );

	$regions = get_terms(array('taxonomy' => 'gn_region', 'hide_empty' => false));
	$centros = get_posts(array('post_type' => 'centro_educativo', 'posts_per_page' => -1, 'post_status' => 'any'));

?>
	<div class="wrap gnf-users-wrap">
		<div class="gnf-users-header">
			<h1>✎ Editar Usuario</h1>
			<a href="<?php echo esc_url(admin_url('admin.php?page=gnf-usuarios')); ?>" class="gnf-btn gnf-btn--secondary">
				← Volver a Usuarios
			</a>
		</div>

		<div style="max-width: 600px;">
			<div style="background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 2px 12px rgba(0,0,0,0.06);">
				<div style="display: flex; align-items: center; gap: 16px; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 1px solid #e5e7eb;">
					<?php echo get_avatar($user_id, 64, '', '', array('style' => 'border-radius: 50%;')); ?>
					<div>
						<h2 style="margin: 0;"><?php echo esc_html($user->display_name); ?></h2>
						<p style="margin: 4px 0 0; color: #64748b;"><?php echo esc_html($user->user_email); ?></p>
						<span class="gnf-badge gnf-badge--<?php echo esc_attr($user_role); ?>" style="margin-top: 8px;">
							<?php echo esc_html(ucfirst($user_role)); ?>
						</span>
					</div>
				</div>

				<form method="post">
					<?php wp_nonce_field('gnf_user_edit', 'gnf_user_edit_nonce'); ?>

					<div class="gnf-form-group">
						<label for="display_name">Nombre</label>
						<input type="text" id="display_name" name="display_name" value="<?php echo esc_attr($user->display_name); ?>" />
					</div>

					<div class="gnf-form-group">
						<label for="user_status">Estado</label>
						<select id="user_status" name="user_status">
							<option value="activo" <?php selected($user_status, 'activo'); ?>>Activo</option>
							<option value="pendiente" <?php selected($user_status, 'pendiente'); ?>>Pendiente</option>
							<option value="rechazado" <?php selected($user_status, 'rechazado'); ?>>Rechazado</option>
						</select>
					</div>

					<div class="gnf-form-group">
						<label for="user_region">Dirección Regional</label>
						<select id="user_region" name="user_region">
							<option value="">— Sin asignar —</option>
							<?php if (!is_wp_error($regions) && !empty($regions)) : ?>
								<?php foreach ($regions as $region) : ?>
									<option value="<?php echo esc_attr($region->term_id); ?>" <?php selected($user_region, $region->term_id); ?>>
										<?php echo esc_html($region->name); ?>
									</option>
								<?php endforeach; ?>
							<?php endif; ?>
						</select>
					</div>

					<?php if ($user_role === 'docente') : ?>
						<div class="gnf-form-group">
							<label for="centro_asociado">Centro Educativo</label>
							<select id="centro_asociado" name="centro_asociado">
								<option value="">— Sin asignar —</option>
								<?php foreach ($centros as $centro) : ?>
									<option value="<?php echo esc_attr($centro->ID); ?>" <?php selected($centro_id, $centro->ID); ?>>
										<?php echo esc_html($centro->post_title); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
					<?php endif; ?>

					<div style="margin-top: 24px; display: flex; gap: 12px;">
						<button type="submit" name="gnf_save_user" value="1" class="gnf-btn">
							💾 Guardar Cambios
						</button>
						<a href="<?php echo esc_url(get_edit_user_link($user_id)); ?>" class="gnf-btn gnf-btn--secondary">
							Ver perfil completo →
						</a>
					</div>
				</form>
			</div>

			<?php
			// Mostrar información adicional.
			$identificacion = get_user_meta($user_id, 'gnf_identificacion', true);
			$telefono       = get_user_meta($user_id, 'gnf_telefono', true);
			$cargo          = get_user_meta($user_id, 'gnf_cargo', true);
			?>
			<?php if ($identificacion || $telefono || $cargo) : ?>
				<div style="background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); margin-top: 20px;">
					<h3 style="margin: 0 0 16px;">Información Adicional</h3>
					<table style="width: 100%;">
						<?php if ($identificacion) : ?>
							<tr>
								<td style="padding: 8px 0; color: #64748b;">Identificación:</td>
								<td style="padding: 8px 0; font-weight: 500;"><?php echo esc_html($identificacion); ?></td>
							</tr>
						<?php endif; ?>
						<?php if ($telefono) : ?>
							<tr>
								<td style="padding: 8px 0; color: #64748b;">Teléfono:</td>
								<td style="padding: 8px 0; font-weight: 500;"><?php echo esc_html($telefono); ?></td>
							</tr>
						<?php endif; ?>
						<?php if ($cargo) : ?>
							<tr>
								<td style="padding: 8px 0; color: #64748b;">Cargo:</td>
								<td style="padding: 8px 0; font-weight: 500;"><?php echo esc_html($cargo); ?></td>
							</tr>
						<?php endif; ?>
						<tr>
							<td style="padding: 8px 0; color: #64748b;">Fecha de registro:</td>
							<td style="padding: 8px 0; font-weight: 500;"><?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($user->user_registered))); ?></td>
						</tr>
					</table>
				</div>
			<?php endif; ?>
		</div>
	</div>
<?php
}

