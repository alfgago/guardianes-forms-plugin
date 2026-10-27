<?php
define( 'ABSPATH', dirname( __DIR__ ) . '/' );
require __DIR__ . '/work-lock-fixture.php';
$wpdb = new Work_Lock_DB(); $options = array(); $tests = $fails = 0;
function wp_cache_delete( $key, $group ) { $GLOBALS['invalidated'][] = array( $key, $group ); }
function maybe_serialize( $value ) { return serialize( $value ); }
function maybe_unserialize( $value ) { return unserialize( $value ); }
function check_lock( $ok, $message ) { global $tests, $fails; $tests++; $fails += $ok ? 0 : 1; echo ( $ok ? 'ok: ' : 'FAIL: ' ) . $message . "\n"; }
check_lock( is_file( ABSPATH . 'includes/work-locks.php' ), 'Shared durable lease helper exists' );
if ( $fails ) { exit( 1 ); }
require ABSPATH . 'includes/work-locks.php';
$key = 'gnf_test_work_lock';
$one = gnf_acquire_work_lock( $key, 60 );
check_lock( is_array( $one ) && false === gnf_acquire_work_lock( $key, 60 ), 'Only one contender acquires a live lease' );
check_lock( gnf_work_lock_is_owned( $key, $one ), 'Current lease is recognized from durable storage' );
$two = array( 'token' => 'successor', 'expires' => time() + 60 ); $options[$key] = $two;
check_lock( ! gnf_release_work_lock( $key, $one ) && $options[$key] === $two, 'Late release cannot delete a successor lease' );
check_lock( ! gnf_work_lock_is_owned( $key, $one ), 'Replaced owner cannot publish' );
$options[$key]['expires'] = time();
$three = gnf_acquire_work_lock( $key, 60 );
check_lock( is_array( $three ) && $three['token'] !== $two['token'], 'Lease expiring exactly now can be recovered' );
check_lock( gnf_release_work_lock( $key, $three ) && ! isset( $options[$key] ), 'Owner releases the durable lease' );
$options[$key] = array( 'token' => 'expired', 'expires' => time() - 1 );
$before_work_sql = function() use ( $key, $two ) { $GLOBALS['options'][$key] = $two; };
check_lock( false === gnf_acquire_work_lock( $key, 60 ) && $options[$key] === $two, 'Takeover between expiry read and compare-delete preserves the new owner' );
unset( $options[$key] );
$before_work_sql = function() use ( $key, $two ) { $GLOBALS['options'][$key] = $two; };
check_lock( false === gnf_acquire_work_lock( $key, 60 ) && $options[$key] === $two, 'Concurrent insertion wins without an upsert overwriting it' );
$work_read_failure = true; $threw = false;
try { gnf_acquire_work_lock( $key, 60 ); } catch ( RuntimeException $error ) { $threw = true; }
check_lock( $threw && $options[$key] === $two, 'SQL read failures fail closed without replacing a lease' );
$work_read_failure = false; unset( $options[$key] ); $work_sql_failure = true; $threw = false;
try { gnf_acquire_work_lock( $key, 60 ); } catch ( RuntimeException $error ) { $threw = true; }
check_lock( $threw && ! isset( $options[$key] ), 'SQL insertion failure never grants ownership' );
$work_sql_failure = false;
check_lock( in_array( array( $key, 'options' ), $invalidated, true ) && in_array( array( 'notoptions', 'options' ), $invalidated, true ), 'Direct SQL invalidates positive and negative option caches' );
echo "{$tests} checks, {$fails} failures\n"; exit( $fails ? 1 : 0 );
