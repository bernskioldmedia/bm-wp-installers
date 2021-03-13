<?php

namespace BernskioldMedia\WP\Installers\Create;

use BernskioldMedia\WP\Installers\RunsCommands;
use BernskioldMedia\WP\Installers\TouchesFiles;
use Exception;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
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
		'https://github.com/bernskioldmedia/bm-wp-experience/archive/master.zip --force',
		'safe-svg',
		'https://github.com/wp-premium/advanced-custom-fields-pro/archive/master.zip --force',
		'https://github.com/wp-premium/gravityforms/archive/master.zip --force',
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
		$clientNameQuestion = new Question( '<options=bold><fg=red>*</> Client Name:</> ', 'en_US' );
		$clientNameQuestion->setValidator( function ( $answer ) {
			if ( ! empty( $answer ) ) {
				throw new RuntimeException( 'You must provide a client name.' );
			}

			return $answer;
		} );

		$localeQuestion      = new Question( '<options=bold>Website Locale [en_US]:</> ', 'en_US' );
		$websiteUrlQuestion  = new Question( '<options=bold>Website URL [' . $websiteUrlDefault . ']:</> ', $websiteUrlDefault );
		$websiteNameQuestion = new Question( '<options=bold>Website Name [' . $websiteNameDefault . ']:</> ', $websiteNameDefault );

		$adminUsernameQuestion = new Question( '<options=bold><fg=red>*</> Admin Username:</> ', '' );
		$adminUsernameQuestion->setValidator( function ( $answer ) {
			if ( ! empty( $answer ) ) {
				throw new RuntimeException( 'You must provide an admin username.' );
			}

			return $answer;
		} );

		$adminEmailQuestion = new Question( '<options=bold><fg=red>*</> Admin E-Mail Address:</> ', '' );
		$adminEmailQuestion->setValidator( function ( $answer ) {
			if ( ! empty( $answer ) ) {
				throw new RuntimeException( 'You must provide an admin e-mail address.' );
			}

			return $answer;
		} );

		$multisiteQuestion     = new ConfirmationQuestion( '<options=bold>Install as multisite?</>', false );
		$multisiteTypeQuestion = new ChoiceQuestion( '<options=bold>Use subdomains or subfolders?</>', [
			'subdomains',
			'subfolders',
		] );

		$useTemplateQuestion = new ConfirmationQuestion( '<options=bold>Use Template [y]:</>', true );

		$ccTemplateQuestion = new ChoiceQuestion( '<options=bold>Company Cloud Template Name [Gallant]: </>', [
			'gallant',
			'vivacious',
		], 'gallant' );

		/**
		 * Ask Introductory Questions
		 */
		$clientName    = $helper->ask( $input, $output, $clientNameQuestion );
		$websiteName   = $helper->ask( $input, $output, $websiteNameQuestion );
		$websiteUrl    = $helper->ask( $input, $output, $websiteUrlQuestion );
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

		/**
		 * Construct Options
		 */
		$databaseName = u( $slug )->snake()->lower();

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
		 * Create the MySQL Database
		 */
		$output->writeln( 'Creating Database...' );
		$this->runShellCommand( "mysql -u root -e \"create database if not exists $databaseName; GRANT ALL PRIVILEGES ON \`$databaseName\`.* TO 'wp'@'localhost'; FLUSH PRIVILEGES;\" " );

		/**
		 * Creating GitHub Repository and Clone it
		 */
		$output->writeln( 'Creating GitHub Repository' );
		$this->runShellCommand( "gh repo create \"bernskioldmedia/$slug\" --private --template=\"bernskioldmedia/company-cloud-website-template\" -y" );

		/**
		 * Get the latest version.
		 */
		chdir( $directory );
		$output->writeln( 'Cloning GitHub Repository' );
		$this->runShellCommand( 'git pull origin master' );


		/**
		 * Install WordPress and Plugins
		 */
		if ( ( $process = $this->runCommands( $initCommands, $input, $output ) )->isSuccessful() ) {

			if ( '1' === $multisite ) {
				$installCommand = 'wp core multisite-install --title="' . $websiteName . '" --url="' . $websiteUrl . '" --subdomains="' . $multisiteType === 'subdomains' ? 'true' : 'false' . '" --admin_user="' . $adminUsername . '" --admin_email="' . $adminEmail . '" --admin_password="' . $adminPassword . '" --skip-config --skip-email';
			} else {
				$installCommand = 'wp core install --title="' . $websiteName . '" --url="' . $websiteUrl . '" --admin_user="' . $adminUsername . '" --admin_email="' . $adminEmail . '" --admin_password="' . $adminPassword . '" --skip-email';
			}

			$this->runCommands( [ $installCommand ], $input, $output );
			$this->setupWordPress( $locale, $input, $output );
			$this->installPlugins( $multisite, $input, $output );
		}


		$output->writeln( '<info><options=bold>THE WEBSITE HAS BEEN INSTALLED AND SET UP</></info>' );
		$output->writeln( 'Here are your admin details. Please save them in 1Password.' );
		$output->writeln( '<options=bold>Username: </>' . $adminUsername );
		$output->writeln( '<options=bold>Password: </>' . $adminPassword );

		return $process->getExitCode();
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
			$setupHomePageProcess = $this->runCommands( [ "wp post create --post_type=page --post_title='$homePageName' --porcelain" ], $input, $output );
			$homePageId           = $setupHomePageProcess->isSuccessful() ? $setupHomePageProcess->getOutput() : '';

			$setupBlogPageProcess = $this->runCommands( [ "wp post create --post_type=page --post_title='$blogPageName' --porcelain" ], $input, $output );
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

	protected function installPlugins( $network, $input, $output ) {

		$commands = [];

		foreach ( self::$plugins as $plugin ) {
			$commands[] = 'wp plugin install ' . $plugin;
		}

		if ( '1' === $network ) {
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