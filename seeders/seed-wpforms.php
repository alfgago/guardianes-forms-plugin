<?php

/**
 * Seeder de WPForms para Guardianes Formularios.
 *
 * Crea formularios WPForms a partir de los archivos JSON de preguntas.
 * Puede ejecutarse standalone o integrarse con seed-retos.php.
 *
 * Ejecutar desde WP-CLI:
 *   wp eval-file wp-content/plugins/guardianes-formularios/seeders/seed-wpforms.php
 *
 * O acceder via navegador añadiendo ?gnf_seed_wpforms=1&gnf_seed_key=TU_CLAVE
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
 * Clase para crear formularios WPForms desde JSON.
 */
class GNF_WPForms_Seeder
{

    /**
     * Ruta base de los archivos de preguntas.
     *
     * @var string
     */
    private $preguntas_base_path;

    /**
     * Modo de ejecución (cli o web).
     *
     * @var string
     */
    private $mode;

    /**
     * Contador de formularios creados.
     *
     * @var int
     */
    private $created = 0;

    /**
     * Contador de formularios omitidos.
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
     * Mapeo de tipos de campo JSON a tipos WPForms.
     *
     * @var array
     */
    private $field_type_map = array(
        'text'           => 'text',
        'textarea'       => 'textarea',
        'number'         => 'number-slider',
        'radio'          => 'radio',
        'select'         => 'select',
        'checkbox'       => 'checkbox',
        'file'           => 'file-upload',
        'email'          => 'email',
        'date'           => 'date-time',
        // Nuevos tipos (formato 2026)
        'seleccion_unica' => 'radio',
        'subida_archivo'  => 'file-upload',
        'numero_entero'   => 'text',
    );

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->preguntas_base_path = dirname(__FILE__);
        $this->mode = defined('WP_CLI') && WP_CLI ? 'cli' : 'web';
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
     * Crea un formulario WPForms a partir de un archivo JSON de preguntas.
     *
     * @param string $json_file     Ruta al archivo JSON (relativa o absoluta).
     * @param string $form_title    Título del formulario (sin sufijo de año).
     * @param int    $reto_post_id  ID del post de reto asociado (opcional).
     * @param bool   $dry_run       Modo simulación.
     * @param int    $anio          Año para sufijo del título (0 = sin sufijo).
     * @return array|false Array con 'form_id', 'checklist', 'gnf_field_points' o false en error.
     */
    public function create_form_from_json($json_file, $form_title, $reto_post_id = 0, $dry_run = false, $anio = 0)
    {
        // Verificar que WPForms esté activo.
        if (! function_exists('wpforms')) {
            $this->log('Error: WPForms no está activo.', 'error');
            return false;
        }

        // Construir ruta completa si es relativa.
        if (strpos($json_file, '/') !== 0 && strpos($json_file, ':') === false) {
            $json_path = $this->preguntas_base_path . '/' . $json_file;
        } else {
            $json_path = $json_file;
        }

        // Verificar que el archivo existe.
        if (! file_exists($json_path)) {
            $this->log("Error: No se encontró el archivo {$json_path}", 'error');
            return false;
        }

        // Leer y decodificar JSON con encoding UTF-8 explícito.
        $json_content = file_get_contents($json_path);

        // Asegurar que el contenido está en UTF-8 (convertir si es necesario).
        if (!mb_check_encoding($json_content, 'UTF-8')) {
            $json_content = mb_convert_encoding($json_content, 'UTF-8', 'auto');
        }

        // Decodificar JSON - esto automáticamente convierte escapes Unicode (\u00bf) a caracteres reales (¿)
        $preguntas = json_decode($json_content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log('Error: JSON inválido - ' . json_last_error_msg(), 'error');
            return false;
        }

        // Asegurar que todos los strings en el array están en UTF-8 correctamente.
        // Esto es importante porque json_decode ya debería haber decodificado los escapes Unicode,
        // pero queremos asegurarnos de que todo está en UTF-8 válido.
        $preguntas = $this->ensure_utf8_recursive($preguntas);

        // Aplicar sufijo de año al título si se proporciona.
        if ($anio > 0) {
            $form_title = rtrim($form_title) . ' - ' . $anio;
        }

        $this->log("Procesando: {$form_title} ({$json_file})");
        $this->log("  Encontradas " . count($preguntas) . " preguntas");

        // Verificar si ya existe un formulario con este título.
        $existing_form = $this->find_existing_form($form_title);
        if ($existing_form) {
            // Por defecto: recrear formularios existentes para corregir caracteres especiales.
            $force = true;
            if (defined('WP_CLI') && WP_CLI && !empty($GLOBALS['argv']) && is_array($GLOBALS['argv'])) {
                if (in_array('--preserve', $GLOBALS['argv'], true) || in_array('--no-force', $GLOBALS['argv'], true)) {
                    $force = false;
                }
            }
            if (isset($_GET['gnf_seed_wpforms_preserve']) && '1' === (string) $_GET['gnf_seed_wpforms_preserve']) {
                $force = false;
            }

            if ($force && ! $dry_run) {
                $this->log("  ⚠ Recreando formulario existente (ID: {$existing_form}) para corregir caracteres especiales", 'warning');
                wp_delete_post($existing_form, true);
                $existing_form = false;
            } else {
                $this->log("  ⏭ Ya existe formulario (ID: {$existing_form}), omitiendo...", 'warning');
                $this->skipped++;

                // Aún retornamos el checklist extraído.
                $checklist = $this->extract_checklist_from_questions($preguntas);
                return array(
                    'form_id'          => $existing_form,
                    'checklist'        => $checklist,
                    'gnf_field_points' => array(),
                    'skipped'          => true,
                );
            }
        }

        // Construir campos WPForms y extraer mapa de puntos.
        $build_result     = $this->build_wpforms_fields($preguntas);
        $fields           = $build_result['fields'];
        $gnf_field_points = $build_result['gnf_field_points'];
        $this->log("  Campos WPForms generados: " . count($fields));
        if (! empty($gnf_field_points)) {
            $this->log("  Campos con puntaje: " . count($gnf_field_points) . ' (' . array_sum(array_column($gnf_field_points, 'puntos')) . ' pts)');
        }

        // Extraer checklist de los campos tipo file.
        $checklist = $this->extract_checklist_from_questions($preguntas);
        $this->log("  Items de checklist extraídos: " . count($checklist));

        if ($dry_run) {
            $this->log("  ✓ [DRY-RUN] Se crearía formulario con " . count($fields) . " campos", 'success');
            $this->created++;
            return array(
                'form_id'          => 0,
                'checklist'        => $checklist,
                'gnf_field_points' => $gnf_field_points,
                'dry_run'          => true,
            );
        }

        // Crear el formulario WPForms.
        $form_id = $this->create_wpforms_form($form_title, $fields, $reto_post_id, $gnf_field_points);

        if (! $form_id) {
            $this->log("  ✗ Error al crear formulario", 'error');
            $this->errors++;
            return false;
        }

        $this->log("  ✓ Formulario creado (ID: {$form_id})", 'success');
        $this->created++;

        return array(
            'form_id'          => $form_id,
            'checklist'        => $checklist,
            'gnf_field_points' => $gnf_field_points,
        );
    }

