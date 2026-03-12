<?php

/**
 * Template: Panel del Comité Bandera Azul Ecológica.
 *
 * Variables disponibles:
 * - $data['stats'] array
 * - $data['centros'] WP_Query
 * - $data['entries_by_centro'] array
 * - $data['historial'] array
 * - $data['regiones'] array
 * - $data['anio'] int
 * - $data['filters'] array
 */

$stats              = $data['stats'];
$centros            = $data['centros'];
$entries_by_centro  = $data['entries_by_centro'];
$historial          = $data['historial'];
$regiones           = $data['regiones'];
$anio               = $data['anio'];
$filters            = $data['filters'];
$region_locked      = ! empty( $data['user_region_locked'] );
$user               = wp_get_current_user();
$current_url       = remove_query_arg(array('gnf_comite_action', '_wpnonce'));

// SVG icons.
$icons = array(
	'home'     => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
	'building' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01"/><path d="M16 6h.01"/><path d="M12 6h.01"/><path d="M12 10h.01"/><path d="M12 14h.01"/><path d="M16 10h.01"/><path d="M16 14h.01"/><path d="M8 10h.01"/><path d="M8 14h.01"/></svg>',
	'check'    => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>',
	'clock'    => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
	'trophy'   => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/></svg>',
	'download' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>',
	'search'   => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>',
	'history'  => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v5h5"/><path d="M3.05 13A9 9 0 1 0 6 5.3L3 8"/><path d="M12 7v5l4 2"/></svg>',
);

