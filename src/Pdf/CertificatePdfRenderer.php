<?php

declare( strict_types=1 );

namespace ForgeBench\InductionRecords\Pdf;

use Dompdf\Dompdf;
use Dompdf\Options;
use RuntimeException;

/**
 * Renders certificate data to an A4 PDF with dompdf.
 *
 * DejaVu Sans ships with dompdf and is embedded (subset) in the file, so the
 * certificate looks the same on every reader and covers accented names.
 * Remote resources, inline PHP and JavaScript are all disabled.
 */
final class CertificatePdfRenderer {

	private const FONT = 'DejaVu Sans';

	/**
	 * Page number position: right-aligned at the 18 mm side margin, on the
	 * first footer line.
	 */
	private const PAGE_NUMBER_SIZE   = 7.5;
	private const PAGE_NUMBER_MARGIN = 51.0;
	private const PAGE_NUMBER_BOTTOM = 63.0;

	public function __construct(
		private readonly CertificateHtml $html,
		private readonly string $temp_dir
	) {
	}

	/**
	 * @return string The PDF file contents.
	 * @throws RuntimeException When dompdf produces no output.
	 */
	public function render( CertificateData $data ): string {
		$options = new Options();
		$options->setDefaultPaperSize( 'a4' );
		$options->setDefaultPaperOrientation( 'portrait' );
		$options->setDefaultFont( self::FONT );
		$options->setIsFontSubsettingEnabled( true );
		$options->setIsRemoteEnabled( false );
		$options->setIsPhpEnabled( false );
		$options->setIsJavascriptEnabled( false );
		$options->setTempDir( $this->temp_dir );

		$dompdf = new Dompdf( $options );
		$dompdf->setPaper( 'A4', 'portrait' );
		$dompdf->loadHtml( $this->html->build( $data ), 'UTF-8' );
		$dompdf->render();

		$canvas  = $dompdf->getCanvas();
		$metrics = $dompdf->getFontMetrics();
		$font    = $metrics->getFont( self::FONT );
		$widest  = str_replace( array( '{PAGE_NUM}', '{PAGE_COUNT}' ), '99', $data->page_label );
		$width   = $metrics->getTextWidth( $widest, $font, self::PAGE_NUMBER_SIZE );

		$canvas->page_text(
			$canvas->get_width() - self::PAGE_NUMBER_MARGIN - $width,
			$canvas->get_height() - self::PAGE_NUMBER_BOTTOM,
			$data->page_label,
			$font,
			self::PAGE_NUMBER_SIZE,
			array( 0.31, 0.34, 0.37 )
		);

		$pdf = $dompdf->output();
		if ( '' === $pdf ) {
			throw new RuntimeException( 'The PDF renderer produced no output.' );
		}

		return $pdf;
	}
}