    /**
     * Busca un formulario WPForms existente por título.
     *
     * @param string $title Título del formulario.
     * @return int|false ID del formulario o false.
     */
    private function find_existing_form($title)
    {
        $forms = get_posts(array(
            'post_type'      => 'wpforms',
            'post_status'    => 'publish',
            'title'          => $title,
            'posts_per_page' => 1,
        ));

        return ! empty($forms) ? $forms[0]->ID : false;
    }

    /**
     * Convierte preguntas JSON a campos WPForms.
     * Soporta el formato original (type/label/choices/conditional_logic)
     * y el nuevo formato 2026 (tipo_de_campo/pregunta/opciones/depends_on/suma_puntos/puntos).
     *
     * @param array $preguntas Array de preguntas del JSON.
     * @return array { fields: array, gnf_field_points: array }
     */
    private function build_wpforms_fields($preguntas)
    {
        $fields           = array();
        $field_id         = 0;
        $field_id_map     = array();
        $gnf_field_points = array();

        // Detectar formato: nuevo formato tiene 'tipo_de_campo'.
        $is_new_format = ! empty($preguntas) && isset($preguntas[0]['tipo_de_campo']);

        // Primera pasada: construir mapa de referencias para lógica condicional.
        foreach ($preguntas as $pregunta) {
            $field_id++;
            if ($is_new_format) {
                if (! empty($pregunta['id'])) {
                    $field_id_map[$pregunta['id']] = $field_id;
                }
            } else {
                $field_id_map[$pregunta['label']] = $field_id;
            }
        }

        // Segunda pasada: construir campos con conditional logic.
        $field_id = 0;
        foreach ($preguntas as $index => $pregunta) {
            $field_id++;

            if ($is_new_format) {
                $type        = $pregunta['tipo_de_campo'] ?? 'text';
                $raw_choices = $pregunta['opciones'] ?? null;
                $required    = ! empty($pregunta['required']);
                $label       = $pregunta['pregunta'] ?? 'Pregunta ' . ($index + 1);
                $cond_raw    = isset($pregunta['depends_on']) ? array(
                    'field'    => $pregunta['depends_on']['field'],
                    'operator' => '==',
                    'value'    => $pregunta['depends_on']['value'],
                ) : null;

                // Acumular puntos para el mapa gnf_field_points (genérico, cualquier tipo).
                if (! empty($pregunta['suma_puntos']) && isset($pregunta['puntos'])) {
                    $gnf_field_points[$field_id] = array(
                        'puntos' => absint($pregunta['puntos']),
                        'tipo'   => $this->field_type_map[$type] ?? 'text',
                        'label'  => $label,
                    );
                }
            } else {
                $type        = $pregunta['type'] ?? 'text';
                $raw_choices = $pregunta['choices'] ?? null;
                $required    = ! empty($pregunta['required']);
                $label       = $pregunta['label'] ?? 'Pregunta ' . ($index + 1);
                $cond_raw    = $pregunta['conditional_logic'] ?? null;
            }

            $wpforms_type = $this->field_type_map[$type] ?? 'text';

            if (is_string($label) && ! mb_check_encoding($label, 'UTF-8')) {
                $label = mb_convert_encoding($label, 'UTF-8', 'auto');
            }

            $field = array(
                'id'       => $field_id,
                'type'     => $wpforms_type,
                'label'    => $label,
                'required' => $required ? '1' : '0',
                'size'     => 'large',
            );

            // Procesar opciones para radio, select, checkbox.
            $choice_types = array('radio', 'select', 'checkbox', 'seleccion_unica');
            if (! empty($raw_choices) && in_array($type, $choice_types, true)) {
                $field['choices'] = array();
                foreach ($raw_choices as $choice_index => $choice) {
                    $choice_label = is_string($choice) ? $choice : (string) $choice;
                    if (! mb_check_encoding($choice_label, 'UTF-8')) {
                        $choice_label = mb_convert_encoding($choice_label, 'UTF-8', 'auto');
                    }
                    $field['choices'][$choice_index + 1] = array(
                        'label' => $choice_label,
                        'value' => '',
                    );
                }
            }

            // Configuración específica por tipo.
            switch ($type) {
                case 'number':
                case 'numero_entero':
                    $field['type']          = 'text';
                    $field['limit_enabled'] = '1';
                    $field['limit_type']    = 'characters';
                    break;

                case 'file':
                case 'subida_archivo':
                    $field['extensions']      = 'jpg,jpeg,png,gif,pdf,mp4,mov';
                    $field['max_size']        = '10';
                    $field['max_file_number'] = '5';
                    $field['style']           = 'modern';
                    break;

                case 'textarea':
                    $field['limit_enabled'] = '0';
                    break;
            }

            // Procesar lógica condicional.
            if (! empty($cond_raw)) {
                $parent_key      = $cond_raw['field'];
                $parent_field_id = $field_id_map[$parent_key] ?? null;

                if ($parent_field_id) {
                    $field['conditionals'] = array(
                        1 => array(
                            1 => array(
                                'field'    => (string) $parent_field_id,
                                'operator' => $this->map_operator($cond_raw['operator'] ?? '=='),
                                'value'    => $cond_raw['value'] ?? '',
                            ),
                        ),
                    );
                    $field['conditional_logic'] = '1';
                    $field['conditional_type']  = 'show';
                }
            }

            $fields[$field_id] = $field;
        }

        return array(
            'fields'           => $fields,
            'gnf_field_points' => $gnf_field_points,
        );
    }

