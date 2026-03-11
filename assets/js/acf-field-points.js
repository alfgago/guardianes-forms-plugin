/**
 * ACF Field Points Auto-Population
 *
 * Listens for WPForms post_object selection changes inside
 * configuracion_por_anio repeater rows. Fetches form fields via AJAX
 * and populates the field_points sub-repeater accordingly.
 */
(function ($) {
  if (typeof acf === 'undefined' || typeof gnfAcfFieldPoints === 'undefined') {
    return;
  }

  var ajaxUrl = gnfAcfFieldPoints.ajaxUrl;
  var nonce = gnfAcfFieldPoints.nonce;

  /**
   * Given a repeater row element, read the field_points sub-repeater rows
   * and return a map of field_id → puntos (preserving user-assigned points).
   */
  function getExistingPoints($row) {
    var map = {};
    $row.find('[data-key="field_gnf_cpa_field_points"] .acf-row:not(.acf-clone)').each(function () {
      var $r = $(this);
      var fid = $r.find('[data-key="field_gnf_cpa_fp_field_id"] input').val();
      var pts = $r.find('[data-key="field_gnf_cpa_fp_puntos"] input').val();
      if (fid) {
        map[fid] = parseInt(pts, 10) || 0;
      }
    });
    return map;
  }

  /**
   * Clear all rows from the field_points sub-repeater inside $row.
   */
  function clearSubRepeater($row) {
    var $repeater = $row.find('[data-key="field_gnf_cpa_field_points"]');
    var field = acf.getField($repeater);
    if (!field) return;

    // Remove all non-clone rows
    $repeater.find('.acf-row:not(.acf-clone)').each(function () {
      field.remove($(this));
    });
  }

  /**
   * Add a row to the field_points sub-repeater.
   */
  function addSubRepeaterRow($row, fieldData, existingPts) {
    var $repeater = $row.find('[data-key="field_gnf_cpa_field_points"]');
    var field = acf.getField($repeater);
    if (!field) return;

    var $newRow = field.add();
    if (!$newRow || !$newRow.length) return;

    $newRow.find('[data-key="field_gnf_cpa_fp_field_id"] input').val(fieldData.field_id);
    $newRow.find('[data-key="field_gnf_cpa_fp_field_label"] input').val(fieldData.label);
    $newRow.find('[data-key="field_gnf_cpa_fp_field_type"] input').val(fieldData.type);

    var pts = existingPts.hasOwnProperty(String(fieldData.field_id))
      ? existingPts[String(fieldData.field_id)]
      : 0;
    $newRow.find('[data-key="field_gnf_cpa_fp_puntos"] input').val(pts);
  }

  /**
   * Recalculate puntaje_total from field_points rows.
   */
  function recalcTotal($row) {
    var total = 0;
    $row.find('[data-key="field_gnf_cpa_field_points"] .acf-row:not(.acf-clone)').each(function () {
      total += parseInt($(this).find('[data-key="field_gnf_cpa_fp_puntos"] input').val(), 10) || 0;
    });
    $row.find('[data-key="field_gnf_cpa_puntaje_total"] input').val(total);
  }

  /**
   * Fetch form fields and populate sub-repeater.
   */
  function loadFormFields($row, formId) {
    if (!formId) {
      clearSubRepeater($row);
      recalcTotal($row);
      return;
    }

    var existingPts = getExistingPoints($row);
    clearSubRepeater($row);

    $.post(ajaxUrl, {
      action: 'gnf_fetch_form_fields',
      nonce: nonce,
      form_id: formId,
    }, function (resp) {
      if (!resp.success || !resp.data) return;

      resp.data.forEach(function (f) {
        addSubRepeaterRow($row, f, existingPts);
      });

      recalcTotal($row);
    });
  }

  // Listen for ACF field changes on wpforms_id post_object inside the repeater.
  acf.addAction('select2_init', function ($select, args, settings, field) {
    if (!field || field.data.key !== 'field_gnf_cpa_wpforms_id') return;

    $select.on('select2:select select2:unselect', function () {
      var val = $select.val();
      var $repeaterRow = $select.closest('.acf-row');
      loadFormFields($repeaterRow, val);
    });
  });

  // Listen for puntos changes to update total.
  $(document).on('input', '[data-key="field_gnf_cpa_fp_puntos"] input', function () {
    var $row = $(this).closest('[data-key="field_gnf_config_por_anio"] > .acf-input > .acf-repeater > .acf-table > tbody > .acf-row');
    if (!$row.length) {
      // Fallback: walk up to the configuracion_por_anio row
      $row = $(this).closest('.acf-row').parents('.acf-row').last();
    }
    recalcTotal($row);
  });

})(jQuery);
