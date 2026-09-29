=== Leone Therapist Finder ===
Requires at least: 6.3
Requires PHP: 7.4
Stable tag: 0.4.0
License: GPLv2 or later

Filterable therapist directory for the Leone Centre, with Acuity Scheduling booking links.

== Description ==

Adds Location, Therapy type, Issue and Language tags to the existing therapist post type
(`associates`), a per-location Acuity calendar field on each therapist, and a
`[therapist_finder]` shortcode that lets visitors filter therapists instantly.

* Works with the site's existing therapist profiles; creates the post type only if missing.
* Booking buttons deep-link to Acuity (calendar + optional appointment type), so intake forms,
  payments and confirmation emails are unchanged. No API keys needed.
* Cards are rendered on the server (SEO-friendly, works without JavaScript); filtering,
  live counts and shareable URLs are added in the browser.
* Admin "Finder check" column and settings-page data check flag missing booking links,
  locations, therapy types, issues and photos.
* Therapists > Order sets the order therapists appear in, by dragging (or typing positions).
  The order is kept inside filtered results. It works even if the site's therapist post type has
  no Order field of its own, and `order="name"` or `order="random"` can override it per page.
* Therapists > Import tags fills in tagging from a data file, matching existing profiles by their
  URL slug. It previews before changing anything, skips therapists that are already tagged unless
  told otherwise, never touches bios, photos or page content, and can be undone.
* Pushes `therapist_finder_filter` and `therapist_finder_book` events to the GTM dataLayer.

== Shortcode ==

    [therapist_finder]

Attributes:

* `service`, `location`, `issue`, `language` – term slugs (comma-separated). Results are fixed
  to them and that filter is hidden – unless the facet is explicitly listed in `filters`, in which
  case the value is just the starting selection and visitors can change it.
* `filters` – controls to show. Default: `location,issue,service,language,search`, minus any
  facet fixed above.
* `order` – `default` (the order set under Therapists > Order), `name`, or `random` (shuffled in
  the browser on each visit, which spreads enquiries across the team).
* `width` – by default the finder fits the page's content column. `wide` lets it step outside a
  narrow column (up to 1200px), `full` uses the whole screen width, or give a number of pixels
  e.g. `width="1000"`. It is centred on the screen and never causes sideways scrolling.
* `title` – optional heading. `help="no"` hides the help box. `url="no"` stops syncing filters to the URL.

Examples:

    Couples Counselling page:  [therapist_finder service="couples-counselling"]
    Family Therapy page:       [therapist_finder service="family-therapy" filters="location,issue,search"]
    Online Therapy page:       [therapist_finder location="online"]
    Start on Fulham, changeable: [therapist_finder location="fulham" filters="location,issue,service,search"]

A shortcode builder is under Therapists > Finder settings.

== Fitting into a theme ==

Styles are scoped to `.ltf` and inherit the theme's fonts. The plugin also neutralises the
content styling themes commonly apply to lists, labels and headings (borders, backgrounds,
columns, uppercase), and deliberately avoids `<header>`, `<footer>` and `<aside>` elements,
which themes often style or script as the site header and footer.

If the page template is narrow, use the `width` attribute rather than a wide template.

== Developer hooks ==

* `ltf_post_type` – therapist post type.
* `ltf_query_args`, `ltf_therapists`, `ltf_therapist_data` – adjust the query and card data.
* `ltf_therapist_photo_html` – e.g. use an ACF image field instead of the featured image.
* `ltf_booking_url` – adjust Acuity links. `ltf_is_online_location` – which locations are online.
* `ltf_admin_checks` – add or remove data-check rules.
* Templates can be overridden in `yourtheme/leone-therapist-finder/finder.php` and `card.php`.
* JS events on the finder element: `ltf:filter`, `ltf:book`.

== Uninstall ==

Deleting the plugin removes its settings. Therapist tags and calendar IDs are kept unless
`LTF_REMOVE_ALL_DATA` is defined as true in wp-config.php.
