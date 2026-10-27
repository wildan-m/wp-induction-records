<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords;

use ForgeBench\InductionRecords\Account\AccountEndpoint;
use ForgeBench\InductionRecords\Account\ConfirmationController;
use ForgeBench\InductionRecords\Account\MemberPage;
use ForgeBench\InductionRecords\Account\Shortcode;
use ForgeBench\InductionRecords\Admin\AdminMenu;
use ForgeBench\InductionRecords\Admin\AdminNotices;
use ForgeBench\InductionRecords\Admin\PublishVersionPage;
use ForgeBench\InductionRecords\Admin\RecordInductionPage;
use ForgeBench\InductionRecords\Admin\SettingsPage;
use ForgeBench\InductionRecords\Admin\SignOffsPage;
use ForgeBench\InductionRecords\Admin\SupervisorProfileField;
use ForgeBench\InductionRecords\Archive\ArchivePaths;
use ForgeBench\InductionRecords\Archive\ArchiveStorage;
use ForgeBench\InductionRecords\Archive\DownloadController;
use ForgeBench\InductionRecords\Briefing\BodyNormaliser;
use ForgeBench\InductionRecords\Briefing\BriefingPostType;
use ForgeBench\InductionRecords\Briefing\VersionPolicy;
use ForgeBench\InductionRecords\Briefing\VersionPublisher;
use ForgeBench\InductionRecords\Certificate\CertificateIssuer;
use ForgeBench\InductionRecords\Cli\Command;
use ForgeBench\InductionRecords\Database\SignOffRepository;
use ForgeBench\InductionRecords\Database\VersionRepository;
use ForgeBench\InductionRecords\Import\LegacyCsvReader;
use ForgeBench\InductionRecords\Import\LegacyImporter;
use ForgeBench\InductionRecords\Import\ScanLocator;
use ForgeBench\InductionRecords\Import\VersionResolver;
use ForgeBench\InductionRecords\Induction\InductionService;
use ForgeBench\InductionRecords\Induction\ValidityEvaluator;
use ForgeBench\InductionRecords\Mail\CertificateMailer;
use ForgeBench\InductionRecords\Membership\SubscriptionGate;
use ForgeBench\InductionRecords\Pdf\CertificateHtml;
use ForgeBench\InductionRecords\Pdf\CertificatePdfRenderer;
use ForgeBench\InductionRecords\Template\MergeFieldRenderer;
use WP_CLI;

/**
 * Composition root: builds the services and registers every hook.
 */
final class Plugin {

	public const VERSION = '1.0.0';

	private static ?self $instance = null;

	/**
	 * Runs on plugins_loaded, so WooCommerce and Subscriptions are already loaded.
	 */
	public static function boot(): void {
		if ( null !== self::$instance ) {
			return;
		}

		self::$instance = new self();
		self::$instance->register();
	}

	/**
	 * Certificate archive in wp-content/uploads/induction-records.
	 *
	 * Offloaded uploads (an s3:// or similar stream wrapper) are ignored on
	 * purpose: certificates hold personal data and stay on the local disk.
	 */
	public static function make_archive_storage(): ArchiveStorage {
		$uploads = wp_upload_dir( null, false );
		$basedir = (string) $uploads['basedir'];
		if ( '' === $basedir || str_contains( $basedir, '://' ) ) {
			$basedir = WP_CONTENT_DIR . '/uploads';
		}

		/**
		 * Filters the absolute path of the certificate archive. On servers that
		 * ignore .htaccess files (nginx), point it outside the web root.
		 *
		 * @param string $root Default: the uploads directory plus /induction-records.
		 */
		$root = (string) apply_filters( 'induction_records_archive_root', trailingslashit( $basedir ) . ArchiveStorage::FOLDER );

		return new ArchiveStorage( untrailingslashit( $root ), new ArchivePaths( Installer::archive_key() ) );
	}

	private function register(): void {
		Installer::maybe_upgrade();

		$settings  = new Settings();
		$versions  = VersionRepository::create();
		$signoffs  = SignOffRepository::create();
		$evaluator = new ValidityEvaluator();
		$archive   = self::make_archive_storage();
		$renderer  = new MergeFieldRenderer(
			(string) get_option( 'date_format' ),
			trim( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
			wp_timezone()
		);

		$inductions = new InductionService(
			$versions,
			$signoffs,
			SubscriptionGate::from_environment(),
			new CertificateIssuer( $renderer, new CertificatePdfRenderer( new CertificateHtml(), get_temp_dir() ), $settings ),
			$archive,
			new CertificateMailer(),
			$settings
		);

		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( Installer::class, 'maybe_flush_rewrite_rules' ), 99 );

		( new BriefingPostType( $versions ) )->register();
		( new DownloadController( $signoffs, $archive ) )->register();
		( new ConfirmationController( $inductions ) )->register();

		$member_page = new MemberPage( $signoffs, $versions );
		( new Shortcode( $member_page ) )->register();
		if ( class_exists( 'WooCommerce' ) ) {
			( new AccountEndpoint( $member_page ) )->register();
		}

		if ( is_admin() ) {
			global $wpdb;

			$publisher     = new VersionPublisher( $wpdb, $versions, $signoffs, new VersionPolicy(), new BodyNormaliser(), $evaluator );
			$signoffs_page = new SignOffsPage( $signoffs, $versions, $inductions );
			$record_page   = new RecordInductionPage( $inductions, $versions );
			$publish_page  = new PublishVersionPage( $publisher, $versions, $signoffs );
			$settings_page = new SettingsPage( $settings, $renderer );

			( new AdminMenu( $signoffs_page, $record_page, $publish_page, $settings_page ) )->register();
			$signoffs_page->register();
			$record_page->register();
			$publish_page->register();
			$settings_page->register();
			( new SupervisorProfileField() )->register();
			( new AdminNotices() )->register();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command(
				'induction-records',
				new Command(
					new LegacyImporter(
						new LegacyCsvReader(),
						new ScanLocator(),
						new VersionResolver(),
						$evaluator,
						$versions,
						$signoffs,
						$archive
					)
				)
			);
		}
	}

	public function load_textdomain(): void {
		load_plugin_textdomain( 'induction-records', false, dirname( plugin_basename( dirname( __DIR__ ) . '/induction-records.php' ) ) . '/languages' );
	}
}
