<?php
/**
 * Template: Panel Supervisor - Notificaciones.
 *
 * Variables:
 * - $notificaciones array
 * - $user WP_User
 * - $region_slug
 * - $anio, $anio_activo, $years_available
 */

$anio_activo = isset( $anio_activo ) ? $anio_activo : ( isset( $anio ) ? $anio : (int) gmdate( 'Y' ) );
$years_available = isset( $years_available ) ? $years_available : array( $anio_activo );
$user = isset( $user ) ? $user : wp_get_current_user();

$region_name = '';
if ( ! empty( $region_slug ) ) {
	$term = get_term( $region_slug, 'gn_region' );
	$region_name = ( $term && ! is_wp_error( $term ) ) ? $term->name : '';
}

$panel_url = home_url( '/panel-supervisor/' );
$notif_url = add_query_arg( 'tab', 'notificaciones', $panel_url );

$icons = array(
	'bell'   => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>',
	'home'   => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
	'download' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>',
	'check'  => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>',
	'clock'  => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
);

$tipo_labels = array(
	'reto_enviado'          => 'Enviado a revisión',
	'participacion_enviada' => 'Participación enviada',
	'invalid_photo_date'    => 'Validación de evidencia',
	'validado'              => 'Validado por Comité',
);
?>
<div class="gnf-dashboard">
	<aside class="gnf-sidebar">
		<div class="gnf-sidebar__logo">
			<img src="<?php echo esc_url( GNF_LOGO_URL ); ?>" alt="Guardianes" style="height: 40px; width: auto; background: #fff; border-radius: 4px;" />
			<div>
				<small style="opacity: 0.7; font-size: 0.75rem;">Panel Supervisor</small>
			</div>
		</div>

		<nav class="gnf-sidebar__nav">
			<div class="gnf-sidebar__section">
				<div class="gnf-sidebar__label">Navegación</div>
				<a href="<?php echo esc_url( $panel_url ); ?>" class="gnf-sidebar__link">
					<span class="gnf-sidebar__icon"><?php echo $icons['home']; ?></span>
					Centros
				</a>
				<a href="<?php echo esc_url( $notif_url ); ?>" class="gnf-sidebar__link is-active">
					<span class="gnf-sidebar__icon"><?php echo $icons['bell']; ?></span>
					Notificaciones
				</a>
			</div>

			<div class="gnf-sidebar__section">
				<div class="gnf-sidebar__label">Región</div>
				<div style="padding: 0 24px; margin-bottom: 16px;">
					<div style="background: rgba(255,255,255,0.1); border-radius: 8px; padding: 12px;">
						<strong style="font-size: 0.9rem;"><?php echo esc_html( $region_name ?: 'Todas las regiones' ); ?></strong>
					</div>
				</div>
			</div>

			<div class="gnf-sidebar__section">
				<div class="gnf-sidebar__label">Año</div>
				<div style="padding: 0 24px;">
					<select onchange="location.href = '<?php echo esc_url( add_query_arg( 'tab', 'notificaciones', $panel_url ) ); ?>&gnf_year=' + this.value" style="width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.2); background: rgba(255,255,255,0.1); color: #fff; font-weight: 500;">
						<?php foreach ( $years_available as $y ) : ?>
							<option value="<?php echo esc_attr( $y ); ?>" <?php selected( $anio ?? $anio_activo, $y ); ?> style="color: #333;">
								<?php echo esc_html( $y ); ?><?php echo (int) $y === (int) $anio_activo ? ' (Activo)' : ''; ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<div class="gnf-sidebar__section">
				<div class="gnf-sidebar__label">Exportar</div>
				<a href="<?php echo esc_url( admin_url( 'admin-post.php?action=gnf_export_csv&year=' . ( $anio ?? $anio_activo ) ) ); ?>" class="gnf-sidebar__link">
					<span class="gnf-sidebar__icon"><?php echo $icons['download']; ?></span>
					Exportar CSV
				</a>
			</div>
		</nav>

		<div class="gnf-sidebar__user">
			<div class="gnf-sidebar__avatar">
				<?php echo esc_html( strtoupper( substr( $user->display_name, 0, 1 ) ) ); ?>
			</div>
			<div>
				<div class="gnf-sidebar__username"><?php echo esc_html( $user->display_name ); ?></div>
				<div class="gnf-sidebar__role">Supervisor</div>
			</div>
		</div>
	</aside>

	<main class="gnf-main">
		<div class="gnf-main__inner">
			<div class="gnf-page-header">
				<div>
					<h1 class="gnf-page-title">Notificaciones</h1>
					<p class="gnf-page-subtitle">
						Retos enviados a revisión, participaciones y validaciones.
					</p>
				</div>
			</div>

			<div class="gnf-section">
				<div class="gnf-section__body">
					<?php if ( empty( $notificaciones ) ) : ?>
						<div class="gnf-empty-state" style="padding: 48px 24px; text-align: center; background: #f8fafc; border-radius: 12px;">
							<p class="gnf-muted" style="margin: 0; font-size: 1rem;">No tienes notificaciones.</p>
							<p class="gnf-muted" style="margin: 8px 0 0;">Aquí aparecerán los retos enviados a revisión y las participaciones de los centros.</p>
						</div>
					<?php else : ?>
						<ul class="gnf-notificaciones-list" style="list-style: none; margin: 0; padding: 0;">
							<?php foreach ( $notificaciones as $n ) : ?>
								<li class="gnf-notif-item" style="display: flex; align-items: flex-start; gap: 16px; padding: 16px; border-bottom: 1px solid #e2e8f0; <?php echo ! $n['leido'] ? 'background: #f0fdf4;' : ''; ?>">
									<div style="flex-shrink: 0; width: 36px; height: 36px; border-radius: 50%; background: <?php echo in_array( $n['tipo'], array( 'reto_enviado', 'participacion_enviada' ), true ) ? 'var(--gnf-sun, #f59e0b)' : 'var(--gnf-leaf, #369484)'; ?>; color: #fff; display: flex; align-items: center; justify-content: center;">
										<?php echo $icons['check']; ?>
									</div>
									<div style="flex: 1; min-width: 0;">
										<div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
											<span class="gnf-badge gnf-badge--sm <?php echo in_array( $n['tipo'], array( 'reto_enviado', 'participacion_enviada' ), true ) ? 'gnf-badge--sun' : 'gnf-badge--forest'; ?>">
												<?php echo esc_html( isset( $tipo_labels[ $n['tipo'] ] ) ? $tipo_labels[ $n['tipo'] ] : $n['tipo'] ); ?>
											</span>
											<?php if ( ! $n['leido'] ) : ?>
												<span class="gnf-badge gnf-badge--sm" style="background: #369484; color: #fff;">Nueva</span>
											<?php endif; ?>
										</div>
										<p style="margin: 0 0 4px; font-size: 0.95rem;"><?php echo esc_html( $n['mensaje'] ); ?></p>
										<small class="gnf-muted" style="font-size: 0.8rem;"><?php echo esc_html( $n['created_at'] ); ?></small>
									</div>
									<?php if ( $n['link'] ) : ?>
										<a href="<?php echo esc_url( $n['link'] ); ?>" class="gnf-btn gnf-btn--sm gnf-btn--primary">Ver centro</a>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</main>
</div>
