<?php

abstract class Writing_On_GitHub_TestCase extends WP_HTTP_TestCase {

	/**
	 * @var string
	 */
	protected $data_dir;

	/**
	 * @var Writing_On_GitHub|Mockery\Mock
	 */
	protected $app;

	/**
	 * @var Writing_On_GitHub_Controller|Mockery\Mock
	 */
	protected $controller;

	/**
	 * @var Writing_On_GitHub_Request|Mockery\Mock
	 */
	protected $request;

	/**
	 * @var Writing_On_GitHub_Import|Mockery\Mock
	 */
	protected $import;

	/**
	 * @var Writing_On_GitHub_Export|Mockery\Mock
	 */
	protected $export;

	/**
	 * @var Writing_On_GitHub_Response|Mockery\Mock
	 */
	protected $response;

	/**
	 * @var Writing_On_GitHub_Payload|Mockery\Mock
	 */
	protected $payload;

	/**
	 * @var Writing_On_GitHub_Api|Mockery\Mock
	 */
	protected $api;

	/**
	 * @var Writing_On_GitHub_Semaphore|Mockery\Mock
	 */
	protected $semaphore;

	/**
	 * @var Writing_On_GitHub_Database|Mockery\Mock
	 */
	protected $database;

	/**
	 * @var Writing_On_GitHub_Post|Mockery\Mock
	 */
	protected $post;

	/**
	 * @var Writing_On_GitHub_Blob|Mockery\Mock
	 */
	protected $blob;

	/**
	 * @var Writing_On_GitHub_Cache|Mockery\Mock
	 */
	protected $api_cache;

	/**
	 * @var Writing_On_GitHub_Fetch_Client|Mockery\Mock
	 */
	protected $fetch;

	/**
	 * @var Writing_On_GitHub_Persist_Client|Mockery\Mock
	 */
	protected $persist;

	public function setUp() {
		parent::setUp();

		$this->data_dir = dirname( __DIR__ ) . '/data/';

		$this->app        = Mockery::mock( 'Writing_On_GitHub' );
		$this->controller = Mockery::mock( 'Writing_On_GitHub_Controller' );
		$this->request    = Mockery::mock( 'Writing_On_GitHub_Request' );
		$this->import     = Mockery::mock( 'Writing_On_GitHub_Import' );
		$this->export     = Mockery::mock( 'Writing_On_GitHub_Export' );
		$this->response   = Mockery::mock( 'Writing_On_GitHub_Response' );
		$this->payload    = Mockery::mock( 'Writing_On_GitHub_Payload' );
		$this->api        = Mockery::mock( 'Writing_On_GitHub_Api' );
		$this->semaphore  = Mockery::mock( 'Writing_On_GitHub_Semaphore' );
		$this->database   = Mockery::mock( 'Writing_On_GitHub_Database' );
		$this->post       = Mockery::mock( 'Writing_On_GitHub_Post' );
		$this->blob       = Mockery::mock( 'Writing_On_GitHub_Blob' );
		$this->api_cache  = Mockery::mock( 'Writing_On_GitHub_Cache' );
		$this->fetch      = Mockery::mock( 'Writing_On_GitHub_Fetch_Client' );
		$this->persist    = Mockery::mock( 'Writing_On_GitHub_Persist_Client' );

		Writing_On_GitHub::$instance = $this->app;

		$this->app
			->shouldReceive( 'request' )
			->andReturn( $this->request )
			->byDefault();
		$this->app
			->shouldReceive( 'import' )
			->andReturn( $this->import )
			->byDefault();
		$this->app
			->shouldReceive( 'export' )
			->andReturn( $this->export )
			->byDefault();
		$this->app
			->shouldReceive( 'response' )
			->andReturn( $this->response )
			->byDefault();
		$this->app
			->shouldReceive( 'api' )
			->andReturn( $this->api )
			->byDefault();
		$this->app
			->shouldReceive( 'semaphore' )
			->andReturn( $this->semaphore )
			->byDefault();
		$this->app
			->shouldReceive( 'database' )
			->andReturn( $this->database )
			->byDefault();
		$this->app
			->shouldReceive( 'blob' )
			->andReturn( $this->blob )
			->byDefault();
		$this->app
			->shouldReceive( 'cache' )
			->andReturn( $this->api_cache )
			->byDefault();
		$this->api
			->shouldReceive( 'fetch' )
			->andReturn( $this->fetch )
			->byDefault();
		$this->api
			->shouldReceive( 'persist' )
			->andReturn( $this->persist )
			->byDefault();
	}

	public function tearDown() {
		Mockery::close();
	}
}
