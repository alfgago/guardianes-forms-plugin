<?php
/**
 * Comando WP-CLI: wp gnf merge-duplicate-centros
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GNF_Merge_Centros_Command {

	/**
	 * Fusiona centros educativos duplicados en un canonico.
	 *
	 * ## OPTIONS
	 *
	 * [--execute]
	 * : Aplica los cambios. Sin esta bandera es dry-run (no escribe nada).
	 *
	 * [--codigo=<codigo_mep>]
	 * : Limita a duplicados con ese codigo MEP.
	 *
	 * [--centro=<id>]
	 * : Limita al grupo que contiene ese centro.
	 *
	 * [--canonical=<id>]
	 * : Fuerza cual ID es el correcto dentro del grupo afectado.
	 *
	 * [--limit=<n>]
	 * : Procesa como maximo N grupos.
	 *
	 * ## EXAMPLES
	 *     wp gnf merge-duplicate-centros
	 *     wp gnf merge-duplicate-centros --centro=79799 --execute
	 *
	 * @when after_wp_load
	 */
	public function __invoke( $args, $assoc_args ) {
		global $wpdb;

		$execute = isset( $assoc_args['execute'] );
		$dry_run = ! $execute;

		if ( $dry_run ) {
			WP_CLI::warning( 'DRY-RUN: no se escribira nada. Usa --execute para aplicar.' );
		} else {
			WP_CLI::warning( 'EJECUCION REAL. Asegurate de tener un backup completo de la BD.' );
		}

		$groups = gnf_merge_find_duplicate_groups();
		$groups = $this->filter_groups( $groups, $assoc_args );

		if ( isset( $assoc_args['limit'] ) ) {
			$groups = array_slice( $groups, 0, (int) $assoc_args['limit'] );
		}

		if ( empty( $groups ) ) {
			WP_CLI::success( 'No se encontraron grupos de duplicados con los filtros dados.' );
			return;
		}

		$total_groups = 0;
		$total_dups   = 0;

		foreach ( $groups as $g ) {
			$canonical = isset( $assoc_args['canonical'] ) ? (int) $assoc_args['canonical'] : $g['canonical'];
			$members   = array_merge( array( $g['canonical'] ), $g['duplicates'] );
			if ( ! in_array( $canonical, $members, true ) ) {
				WP_CLI::warning( "Grupo {$g['key']}: --canonical={$canonical} no pertenece al grupo, se omite." );
				continue;
			}
			$dups = array_values( array_diff( $members, array( $canonical ) ) );

			WP_CLI::log( "Grupo: {$g['key']}" );
			WP_CLI::log( "  Canonico: #{$canonical}" );
			$total_groups++;

			foreach ( $dups as $dup ) {
				if ( $execute ) {
					$wpdb->query( 'START TRANSACTION' );
				}
				try {
					$stats = gnf_merge_centros_pair( $canonical, $dup, $dry_run );
					if ( $execute ) {
						$wpdb->query( 'COMMIT' );
					}
					$total_dups++;
					WP_CLI::log( sprintf(
						'  Duplicado #%d -> entries:%d (%d conflictos), matriculas:%d (%d conflictos), notifs:%d, usuarios:%d, anios ACF: %d nuevos/%d unidos',
						$dup,
						$stats['entries_moved'],
						$stats['entries_conflicts'],
						$stats['matriculas_moved'],
						$stats['matriculas_conflicts'],
						$stats['notifs_moved'],
						$stats['users_moved'],
						$stats['annual_copied'],
						$stats['annual_merged']
					) );
				} catch ( \Throwable $e ) {
					if ( $execute ) {
						$wpdb->query( 'ROLLBACK' );
					}
					WP_CLI::warning( "  Duplicado #{$dup} fallo y se revirtio: " . $e->getMessage() );
				}
			}
		}

		$verb = $dry_run ? 'Se fusionarian' : 'Se fusionaron';
		WP_CLI::success( "{$verb} {$total_dups} duplicado(s) en {$total_groups} grupo(s)." );
	}

	/**
	 * Aplica filtros --codigo / --centro sobre los grupos.
	 */
	private function filter_groups( $groups, $assoc_args ) {
		if ( isset( $assoc_args['centro'] ) ) {
			$centro = (int) $assoc_args['centro'];
			$groups = array_values( array_filter( $groups, function ( $g ) use ( $centro ) {
				return in_array( $centro, array_merge( array( $g['canonical'] ), $g['duplicates'] ), true );
			} ) );
		}
		if ( isset( $assoc_args['codigo'] ) ) {
			$codigo = trim( (string) $assoc_args['codigo'] );
			$groups = array_values( array_filter( $groups, function ( $g ) use ( $codigo ) {
				return 0 === strpos( $g['key'], $codigo . '|' );
			} ) );
		}
		return $groups;
	}
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'gnf merge-duplicate-centros', 'GNF_Merge_Centros_Command' );
}
