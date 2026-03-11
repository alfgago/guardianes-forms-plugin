# Manual Tecnico – Guardianes Formularios

## Arquitectura general
- Lenguaje: PHP 7.4+; WordPress 6.x.
- Formularios: WPForms (procesamiento central en `includes/wpforms-hooks.php`), Save & Resume, PDF, Post Submissions (opcional).
- Configuración y metacampos: ACF Pro, con campos registrados vía código.
- Datos persistentes: tablas custom `wp_gn_reto_entries` y `wp_gn_notificaciones` creadas con dbDelta.
- Front-end: shortcodes y templates en `/templates`, assets mínimos en `/assets`.
- Roles: `docente`, `supervisor`, agrupados bajo menú “Formularios Bandera Azul”.

## Estructura de archivos
- `guardianes-formularios.php`  
  Bootstrap: define constantes, carga includes, activation/deactivation hooks, encola assets condicionalmente según shortcodes.

- `includes/`  
  - `helpers.php`: utilidades varias (opciones ACF, roles, región de usuario, permisos centro, notificaciones, mapeo tipos de evidencia, cache helpers, correcciones).  
  - `roles.php`: registra roles `docente` y `supervisor`, bloquea admin a docentes y oculta toolbar.  
  - `tables.php`: dbDelta para `wp_gn_reto_entries` (con índices y nuevo estado default `no_iniciado`) y `wp_gn_notificaciones`.  
  - `cpts.php`: CPTs `centro_educativo`, `reto`, taxonomía `gn_region`, todos bajo menú padre `gnf-admin`.  
  - `acf-fields.php`: ACF local PHP. Opciones globales (formulario de matrícula, año activo, rangos de estrellas); campos de centro (código MEP, región, dirección, puntaje_total, estrella_final, docentes asociados); campos de reto (descripción, puntaje máximo, `wpforms_form_id`, checklist, tipos de evidencia permitidos, iconos estático/animado, color).  
  - `puntajes.php`: cálculo de puntaje por reto (checklist requerido) y puntaje/estrella por centro con cache/transient.  
  - `evidencias.php`: mueve uploads a carpeta propia, detecta EXIF para año, marca `requires_year_validation`, dispara notificación `invalid_photo_date`, y ofrece descarga protegida AJAX.  
  - `wpforms-hooks.php`: core de integración WPForms. Normaliza campos, filtra tipos de archivo permitidos por reto, valida uploads, maneja envíos completos y parciales (Save & Resume) como `en_progreso`/`enviado`, persiste en tabla custom, valida checklist vs ACF, recalcula puntajes, limpia caches, genera notificaciones.  
  - `docente-panel.php`: renderizador de shortcode docente y acción para reabrir retos en corrección (`en_progreso`).  
  - `supervisor-panel.php`: renderizador de shortcode supervisor (filtrado por región), cache de listados, acciones de aprobar/corrección/puntaje con notificaciones y recalculo.  
  - `shortcodes.php`: registra `[gn_docente_panel]`, `[gn_supervisor_panel]`, `[gn_notificaciones]`.  
  - `reports.php`: export CSV global/región y helper `gn_get_entries_for_report` (solo estados enviado/aprobado).  
  - `admin-menu.php`: menú padre “Formularios Bandera Azul” con accesos a panel, CPTs y opciones ACF.

- `templates/`  
  - `docente-dashboard.php`: grid de retos, estados, puntajes, iconos, warnings de fecha EXIF, feedback y botón “Reabrir y corregir” en corrección.  
  - `docente-reto.php`: plantilla simple opcional.  
  - `supervisor-dashboard.php`: tabla de centros filtrados por región con export CSV.  
  - `supervisor-centro.php`: detalle por centro, evidencias con warnings, notas y acciones de aprobación/corrección/puntaje.  

- `assets/css/guardianes.css`: estilos ligeros con color primario #369484 y badges de estado.  
- `assets/js/guardianes.js`: placeholder para futuras interacciones.  
- `mocks/*.html`: vistas estáticas de paneles para prueba visual sin WordPress.

## Flujo de datos
1) Matrícula (formulario nativo del plugin, esquema ACF): `gnf_handle_submit_matricula` normaliza datos y delega en `gnf_handle_matricula_submission` para crear/actualizar centro, región y relación con docente.  
2) Retos (WPForms por reto): `wpforms_process_complete` → `gnf_handle_reto_submission` → `gnf_store_reto_entry` guarda/actualiza fila en `wp_gn_reto_entries` con estado `enviado`; Save & Resume hooks guardan como `en_progreso`.  
3) Evidencias: archivos copiados a `uploads/guardianes/{anio}/{centro}/{reto}/`; EXIF de foto valida año, marca warning y notifica supervisores/docente. Tipos permitidos se restringen por reto.  
4) Checklist: comparado contra ACF; mismatch o requeridos faltantes => estado `correccion`, nota y notificación.  
5) Supervisor: filtra por región del usuario; puede aprobar modificar puntaje o pedir corrección; dispara notificaciones y recalcula puntajes/estrellas.  
6) Docente: ve estado/puntaje, evidencias/PDF, feedback; puede reabrir en corrección (estado vuelve a `en_progreso`).  
7) Reportes: CSV por región/global; helper de reporte final usa solo estados enviado/aprobado.

