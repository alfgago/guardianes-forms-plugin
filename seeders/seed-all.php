<?php

/**
 * Master Seeder - Ejecuta todos los seeders en orden.
 *
 * Ejecutar desde WP-CLI:
 *   wp eval-file wp-content/plugins/guardianes-formularios/seeders/seed-all.php
 *   wp eval-file wp-content/plugins/guardianes-formularios/seeders/seed-all.php -- --clean
 *   wp eval-file wp-content/plugins/guardianes-formularios/seeders/seed-all.php -- --fresh
 *
 * O via navegador:
 *   ?gnf_seed_all=1&gnf_seed_key=TU_CLAVE
 *   ?gnf_seed_all=1&gnf_seed_key=TU_CLAVE&clean=1  (solo limpiar)
 *   ?gnf_seed_all=1&gnf_seed_key=TU_CLAVE&fresh=1  (limpiar y re-sembrar)
 *
 * @package GuardianesFormularios
 */

if (! defined('ABSPATH')) {
    $wp_load_paths = array(
        dirname(__FILE__) . '/../../../../wp-load.php',
        dirname(__FILE__) . '/../../../wp-load.php',
    );

    $loaded = false;
    foreach ($wp_load_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $loaded = true;
            break;
        }
    }

    if (! $loaded) {
        die('Error: No se pudo cargar WordPress. Ejecuta desde WP-CLI.');
    }
}

/**
 * Limpia todos los datos creados por los seeders.
 *
 * @param bool $dry_run Modo simulación.
 * @return array Estadísticas de limpieza.
 */
