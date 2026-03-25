<?php

/**
 * Lógica del panel de docente.
 */

if (! defined('ABSPATH')) {
	exit;
}

function gnf_render_docente_panel()
{
	if (! is_user_logged_in()) {
		return gnf_render_auth_block(
			array(
				'title'         => 'Registro de Participación del Centro Educativo',
				'description'   => 'Accede para ver tus retos y subir evidencias.',
				'show_register' => true,
				'redirect'      => esc_url_raw(home_url(add_query_arg(array()))),
			)
		);
	}

	$user = wp_get_current_user();
	if (! gnf_user_has_role($user, 'docente') && ! current_user_can('view_guardianes_docente') && ! current_user_can('manage_options') && ! current_user_can('manage_network_options')) {
		return '<div class="gnf-auth"><div class="gnf-auth__card"><p class="gnf-muted">No tienes permisos para ver este panel.</p></div></div>';
	}

	$anio_activo = gnf_get_active_year();
	$anio        = gnf_normalize_year(isset($_GET['gnf_year']) ? absint($_GET['gnf_year']) : null);
	$centro_id   = gnf_get_centro_for_docente($user->ID);
	if (! $centro_id) {
		$centro_id = (int) get_user_meta($user->ID, 'centro_solicitado', true);
	}

	// Obtener años disponibles para este docente (entries + matrículas).
	global $wpdb;
	$table_entries    = $wpdb->prefix . 'gn_reto_entries';
	$table_matriculas = $wpdb->prefix . 'gn_matriculas';
	$years_available  = array();
	if ($centro_id) {
		$years_from_entries = $wpdb->get_col($wpdb->prepare(
			"SELECT DISTINCT anio FROM {$table_entries} WHERE centro_id = %d",
			$centro_id
		));
		$years_from_matriculas = $wpdb->get_col($wpdb->prepare(
			"SELECT DISTINCT anio FROM {$table_matriculas} WHERE centro_id = %d",
			$centro_id
		));
		$years_available = array_unique(array_merge($years_from_entries, $years_from_matriculas));
		rsort($years_available);
	}
	if (empty($years_available)) {
		$years_available = array($anio_activo);
	}
	if (! in_array($anio_activo, $years_available)) {
		array_unshift($years_available, $anio_activo);
	}

	$entries    = $centro_id ? gnf_get_user_reto_entries($user->ID, $anio) : array();
	$entries_by = array();
	foreach ($entries as $entry) {
		$entries_by[$entry->reto_id] = $entry;
	}

	// Obtener retos seleccionados en la matrícula del centro.
	$retos_seleccionados = $centro_id ? gnf_get_centro_retos_seleccionados($centro_id, $anio) : array();
	$meta_estrellas      = $centro_id ? gnf_get_centro_meta_estrellas($centro_id, $anio) : 0;

	// Query de retos matriculados.
	$retos_matriculados_args = array(
		'post_type'      => 'reto',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'orderby'        => 'post__in',
		'order'          => 'ASC',
	);

	// Si hay retos seleccionados, filtrar por ellos.
	if (! empty($retos_seleccionados) && is_array($retos_seleccionados)) {
		$retos_matriculados_args['post__in'] = $retos_seleccionados;
	} else {
		// Sin matrícula: no mostrar retos matriculados.
		$retos_matriculados_args['post__in'] = array(0);
	}

	$retos = new WP_Query($retos_matriculados_args);

	// Query de TODOS los retos disponibles (para mostrar cuáles no están matriculados).
	$todos_retos = new WP_Query(array(
		'post_type'      => 'reto',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'orderby'        => 'menu_order',
		'order'          => 'ASC',
	));

	// Separar retos disponibles (no matriculados) que tengan formulario para el año activo.
	$retos_disponibles = array();
	$retos_seleccionados_ids = is_array($retos_seleccionados) ? array_map('intval', $retos_seleccionados) : array();
	if ($todos_retos->have_posts()) {
		while ($todos_retos->have_posts()) {
			$todos_retos->the_post();
			$reto_id = get_the_ID();
			if (! in_array($reto_id, $retos_seleccionados_ids, true)
				&& function_exists('gnf_reto_has_form_for_year')
				&& gnf_reto_has_form_for_year($reto_id, $anio)) {
				$retos_disponibles[] = $reto_id;
			}
		}
		wp_reset_postdata();
	}
	$retos_disponibles = gnf_sort_reto_ids_required_first( $retos_disponibles );

	// Verificar si tiene matrícula activa.
	$tiene_matricula = ! empty($retos_seleccionados);

	// Calcular puntos potenciales.
	$puntos_potenciales = gnf_get_puntos_potenciales($centro_id, $anio);

	// Verificar si todos los retos están completos.
	$all_retos_complete = gnf_are_all_retos_complete($centro_id, $anio);

	// Determinar tab activa.
	$current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : '';
	if ( '' === $current_tab ) {
		// Onboarding: si no tiene matrícula, abrir directamente la pestaña de matrícula.
		$current_tab = $tiene_matricula ? 'resumen' : 'matricula';
	}
	if ( isset( $_GET['gnf_editar_matricula'] ) ) {
		$current_tab = 'matricula';
	}
	$valid_tabs  = array('resumen', 'formularios', 'matricula');
	if (! in_array($current_tab, $valid_tabs, true)) {
		$current_tab = 'resumen';
	}
	if ( ! $tiene_matricula && in_array( $current_tab, array( 'resumen', 'formularios' ), true ) ) {
		$current_tab = 'matricula';
	}

	ob_start();

	// Datos comunes.
	$data = array(
		'user'                 => $user,
		'centro_id'            => $centro_id,
		'entries_by'           => $entries_by,
		'retos_query'          => $retos,
		'anio'                 => $anio,
		'anio_activo'          => $anio_activo,
		'years_available'      => $years_available,
		'docente_estado'       => gnf_get_docente_estado($user->ID),
		'meta_estrellas'       => $meta_estrellas,
		'tiene_matricula'      => $tiene_matricula,
		'retos_seleccionados'  => $retos_seleccionados,
		'retos_disponibles'    => $retos_disponibles,
		'puntos_potenciales'   => $puntos_potenciales,
		'all_retos_complete'   => $all_retos_complete,
		'current_tab'          => $current_tab,
	);

	// Pasar tab activa al template.
	$data['active_docente_tab'] = $current_tab;

	// Incluir template que tiene la estructura compartida (sidebar + contenido).
	// El template usa $data['active_docente_tab'] para mostrar el contenido correcto.
	extract($data);
	include GNF_PATH . 'templates/docente-dashboard.php';

	wp_reset_postdata();
	return ob_get_clean();
}

