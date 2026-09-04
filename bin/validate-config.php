<?php
/**
 * Contract validator for ProducerKit.
 *
 * Static analysis — no WordPress, no Composer dependencies. It cross-checks the
 * declarations that are spread across the plugin against the code that consumes
 * them, catching the drift a modular, convention-driven plugin is exposed to:
 *
 *   1. text-domain   — the Text Domain header equals the slug. WordPress.org
 *                      requires it, and a mismatch makes translations silently
 *                      fail to load.
 *   2. version       — the plugin header, the VERSION constant, readme.txt's
 *                      Stable tag, package.json and every block.json agree.
 *                      A Stable tag that does not match the tagged release
 *                      makes WordPress.org serve the wrong code.
 *  2b. i18n          — languages/<slug>.pot exists, was generated for this
 *                      text domain, and its Project-Id-Version matches the
 *                      plugin version. A stale template still loads and still
 *                      translates, just without anything added since.
 *   3. readme        — the headers and sections WordPress.org parses are all
 *                      present, and the tag count is within its limit.
 *   4. screenshots   — `screenshot-N` files and the Nth caption line pair up.
 *   5. modules       — every module in get_registered_modules() has a
 *                      bootstrap on disk and a label; every module directory
 *                      is registered. boot() skips a missing bootstrap with no
 *                      warning, so the plugin would just do less.
 *   6. blocks        — block.json name prefix, text domain, and every
 *                      `file:./x` asset reference resolving to a real file.
 *                      register_block_type() scans for block.json, so a
 *                      mis-declared asset only shows up as a broken block.
 *   7. abilities     — ability names are unique and every category an ability
 *                      claims is actually registered.
 *   8. rest          — every register_rest_route() passes a permission_callback.
 *                      Its absence is a WordPress.org review failure and a
 *                      genuine access-control hole.
 *   9. interactivity — actions.X / callbacks.X referenced by a block's
 *                      render.php resolve to a method in that block's view.js.
 *  12. doc-counts     — numbers the docs assert about the plugin (how many
 *                      blocks, abilities, profiles, REST endpoints) match what
 *                      is on disk. Every one of these had drifted at least
 *                      once; prose does not fail a test suite.
 *
 * Usage:
 *   php bin/validate-config.php [--format=human|json]
 *
 * Exit code: 1 if any ERROR-level issue is found (warnings do not fail CI).
 *
 * @package Farm_Stand_Manager
 */

declare( strict_types = 1 );

$root   = dirname( __DIR__ );
$format = 'human';
foreach ( array_slice( $argv, 1 ) as $arg ) {
	if ( str_starts_with( $arg, '--format=' ) ) {
		$format = substr( $arg, strlen( '--format=' ) );
	}
}

/** Collected issues: each ['level' => 'error'|'warning', 'check' => string, 'message' => string]. */
$issues = [];

/** Record an issue. */
$add = static function ( string $level, string $check, string $message ) use ( &$issues ): void {
	$issues[] = compact( 'level', 'check', 'message' );
};

/** Read a file as text, or '' if it does not exist. */
$read = static function ( string $rel ) use ( $root ): string {
	$path = $root . '/' . $rel;
	return is_file( $path ) ? (string) file_get_contents( $path ) : '';
};

/** Every PHP file under a directory, recursively. */
$php_files_in = static function ( string $rel ) use ( $root ): array {
	$dir = $root . '/' . $rel;
	if ( ! is_dir( $dir ) ) {
		return [];
	}
	$out = [];
	$it  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $f ) {
		if ( $f->getExtension() === 'php' ) {
			$out[] = $f->getPathname();
		}
	}
	sort( $out );
	return $out;
};

// ── Locate the main plugin file ──────────────────────────────────────────────
// By its "Plugin Name:" header, never by the directory name: CI clones into a
// folder named after the repository, so a basename-derived slug breaks the
// moment the repo and the plugin are named differently.
$main_file = null;
foreach ( glob( $root . '/*.php' ) ?: [] as $candidate ) {
	$head = (string) file_get_contents( $candidate, false, null, 0, 8192 );
	if ( preg_match( '/^[ \t]*\*?[ \t]*Plugin Name:[ \t]*\S/mi', $head ) ) {
		$main_file = $candidate;
		break;
	}
}