// Tabs del comité.
$tabs = array(
	'centros'   => array('label' => 'Centros', 'icon' => 'building'),
	'historial' => array('label' => 'Historial', 'icon' => 'history'),
);
$active_tab = isset($_GET['view']) ? sanitize_key($_GET['view']) : 'centros';
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
				<?php foreach ($tabs as $tab_key => $tab_data) : ?>
					<a href="<?php echo esc_url(add_query_arg('view', $tab_key, $current_url)); ?>"
						class="gnf-sidebar__link <?php echo $active_tab === $tab_key ? 'is-active' : ''; ?>">
						<span class="gnf-sidebar__icon"><?php echo $icons[$tab_data['icon']]; ?></span>
						<?php echo esc_html($tab_data['label']); ?>
					</a>
				<?php endforeach; ?>
			</div>

			<div class="gnf-sidebar__section">
				<div class="gnf-sidebar__label">Exportar</div>
				<a href="<?php echo esc_url(admin_url('admin-post.php?action=gnf_export_csv&year=' . $anio)); ?>" class="gnf-sidebar__link">
					<span class="gnf-sidebar__icon"><?php echo $icons['download']; ?></span>
					Exportar CSV
				</a>
			</div>

			<div class="gnf-sidebar__section">
				<div class="gnf-sidebar__label">Estadísticas <?php echo esc_html($anio); ?></div>
				<div style="padding: 0 24px;">
					<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 8px;">
						<div style="text-align: center; padding: 12px; background: rgba(255,255,255,0.1); border-radius: 6px;">
							<strong style="display: block; font-size: 1.25rem;"><?php echo esc_html($stats['total_centros']); ?></strong>
							<small style="opacity: 0.7;">Centros</small>
						</div>
						<div style="text-align: center; padding: 12px; background: rgba(255,255,255,0.1); border-radius: 6px;">
							<strong style="display: block; font-size: 1.25rem;"><?php echo esc_html($stats['retos_aprobados']); ?></strong>
							<small style="opacity: 0.7;">Aprobados</small>
						</div>
						<div style="text-align: center; padding: 12px; background: rgba(255,255,255,0.1); border-radius: 6px;">
							<strong style="display: block; font-size: 1.25rem;"><?php echo esc_html($stats['retos_pendientes']); ?></strong>
							<small style="opacity: 0.7;">Pendientes</small>
						</div>
						<div style="text-align: center; padding: 12px; background: rgba(255,255,255,0.1); border-radius: 6px;">
							<strong style="display: block; font-size: 1.25rem;"><?php echo esc_html($stats['centros_completos']); ?></strong>
							<small style="opacity: 0.7;">Completos</small>
						</div>
					</div>
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
			<?php if ('centros' === $active_tab) : ?>
				<!-- CENTROS VIEW -->
				<div class="gnf-page-header">
					<div>
						<h1 class="gnf-page-title">Panel Comité Bandera Azul DRE</h1>
						<p class="gnf-page-subtitle">Validación de centros educativos — Año <?php echo esc_html($anio); ?></p>
					</div>
				</div>

				<!-- Filters -->
				<div class="gnf-filter-bar">
					<form method="get" class="gnf-filter-form">
						<input type="hidden" name="view" value="centros" />

						<?php if ( $region_locked ) :
							$locked_term = get_term( $filters['region'], 'gn_region' );
							$locked_name = ( $locked_term && ! is_wp_error( $locked_term ) ) ? $locked_term->name : '—';
						?>
						<div class="gnf-filter-bar__group">
							<label class="gnf-filter-bar__label">Región:</label>
							<span class="gnf-badge gnf-badge--ocean" style="font-size:0.85rem;padding:4px 12px;"><?php echo esc_html( $locked_name ); ?></span>
							<input type="hidden" name="region" value="<?php echo esc_attr( $filters['region'] ); ?>" />
						</div>
						<?php else : ?>
						<div class="gnf-filter-bar__group">
							<label class="gnf-filter-bar__label">Región:</label>
							<select name="region" class="gnf-select">
								<option value="">Todas las regiones</option>
								<?php foreach ($regiones as $region) : ?>
									<option value="<?php echo esc_attr($region->term_id); ?>" <?php selected($filters['region'], $region->term_id); ?>>
										<?php echo esc_html($region->name); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
						<?php endif; ?>

						<div class="gnf-filter-bar__group">
							<label class="gnf-filter-bar__label">Circuito:</label>
							<select name="circuito" class="gnf-select">
								<option value="">Todos los circuitos</option>
								<?php if ( ! empty( $circuitos_disponibles ) ) : ?>
									<?php foreach ( $circuitos_disponibles as $circ ) : ?>
										<option value="<?php echo esc_attr( $circ ); ?>" <?php selected( $filters['circuito'] ?? '', $circ ); ?>>
											<?php echo esc_html( $circ ); ?>
										</option>
									<?php endforeach; ?>
								<?php endif; ?>
							</select>
						</div>

						<div class="gnf-filter-bar__group">
							<label class="gnf-filter-bar__label">Estado:</label>
							<select name="estado" class="gnf-select">
								<option value="">Todos</option>
								<option value="aprobado" <?php selected($filters['estado'], 'aprobado'); ?>>Aprobados</option>
								<option value="enviado" <?php selected($filters['estado'], 'enviado'); ?>>Pendientes</option>
								<option value="correccion" <?php selected($filters['estado'], 'correccion'); ?>>Corrección</option>
							</select>
						</div>

						<div class="gnf-filter-bar__group gnf-filter-bar__search">
							<input type="text" name="s" class="gnf-input" placeholder="Buscar centro..." value="<?php echo esc_attr($filters['search']); ?>" />
						</div>

						<button type="submit" class="gnf-btn gnf-btn--ocean">Filtrar</button>
						<?php if ($filters['region'] || $filters['estado'] || $filters['search']) : ?>
							<a href="<?php echo esc_url(remove_query_arg(array('region', 'estado', 's'))); ?>" class="gnf-btn gnf-btn--ghost">Limpiar</a>
						<?php endif; ?>
					</form>
				</div>

				<!-- Centros Grid -->
				<div class="gnf-centros-grid">
					<?php if ($centros->have_posts()) : ?>
						<?php while ($centros->have_posts()) : $centros->the_post();
							$centro_id   = get_the_ID();
							$codigo_mep  = get_post_meta($centro_id, 'codigo_mep', true);
							$region_id   = get_post_meta($centro_id, 'region', true);
							$region_term = $region_id ? get_term($region_id, 'gn_region') : null;
							$region_name = ($region_term && ! is_wp_error($region_term)) ? $region_term->name : '—';
							$puntaje     = gnf_get_centro_puntaje_total($centro_id, $anio);
							$estrella    = gnf_get_centro_estrella_final($centro_id, $anio);
							$entries     = $entries_by_centro[$centro_id] ?? array();
							$validado    = get_post_meta($centro_id, 'gnf_comite_validado', true);

							// Calcular estadísticas.
							$total_retos  = count($entries);
							$aprobados    = 0;
							$enviados     = 0;
							$correccion   = 0;
							foreach ($entries as $entry) {
								if ('aprobado' === $entry->estado) $aprobados++;
								elseif ('enviado' === $entry->estado) $enviados++;
								elseif ('correccion' === $entry->estado) $correccion++;
							}
						?>
							<div class="gnf-centro-card <?php echo $validado ? 'gnf-centro-card--validated' : ''; ?>">
								<?php if ($validado) : ?>
									<div class="gnf-centro-card__badge">
										<span class="gnf-badge gnf-badge--forest"><?php echo $icons['check']; ?> Validado</span>
									</div>
								<?php endif; ?>

								<div class="gnf-centro-card__header">
									<div class="gnf-centro-card__icon"><?php echo $icons['building']; ?></div>
									<div>
										<h3 class="gnf-centro-card__title"><?php the_title(); ?></h3>
										<p class="gnf-centro-card__region"><?php echo esc_html($region_name); ?></p>
									</div>
								</div>

								<div class="gnf-centro-card__meta">
									<span><strong><?php echo esc_html($puntaje); ?></strong> pts</span>
									<span class="gnf-stars"><?php echo $estrella > 0 ? str_repeat('<span class="gnf-star">★</span>', $estrella) : '—'; ?></span>
									<?php if ($codigo_mep) : ?>
										<span class="gnf-muted">MEP: <?php echo esc_html($codigo_mep); ?></span>
									<?php endif; ?>
									<?php
									$dependencia = get_post_meta($centro_id, 'dependencia', true);
									if ($dependencia) : ?>
										<span class="gnf-badge gnf-badge--sm"><?php echo esc_html($dependencia); ?></span>
									<?php endif; ?>
								</div>

								<!-- Retos Progress -->
								<div class="gnf-centro-card__stats">
									<div class="gnf-centro-stat gnf-centro-stat--success">
										<span class="gnf-centro-stat__value"><?php echo esc_html($aprobados); ?></span>
										<span class="gnf-centro-stat__label">Aprobados</span>
									</div>
									<div class="gnf-centro-stat gnf-centro-stat--info">
										<span class="gnf-centro-stat__value"><?php echo esc_html($enviados); ?></span>
										<span class="gnf-centro-stat__label">Enviados</span>
									</div>
									<div class="gnf-centro-stat gnf-centro-stat--warning">
										<span class="gnf-centro-stat__value"><?php echo esc_html($correccion); ?></span>
										<span class="gnf-centro-stat__label">Corrección</span>
									</div>
								</div>

								<?php if ($total_retos > 0) : ?>
									<div class="gnf-centro-card__progress">
										<div class="gnf-progress">
											<div class="gnf-progress__bar" style="width: <?php echo esc_attr(($aprobados / $total_retos) * 100); ?>%;"></div>
										</div>
										<small class="gnf-muted"><?php echo esc_html(round(($aprobados / $total_retos) * 100)); ?>% completado</small>
									</div>
								<?php endif; ?>

								<div class="gnf-centro-card__actions">
									<a href="<?php echo esc_url(add_query_arg('centro_id', $centro_id)); ?>" class="gnf-btn gnf-btn--sm gnf-btn--ocean">Ver Detalle</a>
									<?php if (! $validado && $aprobados === $total_retos && $total_retos > 0) : ?>
										<button type="button" class="gnf-btn gnf-btn--sm gnf-comite-validar" data-centro-id="<?php echo esc_attr($centro_id); ?>">
											<?php echo $icons['check']; ?> Validar
										</button>
									<?php endif; ?>
								</div>
							</div>
						<?php endwhile;
						wp_reset_postdata(); ?>
					<?php else : ?>
						<div class="gnf-empty-state">
							<div class="gnf-empty-state__icon"><?php echo $icons['search']; ?></div>
							<h3 class="gnf-empty-state__title">No se encontraron centros</h3>
							<p class="gnf-empty-state__text">Intenta con otros filtros</p>
						</div>
					<?php endif; ?>
				</div>

				<!-- Pagination -->
				<?php if ($centros->max_num_pages > 1) : ?>
					<div class="gnf-pagination">
						<?php
						$current_page = max(1, isset($_GET['paged']) ? absint($_GET['paged']) : 1);
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

			<?php else : ?>
				<!-- HISTORIAL VIEW -->
				<div class="gnf-page-header">
					<div>
						<h1 class="gnf-page-title">Historial de Validaciones</h1>
						<p class="gnf-page-subtitle">Registro de aprobaciones y correcciones — Año <?php echo esc_html($anio); ?></p>
					</div>
				</div>

				<div class="gnf-section">
					<div class="gnf-section__body">
						<?php if (! empty($historial)) : ?>
							<div class="gnf-timeline">
								<?php foreach ($historial as $item) : ?>
									<div class="gnf-timeline__item">
										<div class="gnf-timeline__dot gnf-timeline__dot--<?php echo 'aprobado' === $item->estado ? 'success' : 'warning'; ?>"></div>
										<div class="gnf-timeline__content">
											<strong><?php echo esc_html($item->centro_nombre); ?></strong>
											<br>
											<small><?php echo esc_html($item->reto_nombre); ?></small>
											<span class="gnf-badge gnf-badge--<?php echo 'aprobado' === $item->estado ? 'forest' : 'sun'; ?>">
												<?php echo esc_html($item->estado); ?>
											</span>
											<?php if ($item->supervisor_nombre) : ?>
												<div class="gnf-muted" style="margin-top: 4px;">
													por <?php echo esc_html($item->supervisor_nombre); ?>
												</div>
											<?php endif; ?>
											<div class="gnf-timeline__time">
												<?php echo esc_html(human_time_diff(strtotime($item->updated_at), time())); ?> atrás
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
			<?php endif; ?>
		</div><!-- /.gnf-main__inner -->
	</main>
</div><!-- /.gnf-dashboard -->

<!-- Modal para validar -->
<div id="gnf-validar-modal" class="gnf-modal">
	<div class="gnf-modal__content">
		<h3 class="gnf-modal__title"><?php echo $icons['check']; ?> Validar Centro</h3>
		<p class="gnf-muted">Confirma que este centro cumple con todos los requisitos del programa Bandera Azul Ecológica.</p>

		<form method="post" id="gnf-validar-form">
			<?php wp_nonce_field('gnf_comite_action', 'gnf_nonce'); ?>
			<input type="hidden" name="gnf_comite_action" value="validar_centro" />
			<input type="hidden" name="centro_id" id="gnf-validar-centro-id" value="" />

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