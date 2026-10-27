<?php
/** Deterministic SQL interleavings at the durable options-table boundary. */
class Work_Lock_DB {
	public $options = 'wp_options';
	public $last_error = '';
	public function prepare( $sql, ...$args ) { return array( $sql, $args ); }
	public function get_var( $query ) {
		$this->last_error = '';
		if ( ! empty( $GLOBALS['work_read_failure'] ) ) { $this->last_error = 'Read failed'; return null; }
		return isset( $GLOBALS['options'][ $query[1][0] ] ) ? serialize( $GLOBALS['options'][ $query[1][0] ] ) : null;
	}
	public function query( $query ) {
		list( $sql, $args ) = $query;
		$this->last_error = '';
		if ( ! empty( $GLOBALS['before_work_sql'] ) ) { $hook = $GLOBALS['before_work_sql']; unset( $GLOBALS['before_work_sql'] ); $hook( $sql, $args ); }
		if ( ! empty( $GLOBALS['work_sql_failure'] ) ) { $this->last_error = 'Write failed'; return false; }
		list( $key, $value ) = $args;
		if ( 0 === strpos( $sql, 'INSERT IGNORE' ) ) {
			if ( isset( $GLOBALS['options'][ $key ] ) ) { return 0; }
			$GLOBALS['options'][ $key ] = unserialize( $value ); return 1;
		}
		if ( 0 === strpos( $sql, 'DELETE FROM' ) ) {
			if ( ! isset( $GLOBALS['options'][ $key ] ) || serialize( $GLOBALS['options'][ $key ] ) !== $value ) { return 0; }
			unset( $GLOBALS['options'][ $key ] ); return 1;
		}
		throw new RuntimeException( 'Unexpected SQL: ' . $sql );
	}
}
