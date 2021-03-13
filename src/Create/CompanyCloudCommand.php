<?php

namespace BernskioldMedia\WP\Installers\Create;

use BernskioldMedia\WP\Installers\RunsCommands;
use BernskioldMedia\WP\Installers\TouchesFiles;
use Exception;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Finder\Finder;
use Symfony\Component\String\Slugger\AsciiSlugger;
use function Symfony\Component\String\u;

class CompanyCloudCommand extends Command {

	use TouchesFiles, RunsCommands;

	protected static $defaultName = 'create:companycloud';

	/**
	 * These plugins will be run in the order given.
	 *
	 * @var string[]
	 */
	protected static $plugins = [
		'enable-media-replace',
		'ilmenite-cookie-consent',
		'duracelltomi-google-tag-manager',
		'gravity-forms-google-analytics-event-tracking',
		'redirection',
		'duplicate-post',
		'wordpress-seo',
		'safe-svg',
		'https://github.com/bernskioldmedia/bm-wp-experience/archive/master.zip',
		'https://github.com/wp-premium/advanced-custom-fields-pro/archive/master.zip',
		'https://github.com/wp-premium/gravityforms/archive/master.zip',
	];

	/**
	 * When running in multisite mode, these plugins
	 * will be network activated.
	 *
	 * This list should be a subset of the $plugins list.
	 *
	 * @var string[]
	 */
	protected static $networkPlugins = [
		'enable-media-replace',
		'bm-wp-experience',
		'ilmenite-cookie-consent',
		'wordpress-seo',
		'duplicate-post',
		'redirection',
		'save-svg',
		'advanced-custom-fields-pro',
	];


