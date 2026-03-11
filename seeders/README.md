# Seeders - Guardianes Formularios

Scripts para importar datos iniciales al sistema.

## 📋 Extraer texto de PDFs

### Requisitos

```bash
pip install -r requirements.txt
```

O instalar manualmente:
```bash
pip install pdfplumber requests
```

### Ejecutar

```bash
python extract_pdf_text.py
```

El script:
1. ✅ Lee `retos-data.json`
2. ✅ Descarga cada PDF desde su URL
3. ✅ Extrae el texto de cada PDF
4. ✅ Guarda archivos `.txt` en la carpeta `pdf-texts/` con el mismo nombre del PDF

### Estructura de salida

```
seeders/
├── pdf-texts/              # Archivos .txt extraídos
│   ├── Eco-Reto-Agua-educar-y-movilizar.txt
│   ├── Eco-reto-energia-educar-y-movilizar.txt
│   └── ...
└── temp-pdfs/              # PDFs temporales (se limpian automáticamente)
```

---

## 🌱 Importar Retos a WordPress

### Opción 1: WP-CLI (Recomendado)

```bash
# Simulación (no crea nada)
wp eval-file wp-content/plugins/guardianes-formularios/seeders/seed-retos.php --dry-run

# Ejecución real
wp eval-file wp-content/plugins/guardianes-formularios/seeders/seed-retos.php
```

### Opción 2: Desde navegador

Como administrador logueado:
```
https://tu-sitio.com/?gnf_seed_retos=1&gnf_seed_key=bandera2025
```

---

## 📝 Notas

- Los PDFs se descargan temporalmente y se eliminan después de extraer el texto
- Si un PDF falla, el script continúa con los siguientes
- Los archivos `.txt` se guardan con codificación UTF-8

