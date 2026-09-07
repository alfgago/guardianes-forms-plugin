# Pilot Rollout and UX Hardening Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Allow Guardianes to validate impact indicators, awards, and center reports with selected schools before enabling them globally, while clearly separating preliminary and validated results.

**Architecture:** Add a small rollout service with per-feature modes (`off`, `pilot`, `all`) and one shared center allowlist. Keep administrators able to preview, enforce rollout decisions on server responses and form rendering, and keep public impact output closed unless explicitly enabled for all centers. Extend the existing award/PDF DTOs rather than creating parallel endpoints.

**Tech Stack:** WordPress/PHP 7.4, ACF options, WPForms, WordPress REST API, React 18, TypeScript, TanStack Query, Dompdf.

**Spec:** `docs/superpowers/specs/2026-09-04-impact-reports-awards-design.md`

## Global Constraints

- Preserve existing center, enrollment, challenge, field, and evidence IDs.
- Do not expose credentials, tokens, nonces, or internal metadata.
- Administrators may preview disabled features; docentes and supervisors must follow rollout scope.
- Public impact data remains unavailable until impact mode is `all` and metrics are explicitly selected.
- New impact fields are prepared only through an explicit administrative action.
- PHP target remains 7.4; frontend changes must pass the production Vite build.

---

### Task 1: Rollout service and settings

**Files:**
- Create: `includes/feature-rollout.php`
- Modify: `guardianes-formularios.php`
- Modify: `includes/acf-fields.php`
- Test: `tests/test-feature-rollout.php`

**Interfaces:**
- Produces: `gnf_get_feature_rollout_mode(string): string`, `gnf_get_pilot_center_ids(): int[]`, `gnf_feature_is_enabled_for_center(string, int, bool): bool`, `gnf_get_feature_rollout_summary(string): array`.

- [x] Write tests for mode normalization, pilot membership, administrator preview, and ACF settings.
- [x] Run `php tests/test-feature-rollout.php` and confirm it fails because the service does not exist.
- [x] Add the rollout service, load it before awards/impact, and register configuration fields.
- [x] Run the test and confirm it passes.

### Task 2: Safe impact form preparation and scoped aggregation

**Files:**
- Modify: `includes/impact-metrics.php`
- Modify: `includes/rest-api.php`
- Modify: `tests/test-impact-fields-migration.php`
- Modify: `tests/test-impact-metrics.php`

**Interfaces:**
- Consumes: rollout helpers from Task 1.
- Produces: manual `admin-post.php?action=gnf_prepare_impact_fields`, pilot-aware aggregation, hidden pilot-only fields for non-pilot centers, closed-by-default public metrics.

- [x] Add failing tests proving no `admin_init` mutation, manual permission/nonce handling, pilot-only records, and empty public defaults.
- [x] Run the focused tests and verify the expected failures.
- [x] Replace automatic migration with a configuration-page action and status notice.
- [x] Scope impact records/cache by rollout mode and suppress newly introduced metric fields outside pilot/all mode.
- [x] Require explicit public metric selection and `all` rollout for shortcode/REST output.
- [x] Run the focused tests and confirm they pass.

### Task 3: Stable award criteria and clear preliminary/validated status

**Files:**
- Modify: `includes/award-rules.php`
- Modify: `includes/impact-metrics.php`
- Modify: `includes/rest-api.php`
- Modify: `app/src/types/centro.ts`
- Modify: `app/src/components/domain/AwardSummary.tsx`
- Modify: `tests/test-award-rules.php`
- Modify: `tests/test-award-ui-contract.php`

**Interfaces:**
- Produces: stable `gnf_award_key` field metadata and an `AwardBundle` containing `projected`, `validated`, and rollout state.

- [x] Add failing tests for stable criterion keys, both result modes, rollout gating, and unambiguous labels.
- [x] Run award tests and verify the failures.
- [x] Annotate criteria during the manual schema preparation and resolve rules by stable field ID/key with legacy label fallback.
- [x] Return and render projected and validated outcomes side by side, including next-star progress and unmet requirements.
- [x] Run award tests and the frontend build.

### Task 4: Report status and interface cleanup

**Files:**
- Modify: `includes/report-pdf.php`
- Modify: `includes/rest-api.php`
- Modify: `app/src/panels/docente/pages/ResumenPage.tsx`
- Modify: `app/src/panels/admin/pages/CentroDetailPage.tsx`
- Modify: `app/src/panels/supervisor/pages/CentroDetailPage.tsx`
- Modify: `app/src/panels/admin/pages/ReportesPage.tsx`
- Modify: `app/src/components/ui/Modal.tsx`
- Modify: `app/src/styles/components.css`
- Modify: `tests/test-center-report-pdf.php`
- Create: `tests/test-pilot-ux-contract.php`

**Interfaces:**
- Produces: `gnf_get_center_report_status(int, int): draft|final`, report labels/filenames matching status, searchable impact comparisons, accessible modal behavior.

- [x] Add failing tests for report status, rollout-gated URLs, draft/final copy, removal of the dead export action, chart search/empty states, and modal focus semantics.
- [x] Run the focused tests and verify the expected failures.
- [x] Add report status metadata and update buttons/PDF output.
- [x] Improve impact comparison controls and remove the inactive Reportes export button.
- [x] Add dialog labeling, focus containment/restoration, reduced motion, and modal overscroll handling.
- [x] Run focused tests, all PHP tests, PHP lint, Composer audit, `npm run build`, and `git diff --check`.