if ( null === $main_file ) {
	$add( 'error', 'config', 'No PHP file at the repo root carries a "Plugin Name:" header — nothing below can be checked.' );
	$slug = '';
} else {
	$slug      = basename( $main_file, '.php' );
	$main_src  = (string) file_get_contents( $main_file );
	$main_head = substr( $main_src, 0, 8192 );

	// ── Check 1: text domain equals the slug ─────────────────────────────────
	$text_domain = preg_match( '/^[ \t]*\*?[ \t]*Text Domain:[ \t]*(\S+)/mi', $main_head, $td ) ? $td[1] : null;

	if ( null === $text_domain ) {
		$add( 'error', 'text-domain', 'No "Text Domain:" header in ' . basename( $main_file ) . '.' );
	} elseif ( $text_domain !== $slug ) {
		$add( 'error', 'text-domain', "Text Domain '$text_domain' does not match the plugin slug '$slug' — WordPress.org requires them to be identical, and translations will not load." );
	}
}

// ── Check 2: version consistency ─────────────────────────────────────────────
$version_sources = [];
$canonical       = null;

if ( null !== $main_file ) {
	if ( preg_match( '/^\s*\*\s*Version:\s*(\S+)/m', $main_src, $m ) ) {
		$canonical = $m[1];
		$version_sources[ basename( $main_file ) . ' (header)' ] = $m[1];
	} else {
		$add( 'error', 'version', 'No "Version:" header found in ' . basename( $main_file ) . '.' );
	}

	if ( preg_match( "/const\s+VERSION\s*=\s*'([^']+)'/", $main_src, $m ) ) {
		$version_sources[ basename( $main_file ) . ' (VERSION const)' ] = $m[1];
	} else {
		$add( 'warning', 'version', 'No VERSION constant found in ' . basename( $main_file ) . '.' );
	}
}

$readme_src = $read( 'readme.txt' );
if ( '' === $readme_src ) {
	$add( 'error', 'readme', 'Missing readme.txt — required for WordPress.org.' );
} elseif ( preg_match( '/^Stable tag:\s*(\S+)/mi', $readme_src, $m ) ) {
	$version_sources['readme.txt (Stable tag)'] = $m[1];
} else {
	$add( 'error', 'version', 'readme.txt has no "Stable tag:" header — WordPress.org needs it to know which tag to serve.' );
}

$pkg_src = $read( 'package.json' );
if ( '' !== $pkg_src ) {
	$pkg = json_decode( $pkg_src, true );
	if ( isset( $pkg['version'] ) ) {
		$version_sources['package.json'] = $pkg['version'];
	}
}

// Every block.json carries a version used by core as the block's asset
// cache-buster (see wp-includes/blocks.php). Absent or stale means WordPress
// falls back to its own version, which would not change when the plugin ships
// a CSS or view.js fix.
foreach ( glob( $root . '/blocks/*/block.json' ) ?: [] as $block_json ) {
	$block = json_decode( (string) file_get_contents( $block_json ), true );
	if ( isset( $block['version'] ) ) {
		$version_sources[ 'blocks/' . basename( dirname( $block_json ) ) . '/block.json' ] = $block['version'];
	}
}

if ( null !== $canonical ) {
	foreach ( $version_sources as $where => $found ) {
		if ( $found !== $canonical ) {
			$add( 'error', 'version', "$where declares $found but the plugin header says $canonical — run bin/bump-version.php to resync." );
		}
	}
}

// ── Check 2b: the translation template ───────────────────────────────────────
// The .pot is committed so a translator can work from the repository and so a
// diff shows which strings a change added. Its Project-Id-Version carries the
// plugin version, which means it goes stale on every bump — silently, because
// a stale template still loads and still translates, just missing whatever was
// added since.
$pot_path = $root . '/languages/' . $slug . '.pot';

if ( ! is_file( $pot_path ) ) {
	$add( 'error', 'i18n', "No languages/$slug.pot — run bin/make-pot.sh and commit the result." );
} else {
	$pot_head = (string) file_get_contents( $pot_path, false, null, 0, 2048 );

	if ( preg_match( '/^"Project-Id-Version:\s*(.+?)\\\\n"/m', $pot_head, $m ) ) {
		$declared = trim( $m[1] );

		if ( null !== $canonical && ! str_ends_with( $declared, ' ' . $canonical ) ) {
			$add( 'error', 'i18n', "languages/$slug.pot declares '$declared' but the plugin header says $canonical — run bin/make-pot.sh to resync." );
		}
	} else {
		$add( 'warning', 'i18n', "languages/$slug.pot has no Project-Id-Version header, so its version cannot be checked." );
	}

	if ( ! preg_match( '/^"X-Domain:\s*' . preg_quote( $slug, '/' ) . '\\\\n"/m', $pot_head ) ) {
		$add( 'error', 'i18n', "languages/$slug.pot was generated for a different text domain — regenerate it with bin/make-pot.sh." );
	}
}

