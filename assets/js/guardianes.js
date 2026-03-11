(function ($) {
	/**
	 * Carga dinámica de browser-image-compression si no existe.
	 */
	function ensureCompressor() {
		return new Promise((resolve, reject) => {
			if (window.imageCompression) {
				return resolve(window.imageCompression);
			}
			const script = document.createElement('script');
			script.src = 'https://unpkg.com/browser-image-compression/dist/browser-image-compression.js';
			script.async = true;
			script.onload = () => resolve(window.imageCompression);
			script.onerror = reject;
			document.head.appendChild(script);
		});
	}

	/**
	 * Lee el año desde EXIF (DateTimeOriginal) de un archivo de imagen.
	 */
	function readExifYear(file) {
		return new Promise((resolve) => {
			const reader = new FileReader();
			reader.onload = function (e) {
				const view = new DataView(e.target.result);
				if (view.getUint16(0, false) !== 0xffd8) return resolve(null);
				let offset = 2;
				const length = view.byteLength;
				while (offset < length) {
					if (view.getUint16(offset + 2, false) <= 8) break;
					const marker = view.getUint16(offset, false);
					offset += 2;
					if (marker === 0xffe1) {
						if (view.getUint32((offset += 2), false) !== 0x45786966) return resolve(null);
						const little = view.getUint16((offset += 6), false) === 0x4949;
						offset += view.getUint32(offset + 4, little);
						const tags = view.getUint16(offset, little);
						offset += 2;
						for (let i = 0; i < tags; i++) {
							if (view.getUint16(offset + i * 12, little) === 0x9003) {
								offset += i * 12;
								const valOffset = view.getUint32(offset + 8, little);
								const start = offset + valOffset + 8;
								const year = view.getUint16(start, little);
								return resolve(year);
							}
						}
					} else if ((marker & 0xff00) !== 0xff00) {
						break;
					} else {
						offset += view.getUint16(offset, false);
					}
				}
				return resolve(null);
			};
			reader.readAsArrayBuffer(file);
		});
	}

	/**
	 * Comprime imágenes a WebP manteniendo EXIF cuando el navegador lo permite.
	 */
	async function compressImages(files) {
		const ic = await ensureCompressor();
		const options = {
			maxWidthOrHeight: 1920,
			fileType: 'image/webp',
			maxSizeMB: 5,
			preserveExif: true,
			useWebWorker: true,
		};
		const output = [];
		for (const file of files) {
			if (!file.type.startsWith('image/')) {
				output.push(file);
				continue;
			}
			try {
				const compressed = await ic(file, options);
				output.push(compressed);
			} catch (e) {
				console.warn('No se pudo comprimir', file.name, e);
				output.push(file);
			}
		}
		return output;
	}

	/**
	 * Inyecta warning si año de foto no coincide.
	 */
	function warnIfYearMismatch(results) {
		const activeYear = parseInt((window.gnfData && window.gnfData.anio) || new Date().getFullYear(), 10);
		const mismatches = results.filter((r) => r.year && r.year !== activeYear);
		if (!mismatches.length) return;
		const years = Array.from(new Set(mismatches.map((r) => r.year))).join(', ');
		alert(`Advertencia: Se detectaron fotos con año ${years}, fuera del periodo activo (${activeYear}).`);
	}

	/**
	 * Procesa un input file: valida año y comprime.
	 */
	function enhanceFileInput(input) {
		input.addEventListener('change', async (e) => {
			const files = Array.from(e.target.files || []);
			if (!files.length) return;

			// Lee años de EXIF.
			const exifPromises = files.map(async (file) => ({
				file,
				year: file.type.startsWith('image/') ? await readExifYear(file) : null,
			}));
			const exifResults = await Promise.all(exifPromises);
			warnIfYearMismatch(exifResults);

			// Comprime imágenes a WebP.
			const compressed = await compressImages(files);
			const dt = new DataTransfer();
			compressed.forEach((file) => dt.items.add(file));
			input.files = dt.files;
		});
	}

	/**
	 * Inicializa autocompletado para buscar centros educativos.
	 */
	function initCentroAutocomplete() {
		const searchInputs = document.querySelectorAll('.gnf-centro-search, [data-gnf-centro-autocomplete]');
		
		searchInputs.forEach(function(input) {
			let debounceTimer;
			let resultsContainer = input.nextElementSibling;
			
			// Crear contenedor de resultados si no existe.
			if (!resultsContainer || !resultsContainer.classList.contains('gnf-autocomplete-results')) {
				resultsContainer = document.createElement('div');
				resultsContainer.className = 'gnf-autocomplete-results';
				resultsContainer.style.cssText = 'position:absolute;background:#fff;border:1px solid #ddd;border-radius:8px;max-height:300px;overflow-y:auto;z-index:1000;width:100%;box-shadow:0 4px 12px rgba(0,0,0,0.15);display:none;';
				input.parentNode.style.position = 'relative';
				input.parentNode.appendChild(resultsContainer);
			}

			input.addEventListener('input', function() {
				const term = this.value.trim();
				
				clearTimeout(debounceTimer);
				
				if (term.length < 2) {
					resultsContainer.style.display = 'none';
					return;
				}

				debounceTimer = setTimeout(function() {
					fetch(gnfData.ajaxUrl + '?action=gnf_search_centros&term=' + encodeURIComponent(term))
						.then(res => res.json())
						.then(data => {
							if (!data || !data.length) {
								resultsContainer.innerHTML = '<div style="padding:12px;color:#666;">No se encontraron centros</div>';
								resultsContainer.style.display = 'block';
								return;
							}

							resultsContainer.innerHTML = data.map(centro => `
								<div class="gnf-autocomplete-item" 
									style="padding:12px;cursor:pointer;border-bottom:1px solid #eee;"
									data-id="${centro.id}"
									data-nombre="${centro.value}"
									data-codigo="${centro.codigo_mep || ''}"
									data-region="${centro.region || ''}"
									data-region-name="${centro.region_name || ''}"
									data-circuito="${centro.circuito || ''}"
									data-canton="${centro.canton || ''}"
									data-provincia="${centro.provincia || ''}"
									data-distrito="${centro.distrito || ''}"
									data-poblado="${centro.poblado || ''}"
									data-dependencia="${centro.dependencia || ''}"
									data-zona="${centro.zona || ''}"
									data-telefono="${centro.telefono || ''}">
									<strong>${centro.label}</strong>
									${centro.dependencia ? ' <span class="gnf-badge gnf-badge--sm" style="font-size:10px;padding:2px 6px;vertical-align:middle;">' + centro.dependencia + '</span>' : ''}
									${centro.region_name ? '<br><small style="color:#666;">' + centro.region_name + (centro.circuito ? ' - ' + centro.circuito : '') + '</small>' : ''}
								</div>
							`).join('');
							
							resultsContainer.style.display = 'block';

							// Click en resultado.
							resultsContainer.querySelectorAll('.gnf-autocomplete-item').forEach(item => {
								item.addEventListener('click', function() {
									selectCentro(input, this);
									resultsContainer.style.display = 'none';
								});
								item.addEventListener('mouseenter', function() {
									this.style.background = '#f5f5f5';
								});
								item.addEventListener('mouseleave', function() {
									this.style.background = '#fff';
								});
							});
						})
						.catch(err => {
							console.error('Error buscando centros:', err);
							resultsContainer.style.display = 'none';
						});
				}, 300);
			});

			// Cerrar al hacer clic fuera.
			document.addEventListener('click', function(e) {
				if (!input.contains(e.target) && !resultsContainer.contains(e.target)) {
					resultsContainer.style.display = 'none';
				}
			});
		});
	}

	/**
	 * Selecciona un centro y llena campos relacionados.
	 */
	function selectCentro(input, item) {
		const id = item.dataset.id;
		const nombre = item.dataset.nombre;
		const codigo = item.dataset.codigo;
		const region = item.dataset.region;
		const regionName = item.dataset.regionName;
		const circuito = item.dataset.circuito;
		const canton = item.dataset.canton;
		const provincia = item.dataset.provincia;
		const distrito = item.dataset.distrito;
		const poblado = item.dataset.poblado;
		const dependencia = item.dataset.dependencia;
		const zona = item.dataset.zona;
		const telefono = item.dataset.telefono;

		input.value = nombre;

		// Buscar y llenar campos relacionados en el formulario.
		const form = input.closest('form') || document;
		
		const idField = form.querySelector('[name*="centro-id"], [name*="centro_id"], #gnf-centro-id');
		if (idField) idField.value = id;

		const codigoField = form.querySelector('[name*="codigo-mep"], [name*="codigo_mep"]');
		if (codigoField) codigoField.value = codigo;

		const regionField = form.querySelector('[name*="centro-region"], [name*="region"]');
		if (regionField) regionField.value = region || regionName;

		const circuitoField = form.querySelector('[name*="circuito"]');
		if (circuitoField) circuitoField.value = circuito;

		const cantonField = form.querySelector('[name*="canton"]');
		if (cantonField) cantonField.value = canton;

		const provinciaField = form.querySelector('[name*="provincia"]');
		if (provinciaField) provinciaField.value = provincia;

		const distritoField = form.querySelector('[name*="distrito"]');
		if (distritoField) distritoField.value = distrito;

		const pobladoField = form.querySelector('[name*="poblado"]');
		if (pobladoField) pobladoField.value = poblado;

		// Nota: depende del nombre real del campo en WPForms/ACF; esto cubre la mayoría de casos.
		const dependenciaField = form.querySelector('[name*="dependencia"]');
		if (dependenciaField) dependenciaField.value = dependencia;

		const zonaField = form.querySelector('[name*="zona"]');
		if (zonaField) zonaField.value = zona;

		const telefonoField = form.querySelector('[name*="telefono"]');
		if (telefonoField) telefonoField.value = telefono;

		// Disparar evento custom.
		input.dispatchEvent(new CustomEvent('gnf-centro-selected', {
			detail: { id, nombre, codigo, region, regionName, circuito, canton, provincia, distrito, poblado, dependencia, zona, telefono }
		}));
	}

	$(function () {
		// Mejora todos los inputs file de WPForms en la página.
		document.querySelectorAll('input[type="file"]').forEach(enhanceFileInput);

		// Inicializar autocompletado de centros.
		initCentroAutocomplete();

		// Tabs auth login/register.
		document.querySelectorAll('.gnf-auth__tab').forEach(function (btn) {
			btn.addEventListener('click', function () {
				const tab = this.getAttribute('data-tab');
				const wrap = this.closest('.gnf-auth');
				wrap.querySelectorAll('.gnf-auth__tab').forEach((b) => b.classList.remove('is-active'));
				this.classList.add('is-active');
				wrap.querySelectorAll('.gnf-auth__panel').forEach((p) => {
					p.style.display = p.getAttribute('data-panel') === tab ? 'block' : 'none';
				});
			});
		});
	});
})(jQuery);
