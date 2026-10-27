<?php
/** Short durable leases shared by expensive workers. Never use cached option reads. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function gnf_work_lock_invalidate( $key ) {
	wp_cache_delete( $key, 'options' );
	wp_cache_delete( 'notoptions', 'options' );
}

function gnf_work_lock_read( $key ) {
	global $wpdb;
	$raw = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $key ) );
	if ( ! empty( $wpdb->last_error ) ) { throw new RuntimeException( 'Work lease read failed' ); }
	return null === $raw ? false : maybe_unserialize( $raw );
}

/** INSERT IGNORE admits exactly one owner; expiry recovery deletes only the value read. */
function gnf_acquire_work_lock( $key, $seconds ) {
	global $wpdb;
	if ( $seconds < 1 ) { throw new InvalidArgumentException( 'Work lease lifetime must be positive' ); }
	$old = gnf_work_lock_read( $key );
	if ( false !== $old ) {
		if ( ! is_array( $old ) || ! isset( $old['expires'] ) || (int) $old['expires'] > time() ) { return false; }
		gnf_release_work_lock( $key, $old );
	}
	$lock = array( 'token' => bin2hex( random_bytes( 16 ) ), 'expires' => time() + (int) $seconds );
	$inserted = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $key, maybe_serialize( $lock ) ) );
	gnf_work_lock_invalidate( $key );
	if ( false === $inserted ) { throw new RuntimeException( 'Work lease insertion failed' ); }
	return 1 === $inserted ? $lock : false;
}

function gnf_work_lock_is_owned( $key, $lock ) {
	return is_array( $lock ) && isset( $lock['expires'] ) && (int) $lock['expires'] > time() && $lock === gnf_work_lock_read( $key );
}

/** Compare-and-delete prevents a late worker from releasing a successor's lease. */
function gnf_release_work_lock( $key, $lock ) {
	global $wpdb;
	$removed = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND BINARY option_value = BINARY %s", $key, maybe_serialize( $lock ) ) );
	gnf_work_lock_invalidate( $key );
	if ( false === $removed ) { throw new RuntimeException( 'Work lease release failed' ); }
	return 1 === $removed;
}
