import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { retosApi, type AutosaveFieldPayload, type ConditionalFieldRule, type ConditionalRule } from '@/api/retos';
import { Spinner } from '@/components/ui/Spinner';
import { Alert } from '@/components/ui/Alert';
import { Card } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { EvidenceViewer } from '@/components/domain/EvidenceViewer';
import { StatusBadge } from '@/components/domain/StatusBadge';
import { trackClientEvent } from '@/utils/analytics';
import type { Evidencia, RetoEntry } from '@/types';
import { CheckCircle2, ExternalLink, Save } from 'lucide-react';

interface WpFormsEmbedProps {
  retoId: number;
  year: number;
}

function getFieldId(fieldEl: Element): string | null {
  // Prefer the canonical data-field-id attribute (always present on .wpforms-field)
  const dataId = fieldEl.getAttribute('data-field-id');
  if (dataId) return dataId;
  // Fallback: parse from element ID
  const id = fieldEl.getAttribute('id') ?? '';
  const match = id.match(/field_(\d+)/);
  return match?.[1] ?? null;
}

function getFieldInputs(fieldEl: Element, fieldId: string): Array<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement> {
  return Array.from(
    fieldEl.querySelectorAll<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>(
      `[name^="wpforms[fields][${fieldId}]"]`,
    ),
  );
}

function getFieldLabel(fieldEl: Element, fieldId: string): string {
  const label = fieldEl.querySelector('.wpforms-field-label')?.textContent?.trim();
  return label || `Campo ${fieldId}`;
}

function getFieldType(fieldEl: Element, inputs: Array<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>): string {
  const className = fieldEl.className;

  if (className.includes('wpforms-field-file-upload')) return 'file-upload';
  if (className.includes('wpforms-field-checkbox')) return 'checkbox';
  if (className.includes('wpforms-field-radio')) return 'radio';
  if (className.includes('wpforms-field-textarea')) return 'textarea';
  if (className.includes('wpforms-field-select')) return 'select';
  if (className.includes('wpforms-field-email')) return 'email';

  const firstInput = inputs[0];
  if (!firstInput) return 'text';
  if (firstInput instanceof HTMLTextAreaElement) return 'textarea';
  if (firstInput instanceof HTMLSelectElement) return 'select';
  return firstInput.type || 'text';
}

/**
 * Read the raw DOM value of a field, ignoring visibility.
 * Used by CL evaluation so hidden parent fields still return their real value.
 */
function getFieldValueRaw(fieldEl: HTMLElement, type: string, inputs: Array<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>): string | string[] {
  if (type === 'file-upload') {
    return getFileUploadValue(fieldEl);
  }

  if (type === 'checkbox') {
    return inputs
      .filter((input): input is HTMLInputElement => input instanceof HTMLInputElement)
      .filter((input) => input.checked)
      .map((input) => normalizeBinaryValue(input.value));
  }

  if (type === 'radio') {
    const checked = inputs
      .filter((input): input is HTMLInputElement => input instanceof HTMLInputElement)
      .find((input) => input.checked);
    return normalizeBinaryValue(checked?.value ?? '');
  }

  const select = inputs[0];
  if (select instanceof HTMLSelectElement && select.multiple) {
    return Array.from(select.selectedOptions).map((option) => option.value);
  }

  return select ? normalizeBinaryValue(select.value) : '';
}

/**
 * Read the value of a field for autosave purposes.
 * Returns empty for hidden (CL-invisible) fields to avoid saving stale data.
 */
function getFieldValue(fieldEl: HTMLElement, type: string, inputs: Array<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>): string | string[] {
  const isVisible = fieldEl.offsetParent !== null || window.getComputedStyle(fieldEl).display !== 'none';
  if (!isVisible) {
    return type === 'checkbox' ? [] : '';
  }

  return getFieldValueRaw(fieldEl, type, inputs);
}

/**
 * Get the value of a file-upload field by looking at the Dropzone hidden inputs.
 * WPForms Modern Upload uses `name="wpforms_FORMID_FIELDID"` on the dropzone-input
 * and creates hidden inputs with `name="wpforms[fields][FIELDID][]"` after upload.
 */
function getFileUploadValue(fieldEl: HTMLElement): string | string[] {
  // First: look for hidden inputs matching wpforms[fields][*] created after upload.
  const bracketHiddens = Array.from(fieldEl.querySelectorAll<HTMLInputElement>('input[type="hidden"]'))
    .filter((input) => input.name.includes('wpforms[fields]') && input.value);
  if (bracketHiddens.length > 1) return bracketHiddens.map((i) => i.value);
  if (bracketHiddens.length === 1) return bracketHiddens[0]!.value;

  // Second: check the dropzone-input text field — Dropzone writes uploaded file data there.
  const dzInput = fieldEl.querySelector<HTMLInputElement>('input.dropzone-input');
  if (dzInput && dzInput.value.trim()) return dzInput.value.trim();

  // Third: check any hidden input whose name starts with wpforms_ (the underscore format).
  const underscoreHiddens = Array.from(fieldEl.querySelectorAll<HTMLInputElement>('input[type="hidden"]'))
    .filter((input) => /^wpforms_\d+_\d+/.test(input.name) && input.value);
  if (underscoreHiddens.length) return underscoreHiddens[0]!.value;

  return '';
}

function buildFieldSnapshot(form: HTMLFormElement): Record<string, AutosaveFieldPayload> {
  const fields = Array.from(form.querySelectorAll<HTMLElement>('.wpforms-field'));
  const payload: Record<string, AutosaveFieldPayload> = {};

  fields.forEach((fieldEl) => {
    const fieldId = getFieldId(fieldEl);
    if (!fieldId) return;

    const inputs = getFieldInputs(fieldEl, fieldId);
    const type = getFieldType(fieldEl, inputs);

    // File-upload fields use a different name format (wpforms_FORMID_FIELDID)
    // so getFieldInputs will return 0. Handle them separately.
    if (type === 'file-upload') {
      const allInputs = Array.from(fieldEl.querySelectorAll('input'));
      console.log(`[GNF] buildSnapshot field=${fieldId} type=file-upload`, {
        bracketInputs: inputs.length,
        allInputs: allInputs.length,
        inputNames: allInputs.map((i) => i.name),
        inputValues: allInputs.map((i) => i.value.slice(0, 80)),
      });

      const isVisible = fieldEl.offsetParent !== null || window.getComputedStyle(fieldEl).display !== 'none';
      if (!isVisible) return;

      const value = getFileUploadValue(fieldEl);
      const hasValue = Array.isArray(value) ? value.some((v) => v !== '') : value !== '';
      console.log(`[GNF] buildSnapshot field=${fieldId} file-upload hasValue=${hasValue}`, value);
      if (!hasValue) return;

      payload[fieldId] = {
        type,
        name: getFieldLabel(fieldEl, fieldId),
        value,
      };
      return;
    }

    if (inputs.length === 0) return;

    const value = getFieldValue(fieldEl, type, inputs);

    payload[fieldId] = {
      type,
      name: getFieldLabel(fieldEl, fieldId),
      value,
    };
  });

  console.log('[GNF] buildSnapshot complete', { fieldCount: Object.keys(payload).length, fieldIds: Object.keys(payload), types: Object.fromEntries(Object.entries(payload).map(([k, v]) => [k, v.type])) });
  return payload;
}

