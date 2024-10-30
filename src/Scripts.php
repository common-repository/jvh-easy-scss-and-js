<?php

namespace EasySCSSandJS;

use Exception;
use JShrink\Minifier;
use RuntimeException;

class Scripts {
	private static self $instance;

	public function __construct() {
		require_once __DIR__ . '/../vendor/JShrink/Minifier.php';
	}

	public static function add(
		string $handle,
		string $file,
		array $dependencies = [ 'jquery' ],
		array $variables = [],
		bool $enqueue = true,
		bool $inFooter = true
	): void {
		self::instance()->addScript( $handle, $file, $dependencies, $variables, $enqueue, $inFooter );
	}

	public function addScript(
		string $handle,
		string $file,
		array $dependencies = [ 'jquery' ],
		array $variables = [],
		bool $enqueue = true,
		bool $inFooter = true
	): void {
		$isURL = filter_var( $file, FILTER_VALIDATE_URL );
		if ( ! file_exists( $file ) && ! $isURL ) {
			throw new RuntimeException( "$file does not exist" );
		}
		if ( $isURL ) {
			$outputDirectory = trailingslashit( $this->createOutputDirectory() );
			$url             = $file;
			$fileName        = 'cached-' . str_replace( [ 'https', 'http', '://', '?', '&', '/' ], [ '', '', '', '-', '-', '-' ], $url ) . '.js';
			if ( ! file_exists( $outputDirectory . $fileName ) ) {
				file_put_contents( $outputDirectory . $fileName, file_get_contents( $url ) );
			}

			$file = $outputDirectory . $fileName;
		}

		$file = realpath( $file );

		$compiledUrl = $this->compile( $file, $handle );
		if ( $compiledUrl === '' ) {
			return;
		}

		wp_register_script( $handle, $compiledUrl, $dependencies, false, $inFooter );

		$extraVariables = apply_filters( 'easy_js_extra_variables', $variables, $handle, $file, $dependencies );
		if ( $extraVariables !== [] ) {
			wp_localize_script( $handle, str_replace( '-', '_', $handle ) . '_vars', $extraVariables );
		}

		if ( $enqueue ) {
			wp_enqueue_script( $handle );
		}
	}

	private function createOutputDirectory(): string {
		$uploads_dir = apply_filters( 'easy_js_storage_folder', trailingslashit( wp_upload_dir()['basedir'] ) . $this->getStorageFolderName() );
		if ( ! is_dir( $uploads_dir ) ) {
			wp_mkdir_p( $uploads_dir );
		}

		return $uploads_dir;
	}

	private function getStorageFolderName(): string {
		return apply_filters( 'easy_js_storage_folder_name', 'compiled-scss-and-js' );
	}

	private function compile( string $file, string $handle ): string {
		$filename                 = basename( $file );
		$filenameWithoutExtension = str_replace( '.', '-', $filename );
		$directoryId              = $this->hashDirectory( dirname( $file ) );
		$finalJSFilename          = "$handle-$filenameWithoutExtension-$directoryId.js";

		$outputDirectory    = trailingslashit( $this->createOutputDirectory() );
		$outputDirectoryUrl = trailingslashit( $this->getOutputDirectoryUrl() );

		if ( file_exists( $outputDirectory . $finalJSFilename ) ) {
			return $outputDirectoryUrl . $finalJSFilename;
		}

		try {
			$content = Minifier::minify( file_get_contents( $file ), [ 'flaggedComments' => false ] );
			$content = apply_filters( 'easy_js_after_compilation', $content, $handle, $file );
			$this->removeOldFiles( $outputDirectory . "$handle-$filenameWithoutExtension-*" );
			file_put_contents( $outputDirectory . $finalJSFilename, $content );

			return $outputDirectoryUrl . $finalJSFilename;
		} catch ( Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				echo "JS file ($file) could not be compiled. Error details:\n";
				echo $e->getMessage() . "\n" . $e->getFile() . ' Line:' . $e->getLine() . "\n";
				echo $e->getTraceAsString() . "\n\n";
			} else {
				echo "One or more JS files could not be compiled. Enable WP_DEBUG to see the error details.\n";
			}
		}

		return '';
	}

	private function hashDirectory( $directory ): int {
		if ( ! is_dir( $directory ) ) {
			return 0;
		}

		$total = 0;
		$dir   = dir( $directory );

		while ( false !== ( $file = $dir->read() ) ) {
			if ( $file === '.' || $file === '..' ) {
				continue;
			}
			if ( is_dir( $directory . '/' . $file ) ) {
				$total += $this->hashDirectory( $directory . '/' . $file );
			} else {
				$total += filemtime( $directory . '/' . $file );
			}
		}

		$dir->close();

		return $total;
	}

	private function getOutputDirectoryUrl(): string {
		return apply_filters( 'easy_js_storage_url', trailingslashit( wp_upload_dir()['baseurl'] ) . $this->getStorageFolderName() );
	}

	private function removeOldFiles( string $filenameStart ): void {
		array_map( 'unlink', glob( $filenameStart ) );
	}

	public static function instance(): self {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	public function compileAndGetURL(
		string $handle,
		string $file
	): string {
		$isURL = filter_var( $file, FILTER_VALIDATE_URL );
		if ( ! file_exists( $file ) && ! $isURL ) {
			throw new RuntimeException( "$file does not exist" );
		}
		if ( $isURL ) {
			$outputDirectory = trailingslashit( $this->createOutputDirectory() );
			$url             = $file;
			$fileName        = 'cached-' . str_replace( [ 'https', 'http', '://', '?', '&', '/' ], [ '', '', '', '-', '-', '-' ], $url ) . '.js';
			if ( ! file_exists( $outputDirectory . $fileName ) ) {
				file_put_contents( $outputDirectory . $fileName, file_get_contents( $url ) );
			}

			$file = $outputDirectory . $fileName;
		}

		$file = realpath( $file );

		return $this->compile( $file, $handle );
	}
}