    /**
     * Asegura que todos los strings en un array estén en UTF-8.
     * Convierte recursivamente todos los valores string.
     *
     * @param mixed $data Datos a procesar.
     * @return mixed Datos con strings en UTF-8.
     */
    private function ensure_utf8_recursive($data)
    {
        if (is_string($data)) {
            // Si el string ya está en UTF-8 válido, devolverlo tal cual.
            if (mb_check_encoding($data, 'UTF-8')) {
                return $data;
            }
            // Convertir a UTF-8 si no lo está.
            $converted = mb_convert_encoding($data, 'UTF-8', 'auto');
            // Verificar que la conversión fue exitosa.
            if (mb_check_encoding($converted, 'UTF-8')) {
                return $converted;
            }
            // Si falla, intentar con UTF-8 forzado (ignorar caracteres inválidos).
            return mb_convert_encoding($data, 'UTF-8', 'UTF-8');
        }

        if (is_array($data)) {
            $result = array();
            foreach ($data as $key => $value) {
                $key_utf8 = is_string($key) ? $this->ensure_utf8_recursive($key) : $key;
                $result[$key_utf8] = $this->ensure_utf8_recursive($value);
            }
            return $result;
        }

        return $data;
    }

    /**
     * Limpia caracteres UTF-8 inválidos recursivamente.
     *
     * @param mixed $data Datos a limpiar.
     * @return mixed Datos limpios.
     */
    private function clean_utf8_recursive($data)
    {
        if (is_string($data)) {
            // Remover caracteres de control excepto \n, \r, \t.
            $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $data);
            // Asegurar UTF-8 válido.
            return mb_convert_encoding($cleaned, 'UTF-8', 'UTF-8');
        }