// The header that tells WordPress where the catalogues live. Without it the
// default is /languages anyway, but a plugin that ships a template and does
// not say so reads as an oversight.
if ( '' !== $main_src && ! preg_match( '/^\s*\*\s*Domain Path:\s*\S/mi', $main_src ) ) {
	$add( 'warning', 'i18n', 'No "Domain Path:" header — declare it alongside Text Domain.' );
}

// ── Check 3: readme.txt structure ────────────────────────────────────────────
if ( '' !== $readme_src ) {
	foreach ( [ 'Contributors', 'Tags', 'Requires at least', 'Tested up to', 'Requires PHP', 'License' ] as $header ) {
		if ( ! preg_match( '/^' . preg_quote( $header, '/' ) . ':\s*\S/mi', $readme_src ) ) {
			$add( 'error', 'readme', "readme.txt has no \"$header:\" header." );
		}
	}

	// WordPress.org indexes at most five tags and silently drops the rest.
	if ( preg_match( '/^Tags:\s*(.+)$/mi', $readme_src, $m ) ) {
		$tags = array_filter( array_map( 'trim', explode( ',', $m[1] ) ) );
		if ( count( $tags ) > 5 ) {
			$add( 'warning', 'readme', 'readme.txt declares ' . count( $tags ) . ' tags; WordPress.org only uses the first five.' );
		}
	}

	foreach ( [ 'Description', 'Installation', 'Changelog' ] as $section ) {
		if ( ! preg_match( '/^==\s*' . preg_quote( $section, '/' ) . '\s*==/mi', $readme_src ) ) {
			$add( 'warning', 'readme', "readme.txt has no \"== $section ==\" section." );
		}
	}

	// The short description is the line after the header block, and
	// WordPress.org truncates it at 150 characters.
	if ( preg_match( '/^License URI:.*\R+(.+)$/mi', $readme_src, $m ) ) {
		$short = trim( $m[1] );
		if ( strlen( $short ) > 150 ) {
			$add( 'warning', 'readme', 'The short description is ' . strlen( $short ) . ' characters; WordPress.org truncates at 150.' );
		}
	}
}

// ── Check 4: screenshot captions match the screenshot files ──────────────────
// WordPress.org pairs `screenshot-N.png` in the SVN assets directory with the
// Nth line of the readme's Screenshots section. A mismatch does not error
// anywhere — the captions simply attach to the wrong images, or vanish.
if ( '' !== $readme_src ) {
	$captions = [];
	if ( preg_match( '/^== Screenshots ==\s*(.*?)(?=^== )/ms', $readme_src, $m ) ) {
		if ( preg_match_all( '/^\s*(\d+)\.\s+\S/m', $m[1], $cm ) ) {
			$captions = array_map( 'intval', $cm[1] );
		}
	}

	$files = [];
	foreach ( glob( $root . '/.wordpress-org/screenshot-*.{png,jpg,gif}', GLOB_BRACE ) ?: [] as $shot ) {
		if ( preg_match( '/screenshot-(\d+)\./', basename( $shot ), $fm ) ) {
			$files[] = (int) $fm[1];
		}
	}
	sort( $files );

	if ( $captions ) {
		// Numbering must start at 1 and not skip — WordPress.org stops at the
		// first gap, so a missing 3 hides 4 onwards.
		$expected = range( 1, count( $captions ) );
		if ( $captions !== $expected ) {
			$add( 'error', 'screenshots', 'Screenshot captions are numbered ' . implode( ',', $captions ) . ' — they must run 1..' . count( $captions ) . ' with no gaps.' );
		}

		if ( ! $files ) {
			$add( 'warning', 'screenshots', count( $captions ) . ' screenshot caption(s) written, but no screenshot-N files in .wordpress-org/ yet.' );
		} elseif ( $files !== $expected ) {
			$add( 'error', 'screenshots', 'Screenshot files are numbered ' . implode( ',', $files ) . ' but there are ' . count( $captions ) . ' caption(s) — every caption needs a matching file and vice versa.' );
		}
	} elseif ( $files ) {
		$add( 'error', 'screenshots', count( $files ) . ' screenshot file(s) present with no == Screenshots == captions in readme.txt — they would appear unlabelled.' );
	}
}

