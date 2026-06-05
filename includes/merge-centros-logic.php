<?php
/**
 * Logica pura para fusion de centros duplicados (sin dependencias de WordPress).
 * Separada para poder testearla con PHP plano.
 */

if ( ! function_exists( 'gnf_merge_centro_key' ) ) {

	/**
	 * Clave normalizada para agrupar duplicados. Null si el codigo MEP esta vacio.
	 */
	function gnf_merge_centro_key( $codigo_mep, $region_id, $circuito, $nombre ) {
		$codigo = trim( (string) $codigo_mep );
		if ( '' === $codigo ) {
			return null;
		}
		$region = (int) $region_id;
		$circ   = function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( (string) $circuito ) ) : strtolower( trim( (string) $circuito ) );
		$name   = function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( (string) $nombre ) ) : strtolower( trim( (string) $nombre ) );
		return $codigo . '|' . $region . '|' . $circ . '|' . $name;
	}

	/** Rango de avance de un estado de reto_entry (mayor = mas avanzado). */
	function gnf_merge_entry_estado_rank( $estado ) {
		$map = array(
			'aprobado'    => 5,
			'enviado'     => 4,
			'completo'    => 3,
			'correccion'  => 2,
			'en_progreso' => 2,
			'no_iniciado' => 1,
		);
		return isset( $map[ (string) $estado ] ) ? $map[ (string) $estado ] : 0;
	}

	/** Rango de avance de un estado de matricula. */
	function gnf_merge_matricula_estado_rank( $estado ) {
		$map = array(
			'aprobado'    => 4,
			'enviado'     => 3,
			'pendiente'   => 2,
			'no_iniciado' => 1,
		);
		return isset( $map[ (string) $estado ] ) ? $map[ (string) $estado ] : 0;
	}

	/**
	 * True si la entry $a debe ganar sobre $b. Empate total -> false (gana el canonical).
	 * Cada array: estado, puntaje, evidencias_count, updated_at.
	 */
	function gnf_merge_entry_a_wins( $a, $b ) {
		$ra = gnf_merge_entry_estado_rank( isset( $a['estado'] ) ? $a['estado'] : '' );
		$rb = gnf_merge_entry_estado_rank( isset( $b['estado'] ) ? $b['estado'] : '' );
		if ( $ra !== $rb ) { return $ra > $rb; }
		$pa = (int) ( isset( $a['puntaje'] ) ? $a['puntaje'] : 0 );
		$pb = (int) ( isset( $b['puntaje'] ) ? $b['puntaje'] : 0 );
		if ( $pa !== $pb ) { return $pa > $pb; }
		$ea = (int) ( isset( $a['evidencias_count'] ) ? $a['evidencias_count'] : 0 );
		$eb = (int) ( isset( $b['evidencias_count'] ) ? $b['evidencias_count'] : 0 );
		if ( $ea !== $eb ) { return $ea > $eb; }
		$ua = (string) ( isset( $a['updated_at'] ) ? $a['updated_at'] : '' );
		$ub = (string) ( isset( $b['updated_at'] ) ? $b['updated_at'] : '' );
		if ( $ua !== $ub ) { return $ua > $ub; }
		return false;
	}

	/** True si la matricula $a debe ganar sobre $b. Empate -> false. */
	function gnf_merge_matricula_a_wins( $a, $b ) {
		$ra = gnf_merge_matricula_estado_rank( isset( $a['estado'] ) ? $a['estado'] : '' );
		$rb = gnf_merge_matricula_estado_rank( isset( $b['estado'] ) ? $b['estado'] : '' );
		if ( $ra !== $rb ) { return $ra > $rb; }
		$ua = (string) ( isset( $a['updated_at'] ) ? $a['updated_at'] : '' );
		$ub = (string) ( isset( $b['updated_at'] ) ? $b['updated_at'] : '' );
		if ( $ua !== $ub ) { return $ua > $ub; }
		return false;
	}

	/** Une dos listas de IDs de retos: ints unicos positivos, ordenados. */
	function gnf_merge_union_reto_ids( $a, $b ) {
		$merged = array_merge( (array) $a, (array) $b );
		$ints   = array_map( 'intval', $merged );
		$ints   = array_filter( array_unique( $ints ), function ( $v ) { return $v > 0; } );
		sort( $ints );
		return array_values( $ints );
	}

	/** Cuenta items en una columna de evidencias (JSON o array). */
	function gnf_merge_count_evidencias( $raw ) {
		if ( empty( $raw ) ) { return 0; }
		$arr = is_array( $raw ) ? $raw : json_decode( (string) $raw, true );
		return is_array( $arr ) ? count( $arr ) : 0;
	}
}