        if (is_array($data)) {
            $result = array();
            foreach ($data as $key => $value) {
                $key_clean = is_string($key) ? $this->clean_utf8_recursive($key) : $key;
                $result[$key_clean] = $this->clean_utf8_recursive($value);
            }
            return $result;
        }

        return $data;
    }

    /**
     * Mapea operadores de conditional logic.
     *
     * @param string $operator Operador original.
     * @return string Operador WPForms.
     */
    private function map_operator($operator)
    {
        $map = array(
            '=='  => 'is',
            '!='  => 'is_not',
            '>'   => 'greater_than',
            '<'   => 'less_than',
            '>='  => 'greater_than',
            '<='  => 'less_than',
            'contains' => 'contains',
        );
        return $map[$operator] ?? 'is';
    }

    /**
     * Extrae items de checklist de los campos tipo file.
     * Soporta formato original (type/label) y nuevo formato (tipo_de_campo/pregunta).
     *
     * @param array $preguntas Array de preguntas.
     * @return array Array de items de checklist para ACF.
     */
    public function extract_checklist_from_questions($preguntas)
    {
        $checklist     = array();
        $is_new_format = ! empty($preguntas) && isset($preguntas[0]['tipo_de_campo']);

        foreach ($preguntas as $pregunta) {
            if ($is_new_format) {
                $is_file = in_array($pregunta['tipo_de_campo'] ?? '', array('subida_archivo', 'file'), true);
                $label   = $pregunta['pregunta'] ?? 'Evidencia';
            } else {
                $is_file = ($pregunta['type'] ?? '') === 'file';
                $label   = $pregunta['label'] ?? 'Evidencia';
            }

            if ($is_file) {
                $checklist[] = array(
                    'nombre'         => $label,
                    'tipo_evidencia' => $this->detect_evidence_type(array('label' => $label)),
                    'requerido'      => ! empty($pregunta['required']),
                );
            }
        }

        return $checklist;
    }

    /**
     * Detecta el tipo de evidencia basándose en el label del campo.
     *
     * @param array $pregunta Datos de la pregunta.
     * @return string Tipo: foto, video, pdf.
     */
    private function detect_evidence_type($pregunta)
    {
        $label = strtolower($pregunta['label'] ?? '');

        if (strpos($label, 'video') !== false) {
            return 'video';
        }
        if (strpos($label, 'pdf') !== false || strpos($label, 'documento') !== false) {
            return 'pdf';
        }
        // Por defecto, asumimos foto.
        return 'foto';
    }

    /**
     * Crea el formulario WPForms en la base de datos.
     *
     * @param string $title            Título del formulario.
     * @param array  $fields           Campos del formulario.
     * @param int    $reto_post_id     ID del reto asociado.
     * @param array  $gnf_field_points Mapa { field_id => { puntos, tipo } } para scoring automático.
     * @return int|false ID del formulario o false.
     */
    private function create_wpforms_form($title, $fields, $reto_post_id = 0, $gnf_field_points = array())
    {
        // Estructura de datos del formulario WPForms.
        $form_data = array(
            'field_id' => count($fields) + 1,
            'fields'   => $fields,
            'settings' => array(
                'form_title'                => $title,
                'form_desc'                 => '',
                'submit_text'               => 'Enviar Reto',
                'submit_text_processing'    => 'Enviando...',
                'ajax_submit'               => '1',
                'notification_enable'       => '1',
                'notifications'             => array(
                    1 => array(
                        'notification_name' => 'Notificación por defecto',
                        'email'             => '{admin_email}',
                        'subject'           => 'Nueva entrada: ' . $title,
                        'sender_name'       => get_bloginfo('name'),
                        'sender_address'    => '{admin_email}',
                        'message'           => '{all_fields}',
                    ),
                ),
                'confirmations'             => array(
                    1 => array(
                        'type'           => 'message',
                        'message'        => '<p>¡Gracias! Tu reto ha sido enviado correctamente. Recibirás una notificación cuando sea revisado.</p>',
                        'message_scroll' => '1',
                    ),
                ),
                // Referencia al reto.
                'gnf_reto_id'        => $reto_post_id,
            ),
        );

        // Asegurar que el título está en UTF-8.
        if (is_string($title) && !mb_check_encoding($title, 'UTF-8')) {
            $title = mb_convert_encoding($title, 'UTF-8', 'auto');
        }

        // Asegurar que todos los datos del formulario están en UTF-8 antes de serializar.
        $form_data = $this->ensure_utf8_recursive($form_data);

        // Crear el post del formulario.
        // IMPORTANTE: Usar json_encode directamente (no wp_json_encode) para asegurar que las flags se respeten.
        // JSON_UNESCAPED_UNICODE: No escapar caracteres Unicode (¿, á, é, etc.)
        // JSON_UNESCAPED_SLASHES: No escapar barras /
        // JSON_PRETTY_PRINT: Formato legible (opcional, pero ayuda a debug)
        $json_content = json_encode($form_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // Si hay error en el encoding, intentar limpiar caracteres problemáticos.
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log('Advertencia: Error al codificar JSON - ' . json_last_error_msg(), 'warning');
            // Limpiar y reintentar.
            $form_data_clean = $this->clean_utf8_recursive($form_data);
            $json_content = json_encode($form_data_clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->log('Error crítico: No se pudo codificar el formulario después de limpiar', 'error');
                return false;
            }
        }

        $form_id = wp_insert_post(array(
            'post_title'   => $title,
            'post_status'  => 'publish',
            'post_type'    => 'wpforms',
            'post_content' => $json_content,
        ));

        if (is_wp_error($form_id)) {
            return false;
        }

        return $form_id;
    }

    /**
     * Ejecuta el seeder para todos los retos existentes.
     *
     * @param bool $dry_run Modo simulación.
     */
    public function run_all($dry_run = false)
    {
        $this->log('=== Iniciando creación de formularios WPForms ===');

        if ($dry_run) {
            $this->log('*** MODO DRY-RUN: No se crearán registros ***', 'warning');
        }

        // Cargar datos de retos.
        $json_path = dirname(__FILE__) . '/retos-data.json';
        if (! file_exists($json_path)) {
            $this->log('Error: No se encontró retos-data.json', 'error');
            return false;
        }

        $retos = json_decode(file_get_contents($json_path), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log('Error: JSON inválido - ' . json_last_error_msg(), 'error');
            return false;
        }

        $this->log('Encontrados ' . count($retos) . ' retos.');
        $this->log('');

        foreach ($retos as $index => $reto_data) {
            $this->log('[' . ($index + 1) . '/' . count($retos) . '] Reto: ' . $reto_data['titulo']);

            if (empty($reto_data['preguntas_file'])) {
                $this->log('  ⚠ Sin archivo de preguntas, omitiendo...', 'warning');
                continue;
            }

            // Buscar el post del reto.
            $reto_post = get_posts(array(
                'post_type'      => 'reto',
                'post_status'    => 'any',
                'title'          => $reto_data['titulo'],
                'posts_per_page' => 1,
            ));
            $reto_post_id = ! empty($reto_post) ? $reto_post[0]->ID : 0;

            // Crear formulario 2025.
            $result = $this->create_form_from_json(
                $reto_data['preguntas_file'],
                'Reto: ' . $reto_data['titulo'],
                $reto_post_id,
                $dry_run,
                2025
            );

            if ($result && $reto_post_id && ! $dry_run) {
                if (function_exists('update_field') && ! empty($result['form_id'])) {
                    $field_points = $result['gnf_field_points'] ?? array();
                    $this->register_form_in_repeater($reto_post_id, $result['form_id'], 2025, $field_points);
                }
            }

            $this->log('');
        }

        // Resumen.
        $this->log('=== Resumen ===');
        $this->log("Formularios creados: {$this->created}", 'success');
        $this->log("Omitidos (ya existían): {$this->skipped}", 'warning');
        $this->log("Errores: {$this->errors}", $this->errors > 0 ? 'error' : 'info');

        return true;
    }

    /**
     * Registra un formulario en configuracion_por_anio del reto.
     *
     * @param int   $reto_post_id     ID del reto.
     * @param int   $form_id          ID del formulario WPForms.
     * @param int   $anio             Año.
     * @param array $gnf_field_points Mapa { field_id => { puntos, tipo, label } }.
     * @param int   $pdf_id           Attachment ID del PDF (0 si ninguno).
     * @param int   $icon_id          Attachment ID del ícono (0 si ninguno).
     */
    public function register_form_in_repeater($reto_post_id, $form_id, $anio, $gnf_field_points = array(), $pdf_id = 0, $icon_id = 0)
    {
        if (! function_exists('get_field') || ! function_exists('update_field')) {
            return;
        }

        // Build field_points sub-repeater rows.
        $fp_rows      = array();
        $puntaje_total = 0;
        foreach ($gnf_field_points as $fid => $info) {
            $puntos = absint($info['puntos'] ?? 0);
            $puntaje_total += $puntos;
            $fp_rows[] = array(
                'field_id'    => (int) $fid,
                'field_label' => $info['label'] ?? 'Campo ' . $fid,
                'field_type'  => $info['tipo'] ?? '',
                'puntos'      => $puntos,
            );
        }

        $rows = get_field('configuracion_por_anio', $reto_post_id);
        $rows = is_array($rows) ? $rows : array();

        $new_row_data = array(
            'anio'          => (int) $anio,
            'activo'        => true,
            'notas'         => '',
            'icono'         => $icon_id ?: '',
            'pdf'           => $pdf_id ?: '',
            'wpforms_id'    => $form_id,
            'field_points'  => $fp_rows,
            'puntaje_total' => $puntaje_total,
        );

        // Buscar si el año ya está registrado; update if so.
        foreach ($rows as $idx => &$row) {
            if (absint($row['anio'] ?? 0) === absint($anio)) {
                $row['wpforms_id']    = $form_id;
                $row['activo']        = true;
                $row['field_points']  = $fp_rows;
                $row['puntaje_total'] = $puntaje_total;
                if ($pdf_id) {
                    $row['pdf'] = $pdf_id;
                }
                if ($icon_id) {
                    $row['icono'] = $icon_id;
                }
                update_field('configuracion_por_anio', $rows, $reto_post_id);
                $this->log("  📅 configuracion_por_anio [{$anio}] actualizado (ID: {$form_id}, {$puntaje_total} pts)");
                return;
            }
        }
        unset($row);

        // Año no existía, agregar nueva fila.
        $rows[] = $new_row_data;
        update_field('configuracion_por_anio', $rows, $reto_post_id);
        $this->log("  📅 configuracion_por_anio [{$anio}] registrado (ID: {$form_id}, {$puntaje_total} pts)");
    }

    /**
     * Procesa un solo reto (para integración con seed-retos.php).
     *
     * @param array  $reto_data    Datos del reto desde JSON.
     * @param int    $reto_post_id ID del post creado.
     * @param bool   $dry_run      Modo simulación.
     * @param int    $anio         Año para el sufijo del título (0 = sin sufijo).
     * @param string $preguntas_file_override Ruta alternativa al archivo de preguntas.
     * @return array|false Resultado o false.
     */
    public function process_single_reto($reto_data, $reto_post_id, $dry_run = false, $anio = 0, $preguntas_file_override = '')
    {
        $preguntas_file = ! empty($preguntas_file_override) ? $preguntas_file_override : ($reto_data['preguntas_file'] ?? '');
        if (empty($preguntas_file)) {
            return false;
        }

        return $this->create_form_from_json(
            $preguntas_file,
            'Reto: ' . $reto_data['titulo'],
            $reto_post_id,
            $dry_run,
            $anio
        );
    }
}