// ── Check 5: module registry ↔ filesystem ↔ labels ───────────────────────────
// boot() requires each active module's bootstrap only `if file_exists(...)`,
// with no else branch: a renamed or unshipped module silently disables its
// whole feature set rather than failing.
if ( null !== $main_file ) {
	$registered = [];
	if ( preg_match( '/function get_registered_modules\(\).*?\n\}/s', $main_src, $m ) ) {
		if ( preg_match_all( "/'([a-z0-9-]+)'\s*=>\s*\[\s*\n\s*'bootstrap'\s*=>\s*PLUGIN_DIR\s*\.\s*'([^']+)'/", $m[0], $rm, PREG_SET_ORDER ) ) {
			foreach ( $rm as $hit ) {
				$registered[ $hit[1] ] = ltrim( $hit[2], '/' );
			}
		}
	}

	if ( ! $registered ) {
		$add( 'error', 'modules', 'Could not parse get_registered_modules() — the module checks below did not run.' );
	}

	$labels = [];
	if ( preg_match( '/function get_module_labels\(\).*?\n\}/s', $main_src, $m ) ) {
		if ( preg_match_all( "/'([a-z0-9-]+)'\s*=>\s*__\(/", $m[0], $lm ) ) {
			$labels = $lm[1];
		}
	}

	foreach ( $registered as $module => $bootstrap ) {
		if ( ! is_file( $root . '/' . $bootstrap ) ) {
			$add( 'error', 'modules', "module '$module' points at $bootstrap, which does not exist — boot() would skip it silently." );
		}
		if ( $labels && ! in_array( $module, $labels, true ) ) {
			$add( 'warning', 'modules', "module '$module' has no entry in get_module_labels() — the admin UI would show no name for it." );
		}
	}

	foreach ( glob( $root . '/modules/*', GLOB_ONLYDIR ) ?: [] as $dir ) {
		$module = basename( $dir );
		if ( $registered && ! array_key_exists( $module, $registered ) ) {
			$add( 'warning', 'modules', "modules/$module/ exists but is not in get_registered_modules() — it never loads." );
		}
	}
}

// ── Check 6: block.json integrity ────────────────────────────────────────────
$block_prefix = 'producerkit/';
foreach ( glob( $root . '/blocks/*/block.json' ) ?: [] as $block_json ) {
	$dir   = dirname( $block_json );
	$name  = basename( $dir );
	$rel   = 'blocks/' . $name;
	$block = json_decode( (string) file_get_contents( $block_json ), true );

	if ( ! is_array( $block ) ) {
		$add( 'error', 'blocks', "$rel/block.json is not valid JSON: " . json_last_error_msg() );
		continue;
	}

	if ( ! isset( $block['name'] ) ) {
		$add( 'error', 'blocks', "$rel/block.json has no \"name\"." );
	} elseif ( ! str_starts_with( $block['name'], $block_prefix ) ) {
		$add( 'error', 'blocks', "$rel/block.json name '{$block['name']}' does not use the '$block_prefix' namespace." );
	}

	if ( ( $block['textdomain'] ?? null ) !== $slug ) {
		$add( 'error', 'blocks', "$rel/block.json textdomain is '" . ( $block['textdomain'] ?? '(none)' ) . "' but the plugin slug is '$slug' — its strings would not be translated." );
	}

	// Every `file:./x` reference must resolve, or the block registers with a
	// missing asset and fails at render time rather than at registration.
	foreach ( [ 'editorScript', 'script', 'viewScript', 'viewScriptModule', 'style', 'editorStyle', 'render' ] as $key ) {
		foreach ( (array) ( $block[ $key ] ?? [] ) as $value ) {
			if ( is_string( $value ) && str_starts_with( $value, 'file:' ) ) {
				$target = $dir . '/' . ltrim( substr( $value, strlen( 'file:' ) ), './' );
				if ( ! is_file( $target ) ) {
					$add( 'error', 'blocks', "$rel/block.json declares $key => '$value', which does not exist." );
				}
			}
		}
	}

	// A render.php with no `render` key is dead weight; core never calls it.
	if ( is_file( "$dir/render.php" ) && ! isset( $block['render'] ) ) {
		$add( 'warning', 'blocks', "$rel/render.php exists but block.json declares no \"render\" — the block would render nothing on the front end." );
	}
}

