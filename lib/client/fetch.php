<?php
/**
 * Fetch API client class.
 * @package Writing_On_GitHub
 */

/**
 * Class Writing_On_GitHub_Fetch_Client
 */
class Writing_On_GitHub_Fetch_Client extends Writing_On_GitHub_Base_Client {

	/**
	 * Compare a commit by sha with master from the GitHub API
	 *
	 * @param string $sha Sha for commit to retrieve.
	 *
	 * @return array[Writing_On_GitHub_File_Info]|WP_Error
	 */
	public function compare( $sha ) {
		// https://api.github.com/repos/litefeel/testwpsync/compare/861f87e8851b8debb78db548269d29f8da4d94ac...master
		$endpoint = $this->compare_endpoint();
		$branch = $this->branch();
		$data = $this->call( 'GET', "$endpoint/$sha...$branch" );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$files = array();
		foreach ($data->files as $file) {
			$file->path = $file->filename;
			$files[] = new Writing_On_GitHub_File_Info($file);
		}

		return $files;
	}

	/**
	 * Calls the content API to get the post's contents and metadata
	 *
	 * Returns Object the response from the API
	 *
	 * @param Writing_On_GitHub_Post $post Post to retrieve remote contents for.
	 *
	 * @return mixed
	 */
	public function remote_contents( $post ) {
		return $this->call( 'GET', $this->content_endpoint( $post->github_path() ) );
	}

	public function exists( $path ) {
		$result = $this->call( 'GET', $this->content_endpoint( $path ) );
		if ( is_wp_error( $result ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Retrieves a tree by sha recursively from the GitHub API
	 *
	 * @param string $sha Commit sha to retrieve tree from.
	 *
	 * @return Writing_On_GitHub_Tree|WP_Error
	 */
	public function tree_recursive( $sha = '_default' ) {

		if ( '_default' === $sha ) {
			$sha = $this->branch();
		}

		$data = $this->call( 'GET', $this->tree_endpoint() . '/' . $sha . '?recursive=1' );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$files = array();

		foreach ( $data->tree as $index => $thing ) {
			// We need to remove the trees because
			// the recursive tree includes both
			// the subtrees as well the subtrees' blobs.
			if ( 'blob' === $thing->type ) {
				$thing->status = '';
				$files[] = new Writing_On_GitHub_File_Info( $thing );
			}
		}

		return $files;
	}

	/**
	 * Retrieves the blob data for a given sha
	 *
	 * @param stdClass $blob Tree blob data.
	 *
	 * @return Writing_On_GitHub_Blob|WP_Error
	 */
	public function blob( $blob ) {
		if ( $cache = $this->app->cache()->fetch_blob( $blob->sha ) ) {
			return $cache;
		}

		$data = $this->call( 'GET', $this->blob_endpoint() . '/' . $blob->sha );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$data->path = $blob->path;
		$obj = new Writing_On_GitHub_Blob( $data );

		return $this->app->cache()->set_blob( $obj->sha(), $obj );
	}
}
