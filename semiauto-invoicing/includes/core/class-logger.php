<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'OG_PSR_Logger' ) ) {
	/**
	 * Logger Class
	 * specialized logging for PDF generation and system errors.
	 */
	class OG_PSR_Logger {
	
		/**
		 * Log an error message (always captured for system health and troubleshooting)
		 *
		 * @param string $message
		 * @param string|int $context Optional context (e.g., order ID)
		 */
		public static function error( $message, $context = '' ) {
			self::log( $message, 'error', $context );
		}

		/**
		 * Log a warning message (always captured for system health and troubleshooting)
		 *
		 * @param string $message
		 * @param string|int $context Optional context (e.g., order ID)
		 */
		public static function warning( $message, $context = '' ) {
			self::log( $message, 'warning', $context );
		}

		/**
		 * Log an info message (controlled by ogpsr_enable_activity_logging setting)
		 *
		 * @param string $message
		 * @param string|int $context Optional context (e.g., order ID)
		 */
		public static function info( $message, $context = '' ) {
			if ( self::is_activity_logging_enabled() ) {
				self::log( $message, 'info', $context );
			}
		}

		/**
		 * Log a debug message (controlled by ogpsr_enable_activity_logging setting)
		 *
		 * @param string $message
		 * @param string|int $context Optional context (e.g., order ID)
		 */
		public static function debug( $message, $context = '' ) {
			if ( self::is_activity_logging_enabled() ) {
				self::log( $message, 'debug', $context );
			}
		}

		/**
		 * Check if verbose activity logging is enabled in plugin settings
		 *
		 * @return bool
		 */
		public static function is_activity_logging_enabled() {
			if ( class_exists( 'OG_PSR_Settings' ) ) {
				return 'yes' === OG_PSR_Settings::get( 'ogpsr_enable_activity_logging', 'no' );
			}
			return 'yes' === get_option( 'ogpsr_enable_activity_logging', 'no' );
		}

		/**
		 * Core logging function delegating to WC_Logger
		 *
		 * @param string $message
		 * @param string $level info|error|warning|debug
		 * @param string|int $context
		 */
		private static function log( $message, $level, $context ) {
			if ( ! function_exists( 'wc_get_logger' ) ) {
				return;
			}

			$logger       = wc_get_logger();
			$full_message = $message;

			if ( '' !== $context && null !== $context ) {
				$full_message .= ' [Context: ' . (string) $context . ']';
			}

			$logger->log( $level, $full_message, array( 'source' => 'semiauto-invoicing' ) );
		} // End Log method

		public static function get_log_file_path() {
			if ( ! function_exists( 'wc_get_logger' ) ) {
				return false;
			}

			$upload_dir = wp_upload_dir();
			$logs_dir   = defined( 'WC_LOG_DIR' ) ? WC_LOG_DIR : $upload_dir['basedir'] . '/wc-logs/';

			global $wp_filesystem;
			if ( empty( $wp_filesystem ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				WP_Filesystem();
			}

			if ( $wp_filesystem && $wp_filesystem->is_dir( $logs_dir ) ) {
				$dir_list = $wp_filesystem->dirlist( $logs_dir );
				if ( is_array( $dir_list ) ) {
					$files    = array();
					$prefixes = array( 'semiauto-invoicing-', 'woo-smart-receipt-' );
					foreach ( $dir_list as $file_info ) {
						$name    = $file_info['name'];
						$matched = false;
						foreach ( $prefixes as $prefix ) {
							if ( 0 === strpos( $name, $prefix ) && '.log' === substr( $name, -4 ) ) {
								$matched = true;
								break;
							}
						}
						if ( $matched ) {
							$time = isset( $file_info['lastmod'] ) ? (int) $file_info['lastmod'] : 0;
							if ( ! $time && $wp_filesystem && $wp_filesystem->exists( $logs_dir . $name ) ) {
								$time = (int) $wp_filesystem->mtime( $logs_dir . $name );
							}
							$files[] = array(
								'path' => $logs_dir . $name,
								'time' => $time,
							);
						}
					}

					if ( ! empty( $files ) ) {
						// Sort files by modification time (unix timestamp), newest first
						usort(
							$files,
							function ( $a, $b ) {
								return (int) $b['time'] <=> (int) $a['time'];
							}
						);
						return $files[0]['path'];
					}
				}
			}

			// Fallback to WooCommerce today's expected path
			if ( class_exists( 'WC_Log_Handler_File' ) ) {
				$handler = new WC_Log_Handler_File();
				return $handler->get_log_file_path( 'semiauto-invoicing' );
			}

			return false;
		}

		/**
		 * Reads the last X lines of the log file using bounded memory-efficient seeking.
		 *
		 * @param int $lines Number of lines to read.
		 * @return string
		 */
		public static function get_recent_logs( $lines = 50 ) {
			$file = static::get_log_file_path();
		
			global $wp_filesystem;
			if ( empty( $wp_filesystem ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				WP_Filesystem();
			}

			if ( ! $file || ! $wp_filesystem || ! $wp_filesystem->exists( $file ) || ! $wp_filesystem->is_readable( $file ) ) {
				return __( 'No recent activity found. Try saving settings or generating a document to start logging.', 'semiauto-invoicing' );
			}

			$file_size = (int) $wp_filesystem->size( $file );
			if ( $file_size <= 0 ) {
				return __( 'Log file is currently empty.', 'semiauto-invoicing' );
			}

			// Security check: ensure path is within allowable logs directory before reading
			$upload_dir = wp_upload_dir();
			$logs_dir   = defined( 'WC_LOG_DIR' ) ? WC_LOG_DIR : $upload_dir['basedir'] . '/wc-logs/';
			$is_safe    = class_exists( 'OG_PSR_Security' ) && OG_PSR_Security::is_path_within( $file, $logs_dir );
			if ( ! $is_safe ) {
				return __( 'Access to the requested log file is not permitted.', 'semiauto-invoicing' );
			}

			// Memory ceiling: prevent loading multi-megabyte log files into RAM
			$max_bytes = 128 * 1024; // 128 KB
			if ( $file_size > $max_bytes ) {
				return __( 'Log file exceeds preview memory threshold. Please inspect via WooCommerce > Status > Logs.', 'semiauto-invoicing' );
			}

			// Compliant WP_Filesystem read for files within memory budget
			$data = (string) $wp_filesystem->get_contents( $file );
			if ( empty( $data ) ) {
				return __( 'Log file is currently empty.', 'semiauto-invoicing' );
			}

			// Return the last X lines
			$all_lines = explode( "\n", trim( $data ) );
			if ( count( $all_lines ) > $lines ) {
				$all_lines = array_slice( $all_lines, -$lines );
			}

			return implode( "\n", $all_lines );
		}
	} // End class OG_PSR_Logger
} // End if !class_exists