// ── Check 7: ability names and categories ────────────────────────────────────
// Distinct paths rather than register_rest_route() calls. /availability is
// registered twice — once for GET, once for POST — and counting registrations
// would make the documented number move when a method is added to a route that
// already existed, which reads as drift when nothing was added. A reader of
// the API section is counting addresses, not handlers.
$rest_routes = [];
foreach ( glob( $root . '/modules/*/includes/*.php' ) ?: [] as $rest_file ) {
	if ( preg_match_all( "/register_rest_route\(\s*[^,]+,\s*'([^']+)'/", (string) file_get_contents( $rest_file ), $rm ) ) {
		$rest_routes = array_merge( $rest_routes, $rm[1] );
	}
}
$rest_route_count = count( array_unique( $rest_routes ) );

$ability_names = [];
$ability_cats  = [];
$declared_cats = [];

foreach ( $php_files_in( 'modules' ) as $path ) {
	$src = (string) file_get_contents( $path );
	$rel = str_replace( $root . '/', '', $path );

	if ( preg_match_all( "/wp_register_ability\(\s*\n?\s*'([^']+)'/", $src, $m ) ) {
		foreach ( $m[1] as $name ) {
			if ( isset( $ability_names[ $name ] ) ) {
				$add( 'error', 'abilities', "ability '$name' is registered twice ({$ability_names[$name]} and $rel) — the second registration is rejected." );
			} else {
				$ability_names[ $name ] = $rel;
			}

			if ( ! str_starts_with( $name, $slug . '/' ) ) {
				$add( 'warning', 'abilities', "$rel: ability '$name' is not namespaced with the plugin slug '$slug'." );
			}
		}
	}

	if ( preg_match_all( "/wp_register_ability_category\(\s*\n?\s*'([^']+)'/", $src, $m ) ) {
		foreach ( $m[1] as $cat ) {
			$declared_cats[ $cat ] = $rel;
		}
	}

	if ( preg_match_all( "/'category'\s*=>\s*'([a-z0-9-]+)'/", $src, $m ) ) {
		foreach ( $m[1] as $cat ) {
			$ability_cats[ $cat ][] = $rel;
		}
	}
}

foreach ( $ability_cats as $cat => $where ) {
	if ( ! isset( $declared_cats[ $cat ] ) ) {
		$add( 'error', 'abilities', "category '$cat' is claimed in " . implode( ', ', array_unique( $where ) ) . ' but never passed to wp_register_ability_category() — those abilities will not register.' );
	}
}

foreach ( array_keys( $declared_cats ) as $cat ) {
	if ( ! isset( $ability_cats[ $cat ] ) ) {
		$add( 'warning', 'abilities', "category '$cat' is registered but no ability uses it." );
	}
}

// ── Check 8: every REST route declares a permission_callback ─────────────────
// A route without one is both a WordPress.org review failure and a real
// access-control hole; WordPress only emits a _doing_it_wrong notice.
foreach ( $php_files_in( 'modules' ) as $path ) {
	$src = (string) file_get_contents( $path );
	$rel = str_replace( $root . '/', '', $path );

	if ( ! preg_match_all( '/register_rest_route\(/', $src, $m, PREG_OFFSET_CAPTURE ) ) {
		continue;
	}

	foreach ( $m[0] as $hit ) {
		$start = $hit[1];
		$line  = substr_count( substr( $src, 0, $start ), "\n" ) + 1;

		// Walk to the matching close paren so nested arrays are covered.
		$depth = 0;
		$end   = $start;
		for ( $i = $start; $i < strlen( $src ); $i++ ) {
			if ( '(' === $src[ $i ] ) {
				$depth++;
			} elseif ( ')' === $src[ $i ] ) {
				$depth--;
				if ( 0 === $depth ) {
					$end = $i;
					break;
				}
			}
		}

		$call    = substr( $src, $start, $end - $start + 1 );
		$methods = preg_match_all( "/'methods'\s*=>/", $call );
		$perms   = preg_match_all( "/'permission_callback'\s*=>/", $call );

		if ( $perms < $methods ) {
			$add( 'error', 'rest', "$rel:$line — register_rest_route() declares $methods method handler(s) but only $perms permission_callback(s)." );
		}
	}
}