function gnf_cleanup_all_seeded_data($dry_run = false)
{
    global $wpdb;

    $mode = defined('WP_CLI') && WP_CLI ? 'cli' : 'web';

    $log = function ($message, $type = 'info') use ($mode) {
        if ('cli' === $mode) {
            $colors = array(
                'info'    => '',
                'success' => "\033[32m",
                'warning' => "\033[33m",
                'error'   => "\033[31m",
                'header'  => "\033[1;36m",
            );
            $reset = "\033[0m";
            echo $colors[$type] . $message . $reset . "\n";
        } else {
            $styles = array(
                'info'    => 'color: #333;',
                'success' => 'color: green; font-weight: bold;',
                'warning' => 'color: orange;',
                'error'   => 'color: red; font-weight: bold;',
                'header'  => 'color: #dc2626; font-weight: bold; font-size: 18px; margin-top: 20px;',
            );
            echo '<div style="' . $styles[$type] . ' margin: 5px 0;">' . $message . '</div>';
        }
    };

    $stats = array(
        'wpforms'          => 0,
        'retos'            => 0,
        'centros'          => 0,
        'reto_entries'     => 0,
        'matriculas'       => 0,
        'notificaciones'   => 0,
        'regions'          => 0,
        'docentes'         => 0,
        'supervisores'     => 0,
        'comite'           => 0,
        'admins_demo'      => 0,
    );

    $log('╔══════════════════════════════════════════════════════════════╗', 'header');
    $log('║     🧹 LIMPIEZA DE DATOS SEEDED                              ║', 'header');
    $log('╚══════════════════════════════════════════════════════════════╝', 'header');
    $log('');

    if ($dry_run) {
        $log('⚠️  MODO DRY-RUN: No se eliminarán registros reales', 'warning');
        $log('');
    }

    // ═══════════════════════════════════════════════════════════════
    // 1. Eliminar formularios WPForms creados por el plugin
    // ═══════════════════════════════════════════════════════════════
    $log('─── Eliminando formularios WPForms ───', 'header');

    $wpforms = get_posts(array(
        'post_type'      => 'wpforms',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ));

    $stats['wpforms'] = count($wpforms);

    if (! empty($wpforms)) {
        foreach ($wpforms as $form_id) {
            if (! $dry_run) {
                wp_delete_post($form_id, true);
            }
        }
        $log("  ✓ Eliminados {$stats['wpforms']} formularios WPForms", 'success');
    } else {
        $log('  ○ No hay formularios WPForms para eliminar', 'info');
    }

    // ═══════════════════════════════════════════════════════════════
    // 2. Eliminar Retos (CPT)
    // ═══════════════════════════════════════════════════════════════
    $log('─── Eliminando Retos ───', 'header');

    $retos = get_posts(array(
        'post_type'      => 'reto',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ));

    $stats['retos'] = count($retos);

    if (! empty($retos)) {
        foreach ($retos as $reto_id) {
            if (! $dry_run) {
                wp_delete_post($reto_id, true);
            }
        }
        $log("  ✓ Eliminados {$stats['retos']} retos", 'success');
    } else {
        $log('  ○ No hay retos para eliminar', 'info');
    }

    // ═══════════════════════════════════════════════════════════════
    // 3. Eliminar Centros Educativos (CPT)
    // ═══════════════════════════════════════════════════════════════
    $log('─── Eliminando Centros Educativos ───', 'header');

    $centros = get_posts(array(
        'post_type'      => 'centro_educativo',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ));

    $stats['centros'] = count($centros);

    if (! empty($centros)) {
        foreach ($centros as $centro_id) {
            if (! $dry_run) {
                wp_delete_post($centro_id, true);
            }
        }
        $log("  ✓ Eliminados {$stats['centros']} centros educativos", 'success');
    } else {
        $log('  ○ No hay centros educativos para eliminar', 'info');
    }

    // ═══════════════════════════════════════════════════════════════
    // 4. Limpiar tablas custom
    // ═══════════════════════════════════════════════════════════════
    $log('─── Limpiando tablas de base de datos ───', 'header');

    // Tabla reto_entries
    $table_entries = $wpdb->prefix . 'gn_reto_entries';
    if ($wpdb->get_var("SHOW TABLES LIKE '{$table_entries}'") === $table_entries) {
        $stats['reto_entries'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_entries}");
        if (! $dry_run) {
            $wpdb->query("TRUNCATE TABLE {$table_entries}");
        }
        $log("  ✓ Limpiada tabla reto_entries ({$stats['reto_entries']} registros)", 'success');
    }

    // Tabla matriculas
    $table_matriculas = $wpdb->prefix . 'gn_matriculas';
    if ($wpdb->get_var("SHOW TABLES LIKE '{$table_matriculas}'") === $table_matriculas) {
        $stats['matriculas'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_matriculas}");
        if (! $dry_run) {
            $wpdb->query("TRUNCATE TABLE {$table_matriculas}");
        }
        $log("  ✓ Limpiada tabla matriculas ({$stats['matriculas']} registros)", 'success');
    }

    // Tabla notificaciones
    $table_notif = $wpdb->prefix . 'gn_notificaciones';
    if ($wpdb->get_var("SHOW TABLES LIKE '{$table_notif}'") === $table_notif) {
        $stats['notificaciones'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_notif}");
        if (! $dry_run) {
            $wpdb->query("TRUNCATE TABLE {$table_notif}");
        }
        $log("  ✓ Limpiada tabla notificaciones ({$stats['notificaciones']} registros)", 'success');
    }

    // ═══════════════════════════════════════════════════════════════
    // 5. Eliminar términos de taxonomía gn_region
    // ═══════════════════════════════════════════════════════════════
    $log('─── Eliminando Direcciones Regionales ───', 'header');

    $regions = get_terms(array(
        'taxonomy'   => 'gn_region',
        'hide_empty' => false,
        'fields'     => 'ids',
    ));

    if (! is_wp_error($regions) && ! empty($regions)) {
        $stats['regions'] = count($regions);
        foreach ($regions as $term_id) {
            if (! $dry_run) {
                wp_delete_term($term_id, 'gn_region');
            }
        }
        $log("  ✓ Eliminadas {$stats['regions']} direcciones regionales", 'success');
    } else {
        $log('  ○ No hay direcciones regionales para eliminar', 'info');
    }

    // ═══════════════════════════════════════════════════════════════
    // 6. Eliminar usuarios demo de prueba
    // ═══════════════════════════════════════════════════════════════
    $log('─── Eliminando usuarios de prueba ───', 'header');

    // Docentes de prueba (creados por seed-sample-data)
    $docentes = get_users(array(
        'role'       => 'docente',
        'meta_key'   => 'gnf_seeded',
        'meta_value' => '1',
        'fields'     => 'ids',
    ));

    $stats['docentes'] = count($docentes);

    if (! empty($docentes)) {
        foreach ($docentes as $user_id) {
            if (! $dry_run) {
                wp_delete_user($user_id);
            }
        }
        $log("  ✓ Eliminados {$stats['docentes']} docentes de prueba", 'success');
    } else {
        $log('  ○ No hay docentes de prueba para eliminar', 'info');
    }

    // Supervisores de prueba
    $supervisores = get_users(array(
        'role'       => 'supervisor',
        'meta_key'   => 'gnf_seeded',
        'meta_value' => '1',
        'fields'     => 'ids',
    ));

    $stats['supervisores'] = count($supervisores);

    if (! empty($supervisores)) {
        foreach ($supervisores as $user_id) {
            if (! $dry_run) {
                wp_delete_user($user_id);
            }
        }
        $log("  ✓ Eliminados {$stats['supervisores']} supervisores de prueba", 'success');
    } else {
        $log('  ○ No hay supervisores de prueba para eliminar', 'info');
    }

    // Comite BAE de prueba
    $comite = get_users(array(
        'role'       => 'comite_bae',
        'meta_key'   => 'gnf_seeded',
        'meta_value' => '1',
        'fields'     => 'ids',
    ));
    $stats['comite'] = count($comite);
    if (! empty($comite)) {
        foreach ($comite as $user_id) {
            if (! $dry_run) {
                wp_delete_user($user_id);
            }
        }
        $log("  ✓ Eliminados {$stats['comite']} comite de prueba", 'success');
    } else {
        $log('  ○ No hay usuarios comite de prueba para eliminar', 'info');
    }

    // Administradores demo de prueba
    $admins_demo = get_users(array(
        'role'       => 'administrator',
        'meta_key'   => 'gnf_seeded',
        'meta_value' => '1',
        'fields'     => 'ids',
    ));
    $stats['admins_demo'] = count($admins_demo);
    if (! empty($admins_demo)) {
        foreach ($admins_demo as $user_id) {
            if (! $dry_run) {
                wp_delete_user($user_id);
            }
        }
        $log("  ✓ Eliminados {$stats['admins_demo']} admin demo", 'success');
    } else {
        $log('  ○ No hay admin demo para eliminar', 'info');
    }

    // ═══════════════════════════════════════════════════════════════
    // 7. Limpiar opciones ACF
    // ═══════════════════════════════════════════════════════════════
    $log('─── Limpiando opciones de configuración ───', 'header');

    if (! $dry_run) {
        // Limpiar opciones legacy de matrícula (WPForms).
        delete_option('gnf_wpforms_matricula_form_id');
        delete_option('options_wpforms_matricula_form_id');
    }
    $log('  ✓ Opciones de configuración limpiadas', 'success');

    // ═══════════════════════════════════════════════════════════════
    // 8. Limpiar caché de transients
    // ═══════════════════════════════════════════════════════════════
    $log('─── Limpiando caché ───', 'header');

    if (! $dry_run) {
        // Eliminar transients del plugin
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_gnf_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_gnf_%'");

        // Limpiar object cache si está disponible
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
    }
    $log('  ✓ Caché limpiada', 'success');

    $log('');
    $log('═══════════════════════════════════════════════════════════════', 'header');
    $log('🧹 LIMPIEZA COMPLETADA', 'success');
    $log('═══════════════════════════════════════════════════════════════', 'header');
    $log('');

    $total = array_sum($stats);
    $log("Total de elementos procesados: {$total}", 'info');
    $log("  • WPForms: {$stats['wpforms']}", 'info');
    $log("  • Retos: {$stats['retos']}", 'info');
    $log("  • Centros: {$stats['centros']}", 'info');
    $log("  • Entradas de retos: {$stats['reto_entries']}", 'info');
    $log("  • Matrículas: {$stats['matriculas']}", 'info');
    $log("  • Notificaciones: {$stats['notificaciones']}", 'info');
    $log("  • Regiones: {$stats['regions']}", 'info');
    $log("  • Docentes: {$stats['docentes']}", 'info');
    $log("  • Supervisores: {$stats['supervisores']}", 'info');
    $log("  • Comite BAE: {$stats['comite']}", 'info');
    $log("  • Admin demo: {$stats['admins_demo']}", 'info');
    $log('');

    return $stats;
}

