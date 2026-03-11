<?php

/**
 * Seeder de Retos para Guardianes Formularios.
 *
 * Ejecutar desde WP-CLI:
 *   wp eval-file wp-content/plugins/guardianes-formularios/seeders/seed-retos.php
 *
 * O acceder via navegador añadiendo ?gnf_seed_retos=1&gnf_seed_key=TU_CLAVE
 * (solo administradores logueados)
 *
 * @package GuardianesFormularios
 */

if (! defined('ABSPATH')) {
    // Cargar WordPress si se ejecuta desde CLI.
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
 * Clase para importar retos desde JSON.
 */
class GNF_Retos_Seeder
{
    /**
     * Mapeo de retos habilitados para 2026 => archivo JSON especifico.
     *
     * @return array<string,string>
     */
    public static function get_2026_reto_json_map()
    {
        return array(
            'agua-base'             => dirname(__FILE__) . '/preguntas_dir/2026/Eco-Reto-Agua-2026.json',
            'energias-limpias-base' => dirname(__FILE__) . '/preguntas_dir/2026/Eco-Reto-Electricidad-2026.json',
            'residuos-base'         => dirname(__FILE__) . '/preguntas_dir/2026/Eco-Reto-Residuos-2026.json',
            'ambiente-limpio'       => dirname(__FILE__) . '/preguntas_dir/2026/Eco-Reto-Limpiezas-2026.json',
            'siembra-de-arboles'    => dirname(__FILE__) . '/preguntas_dir/2026/Eco-Reto-Siembra-de-Arboles-2026.json',
            'meriendas-saludables'  => dirname(__FILE__) . '/preguntas_dir/2026/Eco-Reto-Eco-Lonchera-2026.json',
        );
    }


    /**
     * Ruta al archivo JSON con los datos.
     *
     * @var string
     */
    private $json_path;

    /**
     * Modo de ejecución (cli o web).
     *
     * @var string
     */
    private $mode;

    /**
     * Contador de retos creados.
     *
     * @var int
     */
    private $created = 0;

    /**
     * Contador de retos omitidos (ya existen).
     *
     * @var int
     */
    private $skipped = 0;

    /**
     * Contador de errores.
     *
     * @var int
     */
    private $errors = 0;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->json_path = dirname(__FILE__) . '/retos-data.json';
        $this->mode      = defined('WP_CLI') && WP_CLI ? 'cli' : 'web';
    }

    /**
     * Log de mensajes.
     *
     * @param string $message Mensaje a mostrar.
     * @param string $type    Tipo: info, success, warning, error.
     */
    private function log($message, $type = 'info')
    {
        if ('cli' === $this->mode) {
            $colors = array(
                'info'    => '',
                'success' => "\033[32m",
                'warning' => "\033[33m",
                'error'   => "\033[31m",
            );
            $reset  = "\033[0m";
            echo $colors[$type] . $message . $reset . "\n";
        } else {
            $styles = array(
                'info'    => 'color: #333;',
                'success' => 'color: green; font-weight: bold;',
                'warning' => 'color: orange;',
                'error'   => 'color: red; font-weight: bold;',
            );
            echo '<div style="' . $styles[$type] . ' margin: 5px 0;">' . esc_html($message) . '</div>';
        }
    }

    /**
     * Ejecuta el seeder.
     *
     * @param bool $dry_run Si es true, solo muestra lo que haría sin crear nada.
     */
    public function run($dry_run = false)
    {
        $this->log('=== Iniciando importación de Retos ===');

        if ($dry_run) {
            $this->log('*** MODO DRY-RUN: No se crearán registros ***', 'warning');
        }

        // Verificar que el archivo JSON existe.
        if (! file_exists($this->json_path)) {
            $this->log('Error: No se encontró el archivo retos-data.json', 'error');
            return false;
        }

        // Leer y decodificar JSON.
        $json_content = file_get_contents($this->json_path);
        $retos        = json_decode($json_content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log('Error: JSON inválido - ' . json_last_error_msg(), 'error');
            return false;
        }

        $this->log('Encontrados ' . count($retos) . ' retos para importar.');
        $this->log('');

        foreach ($retos as $index => $reto_data) {
            $this->process_reto($reto_data, $index + 1, $dry_run);
        }

        // Resumen final.
        $this->log('');
        $this->log('=== Resumen ===');
        $this->log("Creados: {$this->created}", 'success');
        $this->log("Omitidos (ya existían): {$this->skipped}", 'warning');
        $this->log("Errores: {$this->errors}", $this->errors > 0 ? 'error' : 'info');

        return true;
    }

    /**
     * Procesa un reto individual.
     *
     * @param array $data    Datos del reto.
     * @param int   $number  Número de orden.
     * @param bool  $dry_run Modo simulación.
     */
    private function process_reto($data, $number, $dry_run)
    {
        $titulo = sanitize_text_field($data['titulo']);

        $this->log("[{$number}] Procesando: {$titulo}");

        // Verificar si ya existe.
        $existing = get_posts(
            array(
                'post_type'      => 'reto',
                'post_status'    => 'any',
                'title'          => $titulo,
                'posts_per_page' => 1,
            )
        );

        if (! empty($existing)) {
            $existing_id = $existing[0]->ID;
            $this->log("    ⏭ Ya existe (ID: {$existing_id})", 'warning');

            // Marcar obligatorio en matrícula para los tres retos base (Agua, Energía, Residuos).
            if (! $dry_run && $this->is_reto_obligatorio_por_titulo($titulo)) {
                if (function_exists('update_field')) {
                    update_field('obligatorio_en_matricula', 1, $existing_id);
                } else {
                    update_post_meta($existing_id, 'obligatorio_en_matricula', '1');
                }
            }

            // Verificar si le falta algún formulario WPForms por año.
            if (! empty($data['preguntas_file'])) {
                $this->log("    🔄 Verificando formularios WPForms por año...", 'info');
                $this->create_wpform_and_checklist($data, $existing_id, $dry_run);
            }

            $this->skipped++;
            return;
        }

        if ($dry_run) {
            $this->log("    ✓ [DRY-RUN] Se crearía con {$data['puntos']} pts", 'success');
            $this->created++;
            return;
        }

        // Crear el post.
        $post_id = wp_insert_post(
            array(
                'post_title'  => $titulo,
                'post_type'   => 'reto',
                'post_status' => 'publish',
            )
        );

        if (is_wp_error($post_id)) {
            $this->log('    ✗ Error al crear: ' . $post_id->get_error_message(), 'error');
            $this->errors++;
            return;
        }

        // Actualizar campos ACF.
        if (function_exists('update_field')) {
            update_field('descripcion', $data['descripcion'], $post_id);
            update_field('color_del_reto', $data['color'], $post_id);
            update_field('tipos_evidencia_permitidos', array('foto', 'pdf', 'video'), $post_id);
            if ($this->is_reto_obligatorio_por_titulo($titulo)) {
                update_field('obligatorio_en_matricula', 1, $post_id);
            }
        } else {
            // Fallback a post meta directo.
            update_post_meta($post_id, 'descripcion', $data['descripcion']);
            update_post_meta($post_id, 'color_del_reto', $data['color']);
            if ($this->is_reto_obligatorio_por_titulo($titulo)) {
                update_post_meta($post_id, 'obligatorio_en_matricula', '1');
            }
        }

        // Descargar e importar PDF (stored per-year in repeater later).
        $pdf_attachment_id = 0;
        if (! empty($data['pdf_url'])) {
            $pdf_attachment_id = $this->import_file($data['pdf_url'], $post_id, 'Reto - ' . $titulo);
            if ($pdf_attachment_id) {
                $this->log('    📄 PDF importado (ID: ' . $pdf_attachment_id . ')');
            }
        }

        // Descargar e importar imagen (Featured Image + used per-year as icon).
        $icon_attachment_id = 0;
        if (! empty($data['imagen_url'])) {
            $icon_attachment_id = $this->import_image($data['imagen_url'], $post_id, $titulo);
            if ($icon_attachment_id) {
                set_post_thumbnail($post_id, $icon_attachment_id);
                $this->log('    📷 Imagen importada (ID: ' . $icon_attachment_id . ')');
            }
        }

        // Crear formulario WPForms y checklist desde preguntas_file.
        if (! empty($data['preguntas_file'])) {
            $this->create_wpform_and_checklist($data, $post_id, $dry_run, $pdf_attachment_id, $icon_attachment_id);
        }

        $this->log("    ✓ Creado exitosamente (ID: {$post_id})", 'success');
        $this->created++;
    }

    /**
     * Indica si el reto es uno de los tres obligatorios en matrícula (Agua, Energía, Residuos).
     *
     * @param string $titulo Título del reto.
     * @return bool
     */
    private function is_reto_obligatorio_por_titulo($titulo)
    {
        $obligatorios = array('Agua (Base)', 'Energías limpias (Base)', 'Residuos (Base)');
        return in_array($titulo, $obligatorios, true);
    }

    /**
     * Obtiene el JSON especifico 2026 para el reto, si existe.
     *
     * @param string $titulo Titulo del reto.
     * @return string Ruta absoluta o cadena vacia si no aplica.
     */
    private function get_specific_2026_json_path($titulo)
    {
        $slug = sanitize_title((string) $titulo);
        $map  = self::get_2026_reto_json_map();
        if (empty($map[$slug])) {
            return '';
        }
        $path = (string) $map[$slug];
        return file_exists($path) ? $path : '';
    }

    /**
     * Importa una imagen desde URL a la biblioteca de medios.
     *
     * @param string $url     URL de la imagen.
     * @param int    $post_id ID del post padre.
     * @param string $title   Título para el attachment.
     * @return int|false ID del attachment o false.
     */
    private function import_image($url, $post_id, $title)
    {
        // Incluir funciones necesarias.
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        // Descargar archivo.
        $tmp = download_url($url);

        if (is_wp_error($tmp)) {
            $this->log('    ⚠ Error descargando imagen: ' . $tmp->get_error_message(), 'warning');
            return false;
        }

        // Preparar array de archivo.
        $file_array = array(
            'name'     => basename(wp_parse_url($url, PHP_URL_PATH)),
            'tmp_name' => $tmp,
        );

        // Verificar tipo de archivo.
        $file_type = wp_check_filetype($file_array['name']);
        if (empty($file_type['ext'])) {
            // Intentar detectar extensión del Content-Type.
            $file_array['name'] = sanitize_title($title) . '.jpg';
        }

        // Subir a la biblioteca de medios.
        $attachment_id = media_handle_sideload($file_array, $post_id, $title);

        // Limpiar archivo temporal si hubo error.
        if (is_wp_error($attachment_id)) {
            @unlink($file_array['tmp_name']);
            $this->log('    ⚠ Error subiendo imagen: ' . $attachment_id->get_error_message(), 'warning');
            return false;
        }

        return $attachment_id;
    }

    /**
     * Importa un archivo (PDF, etc.) desde URL a la biblioteca de medios.
     *
     * @param string $url     URL del archivo.
     * @param int    $post_id ID del post padre.
     * @param string $title   Título para el attachment.
     * @return int|false ID del attachment o false.
     */
    private function import_file($url, $post_id, $title)
    {
        // Incluir funciones necesarias.
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        // Descargar archivo.
        $tmp = download_url($url);

        if (is_wp_error($tmp)) {
            $this->log('    ⚠ Error descargando PDF: ' . $tmp->get_error_message(), 'warning');
            return false;
        }

        // Preparar array de archivo.
        $file_array = array(
            'name'     => basename(wp_parse_url($url, PHP_URL_PATH)),
            'tmp_name' => $tmp,
        );

        // Subir a la biblioteca de medios.
        $attachment_id = media_handle_sideload($file_array, $post_id, $title);

        // Limpiar archivo temporal si hubo error.
        if (is_wp_error($attachment_id)) {
            @unlink($file_array['tmp_name']);
            $this->log('    ⚠ Error subiendo PDF: ' . $attachment_id->get_error_message(), 'warning');
            return false;
        }

        return $attachment_id;
    }

    /**
     * Crea formularios WPForms y checklist para un reto.
     * Crea un formulario 2025 (base) y uno 2026 solo cuando existe JSON específico.
     * Writes to configuracion_por_anio with full field_points, PDF, and icon.
     *
     * @param array $data     Datos del reto.
     * @param int   $post_id  ID del post del reto.
     * @param bool  $dry_run  Modo simulación.
     * @param int   $pdf_id   Attachment ID del PDF.
     * @param int   $icon_id  Attachment ID del ícono.
     */
    private function create_wpform_and_checklist($data, $post_id, $dry_run, $pdf_id = 0, $icon_id = 0)
    {
        require_once dirname(__FILE__) . '/seed-wpforms.php';

        if (! class_exists('GNF_WPForms_Seeder')) {
            $this->log('    ⚠ Clase GNF_WPForms_Seeder no disponible', 'warning');
            return;
        }

        $wpforms_seeder = new GNF_WPForms_Seeder();

        // ── Formulario 2025 ──────────────────────────────────────────
        $result = $wpforms_seeder->process_single_reto($data, $post_id, $dry_run, 2025);

        if (! $result) {
            $this->log('    ⚠ No se pudo crear formulario WPForms 2025', 'warning');
        } elseif (! $dry_run && ! empty($result['form_id'])) {
            $field_points = $result['gnf_field_points'] ?? array();
            $wpforms_seeder->register_form_in_repeater($post_id, $result['form_id'], 2025, $field_points, $pdf_id, $icon_id);
            $this->log('    📝 WPForms 2025 creado (ID: ' . $result['form_id'] . ')');
        } elseif ($dry_run) {
            $this->log('    📝 [DRY-RUN] Se crearía formulario WPForms 2025');
        }

        // ── Formulario 2026 solo para retos con JSON específico ──────────────────
        $titulo = $data['titulo'] ?? '';

        // Solo crear 2026 cuando exista JSON específico para ese reto.
        $specific_2026 = $this->get_specific_2026_json_path($titulo);

        if (! empty($specific_2026)) {
            $result_2026 = $wpforms_seeder->process_single_reto(
                $data,
                $post_id,
                $dry_run,
                2026,
                $specific_2026
            );
            if (! $result_2026) {
                $this->log('    ⚠ No se pudo crear formulario WPForms 2026', 'warning');
            } elseif (! $dry_run && ! empty($result_2026['form_id'])) {
                $field_points_2026 = $result_2026['gnf_field_points'] ?? array();
                $wpforms_seeder->register_form_in_repeater($post_id, $result_2026['form_id'], 2026, $field_points_2026, $pdf_id, $icon_id);
                $this->log('    📝 WPForms 2026 creado (ID: ' . $result_2026['form_id'] . ')');
            } elseif ($dry_run) {
                $this->log('    📝 [DRY-RUN] Se crearía formulario WPForms 2026');
            }
        } else {
            $this->log('    ⏭ 2026 omitido: no hay JSON específico configurado para este reto', 'info');
        }
    }
}

