<?php
/**
 * Template: Detalle de centro para Comité BAE.
 *
 * Variables:
 * - $centro_id int
 * - $entries_by_centro array
 * - $anio int
 * - $anio_activo int
 * - $years_available array
 */

$centro_title = get_the_title($centro_id);
$entries      = $entries_by_centro[$centro_id] ?? array();
$user         = wp_get_current_user();
$anio_activo  = isset($anio_activo) ? $anio_activo : $anio;
$years_available = isset($years_available) ? $years_available : array($anio);

// Get centro meta.
$codigo_mep    = get_post_meta($centro_id, 'codigo_mep', true);
$puntaje_total = gnf_get_centro_puntaje_total($centro_id, $anio);
$estrella      = gnf_get_centro_estrella_final($centro_id, $anio);
$region_id     = get_post_meta($centro_id, 'region', true);
$region_term   = $region_id ? get_term($region_id, 'gn_region') : null;
$centro_region = ($region_term && ! is_wp_error($region_term)) ? $region_term->name : '—';
$validado      = get_post_meta($centro_id, 'gnf_comite_validado', true);

// Calculate stats for this centro.
$total_retos = 0;
$aprobados   = 0;
$enviados    = 0;
$correccion  = 0;
foreach ($entries as $entry) {
	$total_retos++;
	if ('aprobado' === $entry->estado) $aprobados++;
	elseif ('enviado' === $entry->estado) $enviados++;
	elseif ('correccion' === $entry->estado) $correccion++;
}

$retos_matriculados_ids = gnf_get_centro_retos_seleccionados($centro_id, $anio);
$retos = new WP_Query(array(
	'post_type'      => 'reto',
	'post_status'    => 'publish',
	'posts_per_page' => -1,
	'orderby'        => 'menu_order',
	'order'          => 'ASC',
	'post__in'       => ! empty($retos_matriculados_ids) ? $retos_matriculados_ids : array(0),
));