/**
 * Renderiza la pestaña de Matrícula.
 */
function gnf_render_matricula_tab($data)
{
	extract($data);
?>
	<div class="gnf-panel gnf-matricula-tab" style="background: #fff; border-radius: 0 0 12px 12px; padding: 24px;">
		<h2 style="margin: 0 0 16px;">✏️ Editar Matrícula <?php echo esc_html($anio); ?></h2>

		<p class="gnf-muted" style="margin-bottom: 24px;">
			Aquí puedes modificar tu matrícula para agregar o quitar retos. Los cambios se reflejarán inmediatamente en tu panel.
		</p>

		<?php
		$registros_drive_url = gnf_get_registros_drive_url();
		if ( $registros_drive_url ) :
			?>
			<div style="margin-bottom: 24px; padding: 16px; background: #eff6ff; border-radius: 8px; border: 1px solid #bfdbfe;">
				<strong style="display: block; margin-bottom: 8px;">📊 Registros (Registrar y Reducir)</strong>
				<p style="margin: 0 0 8px; font-size: 0.95em;">Para cargar o consultar datos de agua, energía y residuos:</p>
				<a href="<?php echo esc_url( $registros_drive_url ); ?>" target="_blank" rel="noopener noreferrer" style="color: #1d4ed8; font-weight: 500;">Abrir tabla de registros en Drive →</a>
			</div>
		<?php endif; ?>

		<?php if (! empty($retos_seleccionados) && is_array($retos_seleccionados)) : ?>
			<div style="margin-bottom: 24px; padding: 16px; background: #f0fdf4; border-radius: 8px; border: 1px solid #bbf7d0;">
				<h4 style="margin: 0 0 12px; color: #16a34a;">Retos actualmente matriculados (<?php echo count($retos_seleccionados); ?>):</h4>
				<ul style="margin: 0; padding-left: 20px;">
					<?php foreach ($retos_seleccionados as $reto_id) :
						$reto = get_post($reto_id);
						if (! $reto) continue;
						$puntaje_max = gnf_get_reto_max_points($reto_id, $anio);
					?>
						<li style="margin: 4px 0;">
							<?php echo esc_html($reto->post_title); ?>
							<small style="color: #64748b;">(<?php echo esc_html($puntaje_max); ?> pts)</small>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<?php echo gnf_render_matricula_form(array('centro_id' => $centro_id, 'anio' => $anio)); ?>
	</div>
<?php
}

