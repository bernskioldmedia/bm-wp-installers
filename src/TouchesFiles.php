<?php

namespace BernskioldMedia\WP\Installers;

use RuntimeException;

trait TouchesFiles {

	/**
	 * Replace the given string in the given file.
	 *
	 * @param  string  $search
	 * @param  string  $replace
	 * @param  string  $file
	 */
	protected function replaceInFile( string $search, string $replace, string $file ) {
		file_put_contents( $file, str_replace( $search, $replace, file_get_contents( $file ) ) );
	}

	/**
	 * Verify that the application does not already exist.
	 *
	 * @param  string  $directory
	 *
	 * @return void
	 */
	protected function verifyFolderDoesntExist( $directory ) {
		if ( ( is_dir( $directory ) || is_file( $directory ) ) && $directory != getcwd() ) {
			throw new RuntimeException( 'The directory already exists!' );
		}
	}

}