	/**
	 * Configure the command options.
	 *
	 * @return void
	 */
	protected function configure() {
		$this->setDescription( 'Set up a new company cloud website.' )
		     ->addArgument( 'slug', InputArgument::REQUIRED, 'The kebab-case slug of the website.' )
		     ->addOption( 'force', 'f', InputOption::VALUE_NONE, 'Forces install even if the directory already exists' )
		     ->addOption( 'repository', 'r', InputOption::VALUE_OPTIONAL, 'Should a GitHub respository be created or not?', true );
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

		// Get slug.
		$slug = $input->getArgument( 'slug' );

		// Set defaults.
		$websiteNameDefault = str_replace( '-', ' ', $slug );
		$websiteNameDefault = u( $websiteNameDefault )->title( true );
		$websiteUrlDefault  = 'https://' . $slug . '.test';

		/**
		 * Setup Helpers
		 */
		$slugger   = new AsciiSlugger();
		$helper    = $this->getHelper( 'question' );
		$directory = $slug !== '.' ? getcwd() . '/' . $slug : '.';
		$composer  = $this->findComposer();
		$finder    = new Finder();

		/**
		 * Write the intro message.
		 */
		$output->writeln( [
			'<fg=blue>',
			"
  ____                                           ____ _                 _ 
 / ___|___  _ __ ___  _ __   __ _ _ __  _   _   / ___| | ___  _   _  __| |
| |   / _ \| '_ ` _ \| '_ \ / _` | '_ \| | | | | |   | |/ _ \| | | |/ _` |
| |__| (_) | | | | | | |_) | (_| | | | | |_| | | |___| | (_) | |_| | (_| |
 \____\___/|_| |_| |_| .__/ \__,_|_| |_|\__, |  \____|_|\___/ \__,_|\__,_|
                     |_|                |___/                              ",
			'</>',
		] );

		/**
		 * If we are not in forced mode, check if
		 * the folder exists to prevent overwrites.
		 */
		if ( ! $input->getOption( 'force' ) ) {
			$this->verifyFolderDoesntExist( $directory );
		}

		if ( $input->getOption( 'force' ) && $directory === '.' ) {
			throw new RuntimeException( 'Cannot use --force option when using current directory for installation!' );
		}

		/**
		 * Print some intro messages.
		 */
		$output->writeln( 'This script will take you through converting the plugin base to a bespoke plugin project.' );
		$output->writeln( 'You\'ll be asked for some configuration details. Some are required and others will allow you to customize the setup.' );

		/**
		 * Setup Questions
		 */
		$clientNameQuestion = new Question( '<options=bold><fg=red>*</> Client Name:</> ' );
		$clientNameQuestion->setValidator( function ( $answer ) {
			if ( empty( $answer ) ) {
				throw new RuntimeException( 'You must provide a client name.' );
			}

			return $answer;
		} );

		$localeQuestion      = new Question( '<options=bold>Website Locale [en_US]:</> ', 'en_US' );
		$websiteUrlQuestion  = new Question( '<options=bold>Website URL [' . $websiteUrlDefault . ']:</> ', $websiteUrlDefault );
		$websiteNameQuestion = new Question( '<options=bold>Website Name [' . $websiteNameDefault . ']:</> ', $websiteNameDefault );

		$adminUsernameQuestion = new Question( '<options=bold><fg=red>*</> Admin Username:</> ', '' );
		$adminUsernameQuestion->setValidator( function ( $answer ) {
			if ( empty( $answer ) ) {
				throw new RuntimeException( 'You must provide an admin username.' );
			}

			return $answer;
		} );

		$adminEmailQuestion = new Question( '<options=bold><fg=red>*</> Admin E-Mail Address:</> ', '' );
		$adminEmailQuestion->setValidator( function ( $answer ) {
			if ( empty( $answer ) ) {
				throw new RuntimeException( 'You must provide an admin e-mail address.' );
			}

			return $answer;
		} );

		$multisiteQuestion     = new ConfirmationQuestion( '<options=bold>Install as multisite [n]: </>', false );
		$multisiteTypeQuestion = new ChoiceQuestion( '<options=bold>Use subdomains or subfolders?</>', [
			'subdomains',
			'subfolders',
		] );

		$useTemplateQuestion = new ConfirmationQuestion( '<options=bold>Use Template [y]: </>', true );

		$ccTemplateQuestion = new ChoiceQuestion( '<options=bold>Company Cloud Template Name [Gallant]: </>', [
			'gallant',
			'vivacious',
		], 'gallant' );

		$packagesQuestion = ( new ChoiceQuestion( "<options=bold>What Company Cloud modules do you want to add?</>\n You may select several by comma-separating. Press enter to not install any.",
			[
				'bm-people',
				'bm-customers',
				'bm-modals',
				'bm-navigation-shelf',
			] ) )->setMultiselect( true );

		/**
		 * Ask Introductory Questions
		 */
		$clientName    = $helper->ask( $input, $output, $clientNameQuestion );
		$websiteName   = $helper->ask( $input, $output, $websiteNameQuestion );
		$websiteUrl    = $helper->ask( $input, $output, $websiteUrlQuestion );
		$websiteDomain = str_replace( 'https://', '', $websiteUrl );
		$locale        = $helper->ask( $input, $output, $localeQuestion );
		$adminPassword = $this->getRandomPassword();
		$adminUsername = $helper->ask( $input, $output, $adminUsernameQuestion );
		$adminEmail    = $helper->ask( $input, $output, $adminEmailQuestion );
		$multisite     = $helper->ask( $input, $output, $multisiteQuestion );

		if ( $multisite ) {
			$multisiteType = $helper->ask( $input, $output, $multisiteTypeQuestion );
		}

		$useTemplate = $helper->ask( $input, $output, $useTemplateQuestion );

		if ( $useTemplate ) {
			$ccTemplate = $helper->ask( $input, $output, $ccTemplateQuestion );
		}

		$packages = $helper->ask( $input, $output, $packagesQuestion );

		/**
		 * Construct Options
		 */
		$databaseName     = u( $slug )->snake()->lower();
		$clientNameDashed = u( $clientName )->snake();
		$clientNameDashed = str_replace( '_', '-', $clientNameDashed );

		// Pause a second to make sure everything's set up.
		sleep( 1 );

		/**
		 * Maybe remove the directory if force.
		 */
		if ( $directory !== '.' && $input->getOption( 'force' ) ) {
			if ( PHP_OS_FAMILY === 'Windows' ) {
				$this->runShellCommand( "rd / s / q \"$directory\"" );
			} else {
				$this->runShellCommand( "rm -rf \"$directory\"" );
			}
		}

		/**
		 * Attempt to create the dir.
		 */
		$this->verifyFolderDoesntExist( $directory );

		/**
		 * Create the MySQL Database
		 */
		$output->writeln( 'Creating Database...' );
		$this->runShellCommand( "mysql -u root -e \"create database if not exists $databaseName; GRANT ALL PRIVILEGES ON \`$databaseName\`.* TO 'wp'@'localhost'; FLUSH PRIVILEGES;\" " );

		/**
		 * Creating GitHub Repository and Clone it
		 */
		$output->writeln( 'Creating Company Cloud website from template' );
		$this->createPrivateProject( 'bernskioldmedia/company-cloud-website-template', $directory, false );

		// Go into the directory.
		chdir( $directory );

		/**
		 * Create a Local Config
		 */
		$configCommand = $this->getApplication()->find( 'make:config' );
		$configArgs    = [
			'name'       => 'local',
			'--database' => $databaseName,
			'--domain'   => $websiteDomain,
		];

		if ( $multisite ) {
			$configArgs['--multisite'] = true;
		}

		$configInput = new ArrayInput( $configArgs );
		$configCommand->run( $configInput, $output );

		/**
		 * Secure the website.
		 */
		$output->writeln( 'Securing website URL.' );
		$this->runShellCommand( "valet secure $slug" );

		/**
		 * Download and install the selected template.
		 */
		if ( $useTemplate ) {

			// Require the template.
			$this->requirePackage( "bernskioldmedia/$ccTemplate" );

			// Create a child theme.
			$this->createPrivateProject( "bernskioldmedia/companycloud-child", "wp-content/themes/$clientNameDashed", false );

			$this->replaceInFile( 'bernskioldmedia/companycloud-child', 'bm-clients/theme-' . $clientNameDashed, "wp-content/themes/$clientNameDashed/composer.json" );

		} else {
			// Create a Pliant theme project.
			$this->createPrivateProject( 'bernskioldmedia/pliant', "wp-content/themes/$clientNameDashed", false );
		}

		/**
		 * Load the selected packages.
		 */
		foreach ( $packages as $package ) {
			$this->requirePackage( "bernskioldmedia/$package" );
		}

		/**
		 * Update Composer.json
		 */
		$output->writeln( 'Updating composer.json' );
		$this->replaceInFile( 'bm-clients/projectname', "bm-clients/$slug", 'composer.json' );
		$this->replaceInFile( 'CLIENTNAME', $clientName, 'composer.json' );

		/**
		 * Updating README.
		 */
		$output->writeln( 'Updating README.md' );
		$this->replaceInFile( 'CLIENTNAME', $clientName, 'README.md' );

		/**
		 * Run Composer Install
		 */
		$output->writeln( 'Installing and setting up.' );
		$this->runShellCommand( 'composer update' );

		/**
		 * Download WordPress
		 */
		$this->runShellCommand( "wp core download --locale=$locale --skip-content --quiet" );

		/**
		 * Install WordPress
		 */
		if ( $multisite ) {
			$subdomains = $multisiteType === 'subdomains' ? '--subdomains ' : '';
			$this->runShellCommand( "wp core multisite-install --title=\"$websiteName\" --url=\"$websiteUrl\" $subdomains--admin_user=\"$adminUsername\" --admin_email=\"$adminEmail\" --admin_password=\"$adminPassword\" --skip-config --skip-email" );
			$this->runShellCommand( 'wp network meta update 1 fileupload_maxk 1000000' );
		} else {
			$this->runShellCommand( "wp core install --title=\"$websiteName\" --url=\"$websiteUrl\" --admin_user=\"$adminUsername\" --admin_email=\"$adminEmail\" --admin_password=\"$adminPassword\" --skip-email" );
		}

		$this->setupWordPress( $locale, $input, $output );
		$this->installPlugins( $multisite, $input, $output );

		// Activate the client theme.
		$this->runShellCommand( "wp theme activate $clientNameDashed" );

		/**
		 * Create a github repo for the folder.
		 */
		if ( $input->getOption( 'repository' ) ) {

			$output->writeln( 'Creating .git respository.' );
			$this->runShellCommand( 'git init' );
			$this->runShellCommand( "git add ." );
			$this->runShellCommand( "git commit -m \"Initial setup\"" );
			$this->runShellCommand( 'git branch -M main' );

			$output->writeln( 'Creating GitHub repository.' );
			$this->runShellCommand( "gh repo create bernskioldmedia/$slug --private -y" );;
			$this->runShellCommand( 'git push -u origin main' );
		}

		/**
		 * Success!
		 */
		$output->writeln( '<info><options=bold>THE WEBSITE HAS BEEN INSTALLED AND SET UP</></info>' );
		$output->writeln( 'Here are your admin details. Please save them in 1Password.' );

		$table = new Table( $output );
		$table->setRows( [
			[ 'Username', $adminUsername ],
			[ 'Password', $adminPassword ],
			[ 'Admin E-Mail', $adminEmail ],
		] );

		$table->render();

		return 0;
	}

	protected function setupWordPress( $locale, $input, $output ) {
		$commands = [
			'wp option update blogdescription ""',
			'wp option update default_comment_status "closed"',
			'wp option update default_ping_status "closed"',
			"wp option update permalink_structure '/%postname%/'",
			'wp option update thumbnail_size_w "300"',
			'wp option update thumbnail_size_h "300"',
			'wp option update medium_size_w "640"',
			'wp option update medium_size_h "640"',
			'wp option update large_size_w "1200"',
			'wp option update large_size_h "1200"',
			'wp option update posts_per_page "12"',
			'wp option update posts_per_rss "24"',
		];

		$setupWpProcess = $this->runCommands( $commands, $input, $output );

		$homePageName = $locale === 'sv_SE' ? 'Hem' : 'Home';
		$blogPageName = $locale === 'sv_SE' ? 'Blogg' : 'Blog';

		if ( $setupWpProcess->isSuccessful() ) {
			$setupHomePageProcess = $this->runCommands( [ "wp post create --post_type=page --post_title='$homePageName' --porcelain --post_status=publish" ], $input, $output );
			$homePageId           = $setupHomePageProcess->isSuccessful() ? $setupHomePageProcess->getOutput() : '';

			$setupBlogPageProcess = $this->runCommands( [ "wp post create --post_type=page --post_title='$blogPageName' --porcelain --post_status=publish" ], $input, $output );
			$blogPageId           = $setupBlogPageProcess->isSuccessful() ? $setupBlogPageProcess->getOutput() : '';
		}

		$updatePagesCommands = [ 'wp option update show_on_front "page"' ];

		if ( $setupHomePageProcess->isSuccessful() ) {
			$updatePagesCommands[] = 'wp option update page_on_front "' . $homePageId . '"';
		}

		if ( $setupBlogPageProcess->isSuccessful() ) {
			$updatePagesCommands[] = 'wp option update page_for_posts "' . $blogPageId . '"';
		}

		$this->runCommands( $updatePagesCommands, $input, $output );
	}

	protected function installPlugins( $multisite, $input, $output ) {

		$commands = [];

		foreach ( self::$plugins as $plugin ) {
			$commands[] = 'wp plugin install ' . $plugin;
		}

		if ( $multisite ) {
			foreach ( self::$networkPlugins as $plugin ) {
				$commands[] = 'wp plugin activate ' . $plugin;
			}
		} else {
			foreach ( self::$plugins as $plugin ) {
				$commands[] = 'wp plugin activate ' . $plugin;
			}
		}

		$this->runCommands( $commands, $input, $output );

	}

	protected function getRandomPassword(
		$length = 25,
		$keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'
	) {
		$str = '';
		$max = mb_strlen( $keyspace, '8bit' ) - 1;
		if ( $max < 1 ) {
			throw new Exception( '$keyspace must be at least two characters long' );
		}
		for ( $i = 0; $i < $length; ++$i ) {
			$str .= $keyspace[ random_int( 0, $max ) ];
		}

		return $str;
	}
}