/**
 * Docente reabre reto en correccion -> estado en_progreso.
 */
function gnf_docente_reopen_entry()
{
	if (! is_user_logged_in()) {
		wp_die('No autorizado');
	}
	check_admin_referer('gnf_docente_reopen', 'gnf_nonce');
	$user_id  = get_current_user_id();
	$entry_id = absint($_POST['entry_id'] ?? 0);
	if (! $entry_id) {
		wp_die('Entrada inválida');
	}
	global $wpdb;
	$table = $wpdb->prefix . 'gn_reto_entries';
	$entry = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $entry_id));
	if (! $entry || (int) $entry->user_id !== (int) $user_id) {
		wp_die('Sin permisos');
	}
	// Solo permitir reabrir desde estado "correccion".
	if ('correccion' !== $entry->estado) {
		wp_die('Solo se puede reabrir un reto en estado de corrección.');
	}
	$wpdb->update(
		$table,
		array(
			'estado'          => 'en_progreso',
			'supervisor_notes' => '',
			'updated_at'      => current_time('mysql'),
		),
		array('id' => $entry_id),
		array('%s', '%s', '%s'),
		array('%d')
	);
	wp_safe_redirect(wp_get_referer() ? wp_get_referer() : home_url());
	exit;
}
add_action('admin_post_gnf_docente_reopen', 'gnf_docente_reopen_entry');

/**
 * Devuelve la URL de panel por defecto para un usuario según su rol.
 *
 * @param WP_User $user Usuario.
 * @return string URL del panel.
 */
function gnf_get_default_panel_url( $user ) {
	$active_year = function_exists( 'gnf_get_active_year' ) ? gnf_get_active_year() : (int) gmdate( 'Y' );

	if ( function_exists( 'gnf_user_can_access_panel' ) && gnf_user_can_access_panel( $user, 'panel-admin' ) ) {
		return home_url( '/panel-admin/' );
	}
	if ( function_exists( 'gnf_user_can_access_panel' ) && gnf_user_can_access_panel( $user, 'panel-supervisor' ) ) {
		return home_url( '/panel-supervisor/' );
	}
	if ( function_exists( 'gnf_user_can_access_panel' ) && gnf_user_can_access_panel( $user, 'panel-docente' ) ) {
		$centro_id = function_exists( 'gnf_get_centro_for_docente' ) ? gnf_get_centro_for_docente( $user->ID ) : 0;
		if ( ! $centro_id ) {
			$centro_id = (int) get_user_meta( $user->ID, 'centro_solicitado', true );
		}

		$matricula_estado = $centro_id && function_exists( 'gnf_get_centro_matricula_estado' )
			? gnf_get_centro_matricula_estado( $centro_id, $active_year )
			: 'no_iniciado';

		if ( 'no_iniciado' === $matricula_estado ) {
			return add_query_arg( 'p', 'matricula', home_url( '/panel-docente/' ) );
		}

		return home_url( '/panel-docente/' );
	}
	// Sin rol reconocido: enviar al panel docente que mostrará el auth form.
	return home_url();
}

/**
 * Maneja login.
 * Redirige al panel del rol correspondiente tras autenticarse.
 */
