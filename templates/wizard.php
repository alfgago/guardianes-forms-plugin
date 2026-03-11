<?php

/**
 * Template: Wizard Maestro de Formularios BAE.
 *
 * Variables disponibles:
 * - $user WP_User
 * - $centro_id int
 * - $anio int
 * - $paso_actual int (1-indexed)
 * - $total_steps int
 * - $steps array
 * - $current_step array
 */

$centro_title  = $centro_id ? get_the_title($centro_id) : 'Sin centro asignado';
$puntaje_total = $centro_id ? gnf_get_centro_puntaje_total($centro_id, $anio) : 0;
$meta_estrellas = $centro_id ? gnf_get_centro_meta_estrellas($centro_id, $anio) : 0;
$puntos_potenciales = gnf_get_puntos_potenciales($centro_id, $anio);

// Calcular progreso general.
$retos_completados = 0;
$total_retos = 0;
foreach ($steps as $step) {
    if ('reto' === $step['type']) {
        $total_retos++;
        if (in_array($step['estado'], array('completo', 'enviado', 'aprobado'), true)) {
            $retos_completados++;
        }
    }
}
$progreso_general = $total_retos > 0 ? round(($retos_completados / $total_retos) * 100) : 0;
?>
<style>
    .gnf-wizard {
        max-width: 1200px;
        margin: 0 auto;
        font-family: var(--gnf-font-body);
    }

    .gnf-wizard__header {
        background: linear-gradient(135deg, #1e3a5f 0%, #2d5a87 100%);
        border-radius: 16px;
        padding: 24px 32px;
        color: #fff;
        margin-bottom: 24px;
    }

    .gnf-wizard__header-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
    }

    .gnf-wizard__header h2 {
        color: #fff;
        margin: 0;
        font-size: 24px;
    }

    .gnf-wizard__meta {
        display: flex;
        gap: 24px;
        font-size: 14px;
        opacity: 0.9;
    }

    .gnf-wizard__score {
        background: rgba(255, 255, 255, 0.15);
        padding: 12px 20px;
        border-radius: 12px;
        text-align: center;
    }

    .gnf-wizard__score strong {
        display: block;
        font-size: 28px;
    }

    /* Navegación de pasos */
    .gnf-wizard__nav {
        display: flex;
        gap: 4px;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 8px;
        margin-bottom: 24px;
        overflow-x: auto;
    }

    .gnf-wizard__step {
        flex: 1;
        min-width: 150px;
        padding: 12px 16px;
        border-radius: 8px;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s;
        text-decoration: none;
        color: #64748b;
        border: 2px solid transparent;
    }

    .gnf-wizard__step:hover {
        background: #fff;
        color: #1e3a5f;
    }

    .gnf-wizard__step--active {
        background: #fff;
        color: #1e3a5f;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        border-color: #2f9e44;
    }

    .gnf-wizard__step--completed {
        background: #dcfce7;
        color: #16a34a;
    }

    .gnf-wizard__step--locked {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .gnf-wizard__step-icon {
        width: 75px;
        height: 75px;
        margin: 0 auto 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
        line-height: 1;
        position: relative;
    }

    .gnf-wizard__step-icon img {
        width: 75px;
        height: 75px;
        object-fit: contain;
        display: block;
        border-radius: 6px;
    }

    .gnf-wizard__step-icon-marker {
        position: absolute;
        bottom: -4px;
        right: -4px;
        background: #22c55e;
        color: #fff;
        border-radius: 50%;
        width: 16px;
        height: 16px;
        font-size: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .gnf-wizard__step-title {
        font-size: 12px;
        font-weight: 600;
        display: block;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .gnf-wizard__step-status {
        font-size: 10px;
        display: block;
        margin-top: 2px;
    }

    /* Barra de progreso general */
    .gnf-wizard__progress {
        background: #e5e7eb;
        border-radius: 8px;
        height: 8px;
        margin-top: 16px;
        overflow: hidden;
    }

    .gnf-wizard__progress-bar {
        height: 100%;
        background: linear-gradient(90deg, #4ade80, #22c55e);
        border-radius: 8px;
        transition: width 0.3s ease;
    }

    /* Contenido del paso */
    .gnf-wizard__content {
        background: #fff;
        border-radius: 16px;
        padding: 32px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        min-height: 400px;
    }

    .gnf-wizard__step-header {
        display: flex;
        align-items: center;
        gap: 16px;
        margin-bottom: 24px;
        padding-bottom: 16px;
        border-bottom: 2px solid #e5e7eb;
    }

    .gnf-wizard__step-header-icon {
        width: 56px;
        height: 56px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
        background: #f0f9ff;
    }

    .gnf-wizard__step-header-icon img {
        width: 40px;
        height: 40px;
        object-fit: contain;
    }

    .gnf-wizard__step-header h3 {
        margin: 0;
        font-size: 20px;
        color: #1e3a5f;
    }

    .gnf-wizard__step-header p {
        margin: 4px 0 0;
        color: #64748b;
        font-size: 14px;
    }

    /* Formulario embebido */
    .gnf-wizard__form {
        margin: 24px 0;
    }

    /* Checklist progress */
    .gnf-wizard__checklist-progress {
        background: #f8fafc;
        border-radius: 8px;
        padding: 16px;
        margin-bottom: 24px;
    }

    .gnf-wizard__checklist-bar {
        background: #e5e7eb;
        border-radius: 4px;
        height: 8px;
        overflow: hidden;
        margin-top: 8px;
    }

    .gnf-wizard__checklist-fill {
        height: 100%;
        background: #369484;
        border-radius: 4px;
        transition: width 0.3s;
    }

    /* Botones de navegación */
    .gnf-wizard__actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 32px;
        padding-top: 24px;
        border-top: 2px solid #e5e7eb;
    }

    .gnf-wizard__actions-left,
    .gnf-wizard__actions-right {
        display: flex;
        gap: 12px;
    }

    /* Panel de envío final */
    .gnf-wizard__submit-panel {
        background: linear-gradient(135deg, #369484 0%, #2d7a6d 100%);
        border-radius: 16px;
        padding: 32px;
        color: #fff;
        text-align: center;
    }

    .gnf-wizard__submit-panel h3 {
        color: #fff;
        margin: 0 0 16px;
        font-size: 24px;
    }

    .gnf-wizard__submit-panel p {
        margin: 0 0 24px;
        opacity: 0.9;
    }

    .gnf-wizard__submit-panel--blocked {
        background: #94a3b8;
    }

    /* Resumen de retos */
    .gnf-wizard__summary {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 16px;
        margin: 24px 0;
    }

    .gnf-wizard__summary-item {
        background: rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        padding: 16px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .gnf-wizard__summary-item--complete {
        background: rgba(74, 222, 128, 0.2);
    }

    .gnf-wizard__summary-item--pending {
        background: rgba(251, 191, 36, 0.2);
    }
</style>

<div class="gnf-wizard" data-centro-id="<?php echo esc_attr($centro_id); ?>" data-anio="<?php echo esc_attr($anio); ?>">
    <!-- Header -->
    <div class="gnf-wizard__header">
        <div class="gnf-wizard__header-top">
            <div>
                <h2>📋 Formulario Bandera Azul Ecológica</h2>
                <div class="gnf-wizard__meta">
                    <span>🏫 <?php echo esc_html($centro_title); ?></span>
                    <span>📅 Año <?php echo esc_html($anio); ?></span>
                    <?php if ($meta_estrellas) : ?>
                        <span>🎯 Meta: <?php echo esc_html($meta_estrellas); ?> estrella<?php echo $meta_estrellas > 1 ? 's' : ''; ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="gnf-wizard__score">
                <strong><?php echo esc_html($puntaje_total); ?></strong>
                <span>de <?php echo esc_html($puntos_potenciales); ?> eco puntos</span>
            </div>
        </div>
        <div class="gnf-wizard__progress">
            <div class="gnf-wizard__progress-bar" style="width: <?php echo esc_attr($progreso_general); ?>%;"></div>
        </div>
        <small style="opacity: 0.7; margin-top: 8px; display: block;">
            <?php echo esc_html($retos_completados); ?> de <?php echo esc_html($total_retos); ?> retos completados (<?php echo esc_html($progreso_general); ?>%)
        </small>
    </div>

    <!-- Navegación de pasos -->
    <nav class="gnf-wizard__nav" role="tablist">
        <?php foreach ($steps as $idx => $step) :
            $step_num = $idx + 1;
            $is_active = $paso_actual === $step_num;
            $is_completed = in_array($step['estado'], array('completo', 'enviado', 'aprobado', 'listo'), true);
            $is_locked = 'bloqueado' === $step['estado'];

            $classes = 'gnf-wizard__step';
            if ($is_active) $classes .= ' gnf-wizard__step--active';
            if ($is_completed) $classes .= ' gnf-wizard__step--completed';
            if ($is_locked) $classes .= ' gnf-wizard__step--locked';

            $step_url = $is_locked ? '#' : add_query_arg('paso', $step_num);
        ?>
            <a href="<?php echo esc_url($step_url); ?>"
                class="<?php echo esc_attr($classes); ?>"
                role="tab"
                aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>"
                <?php echo $is_locked ? 'aria-disabled="true"' : ''; ?>>
                <span class="gnf-wizard__step-icon">
                    <?php if (is_string($step['icon']) && strpos($step['icon'], 'http') === 0) : ?>
                        <img src="<?php echo esc_url($step['icon']); ?>" alt="" />
                        <?php if ($is_completed) : ?>
                            <span class="gnf-wizard__step-icon-marker">✓</span>
                        <?php endif; ?>
                    <?php else : ?>
                        <?php echo $is_completed ? '✅' : esc_html($step['icon']); ?>
                    <?php endif; ?>
                </span>
                <span class="gnf-wizard__step-title"><?php echo esc_html($step['title']); ?></span>
                <span class="gnf-wizard__step-status">
                    <?php
                    if ($is_completed) {
                        echo '✓ Completo';
                    } elseif (isset($step['progress']) && $step['progress'] > 0) {
                        echo esc_html($step['progress']) . '%';
                    } elseif ($is_locked) {
                        echo '🔒 Bloqueado';
                    } else {
                        echo 'Pendiente';
                    }
                    ?>
                </span>
            </a>
        <?php endforeach; ?>
    </nav>

    <!-- Contenido del paso actual -->
    <div class="gnf-wizard__content">
        <?php if ('reto' === $current_step['type']) : ?>
            <!-- Paso de Reto -->
            <?php
            $reto_color = $current_step['color'] ?? '#369484';
            $entry = $current_step['entry'] ?? null;
            $puntaje_max = $current_step['puntaje_max'] ?? 0;
            $entry_puntaje = $entry ? absint($entry->puntaje) : 0;
            ?>

            <div class="gnf-wizard__step-header">
                <div class="gnf-wizard__step-header-icon" style="background: <?php echo esc_attr($reto_color); ?>20;">
                    <?php if (is_string($current_step['icon']) && strpos($current_step['icon'], 'http') === 0) : ?>
                        <img src="<?php echo esc_url($current_step['icon']); ?>" alt="" />
                    <?php else : ?>
                        <span style="color: <?php echo esc_attr($reto_color); ?>;">🎯</span>
                    <?php endif; ?>
                </div>
                <div>
                    <h3 style="color: <?php echo esc_attr($reto_color); ?>;"><?php echo esc_html($current_step['title']); ?></h3>
                    <p><?php echo esc_html($current_step['description']); ?></p>
                </div>
                <div style="margin-left: auto; text-align: right;">
                    <strong style="font-size: 24px; color: <?php echo esc_attr($reto_color); ?>;"><?php echo esc_html($puntaje_max); ?></strong>
                    <span style="color: #64748b; display: block; font-size: 12px;">puntos</span>
                </div>
            </div>

            <!-- Puntaje automático -->
            <?php if ($puntaje_max > 0) : ?>
            <div class="gnf-wizard__checklist-progress">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-weight: 600; color: #1e3a5f;">Puntaje</span>
                    <span style="color: <?php echo $entry_puntaje > 0 ? '#16a34a' : '#64748b'; ?>; font-weight: 600;">
                        <?php echo esc_html($entry_puntaje); ?> / <?php echo esc_html($puntaje_max); ?> eco puntos
                    </span>
                </div>
                <div class="gnf-wizard__checklist-bar">
                    <div class="gnf-wizard__checklist-fill" style="width: <?php echo $puntaje_max > 0 ? round(($entry_puntaje / $puntaje_max) * 100) : 0; ?>%; background: <?php echo esc_attr($reto_color); ?>;"></div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Estado actual -->
            <?php if ($entry) : ?>
                <div style="background: #f8fafc; border-radius: 8px; padding: 12px 16px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center;">
                    <span>
                        <strong>Estado:</strong>
                        <span class="gnf-status gnf-status--<?php echo esc_attr($entry->estado); ?>">
                            <?php echo esc_html(gnf_get_estado_label($entry->estado)); ?>
                        </span>
                    </span>
                    <?php if (! empty($entry->supervisor_notes)) : ?>
                        <span style="color: #dc2626;">
                            ⚠️ <?php echo esc_html($entry->supervisor_notes); ?>
                        </span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="gnf-wizard__form">
                <?php
                if ($current_step['form_id']) {
                    echo do_shortcode('[wpforms id="' . esc_attr($current_step['form_id']) . '"]');
                } else {
                    echo '<p class="gnf-muted">Formulario del reto no configurado. Contacta al administrador.</p>';
                }
                ?>
            </div>

            <!-- Botón Finalizar Reto -->
            <?php if ($entry && in_array($entry->estado, array('en_progreso', 'no_iniciado', 'enviado'), true)) : ?>
                <div style="background: #fef3c7; border-radius: 12px; padding: 20px; margin-top: 24px;">
                    <p style="margin: 0 0 12px; color: #92400e;">
                        <strong>¿Completaste todas las evidencias?</strong><br>
                        Cuando hayas subido todas las evidencias requeridas, haz clic en "Finalizar Reto". El puntaje se calcula automáticamente.
                    </p>
                    <button type="button"
                        class="gnf-btn gnf-wizard-finalizar-reto"
                        data-entry-id="<?php echo esc_attr($entry->id); ?>">
                        ✅ Finalizar Reto
                    </button>
                </div>
            <?php elseif ($entry && 'completo' === $entry->estado) : ?>
                <div style="background: #dcfce7; border-radius: 12px; padding: 20px; margin-top: 24px;">
                    <strong style="color: #16a34a;">✅ Este reto está completo</strong>
                    <p style="margin: 8px 0 0; color: #166534;">Puedes continuar con el siguiente reto o enviarlo junto con los demás para revisión.</p>
                </div>
            <?php elseif ($entry && 'enviado' === $entry->estado) : ?>
                <div style="background: #dbeafe; border-radius: 12px; padding: 20px; margin-top: 24px;">
                    <strong style="color: #1d4ed8;">📤 Reto enviado para revisión</strong>
                    <p style="margin: 8px 0 0; color: #1e40af;">Este reto está pendiente de revisión por el supervisor.</p>
                </div>
            <?php elseif ($entry && 'aprobado' === $entry->estado) : ?>
                <div style="background: #dcfce7; border-radius: 12px; padding: 20px; margin-top: 24px;">
                    <strong style="color: #16a34a;">🏆 ¡Reto aprobado!</strong>
                    <p style="margin: 8px 0 0; color: #166534;">Ganaste <?php echo esc_html($entry->puntaje); ?> puntos con este reto.</p>
                </div>
            <?php endif; ?>

        <?php elseif ('enviar' === $current_step['type']) : ?>
            <!-- Paso Final: Enviar Participación -->
            <?php $can_submit = $current_step['can_submit'] ?? false; ?>

            <div class="gnf-wizard__submit-panel<?php echo ! $can_submit ? ' gnf-wizard__submit-panel--blocked' : ''; ?>">
                <?php if ($can_submit) : ?>
                    <h3>🎉 ¡Todos los retos están completos!</h3>
                    <p>Has completado todos los eco retos matriculados. Revisa el resumen a continuación y envía tu participación para revisión.</p>
                <?php else : ?>
                    <h3>⏳ Completa todos los retos primero</h3>
                    <p>Debes completar todos los eco retos matriculados antes de poder enviar tu participación.</p>
                <?php endif; ?>

                <!-- Resumen de retos -->
                <div class="gnf-wizard__summary">
                    <?php foreach ($steps as $step) :
                        if ('reto' !== $step['type']) continue;
                        $is_complete = in_array($step['estado'], array('completo', 'enviado', 'aprobado'), true);
                    ?>
                        <div class="gnf-wizard__summary-item <?php echo $is_complete ? 'gnf-wizard__summary-item--complete' : 'gnf-wizard__summary-item--pending'; ?>">
                            <?php if (is_string($step['icon']) && strpos($step['icon'], 'http') === 0) : ?>
                                <span style="position:relative;flex-shrink:0;">
                                    <img src="<?php echo esc_url($step['icon']); ?>" alt="" style="width:36px;height:36px;object-fit:contain;border-radius:6px;display:block;" />
                                    <?php if ($is_complete) : ?>
                                        <span style="position:absolute;bottom:-4px;right:-4px;background:#22c55e;color:#fff;border-radius:50%;width:16px;height:16px;font-size:10px;display:flex;align-items:center;justify-content:center;">✓</span>
                                    <?php endif; ?>
                                </span>
                            <?php else : ?>
                                <span style="font-size:24px;flex-shrink:0;"><?php echo $is_complete ? '✅' : esc_html($step['icon']); ?></span>
                            <?php endif; ?>
                            <div>
                                <strong><?php echo esc_html($step['title']); ?></strong>
                                <small style="display: block; opacity: 0.8;">
                                    <?php echo esc_html(gnf_get_estado_label($step['estado'])); ?>
                                </small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($can_submit) : ?>
                    <button type="button"
                        class="gnf-btn gnf-wizard-enviar-participacion"
                        data-centro-id="<?php echo esc_attr($centro_id); ?>"
                        data-anio="<?php echo esc_attr($anio); ?>"
                        style="background: #fff; color: #369484; font-size: 18px; padding: 16px 32px; margin-top: 24px;">
                        📤 Enviar Participación para Revisión
                    </button>
                <?php else : ?>
                    <p style="margin-top: 24px; opacity: 0.7;">
                        Vuelve a los pasos anteriores para completar los retos pendientes.
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Navegación -->
        <div class="gnf-wizard__actions">
            <div class="gnf-wizard__actions-left">
                <?php if ($paso_actual > 1) : ?>
                    <a href="<?php echo esc_url(add_query_arg('paso', $paso_actual - 1)); ?>" class="gnf-btn gnf-btn--ghost">
                        ← Paso anterior
                    </a>
                <?php endif; ?>
            </div>
            <div class="gnf-wizard__actions-right">
                <?php if ($paso_actual < $total_steps) :
                    $next_step = $steps[$paso_actual] ?? null;
                    $next_locked = $next_step && 'bloqueado' === ($next_step['estado'] ?? '');
                ?>
                    <a href="<?php echo esc_url(add_query_arg('paso', $paso_actual + 1)); ?>"
                        class="gnf-btn <?php echo $next_locked ? 'gnf-btn--ghost' : ''; ?>"
                        <?php echo $next_locked ? 'aria-disabled="true" onclick="return false;"' : ''; ?>>
                        Siguiente paso →
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Botón de ayuda flotante -->
    <div style="position: fixed; bottom: 24px; right: 24px;">
        <button type="button" class="gnf-btn gnf-btn--ghost gnf-wizard-ayuda" style="border-radius: 50%; width: 56px; height: 56px; padding: 0; font-size: 24px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
            ❓
        </button>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        var wizardRoot = document.querySelector('.gnf-wizard');
        var wizardAnio = wizardRoot ? wizardRoot.dataset.anio : '';

        // Finalizar reto
        document.querySelectorAll('.gnf-wizard-finalizar-reto').forEach(function(btn) {
            btn.addEventListener('click', function() {
                if (this.disabled) return;

                var entryId = this.dataset.entryId;
                if (!entryId) return;

                this.disabled = true;
                this.textContent = 'Procesando...';

                fetch(gnfData.ajaxUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: 'action=gnf_finalizar_reto&nonce=' + gnfData.nonce + '&entry_id=' + entryId + '&anio=' + encodeURIComponent(wizardAnio || '')
                    })
                    .then(function(res) {
                        return res.json();
                    })
                    .then(function(data) {
                        if (data.success) {
                            alert('✅ ' + data.data.message);
                            location.reload();
                        } else {
                            alert('❌ ' + (data.data || 'Error al finalizar reto'));
                            btn.disabled = false;
                            btn.textContent = '✅ Finalizar Reto';
                        }
                    })
                    .catch(function() {
                        alert('❌ Error de conexión');
                        btn.disabled = false;
                        btn.textContent = '✅ Finalizar Reto';
                    });
            });
        });

        // Enviar participación
        document.querySelectorAll('.gnf-wizard-enviar-participacion').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var centroId = this.dataset.centroId;
                var anio = this.dataset.anio || wizardAnio || '';
                if (!centroId) return;

                if (!confirm('¿Estás seguro de enviar tu participación? Una vez enviada, será revisada por el supervisor.')) {
                    return;
                }

                this.disabled = true;
                this.textContent = 'Enviando...';

                fetch(gnfData.ajaxUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: 'action=gnf_enviar_participacion&nonce=' + gnfData.nonce + '&centro_id=' + centroId + '&anio=' + encodeURIComponent(anio)
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

        // Botón de ayuda
        document.querySelectorAll('.gnf-wizard-ayuda').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var helpContent = '<h3>¿Necesitas ayuda?</h3>' +
                    '<p>Aquí encontrarás recursos para completar tu participación:</p>' +
                    '<ul>' +
                    '<li><a href="https://www.youtube.com/@movimientoguardianes" target="_blank">📺 Canal de YouTube - Tutoriales</a></li>' +
                    '<li><a href="https://movimientoguardianes.org" target="_blank">🌐 Sitio web oficial</a></li>' +
                    '<li><a href="mailto:info@movimientoguardianes.org">📧 Contacto por correo</a></li>' +
                    '</ul>';

                var modal = document.createElement('div');
                modal.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;z-index:9999;';
                modal.innerHTML = '<div style="background:#fff;border-radius:16px;padding:32px;max-width:400px;margin:20px;"><button onclick="this.parentElement.parentElement.remove()" style="float:right;background:none;border:none;font-size:24px;cursor:pointer;">×</button>' + helpContent + '</div>';
                document.body.appendChild(modal);
            });
        });
    });
</script>