// SVG icons.
$icons = array(
	'home'     => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
	'building' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01"/><path d="M16 6h.01"/><path d="M12 6h.01"/><path d="M12 10h.01"/><path d="M12 14h.01"/><path d="M16 10h.01"/><path d="M16 14h.01"/><path d="M8 10h.01"/><path d="M8 14h.01"/></svg>',
	'check'    => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>',
	'clock'    => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
	'alert'    => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
	'download' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>',
	'back'     => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>',
	'shield'   => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
);
?>
<div class="gnf-dashboard">
	<!-- Sidebar -->
	<aside class="gnf-sidebar">
		<div class="gnf-sidebar__logo">
			<img src="<?php echo esc_url(GNF_LOGO_URL); ?>" alt="Guardianes" style="height: 40px; width: auto; background: #fff; border-radius: 4px;" />
			<div>
				<small style="opacity: 0.7; font-size: 0.75rem;">Comité BAE DRE</small>
			</div>
		</div>

		<nav class="gnf-sidebar__nav">
			<div class="gnf-sidebar__section">
				<div class="gnf-sidebar__label">Navegación</div>
				<a href="<?php echo esc_url(remove_query_arg('centro_id')); ?>" class="gnf-sidebar__link">
					<span class="gnf-sidebar__icon"><?php echo $icons['back']; ?></span>
					Volver a lista
				</a>
			</div>

			<div class="gnf-sidebar__section">
				<div class="gnf-sidebar__label">Centro actual</div>
				<div style="padding: 0 24px;">
					<div style="background: rgba(255,255,255,0.1); border-radius: 8px; padding: 12px;">
						<strong style="font-size: 0.85rem; display: block; margin-bottom: 4px;"><?php echo esc_html($centro_title); ?></strong>
						<small style="opacity: 0.7;"><?php echo esc_html($centro_region); ?></small>
						<div style="display: flex; gap: 12px; margin-top: 8px;">
							<span style="font-size: 1rem; font-weight: 600;"><?php echo esc_html($puntaje_total); ?> pts</span>
							<span style="color: var(--gnf-sun);"><?php echo str_repeat('★', $estrella); ?></span>
						</div>
						<?php if ($validado) : ?>
							<div style="margin-top: 8px;">
								<span class="gnf-badge gnf-badge--forest"><?php echo $icons['check']; ?> Validado</span>
							</div>
						<?php endif; ?>
					</div>
				</div>
			</div>

			<div class="gnf-sidebar__section">
				<div class="gnf-sidebar__label">Estado de retos</div>
				<div style="padding: 0 24px;">
					<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 8px;">
						<div style="text-align: center; padding: 10px; background: rgba(255,255,255,0.1); border-radius: 6px;">
							<strong style="display: block; font-size: 1.1rem; color: var(--gnf-sun);"><?php echo esc_html($enviados); ?></strong>
							<small style="opacity: 0.7; font-size: 0.7rem;">Pendientes</small>
						</div>
						<div style="text-align: center; padding: 10px; background: rgba(255,255,255,0.1); border-radius: 6px;">
							<strong style="display: block; font-size: 1.1rem; color: var(--gnf-leaf);"><?php echo esc_html($aprobados); ?></strong>
							<small style="opacity: 0.7; font-size: 0.7rem;">Aprobados</small>
						</div>
						<div style="text-align: center; padding: 10px; background: rgba(255,255,255,0.1); border-radius: 6px;">
							<strong style="display: block; font-size: 1.1rem; color: var(--gnf-coral);"><?php echo esc_html($correccion); ?></strong>
							<small style="opacity: 0.7; font-size: 0.7rem;">Corrección</small>
						</div>
						<div style="text-align: center; padding: 10px; background: rgba(255,255,255,0.1); border-radius: 6px;">
							<strong style="display: block; font-size: 1.1rem;"><?php echo esc_html($total_retos); ?></strong>
							<small style="opacity: 0.7; font-size: 0.7rem;">Total</small>
						</div>
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
		</nav>

		<div class="gnf-sidebar__user">
			<div class="gnf-sidebar__avatar">
				<?php echo esc_html(strtoupper(substr($user->display_name, 0, 1))); ?>
			</div>
			<div>
				<div class="gnf-sidebar__username"><?php echo esc_html($user->display_name); ?></div>
				<div class="gnf-sidebar__role">Comité BAE DRE</div>
			</div>
		</div>
	</aside>

	<!-- Main Content -->
	<main class="gnf-main">
		<div class="gnf-main__inner">
			<!-- Page Header -->
			<div class="gnf-page-header">
				<div>
					<h1 class="gnf-page-title"><?php echo esc_html($centro_title); ?></h1>
					<p class="gnf-page-subtitle">
						<?php if ($codigo_mep) : ?>
							MEP: <?php echo esc_html($codigo_mep); ?> —
						<?php endif; ?>
						<?php echo esc_html($centro_region); ?> — Año <?php echo esc_html($anio); ?>
					</p>
				</div>
				<div style="display: flex; gap: 8px;">
					<?php if (! $validado && $aprobados === $total_retos && $total_retos > 0) : ?>
						<button type="button" class="gnf-btn gnf-comite-validar" data-centro-id="<?php echo esc_attr($centro_id); ?>">
							<?php echo $icons['shield']; ?> Validar Centro
						</button>
					<?php endif; ?>
					<a href="<?php echo esc_url(remove_query_arg('centro_id')); ?>" class="gnf-btn gnf-btn--ghost">
						<?php echo $icons['back']; ?> Volver
					</a>
				</div>
			</div>

			<?php if ($validado) : ?>
				<div class="gnf-alert gnf-alert--success gnf-mb-6">
					<span class="gnf-alert__icon"><?php echo $icons['check']; ?></span>
					<div>
						<strong>Centro Validado</strong>
						<p class="gnf-muted" style="margin: 4px 0 0;">Este centro ha sido validado por el Comité Bandera Azul Ecológica.</p>
					</div>
				</div>
			<?php endif; ?>

			<!-- Stats -->
			<div class="gnf-stats-grid gnf-stats-grid--3 gnf-mb-6">
				<div class="gnf-stat-card">
					<div class="gnf-stat-card__icon gnf-stat-card__icon--sun"><?php echo $icons['clock']; ?></div>
					<div class="gnf-stat-card__value"><?php echo esc_html($enviados); ?></div>
					<div class="gnf-stat-card__label">Por revisar</div>
				</div>
				<div class="gnf-stat-card">
					<div class="gnf-stat-card__icon gnf-stat-card__icon--forest"><?php echo $icons['check']; ?></div>
					<div class="gnf-stat-card__value"><?php echo esc_html($aprobados); ?></div>
					<div class="gnf-stat-card__label">Aprobados</div>
				</div>
				<div class="gnf-stat-card">
					<div class="gnf-stat-card__icon gnf-stat-card__icon--coral"><?php echo $icons['alert']; ?></div>
					<div class="gnf-stat-card__value"><?php echo esc_html($correccion); ?></div>
					<div class="gnf-stat-card__label">Corrección</div>
				</div>
			</div>

			<!-- Retos Accordion -->
			<div class="gnf-section">
				<div class="gnf-section__header">
					<h3 class="gnf-section__title">Eco Retos del Centro</h3>
				</div>
				<div class="gnf-section__body">
					<div class="gnf-reto-accordion">
						<?php while ($retos->have_posts()) : $retos->the_post(); ?>
							<?php
							$reto_id = get_the_ID();
							$entry   = $entries[$reto_id] ?? null;
							$estado  = $entry ? $entry->estado : 'no_iniciado';
							$puntaje = $entry ? (int) $entry->puntaje : 0;
							$puntaje_max = gnf_get_reto_max_points($reto_id, $anio);
							$reto_icon_cm = gnf_get_reto_icon_url($reto_id);
							$reto_color_cm = gnf_get_reto_color($reto_id);
							$notes   = $entry ? $entry->supervisor_notes : '';
							$warnings = array();
							if ($entry && ! empty($entry->evidencias)) {
								$evs = json_decode($entry->evidencias, true);
								foreach ((array) $evs as $ev) {
									if (! empty($ev['requires_year_validation'])) {
										$warnings[] = 'Foto requiere validación de año';
									}
								}
							}
							$badge_class = 'aprobado' === $estado ? 'forest' :
								('enviado' === $estado ? 'sun' :
								('correccion' === $estado ? 'coral' : 'default'));
							?>
							<details class="gnf-reto-accordion__item">
								<summary class="gnf-reto-accordion__summary">
									<?php if ( $reto_icon_cm ) : ?>
										<span class="gnf-reto-accordion__icon" style="background:<?php echo esc_attr( $reto_color_cm ); ?>1a;">
											<img src="<?php echo esc_url( $reto_icon_cm ); ?>" alt="" />
										</span>
									<?php endif; ?>
									<span class="gnf-reto-accordion__title"><?php the_title(); ?></span>
									<span class="gnf-reto-accordion__meta">
										<span class="gnf-reto-accordion__points"><?php echo esc_html($puntaje); ?> / <?php echo esc_html($puntaje_max); ?></span>
										<span class="gnf-badge gnf-badge--<?php echo $badge_class; ?>">
											<?php echo esc_html(ucwords(str_replace('_', ' ', $estado))); ?>
										</span>
									</span>
								</summary>
								<div class="gnf-reto-accordion__body">
									<div class="gnf-reto-accordion__body-grid">
										<!-- Evidencias -->
										<div>
											<div class="gnf-reto-accordion__field-label">Evidencias</div>
											<?php
											if ($entry && ! empty($entry->evidencias)) {
												$evidencias = json_decode($entry->evidencias, true);
												if (! empty($evidencias)) {
													echo '<div class="gnf-evidencias-list">';
													foreach ($evidencias as $ev) {
														$url = ! empty($ev['path_local'])
															? add_query_arg(
																array(
																	'action'    => 'gnf_descargar_evidencia',
																	'nonce'     => wp_create_nonce('gnf_nonce'),
																	'file'      => base64_encode($ev['path_local']),
																	'centro_id' => $centro_id,
																),
																admin_url('admin-ajax.php')
															)
															: ($ev['ruta'] ?? '');
														echo '<a class="gnf-btn gnf-btn--sm gnf-btn--ghost" target="_blank" href="' . esc_url($url) . '">' . esc_html($ev['nombre'] ?? 'Archivo') . '</a> ';
													}
													echo '</div>';
												}
											} else {
												echo '<span class="gnf-muted">Sin evidencias</span>';
											}
											?>
											<?php if ($warnings) : ?>
												<div style="margin-top: 8px;">
													<?php foreach ($warnings as $w) : ?>
														<span class="gnf-badge gnf-badge--coral" style="font-size: 0.7rem;"><?php echo esc_html($w); ?></span>
													<?php endforeach; ?>
												</div>
											<?php endif; ?>
										</div>

										<!-- Notas -->
										<div>
											<div class="gnf-reto-accordion__field-label">Notas del supervisor</div>
											<?php if ($notes) : ?>
												<div class="gnf-correction-note">
													<div class="gnf-correction-note__label">Observación</div>
													<?php echo esc_html($notes); ?>
												</div>
											<?php else : ?>
												<span class="gnf-muted">Sin notas</span>
											<?php endif; ?>
										</div>
									</div>
								</div>
							</details>
						<?php endwhile; ?>
					</div>
				</div>
			</div>
		</div><!-- /.gnf-main__inner -->
	</main>