function gnf_handle_auth_login()
{
	check_admin_referer('gnf_auth_login', 'gnf_nonce');
	$login    = sanitize_text_field(wp_unslash($_POST['user_login'] ?? ''));
	$password = $_POST['user_pass'] ?? '';
	$redirect = ! empty($_POST['redirect']) ? esc_url_raw(wp_unslash($_POST['redirect'])) : '';
	$creds    = array(
		'user_login'    => $login,
		'user_password' => $password,
		'remember'      => true,
	);
	$user = wp_signon($creds, is_ssl());
	if (is_wp_error($user)) {
		$error_redirect = $redirect ?: home_url();
		wp_safe_redirect(add_query_arg('gnf_err', urlencode($user->get_error_message()), $error_redirect));
		exit;
	}

	// Si hay redirect explícito que apunte a un panel permitido para el rol, respetarlo.
	// Si no, redirigir al panel por defecto del rol.
	if ( function_exists( 'gnf_get_primary_panel_slug' ) && '' === gnf_get_primary_panel_slug( $user ) ) {
		if ( function_exists( 'gnf_log_panel_access_context' ) ) {
			gnf_log_panel_access_context(
				'form_auth_login_denied',
				$user,
				array(
					'redirect' => $redirect,
				)
			);
		}

		wp_logout();
		$error_redirect = $redirect ?: home_url( '/panel-docente/' );
		wp_safe_redirect( add_query_arg( 'gnf_err', rawurlencode( 'La cuenta se autentico, pero no tiene un rol o permisos validos para un panel en este sitio.' ), $error_redirect ) );
		exit;
	}

	$default_url = gnf_get_default_panel_url( $user );

	if ( $redirect ) {
		$home_path = parse_url( home_url(), PHP_URL_PATH ) ?: '';
		$req_path  = parse_url( $redirect, PHP_URL_PATH ) ?: '';
		$rel_path  = str_replace( $home_path, '', $req_path );

		foreach ( array( 'panel-admin', 'panel-supervisor', 'panel-docente', 'panel-comite' ) as $panel_slug ) {
			$panel_path = '/' . $panel_slug . '/';
			if ( 0 === strpos( trailingslashit( $rel_path ), $panel_path ) ) {
				if ( ! function_exists( 'gnf_user_can_access_panel' ) || gnf_user_can_access_panel( $user, $panel_slug ) ) {
					wp_safe_redirect( $redirect );
					exit;
				}
				// Panel no permitido → ir al propio.
				wp_safe_redirect( $default_url );
				exit;
			}
		}

		// Redirect apunta a otra URL (no es panel) → respetar.
		wp_safe_redirect( $redirect );
		exit;
	}

	wp_safe_redirect( $default_url );
	exit;
}
add_action('admin_post_nopriv_gnf_auth_login', 'gnf_handle_auth_login');
add_action('admin_post_gnf_auth_login', 'gnf_handle_auth_login');

/**
 * Registro de docente.
 */
function gnf_handle_docente_register()
{
	check_admin_referer('gnf_docente_register', 'gnf_nonce');
	$email    = sanitize_email(wp_unslash($_POST['user_email'] ?? ''));
	$pass     = $_POST['user_pass'] ?? '';
	$name     = sanitize_text_field(wp_unslash($_POST['display_name'] ?? ''));
	$ident    = sanitize_text_field(wp_unslash($_POST['identificacion'] ?? ''));
	$tel      = sanitize_text_field(wp_unslash($_POST['telefono'] ?? ''));
	$cargo    = sanitize_text_field(wp_unslash($_POST['cargo'] ?? ''));
	$redirect = ! empty($_POST['redirect']) ? esc_url_raw(wp_unslash($_POST['redirect'])) : home_url('/panel-docente/');
	$redirect = add_query_arg('tab', 'matricula', remove_query_arg(array('gnf_msg', 'gnf_err', 'tab'), $redirect));

	$centro_id = absint($_POST['centro_id'] ?? 0);
	if (! $centro_id && ! empty($_POST['centro_nombre_lookup'])) {
		$found = gnf_find_similar_centro(
			sanitize_text_field(wp_unslash($_POST['centro_nombre_lookup'])),
			sanitize_text_field(wp_unslash($_POST['centro_codigo_lookup'] ?? ''))
		);
		$centro_id = $found ? (int) $found[0] : 0;
	}

	if (empty($email) || empty($pass)) {
		wp_safe_redirect(add_query_arg('gnf_err', 'Datos incompletos', $redirect));
		exit;
	}

	if (! $centro_id || 'centro_educativo' !== get_post_type($centro_id)) {
		wp_safe_redirect(add_query_arg('gnf_err', rawurlencode('Debes seleccionar un centro educativo válido.'), $redirect));
		exit;
	}

	if (email_exists($email)) {
		wp_safe_redirect(add_query_arg('gnf_err', 'El correo ya existe', $redirect));
		exit;
	}

	$user_id = wp_create_user($email, $pass, $email);
	if (is_wp_error($user_id)) {
		wp_safe_redirect(add_query_arg('gnf_err', urlencode($user_id->get_error_message()), $redirect));
		exit;
	}

	wp_update_user(
		array(
			'ID'           => $user_id,
			'display_name' => $name,
			'role'         => 'docente',
		)
	);

	update_user_meta($user_id, 'gnf_identificacion', $ident);
	update_user_meta($user_id, 'gnf_telefono', $tel);
	update_user_meta($user_id, 'gnf_cargo', $cargo);
	update_user_meta($user_id, 'gnf_docente_status', 'pendiente');

	update_user_meta($user_id, 'centro_solicitado', $centro_id);
	gnf_notify_admins('docente_solicita_acceso', 'Nuevo docente solicita unirse al centro ' . get_the_title($centro_id), 'centro', $centro_id);
	$docentes_activos = (array) get_field('docentes_asociados', $centro_id);
	foreach ($docentes_activos as $doc) {
		gnf_insert_notification($doc, 'docente_solicita_acceso', 'Nuevo docente solicita unirse a tu centro.', 'centro', $centro_id);
	}

	wp_set_current_user($user_id);
	wp_set_auth_cookie($user_id, true);
	$msg = 'Cuenta creada. Ya puedes completar tu matrícula en el portal docente.';
	wp_safe_redirect(add_query_arg('gnf_msg', rawurlencode($msg), $redirect));
	exit;
}
add_action('admin_post_nopriv_gnf_docente_register', 'gnf_handle_docente_register');
add_action('admin_post_gnf_docente_register', 'gnf_handle_docente_register');

