<?php

namespace BernskioldMedia\WP\Installers\Create;

use BernskioldMedia\WP\Installers\RunsCommands;
use BernskioldMedia\WP\Installers\TouchesFiles;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Finder\Finder;
use Symfony\Component\String\Slugger\AsciiSlugger;
use function Symfony\Component\String\u;

class PluginCommand extends Command {

	use TouchesFiles, RunsCommands;

	protected static $defaultName = 'create:plugin';

	/**
	 * Configure the command options.
	 *
	 * @return void
	 */
	protected function configure() {
		$this->setDescription( 'Set up a new WordPress plugin based on the BM WP Plugin Scaffold.' )
		     ->addArgument( 'slug', InputArgument::REQUIRED, 'The plugin slug in kebab-case.' )
		     ->addOption( 'namespace', 'ns', InputOption::VALUE_OPTIONAL, 'What namespace should the plugin use. Leave blank to auto-generate.', null )
		     ->addOption( 'package', 'pkg', InputOption::VALUE_OPTIONAL, 'What name should we give to the composer package.', null )
		     ->addOption( 'force', 'f', InputOption::VALUE_NONE, 'Forces install even if the directory already exists' );
	}

	/**
	 * Execute the command.
	 *
	 * @param  \Symfony\Component\Console\Input\InputInterface    $input
	 * @param  \Symfony\Component\Console\Output\OutputInterface  $output
	 *
	 * @return int
	 */
	protected function execute( InputInterface $input, OutputInterface $output ) {

		$slugger = new AsciiSlugger();
		$helper  = $this->getHelper( 'question' );

		// Get slug.
		$slug = $input->getArgument( 'slug' );

		$directory = $slug !== '.' ? getcwd() . '/' . $slug : '.';
		$composer  = $this->findComposer();
		$finder    = new Finder();

		$output->writeln( [
			'<fg=blue>',
			' _             _   _              __       _  _       
|_)|\/| \    /|_) |_)|    _ o._  (_  _ _._|__|__ | _|
|_)|  |  \/\/ |   |  ||_|(_||| | __)(_(_| |  |(_)|(_| 
                          _|                          ',
			'</>',
		] );

		if ( ! $input->getOption( 'force' ) ) {
			$this->verifyFolderDoesntExist( $directory );
		}

		if ( $input->getOption( 'force' ) && $directory === '.' ) {
			throw new RuntimeException( 'Cannot use --force option when using current directory for installation!' );
		}

		$output->writeln( 'This script will take you through converting the plugin base to a bespoke plugin project.' );
		$output->writeln( 'You\'ll be asked for some configuration details. Some are required and others will allow you to customize the setup.' );

		$pluginNameDefault         = str_replace( '-', ' ', $slug );
		$pluginNameDefault         = u( $pluginNameDefault )->title( true );
		$pluginNameQuestion        = new Question( '<options=bold>Plugin Name (Human Readable) [' . $pluginNameDefault . ']:</> ', $pluginNameDefault );
		$clientNameQuestion        = new Question( '<options=bold>Client Name []:</> ', '' );
		$pluginDescriptionQuestion = new Question( '<options=bold>Plugin Description []:</> ', '' );
		$pluginUrlQuestion         = new Question( '<options=bold>Plugin URL []:</> ', '' );
		$versionQuestion           = new Question( '<options=bold>Version [1.0.0]:</> ', '1.0.0' );

		$pluginName        = $helper->ask( $input, $output, $pluginNameQuestion );
		$clientName        = $helper->ask( $input, $output, $clientNameQuestion );
		$pluginDescription = $helper->ask( $input, $output, $pluginDescriptionQuestion );
		$pluginUrl         = $helper->ask( $input, $output, $pluginUrlQuestion );
		$version           = $helper->ask( $input, $output, $versionQuestion );

		sleep( 1 );

		// Generate package name.
		$package = $input->getOption( 'package' );

		if ( ! $package ) {
			$package = $slugger->slug( $clientName )->lower() . '/' . $slug;
		}

		// Generate Namespace.
		$namespace = $input->getOption( 'namespace' );

		if ( ! $namespace ) {
			$namespace         = 'BernskioldMedia\\' . u( $clientName )->camel()->title() . '\\' . u( $slug )->camel()->title();
			$composerNamespace = str_replace( '\\', '\\\\', $namespace );
		}

		$commands = [
			$composer . " create-project bernskioldmedia/wp-plugin-scaffold \"$directory\" --remove-vcs --prefer-dist",
		];

		if ( $directory != '.' && $input->getOption( 'force' ) ) {
			if ( PHP_OS_FAMILY == 'Windows' ) {
				array_unshift( $commands, "rd /s /q \"$directory\"" );
			} else {
				array_unshift( $commands, "rm -rf \"$directory\"" );
			}
		}

		if ( ( $process = $this->runCommands( $commands, $input, $output ) )->isSuccessful() ) {

			// In all files...
			foreach ( $finder->in( $directory )->name( '*.php' ) as $file ) {

				$output->writeln( '<info>Updating: ' . $file->getRealPath() . '</info>' );

				// ...replace textdomain.
				$this->replaceInFile( 'wp-plugin-scaffold', $slug, $file->getRealPath() );

				// ...replace uppercase prefix.
				$this->replaceInFile( 'WP_PLUGIN_SCAFFOLD', u( $slug )->snake()->upper(), $file->getRealPath() );

				// ...replace namespace.
				$this->replaceInFile( 'BernskioldMedia\\WP\\PluginScaffold', $namespace, $file->getRealPath() );

				// ...replace wp_plugin_scaffold.
				$this->replaceInFile( 'wp_plugin_scaffold', u( $slug )->snake(), $file->getRealPath() );

			}

			/**
			 * Update package.json
			 */
			$output->writeln( '<info>Updating package.json.</info>' );
			$this->replaceInFile( 'wp-plugin-scaffold', $slug, $directory . '/package.json' );
			$this->replaceInFile( 'A WordPress plugin scaffold that we use at Bernskiold Media when developing client specific plugins.', $pluginDescription,
				$directory . '/package.json' );
			$this->replaceInFile( '1.0.0', $version, $directory . '/package.json' );

			/**
			 * Update composer.json
			 */
			$output->writeln( '<info>Updating composer.json.</info>' );
			$this->replaceInFile( 'bernskioldmedia/wp-plugin-scaffold', $package, $directory . '/composer.json' );
			$this->replaceInFile( 'A WordPress plugin scaffold that we use at Bernskiold Media when developing client specific plugins.', $pluginDescription,
				$directory . '/composer.json' );
			$this->replaceInFile( 'BernskioldMedia\\\\WP\\\\PluginScaffold', $composerNamespace, $directory . '/composer.json' );

			/**
			 * Update Main Plugin File
			 */
			$output->writeln( '<info>Updating Main Plugin file.</info>' );
			$this->replaceInFile( 'WP Plugin Scaffold', $pluginName, $directory . '/wp-plugin-scaffold.php' );
			$this->replaceInFile( 'A WordPress plugin scaffold that we use at Bernskiold Media when developing client specific plugins.', $pluginDescription,
				$directory . '/wp-plugin-scaffold.php' );
			$this->replaceInFile( 'https://website.com', $pluginUrl, $directory . '/wp-plugin-scaffold.php' );
			$this->replaceInFile( '1.0.0', $version, $directory . '/wp-plugin-scaffold.php' );
			$this->replaceInFile( 'wp-plugin-scaffold', $slug, $directory . '/wp-plugin-scaffold.php' );

			// Rename main plugin file.
			rename( $directory . '/wp-plugin-scaffold.php', $directory . '/' . $slug . '.php' );

		}

		chdir( $directory );

		$process2 = $this->runCommands( [
			'composer update',
			'npm run setup',
			'npm run i18n',
		], $input, $output );

		$output->writeln( '<info><options=bold>SUCCESS! The plugin has successfully been scaffolded.</></info>' );

		return $process2->getExitCode();
	}

}
