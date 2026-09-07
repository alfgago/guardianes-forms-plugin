<?php
/**
 * Escritor XLSX mínimo y autocontenido para exportaciones administrativas.
 *
 * @package Guardianes_Formularios
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Genera una hoja XLSX por streaming sin depender de Composer.
 */
class GNF_XLSX_Writer {
	/** @var string */
	private $output_path;

	/** @var string */
	private $temp_dir;

	/** @var resource|null */
	private $sheet_handle;

	/** @var string */
	private $sheet_name;

	/** @var int */
	private $row_index = 0;

	/** @var int */
	private $max_columns = 0;

	/** @var bool */
	private $closed = false;

	/**
	 * @param string $output_path Ruta final del XLSX.
	 * @param string $sheet_name  Nombre de la hoja.
	 */
	public function __construct( $output_path, $sheet_name = 'Centros Educativos' ) {
		$this->output_path = (string) $output_path;
		$this->sheet_name  = $this->sanitize_sheet_name( $sheet_name );
		$this->temp_dir    = rtrim( sys_get_temp_dir(), '/\\' ) . DIRECTORY_SEPARATOR . 'gnf-xlsx-' . str_replace( '.', '-', uniqid( '', true ) );

		$this->create_directory( $this->temp_dir . DIRECTORY_SEPARATOR . '_rels' );
		$this->create_directory( $this->temp_dir . DIRECTORY_SEPARATOR . 'docProps' );
		$this->create_directory( $this->temp_dir . DIRECTORY_SEPARATOR . 'xl' . DIRECTORY_SEPARATOR . '_rels' );
		$this->create_directory( $this->temp_dir . DIRECTORY_SEPARATOR . 'xl' . DIRECTORY_SEPARATOR . 'worksheets' );

		$sheet_path         = $this->temp_dir . DIRECTORY_SEPARATOR . 'xl' . DIRECTORY_SEPARATOR . 'worksheets' . DIRECTORY_SEPARATOR . 'sheet1.xml';
		$this->sheet_handle = fopen( $sheet_path, 'wb' );
		if ( false === $this->sheet_handle ) {
			$this->cleanup();
			throw new RuntimeException( 'No se pudo crear la hoja temporal XLSX.' );
		}

		$this->write_sheet( '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' );
		$this->write_sheet( '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' );
		$this->write_sheet( '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>' );
		$this->write_sheet( '<sheetFormatPr defaultRowHeight="15"/><sheetData>' );
	}

	/**
	 * Agrega una fila a la hoja.
	 *
	 * @param array<int,mixed> $values Valores.
	 * @param bool             $header Si es encabezado.
	 * @return void
	 */
	public function add_row( $values, $header = false ) {
		if ( $this->closed || ! is_resource( $this->sheet_handle ) ) {
			throw new RuntimeException( 'El escritor XLSX ya está cerrado.' );
		}

		$values            = array_values( (array) $values );
		$this->row_index++;
		$this->max_columns = max( $this->max_columns, count( $values ) );
		$this->write_sheet( '<row r="' . $this->row_index . '">' );

		foreach ( $values as $column_index => $value ) {
			$reference = $this->column_name( $column_index + 1 ) . $this->row_index;
			$style     = $header ? ' s="1"' : '';

			if ( is_bool( $value ) ) {
				$this->write_sheet( '<c r="' . $reference . '" t="b"' . $style . '><v>' . ( $value ? '1' : '0' ) . '</v></c>' );
				continue;
			}
			if ( ( is_int( $value ) || is_float( $value ) ) && is_finite( (float) $value ) ) {
				$this->write_sheet( '<c r="' . $reference . '" t="n"' . $style . '><v>' . $this->xml_escape( (string) $value ) . '</v></c>' );
				continue;
			}

			$text = $this->xml_escape( (string) $value );
			$this->write_sheet( '<c r="' . $reference . '" t="inlineStr"' . $style . '><is><t xml:space="preserve">' . $text . '</t></is></c>' );
		}

		$this->write_sheet( '</row>' );
	}

	/**
	 * Finaliza y empaqueta el archivo.
	 *
	 * @return string Ruta final.
	 */
	public function close() {
		if ( $this->closed ) {
			return $this->output_path;
		}

		if ( is_resource( $this->sheet_handle ) ) {
			$this->write_sheet( '</sheetData>' );
			if ( $this->row_index > 0 && $this->max_columns > 0 ) {
				$last_cell = $this->column_name( $this->max_columns ) . $this->row_index;
				$this->write_sheet( '<autoFilter ref="A1:' . $last_cell . '"/>' );
			}
			$this->write_sheet( '</worksheet>' );
			fclose( $this->sheet_handle );
			$this->sheet_handle = null;
		}

		try {
			$this->write_package_files();
			$this->create_archive();
			$this->closed = true;
		} finally {
			$this->cleanup();
		}

		return $this->output_path;
	}