## Estados del reto
- `no_iniciado` (default)
- `borrador` (reservado)
- `en_progreso` (Save & Resume / reabierto)
- `enviado` (submit completo)
- `aprobado` (supervisor)
- `correccion` (supervisor solicita o mismatch checklist/evidencias)

## Dependencias externas
- WordPress functions: CPT/tax, roles/caps, dbDelta, transients, WP_Query.
- WPForms hooks: `wpforms_process_complete`, `wpforms_field_file_upload_allowed_file_types`, `wpforms_process_validate_file-upload`, Save & Resume hooks (`wpforms_save_resume_after_save`, `wpforms_save_resume_email_sent`, `wpforms_save_resume_partial_entry_created`).
- ACF Pro: `acf_add_local_field_group`, `get_field`, `update_field`, options page.

## Cache e indices
- Índices en tabla: user_id, centro_id, reto_id, estado, anio.
- Transients: totales de centro (`gnf_total_{centro}`), retos aprobados (`gnf_aprobados_{centro}_{anio}`), listados supervisor (`gnf_sup_entries_*`). Limpieza en recalculos y correcciones.

## Seguridad y permisos
- Docentes sin acceso al admin; toolbar oculta.
- Descarga de evidencias valida permisos por centro y ruta limitada al árbol de uploads.
- Supervisores ven solo centros/entradas de su región.

## Notificaciones
- Almacena en `wp_gn_notificaciones`:
  - `correccion`: cuando supervisor pide corrección o mismatch checklist.
  - `aprobado`: reto aprobado.
  - `invalid_photo_date`: evidencias con EXIF fuera de año.

## Personalización requerida post-instalación
- Configurar IDs de formularios en ACF Options y en cada reto (`wpforms_form_id`).
- Asignar región a supervisores (user_meta/ACF) y a centros (taxonomía).
- Ajustar rangos de estrellas y colores/iconos por reto.
- (Opcional) Integrar generación y guardado real de PDF con el add-on WPForms PDF o librería externa en `gnf_generate_docente_pdf_stub`.

---

## Configuración de ACF Fields

El plugin registra automáticamente los campos ACF vía código en `includes/acf-fields.php`. Sin embargo, si necesitas verificar o modificar campos:

### Campos de Centro Educativo (`group_gnf_centro`)

| Campo | Key | Tipo | Descripción |
|-------|-----|------|-------------|
| Código MEP | `field_gnf_codigo_mep` | text | Código del MEP (opcional para privados) |
| Región | `field_gnf_region` | taxonomy (gn_region) | Dirección Regional |
| Circuito | `field_gnf_circuito` | text | Circuito educativo |
| Provincia | `field_gnf_provincia` | text | Provincia |
| Cantón | `field_gnf_canton` | text | Cantón |
| Distrito | `field_gnf_distrito` | text | Distrito |
| Poblado | `field_gnf_poblado` | text | Poblado |
| Dependencia | `field_gnf_dependencia` | text | Tipo de dependencia |
| Zona | `field_gnf_zona` | text | Zona (urbana/rural) |
| Teléfono 1 | `field_gnf_telefono` | text | Teléfono principal |
| Teléfono 2 | `field_gnf_telefono2` | text | Teléfono secundario |
| Dirección | `field_gnf_direccion` | textarea | Dirección completa |
| Dirección Planificación | `field_gnf_direccion_planificacion` | text | Dirección de planificación |
| Puntaje Total | `field_gnf_puntaje_total` | number | Puntaje acumulado (auto) |
| Estrella Final | `field_gnf_estrella_final` | number | Estrellas obtenidas (auto) |
| Estado Centro | `field_gnf_estado_centro` | select | activo/pendiente |
| Docentes Asociados | `field_gnf_docentes_asociados` | user (multi) | Docentes vinculados |

### Campos de Reto (`group_gnf_reto`)

| Campo | Key | Tipo | Descripción |
|-------|-----|------|-------------|
| Descripción | `field_gnf_descripcion` | wysiwyg | Descripción del reto |
| Puntaje Máximo | `field_gnf_puntaje_maximo` | number | Puntos máximos |
| ID Formulario WPForms | `field_gnf_wpforms_form_id` | number | ID del WPForms asociado |
| Checklist | `field_gnf_checklist` | repeater | Items del checklist (debe tener 3 items requeridos) |
| Color del Reto | `field_gnf_color_reto` | color_picker | Color para UI |
| Icono Estático | `field_gnf_icono_estatico` | image (URL) | Icono para cards |
| Icono Animado | `field_gnf_icono_animado` | image (URL) | Icono animado (opcional) |
| Archivo PDF | `field_gnf_archivo_pdf` | file | Guía del reto en PDF |

