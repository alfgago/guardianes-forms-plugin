<?php
/**
 * Template: Panel de Docente.
 *
 * Variables disponibles:
 * - $user WP_User
 * - $centro_id int
 * - $entries_by array reto_id => entry
 * - $retos_query WP_Query
 * - $anio int
 * - $meta_estrellas int (objetivo de estrellas del centro)
 * - $tiene_matricula bool
 * - $retos_seleccionados array
 */

$centro_title      = $centro_id ? get_the_title($centro_id) : '';
$puntaje_total     = $centro_id ? gnf_get_centro_puntaje_total($centro_id, $anio) : 0;
$estrella          = $centro_id ? gnf_get_centro_estrella_final($centro_id, $anio) : 0;
$centro_estado     = $centro_id ? get_post_meta($centro_id, 'estado_centro', true) : 'pendiente';
$codigo_mep        = $centro_id ? get_post_meta($centro_id, 'codigo_mep', true) : '';
$region_term       = $centro_id ? get_post_meta($centro_id, 'region', true) : '';
$doc_estado        = isset($docente_estado) ? $docente_estado : 'activo';
$meta_estrellas    = isset($meta_estrellas) ? $meta_estrellas : 0;
$tiene_matricula   = isset($tiene_matricula) ? $tiene_matricula : false;
$anio_activo       = isset($anio_activo) ? $anio_activo : $anio;
$years_available   = isset($years_available) ? $years_available : array($anio);
$retos_disponibles = isset($retos_disponibles) ? $retos_disponibles : array();
$puntos_potenciales = isset($puntos_potenciales) ? $puntos_potenciales : 0;
$all_retos_complete = isset($all_retos_complete) ? $all_retos_complete : false;

// Scoring is now automatic — no autoevaluación needed.

// Calcular estadísticas de progreso.
$total_retos       = 0;
$retos_completados = 0;
$retos_pendientes  = 0;
$retos_enviados    = 0;
$retos_correccion  = 0;
$retos_completos   = 0; // Estado 'completo' (listo para enviar).

if (! empty($retos_seleccionados) && is_array($retos_seleccionados)) {
	$total_retos = count($retos_seleccionados);
	foreach ($retos_seleccionados as $reto_id) {
		$entry = $entries_by[$reto_id] ?? null;
		if (! $entry || 'no_iniciado' === $entry->estado) {
			$retos_pendientes++;
		} elseif ('aprobado' === $entry->estado) {
			$retos_completados++;
		} elseif ('completo' === $entry->estado) {
			$retos_completos++;
		} elseif ('enviado' === $entry->estado) {
			$retos_enviados++;
		} elseif ('correccion' === $entry->estado) {
			$retos_correccion++;
		} elseif ('en_progreso' === $entry->estado) {
			$retos_pendientes++;
		}
	}
}

// Meta de centro/usuario para encabezados, sidebar y bloque de resumen.
$centro_provincia = $centro_id ? get_post_meta($centro_id, 'provincia', true) : '';
$centro_canton    = $centro_id ? get_post_meta($centro_id, 'canton', true) : '';
$centro_circuito  = $centro_id ? get_post_meta($centro_id, 'circuito', true) : '';
$centro_direccion = $centro_id ? get_post_meta($centro_id, 'direccion', true) : '';
$centro_telefono  = $centro_id ? get_post_meta($centro_id, 'telefono', true) : '';
$centro_correo_institucional = $centro_id ? get_post_meta($centro_id, 'correo_institucional', true) : '';

$centro_nivel_educativo = $centro_id ? get_post_meta($centro_id, 'nivel_educativo', true) : '';
if (! $centro_nivel_educativo && $centro_id) {
	$centro_nivel_educativo = get_post_meta($centro_id, 'modalidad', true);
}
$centro_dependencia = $centro_id ? get_post_meta($centro_id, 'dependencia', true) : '';
$centro_jornada     = $centro_id ? get_post_meta($centro_id, 'jornada', true) : '';
if (! $centro_jornada && $centro_id) {
	$centro_jornada = get_post_meta($centro_id, 'horario', true);
}
$centro_tipologia   = $centro_id ? get_post_meta($centro_id, 'tipologia', true) : '';

$centro_total_estudiantes     = $centro_id ? (int) get_post_meta($centro_id, 'total_estudiantes', true) : 0;
$centro_estudiantes_hombres   = $centro_id ? (int) get_post_meta($centro_id, 'estudiantes_hombres', true) : 0;
$centro_estudiantes_mujeres   = $centro_id ? (int) get_post_meta($centro_id, 'estudiantes_mujeres', true) : 0;
$centro_estudiantes_migrantes = $centro_id ? (int) get_post_meta($centro_id, 'estudiantes_migrantes', true) : 0;
$centro_ultimo_galardon       = $centro_id ? (int) get_post_meta($centro_id, 'ultimo_galardon_estrellas', true) : 0;
$centro_ultimo_anio           = $centro_id ? (string) get_post_meta($centro_id, 'ultimo_anio_participacion', true) : '';
$centro_ultimo_anio_otro      = $centro_id ? (int) get_post_meta($centro_id, 'ultimo_anio_participacion_otro', true) : 0;
$coordinador_cargo            = $centro_id ? (string) get_post_meta($centro_id, 'coordinador_pbae_cargo', true) : '';
$coordinador_nombre           = $centro_id ? (string) get_post_meta($centro_id, 'coordinador_pbae_nombre', true) : '';
$coordinador_celular          = $centro_id ? (string) get_post_meta($centro_id, 'coordinador_pbae_celular', true) : '';

$centro_nivel_educativo = gnf_normalize_centro_choice('nivel_educativo', (string) $centro_nivel_educativo);
$centro_dependencia     = gnf_normalize_centro_choice('dependencia', (string) $centro_dependencia);
$centro_jornada         = gnf_normalize_centro_choice('jornada', (string) $centro_jornada);
$centro_tipologia       = gnf_normalize_centro_choice('tipologia', (string) $centro_tipologia);
$coordinador_cargo      = gnf_normalize_centro_choice('coordinador_cargo', (string) $coordinador_cargo);

$centro_nivel_educativo_label = $centro_nivel_educativo ? gnf_get_centro_choice_label('nivel_educativo', $centro_nivel_educativo) : '';
$centro_dependencia_label     = $centro_dependencia ? gnf_get_centro_choice_label('dependencia', $centro_dependencia) : '';
$centro_jornada_label         = $centro_jornada ? gnf_get_centro_choice_label('jornada', $centro_jornada) : '';
$centro_tipologia_label       = $centro_tipologia ? gnf_get_centro_choice_label('tipologia', $centro_tipologia) : '';
$coordinador_cargo_label      = $coordinador_cargo ? gnf_get_centro_choice_label('coordinador_cargo', $coordinador_cargo) : '';

