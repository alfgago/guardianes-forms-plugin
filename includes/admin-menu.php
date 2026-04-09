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
		__( 'Reparar Formularios', 'guardianes-formularios' ),
		__( 'Reparar Formularios', 'guardianes-formularios' ),
		$cap,
		'gnf-tools',
		'gnf_render_admin_tools'
	);

	// Página oculta (sin parent): accesible por enlace directo pero no aparece en el menú.
	add_submenu_page(
		null,
		__('Editar Usuario', 'guardianes-formularios'),
		'',
		$cap,
		'gnf-usuario-editar',
		'gnf_render_admin_user_edit'
	);
}
add_action('admin_menu', 'gnf_register_admin_menu', 2);

/**
 * URL canonica de la pantalla de herramientas.
 *
 * @param array<string,mixed> $args Query args opcionales.
 * @return string
 */
function gnf_get_tools_page_url( $args = array() ) {
	$args = array_merge( array( 'page' => 'gnf-tools' ), $args );
	return add_query_arg( $args, admin_url( 'admin.php' ) );
}

/**
 * Renderiza notices de resultado para la pantalla de herramientas.
 *
 * @return void
 */
function gnf_render_tools_result_notices() {
	$run     = absint( $_GET['gnf_fixed_run'] ?? 0 );
	$updated = absint( $_GET['gnf_fixed_updated'] ?? 0 );
	$skipped = absint( $_GET['gnf_fixed_skipped'] ?? 0 );
	$failed  = absint( $_GET['gnf_fixed_failed'] ?? 0 );
	$scanned = absint( $_GET['gnf_fixed_scanned'] ?? 0 );

	$centro_fix_run       = absint( $_GET['gnf_center_fix_run'] ?? 0 );
	$centro_fix_scanned   = absint( $_GET['gnf_center_fix_scanned'] ?? 0 );
	$centro_fix_updated   = absint( $_GET['gnf_center_fix_updated'] ?? 0 );
	$centro_fix_skipped   = absint( $_GET['gnf_center_fix_skipped'] ?? 0 );
	$centro_fix_failed    = absint( $_GET['gnf_center_fix_failed'] ?? 0 );
	$centro_fix_claimed   = absint( $_GET['gnf_center_fix_claimed'] ?? 0 );
	$centro_fix_unmatched = absint( $_GET['gnf_center_fix_unmatched'] ?? 0 );

	if ( $run ) :
		?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html( sprintf( 'Correccion ejecutada. Revisados: %d. Actualizados a docente: %d. Omitidos: %d. Fallidos: %d.', $scanned, $updated, $skipped, $failed ) ); ?></p>
		</div>
		<?php
	endif;

	if ( $centro_fix_run ) :
		?>
		<div class="notice notice-success is-dismissible">
			<p><?php echo esc_html( sprintf( 'Asociacion masiva ejecutada. Revisados: %d. Actualizados: %d. Omitidos: %d. No encontrados: %d. En conflicto: %d. Fallidos: %d.', $centro_fix_scanned, $centro_fix_updated, $centro_fix_skipped, $centro_fix_unmatched, $centro_fix_claimed, $centro_fix_failed ) ); ?></p>
		</div>
		<?php
	endif;

	$result = get_transient( gnf_get_reimport_result_key() );
	if ( $result ) {
		delete_transient( gnf_get_reimport_result_key() );
		$errors_html = '';
		if ( ! empty( $result['errors'] ) ) {
			$errors_html = '<ul style="margin:8px 0 0;padding-left:20px;">';
			foreach ( array_slice( $result['errors'], 0, 5 ) as $err ) {
				$errors_html .= '<li>' . esc_html( $err ) . '</li>';
			}
			if ( count( $result['errors'] ) > 5 ) {
				$errors_html .= '<li>... y ' . ( count( $result['errors'] ) - 5 ) . ' errores mas.</li>';
			}
			$errors_html .= '</ul>';
		}

		$variant      = empty( $result['errors'] ) ? 'notice-success' : 'notice-warning';
		$deleted_line = isset( $result['deleted'] ) ? sprintf( ' &nbsp;&middot;&nbsp; <strong>%d eliminados</strong>', (int) $result['deleted'] ) : '';

		printf(
			'<div class="notice %s is-dismissible"><p><strong>Importacion completada:</strong> %d nuevos &nbsp;&middot;&nbsp; %d actualizados &nbsp;&middot;&nbsp; %d omitidos &nbsp;&middot;&nbsp; %d duplicados%s %s</p></div>',
			esc_attr( $variant ),
			(int) $result['created'],
			(int) $result['updated'],
			(int) $result['skipped'],
			(int) $result['duplicates'],
			$deleted_line,
			$errors_html
		);
	}

	if ( isset( $_GET['gnf_reimport_error'] ) && 'csv_not_found' === sanitize_key( wp_unslash( $_GET['gnf_reimport_error'] ) ) ) {
		echo '<div class="notice notice-error"><p>No se encontro <code>seeders/escuelas-mep.csv</code>.</p></div>';
	}
}

/**
 * Renderiza la tarjeta de reparaciones docentes.
 *
 * @return void
 */
