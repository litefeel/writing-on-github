<?php
/**
 * API Blob model.
 * @package Writing_On_GitHub
 */

/**
 * Class Writing_On_GitHub_Blob
 */
class Writing_On_GitHub_Blob {

	/**
	 * Complete blob content.
	 *
	 * @var string
	 */
	protected $content;

	/**
	 * Blob sha.
	 *
	 * @var string
	 */
	protected $sha;

	/**
	 * Blob path.
	 *
	 * @var string
	 */
	protected $path;

	/**
	 * Post id.
	 *
	 * @var int
	 */
	protected $id;

	/**
	 * Whether the blob has frontmatter.
	 *
	 * @var boolean
	 */
	protected $frontmatter = false;

	/**
	 * Instantiates a new Blob object.
	 *
	 * @param stdClass $data Raw blob data.
	 */
	public function __construct( stdClass $data ) {
		$this->interpret_data( $data );
	}

	public function id() {
		return $this->id;
	}

	public function set_id($id) {
		$this->id = $id;
	}

	/**
	 * Returns the raw blob content.
	 *
	 * @return string
	 */
	public function content() {
		return $this->content;
	}

	/**
	 * Set's the blob's content.
	 *
	 * @param string $content Raw blob content.
	 * @param bool   $base64 Whether the content is base64 encoded.
	 *
	 * @return $this
	 */
	public function set_content( $content, $base64 = false ) {
		if ( $base64 ) {
			$content = base64_decode( $content );
		}

		$this->frontmatter = '---' === substr( $this->content = $content, 0, 3 ) ? true : false;

		return $this;
	}
	/**
	 * Returns the blob sha.
	 *
	 * @return string
	 */
	public function sha() {
		return $this->sha;
	}

	/**
	 * Return's the blob path.
	 *
	 * @return string
	 */
	public function path() {
		return $this->path;
	}

	/**
	 * Whether the blob has frontmatter.
	 *
	 * @return bool
	 */
	public function has_frontmatter() {
		return $this->frontmatter;
	}

	/**
	 * Returns the formatted/filtered blob content used for import.
	 *
	 * @return string
	 */
	public function content_import() {
		$content = $this->content();

		if ( $this->has_frontmatter() ) {
			// Break out content.
			preg_match( '/(^---(.*?)---$)?(.*)/ms', $content, $matches );
			$content = array_pop( $matches );
		}

		if ( function_exists( 'wpmarkdown_markdown_to_html' ) ) {
			$content = wpmarkdown_markdown_to_html( $content );
		}

		/**
		 * Filters the content for import.
		 */
		return apply_filters( 'wogh_content_import', trim( $content ) );
	}

	/**
	 * Returns the blob meta.
	 *
	 * @return array
	 */
	public function meta() {
		$meta = array();

		if ( $this->has_frontmatter() ) {
			// Break out meta, if present.
			preg_match( '/(^---(.*?)---$)?(.*)/ms', $this->content(), $matches );
			array_pop( $matches );

			$meta = spyc_load( $matches[2] );
			if ( isset( $meta['permalink'] ) ) {
				$meta['permalink'] = str_replace( home_url(), '', $meta['permalink'] );
			}
		}

		return $meta;
	}

	/**
	 * Formats the blob into an API call body.
	 *
	 * @return stdClass
	 */
	// public function to_body() {
	// 	$data = new stdClass;

	// 	$data->mode = '100644';
	// 	$data->type = 'blob';

	// 	$data->path = $this->path();

	// 	if ( $this->sha() ) {
	// 		$data->sha = $this->sha();
	// 	} else {
	// 		$data->content = $this->content();
	// 	}

	// 	return $data;
	// }


	/**
	 * Formats the blob into an API call body.
	 *
	 * @return stdClass
	 */
	public function to_body() {
		$data = new stdClass;

		// $data->mode = '100644';
		// $data->type = 'blob';

		$data->path = $this->path();
		$data->content = base64_encode( $this->content() );
		$data->sha = $this->sha;

		return $data;
	}

	/**
	 * Interprets the blob's data into properties.
	 */
	protected function interpret_data( $data ) {
		$this->sha  = isset( $data->sha  ) ? $data->sha  : '';
		$this->path = isset( $data->path ) ? $data->path : '';

		$this->set_content(
			isset( $data->content ) ? trim( $data->content ) : '',
			isset( $data->encoding ) && 'base64' === $data->encoding ? true : false
		);
	}
}