$centro_ultimo_anio_label = '';
if ('otro' === $centro_ultimo_anio) {
	$centro_ultimo_anio_label = $centro_ultimo_anio_otro ? (string) $centro_ultimo_anio_otro : 'Otro';
} elseif (in_array($centro_ultimo_anio, array('2025', '2024'), true)) {
	$centro_ultimo_anio_label = $centro_ultimo_anio;
}

$centro_profile_choices = gnf_get_centro_profile_choice_sets();
$centro_provincias      = gnf_get_cr_provinces();
$centro_cantones_map    = gnf_get_cr_province_canton_map();
$centro_regions         = get_terms(array(
	'taxonomy'   => 'gn_region',
	'hide_empty' => false,
	'orderby'    => 'name',
	'order'      => 'ASC',
));
$dashboard_msg_error  = isset($_GET['gnf_err']) ? sanitize_text_field(wp_unslash($_GET['gnf_err'])) : '';
$dashboard_msg_notice = isset($_GET['gnf_msg']) ? sanitize_text_field(wp_unslash($_GET['gnf_msg'])) : '';

$region_name      = 'N/D';
if ($region_term) {
	$_rterm = get_term($region_term, 'gn_region');
	$region_name = ($_rterm && ! is_wp_error($_rterm)) ? $_rterm->name : $region_term;
}
$_doc_status_label = array(
	'activo'   => array('label' => 'Activo',   'cls' => 'gnf-badge--forest'),
	'pendiente'=> array('label' => 'Pendiente','cls' => 'gnf-badge--sun'),
	'rechazado'=> array('label' => 'Rechazado','cls' => 'gnf-badge--coral'),
);
$_doc_sl = $_doc_status_label[$doc_estado] ?? array('label' => ucfirst($doc_estado), 'cls' => 'gnf-badge--gray');
$_cen_sl = $_doc_status_label[$centro_estado] ?? array('label' => ucfirst($centro_estado ?: 'pendiente'), 'cls' => 'gnf-badge--gray');

// Métricas de dashboard.
$retos_con_avance = $retos_completados + $retos_completos + $retos_enviados;
$progreso_general = $total_retos > 0 ? (int) round(($retos_con_avance / $total_retos) * 100) : 0;

// Mapa de pasos del wizard para acceso rápido por reto.
$wizard_step_map = array();
if (! empty($retos_seleccionados) && is_array($retos_seleccionados)) {
	$step_counter = 1;
	foreach ($retos_seleccionados as $reto_step_id) {
		$reto_step_post = get_post($reto_step_id);
		if ($reto_step_post && 'reto' === $reto_step_post->post_type) {
			$wizard_step_map[(int) $reto_step_id] = $step_counter;
			$step_counter++;
		}
	}
}

// Asegura que $retos_query sea un objeto WP_Query para evitar fatal.
if (empty($retos_query) || ! ($retos_query instanceof WP_Query)) {
	$retos_query = new WP_Query(
		array(
			'post_type'      => 'reto',
			'post_status'    => 'publish',
			'posts_per_page' => 0,
		)
	);
}
?>

<?php
// SVG icons.
$doc_icons = array(
	'home'     => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
	'clipboard' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="2" width="6" height="4" rx="1"/><path d="M9 4H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-3"/><path d="M8 12h8"/><path d="M8 16h5"/></svg>',
	'file-edit' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h5"/><path d="M8 17h3"/><path d="M16.4 11.6a1.8 1.8 0 1 1 2.5 2.5l-2.7 2.7-2.9.4.4-2.9z"/></svg>',
	'file'     => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>',
	'building' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01"/><path d="M16 6h.01"/><path d="M12 6h.01"/><path d="M12 10h.01"/><path d="M12 14h.01"/><path d="M16 10h.01"/><path d="M16 14h.01"/><path d="M8 10h.01"/><path d="M8 14h.01"/></svg>',
);

