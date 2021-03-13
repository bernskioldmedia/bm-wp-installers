<?php

namespace BernskioldMedia\WP\Installers;

use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

trait RunsCommands {

	protected function runShellCommand( $command, $args = [], ?callable $callable = null ) {
		$process = Process::fromShellCommandline( $command );
		$process->run( $callable, $args );

		return $process;
	}

	protected function runCommands( $commands, InputInterface $input, OutputInterface $output ) {
		$process = Process::fromShellCommandline( implode( ' && ', $commands ), null, null, null, null );

		if ( '\\' !== DIRECTORY_SEPARATOR && file_exists( '/dev/tty' ) && is_readable( '/dev/tty' ) ) {
			try {
				$process->setTty( true );
			} catch ( RuntimeException $e ) {
				$output->writeln( 'Warning: ' . $e->getMessage() );
			}
		}

		$process->run( function ( $type, $line ) use ( $output ) {
			$output->write( '    ' . $line );
		} );

		return $process;
	}

	/**
	 * Get the composer command for the environment.
	 *
	 * @return string
	 */
	protected function findComposer() {
		$composerPath = getcwd() . '/composer.phar';

		if ( file_exists( $composerPath ) ) {
			return '"' . PHP_BINARY . '" ' . $composerPath;
		}

		return 'composer';
	}

}