### Opciones Globales (`group_gnf_options`)

Accede en **WordPress Admin → Guardianes → Configuración**:

| Campo | Key | Descripción |
|-------|-----|-------------|
| Año Actual | `anio_actual` | Año activo del programa (ej: 2025) |
| Matrícula frontend | `includes/matricula.php` | Formulario nativo + submit handler (sin WPForms) |
| Rangos de Estrellas | `rangos_estrellas` | Repeater con min/max/estrellas |
| Videos Tutoriales | `videos_tutoriales` | Repeater con URLs de YouTube |

---

## Importación de Centros Educativos (MEP)

### Paso 1: Preparar el archivo CSV

El CSV debe tener las siguientes columnas (los encabezados pueden variar, el sistema los normaliza):

```csv
DIRECCION REGIONAL,CIRCUITO,CODIGO,NOMBRE,PROVINCIA,CANTON,DISTRITO,POBLADO,DEPENDENCIA,ZONA,DireccionPlan,Telefono1,Telefono2
```

**Ejemplo:**
```csv
SAN JOSE NORTE,01,1234,ESCUELA VERDE ESPERANZA,SAN JOSE,GOICOECHEA,GUADALUPE,CENTRO,PUBLICA,URBANA,"100m norte del parque",2234-5678,8888-1234
```

### Paso 2: Colocar el archivo

Coloca el archivo CSV en la carpeta `seeders/` del plugin:

```
wp-content/plugins/guardianes-formularios/seeders/escuelas-mep.csv
```

### Paso 3: Crear las Direcciones Regionales

Antes de importar, crea las regiones en:
**WordPress Admin → Centros Educativos → Direcciones Regionales**

Las regiones estándar de Costa Rica son:
- ALAJUELA
- CARTAGO
- HEREDIA
- SAN JOSE CENTRAL
- SAN JOSE NORTE
- SAN JOSE OESTE
- DESAMPARADOS
- PURISCAL
- PEREZ ZELEDON
- LIBERIA
- NICOYA
- SANTA CRUZ
- CAÑAS
- PUNTARENAS
- AGUIRRE
- GRANDE DE TERRABA
- COTO
- LIMON
- GUAPILES
- TURRIALBA
- ZONA NORTE NORTE
- SULÁ
- LOS SANTOS
- OCCIDENTE
- PENINSULAR
- SARAPIQUI
- UPALA

### Paso 4: Ejecutar la importación

**Opción A: Vía WP-CLI (recomendado para archivos grandes)**

```bash
cd /path/to/wordpress
wp eval-file wp-content/plugins/guardianes-formularios/seeders/seed-centros-mep.php
```

**Opción B: Vía navegador (para archivos pequeños)**

1. Accede a: `https://tusitio.com/wp-content/plugins/guardianes-formularios/seeders/seed-centros-mep.php`
2. El script mostrará el progreso y resultado

**Opción C: Crear un endpoint temporal**

Agrega este código temporalmente a `functions.php`:

```php
add_action('admin_init', function() {
    if (isset($_GET['gnf_import_centros']) && current_user_can('manage_options')) {
        require_once WP_PLUGIN_DIR . '/guardianes-formularios/seeders/seed-centros-mep.php';
        exit;
    }
});
```

Luego accede a: `https://tusitio.com/wp-admin/?gnf_import_centros=1`

### Paso 5: Verificar la importación

1. Ve a **WordPress Admin → Centros Educativos**
2. Verifica que los centros aparezcan con sus datos
3. Revisa que las regiones estén correctamente asignadas

### Detección de duplicados

El seeder detecta duplicados por:
1. **Código MEP** (si existe): match exacto
2. **Nombre + Región + Circuito**: si no tiene código MEP

Si encuentra un duplicado, actualiza los datos en lugar de crear uno nuevo.

### Resultado esperado

```
=== Importación de Centros MEP ===
Procesando: ESCUELA VERDE ESPERANZA... [NUEVO]
Procesando: LICEO RURAL EL PORVENIR... [ACTUALIZADO]
...
=== Completado ===
Creados: 1,234
Actualizados: 56
Omitidos: 3
Total procesados: 1,293
```

---

## Troubleshooting

### El seeder no encuentra el CSV
- Verifica que el archivo esté en `seeders/escuelas-mep.csv`
- El nombre del archivo es case-sensitive en Linux

### Las regiones no se asignan correctamente
- Los nombres de región en el CSV deben coincidir exactamente con los términos creados
- El sistema normaliza a mayúsculas, pero verifica acentos y caracteres especiales

### Error de memoria
- Para archivos grandes (>5000 filas), usa WP-CLI
- Aumenta `memory_limit` en php.ini si es necesario

### Campos vacíos después de la importación
- Verifica que las columnas del CSV tengan los nombres correctos
- Revisa que los valores no sean solo espacios en blanco