	/**
	 * Limpia temporales si el proceso termina antes de cerrar.
	 */
	public function __destruct() {
		if ( is_resource( $this->sheet_handle ) ) {
			fclose( $this->sheet_handle );
			$this->sheet_handle = null;
		}
		$this->cleanup();
	}

	/** @return void */
	private function write_package_files() {
		$created = gmdate( 'Y-m-d\TH:i:s\Z' );
		$files   = array(
			'[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/></Types>',
			'_rels/.rels'       => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>',
			'docProps/app.xml'  => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes"><Application>Guardianes de la Naturaleza</Application></Properties>',
			'docProps/core.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:creator>Guardianes de la Naturaleza</dc:creator><dcterms:created xsi:type="dcterms:W3CDTF">' . $created . '</dcterms:created></cp:coreProperties>',
			'xl/workbook.xml'   => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="' . $this->xml_escape( $this->sheet_name ) . '" sheetId="1" r:id="rId1"/></sheets></workbook>',
			'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
			'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>',
		);

		foreach ( $files as $relative_path => $contents ) {
			$path = $this->temp_dir . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $relative_path );
			if ( false === file_put_contents( $path, $contents ) ) {
				throw new RuntimeException( 'No se pudo escribir la estructura XLSX.' );
			}
		}
	}

	/** @return void */
	private function create_archive() {
		if ( class_exists( 'ZipArchive' ) ) {
			$zip = new ZipArchive();
			if ( true !== $zip->open( $this->output_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
				throw new RuntimeException( 'No se pudo crear el archivo XLSX.' );
			}
			foreach ( $this->list_files() as $absolute_path => $relative_path ) {
				$zip->addFile( $absolute_path, $relative_path );
			}
			if ( ! $zip->close() ) {
				throw new RuntimeException( 'No se pudo finalizar el archivo XLSX.' );
			}
			return;
		}

		if ( ! class_exists( 'PclZip' ) && defined( 'ABSPATH' ) ) {
			$pclzip_path = ABSPATH . 'wp-admin/includes/class-pclzip.php';
			if ( file_exists( $pclzip_path ) ) {
				require_once $pclzip_path;
			}
		}
		if ( ! class_exists( 'PclZip' ) ) {
			throw new RuntimeException( 'El servidor no dispone de ZipArchive ni PclZip.' );
		}

		$archive = new PclZip( $this->output_path );
		$result  = $archive->create( array_keys( $this->list_files() ), PCLZIP_OPT_REMOVE_PATH, $this->temp_dir );
		if ( 0 === $result ) {
			throw new RuntimeException( 'PclZip no pudo crear el archivo XLSX.' );
		}
	}

	/**
	 * @return array<string,string> Ruta absoluta => ruta interna.
	 */
	private function list_files() {
		$files    = array();
		$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $this->temp_dir, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $iterator as $file ) {
			if ( ! $file->isFile() ) {
				continue;
			}
			$absolute            = $file->getPathname();
			$relative            = substr( $absolute, strlen( $this->temp_dir ) + 1 );
			$files[ $absolute ]   = str_replace( DIRECTORY_SEPARATOR, '/', $relative );
		}
		return $files;
	}

	/** @return void */
	private function cleanup() {
		if ( ! $this->temp_dir || ! is_dir( $this->temp_dir ) ) {
			return;
		}
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $this->temp_dir, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $iterator as $item ) {
			if ( $item->isDir() ) {
				@rmdir( $item->getPathname() );
			} else {
				@unlink( $item->getPathname() );
			}
		}
		@rmdir( $this->temp_dir );
	}

	/** @return void */
	private function create_directory( $path ) {
		if ( ! is_dir( $path ) && ! mkdir( $path, 0755, true ) && ! is_dir( $path ) ) {
			throw new RuntimeException( 'No se pudo crear el directorio temporal XLSX.' );
		}
	}

	/** @return void */
	private function write_sheet( $xml ) {
		if ( ! is_resource( $this->sheet_handle ) || false === fwrite( $this->sheet_handle, $xml ) ) {
			throw new RuntimeException( 'No se pudo escribir la hoja XLSX.' );
		}
	}

	/** @return string */
	private function xml_escape( $value ) {
		$value = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string) $value );
		return htmlspecialchars( $value, ENT_QUOTES | ENT_XML1, 'UTF-8' );
	}

	/** @return string */
	private function sanitize_sheet_name( $name ) {
		$name = preg_replace( '/[\\\\\/?*\[\]:]/', ' ', trim( (string) $name ) );
		$name = '' !== $name ? $name : 'Hoja 1';
		return function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 31, 'UTF-8' ) : substr( $name, 0, 31 );
	}

	/** @return string */
	private function column_name( $number ) {
		$name = '';
		while ( $number > 0 ) {
			$number--;
			$name   = chr( 65 + ( $number % 26 ) ) . $name;
			$number = intdiv( $number, 26 );
		}
		return $name;
	}
}
