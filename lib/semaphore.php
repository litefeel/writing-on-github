<?php
/**
 * Locks and unlock the import/export process.
 * @package Writing_On_GitHub
 */

/**
 * Class Writing_On_GitHub_Semaphore
 */
class Writing_On_GitHub_Semaphore {

	/**
	 * Sempahore's option key.
	 */
	const KEY = 'wogh_semaphore_lock';

	/**
	 * Option key when semaphore is locked.
	 */
	const VALUE_LOCKED = 'yes';

	/**
	 * Option key when semaphore is unlocked.
	 */
	const VALUE_UNLOCKED = 'no';

	/**
	 * Clean up the old values on instantiation.
	 */
	public function __construct() {
		delete_option( self::KEY );
	}

	/**
	 * Checks if the Semaphore is open.
	 *
	 * Fails to report it's open if the the Api class can't make a call
	 * or the push lock has been enabled.
	 *
	 * @return bool
	 */
	public function is_open() {
		if ( self::VALUE_LOCKED === get_transient( self::KEY ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Enables the push lock.
	 */
	public function lock() {
		set_transient( self::KEY, self::VALUE_LOCKED, MINUTE_IN_SECONDS );
	}

	/**
	 * Disables the push lock.
	 */
	public function unlock() {
		set_transient( self::KEY, self::VALUE_UNLOCKED, MINUTE_IN_SECONDS );
	}
}
