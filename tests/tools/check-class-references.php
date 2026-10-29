<?php
/**
 * Developer tool: checks that every class referenced in the plugin resolves.
 *
 * Tokenises each PHP file, resolves names used with ::, new and instanceof
 * against the file's namespace and use statements, then asks the autoloader
 * for each one. Catches a missing `use` after moving a class between folders.
 *
 * Run inside WordPress:
 *   wp eval-file wp-content/plugins/satori-manifest/tests/tools/check-class-references.php
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.NamingConventions.PrefixAllGlobals -- CLI developer tool.

$satori_manifest_root     = dirname( __DIR__, 2 );
$satori_manifest_problems = array();
$satori_manifest_files    = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $satori_manifest_root, FilesystemIterator::SKIP_DOTS ) );

foreach ( $satori_manifest_files as $satori_manifest_file ) {
	$satori_manifest_path = $satori_manifest_file->getPathname();

	if ( ! str_ends_with( $satori_manifest_path, '.php' ) || preg_match( '#/(vendor|node_modules|build|tests)/#', $satori_manifest_path ) ) {
		continue;
	}

	$tokens    = token_get_all( (string) file_get_contents( $satori_manifest_path ) );
	$namespace = '';
	$uses      = array();
	$count     = count( $tokens );

	for ( $i = 0; $i < $count; $i++ ) {
		$token = $tokens[ $i ];

		if ( ! is_array( $token ) ) {
			continue;
		}

		if ( T_NAMESPACE === $token[0] ) {
			$namespace = trim( (string) $tokens[ $i + 2 ][1] );
			continue;
		}

		if ( T_USE === $token[0] && isset( $tokens[ $i + 2 ][1] ) && in_array( $tokens[ $i + 2 ][0], array( T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED ), true ) ) {
			$fqn = ltrim( $tokens[ $i + 2 ][1], '\\' );
			$uses[ substr( strrchr( '\\' . $fqn, '\\' ), 1 ) ] = $fqn;
			continue;
		}

		$is_name = in_array( $token[0], array( T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED ), true );

		if ( ! $is_name ) {
			continue;
		}

		$next = $tokens[ $i + 1 ] ?? '';
		$prev = $tokens[ $i - 2 ] ?? '';

		$used_static = is_array( $next ) && T_DOUBLE_COLON === $next[0];
		$used_new    = is_array( $prev ) && in_array( $prev[0], array( T_NEW, T_INSTANCEOF ), true );

		if ( ! $used_static && ! $used_new ) {
			continue;
		}

		$name = $token[1];

		if ( in_array( strtolower( $name ), array( 'self', 'static', 'parent' ), true ) ) {
			continue;
		}

		if ( T_NAME_FULLY_QUALIFIED === $token[0] ) {
			$fqn = ltrim( $name, '\\' );
		} elseif ( isset( $uses[ $name ] ) ) {
			$fqn = $uses[ $name ];
		} else {
			$fqn = '' !== $namespace ? $namespace . '\\' . $name : $name;
		}

		if ( ! class_exists( $fqn ) && ! interface_exists( $fqn ) && ! enum_exists( $fqn ) ) {
			$satori_manifest_problems[] = sprintf( '%s:%d  %s', substr( $satori_manifest_path, strlen( $satori_manifest_root ) + 1 ), $token[2], $fqn );
		}
	}
}

if ( $satori_manifest_problems ) {
	echo "Unresolved class references:\n  " . implode( "\n  ", array_unique( $satori_manifest_problems ) ) . "\n";
	exit( 1 );
}

echo "All class references resolve.\n";
