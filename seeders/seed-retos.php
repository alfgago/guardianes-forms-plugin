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
     * Obtiene los retos habilitados para un anio a partir del JSON canonico.
     *
     * @param int $anio Anio objetivo.
     * @return array<string,string>
     */
    public static function get_reto_json_map_for_year($anio)
    {
        $json_path = dirname(__FILE__) . '/retos-data.json';
        if (! file_exists($json_path)) {
            return array();
        }

        $decoded = json_decode((string) file_get_contents($json_path), true);
        if (! is_array($decoded)) {
            return array();
        }

        $map = array();
        foreach ($decoded as $reto_data) {
            if (empty($reto_data['titulo']) || empty($reto_data['years']) || ! is_array($reto_data['years'])) {
                continue;
            }

            $year_config = $reto_data['years'][(string) $anio] ?? null;
            if (! is_array($year_config)) {
                continue;
            }

            $preguntas_file = trim((string) ($year_config['preguntas_file'] ?? ''));
            if ('' === $preguntas_file) {
                continue;
            }

            $absolute_path = dirname(__FILE__) . '/' . ltrim($preguntas_file, '/');
            if (! file_exists($absolute_path)) {
                continue;
            }

            $map[sanitize_title((string) $reto_data['titulo'])] = $absolute_path;
        }

        return $map;
    }

    /**
     * Mantiene compatibilidad con el cleanup legado de 2026.
     *
     * @return array<string,string>
     */
    public static function get_2026_reto_json_map()
    {
        return self::get_reto_json_map_for_year(2026);
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

        $catalog_2026 = function_exists('gnf_get_reto_catalog') ? gnf_get_reto_catalog(2026) : array();

        foreach ($retos as $index => $reto_data) {
            $slug = function_exists('gnf_get_reto_canonical_slug') ? gnf_get_reto_canonical_slug($reto_data['titulo'] ?? '') : '';
            if (! empty($catalog_2026) && empty($catalog_2026[$slug])) {
                $this->log('[skip] Reto fuera del catalogo 2026: ' . ($reto_data['titulo'] ?? '(sin titulo)'), 'warning');
                continue;
            }
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
        $titulo       = sanitize_text_field($data['titulo']);
        $year_configs = $this->get_year_configs($data);
        $target_slug  = function_exists('gnf_get_reto_canonical_slug') ? gnf_get_reto_canonical_slug($titulo) : sanitize_title($titulo);

        $this->log("[{$number}] Procesando: {$titulo}");

        $existing = get_posts(
            array(
                'post_type'      => 'reto',
                'post_status'    => 'any',
                'title'          => $titulo,
                'posts_per_page' => 1,
            )
        );

        if ($target_slug) {
            $maybe_existing = get_posts(
                array(
                    'post_type'      => 'reto',
                    'post_status'    => 'any',
                    'posts_per_page' => -1,
                    'orderby'        => 'ID',
                    'order'          => 'ASC',
                )
            );

            $matching = array();
            foreach ((array) $existing as $candidate) {
                $matching[(int) $candidate->ID] = $candidate;
            }

            foreach ((array) $maybe_existing as $candidate) {
                $candidate_slug = function_exists('gnf_get_reto_canonical_slug') ? gnf_get_reto_canonical_slug($candidate->post_title) : sanitize_title($candidate->post_title);
                if ($candidate_slug === $target_slug) {
                    $matching[(int) $candidate->ID] = $candidate;
                }
            }

            $matching = array_values($matching);
            if (count($matching) > 1) {
                $keeper = array_shift($matching);
                $this->log('    Eliminando retos duplicados del mismo eco reto canonico...', 'warning');
                foreach ($matching as $duplicate) {
                    $this->log('      Duplicado: ' . $duplicate->post_title . ' (ID: ' . $duplicate->ID . ')', 'warning');
                    if (! $dry_run) {
                        wp_delete_post((int) $duplicate->ID, true);
                    }
                }
                $existing = array($keeper);
            } elseif (! empty($matching)) {
                $existing = array($matching[0]);
            }
        }

        if (! empty($existing)) {
            $existing_id = $existing[0]->ID;
            $this->log("    Ya existe (ID: {$existing_id})", 'warning');

            if (! $dry_run) {
                wp_update_post(
                    array(
                        'ID'         => $existing_id,
                        'post_title' => $titulo,
                    )
                );

                if (function_exists('update_field')) {
                    update_field('descripcion', $data['descripcion'], $existing_id);
                    update_field('color_del_reto', $data['color'], $existing_id);
                    update_field('tipos_evidencia_permitidos', array('foto', 'pdf', 'video'), $existing_id);
                    update_field('obligatorio_en_matricula', $this->is_reto_obligatorio_por_titulo($titulo) ? 1 : 0, $existing_id);
                } else {
                    update_post_meta($existing_id, 'descripcion', $data['descripcion']);
                    update_post_meta($existing_id, 'color_del_reto', $data['color']);
                    update_post_meta($existing_id, 'obligatorio_en_matricula', $this->is_reto_obligatorio_por_titulo($titulo) ? '1' : '0');
                }
            }

            if (! empty($year_configs)) {
                $this->log('    Verificando formularios WPForms por año...', 'info');
                $year_assets = $dry_run ? array() : $this->import_year_assets($data, $existing_id, $titulo);
                $this->create_wpform_and_checklist($data, $existing_id, $dry_run, $year_assets);
            }

            $this->skipped++;
            return;
        }

        if ($dry_run) {
            $this->log("    [DRY-RUN] Se crearia con {$data['puntos']} pts", 'success');
            $this->created++;
            return;
        }

        $post_id = wp_insert_post(
            array(
                'post_title'  => $titulo,
                'post_type'   => 'reto',
                'post_status' => 'publish',
            )
        );

        if (is_wp_error($post_id)) {
            $this->log('    Error al crear: ' . $post_id->get_error_message(), 'error');
            $this->errors++;
            return;
        }

        if (function_exists('update_field')) {
            update_field('descripcion', $data['descripcion'], $post_id);
            update_field('color_del_reto', $data['color'], $post_id);
            update_field('tipos_evidencia_permitidos', array('foto', 'pdf', 'video'), $post_id);
            if ($this->is_reto_obligatorio_por_titulo($titulo)) {
                update_field('obligatorio_en_matricula', 1, $post_id);
            }
        } else {
            update_post_meta($post_id, 'descripcion', $data['descripcion']);
            update_post_meta($post_id, 'color_del_reto', $data['color']);
            if ($this->is_reto_obligatorio_por_titulo($titulo)) {
                update_post_meta($post_id, 'obligatorio_en_matricula', '1');
            }
        }

        $year_assets = $this->import_year_assets($data, $post_id, $titulo);
        if (! empty($year_configs)) {
            $this->create_wpform_and_checklist($data, $post_id, $dry_run, $year_assets);
        }

        $this->log("    Creado exitosamente (ID: {$post_id})", 'success');
        $this->created++;
    }

    /**
     * Normaliza la configuracion anual del reto.
     *
     * @param array $data Datos del reto.
     * @return array<int,array<string,mixed>>
     */
    private function get_year_configs($data)
    {
        $years = array();

        if (! empty($data['years']) && is_array($data['years'])) {
            foreach ($data['years'] as $year_key => $config) {
                $anio = absint($config['anio'] ?? $year_key);
                if (! $anio) {
                    continue;
                }

                $years[$anio] = array(
                    'anio'                 => $anio,
                    'activo'               => ! array_key_exists('activo', $config) || ! empty($config['activo']),
                    'notas'                => isset($config['notas']) ? (string) $config['notas'] : '',
                    'preguntas_file'       => isset($config['preguntas_file']) ? (string) $config['preguntas_file'] : '',
                    'icon_url'             => isset($config['icon_url']) ? (string) $config['icon_url'] : '',
                    'pdf_url'              => isset($config['pdf_url']) ? (string) $config['pdf_url'] : '',
                );
            }
        } elseif (! empty($data['preguntas_file'])) {
            $years[2026] = array(
                'anio'                 => 2026,
                'activo'               => true,
                'notas'                => '',
                'preguntas_file'       => (string) $data['preguntas_file'],
                'icon_url'             => (string) ($data['imagen_url'] ?? ''),
                'pdf_url'              => (string) ($data['pdf_url'] ?? ''),
            );
        }

        ksort($years);
        return $years;
    }

    /**
     * Importa assets por año y retorna los attachment IDs por fila anual.
     *
     * @param array  $data    Datos del reto.
     * @param int    $post_id ID del post.
     * @param string $titulo  Titulo del reto.
     * @return array<int,array<string,int>>
     */
    private function import_year_assets($data, $post_id, $titulo)
    {
        $assets = array();
        $cache  = array();

        foreach ($this->get_year_configs($data) as $anio => $config) {
            $assets[$anio] = array(
                'pdf_id'  => 0,
                'icon_id' => 0,
            );

            $pdf_url = trim((string) ($config['pdf_url'] ?? ''));
            if ($pdf_url) {
                $cache_key = 'pdf:' . $pdf_url;
                if (! array_key_exists($cache_key, $cache)) {
                    $cache[$cache_key] = $this->import_file($pdf_url, $post_id, 'Reto - ' . $titulo . ' ' . $anio);
                }
                $assets[$anio]['pdf_id'] = (int) ($cache[$cache_key] ?: 0);
            }

            $icon_url = trim((string) ($config['icon_url'] ?? ''));
            if ($icon_url) {
                $cache_key = 'icon:' . $icon_url;
                if (! array_key_exists($cache_key, $cache)) {
                    $cache[$cache_key] = $this->import_image($icon_url, $post_id, $titulo . ' ' . $anio);
                }
                $assets[$anio]['icon_id'] = (int) ($cache[$cache_key] ?: 0);
                if ($assets[$anio]['icon_id'] && ! has_post_thumbnail($post_id)) {
                    set_post_thumbnail($post_id, $assets[$anio]['icon_id']);
                }
            }

        }

        return $assets;
    }

    private function is_reto_obligatorio_por_titulo($titulo)
    {
        $slug = function_exists('gnf_get_reto_canonical_slug') ? gnf_get_reto_canonical_slug($titulo) : sanitize_title($titulo);
        return in_array($slug, array('agua', 'electricidad', 'residuos'), true);
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
     * Procesa unicamente la configuracion 2026 disponible.
     * Writes to configuracion_por_anio with full field_points, PDF, and icon.
     *
     * @param array $data     Datos del reto.
     * @param int   $post_id  ID del post del reto.
     * @param bool  $dry_run  Modo simulación.
     * @param int   $pdf_id   Attachment ID del PDF.
     * @param int   $icon_id  Attachment ID del ícono.
     */
    private function create_wpform_and_checklist($data, $post_id, $dry_run, $year_assets = array())
    {
        require_once dirname(__FILE__) . '/seed-wpforms.php';

        if (! class_exists('GNF_WPForms_Seeder')) {
            $this->log('    Clase GNF_WPForms_Seeder no disponible', 'warning');
            return;
        }

        $wpforms_seeder = new GNF_WPForms_Seeder();
        foreach ($this->get_year_configs($data) as $anio => $year_config) {
            if (empty($year_config['preguntas_file'])) {
                $this->log('    Año ' . $anio . ' omitido: sin preguntas_file', 'info');
                continue;
            }

            $result = $wpforms_seeder->process_single_reto(
                $data,
                $post_id,
                $dry_run,
                $anio,
                $year_config['preguntas_file']
            );

            if (! $result) {
                $this->log('    No se pudo crear formulario WPForms ' . $anio, 'warning');
                continue;
            }

            if ($dry_run) {
                $this->log('    [DRY-RUN] Se crearia formulario WPForms ' . $anio, 'info');
                continue;
            }

            if (empty($result['form_id'])) {
                continue;
            }

            $field_points = $result['gnf_field_points'] ?? array();
            $assets       = $year_assets[$anio] ?? array();
            $wpforms_seeder->register_form_in_repeater(
                $post_id,
                $result['form_id'],
                $anio,
                $field_points,
                (int) ($assets['pdf_id'] ?? 0),
                (int) ($assets['icon_id'] ?? 0),
                $year_config['notas'] ?? '',
                ! empty($year_config['activo'])
            );
            $this->log('    WPForms ' . $anio . ' creado (ID: ' . $result['form_id'] . ')');
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

    $allowed_map = GNF_Retos_Seeder::get_reto_json_map_for_year(2026);
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
    $result = $seeder->run($dry_run);

    if (! $dry_run && function_exists('gnf_backfill_required_retos_for_active_centros')) {
        gnf_backfill_required_retos_for_active_centros(2026);
    }

    return $result;
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
    $seed_key = defined('GNF_SEED_KEY') ? GNF_SEED_KEY : 'bandera2026';

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