function gnf_render_docente_repairs_tools_card() {
	?>
	<div style="max-width: 960px; background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 2px 12px rgba(0,0,0,0.06);">
		<h2 style="margin-top:0;">Reparar docentes</h2>
		<p style="margin:0 0 16px;color:#4b5563;">
			Agrupa la correccion de roles y la reparacion de la asociacion docente-centro para cuentas que quedaron con metadata incompleta o legacy.
		</p>
		<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;">
			<div style="border:1px solid #e5e7eb;border-radius:10px;padding:18px;">
				<h3 style="margin:0 0 8px;font-size:16px;">Corregir roles</h3>
				<p style="margin:0 0 14px;color:#555;">
					Revisa usuarios <code>subscriber</code> registrados como docente y los convierte al rol correcto cuando la metadata del plugin lo confirma.
				</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'gnf_bulk_fix_subscriber_docentes', 'gnf_nonce' ); ?>
					<input type="hidden" name="action" value="gnf_bulk_fix_subscriber_docentes" />
					<input type="hidden" name="redirect_to" value="<?php echo esc_url( gnf_get_tools_page_url() ); ?>" />
					<?php submit_button( __( 'Corregir roles', 'guardianes-formularios' ), 'secondary', 'submit', false ); ?>
				</form>
			</div>
			<div style="border:1px solid #e5e7eb;border-radius:10px;padding:18px;">
				<h3 style="margin:0 0 8px;font-size:16px;">Reparar docente-centro</h3>
				<p style="margin:0 0 14px;color:#555;">
					Hace match por codigo MEP o nombre de institucion legacy y sincroniza centro, region y correo institucional. Si detecta conflicto con otra cuenta, omite el caso.
				</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'gnf_bulk_fix_docente_centros', 'gnf_nonce' ); ?>
					<input type="hidden" name="action" value="gnf_bulk_fix_docente_centros" />
					<input type="hidden" name="redirect_to" value="<?php echo esc_url( gnf_get_tools_page_url() ); ?>" />
					<?php submit_button( __( 'Reparar docente-centro', 'guardianes-formularios' ), 'primary', 'submit', false ); ?>
				</form>
			</div>
		</div>
	</div>
	<?php
}

/**
 * Renderiza la tarjeta de importacion de centros.
 *
 * @return void
 */
function gnf_render_centros_import_tools_card() {
	gnf_require_centros_importer();

	$job         = gnf_get_centros_import_job();
	$job_running = ! empty( $job ) && is_array( $job ) && 'running' === ( $job['status'] ?? '' );
	$progress    = gnf_get_centros_import_progress( $job );

	$csv_path  = GNF_PATH . 'seeders/escuelas-mep.csv';
	$csv_rows  = gnf_count_centros_csv_rows( $csv_path );
	$csv_label = $csv_rows ? number_format_i18n( $csv_rows ) . ' filas en escuelas-mep.csv' : 'escuelas-mep.csv no encontrado';

	$json_path  = GNF_PATH . 'seeders/centros_educativos_2024.json';
	$json_rows  = file_exists( $json_path ) ? gnf_count_centros_json_entries( $json_path ) : 0;
	$json_label = $json_rows ? ' + ' . number_format_i18n( $json_rows ) . ' en centros_educativos_2024.json' : '';
	?>
	<div style="max-width: 960px; margin-top: 24px; background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 2px 12px rgba(0,0,0,0.06);">
		<h2 style="margin-top:0;">Importar Centros Educativos MEP</h2>
		<p style="margin:0 0 12px;color:#555;">
			Re-importa el catalogo desde <code>escuelas-mep.csv</code> y <code>centros_educativos_2024.json</code>. Los centros existentes se actualizan sin duplicar y el proceso corre por lotes para evitar timeouts.
			(<?php echo esc_html( $csv_label . $json_label ); ?>)
		</p>
		<div style="display:flex;gap:12px;flex-wrap:wrap;">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
				<?php wp_nonce_field( 'gnf_reimport_centros', 'gnf_reimport_nonce' ); ?>
				<input type="hidden" name="action" value="gnf_reimport_centros" />
				<button type="submit" class="button button-primary" <?php disabled( $job_running ); ?> onclick="return confirm('Esto iniciara una importacion en lotes y mostrara el progreso en esta pantalla.');">
					Importar centros nuevos
				</button>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
				<?php wp_nonce_field( 'gnf_purge_reimport_centros', 'gnf_reimport_nonce' ); ?>
				<input type="hidden" name="action" value="gnf_purge_reimport_centros" />
				<button type="submit" class="button button-secondary" style="border-color:#b91c1c;color:#b91c1c;" <?php disabled( $job_running ); ?> onclick="return confirm('Esto eliminara todos los centros sin docente asignado y luego reimportara el catalogo en lotes. Esta accion no se puede deshacer.');">
					Limpiar duplicados y reimportar
				</button>
			</form>
		</div>
		<?php if ( $job_running ) : ?>
			<p style="margin:10px 0 0;color:#555;">Hay una importacion activa. Deja esta pantalla abierta para ver el progreso.</p>
		<?php endif; ?>
		<div
			id="gnf-centros-import-progress"
			class="notice notice-info"
			data-active="<?php echo esc_attr( $job_running ? '1' : '0' ); ?>"
			data-autostart="<?php echo esc_attr( $job_running ? '1' : '0' ); ?>"
			style="<?php echo $job_running ? 'margin:16px 0 0;padding:16px 20px;' : 'display:none;margin:16px 0 0;padding:16px 20px;'; ?>">
			<strong style="font-size:14px;">Progreso de importacion</strong>
			<p class="gnf-centros-import-status" style="margin:8px 0 12px;color:#374151;">
				<?php echo esc_html( $progress['status_label'] ); ?>
			</p>
			<div style="height:12px;background:#e5e7eb;border-radius:999px;overflow:hidden;">
				<div class="gnf-centros-import-bar" style="height:12px;width:<?php echo esc_attr( (string) $progress['percent'] ); ?>%;background:linear-gradient(90deg,#2563eb,#0891b2);"></div>
			</div>
			<p class="gnf-centros-import-meta" style="margin:10px 0 14px;color:#555;">
				<?php echo esc_html( number_format_i18n( (int) $progress['done_units'] ) . ' de ' . number_format_i18n( (int) $progress['total_units'] ) . ' filas procesadas (' . $progress['percent'] . '%)' ); ?>
			</p>
			<div style="display:flex;gap:18px;flex-wrap:wrap;">
				<span><strong class="gnf-stat-created"><?php echo esc_html( (string) ( $job['stats']['created'] ?? 0 ) ); ?></strong> nuevos</span>
				<span><strong class="gnf-stat-updated"><?php echo esc_html( (string) ( $job['stats']['updated'] ?? 0 ) ); ?></strong> actualizados</span>
				<span><strong class="gnf-stat-skipped"><?php echo esc_html( (string) ( $job['stats']['skipped'] ?? 0 ) ); ?></strong> omitidos</span>
				<span><strong class="gnf-stat-errors"><?php echo esc_html( (string) count( $job['stats']['errors'] ?? array() ) ); ?></strong> errores</span>
				<span class="gnf-stat-deleted-wrap" style="<?php echo isset( $job['stats']['deleted'] ) ? '' : 'display:none;'; ?>">
					<strong class="gnf-stat-deleted"><?php echo esc_html( (string) ( $job['stats']['deleted'] ?? 0 ) ); ?></strong> eliminados
				</span>
			</div>
			<p class="gnf-centros-import-message" style="margin:12px 0 0;color:#1f2937;"></p>
			<p class="gnf-centros-import-error" style="display:none;margin:12px 0 0;color:#b91c1c;font-weight:600;"></p>
		</div>
	</div>
	<?php
}