// Tabs para docente.
$docente_tabs = array(
	'resumen'     => array('label' => 'Resumen', 'icon' => 'home'),
	'formularios' => array('label' => 'Mis Retos', 'icon' => 'clipboard'),
	'matricula'   => array('label' => 'Matrícula', 'icon' => 'file-edit'),
);
// Use active tab from data if passed, otherwise from GET.
$active_docente_tab = isset($active_docente_tab) ? $active_docente_tab : (isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'resumen');
$current_docente_url = remove_query_arg(array('tab', 'paso'));
$matricula_lock_tooltip = 'Debes completar y guardar tu matrícula primero.';
$tab_titles = array(
	'resumen' => 'Dashboard',
	'formularios' => 'Mis Retos',
	'matricula' => 'Matrícula',
);
$active_tab_title = $tab_titles[$active_docente_tab] ?? 'Dashboard';
?>
<div class="gnf-dashboard">
	<!-- Sidebar -->
	<aside class="gnf-sidebar">
		<div class="gnf-sidebar__logo">
			<img src="<?php echo esc_url(GNF_URL . 'assets/logo-guardiana.png'); ?>" alt="Guardianes" style="height: 40px; width: auto;" />
			<div>
				<small class="gnf-sidebar__logo-subtitle">Registro Participación C.E.</small>
			</div>
		</div>

		<nav class="gnf-sidebar__nav">
			<div class="gnf-sidebar__section">
				<div class="gnf-sidebar__label">Navegación</div>
				<?php foreach ($docente_tabs as $tab_key => $tab_data) : ?>
					<?php
					$is_locked = (! $tiene_matricula && 'matricula' !== $tab_key);
					$tab_url = $is_locked ? '#' : add_query_arg('tab', $tab_key, $current_docente_url);
					$tab_classes = 'gnf-sidebar__link';
					if ($active_docente_tab === $tab_key && ! $is_locked) {
						$tab_classes .= ' is-active';
					}
					if ($is_locked) {
						$tab_classes .= ' is-disabled';
					}
					?>
					<a href="<?php echo esc_url($tab_url); ?>"
						class="<?php echo esc_attr($tab_classes); ?>"
						<?php echo $is_locked ? 'aria-disabled="true"' : ''; ?>
						<?php echo $is_locked ? 'data-tooltip="' . esc_attr($matricula_lock_tooltip) . '"' : ''; ?>
						title="<?php echo $is_locked ? esc_attr($matricula_lock_tooltip) : ''; ?>"
						<?php echo $is_locked ? 'onclick="return false;"' : ''; ?>>
						<span class="gnf-sidebar__icon"><?php echo $doc_icons[$tab_data['icon']]; ?></span>
						<?php echo esc_html($tab_data['label']); ?>
					</a>
				<?php endforeach; ?>
			</div>

			<div class="gnf-sidebar__section">
				<div class="gnf-sidebar__label">Mi Centro</div>
				<div class="gnf-sidebar__section-pad">
					<div class="gnf-sidebar__centro-widget">
						<div class="gnf-sidebar__centro-name" title="<?php echo esc_attr($centro_title ?: 'Sin centro'); ?>">
							<?php echo esc_html($centro_title ?: 'Sin centro'); ?>
						</div>
						<?php if ('N/D' !== $region_name && '' !== $region_name) : ?>
							<div class="gnf-sidebar__centro-dre"><?php echo esc_html($region_name); ?></div>
						<?php endif; ?>
						<div class="gnf-sidebar__centro-meta">
							<span class="gnf-sidebar__centro-pts"><?php echo esc_html($puntaje_total); ?> eco puntos</span>
							<?php if ($estrella > 0) : ?>
								<span class="gnf-sidebar__centro-stars"><?php echo esc_html(str_repeat('★', $estrella)); ?></span>
							<?php endif; ?>
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
				<div class="gnf-sidebar__role">Participante</div>
			</div>
		</div>
	</aside>

	<!-- Main Content -->
	<main class="gnf-main">
		<div class="gnf-main__inner">
			<!-- Page Header -->
			<div class="gnf-page-header">
				<div>
					<h1 class="gnf-page-title"><?php echo esc_html($active_tab_title); ?></h1>
					<p class="gnf-page-subtitle">Panel Docente &gt; <?php echo esc_html($active_tab_title); ?> | <?php echo esc_html($centro_title ?: 'Sin centro asignado'); ?></p>
				</div>
				<div class="gnf-page-header__actions">
					<label for="gnf-year-select" class="gnf-page-year__label">Año</label>
					<select id="gnf-year-select" class="gnf-page-year__select" onchange="location.href = '<?php echo esc_url(remove_query_arg('gnf_year')); ?>&gnf_year=' + this.value">
						<?php foreach ($years_available as $y) : ?>
							<option value="<?php echo esc_attr($y); ?>" <?php selected($anio, $y); ?>>
								<?php echo esc_html($y); ?><?php echo $y == $anio_activo ? ' (Activo)' : ''; ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<?php if ($dashboard_msg_error) : ?>
				<div class="gnf-alert gnf-alert--error" style="margin-bottom:16px;"><?php echo esc_html($dashboard_msg_error); ?></div>
			<?php endif; ?>
			<?php if ($dashboard_msg_notice) : ?>
				<div class="gnf-alert gnf-alert--info" style="margin-bottom:16px;"><?php echo esc_html($dashboard_msg_notice); ?></div>
			<?php endif; ?>

			<?php if ('pendiente' === $doc_estado || 'pendiente_de_aprobacion' === $doc_estado) : ?>
				<?php if ('matricula' !== $active_docente_tab) : ?>
					<p class="gnf-muted">Tu cuenta esta pendiente de aprobacion. Mientras se aprueba, puedes completar tu matrícula en la pestaña "Matrícula".</p>
					<?php return; ?>
				<?php endif; ?>
			<?php endif; ?>

			<?php if (! $tiene_matricula) : ?>
				<div class="gnf-card gnf-card--warning" style="margin-bottom:16px; background: #fff3cd; border-left: 4px solid #ffc107;">
					<h3>📋 Completa tu Matrícula</h3>
					<p>Para comenzar a registrar tus eco retos, primero debes completar la matrícula de tu centro educativo para el año <?php echo esc_html($anio); ?>.</p>
					<p>En la matrícula seleccionarás:</p>
					<ul style="margin-left: 20px;">
						<li>Tu meta de estrellas para este año</li>
						<li>Los eco retos en los que participará tu centro</li>
					</ul>
					<a class="gnf-btn" href="<?php echo esc_url(add_query_arg('tab', 'matricula', get_permalink())); ?>">Completar Matrícula <?php echo esc_html($anio); ?></a>
				</div>
				<?php return; ?>
			<?php endif; ?>

			<?php if ('resumen' === $active_docente_tab) : ?>
				<!-- ========== TAB: RESUMEN ========== -->
				<div class="gnf-doc-dashboard__hero">
					<div class="gnf-doc-dashboard__hero-main">
						<h2 class="gnf-doc-dashboard__hero-title">
							Meta anual: <?php echo esc_html($meta_estrellas); ?> estrella<?php echo $meta_estrellas > 1 ? 's' : ''; ?>
						</h2>
						<p class="gnf-doc-dashboard__hero-subtitle">
							Año <?php echo esc_html($anio); ?> | <?php echo esc_html($total_retos); ?> retos matriculados
						</p>
						<div class="gnf-doc-dashboard__hero-progress">
							<div class="gnf-progress">
								<div class="gnf-progress__bar" style="width: <?php echo esc_attr($progreso_general); ?>%;"></div>
							</div>
							<small><?php echo esc_html($progreso_general); ?>% de avance general</small>
						</div>
					</div>
					<div class="gnf-doc-dashboard__hero-stats">
						<div><strong><?php echo esc_html($puntaje_total); ?></strong><span>Eco puntos ganados</span></div>
						<div><strong><?php echo esc_html($puntos_potenciales); ?></strong><span>Eco puntos posibles</span></div>
					</div>
				</div>

				<div class="gnf-stats-grid gnf-stats-grid--4">
					<div class="gnf-stat-card">
						<div class="gnf-stat-card__icon gnf-stat-card__icon--forest">✓</div>
						<div class="gnf-stat-card__value"><?php echo esc_html($retos_completados); ?></div>
						<div class="gnf-stat-card__label">Aprobados</div>
					</div>
					<div class="gnf-stat-card">
						<div class="gnf-stat-card__icon gnf-stat-card__icon--ocean">⌛</div>
						<div class="gnf-stat-card__value"><?php echo esc_html($retos_enviados); ?></div>
						<div class="gnf-stat-card__label">En revisión</div>
					</div>
					<div class="gnf-stat-card">
						<div class="gnf-stat-card__icon gnf-stat-card__icon--sun">★</div>
						<div class="gnf-stat-card__value"><?php echo esc_html($retos_completos); ?></div>
						<div class="gnf-stat-card__label">Completos</div>
					</div>
					<div class="gnf-stat-card">
						<div class="gnf-stat-card__icon gnf-stat-card__icon--coral">!</div>
						<div class="gnf-stat-card__value"><?php echo esc_html($retos_pendientes); ?></div>
						<div class="gnf-stat-card__label">Pendientes</div>
					</div>
				</div>

				<div class="gnf-section">
					<div class="gnf-section__header">
						<span class="gnf-section__title">Mis Eco Retos Matriculados</span>
						<a href="<?php echo esc_url(add_query_arg('tab', 'formularios', $current_docente_url)); ?>" class="gnf-btn gnf-btn--sm">
							Ir a Mis Retos
						</a>
					</div>
					<div class="gnf-section__body gnf-section__body--table">
						<table class="gnf-table">
							<thead>
								<tr>
									<th>Reto</th>
									<th>Estado</th>
									<th>Puntaje</th>
									<th style="text-align:right;">Acción</th>
								</tr>
							</thead>
							<tbody>
								<?php if (! empty($retos_seleccionados)) : ?>
									<?php foreach ((array) $retos_seleccionados as $reto_id_resumen) : ?>
										<?php
										$reto_post_resumen = get_post($reto_id_resumen);
										if (! $reto_post_resumen || 'reto' !== $reto_post_resumen->post_type) {
											continue;
										}
										$entry_resumen = $entries_by[$reto_id_resumen] ?? null;
										$estado_resumen = $entry_resumen ? $entry_resumen->estado : 'no_iniciado';
										$puntaje_resumen = $entry_resumen ? (int) $entry_resumen->puntaje : 0;
										$puntaje_max_resumen = gnf_get_reto_max_points($reto_id_resumen, $anio);
										$icono_resumen = gnf_get_reto_icon_url($reto_id_resumen);
										$color_resumen = gnf_get_reto_color($reto_id_resumen);
										$reto_url_args = array('tab' => 'formularios');
										if (isset($wizard_step_map[(int) $reto_id_resumen])) {
											$reto_url_args['paso'] = $wizard_step_map[(int) $reto_id_resumen];
										}
										$reto_url = add_query_arg($reto_url_args, $current_docente_url);
										?>
										<tr>
											<td>
												<div class="gnf-doc-dashboard__reto-cell">
													<?php if ($icono_resumen) : ?>
														<span class="gnf-reto-icon-badge" style="background:<?php echo esc_attr($color_resumen); ?>1a;">
															<img src="<?php echo esc_url($icono_resumen); ?>" alt="" class="gnf-doc-dashboard__reto-icon" />
														</span>
													<?php endif; ?>
													<strong><?php echo esc_html($reto_post_resumen->post_title); ?></strong>
												</div>
											</td>
											<td><span class="gnf-status gnf-status--<?php echo esc_attr($estado_resumen); ?>"><?php echo esc_html(gnf_get_estado_label($estado_resumen)); ?></span></td>
											<td><strong><?php echo esc_html($puntaje_resumen); ?></strong> / <?php echo esc_html($puntaje_max_resumen); ?> eco puntos</td>
											<td style="text-align:right;">
												<a class="gnf-btn gnf-btn--sm gnf-btn--ghost" href="<?php echo esc_url($reto_url); ?>">Abrir reto</a>
											</td>
										</tr>
									<?php endforeach; ?>
								<?php else : ?>
									<tr>
										<td colspan="4" class="gnf-table__empty">No hay retos matriculados todavía.</td>
									</tr>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
				</div>

				<?php if ( $all_retos_complete && $retos_completos > 0 ) : ?>
					<div class="gnf-alert gnf-alert--success">
						<div class="gnf-alert__icon">✓</div>
						<div>
							<strong>Todo listo para enviar participación</strong>
							<p class="gnf-muted" style="margin-top:6px;">Todos los retos están completos. El puntaje se calculó automáticamente.</p>
							<button type="button" class="gnf-btn gnf-docente-enviar-participacion" data-centro-id="<?php echo esc_attr($centro_id); ?>" data-anio="<?php echo esc_attr($anio); ?>" style="margin-top:10px;">
								Enviar Participación para Revisión
							</button>
						</div>
					</div>
				<?php else : ?>
					<div class="gnf-alert gnf-alert--info">
						<div class="gnf-alert__icon">i</div>
						<div>
							<strong>Continúa completando tus retos</strong>
							<p class="gnf-muted" style="margin-top:6px;">El envío final se habilita cuando todos los retos estén completos.</p>
						</div>
					</div>
				<?php endif; ?>

				<div class="gnf-doc-dashboard__quick-grid">
					<div class="gnf-card">
						<h3 style="margin:0;">Retos disponibles</h3>
						<p class="gnf-muted">Puedes agregar <?php echo esc_html(count($retos_disponibles)); ?> reto(s) adicionales desde Matrícula.</p>
						<a href="<?php echo esc_url(add_query_arg('tab', 'matricula', $current_docente_url)); ?>" class="gnf-btn gnf-btn--ghost">Editar matrícula</a>
					</div>
					<div class="gnf-card">
						<h3 style="margin:0;">Centro educativo</h3>
						<p class="gnf-muted"><?php echo esc_html($centro_title ?: 'Sin centro asignado'); ?> | DRE: <?php echo esc_html($region_name); ?></p>
						<p class="gnf-muted">Estado centro: <span class="gnf-badge <?php echo esc_attr($_cen_sl['cls']); ?>"><?php echo esc_html($_cen_sl['label']); ?></span></p>
					</div>
				</div>

				<div class="gnf-section" style="margin-bottom:20px;">
					<div class="gnf-section__header">
						<span class="gnf-section__title">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;margin-right:6px;"><rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01M16 6h.01M12 6h.01M12 10h.01M8 10h.01M16 10h.01"/></svg>
							Centro Educativo
						</span>
						<?php if ($centro_id) : ?>
							<button type="button" class="gnf-btn gnf-btn--sm gnf-btn--ghost" id="gnf-open-centro-modal" title="Editar informacion del centro">
								<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
								Editar
							</button>
						<?php endif; ?>
					</div>
					<div class="gnf-section__body" style="padding:0;">
						<table class="gnf-info-table">
							<tbody>
								<tr>
									<th>Nombre</th>
									<td><?php echo esc_html($centro_title ?: '-'); ?></td>
									<th>Codigo MEP</th>
									<td><code><?php echo esc_html($codigo_mep ?: '-'); ?></code></td>
								</tr>
								<tr>
									<th>Dir. Regional</th>
									<td><?php echo esc_html($region_name); ?></td>
									<th>Circuito</th>
									<td><?php echo esc_html($centro_circuito ?: '-'); ?></td>
								</tr>
								<tr>
									<th>Provincia</th>
									<td><?php echo esc_html($centro_provincia ?: '-'); ?></td>
									<th>Canton</th>
									<td><?php echo esc_html($centro_canton ?: '-'); ?></td>
								</tr>
								<tr>
									<th>Telefono</th>
									<td><?php echo esc_html($centro_telefono ?: '-'); ?></td>
									<th>Correo institucional</th>
									<td><?php echo esc_html($centro_correo_institucional ?: '-'); ?></td>
								</tr>
								<tr>
									<th>Direccion</th>
									<td><?php echo esc_html($centro_direccion ?: '-'); ?></td>
									<th>Nivel educativo</th>
									<td><?php echo esc_html($centro_nivel_educativo_label ?: '-'); ?></td>
								</tr>
								<tr>
									<th>Dependencia</th>
									<td><?php echo esc_html($centro_dependencia_label ?: '-'); ?></td>
									<th>Jornada</th>
									<td><?php echo esc_html($centro_jornada_label ?: '-'); ?></td>
								</tr>
								<tr>
									<th>Tipologia</th>
									<td><?php echo esc_html($centro_tipologia_label ?: '-'); ?></td>
									<th>Total estudiantes</th>
									<td><?php echo esc_html($centro_total_estudiantes); ?></td>
								</tr>
								<tr>
									<th>Hombres</th>
									<td><?php echo esc_html($centro_estudiantes_hombres); ?></td>
									<th>Mujeres</th>
									<td><?php echo esc_html($centro_estudiantes_mujeres); ?></td>
								</tr>
								<tr>
									<th>Migrantes</th>
									<td><?php echo esc_html($centro_estudiantes_migrantes); ?></td>
									<th>Ultimo galardon</th>
									<td><?php echo $centro_ultimo_galardon ? esc_html(str_repeat('*', $centro_ultimo_galardon) . " ({$centro_ultimo_galardon})") : '-'; ?></td>
								</tr>
								<tr>
									<th>Ultimo ano participacion</th>
									<td><?php echo esc_html($centro_ultimo_anio_label ?: '-'); ?></td>
									<th>Cargo coordinador PBAE</th>
									<td><?php echo esc_html($coordinador_cargo_label ?: '-'); ?></td>
								</tr>
								<tr>
									<th>Coordinador PBAE</th>
									<td><?php echo esc_html($coordinador_nombre ?: '-'); ?></td>
									<th>Celular coordinador</th>
									<td><?php echo esc_html($coordinador_celular ?: '-'); ?></td>
								</tr>
								<tr>
									<th>Estado centro</th>
									<td><span class="gnf-badge <?php echo esc_attr($_cen_sl['cls']); ?>"><?php echo esc_html($_cen_sl['label']); ?></span></td>
									<th>Estado docente</th>
									<td><span class="gnf-badge <?php echo esc_attr($_doc_sl['cls']); ?>"><?php echo esc_html($_doc_sl['label']); ?></span></td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>

				<?php if ($centro_id) : ?>
					<div class="gnf-modal gnf-modal--scroll" id="gnf-centro-edit-modal">
						<div class="gnf-modal__content gnf-modal__content--scroll" style="max-width:860px;width:95%;">
							<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:var(--gnf-space-5);">
								<h3 class="gnf-modal__title" style="margin:0;">
									<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
									Editar Centro Educativo
								</h3>
								<button type="button" class="gnf-modal-close" id="gnf-close-centro-modal" style="background:none;border:none;cursor:pointer;color:var(--gnf-gray-500);font-size:1.5rem;line-height:1;padding:4px;">&times;</button>
							</div>
							<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
								<?php wp_nonce_field('gnf_update_centro', 'gnf_nonce'); ?>
								<input type="hidden" name="action" value="gnf_update_centro" />
								<input type="hidden" name="centro_id" value="<?php echo esc_attr($centro_id); ?>" />
								<input type="hidden" id="gnf-centro-docente-nombre" name="docente_nombre" value="<?php echo esc_attr($user->display_name); ?>" />
								<div class="gnf-modal-form-grid">
									<div class="gnf-form-group">
										<label class="gnf-label">Nombre del Centro</label>
										<input type="text" name="centro_nombre" class="gnf-input" value="<?php echo esc_attr($centro_title); ?>" required />
									</div>
									<div class="gnf-form-group">
										<label class="gnf-label">Codigo MEP</label>
										<input type="text" name="centro_codigo" class="gnf-input" value="<?php echo esc_attr($codigo_mep); ?>" required />
									</div>
									<div class="gnf-form-group">
										<label class="gnf-label">Correo institucional</label>
										<input type="email" name="centro_correo_institucional" class="gnf-input" value="<?php echo esc_attr($centro_correo_institucional); ?>" required />
									</div>
									<div class="gnf-form-group">
										<label class="gnf-label">Telefono</label>
										<input type="text" name="centro_telefono" class="gnf-input" value="<?php echo esc_attr($centro_telefono); ?>" required />
									</div>
									<div class="gnf-form-group">
										<label class="gnf-label">Direccion Regional</label>
										<select name="centro_region" class="gnf-input" required>
											<option value="">- Seleccione -</option>
											<?php if (! is_wp_error($centro_regions)) : ?>
												<?php foreach ($centro_regions as $region_item) : ?>
													<option value="<?php echo esc_attr($region_item->term_id); ?>" <?php selected((int) $region_term, (int) $region_item->term_id); ?>><?php echo esc_html($region_item->name); ?></option>
												<?php endforeach; ?>
											<?php endif; ?>
										</select>
									</div>
									<div class="gnf-form-group">
										<label class="gnf-label">Circuito</label>
										<input type="text" name="centro_circuito" class="gnf-input" value="<?php echo esc_attr($centro_circuito); ?>" required />
									</div>
									<div class="gnf-form-group">
										<label class="gnf-label">Provincia</label>
										<select id="gnf-centro-edit-provincia" name="centro_provincia" class="gnf-input" required>
											<option value="">- Seleccione -</option>
											<?php foreach ($centro_provincias as $provincia_item) : ?>
												<option value="<?php echo esc_attr($provincia_item); ?>" <?php selected((string) $centro_provincia, (string) $provincia_item); ?>><?php echo esc_html($provincia_item); ?></option>
											<?php endforeach; ?>
										</select>
									</div>
									<div class="gnf-form-group">
										<label class="gnf-label">Canton</label>
										<select id="gnf-centro-edit-canton" name="centro_canton" class="gnf-input" data-selected="<?php echo esc_attr($centro_canton); ?>" required>
											<option value="">- Seleccione -</option>
										</select>
									</div>
									<div class="gnf-form-group gnf-form-group--full">
										<label class="gnf-label">Direccion exacta</label>
										<textarea name="centro_direccion" rows="2" class="gnf-input" style="resize:vertical;" required><?php echo esc_textarea($centro_direccion); ?></textarea>
									</div>
									<div class="gnf-form-group">
										<label class="gnf-label">Nivel educativo</label>
										<select name="centro_nivel_educativo" class="gnf-input" required>
											<option value="">- Seleccione -</option>
											<?php foreach ($centro_profile_choices['nivel_educativo'] as $choice_key => $choice_label) : ?>
												<option value="<?php echo esc_attr($choice_key); ?>" <?php selected((string) $centro_nivel_educativo, (string) $choice_key); ?>><?php echo esc_html($choice_label); ?></option>
											<?php endforeach; ?>
										</select>
									</div>
									<div class="gnf-form-group">
										<label class="gnf-label">Dependencia</label>
										<select name="centro_dependencia" class="gnf-input" required>
											<option value="">- Seleccione -</option>
											<?php foreach ($centro_profile_choices['dependencia'] as $choice_key => $choice_label) : ?>
												<option value="<?php echo esc_attr($choice_key); ?>" <?php selected((string) $centro_dependencia, (string) $choice_key); ?>><?php echo esc_html($choice_label); ?></option>
											<?php endforeach; ?>
										</select>
									</div>
									<div class="gnf-form-group">
										<label class="gnf-label">Jornada</label>
										<select name="centro_jornada" class="gnf-input" required>
											<option value="">- Seleccione -</option>
											<?php foreach ($centro_profile_choices['jornada'] as $choice_key => $choice_label) : ?>
												<option value="<?php echo esc_attr($choice_key); ?>" <?php selected((string) $centro_jornada, (string) $choice_key); ?>><?php echo esc_html($choice_label); ?></option>
											<?php endforeach; ?>
										</select>
									</div>
									<div class="gnf-form-group">
										<label class="gnf-label">Tipologia</label>
										<select name="centro_tipologia" class="gnf-input" required>
											<option value="">- Seleccione -</option>
											<?php foreach ($centro_profile_choices['tipologia'] as $choice_key => $choice_label) : ?>
												<option value="<?php echo esc_attr($choice_key); ?>" <?php selected((string) $centro_tipologia, (string) $choice_key); ?>><?php echo esc_html($choice_label); ?></option>
											<?php endforeach; ?>
										</select>
									</div>
									<div class="gnf-form-group">
										<label class="gnf-label">Total estudiantes</label>
										<input type="number" min="0" name="centro_total_estudiantes" class="gnf-input" value="<?php echo esc_attr($centro_total_estudiantes); ?>" required />
									</div>
									<div class="gnf-form-group">
										<label class="gnf-label">Hombres</label>
										<input type="number" min="0" name="centro_estudiantes_hombres" class="gnf-input" value="<?php echo esc_attr($centro_estudiantes_hombres); ?>" required />
									</div>
									<div class="gnf-form-group">
										<label class="gnf-label">Mujeres</label>
										<input type="number" min="0" name="centro_estudiantes_mujeres" class="gnf-input" value="<?php echo esc_attr($centro_estudiantes_mujeres); ?>" required />
									</div>
									<div class="gnf-form-group">
										<label class="gnf-label">Migrantes</label>
										<input type="number" min="0" name="centro_estudiantes_migrantes" class="gnf-input" value="<?php echo esc_attr($centro_estudiantes_migrantes); ?>" />
									</div>
									<div class="gnf-form-group">
										<label class="gnf-label">Ultimo galardon</label>
										<select name="centro_ultimo_galardon_estrellas" class="gnf-input" required>
											<?php foreach ($centro_profile_choices['ultimo_galardon_estrellas'] as $choice_key => $choice_label) : ?>
												<option value="<?php echo esc_attr($choice_key); ?>" <?php selected((string) $centro_ultimo_galardon, (string) $choice_key); ?>><?php echo esc_html($choice_label); ?></option>
											<?php endforeach; ?>
										</select>
									</div>
									<div class="gnf-form-group">
										<label class="gnf-label">Ultimo ano de participacion</label>
										<select id="gnf-centro-edit-ultimo-anio" name="centro_ultimo_anio_participacion" class="gnf-input" required>
											<?php foreach ($centro_profile_choices['ultimo_anio_participacion'] as $choice_key => $choice_label) : ?>
												<option value="<?php echo esc_attr($choice_key); ?>" <?php selected((string) $centro_ultimo_anio, (string) $choice_key); ?>><?php echo esc_html($choice_label); ?></option>
											<?php endforeach; ?>
										</select>
									</div>
									<div class="gnf-form-group" id="gnf-centro-edit-otro-wrap" style="<?php echo 'otro' === (string) $centro_ultimo_anio ? '' : 'display:none;'; ?>">
										<label class="gnf-label">Especifica el ano</label>
										<input type="number" min="1900" max="<?php echo esc_attr(gmdate('Y')); ?>" name="centro_ultimo_anio_participacion_otro" class="gnf-input" value="<?php echo esc_attr($centro_ultimo_anio_otro); ?>" />
									</div>
									<div class="gnf-form-group">
										<label class="gnf-label">Cargo coordinador PBAE</label>
										<select id="gnf-centro-edit-coord-cargo" name="coordinador_cargo" class="gnf-input" required>
											<option value="">- Seleccione -</option>
											<?php foreach ($centro_profile_choices['coordinador_cargo'] as $choice_key => $choice_label) : ?>
												<option value="<?php echo esc_attr($choice_key); ?>" <?php selected((string) $coordinador_cargo, (string) $choice_key); ?>><?php echo esc_html($choice_label); ?></option>
											<?php endforeach; ?>
										</select>
									</div>
									<div class="gnf-form-group" id="gnf-centro-edit-coord-nombre-wrap" style="<?php echo 'director' === (string) $coordinador_cargo ? 'display:none;' : ''; ?>">
										<label class="gnf-label">Nombre coordinador PBAE</label>
										<input type="text" id="gnf-centro-edit-coord-nombre" name="coordinador_nombre" class="gnf-input" value="<?php echo esc_attr($coordinador_nombre); ?>" />
									</div>
									<div class="gnf-form-group">
										<label class="gnf-label">Celular coordinador PBAE</label>
										<input type="text" name="coordinador_celular" class="gnf-input" value="<?php echo esc_attr($coordinador_celular); ?>" required />
									</div>
								</div>
								<div style="display:flex;gap:12px;justify-content:flex-end;margin-top:var(--gnf-space-5);padding-top:var(--gnf-space-4);border-top:1px solid var(--gnf-gray-200);">
									<button type="button" class="gnf-btn gnf-btn--ghost" id="gnf-cancel-centro-modal">Cancelar</button>
									<button type="submit" class="gnf-btn">Guardar cambios</button>
								</div>
							</form>
						</div>
					</div>
				<?php endif; ?>
			<?php endif; ?>

			<?php if ('formularios' === $active_docente_tab) : ?>
				<!-- ========== TAB: MIS RETOS (Wizard) ========== -->
				<?php
				// Cargar datos del wizard.
				$wizard_data = gnf_get_wizard_data($user->ID, $centro_id, $anio);
				extract($wizard_data);
				include GNF_PATH . 'templates/wizard.php';
				?>
			<?php endif; ?>

			<?php if ('matricula' === $active_docente_tab) : ?>
				<!-- ========== TAB: MATRÍCULA ========== -->
				<?php
				$matricula_retos             = $centro_id ? gnf_get_centro_retos_seleccionados($centro_id, $anio_activo) : array();
				$matricula_meta_estrellas    = $centro_id ? gnf_get_centro_meta_estrellas($centro_id, $anio_activo) : 0;
				$matricula_puntaje_total     = $centro_id ? gnf_get_centro_puntaje_total($centro_id, $anio_activo) : 0;
				$matricula_puntos_potenciales = $centro_id ? gnf_get_puntos_potenciales($centro_id, $anio_activo) : 0;
				?>
				<div class="gnf-section">
					<div class="gnf-section__header">
						<h2 class="gnf-section__title">Matrícula <?php echo esc_html($anio_activo); ?></h2>
					</div>
					<div class="gnf-section__body">
						<div class="gnf-card" style="border-left:4px solid var(--gnf-ocean);">
							<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">
								<div>
									<h3 style="margin:0;">Resumen de matrícula <?php echo esc_html($anio_activo); ?></h3>
									<p class="gnf-muted" style="margin:6px 0 0;">
										Revisa la información actual y edita en una ventana emergente por tipo de dato.
									</p>
								</div>
								<div style="display:flex;gap:10px;flex-wrap:wrap;">
									<button type="button" class="gnf-btn" id="gnf-open-matricula-retos-modal">Editar matrícula de eco retos</button>
									<button type="button" class="gnf-btn gnf-btn--ghost" id="gnf-open-matricula-centro-modal">Editar datos del centro</button>
								</div>
							</div>
							<div class="gnf-stats-grid gnf-stats-grid--4" style="margin:16px 0 0;">
								<div class="gnf-stat-card">
									<div class="gnf-stat-card__value"><?php echo esc_html($matricula_meta_estrellas); ?></div>
									<div class="gnf-stat-card__label">Meta de estrellas</div>
								</div>
								<div class="gnf-stat-card">
									<div class="gnf-stat-card__value"><?php echo esc_html(count((array) $matricula_retos)); ?></div>
									<div class="gnf-stat-card__label">Retos matriculados</div>
								</div>
								<div class="gnf-stat-card">
									<div class="gnf-stat-card__value"><?php echo esc_html($matricula_puntaje_total); ?></div>
									<div class="gnf-stat-card__label">Eco puntos actuales</div>
								</div>
								<div class="gnf-stat-card">
									<div class="gnf-stat-card__value"><?php echo esc_html($matricula_puntos_potenciales); ?></div>
									<div class="gnf-stat-card__label">Eco puntos posibles</div>
								</div>
							</div>
							<?php if (! empty($matricula_retos) && is_array($matricula_retos)) : ?>
								<div style="margin-top:16px;">
									<h4 style="margin:0 0 12px;">Retos actualmente matriculados (<?php echo count($matricula_retos); ?>)</h4>
									<div class="gnf-matricula-retos-grid">
										<?php foreach ($matricula_retos as $reto_id_m) :
											$reto_m = get_post($reto_id_m);
											if (! $reto_m) {
												continue;
											}
											$entry_m = $entries_by[$reto_id_m] ?? null;
											$puntaje_actual_m = $entry_m ? (int) $entry_m->puntaje : 0;
											$puntaje_max_m = gnf_get_reto_max_points($reto_id_m, $anio_activo);
											$icono_m = gnf_get_reto_icon_url($reto_id_m);
											$color_m = gnf_get_reto_color($reto_id_m);
										?>
											<div class="gnf-matricula-reto-item">
												<div class="gnf-matricula-reto-item__header">
													<?php if ($icono_m) : ?>
														<span class="gnf-matricula-reto-item__icon" style="border-color:<?php echo esc_attr($color_m); ?>55;">
															<img src="<?php echo esc_url($icono_m); ?>" alt="" />
														</span>
													<?php endif; ?>
													<strong><?php echo esc_html($reto_m->post_title); ?></strong>
												</div>
												<div class="gnf-matricula-reto-item__points"><?php echo esc_html($puntaje_actual_m); ?> / <?php echo esc_html($puntaje_max_m); ?> eco puntos</div>
											</div>
										<?php endforeach; ?>
									</div>
								</div>
							<?php else : ?>
								<div class="gnf-alert gnf-alert--info" style="margin-top:16px;">
									<div class="gnf-alert__icon">i</div>
									<div>No hay retos matriculados en este año.</div>
								</div>
							<?php endif; ?>
						</div>

						<div class="gnf-modal gnf-modal--scroll" id="gnf-matricula-retos-modal">
							<div class="gnf-modal__content gnf-modal__content--scroll" style="max-width:1120px;width:96%;">
								<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
									<h3 class="gnf-modal__title" style="margin:0;">Editar matrícula de eco retos <?php echo esc_html($anio_activo); ?></h3>
									<button type="button" class="gnf-modal-close" id="gnf-close-matricula-retos-modal" style="background:none;border:none;cursor:pointer;color:var(--gnf-gray-500);font-size:1.5rem;line-height:1;padding:4px;">&times;</button>
								</div>
								<?php echo gnf_render_matricula_form(array('centro_id' => $centro_id, 'anio' => $anio_activo, 'section' => 'retos')); ?>
							</div>
						</div>

						<div class="gnf-modal gnf-modal--scroll" id="gnf-matricula-centro-modal">
							<div class="gnf-modal__content gnf-modal__content--scroll" style="max-width:1120px;width:96%;">
								<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
									<h3 class="gnf-modal__title" style="margin:0;">Editar datos del centro <?php echo esc_html($anio_activo); ?></h3>
									<button type="button" class="gnf-modal-close" id="gnf-close-matricula-centro-modal" style="background:none;border:none;cursor:pointer;color:var(--gnf-gray-500);font-size:1.5rem;line-height:1;padding:4px;">&times;</button>
								</div>
								<?php echo gnf_render_matricula_form(array('centro_id' => $centro_id, 'anio' => $anio_activo, 'section' => 'centro')); ?>
							</div>
						</div>
					</div>
				</div>
			<?php endif; ?>

		</div><!-- /.gnf-main__inner -->
	</main>
