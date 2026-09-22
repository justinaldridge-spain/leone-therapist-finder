#!/usr/bin/env bash
# Rebuilds the zips in dist/. Run after changing the plugin or demo, then commit and push –
# the shareable Playground link (playground/blueprint-web.json) loads these zips from GitHub.
#
# Uses bsdtar (built into Windows 10+ as tar.exe, and the default tar on macOS) because it writes
# zip paths with forward slashes, which WordPress needs. GNU tar cannot write zips.
set -euo pipefail
cd "$(dirname "$0")"

TAR=tar
[ -x /c/Windows/System32/tar.exe ] && TAR=/c/Windows/System32/tar.exe

VERSION=$(sed -n 's/^ \* Version: *//p' leone-therapist-finder/leone-therapist-finder.php | tr -d '\r')

mkdir -p dist
rm -f dist/*.zip

"$TAR" -a -cf "dist/leone-therapist-finder-$VERSION.zip" leone-therapist-finder
cp "dist/leone-therapist-finder-$VERSION.zip" dist/leone-therapist-finder.zip   # Stable name for the blueprint.
(cd playground && "$TAR" -a -cf ../dist/leone-demo-look.zip leone-demo-look)
(cd playground && "$TAR" -a -cf ../dist/leone-demo-content.zip seed.php data photos)

ls -l dist
