<?php

/**
 * Template: Admin Dashboard Frontend.
 *
 * Variables disponibles:
 * - $data['stats'] array
 * - $data['active_tab'] string
 * - $data['current_url'] string
 */

$stats       = $data['stats'];
$active_tab  = $data['active_tab'];
$current_url = $data['current_url'];
$user        = wp_get_current_user();
$anio        = isset($data['anio']) ? (int) $data['anio'] : (int) $stats['anio'];
$anio_activo = isset($data['anio_activo']) ? (int) $data['anio_activo'] : $anio;
$years_available = isset($data['years_available']) ? (array) $data['years_available'] : array($anio);

// Tabs disponibles (usando texto simple en lugar de emojis).
$tabs = array(
	'inicio'        => array('label' => 'Inicio', 'icon' => 'home'),
	'usuarios'      => array('label' => 'Usuarios', 'icon' => 'users', 'badge' => $stats['pending_docentes'] + $stats['pending_supervisors']),
	'centros'       => array('label' => 'Centros', 'icon' => 'building'),
	'retos'         => array('label' => 'Retos', 'icon' => 'target'),
	'reportes'      => array('label' => 'Reportes', 'icon' => 'chart'),
	'configuracion' => array('label' => 'Configuración', 'icon' => 'settings'),
);