function getFieldElementById(form: HTMLFormElement, fieldId: string): HTMLElement | null {
  return form.querySelector<HTMLElement>(`.wpforms-field[data-field-id="${fieldId}"]`);
}

function normalizeConditionalComparison(value: string | string[]): string {
  const raw = Array.isArray(value) ? String(value[0] ?? '') : String(value ?? '');
  return normalizeBinaryValue(raw)
    .trim()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase();
}

function evaluateConditionalRule(actualValue: string | string[], rule: ConditionalRule): boolean {
  const actual = normalizeConditionalComparison(actualValue);
  const expected = normalizeConditionalComparison(rule.value);
  const numericActual = Number(actual);
  const numericExpected = Number(expected);
  const hasNumericValues = !Number.isNaN(numericActual) && !Number.isNaN(numericExpected);

  switch (rule.operator) {
    case 'is':
    case '==':
      return actual === expected;
    case 'is_not':
    case '!=':
      return actual !== expected;
    case 'contains':
      return actual.includes(expected);
    case 'greater_than':
      return hasNumericValues ? numericActual > numericExpected : actual > expected;
    case 'less_than':
      return hasNumericValues ? numericActual < numericExpected : actual < expected;
    default:
      return actual === expected;
  }
}

function applyConditionalVisibility(form: HTMLFormElement, rules: ConditionalFieldRule[]) {
  if (!rules.length) return;

  rules.forEach((fieldRule) => {
    const fieldEl = getFieldElementById(form, String(fieldRule.fieldId));
    if (!fieldEl) return;

    const matchesAnyGroup = fieldRule.groups.some((group) =>
      group.every((rule) => {
        const parentFieldEl = getFieldElementById(form, String(rule.fieldId));
        if (!parentFieldEl) return false;

        const inputs = getFieldInputs(parentFieldEl, String(rule.fieldId));
        if (!inputs.length) return false;

        const type = getFieldType(parentFieldEl, inputs);
        // Use getFieldValueRaw — parent may be temporarily hidden by native CL
        // but we still need its real value to evaluate the rule correctly.
        const actualValue = getFieldValueRaw(parentFieldEl, type, inputs);
        return evaluateConditionalRule(actualValue, rule);
      }),
    );

    const shouldShow = fieldRule.conditionalType === 'hide' ? !matchesAnyGroup : matchesAnyGroup;
    fieldEl.style.display = shouldShow ? '' : 'none';
    fieldEl.setAttribute('aria-hidden', shouldShow ? 'false' : 'true');
  });
}

function hydrateSavedValues(form: HTMLFormElement, savedValues: Record<string, string | string[]>) {
  const fieldIds = Object.keys(savedValues).sort((a, b) => Number(a) - Number(b));

  console.log('[GNF] hydrateSavedValues — start', { fieldCount: fieldIds.length, fieldIds });

  fieldIds.forEach((fieldId) => {
    const fieldEl = getFieldElementById(form, fieldId);
    if (!fieldEl) {
      console.log(`[GNF] hydrate field=${fieldId} — element NOT FOUND`);
      return;
    }

    const inputs = getFieldInputs(fieldEl, fieldId);
    if (inputs.length === 0) return;

    const rawValue = savedValues[fieldId];
    const values = (Array.isArray(rawValue) ? rawValue : [rawValue]).map((value) => normalizeBinaryValue(String(value)));
    const type = getFieldType(fieldEl, inputs);

    if (type === 'checkbox') {
      inputs
        .filter((input): input is HTMLInputElement => input instanceof HTMLInputElement)
        .forEach((input) => {
          input.checked = values.includes(normalizeBinaryValue(input.value));
        });
    } else if (type === 'radio') {
      inputs
        .filter((input): input is HTMLInputElement => input instanceof HTMLInputElement)
        .forEach((input) => {
          const match = values[0] === normalizeBinaryValue(input.value);
          input.checked = match;
          // WPForms uses wpforms-selected on the parent <li> for styling.
          const li = input.closest('li');
          if (li) {
            if (match) {
              li.classList.add('wpforms-selected');
            } else {
              li.classList.remove('wpforms-selected');
            }
          }
        });
      console.log(`[GNF] hydrate field=${fieldId} type=radio saved="${values[0]}" → checked="${values[0]}"`);
    } else if (type === 'select') {
      const selectEl = inputs[0];
      if (selectEl instanceof HTMLSelectElement) {
        Array.from(selectEl.options).forEach((option) => {
          option.selected = values.includes(normalizeBinaryValue(option.value));
        });
      }
    } else if (type !== 'file-upload') {
      const input = inputs[0];
      if (input) {
        input.value = values[0] ?? '';
      }
    }
  });
}

function translateCommonWpformsCopy(container: HTMLElement) {
  const replacements = new Map<string, string>([
    ['Drag and drop files here or click to upload.', 'Arrastra los archivos aqui o haz clic para subirlos.'],
    ['Drop files here or click to upload.', 'Arrastra los archivos aqui o haz clic para subirlos.'],
    ['Click or drag a file to this area to upload.', 'Haz clic o arrastra un archivo a esta area para subirlo.'],
    ['Choose File', 'Seleccionar archivo'],
    ['No file chosen', 'Ningun archivo seleccionado'],
  ]);

  container.querySelectorAll<HTMLElement>('*').forEach((element) => {
    if (element.childNodes.length !== 1 || element.childNodes[0]?.nodeType !== Node.TEXT_NODE) {
      return;
    }

    const current = element.textContent?.trim() ?? '';
    const replacement = replacements.get(current);
    if (replacement) {
      element.textContent = replacement;
    }
  });
}

function normalizeBinaryValue(value: string): string {
  const normalized = value
    .trim()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase();

  if (normalized === 'si') return 'si';
  if (normalized === 'no') return 'no';
  return value;
}

function formatBinaryLabel(value: string): string {
  const normalized = normalizeBinaryValue(value);
  if (normalized === 'si') return 'Sí';
  if (normalized === 'no') return 'No';
  return value;
}