/**
 * Handler para registro de supervisores.
 * Los supervisores quedan pendientes de aprobación.
 */
function gnf_handle_supervisor_register()
{
	check_admin_referer('gnf_supervisor_register', 'gnf_nonce');

	$redirect = isset($_POST['redirect']) ? esc_url_raw(wp_unslash($_POST['redirect'])) : home_url();

	// Validar campos obligatorios.
	$display_name   = sanitize_text_field(wp_unslash($_POST['display_name'] ?? ''));
	$email          = sanitize_email(wp_unslash($_POST['user_email'] ?? ''));
	$identificacion = sanitize_text_field(wp_unslash($_POST['identificacion'] ?? ''));
	$telefono       = sanitize_text_field(wp_unslash($_POST['telefono'] ?? ''));
	$password       = $_POST['user_pass'] ?? '';
	$rol_solicitado = sanitize_text_field(wp_unslash($_POST['rol_solicitado'] ?? 'supervisor'));
	$region         = absint($_POST['region'] ?? 0);
	$cargo          = sanitize_text_field(wp_unslash($_POST['cargo'] ?? ''));
	$justificacion  = sanitize_textarea_field(wp_unslash($_POST['justificacion'] ?? ''));

	if (empty($display_name) || empty($email) || empty($identificacion) || empty($password) || empty($region)) {
		wp_safe_redirect(add_query_arg('gnf_err', rawurlencode('Todos los campos obligatorios son requeridos.'), $redirect));
		exit;
	}

	// Verificar si el email ya existe.
	if (email_exists($email)) {
		wp_safe_redirect(add_query_arg('gnf_err', rawurlencode('Ya existe una cuenta con este correo electrónico.'), $redirect));
		exit;
	}

	// Crear usuario.
	$user_login = sanitize_user(strtok($email, '@'), true);
	$suffix     = 1;
	$base_login = $user_login;
	while (username_exists($user_login)) {
		$user_login = $base_login . $suffix;
		++$suffix;
	}

	$user_id = wp_create_user($user_login, $password, $email);

	if (is_wp_error($user_id)) {
		wp_safe_redirect(add_query_arg('gnf_err', rawurlencode('Error al crear usuario: ' . $user_id->get_error_message()), $redirect));
		exit;
	}

	// Asignar rol según solicitud.
	$user = new WP_User($user_id);
	if ('comite_bae' === $rol_solicitado) {
		$user->set_role('comite_bae');
		$status_key = 'gnf_supervisor_status'; // Usamos el mismo meta para simplicidad.
	} else {
		$user->set_role('supervisor');
		$status_key = 'gnf_supervisor_status';
	}

	// Guardar metadatos.
	wp_update_user(array(
		'ID'           => $user_id,
		'display_name' => $display_name,
	));

	update_user_meta($user_id, 'gnf_identificacion', $identificacion);
	update_user_meta($user_id, 'gnf_telefono', $telefono);
	update_user_meta($user_id, 'gnf_cargo', $cargo);
	update_user_meta($user_id, 'gnf_justificacion', $justificacion);
	update_user_meta($user_id, 'gnf_rol_solicitado', $rol_solicitado);
	update_user_meta($user_id, 'region', $region);
	update_user_meta($user_id, $status_key, 'pendiente');

	// Notificar a administradores.
	$admins = get_users(array('role' => 'administrator', 'fields' => 'ID'));
	foreach ($admins as $admin_id) {
		gnf_insert_notification(
			$admin_id,
			'supervisor_pendiente',
			sprintf('Nueva solicitud de %s: %s (%s)', $rol_solicitado, $display_name, $email),
			'usuario',
			$user_id
		);
	}

	// Mensaje de éxito.
	$msg = 'Tu solicitud ha sido enviada. Recibirás un correo cuando sea aprobada.';
	wp_safe_redirect(add_query_arg('gnf_msg', rawurlencode($msg), $redirect));
	exit;
}
add_action('admin_post_nopriv_gnf_supervisor_register', 'gnf_handle_supervisor_register');
add_action('admin_post_gnf_supervisor_register', 'gnf_handle_supervisor_register');