/**
 * Limpia configuracion 2026:
 * - Remueve filas configuracion_por_anio (anio 2026) de retos fuera del set habilitado.
 * - Re-filtra retos seleccionados en matriculas/centro_datos_anuales para 2026.
 *
 * @param bool $dry_run Si true, solo reporta cambios.
 * @return array<string,int>
 */
function gnf_run_retos_2026_cleanup($dry_run = false)
{
    $log = static function ($message) {
        if (defined('WP_CLI') && WP_CLI) {
            echo $message . PHP_EOL;
        } else {
            echo '<div style="margin:5px 0;">' . esc_html($message) . '</div>';
        }
    };

    $log('=== Limpieza 2026: inicio ===');
    if ($dry_run) {
        $log('*** MODO DRY-RUN: no se guardaran cambios ***');
    }

    $allowed_map = GNF_Retos_Seeder::get_2026_reto_json_map();
    $allowed     = array();
    foreach ($allowed_map as $slug => $path) {
        if (file_exists($path)) {
            $allowed[(string) $slug] = true;
        }
    }

    $retos_revisados     = 0;
    $retos_actualizados  = 0;
    $rows_2026_removidas = 0;

    $reto_ids = get_posts(array(
        'post_type'      => 'reto',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ));

    foreach ((array) $reto_ids as $reto_id) {
        $retos_revisados++;
        $slug = sanitize_title((string) get_the_title($reto_id));
        $rows = get_field('configuracion_por_anio', $reto_id);
        if (! is_array($rows) || empty($rows)) {
            continue;
        }

        $next_rows = array();
        $removed   = 0;
        foreach ($rows as $row) {
            $is_2026 = absint($row['anio'] ?? 0) === 2026;
            if ($is_2026 && empty($allowed[$slug])) {
                $removed++;
                continue;
            }
            $next_rows[] = $row;
        }

        if ($removed > 0) {
            $retos_actualizados++;
            $rows_2026_removidas += $removed;
            if (! $dry_run && function_exists('update_field')) {
                update_field('configuracion_por_anio', array_values($next_rows), $reto_id);
            }
        }
    }

    $centros_revisados      = 0;
    $centros_actualizados   = 0;
    $matriculas_actualizadas = 0;

    global $wpdb;
    $table_matriculas = $wpdb->prefix . 'gn_matriculas';

    $centro_ids = get_posts(array(
        'post_type'      => 'centro_educativo',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ));

    foreach ((array) $centro_ids as $centro_id) {
        $centros_revisados++;

        $raw_retos = gnf_normalize_reto_ids(
            gnf_get_centro_anual_field($centro_id, 'retos_seleccionados', 2026, array())
        );
        $new_retos = gnf_sort_reto_ids_required_first(
            gnf_filter_reto_ids_by_year_form($raw_retos, 2026)
        );
        if ($raw_retos !== $new_retos) {
            $centros_actualizados++;
            if (! $dry_run) {
                gnf_set_centro_retos_seleccionados($centro_id, 2026, $new_retos);
            }
        }

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, retos_seleccionados, data FROM {$table_matriculas} WHERE centro_id = %d AND anio = %d ORDER BY id DESC LIMIT 1",
                $centro_id,
                2026
            )
        );

        if (! $row) {
            continue;
        }

        $db_retos = json_decode((string) $row->retos_seleccionados, true);
        if (! is_array($db_retos)) {
            $db_retos = array();
        }
        $db_retos = gnf_normalize_reto_ids($db_retos);
        $db_new   = gnf_sort_reto_ids_required_first(
            gnf_filter_reto_ids_by_year_form($db_retos, 2026)
        );

        if ($db_retos !== $db_new) {
            $matriculas_actualizadas++;
            if (! $dry_run) {
                $payload = array(
                    'retos_seleccionados' => wp_json_encode($db_new, JSON_UNESCAPED_UNICODE),
                    'updated_at'          => current_time('mysql'),
                );

                $data_decoded = json_decode((string) $row->data, true);
                if (is_array($data_decoded)) {
                    $data_decoded['bae-retos-seleccionados'] = $db_new;
                    $payload['data'] = wp_json_encode($data_decoded, JSON_UNESCAPED_UNICODE);
                }

                $wpdb->update($table_matriculas, $payload, array('id' => $row->id));
            }
        }
    }

    $summary = array(
        'retos_revisados'       => $retos_revisados,
        'retos_actualizados'    => $retos_actualizados,
        'rows_2026_removidas'   => $rows_2026_removidas,
        'centros_revisados'     => $centros_revisados,
        'centros_actualizados'  => $centros_actualizados,
        'matriculas_actualizadas' => $matriculas_actualizadas,
    );

    $log('=== Limpieza 2026: resumen ===');
    foreach ($summary as $key => $value) {
        $log($key . ': ' . (int) $value);
    }

    return $summary;
}

