<?php

/**
 * Template: Panel de Supervisor (lista de centros).
 *
 * Variables:
 * - $centros WP_Query
 * - $entries_by_centro array centro_id => [reto_id => entry]
 * - $anio int
 * - $anio_activo int
 * - $years_available array
 */

$anio_activo = isset($anio_activo) ? $anio_activo : $anio;
$years_available = isset($years_available) ? $years_available : array($anio);
$user = wp_get_current_user();

// Calcular estadísticas globales.
$total_centros       = $centros->found_posts;
$total_retos_enviados = 0;
$total_retos_aprobados = 0;
$total_retos_correccion = 0;

foreach ($entries_by_centro as $centro_entries) {
	foreach ($centro_entries as $entry) {
		if ('enviado' === $entry->estado) {
			$total_retos_enviados++;
		} elseif ('aprobado' === $entry->estado) {
			$total_retos_aprobados++;
		} elseif ('correccion' === $entry->estado) {
			$total_retos_correccion++;
		}
	}
}

$region_name = '';
if ($region_slug) {
	$term = get_term($region_slug, 'gn_region');
	$region_name = ($term && ! is_wp_error($term)) ? $term->name : '';
}

// SVG icons.
$icons = array(
	'home'     => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
	'building' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01"/><path d="M16 6h.01"/><path d="M12 6h.01"/><path d="M12 10h.01"/><path d="M12 14h.01"/><path d="M16 10h.01"/><path d="M16 14h.01"/><path d="M8 10h.01"/><path d="M8 14h.01"/></svg>',
	'check'    => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>',
	'clock'    => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
	'alert'    => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
	'download' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>',
);
?>
<div class="gnf-dashboard">
	<!-- Sidebar -->
	<aside class="gnf-sidebar">
		<div class="gnf-sidebar__logo">
			<img src="<?php echo esc_url(GNF_LOGO_URL); ?>" alt="Guardianes" style="height: 40px; width: auto; background: #fff; border-radius: 4px;" />
			<div>
				<small style="opacity: 0.7; font-size: 0.75rem;">Panel Supervisor</small>
			</div>
		</div>

		<nav class="gnf-sidebar__nav">
			<div class="gnf-sidebar__section">
				<div class="gnf-sidebar__label">Navegación</div>
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'notificaciones', remove_query_arg( array( 'centro_id', 'tab' ) ) ) ); ?>" class="gnf-sidebar__link">
					<span class="gnf-sidebar__icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg></span>
					Notificaciones
				</a>
			</div>
			<div class="gnf-sidebar__section">
				<div class="gnf-sidebar__label">Región</div>
				<div style="padding: 0 24px; margin-bottom: 16px;">
					<div style="background: rgba(255,255,255,0.1); border-radius: 8px; padding: 12px;">
						<strong style="font-size: 0.9rem;"><?php echo esc_html($region_name ?: 'Todas las regiones'); ?></strong>
					</div>
				</div>
			</div>

			<div class="gnf-sidebar__section">
				<div class="gnf-sidebar__label">Año</div>
				<div style="padding: 0 24px;">
					<select onchange="location.href = '<?php echo esc_url(remove_query_arg('gnf_year')); ?>&gnf_year=' + this.value" style="width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.2); background: rgba(255,255,255,0.1); color: #fff; font-weight: 500;">
						<?php foreach ($years_available as $y) : ?>
							<option value="<?php echo esc_attr($y); ?>" <?php selected($anio, $y); ?> style="color: #333;">
								<?php echo esc_html($y); ?><?php echo $y == $anio_activo ? ' (Activo)' : ''; ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<div class="gnf-sidebar__section">
				<div class="gnf-sidebar__label">Estadísticas</div>
				<div style="padding: 0 24px;">
					<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 8px;">
						<div style="text-align: center; padding: 12px; background: rgba(255,255,255,0.1); border-radius: 6px;">
							<strong style="display: block; font-size: 1.25rem;"><?php echo esc_html($total_centros); ?></strong>
							<small style="opacity: 0.7;">Centros</small>
						</div>
						<div style="text-align: center; padding: 12px; background: rgba(255,255,255,0.1); border-radius: 6px;">
							<strong style="display: block; font-size: 1.25rem; color: var(--gnf-sun);"><?php echo esc_html($total_retos_enviados); ?></strong>
							<small style="opacity: 0.7;">Pendientes</small>
						</div>
						<div style="text-align: center; padding: 12px; background: rgba(255,255,255,0.1); border-radius: 6px;">
							<strong style="display: block; font-size: 1.25rem; color: var(--gnf-leaf);"><?php echo esc_html($total_retos_aprobados); ?></strong>
							<small style="opacity: 0.7;">Aprobados</small>
						</div>
						<div style="text-align: center; padding: 12px; background: rgba(255,255,255,0.1); border-radius: 6px;">
							<strong style="display: block; font-size: 1.25rem; color: var(--gnf-coral);"><?php echo esc_html($total_retos_correccion); ?></strong>
							<small style="opacity: 0.7;">Corrección</small>
						</div>
					</div>
				</div>
			</div>

			<div class="gnf-sidebar__section">
				<div class="gnf-sidebar__label">Exportar</div>
				<a href="<?php echo esc_url(admin_url('admin-post.php?action=gnf_export_csv&year=' . $anio)); ?>" class="gnf-sidebar__link">
					<span class="gnf-sidebar__icon"><?php echo $icons['download']; ?></span>
					Exportar CSV
				</a>
				<?php if ($region_slug) : ?>
					<a href="<?php echo esc_url(admin_url('admin-post.php?action=gnf_export_csv&year=' . $anio . '&region=' . intval($region_slug))); ?>" class="gnf-sidebar__link">
						<span class="gnf-sidebar__icon"><?php echo $icons['download']; ?></span>
						Exportar mi región
					</a>
				<?php endif; ?>
			</div>
		</nav>

		<div class="gnf-sidebar__user">
			<div class="gnf-sidebar__avatar">
				<?php echo esc_html(strtoupper(substr($user->display_name, 0, 1))); ?>
			</div>
			<div>
				<div class="gnf-sidebar__username"><?php echo esc_html($user->display_name); ?></div>
				<div class="gnf-sidebar__role">Supervisor</div>
			</div>
		</div>
	</aside>

	<!-- Main Content -->
	<main class="gnf-main">
		<div class="gnf-main__inner">
			<!-- Page Header -->
			<div class="gnf-page-header">
				<div>
					<h1 class="gnf-page-title">Panel Supervisor</h1>
					<p class="gnf-page-subtitle">
						<?php if ($region_name) : ?>
							Dirección Regional: <?php echo esc_html($region_name); ?> — Año <?php echo esc_html($anio); ?>
						<?php else : ?>
							Todas las Direcciones Regionales — Año <?php echo esc_html($anio); ?>
						<?php endif; ?>
					</p>
				</div>
			</div>

			<!-- Stats Grid -->
			<div class="gnf-stats-grid gnf-mb-6">
				<div class="gnf-stat-card">
					<div class="gnf-stat-card__icon gnf-stat-card__icon--ocean"><?php echo $icons['building']; ?></div>
					<div class="gnf-stat-card__value"><?php echo esc_html($total_centros); ?></div>
					<div class="gnf-stat-card__label">Centros</div>
				</div>
				<div class="gnf-stat-card">
					<div class="gnf-stat-card__icon gnf-stat-card__icon--sun"><?php echo $icons['clock']; ?></div>
					<div class="gnf-stat-card__value"><?php echo esc_html($total_retos_enviados); ?></div>
					<div class="gnf-stat-card__label">Pendientes revisión</div>
					<?php if ($total_retos_enviados > 0) : ?>
						<span class="gnf-stat-card__trend gnf-stat-card__trend--up">Requiere atención</span>
					<?php endif; ?>
				</div>
				<div class="gnf-stat-card">
					<div class="gnf-stat-card__icon gnf-stat-card__icon--forest"><?php echo $icons['check']; ?></div>
					<div class="gnf-stat-card__value"><?php echo esc_html($total_retos_aprobados); ?></div>
					<div class="gnf-stat-card__label">Aprobados</div>
				</div>
				<div class="gnf-stat-card">
					<div class="gnf-stat-card__icon gnf-stat-card__icon--coral"><?php echo $icons['alert']; ?></div>
					<div class="gnf-stat-card__value"><?php echo esc_html($total_retos_correccion); ?></div>
					<div class="gnf-stat-card__label">En corrección</div>
				</div>
			</div>

			<!-- Filtro por Circuito -->
			<?php if ( ! empty( $circuitos_disponibles ) ) : ?>
				<div class="gnf-filter-bar" style="margin-bottom: 16px; display: flex; align-items: center; gap: 12px;">
					<label style="font-weight: 600; color: #1e3a5f;">Circuito:</label>
					<select class="gnf-select" onchange="location.href=this.value;">
						<option value="<?php echo esc_url( remove_query_arg( 'circuito' ) ); ?>" <?php selected( empty( $circuito_filter ) ); ?>>Todos los circuitos</option>
						<?php foreach ( $circuitos_disponibles as $circ ) : ?>
							<option value="<?php echo esc_url( add_query_arg( 'circuito', rawurlencode( $circ ) ) ); ?>" <?php selected( $circuito_filter, $circ ); ?>>
								<?php echo esc_html( $circ ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			<?php endif; ?>

			<!-- Centros Table -->
			<div class="gnf-section">
				<div class="gnf-section__header">
					<h3 class="gnf-section__title">Centros Educativos</h3>
				</div>
				<div class="gnf-section__body gnf-section__body--table">
					<table class="gnf-table">
						<thead>
							<tr>
								<th>Centro</th>
								<th>Código MEP</th>
								<th>Tipo</th>
								<th>Meta</th>
								<th>Puntaje</th>
								<th>Estrella</th>
								<th>Retos</th>
								<th>Estado</th>
								<th></th>
							</tr>
						</thead>
						<tbody>
							<?php if ($centros->have_posts()) : ?>
								<?php while ($centros->have_posts()) : $centros->the_post(); ?>
									<?php
									$centro_id        = get_the_ID();
									$code             = get_post_meta($centro_id, 'codigo_mep', true);
									$puntaje          = gnf_get_centro_puntaje_total($centro_id, $anio);
									$estrella         = gnf_get_centro_estrella_final($centro_id, $anio);
									$meta_estrellas   = gnf_get_centro_meta_estrellas($centro_id, $anio);
									$retos_matriculados = gnf_get_centro_retos_seleccionados($centro_id, $anio);
									$total_matriculados = is_array($retos_matriculados) ? count($retos_matriculados) : 0;
									$entries          = $entries_by_centro[$centro_id] ?? array();
									$completos        = 0;
									$enviados         = 0;
									$correccion       = 0;
									foreach ($entries as $entry) {
										if ('aprobado' === $entry->estado) {
											$completos++;
										} elseif ('enviado' === $entry->estado) {
											$enviados++;
										} elseif ('correccion' === $entry->estado) {
											$correccion++;
										}
									}
									$tiene_pendientes = $enviados > 0;
									?>
									<?php $dependencia = get_post_meta($centro_id, 'dependencia', true); ?>
									<tr<?php echo $tiene_pendientes ? ' class="gnf-table__row--highlight"' : ''; ?>>
										<td><strong><?php the_title(); ?></strong></td>
										<td><?php echo esc_html($code ?: '—'); ?></td>
										<td>
											<?php if ($dependencia) : ?>
												<span class="gnf-badge gnf-badge--sm"><?php echo esc_html($dependencia); ?></span>
											<?php else : ?>
												<span class="gnf-muted">—</span>
											<?php endif; ?>
										</td>
										<td class="gnf-stars"><?php echo $meta_estrellas ? str_repeat('<span class="gnf-star">★</span>', $meta_estrellas) : '—'; ?></td>
										<td><?php echo esc_html($puntaje); ?> pts</td>
										<td class="gnf-stars"><?php echo $estrella > 0 ? str_repeat('<span class="gnf-star">★</span>', $estrella) : '—'; ?></td>
										<td>
											<span class="gnf-badge gnf-badge--forest" title="Aprobados"><?php echo esc_html($completos); ?></span>
											<span class="gnf-badge gnf-badge--sun" title="Enviados"><?php echo esc_html($enviados); ?></span>
											<?php if ($correccion > 0) : ?>
												<span class="gnf-badge gnf-badge--coral" title="Corrección"><?php echo esc_html($correccion); ?></span>
											<?php endif; ?>
											<small class="gnf-muted">de <?php echo esc_html($total_matriculados); ?></small>
										</td>
										<td>
											<?php if ($enviados > 0) : ?>
												<span class="gnf-badge gnf-badge--sun"><?php echo esc_html($enviados); ?> por revisar</span>
											<?php elseif ($completos === $total_matriculados && $total_matriculados > 0) : ?>
												<span class="gnf-badge gnf-badge--forest">Completo</span>
											<?php else : ?>
												<span class="gnf-muted">En progreso</span>
											<?php endif; ?>
										</td>
										<td><a class="gnf-btn gnf-btn--sm gnf-btn--ghost" href="<?php echo esc_url(add_query_arg('centro_id', $centro_id)); ?>">Ver detalle</a></td>
										</tr>
									<?php endwhile;
								wp_reset_postdata(); ?>
								<?php else : ?>
									<tr>
										<td colspan="9" class="gnf-table__empty">No hay centros en tu región.</td>
									</tr>
								<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div><!-- /.gnf-main__inner -->
	</main>
</div><!-- /.gnf-dashboard -->