// ── Check 9 (heuristic): interactivity references resolve ────────────────────
foreach ( glob( $root . '/blocks/*/render.php' ) ?: [] as $render ) {
	$dir  = dirname( $render );
	$name = basename( $dir );
	$src  = (string) file_get_contents( $render );

	if ( ! preg_match_all( '/data-wp-[a-z-]*(?:--[a-zA-Z-]+)?="(?:!?)((?:actions|callbacks)\.[a-zA-Z_]\w*)"/', $src, $m ) ) {
		continue;
	}

	$view = "$dir/view.js";
	if ( ! is_file( $view ) ) {
		$add( 'error', 'interactivity', "blocks/$name/render.php references " . implode( ', ', array_unique( $m[1] ) ) . ' but the block has no view.js.' );
		continue;
	}

	// A block's view.js may be a shim that imports the shared store instead of
	// defining anything itself, so follow relative imports one level and scan
	// what they pull in too. Without this the check reports every method in a
	// consolidated store as undefined — which is exactly what it did the first
	// time the stores were merged.
	$sources = [ $view ];
	$queue   = [ $view ];
	$seen    = [ realpath( $view ) => true ];

	while ( $queue ) {
		$current = array_shift( $queue );
		$text    = (string) file_get_contents( $current );

		if ( ! preg_match_all( '/^\s*import\s+(?:[^\'"]*\bfrom\s+)?[\'"](\.[^\'"]+)[\'"]/m', $text, $im ) ) {
			continue;
		}

		foreach ( $im[1] as $spec ) {
			$path = realpath( dirname( $current ) . '/' . $spec );
			if ( false === $path || isset( $seen[ $path ] ) || ! str_starts_with( $path, $root ) ) {
				continue;
			}
			$seen[ $path ] = true;
			$sources[]     = $path;
			$queue[]       = $path;
		}
	}

	$js      = implode( "\n", array_map( static fn ( string $f ): string => (string) file_get_contents( $f ), $sources ) );
	$defined = [];

	// Method shorthand `name( args ) {` and generator `*name() {`.
	//
	// The argument class excludes newlines deliberately. With a plain [^)]*
	// the opening `store( 'ns', {` line matches, and the match then runs on
	// to the first `)` anywhere below — swallowing the first real method in
	// the store and reporting it as undefined.
	if ( preg_match_all( '/^[ \t]*\*?[ \t]*([a-zA-Z_]\w*)\s*\([^)\n]*\)\s*\{/m', $js, $dm ) ) {
		$defined = array_merge( $defined, $dm[1] );
	}
	// Property form `name: function`, `name: async`, `name: (`, `name: wrapper(`.
	if ( preg_match_all( '/([a-zA-Z_]\w*)\s*:\s*(?:async\s+)?(?:function\b|[a-zA-Z_$][\w$]*\s*\(|\()/', $js, $dm2 ) ) {
		$defined = array_merge( $defined, $dm2[1] );
	}

	// Control-flow keywords look exactly like method shorthand to the above.
	$defined = array_flip( array_diff( $defined, [ 'if', 'for', 'while', 'switch', 'catch', 'function', 'return' ] ) );

	foreach ( array_unique( $m[1] ) as $ref ) {
		$method = substr( $ref, strpos( $ref, '.' ) + 1 );
		if ( ! isset( $defined[ $method ] ) ) {
			$add( 'warning', 'interactivity', "blocks/$name/render.php references $ref but view.js defines no matching method." );
		}
	}
}

// ── Check 10: no type syntax newer than the declared "Requires PHP" ──────────
// This exists because a developer machine is rarely running the minimum PHP.
// `true` as a type is 8.2+, and on 8.1 it is not a type error but a *parse*
// error, so the whole file dies at include time — the plugin simply does not
// run on the version its own header promises. PHPCompatibility does not flag
// it, and every PHP newer than 8.1 accepts it silently.
$requires_php = null;
if ( null !== $main_file && preg_match( '/^[ \t]*\*?[ \t]*Requires PHP:[ \t]*(\S+)/mi', $main_head, $rp ) ) {
	$requires_php = $rp[1];
}