/**
 * Función helper para ejecutar el seeder de WPForms.
 *
 * @param bool $dry_run Modo simulación.
 */
function gnf_run_wpforms_seeder($dry_run = false)
{
    $seeder = new GNF_WPForms_Seeder();
    return $seeder->run_all($dry_run);
}

/**
 * Función helper para crear un formulario desde un reto.
 *
 * @param array $reto_data    Datos del reto.
 * @param int   $reto_post_id ID del post.
 * @param bool  $dry_run      Modo simulación.
 * @return array|false
 */
function gnf_create_wpform_for_reto($reto_data, $reto_post_id, $dry_run = false)
{
    $seeder = new GNF_WPForms_Seeder();
    return $seeder->process_single_reto($reto_data, $reto_post_id, $dry_run);
}

// Ejecución desde CLI.
if (defined('WP_CLI') && WP_CLI) {
    $dry_run = in_array('--dry-run', $GLOBALS['argv'], true);
    gnf_run_wpforms_seeder($dry_run);
}

// Ejecución desde navegador (solo admins).
if (isset($_GET['gnf_seed_wpforms']) && current_user_can('manage_options')) {
    $seed_key = defined('GNF_SEED_KEY') ? GNF_SEED_KEY : 'bandera2025';

    if (! isset($_GET['gnf_seed_key']) || $_GET['gnf_seed_key'] !== $seed_key) {
        wp_die('Clave de seguridad inválida.');
    }

    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Seeder de WPForms</title>';
    echo '<style>body{font-family:monospace;padding:20px;background:#f5f5f5;}</style></head><body>';
    echo '<h1>📝 Seeder de WPForms - Guardianes Formularios</h1>';
    echo '<div style="background:white;padding:20px;border-radius:8px;max-width:800px;">';

    $dry_run = isset($_GET['dry_run']);
    gnf_run_wpforms_seeder($dry_run);

    echo '</div></body></html>';
    exit;
}
