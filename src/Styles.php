<?php

namespace EasySCSSandJS;

use Exception;
use RuntimeException;
use ScssPhp\ScssPhp\Compiler;
use ScssPhp\ScssPhp\OutputStyle;

class Styles {
	private static self $instance;
	private array $savedLocale = [];
	private Compiler $scssCompiler;

	public function __construct() {
		require_once __DIR__ . '/../vendor/scssphp/scss.inc.php';
		$this->scssCompiler = new Compiler();
	}

	public static function add(
		string $handle,
		string $file,
		array $dependencies = [],
		array $variables = [],
		bool $enqueue = true
	): void {
		self::instance()->addStyle( $handle, $file, $dependencies, $variables, $enqueue );
	}

	public function addStyle(
		string $handle,
		string $file,
		array $dependencies = [],
		array $variables = [],
		bool $enqueue = true
	): void {
		$isURL = filter_var( $file, FILTER_VALIDATE_URL );
		if ( ! file_exists( $file ) && ! $isURL ) {
			throw new RuntimeException( "$file does not exist" );
		}
		if ( $isURL ) {
			$outputDirectory = trailingslashit( $this->createOutputDirectory() );
			$url             = $file;
			$fileName        = 'cached-' . str_replace( [ 'https', 'http', '://', '?', '&', '/' ], [ '', '', '', '-', '-', '-' ], $url ) . '.css';
			if ( ! file_exists( $outputDirectory . $fileName ) ) {
				file_put_contents( $outputDirectory . $fileName, file_get_contents( $url ) );
			}

			$file = $outputDirectory . $fileName;
		}

		$file           = realpath( $file );
		$extraVariables = apply_filters( 'easy_scss_extra_variables', $variables, $handle, $file, $dependencies );

		$compiledUrl = $this->compile( $file, $handle, $extraVariables );
		if ( $compiledUrl === '' ) {
			return;
		}

		wp_register_style( $handle, $compiledUrl, $dependencies );

		if ( $enqueue ) {
			wp_enqueue_style( $handle );
		}
	}

	private function createOutputDirectory(): string {
		$uploads_dir = apply_filters( 'easy_scss_storage_folder', trailingslashit( wp_upload_dir()['basedir'] ) . $this->getStorageFolderName() );
		if ( ! is_dir( $uploads_dir ) ) {
			wp_mkdir_p( $uploads_dir );
		}

		return $uploads_dir;
	}

	private function getStorageFolderName(): string {
		return apply_filters( 'easy_scss_storage_folder_name', 'compiled-scss-and-js' );
	}

	private function compile( string $file, string $handle, array $variables = [] ): string {
		$filename                 = basename( $file );
		$fileDirectory            = dirname( $file );
		$filenameWithoutExtension = str_replace( '.', '-', $filename );
		$directoryId              = $this->hashDirectory( dirname( $file ) );
		$variablesId              = crc32( serialize( $variables ) );
		$finalCSSFilename         = "$handle-$filenameWithoutExtension-$variablesId-$directoryId.css";
		$finalMapFilename         = "$handle-$filenameWithoutExtension-$variablesId-$directoryId.map";

		$outputDirectory    = trailingslashit( $this->createOutputDirectory() );
		$outputDirectoryUrl = trailingslashit( $this->getOutputDirectoryUrl() );

		if ( file_exists( $outputDirectory . $finalCSSFilename ) ) {
			return $outputDirectoryUrl . $finalCSSFilename;
		}

		$this->setLocale( LC_NUMERIC, 'en_US' );

		$content = $this->prepareFile( $file, $variables );

		$this->scssCompiler->setOutputStyle( apply_filters( 'easy_scss_output_style', OutputStyle::COMPRESSED ) );
		$this->scssCompiler->addImportPath( $fileDirectory );

		$shouldCreateSourceMap = apply_filters( 'easy_scss_create_source_map', true );

		if ( $shouldCreateSourceMap === true ) {
			$this->scssCompiler->setSourceMap( Compiler::SOURCE_MAP_INLINE );

			$this->scssCompiler->setSourceMapOptions( [
				'sourceMapURL'      => $outputDirectoryUrl . $finalMapFilename,
				'sourceMapFilename' => str_replace( ABSPATH, '', $outputDirectory . $finalCSSFilename ),
				'sourceMapBasepath' => ABSPATH,
				'sourceRoot'        => '/',
			] );
		} else {
			$this->scssCompiler->setSourceMap( Compiler::SOURCE_MAP_NONE );
		}

		try {
			$content = $this->scssCompiler->compileString( $content )->getCss();
			$content = apply_filters( 'easy_scss_after_compilation', $content, $handle, $file );
			$this->removeOldFiles( $outputDirectory . "$handle-$filenameWithoutExtension-$variablesId-*" );
			file_put_contents( $outputDirectory . $finalCSSFilename, $content );

			$this->resetLocale( LC_NUMERIC );

			return $outputDirectoryUrl . $finalCSSFilename;
		} catch ( Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				echo "SCSS file ($file) could not be compiled. Error details:\n";
				echo $e->getMessage() . "\n" . $e->getFile() . ' Line:' . $e->getLine() . "\n";
				echo $e->getTraceAsString() . "\n\n";
			} else {
				echo "One or more SCSS files could not be compiled. Enable WP_DEBUG to see the error details.\n";
			}
		}