function normalizeWpformsBinaryChoices(container: HTMLElement) {
  container.querySelectorAll<HTMLLabelElement>('.wpforms-field-radio li label, .wpforms-field-checkbox li label').forEach((label) => {
    const input = label.parentElement?.querySelector<HTMLInputElement>('input');
    const rawLabel = label.textContent?.trim() ?? '';
    const normalizedValue = normalizeBinaryValue(input?.value || rawLabel);

    if (normalizedValue === 'si' || normalizedValue === 'no') {
      label.textContent = formatBinaryLabel(rawLabel || normalizedValue);
    }
  });

  container.querySelectorAll<HTMLOptionElement>('select option').forEach((option) => {
    const normalizedValue = normalizeBinaryValue(option.value || option.textContent || '');
    if (normalizedValue === 'si' || normalizedValue === 'no') {
      option.textContent = formatBinaryLabel(option.textContent || normalizedValue);
    }
  });

  container.querySelectorAll<HTMLElement>('.wpforms-field-label, .wpforms-field-description').forEach((element) => {
    const text = element.textContent ?? '';
    element.textContent = text.replace(/\(si\/no\)/gi, '(Sí/No)');
  });
}

function extractFieldValues(snapshot: Record<string, AutosaveFieldPayload>) {
  return Object.fromEntries(Object.entries(snapshot).map(([fieldId, field]) => [fieldId, field.value]));
}

/**
 * For each file-upload field, show a preview of previously uploaded files
 * based on the entry's evidencias. This lets the user see what's already
 * attached without re-uploading.
 */
function injectFileUploadPreviews(form: HTMLFormElement, evidencias: Array<{ field_id?: number; filename?: string; nombre?: string; url?: string; ruta?: string; tipo?: string; type?: string }>) {
  if (!evidencias.length) return;

  // Group evidencias by field_id.
  const byField = new Map<number, typeof evidencias>();
  evidencias.forEach((ev) => {
    const fid = ev.field_id;
    if (!fid) return;
    if (!byField.has(fid)) byField.set(fid, []);
    byField.get(fid)!.push(ev);
  });

  byField.forEach((files, fieldId) => {
    const fieldEl = getFieldElementById(form, String(fieldId));
    if (!fieldEl || !fieldEl.classList.contains('wpforms-field-file-upload')) return;

    // Don't inject twice.
    if (fieldEl.querySelector('.gnf-file-preview')) return;

    const previewDiv = document.createElement('div');
    previewDiv.className = 'gnf-file-preview';
    previewDiv.style.cssText = 'margin-top:8px;display:flex;flex-wrap:wrap;gap:8px;';

    files.forEach((file) => {
      const name = file.filename || file.nombre || 'archivo';
      const url = file.url || file.ruta || '';
      const tipo = file.tipo || file.type || '';
      const isImage = tipo === 'imagen' || /\.(jpg|jpeg|png|gif|webp)$/i.test(name);

      const item = document.createElement('div');
      item.style.cssText = 'display:flex;align-items:center;gap:6px;padding:6px 10px;border-radius:8px;background:rgba(34,197,94,0.08);border:1px solid rgba(34,197,94,0.25);font-size:0.8125rem;color:#166534;max-width:280px;';

      if (isImage && url) {
        const thumb = document.createElement('img');
        thumb.src = url;
        thumb.alt = name;
        thumb.style.cssText = 'width:32px;height:32px;object-fit:cover;border-radius:4px;flex-shrink:0;';
        item.appendChild(thumb);
      } else {
        const icon = document.createElement('span');
        icon.textContent = '📎';
        icon.style.cssText = 'flex-shrink:0;';
        item.appendChild(icon);
      }

      const label = document.createElement('span');
      label.textContent = name;
      label.style.cssText = 'overflow:hidden;text-overflow:ellipsis;white-space:nowrap;';
      item.appendChild(label);

      previewDiv.appendChild(item);
    });

    // Insert before the uploader or at the end of the field.
    const uploader = fieldEl.querySelector('.wpforms-uploader');
    if (uploader) {
      uploader.parentNode?.insertBefore(previewDiv, uploader);
    } else {
      fieldEl.appendChild(previewDiv);
    }
  });
}

function getFileSelectionLimit(fieldEl: HTMLElement): number {
  const uploader = fieldEl.querySelector<HTMLElement>('.wpforms-uploader');
  const fileInput = fieldEl.querySelector<HTMLInputElement>('input[type="file"]');
  const candidates = [
    uploader?.getAttribute('data-max-file-number'),
    uploader?.getAttribute('data-max-files'),
    uploader?.getAttribute('data-file-limit'),
    fileInput?.getAttribute('data-max-file-number'),
    fileInput?.getAttribute('data-max-files'),
    fileInput?.getAttribute('data-file-limit'),
    fieldEl.getAttribute('data-max-file-number'),
    fieldEl.getAttribute('data-max-files'),
    fieldEl.getAttribute('data-file-limit'),
  ];

  for (const value of candidates) {
    const parsed = Number(value ?? '');
    if (Number.isFinite(parsed) && parsed > 0) {
      return parsed;
    }
  }

  return 0;
}

function getCurrentEvidenceCount(fieldEl: HTMLElement): number {
  return fieldEl.querySelectorAll('.gnf-file-preview > div').length;
}

function showFileLimitMessage(fieldEl: HTMLElement, message: string) {
  let hint = fieldEl.querySelector<HTMLElement>('.gnf-file-limit-hint');
  if (!hint) {
    hint = document.createElement('div');
    hint.className = 'gnf-file-limit-hint';
    hint.style.cssText = 'margin-top:8px;color:#b45309;font-size:0.8125rem;font-weight:600;';
    fieldEl.appendChild(hint);
  }

  hint.textContent = message;
}

function enforceFileSelectionLimits(form: HTMLFormElement) {
  form.querySelectorAll<HTMLElement>('.wpforms-field-file-upload').forEach((fieldEl) => {
    const fileInput = fieldEl.querySelector<HTMLInputElement>('input[type="file"]');
    if (!fileInput || fileInput.dataset.gnfLimitBound === '1') return;

    const limit = getFileSelectionLimit(fieldEl);
    if (!limit) return;

    fileInput.dataset.gnfLimitBound = '1';
    fileInput.addEventListener('change', () => {
      const selected = fileInput.files?.length ?? 0;
      const current = getCurrentEvidenceCount(fieldEl);
      const remaining = Math.max(limit - current, 0);

      if (selected > remaining && selected > 0) {
        fileInput.value = '';
        showFileLimitMessage(
          fieldEl,
          remaining > 0
            ? `Solo puedes subir ${remaining} archivo${remaining === 1 ? '' : 's'} más en este campo.`
            : 'Ya alcanzaste el máximo de archivos permitidos en este campo.',
        );
      }
    });
  });
}

