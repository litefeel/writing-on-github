<?php
/**
 * Interfaces with the GitHub API
 * @package Writing_On_GitHub
 */

/**
 * Class Writing_On_GitHub_Api
 */
class Writing_On_GitHub_Api {

	/**
	 * Application container.
	 *
	 * @var Writing_On_GitHub
	 */
	protected $app;

	/**
	 * GitHub fetch client.
	 *
	 * @var Writing_On_GitHub_Fetch_Client
	 */
	protected $fetch;

	/**
	 * Github persist client.
	 *
	 * @var Writing_On_GitHub_Persist_Client
	 */
	protected $persist;

	/**
	 * Instantiates a new Api object.
	 *
	 * @param Writing_On_GitHub $app Application container.
	 */
	public function __construct( Writing_On_GitHub $app ) {
		$this->app = $app;
	}

	/**
	 * Lazy-load fetch client.
	 *
	 * @return Writing_On_GitHub_Fetch_Client
	 */
	public function fetch() {
		if ( ! $this->fetch ) {
			$this->fetch = new Writing_On_GitHub_Fetch_Client( $this->app );
		}

		return $this->fetch;
	}

	/**
	 * Lazy-load persist client.
	 *
	 * @return Writing_On_GitHub_Persist_Client
	 */
	public function persist() {
		if ( ! $this->persist ) {
			$this->persist = new Writing_On_GitHub_Persist_Client( $this->app );
		}

		return $this->persist;
	}
}
