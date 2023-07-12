<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Class FKWCS_Plugin_Compatibilities
 * Loads all the compatibilities files we have to provide compatibility with each plugin
 */
class FKWCS_Plugin_Compatibilities {
	public static $plugin_compatibilities = array();

	public static function load_all_compatibilities() {
		// load all the FKWCS_Compatibilities files automatically
		$compatibilities_folder = [ 'plugins', ];
		self::include_files( $compatibilities_folder );
	}

	public static function register( $object, $slug ) {
		self::$plugin_compatibilities[ $slug ] = $object;
	}

	public static function get_compatibility_class( $slug ) {
		return ( isset( self::$plugin_compatibilities[ $slug ] ) ) ? self::$plugin_compatibilities[ $slug ] : false;
	}


	public static function include_files( $compatibilities_folder = [] ) {
		foreach ( $compatibilities_folder as $folder ) {
			foreach ( glob( plugin_dir_path( FKWCS_FILE ) . 'compatibilities/' . $folder . '/*.php' ) as $_field_filename ) {
				require_once( $_field_filename ); //phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
			}
		}
	}

}

FKWCS_Plugin_Compatibilities::load_all_compatibilities();