/**
 * Admin aprueba docente pendiente.
 */
function gnf_handle_aprobar_docente()
{
	if (! current_user_can('manage_options')) {
		wp_die('No autorizado');
	}
	check_admin_referer('gnf_admin_action', 'gnf_nonce');
	$user_id   = absint($_POST['user_id'] ?? 0);
	$centro_id = absint($_POST['centro_id'] ?? 0);
	if ($user_id) {
		if ($centro_id) {
			$docs = (array) get_field('docentes_asociados', $centro_id);
			if (! in_array($user_id, $docs, true)) {
				$docs[] = $user_id;
				update_field('docentes_asociados', $docs, $centro_id);
			}
		}
		gnf_approve_docente($user_id);
	}
	wp_safe_redirect(wp_get_referer() ? wp_get_referer() : admin_url());
	exit;
}
add_action('admin_post_gnf_aprobar_docente', 'gnf_handle_aprobar_docente');

/**
 * Admin rechaza docente.
 */
function gnf_handle_rechazar_docente()
{
	if (! current_user_can('manage_options')) {
		wp_die('No autorizado');
	}
	check_admin_referer('gnf_admin_action', 'gnf_nonce');
	$user_id   = absint($_POST['user_id'] ?? 0);
	$centro_id = absint($_POST['centro_id'] ?? 0);
	if ($user_id) {
		gnf_insert_notification($user_id, 'docente_rechazado', 'Tu solicitud fue rechazada.', 'centro', $centro_id);
		wp_delete_user($user_id);
	}
	wp_safe_redirect(wp_get_referer() ? wp_get_referer() : admin_url());
	exit;
}
add_action('admin_post_gnf_rechazar_docente', 'gnf_handle_rechazar_docente');

/**
 * Admin aprueba centro pendiente.
 */
function gnf_handle_aprobar_centro()
{
	if (! current_user_can('manage_options')) {
		wp_die('No autorizado');
	}
	check_admin_referer('gnf_admin_action', 'gnf_nonce');
	$centro_id = absint($_POST['centro_id'] ?? 0);
	$user_id   = absint($_POST['user_id'] ?? 0);
	if ($centro_id) {
		wp_update_post(
			array(
				'ID'          => $centro_id,
				'post_status' => 'publish',
			)
		);
		update_post_meta($centro_id, 'estado_centro', 'activo');
		if ($user_id) {
			$docs = (array) get_field('docentes_asociados', $centro_id);
			if (! in_array($user_id, $docs, true)) {
				$docs[] = $user_id;
				update_field('docentes_asociados', $docs, $centro_id);
			}
			gnf_approve_docente($user_id);
		}
		gnf_notify_admins('centro_aprobado', 'Centro aprobado.', 'centro', $centro_id);
		if ($user_id) {
			gnf_insert_notification($user_id, 'centro_aprobado', 'Tu centro fue aprobado.', 'centro', $centro_id);
		}
	}
	wp_safe_redirect(wp_get_referer() ? wp_get_referer() : admin_url());
	exit;
}
add_action('admin_post_gnf_aprobar_centro', 'gnf_handle_aprobar_centro');

/**
 * Admin rechaza centro pendiente.
 */
function gnf_handle_rechazar_centro()
{
	if (! current_user_can('manage_options')) {
		wp_die('No autorizado');
	}
	check_admin_referer('gnf_admin_action', 'gnf_nonce');
	$centro_id = absint($_POST['centro_id'] ?? 0);
	$user_id   = absint($_POST['user_id'] ?? 0);
	if ($centro_id) {
		wp_update_post(
			array(
				'ID'          => $centro_id,
				'post_status' => 'draft',
			)
		);
		update_post_meta($centro_id, 'estado_centro', 'rechazado');
	}
	if ($user_id) {
		gnf_insert_notification($user_id, 'centro_rechazado', 'Tu centro fue rechazado. Corrige la informacion y reenvia.', 'centro', $centro_id);
	}
	wp_safe_redirect(wp_get_referer() ? wp_get_referer() : admin_url());
	exit;
}
add_action('admin_post_gnf_rechazar_centro', 'gnf_handle_rechazar_centro');

