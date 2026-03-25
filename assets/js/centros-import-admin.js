(() => {
  const config = window.gnfCentrosImportAdmin;
  const root = document.getElementById('gnf-centros-import-progress');

  if (!config || !root || root.dataset.active !== '1') {
    return;
  }

  const statusEl = root.querySelector('.gnf-centros-import-status');
  const barEl = root.querySelector('.gnf-centros-import-bar');
  const metaEl = root.querySelector('.gnf-centros-import-meta');
  const messageEl = root.querySelector('.gnf-centros-import-message');
  const errorEl = root.querySelector('.gnf-centros-import-error');
  const createdEl = root.querySelector('.gnf-stat-created');
  const updatedEl = root.querySelector('.gnf-stat-updated');
  const skippedEl = root.querySelector('.gnf-stat-skipped');
  const errorsEl = root.querySelector('.gnf-stat-errors');
  const deletedWrapEl = root.querySelector('.gnf-stat-deleted-wrap');
  const deletedEl = root.querySelector('.gnf-stat-deleted');

  let running = false;

  const sleep = (ms) => new Promise((resolve) => window.setTimeout(resolve, ms));

  const setText = (el, value) => {
    if (el) {
      el.textContent = String(value);
    }
  };

  const renderStats = (stats = {}) => {
    setText(createdEl, stats.created ?? 0);
    setText(updatedEl, stats.updated ?? 0);
    setText(skippedEl, stats.skipped ?? 0);
    setText(errorsEl, Array.isArray(stats.errors) ? stats.errors.length : 0);

    if (deletedWrapEl) {
      const deletedCount = Number(stats.deleted ?? 0);
      deletedWrapEl.style.display = deletedCount > 0 ? '' : 'none';
      setText(deletedEl, deletedCount);
    }
  };

  const renderProgress = (progress = {}) => {
    const percent = Number(progress.percent ?? 0);
    if (barEl) {
      barEl.style.width = `${Math.max(0, Math.min(100, percent))}%`;
    }
    setText(statusEl, progress.status_label ?? 'Procesando...');
    setText(
      metaEl,
      `${Number(progress.done_units ?? 0)} de ${Number(progress.total_units ?? 0)} filas procesadas (${percent}%)`
    );
  };

  const showMessage = (message) => {
    if (messageEl) {
      messageEl.textContent = message || '';
    }
  };

  const showError = (message) => {
    if (errorEl) {
      errorEl.style.display = 'block';
      errorEl.textContent = message;
    }
  };

  const hideError = () => {
    if (errorEl) {
      errorEl.style.display = 'none';
      errorEl.textContent = '';
    }
  };

  const processBatches = async () => {
    if (running) {
      return;
    }

    running = true;
    root.style.display = 'block';
    hideError();

    try {
      while (true) {
        const response = await fetch(config.ajaxUrl, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
          },
          body: new URLSearchParams({
            action: 'gnf_process_centros_import_batch',
            nonce: config.nonce,
          }).toString(),
        });

        let payload;
        try {
          payload = await response.json();
        } catch (error) {
          throw new Error('Respuesta invalida del servidor.');
        }

        if (!payload?.success) {
          throw new Error(payload?.data?.message || 'No se pudo procesar el lote.');
        }

        const data = payload.data || {};
        renderStats(data.stats || {});
        renderProgress(data.progress || {});
        showMessage(data.message || '');

        if (data.done) {
          await sleep(700);
          window.location.assign(data.redirectUrl || config.redirectUrl);
          return;
        }

        await sleep(150);
      }
    } catch (error) {
      showError(error instanceof Error ? error.message : 'La importacion se interrumpio. Recarga la pagina para reanudar.');
    } finally {
      running = false;
    }
  };

  processBatches();
})();
