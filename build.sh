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

mkdir -p dist leone-therapist-finder/data
rm -f dist/*.zip

# The tagging file the plugin's importer ships with: the demo data minus bios and photos.
node -e '
const d = require("./playground/data/therapists.json");
const fields = ["name", "slug", "role", "years", "accreditations", "locations", "calendars", "services", "issues", "languages"];
// menu_order carries the running order from the live team page.
const out = {
  generated: d.generated, source: d.source, note: d.note,
  locations: d.locations, services: d.services, issues: d.issues, languages: d.languages,
  // `summary` is the short description from the live Meet Our Team page, which reads better
  // than the opening of each profile bio.
  therapists: d.therapists.map((t) => ({ ...Object.fromEntries(fields.map((f) => [f, t[f]])), order: t.menu_order + 1, summary: t.excerpt })),
};
require("fs").writeFileSync("./leone-therapist-finder/data/leonecentre-team.json", JSON.stringify(out, null, 2) + "\n");
'

"$TAR" -a -cf "dist/leone-therapist-finder-$VERSION.zip" leone-therapist-finder
cp "dist/leone-therapist-finder-$VERSION.zip" dist/leone-therapist-finder.zip   # Stable name for the blueprint.
(cd playground && "$TAR" -a -cf ../dist/leone-demo-look.zip leone-demo-look)
(cd playground && "$TAR" -a -cf ../dist/leone-demo-content.zip seed.php data photos)

ls -l dist
