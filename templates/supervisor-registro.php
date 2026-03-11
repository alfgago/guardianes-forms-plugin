<?php
/**
 * Template: Formulario de registro para supervisores.
 *
 * Los supervisores se registran y quedan pendientes de aprobación.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$msg_error  = isset( $_GET['gnf_err'] ) ? sanitize_text_field( wp_unslash( $_GET['gnf_err'] ) ) : '';
$msg_notice = isset( $_GET['gnf_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['gnf_msg'] ) ) : '';

// Obtener regiones disponibles.
$regiones = get_terms( array(
	'taxonomy'   => 'gn_region',
	'hide_empty' => false,
	'orderby'    => 'name',
	'order'      => 'ASC',
) );
?>
<div class="gnf-container gnf-supervisor-registro">
	<div class="gnf-card gnf-card--auth">
		<div class="gnf-card__header">
			<h2>📋 Registro de Supervisor / Comité BAE</h2>
			<p class="gnf-muted">Complete el formulario para solicitar acceso como supervisor. Su solicitud será revisada por un administrador.</p>
		</div>

		<?php if ( $msg_error ) : ?>
			<div class="gnf-alert gnf-alert--error"><?php echo esc_html( $msg_error ); ?></div>
		<?php endif; ?>
		<?php if ( $msg_notice ) : ?>
			<div class="gnf-alert gnf-alert--success"><?php echo esc_html( $msg_notice ); ?></div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gnf-form">
			<?php wp_nonce_field( 'gnf_supervisor_register', 'gnf_nonce' ); ?>
			<input type="hidden" name="action" value="gnf_supervisor_register" />
			<input type="hidden" name="redirect" value="<?php echo esc_url( home_url( add_query_arg( array() ) ) ); ?>" />

			<div class="gnf-form__section">
				<h3>Datos personales</h3>

				<label class="gnf-form__label">
					<?php esc_html_e( 'Nombre completo', 'guardianes-formularios' ); ?> <span class="required">*</span>
					<input type="text" name="display_name" required class="gnf-form__input" />
				</label>

				<label class="gnf-form__label">
					<?php esc_html_e( 'Correo electrónico', 'guardianes-formularios' ); ?> <span class="required">*</span>
					<input type="email" name="user_email" required class="gnf-form__input" />
				</label>

				<label class="gnf-form__label">
					<?php esc_html_e( 'Identificación (cédula)', 'guardianes-formularios' ); ?> <span class="required">*</span>
					<input type="text" name="identificacion" required class="gnf-form__input" />
				</label>

				<label class="gnf-form__label">
					<?php esc_html_e( 'Teléfono', 'guardianes-formularios' ); ?> <span class="required">*</span>
					<input type="tel" name="telefono" required class="gnf-form__input" />
				</label>

				<label class="gnf-form__label">
					<?php esc_html_e( 'Contraseña', 'guardianes-formularios' ); ?> <span class="required">*</span>
					<input type="password" name="user_pass" required minlength="8" class="gnf-form__input" />
					<small class="gnf-form__hint">Mínimo 8 caracteres</small>
				</label>
			</div>

			<div class="gnf-form__section">
				<h3>Tipo de registro</h3>

				<label class="gnf-form__label">
					<?php esc_html_e( 'Rol solicitado', 'guardianes-formularios' ); ?> <span class="required">*</span>
					<select name="rol_solicitado" required class="gnf-form__select">
						<option value="supervisor"><?php esc_html_e( 'Supervisor Regional', 'guardianes-formularios' ); ?></option>
						<option value="comite_bae"><?php esc_html_e( 'Comité Bandera Azul', 'guardianes-formularios' ); ?></option>
					</select>
				</label>

				<label class="gnf-form__label">
					<?php esc_html_e( 'Dirección Regional', 'guardianes-formularios' ); ?> <span class="required">*</span>
					<select name="region" required class="gnf-form__select">
						<option value=""><?php esc_html_e( '-- Seleccione --', 'guardianes-formularios' ); ?></option>
						<?php foreach ( $regiones as $region ) : ?>
							<option value="<?php echo esc_attr( $region->term_id ); ?>">
								<?php echo esc_html( $region->name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<small class="gnf-form__hint">Para Comité BAE, seleccione cualquier región (tendrá acceso a todas).</small>
				</label>

				<label class="gnf-form__label">
					<?php esc_html_e( 'Cargo o función', 'guardianes-formularios' ); ?>
					<input type="text" name="cargo" class="gnf-form__input" placeholder="Ej: Asesor regional, Coordinador ambiental..." />
				</label>

				<label class="gnf-form__label">
					<?php esc_html_e( 'Justificación de solicitud', 'guardianes-formularios' ); ?>
					<textarea name="justificacion" rows="3" class="gnf-form__textarea" placeholder="Explique brevemente por qué solicita este acceso..."></textarea>
				</label>
			</div>

			<div class="gnf-form__actions">
				<button type="submit" class="gnf-btn gnf-btn--primary gnf-btn--lg">
					<?php esc_html_e( 'Enviar solicitud de registro', 'guardianes-formularios' ); ?>
				</button>
			</div>

			<p class="gnf-form__note">
				<small>
					<?php esc_html_e( 'Al enviar esta solicitud, un administrador revisará sus datos y le notificará por correo electrónico cuando su cuenta sea aprobada.', 'guardianes-formularios' ); ?>
				</small>
			</p>
		</form>

		<p class="gnf-auth__link" style="text-align:center;margin-top:24px;">
			<?php esc_html_e( '¿Ya tienes cuenta?', 'guardianes-formularios' ); ?>
			<a href="<?php echo esc_url( home_url( '/panel-supervisor/' ) ); ?>"><?php esc_html_e( 'Iniciar sesión', 'guardianes-formularios' ); ?></a>
		</p>
	</div>
</div>

<style>
.gnf-supervisor-registro {
	max-width: 600px;
	margin: 40px auto;
	padding: 0 20px;
}
.gnf-supervisor-registro .gnf-card--auth {
	background: #fff;
	border-radius: 16px;
	padding: 32px;
	box-shadow: 0 4px 24px rgba(0,0,0,0.08);
}
.gnf-supervisor-registro .gnf-card__header h2 {
	margin: 0 0 8px;
	font-size: 1.5rem;
}
.gnf-supervisor-registro .gnf-form__section {
	margin-bottom: 24px;
}
.gnf-supervisor-registro .gnf-form__section h3 {
	margin: 0 0 16px;
	padding-bottom: 8px;
	border-bottom: 2px solid #e0e0e0;
	font-size: 1.1rem;
	color: #333;
}
.gnf-supervisor-registro .gnf-form__label {
	display: block;
	margin-bottom: 16px;
	font-weight: 500;
	color: #444;
}
.gnf-supervisor-registro .gnf-form__label .required {
	color: #e53935;
}
.gnf-supervisor-registro .gnf-form__input,
.gnf-supervisor-registro .gnf-form__select,
.gnf-supervisor-registro .gnf-form__textarea {
	display: block;
	width: 100%;
	margin-top: 6px;
	padding: 12px 14px;
	border: 1px solid #ddd;
	border-radius: 8px;
	font-size: 1rem;
	transition: border-color 0.2s, box-shadow 0.2s;
}
.gnf-supervisor-registro .gnf-form__input:focus,
.gnf-supervisor-registro .gnf-form__select:focus,
.gnf-supervisor-registro .gnf-form__textarea:focus {
	outline: none;
	border-color: #1976d2;
	box-shadow: 0 0 0 3px rgba(25,118,210,0.1);
}
.gnf-supervisor-registro .gnf-form__hint {
	display: block;
	margin-top: 4px;
	color: #888;
	font-size: 0.85rem;
}
.gnf-supervisor-registro .gnf-form__actions {
	margin-top: 24px;
}
.gnf-supervisor-registro .gnf-btn--lg {
	width: 100%;
	padding: 14px 24px;
	font-size: 1.1rem;
}
.gnf-supervisor-registro .gnf-form__note {
	margin-top: 16px;
	text-align: center;
	color: #666;
}
</style>

