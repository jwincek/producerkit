#!/usr/bin/env bash
#
# Build the distributable plugin tree (and optionally a zip) from .distignore.
#
# This is what actually ships to WordPress.org, so every check that matters --
# Plugin Check especially -- should run against THIS output rather than the
# repo. The repo contains dev files that never reach users; checking it instead
# produces both false alarms and false confidence.
#
# Equivalent to `wp dist-archive`, without requiring that wp-cli package.
#
# Usage:
#   bin/build-dist.sh              # build tree into build/<slug>/
#   bin/build-dist.sh --zip        # also produce build/<slug>-<version>.zip
#
# Outputs the built tree at  build/<slug>/
# Prints the resolved version to stdout as the last line.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

# Identify the plugin by its "Plugin Name:" header rather than by the directory
# name. The checkout directory is NOT reliably the slug: CI clones into a folder
# named after the repository, so any basename-derived slug silently breaks the
# moment the repo and the plugin are named differently.
MAIN_FILE=""
for candidate in "$ROOT"/*.php; do
	[[ -f "$candidate" ]] || continue
	if head -c 8192 "$candidate" | grep -qE '^[[:space:]]*\*?[[:space:]]*Plugin Name:'; then
		MAIN_FILE="$candidate"
		break
	fi
done

if [[ -z "$MAIN_FILE" ]]; then
	echo "Error: no PHP file at the repo root carries a 'Plugin Name:' header." >&2
	exit 1
fi

SLUG="$(basename "$MAIN_FILE" .php)"
BUILD_ROOT="$ROOT/build"
DEST="$BUILD_ROOT/$SLUG"

MAKE_ZIP=0
for arg in "$@"; do
	case "$arg" in
		--zip) MAKE_ZIP=1 ;;
		*) echo "Unknown argument: $arg" >&2; exit 1 ;;
	esac
done

if [[ ! -f "$ROOT/.distignore" ]]; then
	echo "Error: no .distignore at repo root — refusing to guess what to ship." >&2
	exit 1
fi

# The plugin header is the single source of truth for the version
# (bin/validate-config.php enforces that everything else agrees).
VERSION="$(sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*\([^[:space:]]*\).*/\1/p' "$MAIN_FILE" | head -1)"
if [[ -z "$VERSION" ]]; then
	echo "Error: could not read the Version header from $(basename "$MAIN_FILE")." >&2
	exit 1
fi

# Translate .distignore into rsync excludes, skipping comments and blank lines.
EXCLUDES=()
while IFS= read -r line || [[ -n "$line" ]]; do
	line="${line%"${line##*[![:space:]]}"}"   # strip trailing whitespace
	[[ -z "$line" || "$line" == \#* ]] && continue
	EXCLUDES+=(--exclude="$line")
done < "$ROOT/.distignore"

rm -rf "$BUILD_ROOT"
mkdir -p "$DEST"
rsync -a "${EXCLUDES[@]}" "$ROOT/" "$DEST/"

# A dev file reaching the zip is a release bug, so fail loudly rather than
# trusting that .distignore stayed correct.
LEAKED=()
for f in .git .github bin vendor node_modules composer.json composer.lock \
         package.json package-lock.json phpcs.xml.dist .wp-env.json \
         .eslintrc.json .stylelintrc.json .distignore .gitignore \
         .wordpress-org assets-src build tests phpunit.xml.dist \
         .git-blame-ignore-revs \
         phpunit-integration.xml.dist .phpunit.result.cache \
         README.md CHANGELOG.md GETTING-STARTED.md docs; do
	[[ -e "$DEST/$f" ]] && LEAKED+=("$f")
done
# Any dotfile at all. The enumerated list above only catches the ones that
# existed when it was written: .gitattributes was added later, shipped, and
# Plugin Check failed on it as a hidden file. A WordPress plugin needs no
# dotfiles in its zip, so the rule is simply that there are none — which
# catches the next one too, whatever it turns out to be called.
while IFS= read -r dotfile; do
	LEAKED+=( "${dotfile#"$DEST/"}" )
done < <( find "$DEST" -name '.*' -not -name '.' -not -name '..' )

if (( ${#LEAKED[@]} )); then
	echo "Error: development files leaked into the build: ${LEAKED[*]}" >&2
	exit 1
fi

# Likewise, a missing runtime file would ship a broken plugin.
MISSING=()
for f in "$SLUG.php" readme.txt LICENSE includes modules blocks assets \
         "languages/$SLUG.pot"; do
	[[ -e "$DEST/$f" ]] || MISSING+=("$f")
done
if (( ${#MISSING[@]} )); then
	echo "Error: required files missing from the build: ${MISSING[*]}" >&2
	exit 1
fi

# Every asset the plugin hands to plugins_url() must be in the build.
#
# The check above is top-level only, which is how a missing library shipped: a
# bare `vendor` in .distignore also matched assets/js/vendor/, so the bundled
# QR library never reached the zip while `assets` itself was present and the
# guard passed. Nothing errored at runtime either — pkit-qr.js returns quietly
# when its global is absent — so QR codes simply never rendered, in every
# release up to 2.6.0 (#74).
#
# Registered assets are a contract: WordPress will emit a script or style tag
# for each one, and a 404 there is a broken feature with no error anywhere.
# BSD sed has no \s, so the path is pulled out with a capture group. Entries
# ending in / are a concatenated prefix (plugins_url( 'assets/js/' . $file ))
# and cannot be checked literally, so they are skipped.
ASSET_REFS=$(grep -rhoE "plugins_url\( *'[^']+'" "$ROOT/producerkit.php" "$ROOT/includes" "$ROOT/modules" 2>/dev/null \
	| sed -E "s/.*'([^']+)'.*/\1/" | grep -v '/$' | sort -u)

MISSING_ASSETS=()
while IFS= read -r asset; do
	[[ -z "$asset" ]] && continue
	[[ -e "$DEST/$asset" ]] || MISSING_ASSETS+=("$asset")
done <<< "$ASSET_REFS"

if (( ${#MISSING_ASSETS[@]} )); then
	echo "Error: registered assets missing from the build: ${MISSING_ASSETS[*]}" >&2
	echo "       (a .distignore rule is probably matching more than it means to)" >&2
	exit 1
fi

# Every module bootstrap the registry names must be present, or the plugin
# silently loads fewer features than it advertises: boot() skips any bootstrap
# whose file_exists() check fails, without warning.
for module_dir in "$ROOT"/modules/*/; do
	module="$(basename "$module_dir")"
	if [[ ! -f "$DEST/modules/$module/bootstrap.php" ]]; then
		echo "Error: modules/$module/bootstrap.php missing from the build." >&2
		exit 1
	fi
done

# Blocks are registered by scanning for block.json, so a block whose render.php
# or block.json got excluded would vanish from the editor with no error.
for block_dir in "$ROOT"/blocks/*/; do
	block="$(basename "$block_dir")"
	if [[ ! -f "$DEST/blocks/$block/block.json" ]]; then
		echo "Error: blocks/$block/block.json missing from the build." >&2
		exit 1
	fi
done

echo "Built $DEST ($(find "$DEST" -type f | wc -l | tr -d ' ') files)"

if (( MAKE_ZIP )); then
	ZIP="$BUILD_ROOT/$SLUG-$VERSION.zip"
	# Zip from BUILD_ROOT so the archive contains a single <slug>/ top-level
	# directory, which is what WordPress expects when installing from a zip.
	( cd "$BUILD_ROOT" && zip -rq "$ZIP" "$SLUG" -x '*.DS_Store' )
	echo "Packaged $ZIP"
fi

echo "$VERSION"