// SVG icons simple.
$icons = array(
	'home'     => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
	'users'    => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
	'building' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01"/><path d="M16 6h.01"/><path d="M12 6h.01"/><path d="M12 10h.01"/><path d="M12 14h.01"/><path d="M16 10h.01"/><path d="M16 14h.01"/><path d="M8 10h.01"/><path d="M8 14h.01"/></svg>',
	'target'   => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>',
	'chart'    => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>',
	'settings' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
	'download' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>',
	'plus'     => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>',
	'check'    => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>',
	'x'        => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
	'clock'    => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
	'search'   => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>',
	'trophy'   => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/></svg>',
	'map'      => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/><line x1="8" y1="2" x2="8" y2="18"/><line x1="16" y1="6" x2="16" y2="22"/></svg>',
);
?>
<div class="gnf-dashboard">
	<!-- Sidebar -->
	<aside class="gnf-sidebar">
		<div class="gnf-sidebar__logo">
			<img src="<?php echo esc_url(GNF_URL . 'assets/logo-guardiana.png'); ?>" alt="Guardianes" style="height: 40px; width: auto;" />
			<div>
				<small style="opacity: 0.7; font-size: 0.75rem;">Panel Admin</small>
			</div>
		</div>

		<nav class="gnf-sidebar__nav">
			<div class="gnf-sidebar__section">
				<div class="gnf-sidebar__label">Principal</div>
				<?php foreach ($tabs as $tab_key => $tab_data) : ?>
					<a href="<?php echo esc_url(add_query_arg(array('tab' => $tab_key, 'gnf_year' => $anio), $current_url)); ?>"
						class="gnf-sidebar__link <?php echo $active_tab === $tab_key ? 'is-active' : ''; ?>">
						<span class="gnf-sidebar__icon"><?php echo $icons[$tab_data['icon']]; ?></span>
						<?php echo esc_html($tab_data['label']); ?>
						<?php if (! empty($tab_data['badge']) && $tab_data['badge'] > 0) : ?>
							<span class="gnf-sidebar__badge"><?php echo esc_html($tab_data['badge']); ?></span>
						<?php endif; ?>
					</a>
				<?php endforeach; ?>
			</div>

			<div class="gnf-sidebar__section">
				<div class="gnf-sidebar__label">Año</div>
				<div style="padding: 0 24px;">
					<select onchange="location.href = '<?php echo esc_url(add_query_arg(array('tab' => $active_tab), $current_url)); ?>&gnf_year=' + this.value" style="width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.2); background: rgba(255,255,255,0.1); color: #fff; font-weight: 500;">
						<?php foreach ($years_available as $y) : ?>
							<option value="<?php echo esc_attr($y); ?>" <?php selected($anio, $y); ?> style="color: #333;">
								<?php echo esc_html($y); ?><?php echo (int) $y === (int) $anio_activo ? ' (Activo)' : ''; ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<div class="gnf-sidebar__section">
				<div class="gnf-sidebar__label">Acciones Rápidas</div>
				<a href="<?php echo esc_url(admin_url('admin-post.php?action=gnf_export_csv&year=' . $stats['anio'])); ?>" class="gnf-sidebar__link">
					<span class="gnf-sidebar__icon"><?php echo $icons['download']; ?></span>
					Exportar CSV
				</a>
				<a href="<?php echo esc_url(admin_url('edit.php?post_type=centro_educativo')); ?>" class="gnf-sidebar__link">
					<span class="gnf-sidebar__icon"><?php echo $icons['plus']; ?></span>
					Nuevo Centro
				</a>
				<a href="<?php echo esc_url(admin_url('edit.php?post_type=reto')); ?>" class="gnf-sidebar__link">
					<span class="gnf-sidebar__icon"><?php echo $icons['plus']; ?></span>
					Nuevo Reto
				</a>
			</div>
		</nav>

		<div class="gnf-sidebar__user">
			<div class="gnf-sidebar__avatar">
				<?php echo esc_html(strtoupper(substr($user->display_name, 0, 1))); ?>
			</div>
			<div>
				<div class="gnf-sidebar__username"><?php echo esc_html($user->display_name); ?></div>
				<div class="gnf-sidebar__role">Administrador</div>
			</div>
		</div>
	</aside>

	<!-- Main Content -->
	<main class="gnf-main">
		<div class="gnf-main__inner">
			<?php if ('inicio' === $active_tab) : ?>
				<!-- INICIO TAB -->
				<div class="gnf-page-header">
					<div>
						<h1 class="gnf-page-title">Panel Administrativo</h1>
						<p class="gnf-page-subtitle">Bienvenido, <?php echo esc_html($user->display_name); ?>. Visualizando año: <?php echo esc_html($anio); ?></p>
					</div>
				</div>

				<!-- Stats Grid -->
				<div class="gnf-stats-grid">
					<div class="gnf-stat-card">
						<div class="gnf-stat-card__icon gnf-stat-card__icon--ocean"><?php echo $icons['building']; ?></div>
						<div class="gnf-stat-card__value"><?php echo esc_html($stats['centros_activos']); ?></div>
						<div class="gnf-stat-card__label">Centros Activos</div>
					</div>

					<div class="gnf-stat-card">
						<div class="gnf-stat-card__icon gnf-stat-card__icon--forest"><?php echo $icons['users']; ?></div>
						<div class="gnf-stat-card__value"><?php echo esc_html($stats['total_docentes']); ?></div>
						<div class="gnf-stat-card__label">Docentes</div>
						<?php if ($stats['pending_docentes'] > 0) : ?>
							<span class="gnf-stat-card__trend gnf-stat-card__trend--up">+<?php echo esc_html($stats['pending_docentes']); ?> pendientes</span>
						<?php endif; ?>
					</div>

					<div class="gnf-stat-card">
						<div class="gnf-stat-card__icon gnf-stat-card__icon--sun"><?php echo $icons['clock']; ?></div>
						<div class="gnf-stat-card__value"><?php echo esc_html($stats['retos_enviados']); ?></div>
						<div class="gnf-stat-card__label">Retos por Revisar</div>
					</div>

					<div class="gnf-stat-card">
						<div class="gnf-stat-card__icon gnf-stat-card__icon--coral"><?php echo $icons['check']; ?></div>
						<div class="gnf-stat-card__value"><?php echo esc_html($stats['retos_aprobados']); ?></div>
						<div class="gnf-stat-card__label">Retos Aprobados</div>
					</div>
				</div>

				<!-- Quick Actions & Activity -->
				<div class="gnf-content-grid">
					<!-- Pending Approvals -->
					<?php if ($stats['pending_docentes'] > 0 || $stats['pending_supervisors'] > 0) : ?>
						<div class="gnf-section">
							<div class="gnf-section__header">
								<h3 class="gnf-section__title">Pendientes de Aprobación</h3>
								<a href="<?php echo esc_url(add_query_arg('tab', 'usuarios', $current_url)); ?>" class="gnf-btn gnf-btn--sm gnf-btn--ghost">Ver todos</a>
							</div>
							<div class="gnf-section__body">
								<?php if ($stats['pending_docentes'] > 0) : ?>
									<div class="gnf-alert gnf-alert--warning">
										<span class="gnf-alert__icon"><?php echo $icons['users']; ?></span>
										<div>
											<strong><?php echo esc_html($stats['pending_docentes']); ?> docentes</strong> esperando aprobación
										</div>
									</div>
								<?php endif; ?>
								<?php if ($stats['pending_supervisors'] > 0) : ?>
									<div class="gnf-alert gnf-alert--info">
										<span class="gnf-alert__icon"><?php echo $icons['search']; ?></span>
										<div>
											<strong><?php echo esc_html($stats['pending_supervisors']); ?> supervisores</strong> esperando aprobación
										</div>
									</div>
								<?php endif; ?>
							</div>
						</div>
					<?php endif; ?>

					<!-- Recent Activity -->
					<div class="gnf-section">
						<div class="gnf-section__header">
							<h3 class="gnf-section__title">Actividad Reciente</h3>
						</div>
						<div class="gnf-section__body">
							<?php if (! empty($stats['actividad_reciente'])) : ?>
								<div class="gnf-timeline">
									<?php foreach (array_slice($stats['actividad_reciente'], 0, 5) as $activity) : ?>
										<div class="gnf-timeline__item">
											<div class="gnf-timeline__dot gnf-timeline__dot--<?php echo 'aprobado' === $activity->estado ? 'success' : ('correccion' === $activity->estado ? 'warning' : ''); ?>"></div>
											<div class="gnf-timeline__content">
												<strong><?php echo esc_html($activity->centro_nombre ?: 'Centro #' . $activity->centro_id); ?></strong>
												— <?php echo esc_html($activity->reto_nombre ?: 'Reto #' . $activity->reto_id); ?>
												<span class="gnf-badge gnf-badge--<?php echo 'aprobado' === $activity->estado ? 'forest' : ('enviado' === $activity->estado ? 'ocean' : 'sun'); ?>">
													<?php echo esc_html($activity->estado); ?>
												</span>
												<div class="gnf-timeline__time">
													<?php echo esc_html(human_time_diff(strtotime($activity->updated_at), time())); ?> atrás
												</div>
											</div>
										</div>
									<?php endforeach; ?>
								</div>
							<?php else : ?>
								<p class="gnf-muted">No hay actividad reciente.</p>
							<?php endif; ?>
						</div>
					</div>
				</div>

			<?php elseif ('usuarios' === $active_tab) : ?>
				<!-- USUARIOS TAB -->
				<div class="gnf-page-header">
					<div>
						<h1 class="gnf-page-title">Gestión de Usuarios</h1>
						<p class="gnf-page-subtitle">Aprueba docentes, supervisores y gestiona cuentas</p>
					</div>
				</div>

				<!-- Pending Docentes -->
				<?php if (! empty($data['pending_docentes'])) : ?>
					<div class="gnf-section gnf-mb-6">
						<div class="gnf-section__header gnf-section__header--warning">
							<h3 class="gnf-section__title">Docentes Pendientes (<?php echo count($data['pending_docentes']); ?>)</h3>
						</div>
						<div class="gnf-section__body gnf-section__body--table">
							<table class="gnf-table">
								<thead>
									<tr>
										<th>Nombre</th>
										<th>Email</th>
										<th>Centro</th>
										<th>Fecha</th>
										<th>Acciones</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($data['pending_docentes'] as $user_item) :
										$centro_id = get_user_meta($user_item->ID, 'gnf_centro_id', true);
										$centro_name = $centro_id ? get_the_title($centro_id) : '—';
									?>
										<tr>
											<td><strong><?php echo esc_html($user_item->display_name); ?></strong></td>
											<td><?php echo esc_html($user_item->user_email); ?></td>
											<td><?php echo esc_html($centro_name); ?></td>
											<td><?php echo esc_html(date_i18n('d/m/Y', strtotime($user_item->user_registered))); ?></td>
											<td class="gnf-table__actions">
												<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
													<?php wp_nonce_field('gnf_admin_action'); ?>
													<input type="hidden" name="action" value="gnf_admin_panel_action" />
													<input type="hidden" name="admin_action" value="aprobar_docente" />
													<input type="hidden" name="user_id" value="<?php echo esc_attr($user_item->ID); ?>" />
													<button type="submit" class="gnf-btn gnf-btn--sm"><?php echo $icons['check']; ?> Aprobar</button>
												</form>
												<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
													<?php wp_nonce_field('gnf_admin_action'); ?>
													<input type="hidden" name="action" value="gnf_admin_panel_action" />
													<input type="hidden" name="admin_action" value="rechazar_docente" />
													<input type="hidden" name="user_id" value="<?php echo esc_attr($user_item->ID); ?>" />
													<button type="submit" class="gnf-btn gnf-btn--sm gnf-btn--danger"><?php echo $icons['x']; ?> Rechazar</button>
												</form>
												<a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=gnf_impersonate&user_id=' . $user_item->ID), 'gnf_impersonate')); ?>" class="gnf-btn gnf-btn--sm gnf-btn--ghost" title="Entrar como este usuario">Ver como</a>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					</div>
				<?php endif; ?>

				<!-- Pending Supervisors -->
				<?php if (! empty($data['pending_supervisors'])) : ?>
					<div class="gnf-section gnf-mb-6">
						<div class="gnf-section__header gnf-section__header--info">
							<h3 class="gnf-section__title">Supervisores Pendientes (<?php echo count($data['pending_supervisors']); ?>)</h3>
						</div>
						<div class="gnf-section__body gnf-section__body--table">
							<table class="gnf-table">
								<thead>
									<tr>
										<th>Nombre</th>
										<th>Email</th>
										<th>Región</th>
										<th>Teléfono</th>
										<th>Fecha</th>
										<th>Acciones</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($data['pending_supervisors'] as $user_item) :
										$region_id = get_user_meta($user_item->ID, 'gnf_region', true);
										$region_term = $region_id ? get_term($region_id, 'gn_region') : null;
										$region_name = ($region_term && ! is_wp_error($region_term)) ? $region_term->name : '—';
										$phone = get_user_meta($user_item->ID, 'gnf_telefono', true);
									?>
										<tr>
											<td><strong><?php echo esc_html($user_item->display_name); ?></strong></td>
											<td><?php echo esc_html($user_item->user_email); ?></td>
											<td><?php echo esc_html($region_name); ?></td>
											<td><?php echo esc_html($phone ?: '—'); ?></td>
											<td><?php echo esc_html(date_i18n('d/m/Y', strtotime($user_item->user_registered))); ?></td>
											<td class="gnf-table__actions">
												<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
													<?php wp_nonce_field('gnf_admin_action'); ?>
													<input type="hidden" name="action" value="gnf_admin_panel_action" />
													<input type="hidden" name="admin_action" value="aprobar_supervisor" />
													<input type="hidden" name="user_id" value="<?php echo esc_attr($user_item->ID); ?>" />
													<button type="submit" class="gnf-btn gnf-btn--sm gnf-btn--ocean"><?php echo $icons['check']; ?> Aprobar</button>
												</form>
												<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
													<?php wp_nonce_field('gnf_admin_action'); ?>
													<input type="hidden" name="action" value="gnf_admin_panel_action" />
													<input type="hidden" name="admin_action" value="rechazar_supervisor" />
													<input type="hidden" name="user_id" value="<?php echo esc_attr($user_item->ID); ?>" />
													<button type="submit" class="gnf-btn gnf-btn--sm gnf-btn--danger"><?php echo $icons['x']; ?> Rechazar</button>
												</form>
												<a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=gnf_impersonate&user_id=' . $user_item->ID), 'gnf_impersonate')); ?>" class="gnf-btn gnf-btn--sm gnf-btn--ghost" title="Entrar como este usuario">Ver como</a>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					</div>
				<?php endif; ?>

				<!-- All Users Summary -->
				<div class="gnf-section gnf-mb-6">
					<div class="gnf-section__header">
						<h3 class="gnf-section__title">Resumen de Usuarios</h3>
						<a href="<?php echo esc_url(admin_url('user-new.php')); ?>" class="gnf-btn gnf-btn--sm"><?php echo $icons['plus']; ?> Nuevo Usuario</a>
					</div>
					<div class="gnf-section__body">
						<div class="gnf-stats-grid gnf-stats-grid--3">
							<div class="gnf-stat-card">
								<div class="gnf-stat-card__icon gnf-stat-card__icon--forest"><?php echo $icons['users']; ?></div>
								<div class="gnf-stat-card__value"><?php echo count($data['all_users']['docentes'] ?? array()); ?></div>
								<div class="gnf-stat-card__label">Docentes</div>
							</div>
							<div class="gnf-stat-card">
								<div class="gnf-stat-card__icon gnf-stat-card__icon--ocean"><?php echo $icons['search']; ?></div>
								<div class="gnf-stat-card__value"><?php echo count($data['all_users']['supervisors'] ?? array()); ?></div>
								<div class="gnf-stat-card__label">Supervisores</div>
							</div>
							<div class="gnf-stat-card">
								<div class="gnf-stat-card__icon gnf-stat-card__icon--sun"><?php echo $icons['building']; ?></div>
								<div class="gnf-stat-card__value"><?php echo count($data['all_users']['comite'] ?? array()); ?></div>
								<div class="gnf-stat-card__label">Comité BAE</div>
							</div>
						</div>
					</div>
				</div>

				<!-- Docentes Table -->
				<div class="gnf-section gnf-mb-6">
					<div class="gnf-section__header">
						<h3 class="gnf-section__title">Docentes (<?php echo count($data['all_users']['docentes'] ?? array()); ?>)</h3>
					</div>
					<div class="gnf-section__body gnf-section__body--table">
						<table class="gnf-table">
							<thead>
								<tr>
									<th>Nombre</th>
									<th>Email</th>
									<th>Centro Educativo</th>
									<th>Estado</th>
									<th>Acciones</th>
								</tr>
							</thead>
							<tbody>
								<?php if (! empty($data['all_users']['docentes'])) : ?>
									<?php foreach ($data['all_users']['docentes'] as $docente) :
										$centro_id = get_user_meta($docente->ID, 'gnf_centro_id', true);
										$centro_name = $centro_id ? get_the_title($centro_id) : '—';
										$doc_status = get_user_meta($docente->ID, 'gnf_docente_status', true) ?: 'activo';
									?>
										<tr>
											<td><strong><?php echo esc_html($docente->display_name); ?></strong></td>
											<td><?php echo esc_html($docente->user_email); ?></td>
											<td>
												<?php if ($centro_id) : ?>
													<a href="<?php echo esc_url(add_query_arg(array('tab' => 'centros', 'centro_id' => $centro_id), $current_url)); ?>"><?php echo esc_html($centro_name); ?></a>
												<?php else : ?>
													—
												<?php endif; ?>
											</td>
											<td>
												<span class="gnf-badge gnf-badge--<?php echo 'activo' === $doc_status ? 'forest' : 'sun'; ?>">
													<?php echo esc_html(ucfirst($doc_status)); ?>
												</span>
											</td>
											<td class="gnf-table__actions">
												<a href="<?php echo esc_url(get_edit_user_link($docente->ID)); ?>" class="gnf-btn gnf-btn--sm gnf-btn--ghost">Editar</a>
												<a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=gnf_impersonate&user_id=' . $docente->ID), 'gnf_impersonate')); ?>" class="gnf-btn gnf-btn--sm gnf-btn--ghost" title="Entrar como este usuario">Ver como</a>
											</td>
										</tr>
									<?php endforeach; ?>
								<?php else : ?>
									<tr>
										<td colspan="5" class="gnf-table__empty">No hay docentes registrados.</td>
									</tr>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
				</div>

				<!-- Supervisors Table -->
				<div class="gnf-section gnf-mb-6">
					<div class="gnf-section__header">
						<h3 class="gnf-section__title">Supervisores (<?php echo count($data['all_users']['supervisors'] ?? array()); ?>)</h3>
					</div>
					<div class="gnf-section__body gnf-section__body--table">
						<table class="gnf-table">
							<thead>
								<tr>
									<th>Nombre</th>
									<th>Email</th>
									<th>Región</th>
									<th>Estado</th>
									<th>Acciones</th>
								</tr>
							</thead>
							<tbody>
								<?php if (! empty($data['all_users']['supervisors'])) : ?>
									<?php foreach ($data['all_users']['supervisors'] as $sup) :
										$region_id = get_user_meta($sup->ID, 'gnf_region', true);
										$region_term = $region_id ? get_term($region_id, 'gn_region') : null;
										$region_name = ($region_term && ! is_wp_error($region_term)) ? $region_term->name : '—';
										$sup_status = get_user_meta($sup->ID, 'gnf_supervisor_status', true) ?: 'activo';
									?>
										<tr>
											<td><strong><?php echo esc_html($sup->display_name); ?></strong></td>
											<td><?php echo esc_html($sup->user_email); ?></td>
											<td><?php echo esc_html($region_name); ?></td>
											<td>
												<span class="gnf-badge gnf-badge--<?php echo 'activo' === $sup_status ? 'ocean' : 'sun'; ?>">
													<?php echo esc_html(ucfirst($sup_status)); ?>
												</span>
											</td>
											<td class="gnf-table__actions">
												<a href="<?php echo esc_url(get_edit_user_link($sup->ID)); ?>" class="gnf-btn gnf-btn--sm gnf-btn--ghost">Editar</a>
												<a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=gnf_impersonate&user_id=' . $sup->ID), 'gnf_impersonate')); ?>" class="gnf-btn gnf-btn--sm gnf-btn--ghost" title="Entrar como este usuario">Ver como</a>
											</td>
										</tr>
									<?php endforeach; ?>
								<?php else : ?>
									<tr>
										<td colspan="5" class="gnf-table__empty">No hay supervisores registrados.</td>
									</tr>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
				</div>

				<!-- Comité BAE Table -->
				<div class="gnf-section">
					<div class="gnf-section__header">
						<h3 class="gnf-section__title">Comité BAE (<?php echo count($data['all_users']['comite'] ?? array()); ?>)</h3>
					</div>
					<div class="gnf-section__body gnf-section__body--table">
						<table class="gnf-table">
							<thead>
								<tr>
									<th>Nombre</th>
									<th>Email</th>
									<th>Acciones</th>
								</tr>
							</thead>
							<tbody>
								<?php if (! empty($data['all_users']['comite'])) : ?>
									<?php foreach ($data['all_users']['comite'] as $comite_user) : ?>
										<tr>
											<td><strong><?php echo esc_html($comite_user->display_name); ?></strong></td>
											<td><?php echo esc_html($comite_user->user_email); ?></td>
											<td class="gnf-table__actions">
												<a href="<?php echo esc_url(get_edit_user_link($comite_user->ID)); ?>" class="gnf-btn gnf-btn--sm gnf-btn--ghost">Editar</a>
												<a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=gnf_impersonate&user_id=' . $comite_user->ID), 'gnf_impersonate')); ?>" class="gnf-btn gnf-btn--sm gnf-btn--ghost" title="Entrar como este usuario">Ver como</a>
											</td>
										</tr>
									<?php endforeach; ?>
								<?php else : ?>
									<tr>
										<td colspan="3" class="gnf-table__empty">No hay miembros del Comité BAE.</td>
									</tr>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
				</div>

			<?php elseif ('centros' === $active_tab) : ?>
				<!-- CENTROS TAB -->
				<?php
				$detail_centro_id = isset($_GET['centro_id']) ? absint($_GET['centro_id']) : 0;
				if ($detail_centro_id) :
					// ========== CENTRO DETAIL VIEW ==========
					$detail_centro = get_post($detail_centro_id);
					if ($detail_centro) :
						$centro_codigo = get_post_meta($detail_centro_id, 'codigo_mep', true);
						$centro_region_id = get_post_meta($detail_centro_id, 'region', true);
						$centro_region_term = $centro_region_id ? get_term($centro_region_id, 'gn_region') : null;
						$centro_region_name = ($centro_region_term && ! is_wp_error($centro_region_term)) ? $centro_region_term->name : '—';
						$centro_puntaje = gnf_get_centro_puntaje_total($detail_centro_id, $stats['anio']);
						$centro_estrella = gnf_get_centro_estrella_final($detail_centro_id, $stats['anio']);
						$centro_estado = get_post_meta($detail_centro_id, 'estado_centro', true) ?: 'pendiente';

						// Get reto entries for this centro
						global $wpdb;
						$table_entries = $wpdb->prefix . 'gn_reto_entries';
						$centro_entries = $wpdb->get_results($wpdb->prepare(
							"SELECT e.*, r.post_title as reto_nombre
							 FROM {$table_entries} e
							 LEFT JOIN {$wpdb->posts} r ON e.reto_id = r.ID
							 WHERE e.centro_id = %d AND e.anio = %d
							 ORDER BY r.menu_order ASC",
							$detail_centro_id,
							$stats['anio']
						));

						// Get docentes for this centro
						$centro_docentes = get_users(array(
							'meta_key'   => 'gnf_centro_id',
							'meta_value' => $detail_centro_id,
						));
				?>
						<div class="gnf-page-header">
							<div>
								<h1 class="gnf-page-title"><?php echo esc_html($detail_centro->post_title); ?></h1>
								<p class="gnf-page-subtitle">
									<?php echo esc_html($centro_region_name); ?>
									<?php if ($centro_codigo) : ?> • Código MEP: <?php echo esc_html($centro_codigo); ?><?php endif; ?>
								</p>
							</div>
							<a href="<?php echo esc_url(remove_query_arg('centro_id')); ?>" class="gnf-btn gnf-btn--ghost"><?php echo $icons['home']; ?> Volver a Lista</a>
						</div>

						<!-- Centro Stats -->
						<div class="gnf-stats-grid gnf-stats-grid--4 gnf-mb-6">
							<div class="gnf-stat-card">
								<div class="gnf-stat-card__icon gnf-stat-card__icon--ocean"><?php echo $icons['target']; ?></div>
								<div class="gnf-stat-card__value"><?php echo count($centro_entries); ?></div>
								<div class="gnf-stat-card__label">Retos</div>
							</div>
							<div class="gnf-stat-card">
								<div class="gnf-stat-card__icon gnf-stat-card__icon--forest"><?php echo $icons['check']; ?></div>
								<div class="gnf-stat-card__value"><?php echo esc_html($centro_puntaje); ?></div>
								<div class="gnf-stat-card__label">Puntos</div>
							</div>
							<div class="gnf-stat-card">
								<div class="gnf-stat-card__icon gnf-stat-card__icon--sun"><?php echo $icons['trophy']; ?></div>
								<div class="gnf-stat-card__value"><?php echo $centro_estrella > 0 ? str_repeat('★', $centro_estrella) : '—'; ?></div>
								<div class="gnf-stat-card__label">Estrellas</div>
							</div>
							<div class="gnf-stat-card">
								<div class="gnf-stat-card__icon gnf-stat-card__icon--coral"><?php echo $icons['users']; ?></div>
								<div class="gnf-stat-card__value"><?php echo count($centro_docentes); ?></div>
								<div class="gnf-stat-card__label">Docentes</div>
							</div>
						</div>

						<!-- Retos Table -->
						<div class="gnf-section gnf-mb-6">
							<div class="gnf-section__header">
								<h3 class="gnf-section__title">Retos del Centro - Año <?php echo esc_html($stats['anio']); ?></h3>
							</div>
							<div class="gnf-section__body gnf-section__body--table">
								<table class="gnf-table">
									<thead>
										<tr>
											<th>Reto</th>
											<th>Estado</th>
											<th>Puntaje</th>
											<th>Evidencias</th>
											<th>Notas Supervisor</th>
											<th>Última Actualización</th>
										</tr>
									</thead>
									<tbody>
										<?php if (! empty($centro_entries)) : ?>
											<?php foreach ($centro_entries as $entry) : ?>
												<tr>
													<td><strong><?php echo esc_html($entry->reto_nombre ?: 'Reto #' . $entry->reto_id); ?></strong></td>
													<td>
														<span class="gnf-badge gnf-badge--<?php echo esc_attr(gnf_get_estado_badge_color($entry->estado)); ?>">
															<?php echo esc_html(ucwords(str_replace('_', ' ', $entry->estado))); ?>
														</span>
													</td>
													<td><?php echo esc_html($entry->puntaje); ?> pts</td>
													<td>
														<?php
														$evidencias = json_decode($entry->evidencias, true);
														if (! empty($evidencias)) {
															foreach ($evidencias as $ev) {
																$url = ! empty($ev['path_local'])
																	? add_query_arg(array(
																		'action'    => 'gnf_descargar_evidencia',
																		'nonce'     => wp_create_nonce('gnf_nonce'),
																		'file'      => base64_encode($ev['path_local']),
																		'centro_id' => $detail_centro_id,
																	), admin_url('admin-ajax.php'))
																	: ($ev['ruta'] ?? '');
																echo '<a class="gnf-link" target="_blank" href="' . esc_url($url) . '">' . esc_html($ev['nombre'] ?? 'Archivo') . '</a><br />';
															}
														} else {
															echo '<em class="gnf-muted">Sin evidencias</em>';
														}
														?>
													</td>
													<td><?php echo $entry->supervisor_notes ? esc_html($entry->supervisor_notes) : '<em class="gnf-muted">—</em>'; ?></td>
													<td><?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($entry->updated_at))); ?></td>
												</tr>
											<?php endforeach; ?>
										<?php else : ?>
											<tr>
												<td colspan="6" class="gnf-table__empty">Este centro no tiene retos registrados para el año <?php echo esc_html($stats['anio']); ?>.</td>
											</tr>
										<?php endif; ?>
									</tbody>
								</table>
							</div>
						</div>

						<!-- Docentes del Centro -->
						<div class="gnf-section">
							<div class="gnf-section__header">
								<h3 class="gnf-section__title">Docentes del Centro</h3>
							</div>
							<div class="gnf-section__body gnf-section__body--table">
								<table class="gnf-table">
									<thead>
										<tr>
											<th>Nombre</th>
											<th>Email</th>
											<th>Estado</th>
											<th>Registrado</th>
										</tr>
									</thead>
									<tbody>
										<?php if (! empty($centro_docentes)) : ?>
											<?php foreach ($centro_docentes as $doc) :
												$doc_status = get_user_meta($doc->ID, 'gnf_docente_status', true) ?: 'activo';
											?>
												<tr>
													<td><strong><?php echo esc_html($doc->display_name); ?></strong></td>
													<td><?php echo esc_html($doc->user_email); ?></td>
													<td>
														<span class="gnf-badge gnf-badge--<?php echo 'activo' === $doc_status ? 'forest' : 'sun'; ?>">
															<?php echo esc_html(ucfirst($doc_status)); ?>
														</span>
													</td>
													<td><?php echo esc_html(date_i18n('d/m/Y', strtotime($doc->user_registered))); ?></td>
												</tr>
											<?php endforeach; ?>
										<?php else : ?>
											<tr>
												<td colspan="4" class="gnf-table__empty">No hay docentes asignados a este centro.</td>
											</tr>
										<?php endif; ?>
									</tbody>
								</table>
							</div>
						</div>

					<?php else : ?>
						<div class="gnf-alert gnf-alert--error">Centro no encontrado.</div>
						<a href="<?php echo esc_url(remove_query_arg('centro_id')); ?>" class="gnf-btn">Volver a Lista</a>
					<?php endif; ?>
				<?php else : ?>
					<!-- ========== CENTROS LIST VIEW ========== -->
					<div class="gnf-page-header">
						<div>
							<h1 class="gnf-page-title">Centros Educativos</h1>
							<p class="gnf-page-subtitle">Gestiona los centros participantes</p>
						</div>
						<a href="<?php echo esc_url(admin_url('post-new.php?post_type=centro_educativo')); ?>" class="gnf-btn"><?php echo $icons['plus']; ?> Nuevo Centro</a>
					</div>

					<!-- Filters -->
					<div class="gnf-filter-bar">
						<form method="get" class="gnf-filter-form">
							<input type="hidden" name="tab" value="centros" />

							<div class="gnf-filter-bar__group">
								<label class="gnf-filter-bar__label">Región:</label>
								<select name="region" class="gnf-select">
									<option value="">Todas</option>
									<?php
									$regions = get_terms(array('taxonomy' => 'gn_region', 'hide_empty' => false));
									$selected_region = isset($_GET['region']) ? absint($_GET['region']) : 0;
									foreach ($regions as $region) : ?>
										<option value="<?php echo esc_attr($region->term_id); ?>" <?php selected($selected_region, $region->term_id); ?>>
											<?php echo esc_html($region->name); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>

							<div class="gnf-filter-bar__group">
								<label class="gnf-filter-bar__label">Estado:</label>
								<select name="estado" class="gnf-select">
									<option value="">Todos</option>
									<option value="activo" <?php selected($_GET['estado'] ?? '', 'activo'); ?>>Activo</option>
									<option value="pendiente_de_revision_admin" <?php selected($_GET['estado'] ?? '', 'pendiente_de_revision_admin'); ?>>Pendiente</option>
								</select>
							</div>

							<div class="gnf-filter-bar__group gnf-filter-bar__search">
								<input type="text" name="s" class="gnf-input" placeholder="Buscar centro..." value="<?php echo esc_attr($_GET['s'] ?? ''); ?>" />
							</div>

							<button type="submit" class="gnf-btn">Filtrar</button>
						</form>
					</div>

					<!-- Centros Table -->
					<?php $centros = $data['centros']; ?>
					<div class="gnf-section">
						<div class="gnf-section__body gnf-section__body--table">
							<table class="gnf-table">
								<thead>
									<tr>
										<th>Centro</th>
										<th>Código MEP</th>
										<th>Región</th>
										<th>Puntaje</th>
										<th>Estrella</th>
										<th>Estado</th>
										<th>Acciones</th>
									</tr>
								</thead>
								<tbody>
									<?php if ($centros->have_posts()) : ?>
										<?php while ($centros->have_posts()) : $centros->the_post();
											$centro_id = get_the_ID();
											$codigo = get_post_meta($centro_id, 'codigo_mep', true);
											$region_id = get_post_meta($centro_id, 'region', true);
											$region_term = $region_id ? get_term($region_id, 'gn_region') : null;
											$region_name = ($region_term && ! is_wp_error($region_term)) ? $region_term->name : '—';
											$puntaje = gnf_get_centro_puntaje_total($centro_id, $stats['anio']);
											$estrella = gnf_get_centro_estrella_final($centro_id, $stats['anio']);
											$estado = get_post_meta($centro_id, 'estado_centro', true) ?: 'activo';
										?>
											<tr>
												<td><strong><?php the_title(); ?></strong></td>
												<td><?php echo esc_html($codigo ?: '—'); ?></td>
												<td><?php echo esc_html($region_name); ?></td>
												<td><?php echo esc_html($puntaje); ?> pts</td>
												<td class="gnf-stars"><?php echo $estrella > 0 ? str_repeat('<span class="gnf-star">★</span>', $estrella) : '—'; ?></td>
												<td>
													<span class="gnf-badge gnf-badge--<?php echo 'activo' === $estado ? 'forest' : 'sun'; ?>">
														<?php echo esc_html($estado); ?>
													</span>
												</td>
												<td class="gnf-table__actions">
													<a href="<?php echo esc_url(add_query_arg(array('tab' => 'centros', 'centro_id' => $centro_id), $current_url)); ?>" class="gnf-btn gnf-btn--sm">Ver Detalle</a>
													<a href="<?php echo esc_url(get_edit_post_link($centro_id)); ?>" class="gnf-btn gnf-btn--sm gnf-btn--ghost">Editar</a>
												</td>
											</tr>
										<?php endwhile;
										wp_reset_postdata(); ?>
									<?php else : ?>
										<tr>
											<td colspan="7" class="gnf-table__empty">No se encontraron centros.</td>
										</tr>
									<?php endif; ?>
								</tbody>
							</table>
						</div>
					</div>

					<!-- Pagination -->
					<?php if ($centros->max_num_pages > 1) : ?>
						<div class="gnf-pagination">
							<?php
							$current_page = max(1, get_query_var('paged'));
							if ($current_page > 1) : ?>
								<a href="<?php echo esc_url(add_query_arg('paged', $current_page - 1)); ?>" class="gnf-btn gnf-btn--ghost">← Anterior</a>
							<?php else : ?>
								<span></span>
							<?php endif; ?>

							<span class="gnf-pagination__info">Página <?php echo esc_html($current_page); ?> de <?php echo esc_html($centros->max_num_pages); ?></span>

							<?php if ($current_page < $centros->max_num_pages) : ?>
								<a href="<?php echo esc_url(add_query_arg('paged', $current_page + 1)); ?>" class="gnf-btn gnf-btn--ghost">Siguiente →</a>
							<?php else : ?>
								<span></span>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				<?php endif; ?><!-- end centro_id check -->

			<?php elseif ('retos' === $active_tab) : ?>
				<!-- RETOS TAB -->
				<div class="gnf-page-header">
					<div>
						<h1 class="gnf-page-title">Eco Retos</h1>
						<p class="gnf-page-subtitle">Estadísticas de participación por reto - Año <?php echo esc_html($stats['anio']); ?></p>
					</div>
					<a href="<?php echo esc_url(admin_url('post-new.php?post_type=reto')); ?>" class="gnf-btn"><?php echo $icons['plus']; ?> Nuevo Reto</a>
				</div>

				<div class="gnf-retos-grid">
					<?php foreach ($data['retos'] as $item) :
						$reto = $item['reto'];
						$reto_stats = $item['stats'];
						$icono = gnf_get_reto_icon_url($reto->ID);
						$reto_color_admin = gnf_get_reto_color($reto->ID);
						$puntaje_max = gnf_get_reto_max_points($reto->ID, $data['anio']);
						$default_icon = GNF_URL . 'assets/logo-guardiana.png';
					?>
						<div class="gnf-reto-card" style="border-top: 3px solid <?php echo esc_attr($reto_color_admin); ?>;">
							<div class="gnf-reto-card__header">
								<span style="display:inline-flex;align-items:center;justify-content:center;width:48px;height:48px;border-radius:10px;background:<?php echo esc_attr($reto_color_admin); ?>1a;flex-shrink:0;">
									<img src="<?php echo esc_url($icono ?: $default_icon); ?>" alt="" class="gnf-reto-card__icon" style="width: 36px; height: 36px; object-fit: contain;" />
								</span>
								<div>
									<h4 class="gnf-reto-card__title" style="font-size: 1rem; margin: 0;"><?php echo esc_html($reto->post_title); ?></h4>
									<p class="gnf-reto-card__meta" style="margin: 4px 0 0; font-size: 0.8rem;"><?php echo esc_html($puntaje_max); ?> pts máximo</p>
								</div>
							</div>

							<div class="gnf-reto-card__stats" style="display: flex; gap: 16px; justify-content: center;">
								<div class="gnf-reto-stat" style="text-align: center;">
									<span class="gnf-reto-stat__value" style="font-size: 1.5rem; font-weight: 700; display: block;"><?php echo esc_html($reto_stats->total ?? 0); ?></span>
									<span class="gnf-reto-stat__label" style="font-size: 0.75rem; color: #64748b;">Participantes</span>
								</div>
								<div class="gnf-reto-stat gnf-reto-stat--success" style="text-align: center;">
									<span class="gnf-reto-stat__value" style="font-size: 1.5rem; font-weight: 700; display: block; color: var(--gnf-forest);"><?php echo esc_html($reto_stats->aprobados ?? 0); ?></span>
									<span class="gnf-reto-stat__label" style="font-size: 0.75rem; color: #64748b;">Aprobados</span>
								</div>
							</div>

							<div class="gnf-reto-card__footer" style="margin-top: 12px; display: flex; gap: 8px; justify-content: center;">
								<a href="<?php echo esc_url(get_edit_post_link($reto->ID)); ?>" class="gnf-btn gnf-btn--sm gnf-btn--ghost">Editar</a>
								<a href="<?php echo esc_url(add_query_arg(array('tab' => 'centros', 'reto_filter' => $reto->ID), $current_url)); ?>" class="gnf-btn gnf-btn--sm gnf-btn--ghost">Ver Centros</a>
							</div>
						</div>
					<?php endforeach; ?>
				</div>

			<?php elseif ('reportes' === $active_tab) : ?>
				<!-- REPORTES TAB -->
				<div class="gnf-page-header">
					<div>
						<h1 class="gnf-page-title">Reportes</h1>
						<p class="gnf-page-subtitle">Análisis y exportación de datos - Año <?php echo esc_html($data['report_data']['anio']); ?></p>
					</div>
					<a href="<?php echo esc_url(admin_url('admin-post.php?action=gnf_export_csv&year=' . $data['report_data']['anio'])); ?>" class="gnf-btn"><?php echo $icons['download']; ?> Exportar CSV</a>
				</div>

				<!-- Top Centros -->
				<div class="gnf-section gnf-mb-6">
					<div class="gnf-section__header">
						<h3 class="gnf-section__title">Top 10 Centros por Puntaje</h3>
					</div>
					<div class="gnf-section__body gnf-section__body--table">
						<table class="gnf-table">
							<thead>
								<tr>
									<th>#</th>
									<th>Centro</th>
									<th>Puntaje</th>
									<th>Estrella</th>
								</tr>
							</thead>
							<tbody>
								<?php $rank = 1;
								foreach ($data['report_data']['top_centros'] as $centro) : ?>
									<tr>
										<td><strong><?php echo esc_html($rank++); ?></strong></td>
										<td><?php echo esc_html($centro->post_title); ?></td>
										<td><strong><?php echo esc_html($centro->puntaje_total_anual ?? 0); ?></strong> pts</td>
										<td class="gnf-stars"><?php $e = (int) ($centro->estrella_anual ?? 0);
																echo $e > 0 ? str_repeat('<span class="gnf-star">★</span>', $e) : '—'; ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>

				<!-- Por Región -->
				<div class="gnf-section">
					<div class="gnf-section__header">
						<h3 class="gnf-section__title">Resumen por Región</h3>
					</div>
					<div class="gnf-section__body gnf-section__body--table">
						<table class="gnf-table">
							<thead>
								<tr>
									<th>Región</th>
									<th>Centros</th>
									<th>Aprobados</th>
									<th>Pendientes</th>
									<th>Exportar</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($data['report_data']['por_region'] as $region) : ?>
									<tr>
										<td><strong><?php echo esc_html($region->region ?: 'Sin región'); ?></strong></td>
										<td><?php echo esc_html($region->centros); ?></td>
										<td><span class="gnf-badge gnf-badge--forest"><?php echo esc_html($region->aprobados ?? 0); ?></span></td>
										<td><span class="gnf-badge gnf-badge--sun"><?php echo esc_html($region->pendientes ?? 0); ?></span></td>
										<td>
											<?php if ($region->term_id) : ?>
												<a href="<?php echo esc_url(admin_url('admin-post.php?action=gnf_export_csv&year=' . $data['report_data']['anio'] . '&region=' . $region->term_id)); ?>" class="gnf-btn gnf-btn--sm gnf-btn--ghost">CSV</a>
											<?php endif; ?>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>

			<?php elseif ('configuracion' === $active_tab) : ?>
				<!-- CONFIGURACION TAB -->
				<div class="gnf-page-header">
					<div>
						<h1 class="gnf-page-title">Configuración</h1>
						<p class="gnf-page-subtitle">Ajustes generales del plugin</p>
					</div>
				</div>

				<div class="gnf-section">
					<div class="gnf-section__body">
						<p class="gnf-muted gnf-mb-6">La configuración avanzada está disponible en el panel de WordPress:</p>

						<div class="gnf-config-grid">
							<a href="<?php echo esc_url(admin_url('admin.php?page=guardianes-config')); ?>" class="gnf-config-card">
								<span class="gnf-config-card__icon"><?php echo $icons['settings']; ?></span>
								<div>
									<h3 class="gnf-config-card__title">Configuración General</h3>
									<p class="gnf-config-card__desc">Año activo, formularios, rangos de estrella</p>
								</div>
							</a>

							<a href="<?php echo esc_url(admin_url('edit-tags.php?taxonomy=gn_region&post_type=centro_educativo')); ?>" class="gnf-config-card">
								<span class="gnf-config-card__icon"><?php echo $icons['map']; ?></span>
								<div>
									<h3 class="gnf-config-card__title">Direcciones Regionales</h3>
									<p class="gnf-config-card__desc">Gestionar regiones</p>
								</div>
							</a>

							<a href="<?php echo esc_url(admin_url('admin.php?page=wpforms-overview')); ?>" class="gnf-config-card">
								<span class="gnf-config-card__icon"><?php echo $icons['target']; ?></span>
								<div>
									<h3 class="gnf-config-card__title">WPForms</h3>
									<p class="gnf-config-card__desc">Gestionar formularios</p>
								</div>
							</a>

							<a href="<?php echo esc_url(admin_url('users.php')); ?>" class="gnf-config-card">
								<span class="gnf-config-card__icon"><?php echo $icons['users']; ?></span>
								<div>
									<h3 class="gnf-config-card__title">Usuarios WP</h3>
									<p class="gnf-config-card__desc">Panel nativo de usuarios</p>
								</div>
							</a>
						</div>
					</div>
				</div>

				<!-- Direcciones Regionales: Activar/Desactivar para Pilotaje -->
				<?php if ( ! empty( $regiones_dre ) ) : ?>
				<div class="gnf-section" style="margin-top: 24px;">
					<div class="gnf-section__header">
						<h3 class="gnf-section__title">Direcciones Regionales — Pilotaje</h3>
					</div>
					<div class="gnf-section__body">
						<p class="gnf-muted" style="margin-bottom: 16px;">
							Activa o desactiva Direcciones Regionales para el pilotaje. Solo las DRE activas aparecerán disponibles en el buscador de centros.
						</p>
						<table class="gnf-table">
							<thead>
								<tr>
									<th>Dirección Regional</th>
									<th style="text-align: center;">Estado</th>
									<th style="text-align: center;">Acción</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $regiones_dre as $dre ) : ?>
									<tr>
										<td><strong><?php echo esc_html( $dre['name'] ); ?></strong></td>
										<td style="text-align: center;">
											<span class="gnf-badge gnf-badge--<?php echo $dre['activa'] ? 'forest' : 'default'; ?>" id="gnf-dre-status-<?php echo esc_attr( $dre['term_id'] ); ?>">
												<?php echo $dre['activa'] ? 'Activa' : 'Inactiva'; ?>
											</span>
										</td>
										<td style="text-align: center;">
											<button type="button"
												class="gnf-btn gnf-btn--sm gnf-toggle-dre"
												data-term-id="<?php echo esc_attr( $dre['term_id'] ); ?>"
												data-activa="<?php echo $dre['activa'] ? '1' : '0'; ?>">
												<?php echo $dre['activa'] ? 'Desactivar' : 'Activar'; ?>
											</button>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>

				<script>
				(function() {
					document.querySelectorAll('.gnf-toggle-dre').forEach(function(btn) {
						btn.addEventListener('click', function() {
							var termId = this.getAttribute('data-term-id');
							var currentlyActive = this.getAttribute('data-activa') === '1';
							var newState = !currentlyActive;
							var button = this;

							button.disabled = true;
							button.textContent = 'Guardando...';

							var formData = new FormData();
							formData.append('action', 'gnf_toggle_dre');
							formData.append('nonce', '<?php echo wp_create_nonce( "gnf_nonce" ); ?>');
							formData.append('term_id', termId);
							formData.append('activa', newState ? '1' : '0');

							fetch('<?php echo esc_url( admin_url( "admin-ajax.php" ) ); ?>', {
								method: 'POST',
								body: formData,
								credentials: 'same-origin'
							})
							.then(function(r) { return r.json(); })
							.then(function(resp) {
								if (resp.success) {
									button.setAttribute('data-activa', newState ? '1' : '0');
									button.textContent = newState ? 'Desactivar' : 'Activar';
									var badge = document.getElementById('gnf-dre-status-' + termId);
									if (badge) {
										badge.className = 'gnf-badge gnf-badge--' + (newState ? 'forest' : 'default');
										badge.textContent = newState ? 'Activa' : 'Inactiva';
									}
								} else {
									alert(resp.data || 'Error al actualizar');
									button.textContent = currentlyActive ? 'Desactivar' : 'Activar';
								}
								button.disabled = false;
							})
							.catch(function() {
								alert('Error de red');
								button.textContent = currentlyActive ? 'Desactivar' : 'Activar';
								button.disabled = false;
							});
						});
					});
				})();
				</script>
				<?php endif; ?>

			<?php endif; ?>
		</div><!-- /.gnf-main__inner -->
	</main>
</div><!-- /.gnf-dashboard -->