/**
 * Docente actualiza información de centro.
 */
function gnf_handle_update_centro()
{
	if (! is_user_logged_in()) {
		wp_die('No autorizado');
	}
	check_admin_referer('gnf_update_centro', 'gnf_nonce');
	$redirect = wp_get_referer() ? wp_get_referer() : home_url('/panel-docente/');
	$redirect = remove_query_arg(array('gnf_msg', 'gnf_err'), $redirect);

	$centro_id = absint($_POST['centro_id'] ?? 0);
	$user_id   = get_current_user_id();
	if (! $centro_id) {
		wp_safe_redirect(add_query_arg('gnf_err', rawurlencode('Centro invalido.'), $redirect));
		exit;
	}
	if (! gnf_user_can_access_centro($user_id, $centro_id) && ! current_user_can('manage_options')) {
		wp_safe_redirect(add_query_arg('gnf_err', rawurlencode('Sin permisos para editar este centro.'), $redirect));
		exit;
	}
	$title      = sanitize_text_field(wp_unslash($_POST['centro_nombre'] ?? ''));
	$dir        = sanitize_textarea_field(wp_unslash($_POST['centro_direccion'] ?? ''));
	$prov       = sanitize_text_field(wp_unslash($_POST['centro_provincia'] ?? ''));
	$canton     = sanitize_text_field(wp_unslash($_POST['centro_canton'] ?? ''));
	$codigo     = sanitize_text_field(wp_unslash($_POST['centro_codigo'] ?? $_POST['centro_codigo_mep'] ?? ''));
	$region     = absint($_POST['centro_region'] ?? 0);
	$circuito   = sanitize_text_field(wp_unslash($_POST['centro_circuito'] ?? ''));
	$telefono   = sanitize_text_field(wp_unslash($_POST['centro_telefono'] ?? ''));
	$correo     = sanitize_email(wp_unslash($_POST['centro_correo_institucional'] ?? ''));

	$nivel      = gnf_normalize_centro_choice('nivel_educativo', sanitize_text_field(wp_unslash($_POST['centro_nivel_educativo'] ?? '')));
	$dependencia = gnf_normalize_centro_choice('dependencia', sanitize_text_field(wp_unslash($_POST['centro_dependencia'] ?? '')));
	$jornada    = gnf_normalize_centro_choice('jornada', sanitize_text_field(wp_unslash($_POST['centro_jornada'] ?? '')));
	$tipologia  = gnf_normalize_centro_choice('tipologia', sanitize_text_field(wp_unslash($_POST['centro_tipologia'] ?? '')));

	$total_estudiantes     = absint($_POST['centro_total_estudiantes'] ?? 0);
	$estudiantes_hombres   = absint($_POST['centro_estudiantes_hombres'] ?? 0);
	$estudiantes_mujeres   = absint($_POST['centro_estudiantes_mujeres'] ?? 0);
	$estudiantes_migrantes = absint($_POST['centro_estudiantes_migrantes'] ?? 0);

	$ultimo_galardon = max(1, min(5, absint($_POST['centro_ultimo_galardon_estrellas'] ?? 1)));
	$ultimo_anio     = sanitize_text_field(wp_unslash($_POST['centro_ultimo_anio_participacion'] ?? '2025'));
	if (! in_array($ultimo_anio, array('2025', '2024', 'otro'), true)) {
		$ultimo_anio = '2025';
	}
	$ultimo_anio_otro = absint($_POST['centro_ultimo_anio_participacion_otro'] ?? 0);

	$coordinador_cargo   = gnf_normalize_centro_choice('coordinador_cargo', sanitize_text_field(wp_unslash($_POST['coordinador_cargo'] ?? '')));
	$coordinador_nombre  = sanitize_text_field(wp_unslash($_POST['coordinador_nombre'] ?? ''));
	$coordinador_celular = sanitize_text_field(wp_unslash($_POST['coordinador_celular'] ?? ''));
	$docente_nombre      = sanitize_text_field(wp_unslash($_POST['docente_nombre'] ?? wp_get_current_user()->display_name));
	if ('director' === $coordinador_cargo && '' === $coordinador_nombre) {
		$coordinador_nombre = $docente_nombre;
	}

	$required = array(
		$title,
		$codigo,
		$correo,
		$telefono,
		$dir,
		$prov,
		$canton,
		$circuito,
		$region,
		$nivel,
		$dependencia,
		$jornada,
		$tipologia,
		$coordinador_cargo,
		$coordinador_celular,
	);
	foreach ($required as $req) {
		if ('' === (string) $req || 0 === $req) {
			wp_safe_redirect(add_query_arg('gnf_err', rawurlencode('Completa todos los campos obligatorios del centro.'), $redirect));
			exit;
		}
	}

	if (! is_email($correo)) {
		wp_safe_redirect(add_query_arg('gnf_err', rawurlencode('El correo institucional no es valido.'), $redirect));
		exit;
	}

	if (! gnf_is_valid_cr_province_canton($prov, $canton)) {
		wp_safe_redirect(add_query_arg('gnf_err', rawurlencode('El canton seleccionado no pertenece a la provincia elegida.'), $redirect));
		exit;
	}

	if ('otro' === $ultimo_anio && ! $ultimo_anio_otro) {
		wp_safe_redirect(add_query_arg('gnf_err', rawurlencode('Debes indicar el ultimo año de participación cuando eliges "otro".'), $redirect));
		exit;
	}

	if ('director' !== $coordinador_cargo && '' === $coordinador_nombre) {
		wp_safe_redirect(add_query_arg('gnf_err', rawurlencode('Debes indicar el nombre del coordinador(a) PBAE.'), $redirect));
		exit;
	}

	if ($total_estudiantes > 0 && ($estudiantes_hombres + $estudiantes_mujeres) > $total_estudiantes) {
		wp_safe_redirect(add_query_arg('gnf_err', rawurlencode('La suma de hombres y mujeres no puede superar el total de estudiantes.'), $redirect));
		exit;
	}

	if ($title) {
		wp_update_post(
			array(
				'ID'         => $centro_id,
				'post_title' => $title,
			)
		);
	}
	update_post_meta($centro_id, 'direccion', $dir);
	update_post_meta($centro_id, 'provincia', $prov);
	update_post_meta($centro_id, 'canton', $canton);
	update_post_meta($centro_id, 'circuito', $circuito);
	update_post_meta($centro_id, 'codigo_mep', $codigo);
	update_post_meta($centro_id, 'correo_institucional', $correo);
	update_post_meta($centro_id, 'telefono', $telefono);
	update_post_meta($centro_id, 'nivel_educativo', $nivel);
	update_post_meta($centro_id, 'dependencia', $dependencia);
	update_post_meta($centro_id, 'jornada', $jornada);
	update_post_meta($centro_id, 'tipologia', $tipologia);
	// Compatibilidad con llaves legacy.
	update_post_meta($centro_id, 'modalidad', $nivel);
	update_post_meta($centro_id, 'horario', $jornada);
	update_post_meta($centro_id, 'total_estudiantes', $total_estudiantes);
	update_post_meta($centro_id, 'estudiantes_hombres', $estudiantes_hombres);
	update_post_meta($centro_id, 'estudiantes_mujeres', $estudiantes_mujeres);
	update_post_meta($centro_id, 'estudiantes_migrantes', $estudiantes_migrantes);
	update_post_meta($centro_id, 'ultimo_galardon_estrellas', $ultimo_galardon);
	update_post_meta($centro_id, 'ultimo_anio_participacion', $ultimo_anio);
	update_post_meta($centro_id, 'ultimo_anio_participacion_otro', $ultimo_anio_otro);
	update_post_meta($centro_id, 'coordinador_pbae_cargo', $coordinador_cargo);
	update_post_meta($centro_id, 'coordinador_pbae_nombre', $coordinador_nombre);
	update_post_meta($centro_id, 'coordinador_pbae_celular', $coordinador_celular);

	if ($region) {
		update_post_meta($centro_id, 'region', $region);
		wp_set_object_terms($centro_id, array($region), 'gn_region', false);
	}
	if (! current_user_can('manage_options')) {
		update_post_meta($centro_id, 'estado_centro', 'pendiente_de_revision_admin');
		gnf_notify_admins('centro_necesita_revision', 'Centro actualizado, requiere revision.', 'centro', $centro_id);
	}

	$msg = current_user_can('manage_options')
		? 'Centro actualizado correctamente.'
		: 'Centro actualizado y enviado para revision administrativa.';
	wp_safe_redirect(add_query_arg('gnf_msg', rawurlencode($msg), $redirect));
	exit;
}
add_action('admin_post_gnf_update_centro', 'gnf_handle_update_centro');