/**
 * Renderiza herramientas administrativas puntuales.
 *
 * @return void
 */
function gnf_render_admin_tools() {
	$cap = gnf_menu_capability();
	if ( ! current_user_can( $cap ) ) {
		wp_die( esc_html__( 'No autorizado.', 'guardianes-formularios' ) );
	}
	?>
	<div class="wrap">
		<h1>Reparar Formularios</h1>
		<p style="max-width:960px;color:#4b5563;">
			Concentra reparaciones, imports, reset y reseeds operativos del plugin en una sola pantalla administrativa.
		</p>
		<?php gnf_render_tools_result_notices(); ?>
		<?php gnf_render_docente_repairs_tools_card(); ?>
		<?php gnf_render_centros_import_tools_card(); ?>
		<div style="margin-top:24px;">
			<?php if ( function_exists( 'gnf_render_reset_tools_card' ) ) { gnf_render_reset_tools_card(); } ?>
		</div>
	</div>
	<?php
	return;

	$run     = absint( $_GET['gnf_fixed_run'] ?? 0 );
	$updated = absint( $_GET['gnf_fixed_updated'] ?? 0 );
	$skipped = absint( $_GET['gnf_fixed_skipped'] ?? 0 );
	$failed  = absint( $_GET['gnf_fixed_failed'] ?? 0 );
	$scanned = absint( $_GET['gnf_fixed_scanned'] ?? 0 );
	$centro_fix_run       = absint( $_GET['gnf_center_fix_run'] ?? 0 );
	$centro_fix_scanned   = absint( $_GET['gnf_center_fix_scanned'] ?? 0 );
	$centro_fix_updated   = absint( $_GET['gnf_center_fix_updated'] ?? 0 );
	$centro_fix_skipped   = absint( $_GET['gnf_center_fix_skipped'] ?? 0 );
	$centro_fix_failed    = absint( $_GET['gnf_center_fix_failed'] ?? 0 );
	$centro_fix_claimed   = absint( $_GET['gnf_center_fix_claimed'] ?? 0 );
	$centro_fix_unmatched = absint( $_GET['gnf_center_fix_unmatched'] ?? 0 );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Herramientas de recuperación', 'guardianes-formularios' ); ?></h1>

		<?php if ( $run ) : ?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					echo esc_html(
						sprintf(
							'Corrección ejecutada. Revisados: %d. Actualizados a docente: %d. Omitidos: %d. Fallidos: %d.',
							$scanned,
							$updated,
							$skipped,
							$failed
						)
					);
					?>
				</p>
			</div>
		<?php endif; ?>

		<?php if ( $centro_fix_run ) : ?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					echo esc_html(
						sprintf(
							'Asociacion masiva ejecutada. Revisados: %d. Actualizados: %d. Omitidos: %d. No encontrados: %d. En conflicto: %d. Fallidos: %d.',
							$centro_fix_scanned,
							$centro_fix_updated,
							$centro_fix_skipped,
							$centro_fix_unmatched,
							$centro_fix_claimed,
							$centro_fix_failed
						)
					);
					?>
				</p>
			</div>
		<?php endif; ?>

		<div style="max-width: 760px; background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 2px 12px rgba(0,0,0,0.06);">
			<h2 style="margin-top:0;"><?php esc_html_e( 'Corregir docentes creados como subscriber', 'guardianes-formularios' ); ?></h2>
			<p>
				<?php esc_html_e( 'Esta acción revisa usuarios con rol subscriber registrados desde el 1 de marzo de 2026 y convierte a docente solo los que tienen metadata de registro docente del plugin. No toca cuentas que ya sean docente, supervisor, comité o administrador.', 'guardianes-formularios' ); ?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'gnf_bulk_fix_subscriber_docentes', 'gnf_nonce' ); ?>
				<input type="hidden" name="action" value="gnf_bulk_fix_subscriber_docentes" />
				<input type="hidden" name="redirect_to" value="<?php echo esc_url( admin_url( 'admin.php?page=gnf-tools' ) ); ?>" />
				<?php submit_button( __( 'Ejecutar corrección masiva', 'guardianes-formularios' ), 'primary', 'submit', false ); ?>
			</form>
		</div>

		<div style="max-width: 760px; margin-top: 24px; background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 2px 12px rgba(0,0,0,0.06);">
			<h2 style="margin-top:0;"><?php esc_html_e( 'Reparar asociacion docente-centro desde institucion legacy', 'guardianes-formularios' ); ?></h2>
			<p>
				<?php esc_html_e( 'Busca docentes sin centro asociado o con rol inconsistente, intenta hacer match por codigo MEP o nombre de institucion guardado en texto, y sincroniza rol, centro, direccion regional y correo institucional. Si el centro ya pertenece a otra cuenta docente, lo omite para evitar conflictos.', 'guardianes-formularios' ); ?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'gnf_bulk_fix_docente_centros', 'gnf_nonce' ); ?>
				<input type="hidden" name="action" value="gnf_bulk_fix_docente_centros" />
				<input type="hidden" name="redirect_to" value="<?php echo esc_url( admin_url( 'admin.php?page=gnf-tools' ) ); ?>" />
				<?php submit_button( __( 'Ejecutar reparacion docente-centro', 'guardianes-formularios' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
	</div>
	<?php
}

/**
 * Ejecuta la corrección masiva de subscribers dañados.
 *
 * @return void
 */
function gnf_handle_bulk_fix_subscriber_docentes() {
	$cap = gnf_menu_capability();
	if ( ! current_user_can( $cap ) ) {
		wp_die( 'No autorizado' );
	}

	check_admin_referer( 'gnf_bulk_fix_subscriber_docentes', 'gnf_nonce' );

	$result = function_exists( 'gnf_run_bulk_fix_subscriber_docentes' )
		? gnf_run_bulk_fix_subscriber_docentes()
		: array(
			'scanned' => 0,
			'updated' => 0,
			'skipped' => 0,
			'failed'  => 0,
		);

	$query_args = array(
		'page'              => 'gnf-tools',
		'gnf_fixed_run'     => 1,
		'gnf_fixed_scanned' => (int) $result['scanned'],
		'gnf_fixed_updated' => (int) $result['updated'],
		'gnf_fixed_skipped' => (int) $result['skipped'],
		'gnf_fixed_failed'  => (int) $result['failed'],
	);

	wp_safe_redirect(
		add_query_arg(
			$query_args,
			admin_url( 'admin.php' )
		)
	);
	exit;
}
add_action( 'admin_post_gnf_bulk_fix_subscriber_docentes', 'gnf_handle_bulk_fix_subscriber_docentes' );

/**
 * Ejecuta la reparacion masiva de relaciones docente -> centro.
 *
 * @return void
 */
function gnf_handle_bulk_fix_docente_centros() {
	$cap = gnf_menu_capability();
	if ( ! current_user_can( $cap ) ) {
		wp_die( 'No autorizado' );
	}

	check_admin_referer( 'gnf_bulk_fix_docente_centros', 'gnf_nonce' );

	$result = function_exists( 'gnf_run_bulk_fix_docente_centros' )
		? gnf_run_bulk_fix_docente_centros()
		: array(
			'scanned'   => 0,
			'updated'   => 0,
			'skipped'   => 0,
			'failed'    => 0,
			'claimed'   => 0,
			'unmatched' => 0,
		);

	wp_safe_redirect(
		add_query_arg(
			array(
				'page'                     => 'gnf-tools',
				'gnf_center_fix_run'       => 1,
				'gnf_center_fix_scanned'   => (int) ( $result['scanned'] ?? 0 ),
				'gnf_center_fix_updated'   => (int) ( $result['updated'] ?? 0 ),
				'gnf_center_fix_skipped'   => (int) ( $result['skipped'] ?? 0 ),
				'gnf_center_fix_failed'    => (int) ( $result['failed'] ?? 0 ),
				'gnf_center_fix_claimed'   => (int) ( $result['claimed'] ?? 0 ),
				'gnf_center_fix_unmatched' => (int) ( $result['unmatched'] ?? 0 ),
			),
			admin_url( 'admin.php' )
		)
	);
	exit;
}
add_action( 'admin_post_gnf_bulk_fix_docente_centros', 'gnf_handle_bulk_fix_docente_centros' );

/**
 * Muestra en Ajustes > General el aviso y botón para corregir roles subscriber/docente.
 *
 * @return void
 */
function gnf_render_bulk_fix_docentes_notice() {
	return;

	$run     = absint( $_GET['gnf_fixed_run'] ?? 0 );
	$updated = absint( $_GET['gnf_fixed_updated'] ?? 0 );
	$skipped = absint( $_GET['gnf_fixed_skipped'] ?? 0 );
	$failed  = absint( $_GET['gnf_fixed_failed'] ?? 0 );
	$scanned = absint( $_GET['gnf_fixed_scanned'] ?? 0 );
	?>
	<?php if ( $run ) : ?>
		<div class="notice notice-success is-dismissible">
			<p>
				<?php
				echo esc_html(
					sprintf(
						'Corrección ejecutada. Revisados: %d. Actualizados a docente: %d. Omitidos: %d. Fallidos: %d.',
						$scanned,
						$updated,
						$skipped,
						$failed
					)
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<div class="notice notice-warning" style="padding:16px 20px;">
		<strong style="font-size:14px;">Corregir docentes creados como subscriber</strong>
		<p style="margin:6px 0 12px;color:#555;">
			Revisa usuarios <code>subscriber</code> registrados desde el 1 de marzo de 2026 y convierte a <code>docente</code> solo los que tienen metadata de registro docente del plugin.
			No toca cuentas que ya sean docente, supervisor, comité o administrador.
		</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
			<?php wp_nonce_field( 'gnf_bulk_fix_subscriber_docentes', 'gnf_nonce' ); ?>
			<input type="hidden" name="action" value="gnf_bulk_fix_subscriber_docentes" />
			<input type="hidden" name="redirect_to" value="<?php echo esc_url( admin_url( 'options-general.php' ) ); ?>" />
			<?php submit_button( __( 'Corregir roles de usuarios', 'guardianes-formularios' ), 'secondary', 'submit', false ); ?>
		</form>
	</div>
	<?php
}
add_action( 'admin_notices', 'gnf_render_bulk_fix_docentes_notice' );

/**
 * Returns the transient key used to store the final centro import result.
 *
 * @param int|null $user_id User ID.
 * @return string
 */
function gnf_get_reimport_result_key( $user_id = null ) {
	$user_id = $user_id ? (int) $user_id : get_current_user_id();
	return 'gnf_reimport_result_' . $user_id;
}

/**
 * Returns the transient key used to store the active centro import job.
 *
 * @param int|null $user_id User ID.
 * @return string
 */
function gnf_get_centros_import_job_key( $user_id = null ) {
	$user_id = $user_id ? (int) $user_id : get_current_user_id();
	return 'gnf_centros_import_job_' . $user_id;
}

/**
 * Ensures the batch importer helpers are loaded.
 *
 * @return void
 */
function gnf_require_centros_importer() {
	if ( ! function_exists( 'gnf_import_centros_from_csv_batch' ) ) {
		require_once GNF_PATH . 'seeders/seed-centros-mep.php';
	}
}

/**
 * Batch size used by the admin importer.
 *
 * @return int
 */
function gnf_get_centros_import_batch_size() {
	return 250;
}

/**
 * Returns the current import job for the active user.
 *
 * @param int|null $user_id User ID.
 * @return array<string,mixed>|null
 */
function gnf_get_centros_import_job( $user_id = null ) {
	$job = get_transient( gnf_get_centros_import_job_key( $user_id ) );
	return is_array( $job ) ? $job : null;
}

/**
 * Computes a progress snapshot for the current job.
 *
 * @param array<string,mixed>|null $job Import job.
 * @return array<string,mixed>
 */
function gnf_get_centros_import_progress( $job ) {
	$progress = array(
		'percent'      => 0,
		'done_units'   => 0,
		'total_units'  => 0,
		'status_label' => 'Listo para iniciar.',
	);

	if ( empty( $job ) || ! is_array( $job ) ) {
		return $progress;
	}

	$total_units = 0;
	$done_units  = 0;

	if ( ! empty( $job['purge']['total'] ) ) {
		$total_units += (int) $job['purge']['total'];
		$done_units  += min( (int) $job['purge']['offset'], (int) $job['purge']['total'] );
	}

	if ( ! empty( $job['sources'] ) && is_array( $job['sources'] ) ) {
		foreach ( $job['sources'] as $source ) {
			$total_units += (int) ( $source['total'] ?? 0 );
			$done_units  += min( (int) ( $source['offset'] ?? 0 ), (int) ( $source['total'] ?? 0 ) );
		}
	}

	$progress['done_units']  = $done_units;
	$progress['total_units'] = $total_units;
	$progress['percent']     = $total_units > 0 ? round( min( 100, ( $done_units / $total_units ) * 100 ), 1 ) : 0;

	if ( ! empty( $job['purge']['total'] ) && (int) $job['purge']['offset'] < (int) $job['purge']['total'] ) {
		$progress['status_label'] = 'Eliminando centros sin docente asignado.';
	} elseif ( isset( $job['current_source'] ) && isset( $job['sources'][ $job['current_source'] ] ) ) {
		$source = $job['sources'][ $job['current_source'] ];
		$progress['status_label'] = 'Importando ' . ( $source['label'] ?? 'centros' ) . '.';
	} elseif ( ! empty( $job['status'] ) && 'completed' === $job['status'] ) {
		$progress['status_label'] = 'Importacion completada.';
	} elseif ( ! empty( $job['status'] ) && 'running' === $job['status'] ) {
		$progress['status_label'] = 'Preparando siguiente lote.';
	}

	return $progress;
}

/**
 * Merge two import stats arrays (CSV + JSON).
 *
 * @param array<string,mixed> $a Left stats array.
 * @param array<string,mixed> $b Right stats array.
 * @return array<string,mixed>
 */
function gnf_merge_import_stats( $a, $b ) {
	$a['total']      = ( $a['total'] ?? 0 ) + ( $b['total'] ?? 0 );
	$a['created']    = ( $a['created'] ?? 0 ) + ( $b['created'] ?? 0 );
	$a['updated']    = ( $a['updated'] ?? 0 ) + ( $b['updated'] ?? 0 );
	$a['skipped']    = ( $a['skipped'] ?? 0 ) + ( $b['skipped'] ?? 0 );
	$a['duplicates'] = ( $a['duplicates'] ?? 0 ) + ( $b['duplicates'] ?? 0 );
	$a['errors']     = array_merge( $a['errors'] ?? array(), $b['errors'] ?? array() );
	return $a;
}

/**
 * Builds and stores a new import job.
 *
 * @param string $mode Import mode.
 * @return array<string,mixed>|WP_Error
 */
function gnf_create_centros_import_job( $mode = 'reimport' ) {
	gnf_require_centros_importer();

	$csv_file = GNF_PATH . 'seeders/escuelas-mep.csv';
	if ( ! file_exists( $csv_file ) ) {
		return new WP_Error( 'csv_not_found', 'No se encontro escuelas-mep.csv.' );
	}

	$job = array(
		'mode'           => $mode,
		'status'         => 'running',
		'batch_size'     => gnf_get_centros_import_batch_size(),
		'current_source' => 0,
		'created_at'     => time(),
		'updated_at'     => time(),
		'stats'          => gnf_get_centros_import_stats_template(),
		'sources'        => array(
			array(
				'type'   => 'csv',
				'label'  => 'escuelas-mep.csv',
				'path'   => $csv_file,
				'offset' => 0,
				'total'  => gnf_count_centros_csv_rows( $csv_file ),
			),
		),
	);

	$json_file = GNF_PATH . 'seeders/centros_educativos_2024.json';
	if ( file_exists( $json_file ) ) {
		$job['sources'][] = array(
			'type'   => 'json',
			'label'  => 'centros_educativos_2024.json',
			'path'   => $json_file,
			'offset' => 0,
			'total'  => gnf_count_centros_json_entries( $json_file ),
		);
	}

	if ( 'purge_reimport' === $mode ) {
		global $wpdb;

		$claimed_ids = $wpdb->get_col(
			"SELECT DISTINCT CAST(meta_value AS UNSIGNED) FROM {$wpdb->usermeta}
			 WHERE meta_key IN ('centro_educativo_id','centro_solicitado','gnf_centro_id')
			   AND meta_value != '0' AND meta_value != '' AND meta_value IS NOT NULL"
		);
		$claimed_ids   = array_filter( array_map( 'intval', (array) $claimed_ids ) );
		$all_ids       = $wpdb->get_col(
			"SELECT ID FROM {$wpdb->posts}
			 WHERE post_type = 'centro_educativo'
			   AND post_status NOT IN ('auto-draft')"
		);
		$all_ids       = array_map( 'intval', (array) $all_ids );
		$ids_to_delete = array_values( array_diff( $all_ids, $claimed_ids ) );

		$job['purge'] = array(
			'ids'    => $ids_to_delete,
			'offset' => 0,
			'total'  => count( $ids_to_delete ),
		);
		$job['stats']['deleted'] = 0;
	}

	set_transient( gnf_get_centros_import_job_key(), $job, HOUR_IN_SECONDS );

	return $job;
}

/**
 * Deletes a chunk of centro posts directly for purge mode.
 *
 * @param int[] $ids IDs to delete.
 * @return int
 */
function gnf_delete_centros_import_chunk( $ids ) {
	global $wpdb;

	$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
	if ( empty( $ids ) ) {
		return 0;
	}

	$ids_sql = implode( ',', $ids );

	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
	$wpdb->query( "DELETE FROM {$wpdb->posts} WHERE ID IN ({$ids_sql})" );
	$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE post_id IN ({$ids_sql})" );
	$wpdb->query( "DELETE FROM {$wpdb->term_relationships} WHERE object_id IN ({$ids_sql})" );
	// phpcs:enable

	return count( $ids );
}

/**
 * Processes a single AJAX batch for the active import job.
 *
 * @return void
 */
function gnf_ajax_process_centros_import_batch() {
	check_ajax_referer( 'gnf_centros_import_batch', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Sin permisos.' ), 403 );
	}

	$job = gnf_get_centros_import_job();
	if ( empty( $job ) ) {
		wp_send_json_error( array( 'message' => 'No hay una importacion activa para este usuario.' ), 404 );
	}

	gnf_require_centros_importer();
	@set_time_limit( 60 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

	$batch_message = 'Procesando lote...';
	$fatal_batch   = false;

	wp_suspend_cache_invalidation( true );
	wp_defer_term_counting( true );
	wp_defer_comment_counting( true );

	try {
		if ( ! empty( $job['purge']['total'] ) && (int) $job['purge']['offset'] < (int) $job['purge']['total'] ) {
			$start       = (int) $job['purge']['offset'];
			$chunk       = array_slice( $job['purge']['ids'], $start, (int) $job['batch_size'] );
			$deleted_now = gnf_delete_centros_import_chunk( $chunk );
			$job['purge']['offset'] += $deleted_now;
			$job['stats']['deleted'] = ( $job['stats']['deleted'] ?? 0 ) + $deleted_now;
			$batch_message = sprintf( 'Eliminados %d centros sin docente en este lote.', $deleted_now );

			if ( (int) $job['purge']['offset'] >= (int) $job['purge']['total'] ) {
				unset( $job['purge']['ids'] );
			}
		} else {
			while ( isset( $job['sources'][ $job['current_source'] ] ) ) {
				$current_source = $job['sources'][ $job['current_source'] ];
				if ( (int) $current_source['offset'] < (int) $current_source['total'] ) {
					break;
				}
				$job['current_source']++;
			}

			if ( isset( $job['sources'][ $job['current_source'] ] ) ) {
				$current_source = $job['sources'][ $job['current_source'] ];
				$offset         = (int) $current_source['offset'];
				$limit          = (int) $job['batch_size'];

				if ( 'json' === $current_source['type'] ) {
					$batch = gnf_import_centros_from_json_batch( $current_source['path'], $offset, $limit, false );
				} else {
					$batch = gnf_import_centros_from_csv_batch( $current_source['path'], $offset, $limit, false );
				}

				$job['stats'] = gnf_merge_import_stats( $job['stats'], $batch['stats'] );
				$job['sources'][ $job['current_source'] ]['offset'] = (int) $batch['next_offset'];
				$batch_message = sprintf(
					'%s: %d/%d filas procesadas.',
					$current_source['label'],
					(int) $batch['next_offset'],
					(int) $current_source['total']
				);

				if ( ! empty( $batch['stats']['errors'] ) && 0 === (int) $batch['processed'] ) {
					$fatal_batch = true;
				}

				if ( empty( $batch['has_more'] ) ) {
					$job['current_source']++;
				}
			}
		}
	} finally {
		wp_defer_term_counting( false );
		wp_defer_comment_counting( false );
		wp_suspend_cache_invalidation( false );
	}

	$job['updated_at'] = time();
	$is_done           = $fatal_batch;

	if ( ! $is_done ) {
		$is_done = true;

		if ( ! empty( $job['purge']['total'] ) && (int) $job['purge']['offset'] < (int) $job['purge']['total'] ) {
			$is_done = false;
		}

		if ( $is_done && ! empty( $job['sources'] ) && is_array( $job['sources'] ) ) {
			foreach ( $job['sources'] as $source ) {
				if ( (int) ( $source['offset'] ?? 0 ) < (int) ( $source['total'] ?? 0 ) ) {
					$is_done = false;
					break;
				}
			}
		}
	}

	if ( $is_done ) {
		$job['status'] = 'completed';
		set_transient( gnf_get_reimport_result_key(), $job['stats'], 120 );
		delete_transient( gnf_get_centros_import_job_key() );

		if ( ! empty( $job['stats']['deleted'] ) ) {
			wp_cache_flush();
		}

		$progress            = gnf_get_centros_import_progress( $job );
		$progress['percent'] = 100;

		wp_send_json_success(
			array(
				'done'        => true,
				'message'     => $fatal_batch ? 'La importacion termino con errores.' : 'Importacion completada.',
				'progress'    => $progress,
				'stats'       => $job['stats'],
				'redirectUrl' => gnf_get_tools_page_url(),
			)
		);
	}

	set_transient( gnf_get_centros_import_job_key(), $job, HOUR_IN_SECONDS );
	$progress = gnf_get_centros_import_progress( $job );

	wp_send_json_success(
		array(
			'done'     => false,
			'message'  => $batch_message,
			'progress' => $progress,
			'stats'    => $job['stats'],
		)
	);
}
add_action( 'wp_ajax_gnf_process_centros_import_batch', 'gnf_ajax_process_centros_import_batch' );

/**
 * Renders the settings-page notice used to launch and monitor the import.
 *
 * @return void
 */
function gnf_render_centros_import_notice() {
	return;

	gnf_require_centros_importer();

	$result = get_transient( gnf_get_reimport_result_key() );
	if ( $result ) {
		delete_transient( gnf_get_reimport_result_key() );
		$errors_html = '';
		if ( ! empty( $result['errors'] ) ) {
			$errors_html = '<ul style="margin:8px 0 0;padding-left:20px;">';
			foreach ( array_slice( $result['errors'], 0, 5 ) as $err ) {
				$errors_html .= '<li>' . esc_html( $err ) . '</li>';
			}
			if ( count( $result['errors'] ) > 5 ) {
				$errors_html .= '<li>... y ' . ( count( $result['errors'] ) - 5 ) . ' errores mas.</li>';
			}
			$errors_html .= '</ul>';
		}

		$variant      = empty( $result['errors'] ) ? 'notice-success' : 'notice-warning';
		$deleted_line = isset( $result['deleted'] ) ? sprintf( ' &nbsp;&middot;&nbsp; <strong>%d eliminados</strong>', (int) $result['deleted'] ) : '';

		printf(
			'<div class="notice %s is-dismissible"><p><strong>Importacion completada:</strong> %d nuevos &nbsp;&middot;&nbsp; %d actualizados &nbsp;&middot;&nbsp; %d omitidos &nbsp;&middot;&nbsp; %d duplicados%s %s</p></div>',
			esc_attr( $variant ),
			(int) $result['created'],
			(int) $result['updated'],
			(int) $result['skipped'],
			(int) $result['duplicates'],
			$deleted_line,
			$errors_html
		);
	}

	if ( isset( $_GET['gnf_reimport_error'] ) && 'csv_not_found' === sanitize_key( wp_unslash( $_GET['gnf_reimport_error'] ) ) ) {
		echo '<div class="notice notice-error"><p>No se encontro <code>seeders/escuelas-mep.csv</code>.</p></div>';
	}

	$job         = gnf_get_centros_import_job();
	$job_running = ! empty( $job ) && is_array( $job ) && 'running' === ( $job['status'] ?? '' );
	$progress    = gnf_get_centros_import_progress( $job );

	$csv_path  = GNF_PATH . 'seeders/escuelas-mep.csv';
	$csv_rows  = gnf_count_centros_csv_rows( $csv_path );
	$csv_label = $csv_rows ? number_format_i18n( $csv_rows ) . ' filas en escuelas-mep.csv' : 'escuelas-mep.csv no encontrado';

	$json_path  = GNF_PATH . 'seeders/centros_educativos_2024.json';
	$json_rows  = file_exists( $json_path ) ? gnf_count_centros_json_entries( $json_path ) : 0;
	$json_label = $json_rows ? ' + ' . number_format_i18n( $json_rows ) . ' en centros_educativos_2024.json' : '';
	?>
	<div class="notice notice-info" style="padding:16px 20px;">
		<strong style="font-size:14px;">Importar Centros Educativos MEP</strong>
		<p style="margin:6px 0 12px;color:#555;">
			Re-importa todos los centros del archivo <code>escuelas-mep.csv</code> y <code>centros_educativos_2024.json</code> que no esten aun en el sistema.
			Los ya existentes se actualizan sin duplicar. Ahora corre en lotes de <?php echo esc_html( (string) gnf_get_centros_import_batch_size() ); ?> filas para evitar timeouts.
			(<?php echo esc_html( $csv_label . $json_label ); ?>)
		</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;margin-right:12px;">
			<?php wp_nonce_field( 'gnf_reimport_centros', 'gnf_reimport_nonce' ); ?>
			<input type="hidden" name="action" value="gnf_reimport_centros" />
			<button type="submit" class="button button-primary" <?php disabled( $job_running ); ?> onclick="return confirm('Esto iniciara una importacion en lotes y mostrara el progreso en esta pantalla.');">
				Importar centros nuevos
			</button>
		</form>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
			<?php wp_nonce_field( 'gnf_purge_reimport_centros', 'gnf_reimport_nonce' ); ?>
			<input type="hidden" name="action" value="gnf_purge_reimport_centros" />
			<button type="submit" class="button button-secondary" style="border-color:#b91c1c;color:#b91c1c;" <?php disabled( $job_running ); ?> onclick="return confirm('Esto eliminara todos los centros sin docente asignado y luego reimportara el catalogo en lotes. Esta accion no se puede deshacer.');">
				Limpiar duplicados y reimportar
			</button>
		</form>
		<?php if ( $job_running ) : ?>
			<p style="margin:10px 0 0;color:#555;">Hay una importacion activa. Deja esta pantalla abierta para ver el progreso.</p>
		<?php endif; ?>
	</div>

	<div
		id="gnf-centros-import-progress"
		class="notice notice-info"
		data-active="<?php echo esc_attr( $job_running ? '1' : '0' ); ?>"
		data-autostart="<?php echo esc_attr( $job_running ? '1' : '0' ); ?>"
		style="<?php echo $job_running ? 'padding:16px 20px;' : 'display:none;padding:16px 20px;'; ?>">
		<strong style="font-size:14px;">Progreso de importacion</strong>
		<p class="gnf-centros-import-status" style="margin:8px 0 12px;color:#374151;">
			<?php echo esc_html( $progress['status_label'] ); ?>
		</p>
		<div style="height:12px;background:#e5e7eb;border-radius:999px;overflow:hidden;">
			<div class="gnf-centros-import-bar" style="height:12px;width:<?php echo esc_attr( (string) $progress['percent'] ); ?>%;background:linear-gradient(90deg,#2563eb,#0891b2);"></div>
		</div>
		<p class="gnf-centros-import-meta" style="margin:10px 0 14px;color:#555;">
			<?php echo esc_html( number_format_i18n( (int) $progress['done_units'] ) . ' de ' . number_format_i18n( (int) $progress['total_units'] ) . ' filas procesadas (' . $progress['percent'] . '%)' ); ?>
		</p>
		<div style="display:flex;gap:18px;flex-wrap:wrap;">
			<span><strong class="gnf-stat-created"><?php echo esc_html( (string) ( $job['stats']['created'] ?? 0 ) ); ?></strong> nuevos</span>
			<span><strong class="gnf-stat-updated"><?php echo esc_html( (string) ( $job['stats']['updated'] ?? 0 ) ); ?></strong> actualizados</span>
			<span><strong class="gnf-stat-skipped"><?php echo esc_html( (string) ( $job['stats']['skipped'] ?? 0 ) ); ?></strong> omitidos</span>
			<span><strong class="gnf-stat-errors"><?php echo esc_html( (string) count( $job['stats']['errors'] ?? array() ) ); ?></strong> errores</span>
			<span class="gnf-stat-deleted-wrap" style="<?php echo isset( $job['stats']['deleted'] ) ? '' : 'display:none;'; ?>">
				<strong class="gnf-stat-deleted"><?php echo esc_html( (string) ( $job['stats']['deleted'] ?? 0 ) ); ?></strong> eliminados
			</span>
		</div>
		<p class="gnf-centros-import-message" style="margin:12px 0 0;color:#1f2937;"></p>
		<p class="gnf-centros-import-error" style="display:none;margin:12px 0 0;color:#b91c1c;font-weight:600;"></p>
	</div>
	<?php
}
add_action( 'admin_notices', 'gnf_render_centros_import_notice' );

/**
 * Starts the standard import job and redirects back to settings.
 *
 * @return void
 */
function gnf_handle_reimport_centros() {
	check_admin_referer( 'gnf_reimport_centros', 'gnf_reimport_nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Sin permisos.' );
	}

	$job = gnf_create_centros_import_job( 'reimport' );
	if ( is_wp_error( $job ) ) {
		wp_safe_redirect( gnf_get_tools_page_url( array( 'gnf_reimport_error' => 'csv_not_found' ) ) );
		exit;
	}

	wp_safe_redirect( gnf_get_tools_page_url( array( 'gnf_centros_import' => 'reimport' ) ) );
	exit;
}
add_action( 'admin_post_gnf_reimport_centros', 'gnf_handle_reimport_centros' );

/**
 * Starts the purge + reimport job and redirects back to settings.
 *
 * @return void
 */
function gnf_handle_purge_and_reimport_centros() {
	check_admin_referer( 'gnf_purge_reimport_centros', 'gnf_reimport_nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Sin permisos.' );
	}

	$job = gnf_create_centros_import_job( 'purge_reimport' );
	if ( is_wp_error( $job ) ) {
		wp_safe_redirect( gnf_get_tools_page_url( array( 'gnf_reimport_error' => 'csv_not_found' ) ) );
		exit;
	}

	wp_safe_redirect( gnf_get_tools_page_url( array( 'gnf_centros_import' => 'purge_reimport' ) ) );
	exit;
}
add_action( 'admin_post_gnf_purge_reimport_centros', 'gnf_handle_purge_and_reimport_centros' );

/**
 * Resalta el menu correcto cuando estamos en la taxonomia de regiones.
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
						$centro = (int) gnf_get_centro_for_docente( $doc->ID );
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