</div><!-- /.gnf-dashboard -->

<script>
	document.addEventListener('DOMContentLoaded', function() {
		function bindModal(modalId, openSelectors, closeSelectors) {
			var modal = document.getElementById(modalId);
			if (!modal) return;

			var openList = Array.isArray(openSelectors) ? openSelectors : [openSelectors];
			var closeList = Array.isArray(closeSelectors) ? closeSelectors : [closeSelectors];

			function openModal() {
				modal.classList.add('is-open');
				document.body.style.overflow = 'hidden';
			}

			function closeModal() {
				modal.classList.remove('is-open');
				if (!document.querySelector('.gnf-modal.is-open')) {
					document.body.style.overflow = '';
				}
			}

			openList.forEach(function(selector) {
				if (!selector) return;
				document.querySelectorAll(selector).forEach(function(btn) {
					btn.addEventListener('click', openModal);
				});
			});
			closeList.forEach(function(selector) {
				if (!selector) return;
				document.querySelectorAll(selector).forEach(function(btn) {
					btn.addEventListener('click', closeModal);
				});
			});
			modal.addEventListener('click', function(e) {
				if (e.target === modal) {
					closeModal();
				}
			});
		}

		bindModal('gnf-centro-edit-modal', '#gnf-open-centro-modal', ['#gnf-close-centro-modal', '#gnf-cancel-centro-modal']);
		bindModal('gnf-matricula-retos-modal', '#gnf-open-matricula-retos-modal', '#gnf-close-matricula-retos-modal');
		bindModal('gnf-matricula-centro-modal', '#gnf-open-matricula-centro-modal', '#gnf-close-matricula-centro-modal');

		document.addEventListener('keydown', function(e) {
			if (e.key !== 'Escape') return;
			document.querySelectorAll('.gnf-modal.is-open').forEach(function(modal) {
				modal.classList.remove('is-open');
			});
			document.body.style.overflow = '';
		});

		var provinciaCantones = <?php echo wp_json_encode($centro_cantones_map, JSON_UNESCAPED_UNICODE); ?>;
		var provinciaSel = document.getElementById('gnf-centro-edit-provincia');
		var cantonSel = document.getElementById('gnf-centro-edit-canton');
		function norm(v){ return (v || '').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim(); }
		function getCantones(provincia) {
			var p = norm(provincia);
			for (var key in provinciaCantones) {
				if (norm(key) === p) return provinciaCantones[key] || [];
			}
			return [];
		}
		function fillCantones(selected) {
			if (!provinciaSel || !cantonSel) return;
			var cantones = getCantones(provinciaSel.value);
			var current = selected || cantonSel.getAttribute('data-selected') || '';
			cantonSel.innerHTML = '<option value="">- Seleccione -</option>';
			cantones.forEach(function(canton){
				var option = document.createElement('option');
				option.value = canton;
				option.textContent = canton;
				if (norm(current) === norm(canton)) option.selected = true;
				cantonSel.appendChild(option);
			});
		}
		if (provinciaSel && cantonSel) {
			fillCantones(cantonSel.value || cantonSel.getAttribute('data-selected'));
			provinciaSel.addEventListener('change', function(){ fillCantones(''); });
		}

		var ultimoAnioSel = document.getElementById('gnf-centro-edit-ultimo-anio');
		var ultimoAnioOtroWrap = document.getElementById('gnf-centro-edit-otro-wrap');
		if (ultimoAnioSel && ultimoAnioOtroWrap) {
			var toggleAnio = function(){ ultimoAnioOtroWrap.style.display = ultimoAnioSel.value === 'otro' ? '' : 'none'; };
			ultimoAnioSel.addEventListener('change', toggleAnio);
			toggleAnio();
		}

		var cargoSel = document.getElementById('gnf-centro-edit-coord-cargo');
		var coordNombreWrap = document.getElementById('gnf-centro-edit-coord-nombre-wrap');
		var coordNombreInput = document.getElementById('gnf-centro-edit-coord-nombre');
		var docenteNombre = document.getElementById('gnf-centro-docente-nombre');
		if (cargoSel && coordNombreWrap) {
			var toggleCoord = function() {
				var isDirector = cargoSel.value === 'director';
				coordNombreWrap.style.display = isDirector ? 'none' : '';
				if (isDirector && coordNombreInput && docenteNombre) {
					coordNombreInput.value = docenteNombre.value || '';
				}
			};
			cargoSel.addEventListener('change', toggleCoord);
			toggleCoord();
		}

		// Enviar participación
		document.querySelectorAll('.gnf-docente-enviar-participacion').forEach(function(btn) {
			btn.addEventListener('click', function() {
				var centroId = this.dataset.centroId;
				var anio = this.dataset.anio || '';
				if (!centroId) return;

				if (!confirm('¿Enviar todos los retos completos para revisión? El supervisor de tu región los revisará.')) {
					return;
				}

				this.disabled = true;
				this.textContent = 'Enviando...';

				fetch(gnfData.ajaxUrl, {
						method: 'POST',
						headers: {
							'Content-Type': 'application/x-www-form-urlencoded'
						},
						body: 'action=gnf_enviar_participacion&nonce=' + gnfData.nonce + '&centro_id=' + centroId + '&anio=' + anio
					})
					.then(function(res) {
						return res.json();
					})
					.then(function(data) {
						if (data.success) {
							alert('🎉 ' + data.data.message);
							location.reload();
						} else {
							alert('❌ ' + (data.data || 'Error al enviar participación'));
							btn.disabled = false;
							btn.textContent = '📤 Enviar Participación para Revisión';
						}
					})
					.catch(function() {
						alert('❌ Error de conexión');
						btn.disabled = false;
						btn.textContent = '📤 Enviar Participación para Revisión';
					});
			});
		});

		// Agregar reto a matrícula
		document.querySelectorAll('.gnf-docente-agregar-reto').forEach(function(btn) {
			btn.addEventListener('click', function() {
				var centroId = this.dataset.centroId;
				var retoId = this.dataset.retoId;
				var yearSelect = document.getElementById('gnf-year-select');
				var anio = yearSelect ? yearSelect.value : '<?php echo esc_js((string) $anio); ?>';
				if (!centroId || !retoId) return;

				if (!confirm('¿Agregar este reto a tu matrícula?')) {
					return;
				}

				this.disabled = true;
				this.textContent = 'Agregando...';

				fetch(gnfData.ajaxUrl, {
						method: 'POST',
						headers: {
							'Content-Type': 'application/x-www-form-urlencoded'
						},
						body: 'action=gnf_agregar_reto_matricula&nonce=' + gnfData.nonce + '&centro_id=' + centroId + '&reto_id=' + retoId + '&anio=' + encodeURIComponent(anio)
					})
					.then(function(res) {
						return res.json();
					})
					.then(function(data) {
						if (data.success) {
							alert('✅ ' + data.data.message);
							location.reload();
						} else {
							alert('❌ ' + (data.data || 'Error al agregar reto'));
							btn.disabled = false;
							btn.textContent = '➕ Agregar a mi matrícula';
						}
					})
					.catch(function() {
						alert('❌ Error de conexión');
						btn.disabled = false;
						btn.textContent = '➕ Agregar a mi matrícula';
					});
			});
		});

	});
</script>

