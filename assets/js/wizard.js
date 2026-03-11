/**
 * JavaScript para el Wizard Maestro BAE.
 */
(function() {
    'use strict';

    // Inicializar cuando el DOM esté listo.
    document.addEventListener('DOMContentLoaded', initWizard);

    function initWizard() {
        var wizard = document.querySelector('.gnf-wizard');
        if (!wizard) return;

        var centroId = wizard.dataset.centroId;
        var anio = wizard.dataset.anio;

        // Auto-guardar progreso cada 30 segundos si hay formulario.
        initAutoSave(wizard, centroId, anio);

        // Manejar eventos de WPForms.
        initWPFormsIntegration();

        // Animaciones de progreso.
        initProgressAnimations();
    }

    /**
     * Auto-guardar progreso periódicamente.
     */
    function initAutoSave(wizard, centroId, anio) {
        var forms = wizard.querySelectorAll('.wpforms-form');
        if (!forms.length) return;

        setInterval(function() {
            forms.forEach(function(form) {
                saveFormProgress(form, centroId, anio);
            });
        }, 30000); // Cada 30 segundos.
    }

    /**
     * Guarda el progreso del formulario via AJAX.
     */
    function saveFormProgress(form, centroId, anio) {
        if (!form || !window.gnfData) return;

        var formData = new FormData(form);
        var stepData = {};
        
        formData.forEach(function(value, key) {
            stepData[key] = value;
        });

        // Solo guardar si hay datos.
        if (Object.keys(stepData).length < 2) return;

        var stepId = form.dataset.formid || 'unknown';

        fetch(gnfData.ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=gnf_save_wizard_progress&nonce=' + gnfData.nonce + 
                  '&centro_id=' + centroId + 
                  '&anio=' + encodeURIComponent(anio || '') +
                  '&step_id=' + stepId +
                  '&step_data=' + encodeURIComponent(JSON.stringify(stepData))
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.success) {
                showSaveIndicator('Progreso guardado');
            }
        })
        .catch(function() {
            // Silencioso en errores de auto-save.
        });
    }

    /**
     * Muestra indicador temporal de guardado.
     */
    function showSaveIndicator(message) {
        var existing = document.querySelector('.gnf-save-indicator');
        if (existing) existing.remove();

        var indicator = document.createElement('div');
        indicator.className = 'gnf-save-indicator';
        indicator.textContent = '✓ ' + message;
        indicator.style.cssText = 'position:fixed;bottom:80px;right:24px;background:#22c55e;color:#fff;' +
            'padding:8px 16px;border-radius:8px;font-size:14px;z-index:9998;animation:fadeIn 0.3s;';
        
        document.body.appendChild(indicator);

        setTimeout(function() {
            indicator.style.opacity = '0';
            indicator.style.transition = 'opacity 0.3s';
            setTimeout(function() { indicator.remove(); }, 300);
        }, 2000);
    }

    /**
     * Integración con eventos de WPForms.
     */
    function initWPFormsIntegration() {
        // Escuchar cuando WPForms complete un envío.
        if (window.jQuery) {
            jQuery(document).on('wpformsAjaxSubmitSuccess', function(e, response) {
                if (response && response.data) {
                    // Recargar la página para mostrar el nuevo estado.
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                }
            });
        }
    }

    /**
     * Animaciones de progreso.
     */
    function initProgressAnimations() {
        // Animar barras de progreso al cargar.
        var progressBars = document.querySelectorAll('.gnf-wizard__progress-bar, .gnf-wizard__checklist-fill');
        
        progressBars.forEach(function(bar) {
            var targetWidth = bar.style.width;
            bar.style.width = '0';
            
            setTimeout(function() {
                bar.style.transition = 'width 0.8s ease-out';
                bar.style.width = targetWidth;
            }, 100);
        });

        // Highlight al paso actual.
        var activeStep = document.querySelector('.gnf-wizard__step--active');
        if (activeStep) {
            activeStep.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
        }
    }

    /**
     * Validación antes de cambiar de paso.
     */
    window.gnfValidateStep = function(currentStep) {
        var form = document.querySelector('.gnf-wizard__content .wpforms-form');
        if (!form) return true;

        // WPForms maneja su propia validación.
        // Solo verificamos campos requeridos visualmente.
        var required = form.querySelectorAll('[required]:not([type="hidden"])');
        var allFilled = true;

        required.forEach(function(field) {
            if (!field.value || field.value.trim() === '') {
                allFilled = false;
                field.classList.add('wpforms-error');
            } else {
                field.classList.remove('wpforms-error');
            }
        });

        if (!allFilled) {
            alert('Por favor completa todos los campos requeridos antes de continuar.');
            return false;
        }

        return true;
    };

    /**
     * Utilidades para manejo de checklist.
     */
    window.gnfUpdateChecklistProgress = function(retoId) {
        var form = document.querySelector('.gnf-wizard__content .wpforms-form');
        if (!form) return;

        var checkboxes = form.querySelectorAll('input[type="checkbox"]');
        var total = checkboxes.length;
        var checked = 0;

        checkboxes.forEach(function(cb) {
            if (cb.checked) checked++;
        });

        var progress = total > 0 ? Math.round((checked / total) * 100) : 0;
        
        var progressFill = document.querySelector('.gnf-wizard__checklist-fill');
        var progressText = document.querySelector('.gnf-wizard__checklist-progress span:last-child');
        
        if (progressFill) {
            progressFill.style.width = progress + '%';
        }
        if (progressText) {
            progressText.textContent = progress + '%';
            progressText.style.color = progress === 100 ? '#16a34a' : '#64748b';
        }

        // Habilitar/deshabilitar botón de finalizar.
        var finalizarBtn = document.querySelector('.gnf-wizard-finalizar-reto');
        if (finalizarBtn) {
            if (progress === 100) {
                finalizarBtn.disabled = false;
                finalizarBtn.textContent = '✅ Finalizar Reto';
            } else {
                finalizarBtn.disabled = true;
                finalizarBtn.textContent = '🔒 Completa el checklist primero';
            }
        }
    };

    // Escuchar cambios en checkboxes para actualizar progreso.
    document.addEventListener('change', function(e) {
        if (e.target.type === 'checkbox' && e.target.closest('.gnf-wizard')) {
            window.gnfUpdateChecklistProgress();
        }
    });

})();