/**
 * Ejecuta todos los seeders.
 */
function gnf_run_all_seeders($dry_run = false)
{
    $mode = defined('WP_CLI') && WP_CLI ? 'cli' : 'web';

    $log = function ($message, $type = 'info') use ($mode) {
        if ('cli' === $mode) {
            $colors = array(
                'info'    => '',
                'success' => "\033[32m",
                'warning' => "\033[33m",
                'error'   => "\033[31m",
                'header'  => "\033[1;36m",
            );
            $reset = "\033[0m";
            echo $colors[$type] . $message . $reset . "\n";
        } else {
            $styles = array(
                'info'    => 'color: #333;',
                'success' => 'color: green; font-weight: bold;',
                'warning' => 'color: orange;',
                'error'   => 'color: red; font-weight: bold;',
                'header'  => 'color: #0066cc; font-weight: bold; font-size: 18px; margin-top: 20px;',
            );
            echo '<div style="' . $styles[$type] . ' margin: 5px 0;">' . $message . '</div>';
        }
    };

    $log('╔══════════════════════════════════════════════════════════════╗', 'header');
    $log('║     🌱 GUARDIANES FORMULARIOS - MASTER SEEDER                ║', 'header');
    $log('╚══════════════════════════════════════════════════════════════╝', 'header');
    $log('');

    if ($dry_run) {
        $log('⚠️  MODO DRY-RUN: No se crearán registros reales', 'warning');
        $log('');
    }

    // Verificar dependencias.
    $log('Verificando dependencias...', 'info');

    if (! function_exists('wpforms')) {
        $log('❌ WPForms no está activo. Instala y activa WPForms primero.', 'error');
        return false;
    }
    $log('  ✓ WPForms activo', 'success');

    if (! function_exists('acf_add_local_field_group')) {
        $log('⚠️  ACF Pro no detectado. Algunos campos pueden no guardarse correctamente.', 'warning');
    } else {
        $log('  ✓ ACF Pro activo', 'success');
    }

    $log('');

    // ═══════════════════════════════════════════════════════════════
    // PASO 0: Configuración base (año activo = 2026)
    // ═══════════════════════════════════════════════════════════════
    $log('═══════════════════════════════════════════════════════════════', 'header');
    $log('PASO 0: Configuración base', 'header');
    $log('═══════════════════════════════════════════════════════════════', 'header');
    if (! $dry_run) {
        if (function_exists('update_field')) {
            update_field('anio_actual', 2026, 'option');
        }
        update_option('options_anio_actual', 2026);
        $log('  ✓ Año activo configurado: 2026', 'success');
    } else {
        $log('  [DRY-RUN] Se configuraría año activo = 2026', 'info');
    }
    $log('');

    // ═══════════════════════════════════════════════════════════════
    // PASO 1: Crear tablas si no existen
    // ═══════════════════════════════════════════════════════════════
    $log('═══════════════════════════════════════════════════════════════', 'header');
    $log('PASO 1: Verificando/creando tablas de base de datos', 'header');
    $log('═══════════════════════════════════════════════════════════════', 'header');

    if (function_exists('gnf_create_tables')) {
        if (! $dry_run) {
            gnf_create_tables();
            $log('  ✓ Tablas verificadas/creadas', 'success');
        } else {
            $log('  [DRY-RUN] Se verificarían las tablas', 'info');
        }
    } else {
        $log('  ⚠️ Función gnf_create_tables no encontrada', 'warning');
    }

    $log('');

    // ═══════════════════════════════════════════════════════════════
    // PASO 2: Importar Retos (+ WPForms + Checklists)
    // ═══════════════════════════════════════════════════════════════
    $log('═══════════════════════════════════════════════════════════════', 'header');
    $log('PASO 2: Importando Retos con formularios WPForms', 'header');
    $log('═══════════════════════════════════════════════════════════════', 'header');

    require_once dirname(__FILE__) . '/seed-retos.php';
    gnf_run_retos_seeder($dry_run);

    $log('');

    // ═══════════════════════════════════════════════════════════════
    // PASO 3: Crear datos demo (usuarios sobre centro importado)
    // ═══════════════════════════════════════════════════════════════
    $log('═══════════════════════════════════════════════════════════════', 'header');
    $log('PASO 3: Creando datos demo (docente/supervisor/comite/admin)', 'header');
    $log('═══════════════════════════════════════════════════════════════', 'header');

    require_once dirname(__FILE__) . '/seed-sample-data.php';
    gnf_run_sample_data_seeder($dry_run);

    $log('');

    // ═══════════════════════════════════════════════════════════════
    // PASO 4: Validacion 2026 de consistencia (puntaje BD vs ACF)
    // ═══════════════════════════════════════════════════════════════
    $log('═══════════════════════════════════════════════════════════════', 'header');
    $log('PASO 4: Validando consistencia 2026', 'header');
    $log('═══════════════════════════════════════════════════════════════', 'header');
    if (function_exists('gnf_validate_multi_year_consistency')) {
        $validation = gnf_validate_multi_year_consistency(array(2026));
        $log('  ✓ Validacion 2026 ejecutada', 'success');
        foreach ((array) ($validation['years'] ?? array()) as $row) {
            $log(
                '  • Año ' . intval($row['year'] ?? 0) .
                    ' | centros actividad=' . intval($row['centers_with_activity'] ?? 0) .
                    ', desajuste puntaje=' . intval($row['puntaje_mismatch'] ?? 0),
                'info'
            );
        }
    } else {
        $log('  ⚠️  Función de validación multi-año no disponible', 'warning');
    }

    $log('');

    // ═══════════════════════════════════════════════════════════════
    // RESUMEN FINAL
    // ═══════════════════════════════════════════════════════════════
    $log('═══════════════════════════════════════════════════════════════', 'header');
    $log('✅ PROCESO COMPLETADO', 'success');
    $log('═══════════════════════════════════════════════════════════════', 'header');

    // Contar resultados.
    global $wpdb;
    $retos_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'reto' AND post_status = 'publish'");
    $forms_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'wpforms' AND post_status = 'publish'");

    $log('');
    $log('Resumen del sistema:', 'info');
    $log("  • Retos publicados: {$retos_count}", 'info');
    $log("  • Formularios WPForms: {$forms_count}", 'info');
    $log('  • Matrícula frontend: nativa (sin WPForms)', 'info');

    $log('');
    $log('Próximos pasos:', 'info');
    $log('  1. Ve a WPForms para revisar los formularios creados', 'info');
    $log('  2. Configura las notificaciones de email si es necesario', 'info');
    $log('  3. Agrega el shortcode [gnf_panel_docente] a una página', 'info');
    $log('  4. Agrega el shortcode [gnf_panel_supervisor] para supervisores', 'info');
    $log('');

    return true;
}

