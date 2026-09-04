#!/usr/bin/env bash
#
# Regenerate languages/<slug>.pot from the source tree.
#
# The template is committed rather than built, for two reasons: a translator
# working from the repository can start without a WordPress checkout and a
# wp-cli install, and a diff shows exactly which strings a change added or
# removed — which is the only cheap way to notice that a new control shipped
# hard-coded.
#
# Run this whenever user-visible strings change, and after bin/bump-version.php,
# because the version is embedded in the template's Project-Id-Version header.
# bin/validate-config.php fails if that header falls out of step.
#
# Usage:
#   bin/make-pot.sh           # regenerate
#   bin/make-pot.sh --check   # exit 1 if the committed file is out of date

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

CHECK=0
[[ "${1:-}" == "--check" ]] && CHECK=1

if ! command -v wp >/dev/null 2>&1; then
	echo "Error: wp-cli is not installed. See https://wp-cli.org/#installing" >&2
	exit 1
fi

# Same rule as bin/build-dist.sh: identify the plugin by its header, never by
# the directory name, because CI clones into a folder named after the repo.
MAIN_FILE=""
for f in "$ROOT"/*.php; do
	if grep -q '^[[:space:]]*\*[[:space:]]*Plugin Name:' "$f" 2>/dev/null; then
		MAIN_FILE="$f"
		break
	fi
done

if [[ -z "$MAIN_FILE" ]]; then
	echo "Error: no PHP file at the repo root carries a 'Plugin Name:' header." >&2
	exit 1
fi

SLUG="$(basename "$MAIN_FILE" .php)"
POT="$ROOT/languages/$SLUG.pot"

mkdir -p "$ROOT/languages"

# build/ holds a copy of the whole plugin, so without excluding it every string
# is extracted twice and every entry carries a duplicate source reference.
# The rest never reach a user.
EXCLUDES="build,vendor,node_modules,tests,bin,.github,assets-src,.wordpress-org"

TARGET="$POT"
if (( CHECK )); then
	TARGET="$(mktemp -t producerkit-pot.XXXXXX)"
	trap 'rm -f "$TARGET"' EXIT
fi

# --skip-themes keeps other plugins on the machine from being loaded and
# emitting deprecation notices into the output; on a bare checkout with no
# WordPress around it is simply a no-op.
#
# Output is captured rather than discarded: on a runner there is nothing else
# to look at, and a swallowed error would leave a red job with no reason.
if ! MAKE_POT_OUTPUT="$( wp i18n make-pot "$ROOT" "$TARGET" \
	--slug="$SLUG" \
	--exclude="$EXCLUDES" \
	--skip-themes 2>&1 )"; then
	echo "Error: wp i18n make-pot failed." >&2
	echo "$MAKE_POT_OUTPUT" >&2
	exit 1
fi

if (( ! CHECK )); then
	echo "Wrote $POT ($(grep -c '^msgid ' "$POT") strings)"
	exit 0
fi

if [[ ! -f "$POT" ]]; then
	echo "Error: $POT does not exist. Run bin/make-pot.sh." >&2
	exit 1
fi

# Compare the entries, not the file. Three headers churn on every run for
# reasons that have nothing to do with the strings: POT-Creation-Date is the
# clock, X-Generator is whichever wp-cli produced it, and Project-Id-Version
# carries the plugin version. A byte comparison would fail on all three and
# teach everyone to ignore it.
strip_headers() {
	sed '/^"POT-Creation-Date:/d; /^"X-Generator:/d; /^"Project-Id-Version:/d; /^# Copyright (C)/d' "$1"
}

if diff -q <( strip_headers "$POT" ) <( strip_headers "$TARGET" ) >/dev/null; then
	echo "languages/$SLUG.pot is up to date."
	exit 0
fi

# Distinguish the two reasons it can differ. A string appearing or vanishing
# is translation work; source references moving because a line shifted is a
# one-command fix that loses nothing, and saying so keeps the check from
# reading as noise the first time an unrelated edit trips it.
STRINGS="$( diff <( strip_headers "$POT" ) <( strip_headers "$TARGET" ) | grep -E '^[<>] msgid ' || true )"

if [[ -n "$STRINGS" ]]; then
	echo "Error: languages/$SLUG.pot is out of date — strings have changed." >&2
	echo "Run bin/make-pot.sh and commit the result." >&2
	echo >&2
	echo "$STRINGS" | head -40 >&2
else
	echo "Error: languages/$SLUG.pot is out of date — source references have moved." >&2
	echo "No strings changed, so this is just line numbers. Run bin/make-pot.sh and commit." >&2
fi

exit 1
