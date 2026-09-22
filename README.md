# Leone Centre – Therapist Finder (Phase 1 prototype)

A WordPress plugin that adds filtering to leonecentre.com's therapist directory, by location
(online or one of the clinics), issue, therapy type and language. Book buttons go straight to the
right Acuity calendar. It is added to a page with a shortcode.

```
leone-therapist-finder/   The plugin (install this on the site)
playground/               Local demo: blueprint, seed script, scraped data, photos
dist/                     Installable plugin zip + demo zips (rebuild with ./build.sh)
.claude/launch.json       Starts the Playground demo
```

## Run the demo

Requires Node 20.18+. From this folder:

```bash
npx @wp-playground/cli@latest server --port=9400 --login --define-bool LEONE_DEMO_AUTOLOGIN true --blueprint=./playground/blueprint.json --mount-dir ./leone-therapist-finder /wordpress/wp-content/plugins/leone-therapist-finder --mount-dir ./playground/leone-demo-look /wordpress/wp-content/plugins/leone-demo-look --mount-dir ./playground /demo
```

Then open http://127.0.0.1:9400 (use `127.0.0.1`, not `localhost`). Playground is disposable,
so each start builds a fresh site and seeds it again. Plugin files are mounted live, so edits show
up on refresh. `LEONE_DEMO_AUTOLOGIN` keeps you logged in as admin on this local demo only.

## Share with the client

This link builds the demo in the visitor's own browser. It needs no server and your computer
doesn't need to be on:

https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/justinaldridge-spain/leone-therapist-finder/main/playground/blueprint-web.json

It loads the zips in `dist/` from this GitHub repo. After changing the plugin or demo data,
run `./build.sh`, then commit and push. The same link then shows the update, although GitHub can
take about 5 minutes to serve the new files. Each visit starts a fresh sandbox, so nothing the
client does is saved.

| Page | Shortcode | Shows |
|---|---|---|
| `/` Find a Therapist | `[therapist_finder]` | All filters |
| `/couples-counselling/` | `[therapist_finder service="couples-counselling"]` | Fixed to couples; filter by location / issue / language |
| `/family-therapy/` | `[therapist_finder service="family-therapy"]` | 6 therapists |
| `/psychosexual-therapy/` | `[therapist_finder service="psychosexual-therapy"]` | 6 therapists |
| `/online-therapy/` | `[therapist_finder location="online"]` | Fixed to online |
| `/fulham/` | `[therapist_finder location="fulham" order="random"]` | Fixed to Fulham, shuffled each visit |

Admin (you are logged in): **Therapists** menu, which has the list with the "Finder check"
column, the edit screen with the "Therapist finder" box, **Finder settings** (shortcode builder
and data check), and the Locations / Therapy types / Issues / Languages screens.

## How it works

- **Data:** adds four admin-only taxonomies (Location, Therapy type, Issue, Language) to the
  existing therapist post type `associates`. Each therapist gets an Acuity calendar ID per
  location. If the post type does not exist, the plugin creates a compatible one.
- **Shortcode:** `service`, `location`, `issue` and `language` fix the results to those values
  and hide that filter. Listing a facet in `filters` makes it a starting value that visitors
  can change instead. See `leone-therapist-finder/readme.txt` for every attribute.
- **Rendering:** every card is rendered on the server, so the page is SEO-friendly and the
  filters still work without JavaScript. JavaScript then filters instantly, shows live counts on
  each option, keeps filters in the URL so results can be shared, and offers one-click ways out
  when nothing matches. On phones, the less-used filters fold behind "More filters".
- **Booking:** buttons deep-link to Acuity (`?calendarID=…`, plus `appointmentType=…` if one is
  set on the therapy type). Acuity still handles intake forms, payment and confirmation emails,
  and no API key is needed. When a location is selected, the button books that location
  directly.
- **Analytics:** pushes `therapist_finder_filter` and `therapist_finder_book` events to the GTM
  `dataLayer`.

Tested in Playground (PHP 8.3, WordPress latest, `WP_DEBUG` on, no notices); lints on PHP 7.4.

## Where the demo data came from

`playground/data/therapists.json` was scraped from the live team page and profiles on 2026-09-21:

- **Locations and calendar IDs:** from each therapist's "Book … Appointment" links, so they are
  exact. There are 31 calendars. Alison Wragg's Fulham link is a custom Acuity URL, which is
  supported.
- **Therapy types:** from each card's "can help with" links and the therapist's role title.
- **Issues:** matched by keyword from the bios. **Treat these as a first draft for the Leone
  team to review**, not a final list.

## To raise with the client

1. **Kensington has no therapists** on the site, so it is hidden from visitors. Is that
   intentional?
2. **All 19 therapists do couples work,** so the Couples page filter only narrows results once a
   visitor picks a location or issue. That reflects the data, but it's worth knowing before the demo.
3. **The issue list** (25 issues in 4 groups) needs agreeing, and each therapist's issue tags
   need checking by someone who knows them.
4. **Acuity appointment type IDs** (optional). These make "Book" on the Couples page open the
   couples appointment type directly. Add them under Therapists > Therapy types.
5. **Whether Acuity takes payment at booking.** It doesn't affect Phase 1, which uses Acuity's
   own pages, but it matters for Phase 2.

## Installing on the real site (staging first)

1. Upload `dist/leone-therapist-finder-0.1.0.zip` under Plugins > Add New > Upload.
2. Under Therapists > Finder settings, confirm the therapist post type.
3. Create the Locations, Therapy types and Issues (or import the demo's list).
4. Tag each therapist and paste their calendar IDs. The "Finder check" column shows what's missing.
5. Add shortcodes to pages. The Finder settings page has a shortcode builder.

Styles inherit the theme's fonts and use Leone's teal (`#2cb2b2`). Buttons use a darker shade
generated from it, so white text meets WCAG AA contrast. Templates can be overridden from the
theme (`leone-therapist-finder/finder.php`, `card.php`).

## Next (Phase 2)

Server-side Acuity API integration: "Next available" times on each card, a "this week" filter,
and slot buttons that open Acuity with the time pre-selected (`datetime=`). Availability would be
cached and cleared by Acuity webhooks. The Acuity plan must include API access.
