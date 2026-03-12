<?php

/**
 * Seeder de datos demo para Guardianes Formularios.
 *
 * Crea exactamente 1 usuario por tipo con emails faciles de identificar:
 * - demo.docente@movimientoguardianes.org
 * - demo.supervisor@movimientoguardianes.org
 * - demo.comite@movimientoguardianes.org
 * - demo.admin@movimientoguardianes.org
 *
 * Todos se asocian a 1 centro importado desde el CSV MEP.
 *
 * Ejecutar desde WP-CLI:
 *   wp eval-file wp-content/plugins/guardianes-formularios/seeders/seed-sample-data.php
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

class GNF_Sample_Data_Seeder
{
    private $mode;
    private $anio;
    private $demo_password = 'demo123';

    private $demo_users = array(
        'docente' => array(
            'email'   => 'demo.docente@movimientoguardianes.org',
            'nombre'  => 'Demo Docente',
            'role'    => 'docente',
            'cargo'   => 'Docente Guia',
            'telefono'=> '8888-0001',
        ),
        'supervisor' => array(
            'email'   => 'demo.supervisor@movimientoguardianes.org',
            'nombre'  => 'Demo Supervisor',
            'role'    => 'supervisor',
            'cargo'   => 'Supervisor Regional',
            'telefono'=> '8888-0002',
        ),
        'comite' => array(
            'email'   => 'demo.comite@movimientoguardianes.org',
            'nombre'  => 'Demo Comite BAE',
            'role'    => 'comite_bae',
            'cargo'   => 'Comite Regional',
            'telefono'=> '8888-0003',
        ),
        'admin' => array(
            'email'   => 'demo.admin@movimientoguardianes.org',
            'nombre'  => 'Demo Administrador',
            'role'    => 'administrator',
            'cargo'   => 'Administrador Demo',
            'telefono'=> '8888-0004',
        ),
    );

    public function __construct()
    {
        $this->mode = defined('WP_CLI') && WP_CLI ? 'cli' : 'web';
        $this->anio = function_exists('gnf_get_active_year') ? gnf_get_active_year() : (int) date('Y');
    }

    private function log($message, $type = 'info')
    {
        if ('cli' === $this->mode) {
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
                'header'  => 'color: #0066cc; font-weight: bold; font-size: 16px;',
            );
            echo '<div style="' . $styles[$type] . ' margin: 5px 0;">' . esc_html($message) . '</div>';
        }
    }

    public function run($dry_run = false)
    {
        $this->log('═══════════════════════════════════════════════════════════════', 'header');
        $this->log('CREANDO USUARIOS DEMO - Ano ' . $this->anio, 'header');
        $this->log('═══════════════════════════════════════════════════════════════', 'header');
        $this->log('');

        if ($dry_run) {
            $this->log('*** MODO DRY-RUN: No se crearan registros ***', 'warning');
            $this->log('');
        }

        $retos = $this->get_available_retos();
        $this->log('Retos disponibles: ' . count($retos), 'info');

        $centro_info = $this->find_imported_centro();
        if (empty($centro_info['centro_id'])) {
            $this->log('No se encontro ningun centro importado. Ejecuta el seeder de escuelas primero.', 'error');
            return false;
        }

        $this->log('Centro demo seleccionado: ' . $centro_info['centro_title'] . ' (ID: ' . $centro_info['centro_id'] . ')', 'success');
        if (! empty($centro_info['region_id'])) {
            $this->log('Region asociada: ' . $centro_info['region_id'], 'info');
        }
        $this->log('');

        $created_users = $this->create_demo_users($centro_info, $dry_run);

        if (! $dry_run && ! empty($created_users['docente']) && ! empty($retos)) {
            $this->create_demo_matricula($centro_info['centro_id'], $created_users['docente'], $retos);
        }

        $this->log('');
        $this->log('═══════════════════════════════════════════════════════════════', 'header');
        $this->log('USUARIOS DEMO LISTOS', 'success');
        $this->log('═══════════════════════════════════════════════════════════════', 'header');
        $this->log('Password para todos: ' . $this->demo_password, 'info');
        $this->log('  - demo.docente@movimientoguardianes.org', 'info');
        $this->log('  - demo.supervisor@movimientoguardianes.org', 'info');
        $this->log('  - demo.comite@movimientoguardianes.org', 'info');
        $this->log('  - demo.admin@movimientoguardianes.org', 'info');
        $this->log('');

        return true;
    }

    private function get_available_retos()
    {
        $query = new WP_Query(array(
            'post_type'      => 'reto',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ));

        return is_array($query->posts) ? $query->posts : array();
    }

    private function find_imported_centro()
    {
        $query = new WP_Query(array(
            'post_type'      => 'centro_educativo',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'fields'         => 'ids',
            'meta_query'     => array(
                array(
                    'key'     => 'codigo_mep',
                    'compare' => 'EXISTS',
                ),
                array(
                    'key'     => 'codigo_mep',
                    'value'   => '',
                    'compare' => '!=',
                ),
            ),
        ));

        $centro_id = ! empty($query->posts[0]) ? (int) $query->posts[0] : 0;
        if (! $centro_id) {
            $fallback = get_posts(array(
                'post_type'      => 'centro_educativo',
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'orderby'        => 'ID',
                'order'          => 'ASC',
                'fields'         => 'ids',
            ));
            $centro_id = ! empty($fallback[0]) ? (int) $fallback[0] : 0;
        }

        if (! $centro_id) {
            return array();
        }

        $region_id = (int) get_post_meta($centro_id, 'region', true);
        if (! $region_id) {
            $terms = wp_get_post_terms($centro_id, 'gn_region', array('fields' => 'ids'));
            if (! empty($terms[0])) {
                $region_id = (int) $terms[0];
                update_post_meta($centro_id, 'region', $region_id);
            }
        }

        return array(
            'centro_id'    => $centro_id,
            'centro_title' => get_the_title($centro_id),
            'region_id'    => $region_id,
        );
    }

    private function create_demo_users($centro_info, $dry_run)
    {
        $result = array();
        $centro_id = (int) $centro_info['centro_id'];
        $region_id = (int) $centro_info['region_id'];

        foreach ($this->demo_users as $key => $user_data) {
            $user_id = $this->create_or_update_demo_user($user_data, $centro_id, $region_id, $dry_run);
            if ($user_id) {
                $result[$key] = $user_id;
            }
        }

        return $result;
    }

    private function create_or_update_demo_user($data, $centro_id, $region_id, $dry_run)
    {
        $email = $data['email'];
        $role  = $data['role'];

        if ($dry_run) {
            $this->log('  [DRY-RUN] Se crearia/actualizaria usuario ' . $email . ' (' . $role . ')', 'success');
            return 0;
        }

        $existing = get_user_by('email', $email);
        $user_id = 0;

        if ($existing) {
            $user_id = (int) $existing->ID;
            $this->log('  Actualizando usuario existente: ' . $email . ' (ID: ' . $user_id . ')', 'warning');
        } else {
            $created = wp_create_user($email, $this->demo_password, $email);
            if (is_wp_error($created)) {
                $this->log('  Error creando ' . $email . ': ' . $created->get_error_message(), 'error');
                return 0;
            }
            $user_id = (int) $created;
            $this->log('  Usuario creado: ' . $email . ' (ID: ' . $user_id . ')', 'success');
        }

        $name_parts = explode(' ', trim($data['nombre']));
        $first_name = ! empty($name_parts[0]) ? $name_parts[0] : $data['nombre'];
        $last_name  = count($name_parts) > 1 ? implode(' ', array_slice($name_parts, 1)) : '';

        wp_update_user(array(
            'ID'           => $user_id,
            'display_name' => $data['nombre'],
            'first_name'   => $first_name,
            'last_name'    => $last_name,
            'role'         => $role,
        ));
        wp_set_password($this->demo_password, $user_id);

        update_user_meta($user_id, 'gnf_seeded', '1');
        update_user_meta($user_id, 'gnf_demo_user', '1');
        update_user_meta($user_id, 'gnf_cargo', $data['cargo']);
        update_user_meta($user_id, 'gnf_telefono', $data['telefono']);

        if ('docente' === $role) {
            update_user_meta($user_id, 'centro_educativo_id', $centro_id);
            update_user_meta($user_id, 'centro_solicitado', $centro_id);
            update_user_meta($user_id, 'gnf_docente_status', 'activo');
            update_user_meta($user_id, 'docente_cargo', $data['cargo']);
            update_user_meta($user_id, 'docente_telefono', $data['telefono']);

            if (function_exists('update_field')) {
                $docentes_asociados = (array) get_field('docentes_asociados', $centro_id);
                if (! in_array($user_id, $docentes_asociados, true)) {
                    $docentes_asociados[] = $user_id;
                    update_field('docentes_asociados', $docentes_asociados, $centro_id);
                }
            }
        } elseif ('supervisor' === $role || 'comite_bae' === $role) {
            if ($region_id > 0) {
                update_user_meta($user_id, 'region', $region_id);
                update_user_meta($user_id, 'gnf_region', $region_id);
            }
            update_user_meta($user_id, 'gnf_supervisor_status', 'activo');
            update_user_meta($user_id, 'gnf_rol_solicitado', $role);
        }

        return $user_id;
    }

    private function create_demo_matricula($centro_id, $user_id, $all_retos)
    {
        global $wpdb;

        $meta_estrellas = 3;
        $comite_estudiantes = 10;
        $num_retos = min(count($all_retos), 5);
        $retos_seleccionados = array_slice($all_retos, 0, $num_retos);

        gnf_set_centro_anual_data(
            $centro_id,
            $this->anio,
            array(
                'retos_seleccionados' => $retos_seleccionados,
                'meta_estrellas'      => $meta_estrellas,
                'comite_estudiantes'  => $comite_estudiantes,
                'estado_matricula'    => 'aprobada',
            )
        );

        $table = $wpdb->prefix . 'gn_matriculas';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            $this->log('  Tabla de matriculas no existe. Ejecuta gnf_create_tables().', 'warning');
            return;
        }

        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$table} WHERE centro_id = %d AND anio = %d",
            $centro_id,
            $this->anio
        ));

        $matricula_data = array(
            'centro_id'           => $centro_id,
            'user_id'             => $user_id,
            'anio'                => $this->anio,
            'meta_estrellas'      => $meta_estrellas,
            'retos_seleccionados' => wp_json_encode($retos_seleccionados, JSON_UNESCAPED_UNICODE),
            'data'                => wp_json_encode(array(
                'bae_inscripcion_anterior' => 'Sí',
                'comite_estudiantes'       => $comite_estudiantes,
            ), JSON_UNESCAPED_UNICODE),
            'estado'              => 'aprobada',
            'updated_at'          => current_time('mysql'),
        );

        if ($existing) {
            $wpdb->update($table, $matricula_data, array('id' => $existing->id));
        } else {
            $matricula_data['created_at'] = current_time('mysql');
            $wpdb->insert($table, $matricula_data);
        }

        $this->log('  Matricula demo creada/actualizada con ' . $num_retos . ' retos.', 'success');
    }
}

function gnf_run_sample_data_seeder($dry_run = false)
{
    $seeder = new GNF_Sample_Data_Seeder();
    return $seeder->run($dry_run);
}

if (defined('WP_CLI') && WP_CLI) {
    $dry_run = in_array('--dry-run', $GLOBALS['argv'], true);
    gnf_run_sample_data_seeder($dry_run);
}

if (isset($_GET['gnf_seed_sample']) && current_user_can('manage_options')) {
    $seed_key = defined('GNF_SEED_KEY') ? GNF_SEED_KEY : 'bandera2026';

    if (! isset($_GET['gnf_seed_key']) || $_GET['gnf_seed_key'] !== $seed_key) {
        wp_die('Clave de seguridad invalida.');
    }

    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Datos Demo</title>';
    echo '<style>body{font-family:monospace;padding:20px;background:#1a1a2e;color:#eee;}</style></head><body>';
    echo '<div style="background:#16213e;padding:24px;border-radius:12px;max-width:800px;margin:0 auto;">';
    echo '<h1 style="color:#4ade80;">Datos Demo - Guardianes Formularios</h1>';

    $dry_run = isset($_GET['dry_run']);
    gnf_run_sample_data_seeder($dry_run);

    echo '</div></body></html>';
    exit;
}
