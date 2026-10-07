#!/usr/bin/env bash
set -euo pipefail
root="$(cd "$(dirname "$0")/.." && pwd)"
slug=custom-hook-block
version="$(sed -n 's/^[[:space:]]*\* Version:[[:space:]]*//p' "$root/$slug.php" | head -n 1)"
test -n "$version"
stage="$(mktemp -d)"
trap 'rm -rf "$stage"' EXIT
mkdir -p "$root/dist" "$stage/$slug"
rsync -a --exclude-from="$root/.distignore" "$root/" "$stage/$slug/"
rm -f "$root/dist/$slug-$version.zip"
(cd "$stage" && zip -qr "$root/dist/$slug-$version.zip" "$slug")
printf '%s\n' "$root/dist/$slug-$version.zip"
