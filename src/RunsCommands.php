<?php

namespace BernskioldMedia\WP\Installers;

use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

trait RunsCommands {

	protected function requirePackage( $package ) {
		$composer = $this->findComposer();

		return $this->runShellCommand( "$composer require $package" );
	}

	protected function createPrivateProject( $package, $directory, $scripts = true ) {
		$composer = $this->findComposer();
		$command  = "$composer create-project $package $directory --add-repository --repository='{\"packagist.org\": false}' --repository=\"https://repo.packagist.com/bernskioldmedia/\" --stability=dev --prefer-dist --remove-vcs";

		if ( ! $scripts ) {
			$command .= ' --no-scripts';
		}

		return $this->runShellCommand( $command );
	}

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