if ( null !== $requires_php && version_compare( $requires_php, '8.2', '<' ) ) {
	$scan = array_merge(
		$php_files_in( 'modules' ),
		$php_files_in( 'includes' ),
		$php_files_in( 'blocks' ),
		null !== $main_file ? [ $main_file ] : []
	);

	foreach ( $scan as $path ) {
		$rel    = str_replace( $root . '/', '', $path );
		$tokens = @token_get_all( (string) file_get_contents( $path ) );
		if ( ! is_array( $tokens ) ) {
			continue;
		}

		$count = count( $tokens );
		for ( $i = 0; $i < $count; $i++ ) {
			if ( ! is_array( $tokens[ $i ] ) || T_FUNCTION !== $tokens[ $i ][0] ) {
				continue;
			}

			// Walk the parameter list to its closing paren, then read the
			// return type (if any) up to the body or the statement end.
			$depth = 0;
			$j     = $i;
			for ( ; $j < $count; $j++ ) {
				$t = $tokens[ $j ];
				if ( '(' === $t ) {
					$depth++;
				} elseif ( ')' === $t ) {
					$depth--;
					if ( 0 === $depth ) {
						break;
					}
				}
			}

			$type = '';
			$line = is_array( $tokens[ $i ] ) ? $tokens[ $i ][2] : 0;
			for ( $k = $j + 1; $k < $count; $k++ ) {
				$t = $tokens[ $k ];
				if ( '{' === $t || ';' === $t ) {
					break;
				}
				$type .= is_array( $t ) ? $t[1] : $t;
			}

			$type = trim( ltrim( trim( $type ), ':' ) );
			if ( '' === $type ) {
				continue;
			}

			$parts = array_map( 'trim', explode( '|', $type ) );
			$lower = array_map( 'strtolower', $parts );

			// `true` needs 8.2 anywhere it appears. Standalone `false`/`null`
			// need 8.2 too; inside a union both have been legal since 8.0.
			$offenders = [];
			if ( in_array( 'true', $lower, true ) ) {
				$offenders[] = 'true';
			}
			if ( 1 === count( $lower ) && in_array( $lower[0], [ 'false', 'null' ], true ) ) {
				$offenders[] = $lower[0];
			}

			foreach ( array_unique( $offenders ) as $offender ) {
				$add( 'error', 'php-version', "$rel:$line declares the return type '$type', but '$offender' as a type requires PHP 8.2 and the plugin header says Requires PHP: $requires_php — on $requires_php this is a parse error, not a type error." );
			}
		}
	}
}

// ── Check 11: no DEFAULT on a BLOB/TEXT/JSON/GEOMETRY column ────────────────
// MySQL forbids it. A non-strict server drops the default with a warning and
// dbDelta then retries the impossible ALTER on every run; a server with
// STRICT_TRANS_TABLES — the default since MySQL 5.7 — rejects the CREATE
// outright, so the table is never created and every feature reading it fails.
// Cheap to get wrong, invisible on a permissive dev database.
foreach ( $php_files_in( 'modules' ) as $path ) {
	$src = (string) file_get_contents( $path );
	$rel = str_replace( $root . '/', '', $path );

	if ( ! str_contains( $src, 'CREATE TABLE' ) ) {
		continue;
	}

	foreach ( explode( "\n", $src ) as $n => $line ) {
		if ( ! preg_match( '/^\s*(\w+)\s+(TINYTEXT|TEXT|MEDIUMTEXT|LONGTEXT|TINYBLOB|BLOB|MEDIUMBLOB|LONGBLOB|JSON|GEOMETRY)\b.*\bDEFAULT\b/i', $line, $m ) ) {
			continue;
		}

		// `DEFAULT NULL` is the one form MySQL accepts on these types.
		if ( preg_match( '/\bDEFAULT\s+NULL\b/i', $line ) ) {
			continue;
		}

		$add( 'error', 'schema', "$rel:" . ( $n + 1 ) . " declares column '{$m[1]}' as {$m[2]} with a DEFAULT — MySQL forbids defaults on those types, and under STRICT_TRANS_TABLES the CREATE TABLE fails outright." );
	}
}

// ── Check 12: documented counts match reality ────────────────────────────────
// Every one of these had already drifted. README claimed 10 abilities when
// there were 14 and 16 REST endpoints when there were 46; readme.txt claimed
// ten blocks the day an eleventh shipped. Prose does not fail a test suite, so
// it silently stops being true unless something counts it.
//
// Claims are named explicitly rather than found by pattern. A general "<number>
// blocks" search cannot tell a claim about the whole plugin from a true
// statement about one module's three blocks — and a missing claim matters as
// much as a wrong one, so a pattern that no longer matches is itself reported.
$number_words = [
	1  => 'one',
	2  => 'two',
	3  => 'three',
	4  => 'four',
	5  => 'five',
	6  => 'six',
	7  => 'seven',
	8  => 'eight',
	9  => 'nine',
	10 => 'ten',
	11 => 'eleven',
	12 => 'twelve',
	13 => 'thirteen',
	14 => 'fourteen',
	15 => 'fifteen',
	16 => 'sixteen',
	17 => 'seventeen',
	18 => 'eighteen',
	19 => 'nineteen',
	20 => 'twenty',
];