/**
 * Función principal para ejecutar el seeder.
 *
 * @param bool $dry_run Modo simulación.
 */
function gnf_run_retos_seeder($dry_run = false)
{
    $seeder = new GNF_Retos_Seeder();
    return $seeder->run($dry_run);
}

// Ejecución desde CLI.
if (defined('WP_CLI') && WP_CLI) {
    $dry_run = in_array('--dry-run', $GLOBALS['argv'], true);
    $cleanup_2026 = in_array('--cleanup-2026', $GLOBALS['argv'], true);
    if ($cleanup_2026) {
        gnf_run_retos_2026_cleanup($dry_run);
    } else {
        gnf_run_retos_seeder($dry_run);
    }
}

// Ejecución desde navegador (solo admins).
if ((isset($_GET['gnf_seed_retos']) || isset($_GET['gnf_cleanup_2026'])) && current_user_can('manage_options')) {
    $seed_key = defined('GNF_SEED_KEY') ? GNF_SEED_KEY : 'bandera2025';

    if (! isset($_GET['gnf_seed_key']) || $_GET['gnf_seed_key'] !== $seed_key) {
        wp_die('Clave de seguridad inválida.');
    }

    // Salida HTML.
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Seeder de Retos</title>';
    echo '<style>body{font-family:monospace;padding:20px;background:#f5f5f5;}</style></head><body>';
    echo '<h1>🌱 Seeder de Retos - Guardianes Formularios</h1>';
    echo '<div style="background:white;padding:20px;border-radius:8px;max-width:800px;">';

    $dry_run      = isset($_GET['dry_run']);
    $cleanup_2026 = isset($_GET['gnf_cleanup_2026']) || isset($_GET['cleanup_2026']);
    if ($cleanup_2026) {
        gnf_run_retos_2026_cleanup($dry_run);
    } else {
        gnf_run_retos_seeder($dry_run);
    }

    echo '</div></body></html>';
    exit;
}