		$this->resetLocale( LC_NUMERIC );

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
		return apply_filters( 'easy_scss_storage_url', trailingslashit( wp_upload_dir()['baseurl'] ) . $this->getStorageFolderName() );
	}

	private function setLocale( $category, $locale ): void {
		if ( $category === LC_ALL ) {
			throw new RuntimeException( 'Can not set LC_ALL' );
		}
		$this->savedLocale[ $category ] = setlocale( $category, 0 );
		setlocale( $category, $locale );
	}

	private function prepareFile( string $file, array $variables = [] ): string {
		if ( $this->getExtension( $file ) !== 'scss' ) {
			return file_get_contents( $file );
		}

		$content = '';
		foreach ( $variables as $key => $value ) {
			if ( is_array( $value ) ) {
				$newValue = '(';
				foreach ( $value as $valueKey => $valueValue ) {
					$newValue .= $valueKey . ': ' . $this->sanitizeVariableValue( $valueValue ) . ', ';
				}
				$value = $newValue . ')';
			} else {
				$value = $this->sanitizeVariableValue( $value );
			}
			$content .= '$' . $key . ': ' . $value . '; ';
		}
		$content = apply_filters( 'easy_scss_add_code_before_content', $content, $file );
		$content .= '@import "' . basename( $file ) . '";';
		$content = apply_filters( 'easy_scss_add_code_after_content', $content, $file );

		return $content;
	}

	private function getExtension( string $file ): string {
		$explodedPath = explode( '.', $file );

		return $explodedPath[ count( $explodedPath ) - 1 ];
	}

	private function sanitizeVariableValue( $value ): string {
		$isPx         = $this->endsWith( $value, 'px' );
		$isEm         = $this->endsWith( $value, 'em' );
		$isPercentage = $this->endsWith( $value, '%' );
		$isVh         = $this->endsWith( $value, 'vh' );
		$isVw         = $this->endsWith( $value, 'vw' );
		$isRem        = $this->endsWith( $value, 'rem' );
		$isHex        = $this->startsWith( $value, '#' );
		if ( is_string( $value ) && ! $isEm && ! $isPx && ! $isPercentage && ! $isVh && ! $isVw && ! $isRem && ! $isHex ) {
			$value = "\"$value\"";
		}
		if ( $value === false || $value === null ) {
			$value = 'false !default';
		}

		return $value;
	}

	private function endsWith( string $haystack, string $needle ): bool {
		$length = strlen( $needle );
		if ( $length === 0 ) {
			return true;
		}

		return ( substr( $haystack, - $length ) === $needle );
	}

	private function startsWith( string $haystack, string $needle ): bool {
		return ( strpos( $haystack, $needle ) === 0 );
	}

	private function removeOldFiles( string $filenameStart ): void {
		array_map( 'unlink', glob( $filenameStart ) );
	}

	private function resetLocale( $category ): void {
		if ( ! isset( $this->savedLocale[ $category ] ) ) {
			return;
		}
		setlocale( $category, $this->savedLocale[ $category ] );
		unset( $this->savedLocale[ $category ] );
	}

	public static function instance(): self {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	public function compileAndGetURL(
		string $handle,
		string $file,
		array $dependencies = [],
		array $variables = []
	): string {
		$isURL = filter_var( $file, FILTER_VALIDATE_URL );
		if ( ! file_exists( $file ) && ! $isURL ) {
			throw new RuntimeException( "$file does not exist" );
		}
		if ( $isURL ) {
			$outputDirectory = trailingslashit( $this->createOutputDirectory() );
			$url             = $file;
			$fileName        = 'cached-' . str_replace( [ 'https', 'http', '://', '?', '&', '/' ], [ '', '', '', '-', '-', '-' ], $url ) . '.css';
			if ( ! file_exists( $outputDirectory . $fileName ) ) {
				file_put_contents( $outputDirectory . $fileName, file_get_contents( $url ) );
			}

			$file = $outputDirectory . $fileName;
		}

		$file           = realpath( $file );
		$extraVariables = apply_filters( 'easy_scss_extra_variables', $variables, $handle, $file, $dependencies );

		return $this->compile( $file, $handle, $extraVariables );
	}
}
