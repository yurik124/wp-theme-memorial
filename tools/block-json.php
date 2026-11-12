<?php
/**
 * block.json for every plugin block, made from the PHP definitions of the blocks (without WordPress).
 *
 *   php tools/block-json.php build  — write memorial-plugin/blocks/types/<slug>/block.json and blocks-manifest.php;
 *   php tools/block-json.php check  — the files are up to date (CI).
 *
 * The PHP definitions (blocks/content, blocks/home, blocks/legacy, blocks/floating) stay the source: their
 * registration functions are run here with register_block_type() replaced by a recorder.
 *
 * @package memorial-plugin
 */

// phpcs:disable -- a command-line tool, not a part of the site.

define( 'ABSPATH', __DIR__ . '/' );
define( 'MEMORIAL_PLUGIN_BLOCK_JSON_BUILD', true );
define( 'MEMORIAL_PLUGIN_DIR', dirname( __DIR__ ) . '/memorial-plugin' );

$memorial_hooks  = array();
$memorial_blocks = array();

function __( $s, $d = null ) { return $s; }
function add_action( $hook, $cb, $prio = 10, $args = 1 ) { $GLOBALS['memorial_hooks'][ $hook ][] = $cb; return true; }
function add_filter( $hook, $cb, $prio = 10, $args = 1 ) { return add_action( $hook, $cb, $prio, $args ); }
function add_shortcode() {}
function apply_filters( $hook, $value ) { return $value; }
function get_option( $name, $default = false ) { return $default; }
function register_block_type( $name, $args = array() ) { $GLOBALS['memorial_blocks'][ $name ] = $args; return true; }
function register_block_template() {}
function register_rest_route() {}
function memorial_plugin_disabled_files() { return array(); }
function memorial_plugin_optional() { return array(); }

$dir = MEMORIAL_PLUGIN_DIR;
require $dir . '/blocks/block-meta.php';
require $dir . '/blocks/legacy/legacy.php';
require $dir . '/blocks/content/content-blocks.php';
require $dir . '/blocks/floating/floating.php';
require $dir . '/blocks/home/home.php';

// Registration functions of the four families (the legacy one is an anonymous init callback).
foreach ( $memorial_hooks['init'] ?? array() as $cb ) {
	if ( $cb instanceof Closure ) {
		$ref = new ReflectionFunction( $cb );
		if ( str_ends_with( $ref->getFileName(), 'legacy/legacy.php' ) && false !== strpos( file_get_contents( $ref->getFileName() ), 'memorial_plugin_legacy_block_attrs' ) ) {
			try {
				$cb();
			} catch ( Throwable $e ) { // register_block_template() of the same file — not needed here.
			}
		}
	}
}
memorial_plugin_content_blocks_register();
memorial_plugin_home_blocks_register();
memorial_plugin_floating_register();

/**
 * block.json of one block.
 */
function memorial_block_json( $name, $args ) {
	$attrs = array();
	foreach ( (array) ( $args['attributes'] ?? array() ) as $key => $def ) {
		$a = array_intersect_key( $def, array_flip( array( 'type', 'default', 'enum' ) ) );
		// A list built from the site (the icon set) is given at run time, not here.
		if ( 'memorial-plugin/icon' === $name && 'name' === $key ) {
			unset( $a['enum'] );
		}
		$attrs[ $key ] = $a;
	}
	$supports = (array) ( $args['supports'] ?? array() );
	$json     = array(
		'$schema'     => 'https://schemas.wp.org/trunk/block.json',
		'apiVersion'  => 3,
		'name'        => $name,
		'title'       => $args['title'],
		'category'    => $args['category'],
		'icon'        => $args['icon'],
		'description' => $args['description'],
		'keywords'    => array_values( $args['keywords'] ?? array() ),
		'textdomain'  => 'memorial-plugin',
	);
	if ( $attrs ) {
		$json['attributes'] = $attrs;
	}
	$json['supports'] = $supports;
	if ( ! empty( $args['example'] ) ) {
		$example = $args['example'];
		if ( 'memorial-plugin/icon' === $name ) {
			$example['attributes']['name'] = 'candle';
		}
		$example['attributes'] = (object) ( $example['attributes'] ?? array() );
		$json['example']       = $example;
	}
	return json_encode( $json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n";
}

$out   = $dir . '/blocks/types';
$files = array();
$meta  = array();
ksort( $memorial_blocks );
foreach ( $memorial_blocks as $name => $args ) {
	$slug                                  = substr( $name, strlen( 'memorial-plugin/' ) );
	$files[ "$out/$slug/block.json" ]      = memorial_block_json( $name, $args );
	$meta[ $slug ]                         = json_decode( $files[ "$out/$slug/block.json" ], true );
}
// The manifest WordPress reads instead of the JSON files (wp_register_block_metadata_collection).
$files[ "$out/blocks-manifest.php" ] = "<?php\n// Made by tools/block-json.php from blocks/types/*/block.json — do not edit by hand.\nreturn " . var_export( $meta, true ) . ";\n";

$cmd  = $argv[1] ?? 'check';
$fail = 0;
foreach ( $files as $file => $content ) {
	if ( 'build' === $cmd ) {
		@mkdir( dirname( $file ), 0777, true );
		file_put_contents( $file, $content );
	} elseif ( ! is_file( $file ) || file_get_contents( $file ) !== $content ) {
		$fail = 1;
		echo '::error::' . substr( $file, strlen( dirname( __DIR__ ) ) + 1 ) . " is out of date — run: php tools/block-json.php build\n";
	}
}
// Files of blocks that are gone.
foreach ( glob( "$out/*/block.json" ) as $file ) {
	if ( ! isset( $files[ $file ] ) ) {
		if ( 'build' === $cmd ) {
			unlink( $file );
			@rmdir( dirname( $file ) );
		} else {
			$fail = 1;
			echo "::error::$file belongs to no block — run: php tools/block-json.php build\n";
		}
	}
}
echo count( $memorial_blocks ) . ' blocks' . ( 'build' === $cmd ? ' written' : ( $fail ? '' : ', block.json up to date' ) ) . "\n";
exit( $fail );