function hasCompletedValue(value: string | string[] | undefined, tipo: string): boolean {
  if (Array.isArray(value)) {
    return value.some((item) => String(item).trim() !== '');
  }

  const normalized = String(value ?? '').trim();
  if (normalized === '') {
    return false;
  }

  if (tipo === 'file-upload') {
    return normalized !== '' && normalized !== '[]';
  }

  return true;
}

function formatSavedStamp(savedAt?: string | null) {
  if (!savedAt) return '';
  const date = new Date(savedAt);
  if (Number.isNaN(date.getTime())) return '';

  return date.toLocaleString('es-CR', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

/**
 * Try to initialize the WPForms Modern File Upload (Dropzone) on upload zones
 * that haven't been activated yet. WPForms Pro splits the uploader into a
 * separate module that runs on DOMContentLoaded, so dynamically injected forms
 * miss that window. We re-trigger the initialization here.
 *
 * IMPORTANT: We intentionally do NOT fire `wpformsReady` on `document`,
 * because that event activates ALL WPForms sub-modules — including the
 * native conditional logic engine which conflicts with our custom CL.
 */
function initModernFileUploads(container: HTMLElement) {
  const win = window as unknown as Record<string, unknown>;
  const jq = win.jQuery as
    | ((sel: unknown) => {
        trigger: (evt: string) => void;
        find: (sel: string) => { each: (fn: (this: HTMLElement) => void) => void; length: number };
        length: number;
      })
    | undefined;

  if (!jq) {
    console.log('[GNF] initModernFileUploads — no jQuery');
    return;
  }

  // WPForms Modern Upload stores its constructor on the global scope.
  // If available, tell it to scan for uninitialised upload zones.
  const modernUploadClass = win.WPFormsModernFileUpload as { init?: () => void } | undefined;
  if (modernUploadClass?.init) {
    console.log('[GNF] initModernFileUploads — calling WPFormsModernFileUpload.init()');
    modernUploadClass.init();
    return;
  }

  // Fallback: find upload containers without a Dropzone instance and
  // trigger the per-form loaded event (not the global wpformsReady).
  const uploaders = container.querySelectorAll<HTMLElement>('.wpforms-uploader');
  if (!uploaders.length) {
    console.log('[GNF] initModernFileUploads — no .wpforms-uploader elements found');
    return;
  }

  const needsInit = Array.from(uploaders).some((el) => {
    // WPForms marks initialised uploaders with a Dropzone instance on the element.
    return !(el as unknown as Record<string, unknown>).dropzone;
  });

  console.log(`[GNF] initModernFileUploads — ${uploaders.length} uploaders, needsInit=${needsInit}`);

  if (needsInit) {
    const formEl = container.querySelector('.wpforms-form');
    if (formEl) {
      console.log('[GNF] initModernFileUploads — triggering wpformsFormLoaded on form');
      jq(formEl).trigger('wpformsFormLoaded');
    }
  }
}

/**
 * Aggressively strip all WPForms conditional-logic artefacts from the
 * container so the native CL engine cannot manage visibility. Our own
 * `applyConditionalVisibility` handles show/hide instead — the native CL
 * engine conflicts because its configuration data isn't present in forms
 * fetched via REST API, causing it to hide all conditional fields.
 */
function disableNativeConditionalLogic(container: HTMLElement) {
  // Strip all CL-related CSS classes.
  container.querySelectorAll('.wpforms-conditional-trigger').forEach((el) => {
    el.classList.remove('wpforms-conditional-trigger');
  });
  container.querySelectorAll('.wpforms-conditional-field').forEach((el) => {
    el.classList.remove('wpforms-conditional-field', 'wpforms-conditional-show', 'wpforms-conditional-hide');
  });

  // Force ALL .wpforms-field elements visible — our applyConditionalVisibility
  // is the sole manager of display. This catches both CL-classed elements and
  // any field hidden via inline style by WPForms server rendering or JS.
  container.querySelectorAll<HTMLElement>('.wpforms-field').forEach((el) => {
    if (el.style.display === 'none') {
      el.style.display = '';
    }
  });
}

/**
 * Temporarily nullify WPForms' conditional-logic module constructors so
 * that `wpforms.init()` skips CL initialisation entirely. Restores the
 * originals afterwards so other WPForms pages aren't affected.
 */
function withNativeCLDisabled<T>(fn: () => T): T {
  const win = window as unknown as Record<string, unknown>;
  const clKeys = [
    'WPFormsConditionalLogicField',
    'WPFormsConditionalLogic',
    'wpforms_conditional_logic',
  ];

  const saved = new Map<string, unknown>();
  clKeys.forEach((key) => {
    if (key in win) {
      saved.set(key, win[key]);
      win[key] = undefined;
    }
  });

  try {
    return fn();
  } finally {
    saved.forEach((val, key) => {
      win[key] = val;
    });
  }
}

/**
 * Try to initialize WPForms JS (validation, uploader, etc.) with retries.
 *
 * WPForms Pro splits its frontend into multiple scripts: base validation,
 * modern file upload (Dropzone), conditional logic, etc. We strip the CL
 * artefacts and nullify the CL module globals so the native CL engine
 * never interferes — our `applyConditionalVisibility` handles it instead.
 *
 * @param onReady Callback invoked after wpforms.init() succeeds. Used to
 *                re-hydrate saved values since wpforms.init() may reset fields.
 */
function initWpForms(container: HTMLElement, onReady?: () => void, maxRetries = 12) {
  // Prevent native CL from running — we handle it ourselves.
  disableNativeConditionalLogic(container);

  // Fix WPForms seeder bug: limit_enabled=1 without limit_count defaults
  // to maxlength="1", making text/textarea fields accept only 1 character.
  container.querySelectorAll<HTMLInputElement | HTMLTextAreaElement>('input[maxlength="1"], textarea[maxlength="1"]').forEach((el) => {
    el.removeAttribute('maxlength');
    if (el.getAttribute('data-text-limit') === '1') {
      el.removeAttribute('data-text-limit');
    }
  });

  let attempts = 0;
  const tryInit = () => {
    const wpforms = (window as unknown as Record<string, unknown>).wpforms as { init?: () => void } | undefined;
    if (wpforms?.init) {
      console.log('[GNF] initWpForms — wpforms.init() available, calling with CL disabled');
      // Run wpforms.init() with CL module constructors nullified so the
      // native CL engine never activates.
      withNativeCLDisabled(() => wpforms.init!());

      // Re-strip any CL artefacts that wpforms.init() may have re-added.
      disableNativeConditionalLogic(container);

      initModernFileUploads(container);
      normalizeWpformsBinaryChoices(container);
      translateCommonWpformsCopy(container);

      // Re-hydrate saved values — wpforms.init() may have reset field states.
      console.log('[GNF] initWpForms — calling onReady callback to re-hydrate');
      onReady?.();
      return;
    }
    attempts++;
    if (attempts < maxRetries) {
      console.log(`[GNF] initWpForms — wpforms not ready, retry ${attempts}/${maxRetries}`);
      window.setTimeout(tryInit, Math.min(attempts * 200, 1200));
    } else {
      console.warn('[GNF] initWpForms — max retries reached, wpforms.init() never became available');
      // Even without wpforms.init(), still call onReady so hydration runs.
      onReady?.();
    }
  };
  tryInit();
}

export function WpFormsEmbed({ retoId, year }: WpFormsEmbedProps) {
  const queryClient = useQueryClient();
  const containerRef = useRef<HTMLDivElement>(null);
  const formRef = useRef<HTMLFormElement | null>(null);
  const saveTimerRef = useRef<number | null>(null);
  const hydratingRef = useRef(false);
  const queuedSaveRef = useRef(false);
  const lastSnapshotRef = useRef('');
  const mountedRef = useRef(false);
  const savingRef = useRef(false);
  const dataRef = useRef<Awaited<ReturnType<typeof retosApi.getFormHtml>> | null>(null);

  const [saveState, setSaveState] = useState<'idle' | 'saving' | 'saved' | 'error'>('idle');
  const [lastSavedAt, setLastSavedAt] = useState<string | null>(null);
  const [currentEntry, setCurrentEntry] = useState<RetoEntry | null>(null);
  const [fieldValues, setFieldValues] = useState<Record<string, string | string[]>>({});

  const { data, isLoading, error } = useQuery({
    queryKey: ['wpforms-html', retoId, year],
    queryFn: () => retosApi.getFormHtml(retoId, year),
    staleTime: 0,
    refetchOnWindowFocus: false,
    refetchOnReconnect: false,
  });

  useEffect(() => {
    mountedRef.current = true;
    return () => {
      mountedRef.current = false;
    };
  }, []);

  useEffect(() => {
    dataRef.current = data ?? null;
  });

  useEffect(() => {
    setLastSavedAt(data?.savedAt ?? null);
    setCurrentEntry(data?.entry ?? null);
    setFieldValues(data?.savedValues ?? {});
    setSaveState('idle');
  }, [data?.entry, data?.savedAt, data?.savedValues]);

  // Stable: no mutation objects in deps, only primitive props.
  const triggerAutosave = useCallback(
    async (mode: 'auto' | 'manual') => {
      const form = formRef.current;
      const currentData = dataRef.current;
      if (!form || !currentData) {
        console.log(`[GNF] triggerAutosave(${mode}) SKIPPED — no form or data`);
        return;
      }

      console.log(`[GNF] triggerAutosave(${mode}) building snapshot...`);
      const snapshot = buildFieldSnapshot(form);
      if (mountedRef.current) {
        setFieldValues(extractFieldValues(snapshot));
      }
      const serialized = JSON.stringify(snapshot);

      if (mode === 'auto' && serialized === lastSnapshotRef.current) {
        console.log(`[GNF] triggerAutosave(${mode}) SKIPPED — no changes`);
        return;
      }
      if (savingRef.current) {
        console.log(`[GNF] triggerAutosave(${mode}) QUEUED — save in progress`);
        queuedSaveRef.current = true;
        return;
      }

      savingRef.current = true;
      if (mountedRef.current) setSaveState('saving');

      console.log(`[GNF] triggerAutosave(${mode}) SENDING`, { formId: currentData.formId, fieldCount: Object.keys(snapshot).length, fields: snapshot });

      try {
        const response = await retosApi.autosaveReto(retoId, year, currentData.formId, snapshot);
        lastSnapshotRef.current = serialized;

        console.log(`[GNF] triggerAutosave(${mode}) RESPONSE`, {
          success: response.success,
          savedAt: response.savedAt,
          puntaje: response.entry?.puntaje,
          puntajeMaximo: response.entry?.puntajeMaximo,
          estado: response.entry?.estado,
          evidencias: response.entry?.evidencias?.length ?? 0,
        });

        if (mountedRef.current) {
          setLastSavedAt(response.savedAt ?? new Date().toISOString());
          setCurrentEntry(response.entry);
          setSaveState('saved');
        }

        queryClient.invalidateQueries({ queryKey: ['wizard-steps', year] });
        queryClient.invalidateQueries({ queryKey: ['docente-retos', year] });
        queryClient.invalidateQueries({ queryKey: ['docente-dashboard', year] });

        trackClientEvent('form_autosave', {
          panel: 'docente',
          page: 'formularios',
          year,
          retoId,
          formId: currentData.formId,
          status: response.entry?.estado ?? '',
        });
      } catch (err) {
        console.error(`[GNF] triggerAutosave(${mode}) FAILED`, err);
        if (mountedRef.current) setSaveState('error');
      } finally {
        savingRef.current = false;
        if (queuedSaveRef.current) {
          queuedSaveRef.current = false;
          void triggerAutosave('auto');
        }
      }
    },
    [retoId, year, queryClient],
  );

  // Stable: depends only on triggerAutosave (which is stable).
  const scheduleAutosave = useCallback(
    (delay = 700) => {
      if (hydratingRef.current) return;
      if (saveTimerRef.current) {
        window.clearTimeout(saveTimerRef.current);
      }
      saveTimerRef.current = window.setTimeout(() => {
        void triggerAutosave('auto');
      }, delay);
    },
    [triggerAutosave],
  );

  // ── Form injection + event binding ─────────────────────────────────
  // All callback deps (scheduleAutosave, syncFieldValues, triggerAutosave)
  // are now STABLE — they only change when retoId/year change, NOT on
  // every autosave cycle. This prevents the form from being destroyed
  // and rebuilt mid-interaction (the radio-button-unchecking bug).
  useEffect(() => {
    if (!data?.html || !containerRef.current) return;

    const container = containerRef.current;
    container.innerHTML = data.html;

    const form = container.querySelector('form');
    if (!(form instanceof HTMLFormElement)) {
      console.warn('[GNF] No <form> element found in container');
      formRef.current = null;
      return;
    }

    formRef.current = form;
    hydratingRef.current = true;

    const allFields = form.querySelectorAll('.wpforms-field');
    const fileFields = form.querySelectorAll('.wpforms-field-file-upload');
    console.log(`[GNF] Form mounted — ${allFields.length} fields total, ${fileFields.length} file-upload fields, conditionalRules=${(data.conditionalRules ?? []).length}`);

    const submitContainer = form.querySelector<HTMLElement>('.wpforms-submit-container');
    if (submitContainer) {
      submitContainer.style.display = 'none';
    }

    // Show thumbnails/names of previously uploaded files so the user
    // can verify what's already attached without re-uploading.
    const entryEvidencias = (data.entry?.evidencias ?? []) as Array<{ field_id?: number; filename?: string; nombre?: string; url?: string; ruta?: string; tipo?: string; type?: string }>;
    injectFileUploadPreviews(form, entryEvidencias);
    enforceFileSelectionLimits(form);

    // ── Full re-hydration (values + CL) ────────────────────────────
    // Used on initial mount and once after wpforms.init() completes
    // (which may reset radio buttons). NOT used by the enforcement
    // timer, otherwise it would overwrite the user's edits.
    const rehydrateValues = () => {
      console.log('[GNF] rehydrateValues — applying saved values + CL');
      hydratingRef.current = true;

      if (data.savedValues && Object.keys(data.savedValues).length > 0) {
        hydrateSavedValues(form, data.savedValues);
      }

      disableNativeConditionalLogic(container);
      applyConditionalVisibility(form, data.conditionalRules ?? []);

      window.setTimeout(() => {
        hydratingRef.current = false;
      }, 150);
    };

    // ── CL-only enforcement (no value reset) ────────────────────────
    // Safe to call repeatedly — just strips native CL and re-applies
    // our visibility rules based on current field values.
    const enforceVisibility = () => {
      disableNativeConditionalLogic(container);
      applyConditionalVisibility(form, data.conditionalRules ?? []);
    };

    // Initial hydration.
    rehydrateValues();

    const initialSnapshot = buildFieldSnapshot(form);
    setFieldValues(extractFieldValues(initialSnapshot));
    lastSnapshotRef.current = JSON.stringify(initialSnapshot);

    // ── Execute embedded scripts + init WPForms ─────────────────────
    // initWpForms callback = full re-hydration ONCE after wpforms.init().
    // The enforcement timer only does CL enforcement (no value overwrite).
    const scripts = container.querySelectorAll('script');
    scripts.forEach((oldScript) => {
      const newScript = document.createElement('script');
      if (oldScript.src) {
        newScript.src = oldScript.src;
        newScript.addEventListener('load', () => initWpForms(container, rehydrateValues));
      } else {
        newScript.textContent = oldScript.textContent;
      }
      oldScript.parentNode?.replaceChild(newScript, oldScript);
    });

    initWpForms(container, rehydrateValues);

    // Delayed retry: re-trigger file upload init after scripts settle.
    const uploadRetryTimer = window.setTimeout(() => {
      initModernFileUploads(container);
      enforceVisibility();
    }, 1500);

    // ── Event handlers ───────────────────────────────────────────────
    // `input` fires on every keystroke — update CL only, NO autosave.
    const handleInput = () => {
      applyConditionalVisibility(form, data.conditionalRules ?? []);
    };

    // `change` fires when a field loses focus (text) or on click (radio/select/checkbox).
    // This is the ONLY event that triggers autosave (besides file upload detection).
    const handleChange = (event: Event) => {
      const target = event.target;
      applyConditionalVisibility(form, data.conditionalRules ?? []);

      if (target instanceof HTMLInputElement && target.type === 'file') {
        // File input changes don't mean upload is done — Dropzone uploads async.
        return;
      }

      console.log('[GNF] handleChange — scheduling autosave');
      scheduleAutosave(800);
    };

    const handleSubmit = (event: Event) => {
      event.preventDefault();
      event.stopPropagation();
      void triggerAutosave('manual');
    };

    form.addEventListener('input', handleInput);
    form.addEventListener('change', handleChange);
    form.addEventListener('submit', handleSubmit, true);

    // ── MutationObserver ────────────────────────────────────────────
    const observer = new MutationObserver(() => {
      if (hydratingRef.current) return;
      applyConditionalVisibility(form, data.conditionalRules ?? []);
    });
    observer.observe(form, {
      childList: true,
      subtree: true,
    });

    // ── CL enforcement timer ────────────────────────────────────────
    // Only strips native CL classes and re-applies visibility.
    // Does NOT re-hydrate values — that would overwrite user edits.
    let enforcementCount = 0;
    const enforcementTimer = window.setInterval(() => {
      enforceVisibility();
      enforcementCount++;
      if (enforcementCount >= 8) {
        window.clearInterval(enforcementTimer);
      }
    }, 500);

    // ── File upload detection ────────────────────────────────────────
    // WPForms Modern Upload uses Dropzone. The jQuery events we tried
    // previously don't always fire for REST-injected forms. Instead:
    // 1. Attach to Dropzone instance events directly (with retry)
    // 2. Keep jQuery events as fallback
    // 3. Poll .dropzone-input values as last resort

    const jq = (window as unknown as Record<string, unknown>).jQuery as
      | { (sel: unknown): { on: (evt: string, fn: () => void) => void; off: (evt: string) => void }; }
      | undefined;

    const onFileUploaded = () => {
      console.log('[GNF] file upload detected — scheduling autosave');
      if (!hydratingRef.current) {
        scheduleAutosave(500);
      }
    };

    // Strategy 1: Attach directly to Dropzone instances on each uploader.
    const attachedUploaders = new Set<HTMLElement>();
    const attachDropzoneListeners = () => {
      container.querySelectorAll<HTMLElement>('.wpforms-uploader').forEach((el) => {
        if (attachedUploaders.has(el)) return;
        const dz = (el as unknown as Record<string, unknown>).dropzone as
          | { on: (evt: string, fn: (...args: unknown[]) => void) => void }
          | undefined;
        if (!dz) return;

        attachedUploaders.add(el);
        console.log('[GNF] Attached Dropzone listener on field', el.getAttribute('data-field-id'));
        dz.on('success', () => {
          console.log('[GNF] Dropzone success event');
          onFileUploaded();
        });
        dz.on('removedfile', () => {
          console.log('[GNF] Dropzone removedfile event');
          onFileUploaded();
        });
      });
    };

    // Try immediately and retry several times as Dropzone inits async.
    attachDropzoneListeners();
    const dzRetryTimers = [500, 1500, 3000, 5000].map((delay) =>
      window.setTimeout(() => attachDropzoneListeners(), delay),
    );

    // Strategy 2: jQuery events as fallback (may work in some WPForms versions).
    if (jq) {
      jq(document).on('wpformsModernFileUploadFileComplete.gnfEmbed', onFileUploaded);
      jq(document).on('wpformsModernFileUploadAllComplete.gnfEmbed', onFileUploaded);
    }

    // Strategy 3: Poll .dropzone-input values for changes (last resort).
    // Check every 3 seconds whether any dropzone-input values changed.
    const lastDropzoneValues = new Map<string, string>();
    container.querySelectorAll<HTMLInputElement>('input.dropzone-input').forEach((input) => {
      lastDropzoneValues.set(input.name, input.value);
    });
    const dzPollTimer = window.setInterval(() => {
      let changed = false;
      container.querySelectorAll<HTMLInputElement>('input.dropzone-input').forEach((input) => {
        const prev = lastDropzoneValues.get(input.name) ?? '';
        if (input.value !== prev) {
          console.log(`[GNF] dropzone-input poll: ${input.name} changed`, { prev: prev.slice(0, 40), now: input.value.slice(0, 40) });
          lastDropzoneValues.set(input.name, input.value);
          changed = true;
        }
      });
      if (changed) {
        onFileUploaded();
      }
    }, 3000);

    // ── Cleanup ──────────────────────────────────────────────────────
    return () => {
      window.clearTimeout(uploadRetryTimer);
      window.clearInterval(enforcementTimer);
      window.clearInterval(dzPollTimer);
      dzRetryTimers.forEach((t) => window.clearTimeout(t));
      form.removeEventListener('input', handleInput);
      form.removeEventListener('change', handleChange);
      form.removeEventListener('submit', handleSubmit, true);
      observer.disconnect();

      if (jq) {
        jq(document).off('wpformsModernFileUploadFileComplete.gnfEmbed');
        jq(document).off('wpformsModernFileUploadAllComplete.gnfEmbed');
      }

      // Flush pending autosave with the OLD form values before teardown.
      if (saveTimerRef.current) {
        window.clearTimeout(saveTimerRef.current);
        saveTimerRef.current = null;
      }

      if (!savingRef.current) {
        const fields = buildFieldSnapshot(form);
        const serialized = JSON.stringify(fields);
        if (serialized !== lastSnapshotRef.current) {
          lastSnapshotRef.current = serialized;
          const formId = dataRef.current?.formId;
          if (formId) {
            retosApi.autosaveReto(retoId, year, formId, fields).catch(() => {});
          }
        }
      }
    };
  }, [data?.conditionalRules, data?.html, data?.savedValues, retoId, scheduleAutosave, triggerAutosave]);

  // ── Derived values ─────────────────────────────────────────────────
  const saveStatusLabel = useMemo(() => {
    if (saveState === 'saving') return 'Guardando cambios...';
    if (saveState === 'saved' && lastSavedAt) return `Guardado automatico: ${formatSavedStamp(lastSavedAt)}`;
    if (saveState === 'error') return 'No se pudo guardar. Revisa la conexion y vuelve a intentarlo.';
    return 'El progreso se guarda automaticamente, incluso cuando subes evidencias.';
  }, [lastSavedAt, saveState]);

  const evidenceList = (currentEntry?.evidencias ?? []) as Evidencia[];
  const isEditable = !currentEntry?.estado || !['enviado', 'aprobado'].includes(currentEntry.estado);

  const removeEvidenceMutation = useMutation({
    mutationFn: (index: number) => retosApi.removeEvidence(retoId, year, index),
    onSuccess: (res) => {
      if (res.entry) {
        setCurrentEntry(res.entry);
      }
      queryClient.invalidateQueries({ queryKey: ['docente-reto-form', retoId, year] });
    },
  });

  const handleRemoveEvidence = useCallback(
    (index: number) => {
      removeEvidenceMutation.mutate(index);
    },
    [removeEvidenceMutation],
  );

  const completedFieldIds = useMemo(() => {
    const ids = new Set<string>();
    const activeEvidenceByField = new Set<string>();

    evidenceList.forEach((item) => {
      if (item.replaced || item.estado === 'rechazada') {
        return;
      }

      const fieldId = String(item.field_id ?? '');
      if (fieldId) {
        activeEvidenceByField.add(fieldId);
      }
    });

    data?.fieldPoints.forEach((fieldPoint) => {
      const fieldId = String(fieldPoint.fieldId);
      if (fieldPoint.tipo === 'file-upload' || fieldPoint.tipo === 'file') {
        if (activeEvidenceByField.has(fieldId)) {
          ids.add(fieldId);
        }
        return;
      }

      if (hasCompletedValue(fieldValues[fieldId], fieldPoint.tipo)) {
        ids.add(String(fieldPoint.fieldId));
      }
    });

    return ids;
  }, [data?.fieldPoints, evidenceList, fieldValues]);

  if (isLoading) return <Spinner label="Cargando formulario..." />;
  if (error || !data) return <Alert variant="error">Error al cargar el formulario.</Alert>;

  return (
    <div>
      <style>{`
        .gnf-wpforms-shell .wpforms-container-full,
        .gnf-wpforms-shell .wpforms-form {
          margin: 0;
        }

        .gnf-wpforms-shell .wpforms-field {
          border: 1px solid var(--gnf-border);
          border-radius: var(--gnf-radius);
          background: #fff;
          padding: var(--gnf-space-5) !important;
          margin-bottom: var(--gnf-space-4);
          box-shadow: var(--gnf-shadow-sm);
        }

        .gnf-wpforms-shell .wpforms-field-label {
          display: block;
          font-weight: 700;
          color: var(--gnf-gray-900);
          margin-bottom: var(--gnf-space-4);
          padding-right: var(--gnf-space-2);
        }

        .gnf-wpforms-shell input:not([type="checkbox"]):not([type="radio"]):not([type="file"]),
        .gnf-wpforms-shell textarea,
        .gnf-wpforms-shell select {
          width: 100%;
          border-radius: 12px;
          border: 1.5px solid var(--gnf-field-border);
          padding: 12px 14px;
          background: #fff;
          transition: border-color var(--gnf-transition-fast), box-shadow var(--gnf-transition-fast);
        }

        .gnf-wpforms-shell input:not([type="checkbox"]):not([type="radio"]):not([type="file"]):focus,
        .gnf-wpforms-shell textarea:focus,
        .gnf-wpforms-shell select:focus {
          outline: none;
          border-color: var(--gnf-ocean);
          box-shadow: 0 0 0 3px rgba(30, 95, 138, 0.12);
        }

        .gnf-wpforms-shell textarea {
          min-height: 140px;
        }

        .gnf-wpforms-shell .wpforms-field-description {
          color: var(--gnf-muted);
          margin-top: var(--gnf-space-2);
        }

        .gnf-wpforms-shell .wpforms-field-file-upload .wpforms-uploader {
          border-radius: var(--gnf-radius);
          border: 1px dashed var(--gnf-ocean);
          background: linear-gradient(135deg, #f3fbff 0%, #eefbf5 100%);
        }
      `}</style>

      <Card padding="0" style={{ overflow: 'hidden', boxShadow: 'var(--gnf-shadow-md)' }}>
        <div
          style={{
            padding: 'var(--gnf-space-6)',
            background: 'linear-gradient(135deg, rgba(45,138,95,0.11) 0%, rgba(30,95,138,0.12) 100%)',
            borderBottom: '1px solid var(--gnf-border)',
          }}
        >
          <div style={{ display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: 'var(--gnf-space-4)', flexWrap: 'wrap' }}>
            <div style={{ display: 'flex', gap: 'var(--gnf-space-4)', alignItems: 'center' }}>
              {data.reto.iconUrl && (
                <img
                  src={data.reto.iconUrl}
                  alt=""
                  style={{
                    width: 72,
                    height: 72,
                    objectFit: 'contain',
                    borderRadius: 'var(--gnf-radius)',
                    background: '#fff',
                    padding: 'var(--gnf-space-2)',
                    boxShadow: 'var(--gnf-shadow-sm)',
                  }}
                />
              )}
              <div>
                <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--gnf-space-2)', flexWrap: 'wrap', marginBottom: 'var(--gnf-space-2)' }}>
                  {currentEntry && <StatusBadge estado={currentEntry.estado} />}
                  <span
                    style={{
                      display: 'inline-flex',
                      alignItems: 'center',
                      gap: '6px',
                      padding: '6px 12px',
                      borderRadius: 'var(--gnf-radius-full)',
                      background: '#fff',
                      fontSize: '0.8125rem',
                      color: 'var(--gnf-ocean-dark)',
                    }}
                  >
                    <CheckCircle2 size={14} />
                    {currentEntry?.puntaje ?? 0} / {data.reto.puntajeMaximo} eco puntos
                  </span>
                </div>
                <h4 style={{ margin: 0, color: data.reto.color || 'var(--gnf-forest)' }}>{data.reto.titulo}</h4>
                <p style={{ margin: 'var(--gnf-space-2) 0 0', color: 'var(--gnf-gray-600)', maxWidth: 720 }}>
                  Completa las actividades poco a poco. Todo lo que subas o respondas queda guardado y el envio final del wizard solo notifica a supervision y comite.
                </p>
              </div>
            </div>

            <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--gnf-space-3)', flexWrap: 'wrap' }}>
              {data.reto.pdfUrl && (
                <a href={data.reto.pdfUrl} target="_blank" rel="noopener noreferrer" style={{ textDecoration: 'none' }}>
                  <Button variant="ghost" size="sm" icon={<ExternalLink size={14} />}>
                    Ver guia
                  </Button>
                </a>
              )}
              <Button
                variant="outline"
                size="sm"
                icon={<Save size={14} />}
                loading={saveState === 'saving'}
                onClick={() => void triggerAutosave('manual')}
              >
                Guardar ahora
              </Button>
            </div>
          </div>

          <div style={{ marginTop: 'var(--gnf-space-4)' }}>
            <Alert variant={saveState === 'error' ? 'error' : saveState === 'saved' ? 'success' : 'info'}>
              {saveStatusLabel}
            </Alert>
          </div>
        </div>

        <div style={{ padding: 'var(--gnf-space-6)' }}>
          <div style={{ display: 'flex', flexWrap: 'wrap', gap: 'var(--gnf-space-6)', alignItems: 'flex-start' }}>
            <div style={{ flex: '999 1 620px', minWidth: 0 }}>
              <div className="gnf-wpforms-shell" ref={containerRef} />
            </div>

            <div style={{ flex: '320 1 300px', display: 'grid', gap: 'var(--gnf-space-4)' }}>
              <Card style={{ background: '#fbfffd' }}>
                <h5 style={{ marginBottom: 'var(--gnf-space-3)' }}>Resumen del reto</h5>
                <div style={{ display: 'grid', gap: 'var(--gnf-space-2)', fontSize: '0.875rem' }}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', gap: 'var(--gnf-space-3)' }}>
                    <span style={{ color: 'var(--gnf-muted)' }}>Eco puntos actuales</span>
                    <strong>{currentEntry?.puntaje ?? 0}</strong>
                  </div>
                  <div style={{ display: 'flex', justifyContent: 'space-between', gap: 'var(--gnf-space-3)' }}>
                    <span style={{ color: 'var(--gnf-muted)' }}>Maximo del reto</span>
                    <strong>{data.reto.puntajeMaximo}</strong>
                  </div>
                  {lastSavedAt && (
                    <div style={{ display: 'flex', justifyContent: 'space-between', gap: 'var(--gnf-space-3)' }}>
                      <span style={{ color: 'var(--gnf-muted)' }}>Último guardado</span>
                      <strong style={{ textAlign: 'right' }}>{formatSavedStamp(lastSavedAt)}</strong>
                    </div>
                  )}
                </div>
              </Card>

              {evidenceList.length > 0 && (
                <Card>
                  <h5 style={{ marginBottom: 'var(--gnf-space-3)' }}>Evidencias guardadas</h5>
                  <EvidenceViewer evidencias={evidenceList} onRemove={isEditable ? handleRemoveEvidence : undefined} />
                </Card>
              )}

              {data.fieldPoints.length > 0 && (
                <Card>
                  <h5 style={{ marginBottom: 'var(--gnf-space-3)' }}>Puntos por evidencia</h5>
                  <div style={{ display: 'grid', gap: 'var(--gnf-space-2)' }}>
                    {data.fieldPoints.map((fieldPoint) => (
                      <div
                        key={fieldPoint.fieldId}
                        style={{
                          display: 'flex',
                          justifyContent: 'space-between',
                          gap: 'var(--gnf-space-3)',
                          padding: 'var(--gnf-space-3)',
                          borderRadius: '12px',
                          border: `1px solid ${completedFieldIds.has(String(fieldPoint.fieldId)) ? 'rgba(34, 197, 94, 0.35)' : 'var(--gnf-gray-100)'}`,
                          background: completedFieldIds.has(String(fieldPoint.fieldId)) ? 'rgba(34, 197, 94, 0.08)' : 'var(--gnf-white)',
                          fontSize: '0.8125rem',
                        }}
                      >
                        <div style={{ display: 'flex', gap: 'var(--gnf-space-2)', alignItems: 'flex-start' }}>
                          <CheckCircle2
                            size={16}
                            style={{
                              color: completedFieldIds.has(String(fieldPoint.fieldId)) ? '#22c55e' : 'var(--gnf-gray-300)',
                              flexShrink: 0,
                              marginTop: 1,
                            }}
                          />
                          <span style={{ color: 'var(--gnf-gray-700)' }}>{fieldPoint.label || `Campo ${fieldPoint.fieldId}`}</span>
                        </div>
                        <strong style={{ whiteSpace: 'nowrap', color: 'var(--gnf-forest)' }}>{fieldPoint.puntos} pts</strong>
                      </div>
                    ))}
                  </div>
                </Card>
              )}
            </div>
          </div>
        </div>
      </Card>
    </div>
  );
}
