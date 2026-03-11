<?php
/**
 * Registro de shortcodes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_shortcode(
	'gn_docente_panel',
	function () {
		if ( ! is_user_logged_in() ) {
			return gnf_render_react_panel( 'auth', array( 'redirectTo' => 'docente' ) );
		}
		$user      = wp_get_current_user();
		$centro_id = function_exists( 'gnf_get_centro_for_docente' ) ? gnf_get_centro_for_docente( $user->ID ) : 0;
		return gnf_render_react_panel(
			'docente',
			array(
				'centroId'       => $centro_id,
				'availableYears' => array( (int) gmdate( 'Y' ) ),
			)
		);
	}
);

add_shortcode(
	'gn_supervisor_panel',
	function () {
		if ( ! is_user_logged_in() ) {
			return gnf_render_react_panel( 'auth', array( 'redirectTo' => 'supervisor' ) );
		}
		$user      = wp_get_current_user();
		$region_id = function_exists( 'gnf_get_user_region' ) ? gnf_get_user_region( $user->ID ) : 0;
		return gnf_render_react_panel(
			'supervisor',
			array(
				'regionId'       => $region_id,
				'availableYears' => array( (int) gmdate( 'Y' ) ),
			)
		);
	}
);

/**
 * Panel de Administrador Frontend.
 * Acceso completo a gestión de usuarios, centros, retos, reportes y configuración.
 */
add_shortcode(
	'gn_admin_panel',
	function () {
		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			return gnf_render_react_panel( 'auth', array( 'redirectTo' => 'admin' ) );
		}
		return gnf_render_react_panel(
			'admin',
			array( 'availableYears' => array( (int) gmdate( 'Y' ) ) )
		);
	}
);

/**
 * Panel del Comité Bandera Azul Ecológica.
 * Vista de todos los centros con capacidad de validación final.
 */
add_shortcode(
	'gn_comite_panel',
	function () {
		if ( ! is_user_logged_in() ) {
			return gnf_render_react_panel( 'auth', array( 'redirectTo' => 'comite' ) );
		}
		return gnf_render_react_panel(
			'comite',
			array( 'availableYears' => array( (int) gmdate( 'Y' ) ) )
		);
	}
);

add_shortcode(
	'gn_notificaciones',
	function () {
		if ( ! is_user_logged_in() ) {
			return '<p>Debes iniciar sesión.</p>';
		}
		global $wpdb;
		$user_id = get_current_user_id();
		$table   = $wpdb->prefix . 'gn_notificaciones';
		$items   = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d AND leido = 0 ORDER BY created_at DESC",
				$user_id
			)
		);
		ob_start();
		if ( empty( $items ) ) {
			echo '<p>Sin notificaciones pendientes.</p>';
		} else {
			echo '<ul class="gnf-notificaciones">';
			foreach ( $items as $item ) {
				echo '<li><strong>' . esc_html( $item->tipo ) . ':</strong> ' . esc_html( $item->mensaje ) . ' <small>' . esc_html( $item->created_at ) . '</small></li>';
				$wpdb->update( $table, array( 'leido' => 1 ), array( 'id' => $item->id ), array( '%d' ), array( '%d' ) );
			}
			echo '</ul>';
		}
		return ob_get_clean();
	}
);

/**
 * Shortcode del Wizard Maestro.
 * Decisión BAE: Los docentes llenan un solo formulario tipo wizard.
 * Nota: El wizard ahora está integrado como pestaña en el panel docente.
 */
add_shortcode(
	'gn_wizard',
	function () {
		// Si está como shortcode independiente, mostrar mensaje con link al panel.
		if ( is_user_logged_in() ) {
			$panel_url = add_query_arg( 'tab', 'formularios', home_url( '/panel-docente/' ) );
			return '<div class="gnf-wizard-redirect" style="padding: 24px; text-align: center; background: #f0f9ff; border-radius: 12px;">
				<p style="margin: 0 0 16px;">El wizard de formularios ahora está integrado en el Panel Docente.</p>
				<a href="' . esc_url( $panel_url ) . '" class="gnf-btn" style="display: inline-block; padding: 12px 24px; background: #369484; color: #fff; text-decoration: none; border-radius: 8px;">
					Ir al Panel Docente → Formularios
				</a>
			</div>';
		}
		// Si no está logueado, mostrar el wizard completo.
		return gnf_render_wizard();
	}
);