</div><!-- /.gnf-dashboard -->

<!-- Modal para validar -->
<div id="gnf-validar-modal" class="gnf-modal">
	<div class="gnf-modal__content">
		<h3 class="gnf-modal__title"><?php echo $icons['shield']; ?> Validar Centro</h3>
		<p class="gnf-muted">Confirma que este centro cumple con todos los requisitos del programa Bandera Azul Ecológica.</p>

		<form method="post" id="gnf-validar-form">
			<?php wp_nonce_field('gnf_comite_action', 'gnf_nonce'); ?>
			<input type="hidden" name="gnf_comite_action" value="validar_centro" />
			<input type="hidden" name="centro_id" id="gnf-validar-centro-id" value="<?php echo esc_attr($centro_id); ?>" />

			<div class="gnf-form-group">
				<label class="gnf-label">Nota (opcional)</label>
				<textarea name="nota" class="gnf-textarea" rows="3" placeholder="Agregar comentario..."></textarea>
			</div>

			<div class="gnf-modal__actions">
				<button type="submit" class="gnf-btn">Confirmar Validación</button>
				<button type="button" class="gnf-btn gnf-btn--ghost gnf-modal-close">Cancelar</button>
			</div>
		</form>
	</div>
</div>

<script>
	document.addEventListener('DOMContentLoaded', function() {
		var modal = document.getElementById('gnf-validar-modal');

		// Abrir modal de validación.
		document.querySelectorAll('.gnf-comite-validar').forEach(function(btn) {
			btn.addEventListener('click', function() {
				var centroId = this.dataset.centroId;
				document.getElementById('gnf-validar-centro-id').value = centroId;
				modal.classList.add('is-open');
			});
		});

		// Cerrar modal.
		document.querySelectorAll('.gnf-modal-close').forEach(function(btn) {
			btn.addEventListener('click', function() {
				modal.classList.remove('is-open');
			});
		});

		// Cerrar modal al hacer clic fuera.
		modal.addEventListener('click', function(e) {
			if (e.target === this) {
				this.classList.remove('is-open');
			}
		});
	});
</script>
<?php wp_reset_postdata(); ?>

