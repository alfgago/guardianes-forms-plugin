#!/usr/bin/env python3
"""
Script para extraer texto de PDFs de retos y guardarlos en archivos .txt

Uso:
    python extract_pdf_text.py

Requisitos:
    pip install pdfplumber requests
"""

import json
import os
import sys
from pathlib import Path
from urllib.parse import urlparse
import requests

try:
    import pdfplumber
    USE_PDFPLUMBER = True
except ImportError:
    try:
        from PyPDF2 import PdfReader
        USE_PDFPLUMBER = False
    except ImportError:
        print("Error: Se requiere pdfplumber o PyPDF2")
        print("Instala con: pip install pdfplumber")
        print("O con: pip install PyPDF2")
        sys.exit(1)


def sanitize_filename(filename):
    """Limpia el nombre de archivo para que sea válido en el sistema de archivos."""
    # Remover caracteres inválidos
    invalid_chars = '<>:"/\\|?*'
    for char in invalid_chars:
        filename = filename.replace(char, '_')
    return filename


def get_pdf_filename_from_url(url):
    """Extrae el nombre del archivo PDF desde la URL."""
    parsed = urlparse(url)
    filename = os.path.basename(parsed.path)
    return filename


def download_pdf(url, output_dir):
    """Descarga un PDF desde una URL y lo guarda en el directorio especificado."""
    try:
        print(f"  [>>] Descargando: {url}")
        response = requests.get(url, timeout=30, stream=True)
        response.raise_for_status()
        
        filename = get_pdf_filename_from_url(url)
        filepath = os.path.join(output_dir, filename)
        
        with open(filepath, 'wb') as f:
            for chunk in response.iter_content(chunk_size=8192):
                f.write(chunk)
        
        print(f"  [OK] Descargado: {filename}")
        return filepath
    except Exception as e:
        print(f"  [ERROR] Error descargando: {e}")
        return None


def extract_text_from_pdf(pdf_path):
    """Extrae todo el texto de un archivo PDF."""
    text_content = []
    
    try:
        if USE_PDFPLUMBER:
            with pdfplumber.open(pdf_path) as pdf:
                print(f"  [>>] Procesando {len(pdf.pages)} pagina(s)...")
                
                for page_num, page in enumerate(pdf.pages, 1):
                    text = page.extract_text()
                    if text:
                        text_content.append(f"--- Pagina {page_num} ---\n")
                        text_content.append(text)
                        text_content.append("\n\n")
        else:
            # Usar PyPDF2
            reader = PdfReader(pdf_path)
            print(f"  [>>] Procesando {len(reader.pages)} pagina(s)...")
            
            for page_num, page in enumerate(reader.pages, 1):
                text = page.extract_text()
                if text:
                    text_content.append(f"--- Pagina {page_num} ---\n")
                    text_content.append(text)
                    text_content.append("\n\n")
        
        return "\n".join(text_content)
    except Exception as e:
        print(f"  [ERROR] Error extrayendo texto: {e}")
        return None


def save_text_file(text, output_path):
    """Guarda el texto extraído en un archivo .txt."""
    try:
        with open(output_path, 'w', encoding='utf-8') as f:
            f.write(text)
        print(f"  [OK] Texto guardado: {os.path.basename(output_path)}")
        return True
    except Exception as e:
        print(f"  [ERROR] Error guardando texto: {e}")
        return False


def main():
    """Función principal."""
    # Rutas
    script_dir = Path(__file__).parent
    json_path = script_dir / 'retos-data.json'
    pdf_texts_dir = script_dir / 'pdf-texts'
    temp_pdfs_dir = script_dir / 'temp-pdfs'
    
    # Crear directorios si no existen
    pdf_texts_dir.mkdir(exist_ok=True)
    temp_pdfs_dir.mkdir(exist_ok=True)
    
    # Leer JSON
    print("[>>] Leyendo retos-data.json...")
    try:
        with open(json_path, 'r', encoding='utf-8') as f:
            retos = json.load(f)
    except Exception as e:
        print(f"[ERROR] Error leyendo JSON: {e}")
        sys.exit(1)
    
    print(f"[OK] Encontrados {len(retos)} retos\n")
    
    # Contadores
    success_count = 0
    error_count = 0
    processed_targets = set()

    # Procesar cada reto y cada configuracion anual
    for index, reto in enumerate(retos, 1):
        titulo = reto.get('titulo', f'Reto {index}')
        years = reto.get('years', {})

        if not isinstance(years, dict) or not years:
            print(f"[{index}/{len(retos)}] [SKIP] Omitiendo '{titulo}': Sin configuracion anual")
            continue

        print(f"[{index}/{len(retos)}] [>>] Procesando: {titulo}")

        for year_key in sorted(years.keys(), key=lambda value: int(value)):
            year_config = years.get(year_key, {})
            if not isinstance(year_config, dict):
                continue

            pdf_url = year_config.get('pdf_url', '')
            pdf_text_dir = year_config.get('pdf_text_dir', '')

            if not pdf_url:
                print(f"  [{year_key}] [SKIP] Sin URL de PDF")
                continue

            if pdf_text_dir:
                txt_path = script_dir / pdf_text_dir
            else:
                pdf_filename = get_pdf_filename_from_url(pdf_url)
                txt_path = pdf_texts_dir / pdf_filename.replace('.pdf', '.txt')

            txt_path.parent.mkdir(parents=True, exist_ok=True)
            target_key = str(txt_path.resolve())

            if target_key in processed_targets:
                print(f"  [{year_key}] [SKIP] Ya procesado: {txt_path.name}")
                continue

            print(f"  [{year_key}] [>>] Procesando PDF anual")

            # Descargar PDF
            pdf_path = download_pdf(pdf_url, temp_pdfs_dir)
            if not pdf_path:
                error_count += 1
                print()
                continue

            # Extraer texto
            text = extract_text_from_pdf(pdf_path)
            if not text:
                error_count += 1
                try:
                    os.remove(pdf_path)
                except Exception:
                    pass
                print()
                continue

            if save_text_file(text, txt_path):
                processed_targets.add(target_key)
                success_count += 1
            else:
                error_count += 1

            try:
                os.remove(pdf_path)
            except Exception:
                pass

            print()
    
    # Resumen
    print("=" * 60)
    print("RESUMEN")
    print("=" * 60)
    print(f"[OK] Exitosos: {success_count}")
    print(f"[ERROR] Errores: {error_count}")
    print(f"[INFO] Archivos guardados en: {pdf_texts_dir}")
    
    # Limpiar directorio temporal si está vacío
    try:
        if not any(temp_pdfs_dir.iterdir()):
            temp_pdfs_dir.rmdir()
    except:
        pass


if __name__ == '__main__':
    main()