/**
 * Shortcode para registro de supervisores.
 * Los supervisores se registran y quedan pendientes de aprobación.
 */
add_shortcode(
	'gn_registro_supervisor',
	function () {
		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			if ( in_array( 'supervisor', (array) $user->roles, true ) || in_array( 'comite_bae', (array) $user->roles, true ) ) {
				return '<p>Ya tienes una cuenta de supervisor. <a href="' . esc_url( home_url( '/panel-supervisor/' ) ) . '">Ir al panel</a></p>';
			}
		}
		return gnf_render_react_panel( 'auth', array( 'defaultTab' => 'register-supervisor' ) );
	}
);

/**
 * Mock shortcodes para visualizar los paneles con datos de prueba.
 * Útil para desarrollo y demostración sin necesidad de datos reales.
 * Usa datos reales de retos desde seeders/retos-data.json
 */
add_shortcode(
	'mock_docente',
	function () {
		// Encolar estilos.
		wp_enqueue_style( 'gnf-guardianes', GNF_URL . 'assets/css/guardianes.css', array(), GNF_VERSION );

		ob_start();
		?>
		<div class="gnf-panel gnf-docente">
			<header class="gnf-panel__header">
				<div>
					<h2>Panel Docente</h2>
					<p class="gnf-muted">Escuela Verde Esperanza | Año 2025</p>
				</div>
				<div class="gnf-score">
					<strong>85 pts</strong>
					<span>3 ★</span>
				</div>
			</header>

			<!-- Info Centro Educativo -->
			<div class="gnf-card" style="margin-bottom:16px;">
				<h3>Centro educativo</h3>
				<p><strong>Nombre:</strong> Escuela Verde Esperanza | <strong>ID:</strong> 142</p>
				<p><strong>Codigo MEP:</strong> MEP-2024-0142 | <strong>Region:</strong> Central</p>
				<p><strong>Estado centro:</strong> activo | <strong>Estado docente:</strong> activo</p>
				<form class="gnf-inline-form" onsubmit="return false;">
					<label>Nombre <input type="text" name="centro_nombre" value="Escuela Verde Esperanza" /></label>
					<label>Direccion <textarea name="centro_direccion">San José, Montes de Oca, San Pedro, 200m norte del Mall</textarea></label>
					<label>Provincia <input type="text" name="centro_provincia" value="San José" /></label>
					<label>Canton <input type="text" name="centro_canton" value="Montes de Oca" /></label>
					<label>Codigo MEP <input type="text" name="centro_codigo" value="MEP-2024-0142" /></label>
					<label>Region (ID) <input type="number" name="centro_region" value="1" /></label>
					<button class="gnf-btn" type="button">Completar informacion del Centro Educativo</button>
				</form>
			</div>

			<!-- Resumen de progreso -->
			<div class="gnf-card" style="margin-bottom:16px; background: linear-gradient(135deg, #369484 0%, #2d7a6d 100%); color: white;">
				<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
					<div>
						<h3 style="color: white; margin: 0;">🎯 Meta: 3 Estrellas</h3>
						<p style="margin: 8px 0 0; opacity: 0.9;">Año 2025 | 6 retos matriculados</p>
					</div>
					<div style="display: flex; gap: 24px; text-align: center;">
						<div>
							<strong style="font-size: 24px; display: block;">2</strong>
							<small>Aprobados</small>
						</div>
						<div>
							<strong style="font-size: 24px; display: block;">1</strong>
							<small>En revisión</small>
						</div>
						<div>
							<strong style="font-size: 24px; display: block;">2</strong>
							<small>Pendientes</small>
						</div>
						<div style="color: #ffeb3b;">
							<strong style="font-size: 24px; display: block;">1</strong>
							<small>Corrección</small>
						</div>
					</div>
				</div>
				<div style="margin-top: 16px; background: rgba(255,255,255,0.2); border-radius: 8px; height: 8px; overflow: hidden;">
					<div style="height: 100%; background: #4ade80; width: 33%;"></div>
				</div>
				<small style="opacity: 0.8;">33% completado</small>
			</div>

			<h3 style="margin: 24px 0 16px;">📝 Mis Eco Retos Matriculados</h3>

			<div class="gnf-grid">
				<!-- Reto: Agua (Base) - Aprobado -->
				<div class="gnf-card">
					<div class="gnf-card__header">
						<img src="https://movimientoguardianes.org/wp-content/uploads/2025/02/Reto-Agua.jpg" alt="" class="gnf-icon" />
						<div>
							<h3 style="color: #273F80;">Agua (Base)</h3>
							<p class="gnf-muted">Implementar estrategias pedagógicas que sensibilicen sobre la importancia del agua, sus desafíos actuales y futuros, y promuevan hábitos sostenibles.</p>
						</div>
					</div>
					<p class="gnf-status gnf-status--aprobado">Estado: Aprobado</p>
					<p class="gnf-puntaje">Puntaje: 10 / 10</p>
					<div class="gnf-actions">
						<a class="gnf-btn" href="#">Llenar formulario</a>
						<a class="gnf-btn gnf-btn--ghost" href="https://movimientoguardianes.org/wp-content/uploads/2025/03/Eco-Reto-Agua-educar-y-movilizar.pdf" target="_blank">Ver PDF</a>
					</div>
				</div>

				<!-- Reto: Residuos (Base) - Aprobado -->
				<div class="gnf-card">
					<div class="gnf-card__header">
						<img src="https://movimientoguardianes.org/wp-content/uploads/2025/02/Reto-residuos.jpg" alt="" class="gnf-icon" />
						<div>
							<h3 style="color: #A03260;">Residuos (Base)</h3>
							<p class="gnf-muted">Impulsar el desarrollo o fortalecimiento de un programa de gestión integral de residuos en el centro educativo.</p>
						</div>
					</div>
					<p class="gnf-status gnf-status--aprobado">Estado: Aprobado</p>
					<p class="gnf-puntaje">Puntaje: 10 / 10</p>
					<div class="gnf-actions">
						<a class="gnf-btn" href="#">Llenar formulario</a>
						<a class="gnf-btn gnf-btn--ghost" href="https://movimientoguardianes.org/wp-content/uploads/2025/02/Eco-Reto-Residuos.pdf" target="_blank">Ver PDF</a>
					</div>
				</div>

				<!-- Reto: Energías limpias (Base) - Enviado -->
				<div class="gnf-card">
					<div class="gnf-card__header">
						<img src="https://movimientoguardianes.org/wp-content/uploads/2025/02/REto-energias-limpias.jpg" alt="" class="gnf-icon" />
						<div>
							<h3 style="color: #99BF12;">Energías limpias (Base)</h3>
							<p class="gnf-muted">Implementar estrategias pedagógicas que sensibilicen sobre la importancia de las energías limpias y promuevan hábitos sostenibles.</p>
						</div>
					</div>
					<p class="gnf-status gnf-status--enviado">Estado: Enviado</p>
					<p class="gnf-puntaje">Puntaje: 0 / 10</p>
					<div class="gnf-actions">
						<a class="gnf-btn" href="#">Llenar formulario</a>
						<a class="gnf-btn gnf-btn--ghost" href="https://movimientoguardianes.org/wp-content/uploads/2025/03/Eco-reto-energia-educar-y-movilizar.pdf" target="_blank">Ver PDF</a>
					</div>
				</div>

				<!-- Reto: Vida Marina - Corrección -->
				<div class="gnf-card">
					<div class="gnf-card__header">
						<img src="https://movimientoguardianes.org/wp-content/uploads/2025/02/Reto-vida-marina.jpg" alt="" class="gnf-icon" />
						<div>
							<h3 style="color: #50AAB1;">Vida Marina</h3>
							<p class="gnf-muted">Realizar actividades pedagógicas sobre la importancia de los océanos y sensibilización sobre problemas que afectan la vida marina.</p>
						</div>
					</div>
					<p class="gnf-status gnf-status--correccion">Estado: Corrección</p>
					<p class="gnf-puntaje">Puntaje: 3 / 5</p>
					<div class="gnf-actions">
						<a class="gnf-btn" href="#">Llenar formulario</a>
						<button class="gnf-btn gnf-btn--ghost" type="button" onclick="alert('Las fotos de evidencia no corresponden al año activo (2025). Por favor, sube fotos tomadas este año.')">Ver feedback</button>
						<button class="gnf-btn gnf-btn--ghost" type="button">Reabrir y corregir</button>
					</div>
					<ul class="gnf-muted">
						<li>Foto requiere validacion de año activo.</li>
						<li>Las fotos de evidencia no corresponden al año activo (2025). Por favor, sube fotos tomadas este año.</li>
					</ul>
				</div>

				<!-- Reto: Compostaje - No iniciado -->
				<div class="gnf-card">
					<div class="gnf-card__header">
						<img src="https://movimientoguardianes.org/wp-content/uploads/2025/02/Reto-compostaje.jpg" alt="" class="gnf-icon" />
						<div>
							<h3 style="color: #32A09A;">Compostaje</h3>
							<p class="gnf-muted">Poner en funcionamiento un sistema de compostaje eficiente a largo plazo para producir abono a partir de los residuos orgánicos.</p>
						</div>
					</div>
					<p class="gnf-status gnf-status--no_iniciado">Estado: No Iniciado</p>
					<p class="gnf-puntaje">Puntaje: 0 / 15</p>
					<div class="gnf-actions">
						<a class="gnf-btn" href="#">Llenar formulario</a>
						<a class="gnf-btn gnf-btn--ghost" href="https://movimientoguardianes.org/wp-content/uploads/2025/02/Reto-compostaje.pdf" target="_blank">Ver PDF</a>
					</div>
				</div>

				<!-- Reto: Huerta - En progreso -->
				<div class="gnf-card">
					<div class="gnf-card__header">
						<img src="https://movimientoguardianes.org/wp-content/uploads/2025/02/Reto-huerta-estudiantil.jpg" alt="" class="gnf-icon" />
						<div>
							<h3 style="color: #AADA63;">Huerta</h3>
							<p class="gnf-muted">Cultivar un huerto con la participación de estudiantes para adoptar prácticas de cultivo sostenibles y alimentación saludable.</p>
						</div>
					</div>
					<p class="gnf-status gnf-status--en_progreso">Estado: En Progreso</p>
					<p class="gnf-puntaje">Puntaje: 0 / 15</p>
					<div class="gnf-actions">
						<a class="gnf-btn" href="#">Continuar formulario</a>
						<a class="gnf-btn gnf-btn--ghost" href="https://movimientoguardianes.org/wp-content/uploads/2025/02/Reto-Huerta-2.pdf" target="_blank">Ver PDF</a>
					</div>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
);

add_shortcode(
	'mock_supervisor',
	function () {
		// Encolar estilos.
		wp_enqueue_style( 'gnf-guardianes', GNF_URL . 'assets/css/guardianes.css', array(), GNF_VERSION );

		ob_start();
		?>
		<div class="gnf-panel gnf-supervisor">
			<header class="gnf-panel__header">
				<div>
					<h2>Panel Supervisor</h2>
					<p class="gnf-muted">Año 2025 | Región: Central</p>
				</div>
				<div class="gnf-actions">
					<a class="gnf-btn" href="#">Exportar global CSV</a>
					<a class="gnf-btn gnf-btn--ghost" href="#">Exportar región</a>
				</div>
			</header>

			<!-- Resumen de estadísticas -->
			<div class="gnf-stats" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 24px;">
				<div class="gnf-stat" style="padding: 16px; background: #f0f9ff; border-radius: 8px; text-align: center;">
					<strong style="font-size: 28px; color: #0369a1; display: block;">12</strong>
					<span style="color: #64748b;">Centros</span>
				</div>
				<div class="gnf-stat" style="padding: 16px; background: #fef3c7; border-radius: 8px; text-align: center;">
					<strong style="font-size: 28px; color: #d97706; display: block;">8</strong>
					<span style="color: #64748b;">Pendientes revisión</span>
				</div>
				<div class="gnf-stat" style="padding: 16px; background: #dcfce7; border-radius: 8px; text-align: center;">
					<strong style="font-size: 28px; color: #16a34a; display: block;">23</strong>
					<span style="color: #64748b;">Aprobados</span>
				</div>
				<div class="gnf-stat" style="padding: 16px; background: #fee2e2; border-radius: 8px; text-align: center;">
					<strong style="font-size: 28px; color: #dc2626; display: block;">3</strong>
					<span style="color: #64748b;">En corrección</span>
				</div>
			</div>

			<table class="gnf-table">
				<thead>
					<tr>
						<th>Centro</th>
						<th>Código MEP</th>
						<th>Meta</th>
						<th>Puntaje</th>
						<th>Estrella</th>
						<th>Retos</th>
						<th>Estado</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<!-- Centro 1: Con retos pendientes -->
					<tr style="background: #fef3c7;">
						<td><strong>Escuela Verde Esperanza</strong></td>
						<td>MEP-2024-0142</td>
						<td>3 ★</td>
						<td>185 pts</td>
						<td>3 ★</td>
						<td>
							<span title="Aprobados" style="color: #16a34a;">✓2</span>
							<span title="Enviados" style="color: #d97706;">/📤1</span>
							<span title="Corrección" style="color: #dc2626;">/⚠1</span>
							<small style="color: #64748b;">de 5</small>
						</td>
						<td>
							<span style="background: #fbbf24; color: #000; padding: 2px 8px; border-radius: 4px; font-size: 11px;">📋 1 por revisar</span>
						</td>
						<td><a class="gnf-btn gnf-btn--ghost" href="#">Ver detalle</a></td>
					</tr>

					<!-- Centro 2: Con pendientes -->
					<tr style="background: #fef3c7;">
						<td><strong>Escuela Los Pinos</strong></td>
						<td>MEP-2024-0089</td>
						<td>2 ★</td>
						<td>120 pts</td>
						<td>2 ★</td>
						<td>
							<span title="Aprobados" style="color: #16a34a;">✓3</span>
							<span title="Enviados" style="color: #d97706;">/📤2</span>
							<small style="color: #64748b;">de 4</small>
						</td>
						<td>
							<span style="background: #fbbf24; color: #000; padding: 2px 8px; border-radius: 4px; font-size: 11px;">📋 2 por revisar</span>
						</td>
						<td><a class="gnf-btn gnf-btn--ghost" href="#">Ver detalle</a></td>
					</tr>

					<!-- Centro 3: Completo -->
					<tr>
						<td><strong>Colegio Montaña Azul</strong></td>
						<td>MEP-2024-0203</td>
						<td>3 ★</td>
						<td>240 pts</td>
						<td>3 ★</td>
						<td>
							<span title="Aprobados" style="color: #16a34a;">✓6</span>
							<span title="Enviados" style="color: #d97706;">/📤0</span>
							<small style="color: #64748b;">de 6</small>
						</td>
						<td>
							<span style="background: #22c55e; color: #fff; padding: 2px 8px; border-radius: 4px; font-size: 11px;">✅ Completo</span>
						</td>
						<td><a class="gnf-btn gnf-btn--ghost" href="#">Ver detalle</a></td>
					</tr>

					<!-- Centro 4: En progreso -->
					<tr>
						<td><strong>Escuela Las Palmas</strong></td>
						<td>MEP-2024-0056</td>
						<td>2 ★</td>
						<td>80 pts</td>
						<td>1 ★</td>
						<td>
							<span title="Aprobados" style="color: #16a34a;">✓2</span>
							<span title="Enviados" style="color: #d97706;">/📤0</span>
							<small style="color: #64748b;">de 4</small>
						</td>
						<td>
							<span style="color: #64748b; font-size: 11px;">En progreso</span>
						</td>
						<td><a class="gnf-btn gnf-btn--ghost" href="#">Ver detalle</a></td>
					</tr>

					<!-- Centro 5: Con corrección -->
					<tr>
						<td><strong>Liceo Nuevo Horizonte</strong></td>
						<td>MEP-2024-0178</td>
						<td>3 ★</td>
						<td>160 pts</td>
						<td>2 ★</td>
						<td>
							<span title="Aprobados" style="color: #16a34a;">✓4</span>
							<span title="Enviados" style="color: #d97706;">/📤0</span>
							<span title="Corrección" style="color: #dc2626;">/⚠2</span>
							<small style="color: #64748b;">de 6</small>
						</td>
						<td>
							<span style="color: #64748b; font-size: 11px;">En progreso</span>
						</td>
						<td><a class="gnf-btn gnf-btn--ghost" href="#">Ver detalle</a></td>
					</tr>

					<!-- Centro 6: Con pendientes -->
					<tr style="background: #fef3c7;">
						<td><strong>Escuela Rural El Porvenir</strong></td>
						<td>MEP-2024-0312</td>
						<td>1 ★</td>
						<td>40 pts</td>
						<td>1 ★</td>
						<td>
							<span title="Aprobados" style="color: #16a34a;">✓1</span>
							<span title="Enviados" style="color: #d97706;">/📤1</span>
							<small style="color: #64748b;">de 3</small>
						</td>
						<td>
							<span style="background: #fbbf24; color: #000; padding: 2px 8px; border-radius: 4px; font-size: 11px;">📋 1 por revisar</span>
						</td>
						<td><a class="gnf-btn gnf-btn--ghost" href="#">Ver detalle</a></td>
					</tr>

					<!-- Centro 7: Completo -->
					<tr>
						<td><strong>Centro Educativo San Rafael</strong></td>
						<td>MEP-2024-0098</td>
						<td>2 ★</td>
						<td>160 pts</td>
						<td>2 ★</td>
						<td>
							<span title="Aprobados" style="color: #16a34a;">✓4</span>
							<span title="Enviados" style="color: #d97706;">/📤0</span>
							<small style="color: #64748b;">de 4</small>
						</td>
						<td>
							<span style="background: #22c55e; color: #fff; padding: 2px 8px; border-radius: 4px; font-size: 11px;">✅ Completo</span>
						</td>
						<td><a class="gnf-btn gnf-btn--ghost" href="#">Ver detalle</a></td>
					</tr>

					<!-- Centro 8: En progreso -->
					<tr>
						<td><strong>Escuela Bilingüe Sol Naciente</strong></td>
						<td>MEP-2024-0267</td>
						<td>3 ★</td>
						<td>120 pts</td>
						<td>2 ★</td>
						<td>
							<span title="Aprobados" style="color: #16a34a;">✓3</span>
							<span title="Enviados" style="color: #d97706;">/📤0</span>
							<small style="color: #64748b;">de 5</small>
						</td>
						<td>
							<span style="color: #64748b; font-size: 11px;">En progreso</span>
						</td>
						<td><a class="gnf-btn gnf-btn--ghost" href="#">Ver detalle</a></td>
					</tr>

					<!-- Centro 9: Con pendientes -->
					<tr style="background: #fef3c7;">
						<td><strong>Colegio Técnico Industrial</strong></td>
						<td>MEP-2024-0445</td>
						<td>2 ★</td>
						<td>80 pts</td>
						<td>1 ★</td>
						<td>
							<span title="Aprobados" style="color: #16a34a;">✓2</span>
							<span title="Enviados" style="color: #d97706;">/📤2</span>
							<small style="color: #64748b;">de 4</small>
						</td>
						<td>
							<span style="background: #fbbf24; color: #000; padding: 2px 8px; border-radius: 4px; font-size: 11px;">📋 2 por revisar</span>
						</td>
						<td><a class="gnf-btn gnf-btn--ghost" href="#">Ver detalle</a></td>
					</tr>

					<!-- Centro 10: En progreso -->
					<tr>
						<td><strong>Escuela Jardín de Niños</strong></td>
						<td>MEP-2024-0123</td>
						<td>1 ★</td>
						<td>40 pts</td>
						<td>1 ★</td>
						<td>
							<span title="Aprobados" style="color: #16a34a;">✓1</span>
							<span title="Enviados" style="color: #d97706;">/📤0</span>
							<small style="color: #64748b;">de 2</small>
						</td>
						<td>
							<span style="color: #64748b; font-size: 11px;">En progreso</span>
						</td>
						<td><a class="gnf-btn gnf-btn--ghost" href="#">Ver detalle</a></td>
					</tr>

					<!-- Centro 11: Completo -->
					<tr>
						<td><strong>Liceo Experimental Bilingüe</strong></td>
						<td>MEP-2024-0389</td>
						<td>3 ★</td>
						<td>280 pts</td>
						<td>3 ★</td>
						<td>
							<span title="Aprobados" style="color: #16a34a;">✓7</span>
							<span title="Enviados" style="color: #d97706;">/📤0</span>
							<small style="color: #64748b;">de 7</small>
						</td>
						<td>
							<span style="background: #22c55e; color: #fff; padding: 2px 8px; border-radius: 4px; font-size: 11px;">✅ Completo</span>
						</td>
						<td><a class="gnf-btn gnf-btn--ghost" href="#">Ver detalle</a></td>
					</tr>

					<!-- Centro 12: En progreso -->
					<tr>
						<td><strong>Escuela Comunidad del Sur</strong></td>
						<td>MEP-2024-0501</td>
						<td>2 ★</td>
						<td>60 pts</td>
						<td>1 ★</td>
						<td>
							<span title="Aprobados" style="color: #16a34a;">✓1</span>
							<span title="Enviados" style="color: #d97706;">/📤0</span>
							<small style="color: #64748b;">de 4</small>
						</td>
						<td>
							<span style="color: #64748b; font-size: 11px;">En progreso</span>
						</td>
						<td><a class="gnf-btn gnf-btn--ghost" href="#">Ver detalle</a></td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
		return ob_get_clean();
	}
);