// Ejecución desde CLI.
if (defined('WP_CLI') && WP_CLI) {
    $args    = isset($GLOBALS['argv']) ? $GLOBALS['argv'] : array();
    $dry_run = in_array('--dry-run', $args, true);
    $clean   = in_array('--clean', $args, true);
    $fresh   = in_array('--fresh', $args, true);

    if ($clean) {
        // Solo limpiar
        gnf_cleanup_all_seeded_data($dry_run);
    } elseif ($fresh) {
        // Limpiar y luego sembrar
        gnf_cleanup_all_seeded_data($dry_run);
        echo "\n";
        gnf_run_all_seeders($dry_run);
    } else {
        // Solo sembrar
        gnf_run_all_seeders($dry_run);
    }
}

// Ejecución desde navegador.
if (isset($_GET['gnf_seed_all']) && current_user_can('manage_options')) {
    $seed_key = defined('GNF_SEED_KEY') ? GNF_SEED_KEY : 'bandera2026';

    if (! isset($_GET['gnf_seed_key']) || $_GET['gnf_seed_key'] !== $seed_key) {
        wp_die('Clave de seguridad inválida.');
    }

    $dry_run = isset($_GET['dry_run']);
    $clean   = isset($_GET['clean']);
    $fresh   = isset($_GET['fresh']);

    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Master Seeder</title>';
    echo '<style>
        body { font-family: "Fira Code", monospace; padding: 20px; background: #1a1a2e; color: #eee; }
        .container { background: #16213e; padding: 24px; border-radius: 12px; max-width: 900px; margin: 0 auto; }
        h1 { color: #4ade80; margin-bottom: 24px; }
        h1.clean { color: #f87171; }
        a { color: #60a5fa; }
        .btn { display: inline-block; padding: 12px 24px; margin: 8px; border-radius: 8px; text-decoration: none; font-weight: bold; }
        .btn-seed { background: #22c55e; color: #000; }
        .btn-clean { background: #ef4444; color: #fff; }
        .btn-fresh { background: #f59e0b; color: #000; }
        .btn-back { background: #3b82f6; color: #fff; }
    </style></head><body>';
    echo '<div class="container">';

    if ($clean) {
        echo '<h1 class="clean">🧹 Limpieza de Datos - Guardianes Formularios</h1>';
        gnf_cleanup_all_seeded_data($dry_run);
    } elseif ($fresh) {
        echo '<h1 class="clean">🔄 Fresh Seed - Guardianes Formularios</h1>';
        gnf_cleanup_all_seeded_data($dry_run);
        echo '<hr style="border-color: #333; margin: 24px 0;">';
        echo '<h1>🌱 Re-sembrando datos...</h1>';
        gnf_run_all_seeders($dry_run);
    } else {
        echo '<h1>🌱 Master Seeder - Guardianes Formularios</h1>';
        gnf_run_all_seeders($dry_run);
    }

    $base_url = admin_url('admin.php?page=gnf-admin');
    $seed_url = add_query_arg(array('gnf_seed_all' => 1, 'gnf_seed_key' => $seed_key), site_url());

    echo '<div style="margin-top: 24px; padding-top: 24px; border-top: 1px solid #333;">';
    echo '<p><strong>Acciones:</strong></p>';
    echo '<a class="btn btn-back" href="' . esc_url($base_url) . '">← Volver al Panel</a>';
    echo '<a class="btn btn-seed" href="' . esc_url($seed_url) . '">🌱 Sembrar</a>';
    echo '<a class="btn btn-clean" href="' . esc_url($seed_url . '&clean=1') . '">🧹 Limpiar</a>';
    echo '<a class="btn btn-fresh" href="' . esc_url($seed_url . '&fresh=1') . '">🔄 Fresh (Limpiar + Sembrar)</a>';
    echo '</div>';

    echo '<p style="margin-top: 24px;"><a href="' . admin_url('edit.php?post_type=reto') . '">→ Ver Retos</a> | ';
    echo '<a href="' . admin_url('admin.php?page=wpforms-overview') . '">→ Ver WPForms</a> | ';
    echo '<a href="' . admin_url('edit.php?post_type=centro_educativo') . '">→ Ver Centros</a></p>';
    echo '</div></body></html>';
    exit;
}

