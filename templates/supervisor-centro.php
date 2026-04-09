<?php
/**
 * Template: Detalle de centro para supervisor.
 *
 * Variables:
 * - $centro_id int
 * - $entries_by_centro array
 * - $anio int
 * - $anio_activo int
 * - $years_available array
 * - $region_slug int|string
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

// Get user's region name.
$region_name = '';
if (isset($region_slug) && $region_slug) {
	$term = get_term($region_slug, 'gn_region');
	$region_name = ($term && ! is_wp_error($term)) ? $term->name : '';
}

// Calculate stats for this centro.
$total_retos = 0;
$aprobados   = 0;
$enviados    = 0;
$correccion  = 0;
foreach ($entries as $entry) {
	$total_retos++;
	$cs = gnf_get_reto_entry_computed_status( $entry );
	if ( 'completo' === $cs['status'] ) $aprobados++;
	elseif ( 'requiere_atencion' === $cs['status'] ) $correccion++;
	elseif ( 'en_progreso' === $cs['status'] ) $enviados++;
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
);
?>
<div class="gnf-dashboard">
	<!-- Sidebar -->
	<aside class="gnf-sidebar">
		<div class="gnf-sidebar__logo">
			<img src="<?php echo esc_url(GNF_LOGO_URL); ?>" alt="Guardianes" style="height: 40px; width: auto; background: #fff; border-radius: 4px;" />
			<div>
				<small style="opacity: 0.7; font-size: 0.75rem;">Panel DRE</small>
			</div>
		</div>

		<nav class="gnf-sidebar__nav">
			<div class="gnf-sidebar__section">
				<div class="gnf-sidebar__label">Navegación</div>
				<a href="<?php echo esc_url( remove_query_arg( 'centro_id' ) ); ?>" class="gnf-sidebar__link">
					<span class="gnf-sidebar__icon"><?php echo $icons['back']; ?></span>
					Volver a centros
				</a>
				<a href="<?php echo esc_url( add_query_arg( 'tab', 'notificaciones', remove_query_arg( 'centro_id' ) ) ); ?>" class="gnf-sidebar__link">
					<span class="gnf-sidebar__icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg></span>
					Notificaciones
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
					<h1 class="gnf-page-title"><?php echo esc_html($centro_title); ?></h1>
					<p class="gnf-page-subtitle">
						<?php if ($codigo_mep) : ?>
							MEP: <?php echo esc_html($codigo_mep); ?> —
						<?php endif; ?>
						<?php echo esc_html($centro_region); ?> — Año <?php echo esc_html($anio); ?>
					</p>
				</div>
				<a href="<?php echo esc_url(remove_query_arg('centro_id')); ?>" class="gnf-btn gnf-btn--ghost">
					<?php echo $icons['back']; ?> Volver
				</a>
			</div>

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
							$reto_icon_sv = gnf_get_reto_icon_url($reto_id);
							$reto_color_sv = gnf_get_reto_color($reto_id);
							$computed = $entry ? gnf_get_reto_entry_computed_status( $entry ) : null;
							$badge_class = $computed ? $computed['badge'] : 'default';
							$badge_label = $computed ? $computed['label'] : 'Sin evidencias';
							$has_highlight = $computed && in_array( $computed['status'], array( 'en_progreso', 'requiere_atencion' ), true );
							?>
							<details class="gnf-reto-accordion__item <?php echo $has_highlight ? 'gnf-reto-accordion__item--highlight' : ''; ?>">
								<summary class="gnf-reto-accordion__summary">
									<?php if ( $reto_icon_sv ) : ?>
										<span class="gnf-reto-accordion__icon" style="background:<?php echo esc_attr( $reto_color_sv ); ?>1a;">
											<img src="<?php echo esc_url( $reto_icon_sv ); ?>" alt="" />
										</span>
									<?php endif; ?>
									<span class="gnf-reto-accordion__title"><?php the_title(); ?></span>
									<span class="gnf-reto-accordion__meta">
										<span class="gnf-reto-accordion__points"><?php echo esc_html($puntaje); ?> / <?php echo esc_html($puntaje_max); ?></span>
										<span class="gnf-badge gnf-badge--<?php echo esc_attr( $badge_class ); ?>">
											<?php echo esc_html( $badge_label ); ?>
										</span>
										<?php if ( $computed && $computed['total'] > 0 ) : ?>
											<span class="gnf-muted" style="font-size: 0.8rem; margin-left: 4px;">
												<?php echo esc_html( $computed['aprobadas'] . '/' . $computed['total'] ); ?>
											</span>
										<?php endif; ?>
									</span>
								</summary>
								<div class="gnf-reto-accordion__body">
									<?php if ( $entry && ! empty( $entry->evidencias ) ) : ?>
										<?php $evidencias = json_decode( $entry->evidencias, true ); ?>
										<div class="gnf-ev-review-list">
											<?php foreach ( (array) $evidencias as $ev_index => $ev ) : ?>
												<?php
												if ( ! empty( $ev['replaced'] ) ) { continue; }
												$ev_puntos   = $ev['puntos'] ?? null;
												$ev_estado   = $ev['estado'] ?? null;
												$ev_nombre   = $ev['nombre'] ?? 'Archivo';
												$ev_tipo     = $ev['tipo'] ?? 'archivo';
												$ev_comment  = $ev['supervisor_comment'] ?? '';
												$has_puntos  = null !== $ev_puntos;
												$url = ! empty( $ev['path_local'] )
													? add_query_arg( array(
														'action'    => 'gnf_descargar_evidencia',
														'nonce'     => wp_create_nonce( 'gnf_nonce' ),
														'file'      => base64_encode( $ev['path_local'] ),
														'centro_id' => $centro_id,
													), admin_url( 'admin-ajax.php' ) )
													: ( $ev['ruta'] ?? '' );
												$ev_badge = 'default'; $ev_label = 'Informativa';
												if ( $has_puntos ) {
													if ( 'aprobada' === $ev_estado ) { $ev_badge = 'forest'; $ev_label = 'Aprobada'; }
													elseif ( 'rechazada' === $ev_estado ) { $ev_badge = 'coral'; $ev_label = 'Rechazada'; }
													else { $ev_badge = 'sun'; $ev_label = 'Pendiente'; }
												}
												?>
												<div class="gnf-ev-card <?php echo 'rechazada' === $ev_estado ? 'gnf-ev-card--rejected' : ''; ?>"
													data-entry-id="<?php echo esc_attr( $entry->id ); ?>"
													data-ev-index="<?php echo esc_attr( $ev_index ); ?>">
													<div class="gnf-ev-card__header">
														<div class="gnf-ev-card__file">
															<?php if ( 'imagen' === $ev_tipo && ! empty( $ev['ruta'] ) ) : ?>
																<a href="<?php echo esc_url( $ev['ruta'] ); ?>" target="_blank">
																	<img src="<?php echo esc_url( $ev['ruta'] ); ?>" alt="<?php echo esc_attr( $ev_nombre ); ?>" class="gnf-ev-card__thumb" />
																</a>
															<?php endif; ?>
															<div>
																<a class="gnf-ev-card__name" href="<?php echo esc_url( $url ); ?>" target="_blank"><?php echo esc_html( $ev_nombre ); ?></a>
																<?php if ( $has_puntos ) : ?>
																	<span class="gnf-ev-card__points"><?php echo esc_html( $ev_puntos ); ?> pts</span>
																<?php endif; ?>
															</div>
														</div>
														<span class="gnf-badge gnf-badge--<?php echo esc_attr( $ev_badge ); ?>"><?php echo esc_html( $ev_label ); ?></span>
													</div>
													<?php if ( $ev_comment ) : ?>
														<div class="gnf-ev-card__comment">
															<small class="gnf-muted"><?php echo esc_html( $ev_comment ); ?></small>
														</div>
													<?php endif; ?>
													<?php if ( $has_puntos ) : ?>
														<div class="gnf-ev-card__actions">
															<textarea class="gnf-ev-card__note gnf-input" rows="1" placeholder="Comentario (requerido al rechazar)..."><?php echo esc_textarea( $ev_comment ); ?></textarea>
															<div class="gnf-ev-card__buttons">
																<button type="button" class="gnf-btn gnf-btn--sm gnf-ev-aprobar"<?php echo 'aprobada' === $ev_estado ? ' disabled' : ''; ?>>
																	<?php echo $icons['check']; ?> Aprobar
																</button>
																<button type="button" class="gnf-btn gnf-btn--sm gnf-btn--danger gnf-ev-rechazar"<?php echo 'rechazada' === $ev_estado ? ' disabled' : ''; ?>>
																	<?php echo $icons['alert']; ?> Rechazar
																</button>
															</div>
														</div>
													<?php endif; ?>
												</div>
											<?php endforeach; ?>
										</div>
									<?php else : ?>
										<span class="gnf-muted" style="padding: 16px; display: block;">Sin evidencias</span>
									<?php endif; ?>
								</div>
							</details>
						<?php endwhile; ?>
					</div>
				</div>
			</div>

			<script>
			document.addEventListener('DOMContentLoaded', function() {
				var restUrl = '<?php echo esc_js( rest_url( 'gnf/v1/supervisor/evidence/' ) ); ?>';
				var restNonce = '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>';

				function reviewEvidence(card, action) {
					var entryId = card.getAttribute('data-entry-id');
					var evIndex = card.getAttribute('data-ev-index');
					var noteEl  = card.querySelector('.gnf-ev-card__note');
					var comment = noteEl ? noteEl.value.trim() : '';

					if ('rechazar' === action && !comment) {
						noteEl.style.borderColor = 'var(--gnf-coral)';
						noteEl.focus();
						return;
					}

					var confirmMsg = 'aprobar' === action
						? '¿Confirmar aprobación de esta evidencia?'
						: '¿Confirmar rechazo de esta evidencia?';
					if (!confirm(confirmMsg)) return;

					var btns = card.querySelectorAll('button');
					btns.forEach(function(b) { b.disabled = true; });

					fetch(restUrl + entryId + '/' + evIndex, {
						method: 'POST',
						headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': restNonce },
						body: JSON.stringify({ action: action, comment: comment }),
					})
					.then(function(r) { return r.json(); })
					.then(function(resp) {
						if (resp && resp.success) {
							var badge = card.querySelector('.gnf-badge');
							if (badge) {
								badge.className = 'gnf-badge gnf-badge--' + ('aprobar' === action ? 'forest' : 'coral');
								badge.textContent = 'aprobar' === action ? 'Aprobada' : 'Rechazada';
							}
							if ('rechazar' === action) card.classList.add('gnf-ev-card--rejected');
							else card.classList.remove('gnf-ev-card--rejected');

							var commentEl = card.querySelector('.gnf-ev-card__comment');
							if (comment && !commentEl) {
								commentEl = document.createElement('div');
								commentEl.className = 'gnf-ev-card__comment';
								card.querySelector('.gnf-ev-card__header').after(commentEl);
							}
							if (commentEl) commentEl.innerHTML = '<small class="gnf-muted">' + comment.replace(/</g, '&lt;') + '</small>';

							var accordion = card.closest('.gnf-reto-accordion__item');
							if (accordion && resp.entry_puntaje !== undefined) {
								var pointsEl = accordion.querySelector('.gnf-reto-accordion__points');
								if (pointsEl) pointsEl.textContent = resp.entry_puntaje + ' / ' + pointsEl.textContent.split('/')[1].trim();
							}
							if (accordion && resp.entry_status) {
								var summaryBadge = accordion.querySelector('summary .gnf-badge');
								if (summaryBadge) {
									summaryBadge.className = 'gnf-badge gnf-badge--' + resp.entry_status.badge;
									summaryBadge.textContent = resp.entry_status.label;
								}
								var countEl = accordion.querySelector('summary .gnf-muted');
								if (countEl) countEl.textContent = resp.entry_status.aprobadas + '/' + resp.entry_status.total;
							}
							btns.forEach(function(b) {
								if (b.classList.contains('gnf-ev-aprobar')) b.disabled = ('aprobar' === action);
								else if (b.classList.contains('gnf-ev-rechazar')) b.disabled = ('rechazar' === action);
							});
						} else {
							alert((resp && resp.message) ? resp.message : 'Error al procesar.');
							btns.forEach(function(b) { b.disabled = false; });
						}
					})
					.catch(function() {
						alert('Error de conexión.');
						btns.forEach(function(b) { b.disabled = false; });
					});
				}

				document.querySelectorAll('.gnf-ev-aprobar').forEach(function(btn) {
					btn.addEventListener('click', function() { reviewEvidence(this.closest('.gnf-ev-card'), 'aprobar'); });
				});
				document.querySelectorAll('.gnf-ev-rechazar').forEach(function(btn) {
					btn.addEventListener('click', function() { reviewEvidence(this.closest('.gnf-ev-card'), 'rechazar'); });
				});
			});
			</script>
		</div><!-- /.gnf-main__inner -->
	</main>
</div><!-- /.gnf-dashboard -->
<?php wp_reset_postdata(); ?>
