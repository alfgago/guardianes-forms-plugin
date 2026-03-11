<?php
/**
 * Registros "Registrar y Reducir" — Fase 2 (pendiente).
 *
 * Objetivo: sustituir la tabla Excel por centro con un formulario en plataforma
 * donde cada centro carga datos (consumo agua, energía, pesaje residuos por mes).
 * El sistema genera una tabla general (todos los centros) consultable y abierta
 * todo el año (a diferencia del cierre de participación ~30 oct).
 *
 * Requerimientos:
 * - Formulario(s) para cargar datos (por mes o acumulado).
 * - Almacenamiento en BD (tabla custom o entries) por centro y período.
 * - Vista "llenar" (form) y vista "ver" (consultar datos del centro / tabla general).
 * - Enlace desde panel docente (corto plazo ya cubierto con registros_drive_url).
 *
 * Cuando se implemente: registrar shortcode, menú en panel docente, y opción
 * en Guardianes → Configuración para activar/desactivar o reemplazar el link Drive.
 *
 * @package GuardianesFormularios
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Fase 2: aquí se cargarán el formulario de registros, tabla y vistas.
// Por ahora no se registra ningún shortcode ni acción.
