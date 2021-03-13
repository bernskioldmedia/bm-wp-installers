<?php

namespace BernskioldMedia\WP\Installers\Make;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use function Symfony\Component\String\u;

class ConfigCommand extends MakeCommand {

	protected static $basePath    = '/config/environments/';
	protected static $defaultName = 'make:config';
	protected static $stubName    = 'config';

	protected function configure() {
		parent::configure();
		$this->addOption( 'database', 'd', InputOption::VALUE_REQUIRED, 'The database name to use in the config.' )
		     ->addOption( 'multisite', 'm', InputOption::VALUE_NONE, false )
		     ->addOption( 'domain', 'u', InputOption::VALUE_OPTIONAL, '' );
	}

	protected function getReplacements( InputInterface $input ): array {
		$name = $input->getArgument( 'name' );

		$args = [
			'{{ databaseName }}' => $input->getOption( 'database' ),
			'{{ name }}'         => u( $name )->snake()->title( true )->toString(),
			'{{ salts }}'        => $this->getSalts(),
		];

		if ( 'local' === $input->getArgument( 'name' ) ) {
			$args['{{ environment }}'] = 'local';
			$args['{{ logging }}']     = 'true';
		} elseif ( 'staging' === $input->getArgument( 'name' ) ) {
			$args['{{ environment }}'] = 'staging';
			$args['{{ logging }}']     = 'false';
		} else {
			$args['{{ environment }}'] = 'production';
			$args['{{ logging }}']     = 'false';
		}

		if ( $input->getOption( 'multisite' ) ) {
			$args['{{ multisite }}'] = $this->getMultisiteConfig( $input->getOption( 'domain' ) );
		} else {
			$args['{{ multisite }}'] = '';
		}

		return $args;
	}

	protected function getSalts() {
		return file_get_contents( 'https://api.wordpress.org/secret-key/1.1/salt/' );
	}

	protected function getMultisiteConfig( $domain ) {
		return "
/**
* Environment-Specific Multisite Variables
*/
define( 'NOBLOGREDIRECT', 'https://<?php echo $domain; ?>' );
define( 'DOMAIN_CURRENT_SITE', '<?php echo $domain; ?>' );";
	}

}