$ability_names = [];
foreach ( glob( $root . '/modules/*/includes/abilities.php' ) ?: [] as $abilities_file ) {
	if ( preg_match_all( "/'producerkit\/[a-z0-9-]+'/", (string) file_get_contents( $abilities_file ), $am ) ) {
		$ability_names = array_merge( $ability_names, $am[0] );
	}
}

$documented_counts = [
	[
		'doc'     => 'readme.txt',
		'pattern' => '/^\* (\S+) blocks under a dedicated category/mi',
		'actual'  => count( glob( $root . '/blocks/*/block.json' ) ?: [] ),
		'label'   => 'blocks',
	],
	[
		'doc'     => 'readme.txt',
		'pattern' => '/on WordPress 6\.9\+, (\S+) operations/i',
		'actual'  => count( array_unique( $ability_names ) ),
		'label'   => 'abilities',
	],
	[
		'doc'     => 'readme.txt',
		'pattern' => '/\*\*(\S+) trades\*\*/i',
		'actual'  => count( glob( $root . '/modules/producer-profiles/profiles/*.php' ) ?: [] ),
		'label'   => 'producer profiles',
	],
	[
		'doc'     => 'README.md',
		'pattern' => '/(\S+) profiles ship/i',
		'actual'  => count( glob( $root . '/modules/producer-profiles/profiles/*.php' ) ?: [] ),
		'label'   => 'producer profiles',
	],
	[
		'doc'     => 'README.md',
		// Digits only, deliberately. \S+ would match whatever word happens to
		// precede "custom endpoints" if the number were deleted, reporting a
		// confusing mismatch instead of the missing claim — and the count is
		// past the number-word table anyway.
		'pattern' => '/(\d+) custom endpoints/i',
		'actual'  => $rest_route_count,
		'label'   => 'REST endpoints',
	],
];

foreach ( $documented_counts as $claim ) {
	$text = $read( $claim['doc'] );
	if ( '' === $text ) {
		continue;
	}

	if ( ! preg_match( $claim['pattern'], $text, $cm ) ) {
		$add(
			'warning',
			'doc-counts',
			"{$claim['doc']} no longer states how many {$claim['label']} there are — the sentence this check reads was reworded or removed."
		);
		continue;
	}

	$claimed = strtolower( trim( $cm[1] ) );
	$value   = ctype_digit( $claimed ) ? (int) $claimed : (int) array_search( $claimed, $number_words, true );

	if ( $value !== $claim['actual'] ) {
		$add(
			'error',
			'doc-counts',
			"{$claim['doc']} says \"{$cm[1]} {$claim['label']}\" but there are {$claim['actual']}."
		);
	}
}

// ── Report ───────────────────────────────────────────────────────────────────
$errors   = array_filter( $issues, static fn( $i ) => $i['level'] === 'error' );
$warnings = array_filter( $issues, static fn( $i ) => $i['level'] === 'warning' );

if ( $format === 'json' ) {
	echo json_encode(
		[
			'ok'       => count( $errors ) === 0,
			'errors'   => count( $errors ),
			'warnings' => count( $warnings ),
			'issues'   => array_values( $issues ),
		],
		JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
	) . "\n";
} else {
	$colors = [
		'error'   => "\033[31m",
		'warning' => "\033[33m",
	];
	$reset  = "\033[0m";
	$tty    = function_exists( 'posix_isatty' ) ? @posix_isatty( STDOUT ) : false;

	if ( empty( $issues ) ) {
		echo ( $tty ? "\033[32m" : '' ) . '✓ validation passed — no issues.' . ( $tty ? $reset : '' ) . "\n";
	} else {
		foreach ( $issues as $i ) {
			$tag = strtoupper( $i['level'] );
			$c   = $tty ? ( $colors[ $i['level'] ] ?? '' ) : '';
			$r   = $tty ? $reset : '';
			echo "{$c}[{$tag}]{$r} ({$i['check']}) {$i['message']}\n";
		}
		echo "\n" . count( $errors ) . ' error(s), ' . count( $warnings ) . " warning(s).\n";
	}
}

exit( count( $errors ) > 0 ? 1 : 0 );
