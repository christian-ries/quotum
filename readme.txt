=== Quotum ===
Contributors: christian-ries
Tags: quotations, quotes, shortcode, widget, csv
Requires at least: 6.2
Requires PHP: 8.0
Tested up to: 7.1
Stable tag: 0.7.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A lightweight library for managing and displaying quotations in WordPress.

== Description ==

Quotum provides a simple way to store, manage and display quotations without requiring a separate custom post type or complex author taxonomy.

Each quotation contains:

* Quote
* Author
* Source
* Tags
* Active/inactive status

Quotations can be displayed with a shortcode or the Quotum widget.

== Features ==

* Add, edit and delete quotations.
* Activate or deactivate quotations.
* Search quotations, authors, sources and tags.
* Filter by tag and active status.
* Sort the quotation list by Quote, Author, Source, Tags or Status.
* Bulk activate, deactivate or permanently delete quotations.
* Manage tags, including rename and remove.
* Normalize tags when quotations are saved or imported.
* Export all quotations to UTF-8 CSV.
* Import quotations from a Quotum CSV export.
* Detect duplicates using Quote + Author.
* Imported quotations are inactive by default; optionally keep their CSV status or import all as active.
* Display one or several random active quotations with a shortcode.
* Display one or several random active quotations with a widget.
* Filter shortcode quotations by tag.
* Optional author, source and vertical-line display.
* Responsive public quotation display.
* Optional Next Quote link for widgets and shortcodes, without reloading the page.

== Installation ==

1. Upload the `quotum` folder to `/wp-content/plugins/` or install the ZIP from the WordPress Plugins screen.
2. Activate **Quotum** under **Plugins**.
3. Open **Quotum** in the WordPress administration area.
4. Add quotations or import them from a CSV file.

The plugin creates its own database table when activated. Existing quotations are preserved when upgrading or deactivating the plugin.

Deleting the plugin does not automatically delete its quotations table. Back up your database before removing quotation data manually.

== Try the Sample Quotations ==

On a fresh installation, the administrator can choose **Import sample quotations** or **No thanks** in the welcome notice. The notice is optional and will not reappear after either choice. The **Export / Import** page always offers a button to import the sample quotations later. Duplicate quotations are skipped. Sample quotations are always imported as inactive and must be activated before they can appear in a widget or shortcode.


An optional CSV file with three sample quotations by Victor Hugo, Dag Hammarskjöld and Franz Kafka is included at `examples/sample-quotes.csv`.

To try the plugin:

1. Go to **Quotum > Export / Import** in WordPress administration.
2. Select `examples/sample-quotes.csv` from the downloaded plugin package.
3. Choose **Import all as Inactive** (the default), then import.
4. In **Quotum**, open the quotation list, select the imported examples, choose **Activate**, and click **Apply**.
5. Add `[quotum count="3"]` to a page to display active quotations.

The examples are not inserted automatically on activation. Importing is additive, and existing quotations are not modified. Quotations with the same Quote + Author are skipped as duplicates.

== Shortcode ==

The basic shortcode is:

`[quotum]`

By default it displays one random active quotation.

Display several quotations:

`[quotum count="3"]`

Show or hide the author, source and optional vertical line:

`[quotum author="yes" source="yes" line="yes"]`

Filter by tag:

`[quotum tags="philo"]`

Combine options:

`[quotum count="3" tags="philo" author="yes" source="yes" line="yes"]`

When several tags are supplied, quotations matching at least one of the supplied tags may be selected.

The `count` value is limited to 20 quotations per shortcode.

The optional refresh link is enabled with `[quotum refresh="yes" refresh_text="Next quote »"]`.
The link loads another active quotation without reloading the page. It is disabled by default.

== Widget ==

Add the **Quotum** widget through the WordPress Widgets interface.

The widget supports:

* Title
* Number of quotations (1–20)
* Show author
* Show source
* Show vertical line
* Optional Next Quote link and customizable link text

The widget displays random active quotations.

== Widgets, Polylang and Custom Sidebars ==

Quotum widgets should be created as new widget instances from the **Available Widgets** section of the WordPress Widgets interface.

If you use the **Custom Sidebars** plugin, avoid using its **Clone** function to create additional Quotum widgets. Cloned widget instances may retain or share settings in unexpected ways, especially when combined with language-specific widget visibility.

For multilingual sites using Polylang, create a separate Quotum widget instance for each language and assign the appropriate language through the widget visibility settings.

== Import and Export ==

The Export / Import page is available under **Quotum → Export / Import**.

The CSV format is:

`Quote, Author, Source, Tags, Active`

Importing is additive. Existing quotations are not deleted or modified.

A quotation is considered a duplicate when its Quote and Author match an existing quotation, ignoring capitalization and differences in whitespace. Source, Tags and Active status are not part of duplicate detection.

Tags are normalized when imported. Whitespace around commas is standardized and duplicate tags within the same quotation are removed case-insensitively.

CSV imports are limited to 5 MB.

== Data and privacy ==

Quotum stores quotations in a dedicated WordPress database table. It does not send quotation data to an external service.

The plugin does not collect analytics or tracking data.

== Security ==

Administrative actions require the appropriate WordPress capability and nonce verification. User-supplied values are sanitized and database operations use WordPress database APIs.

== Changelog ==

= 0.7.4 =
* Remove obsolete manual translation loading for WordPress.org distribution.
* No database schema or front-end behavior changes.

= 0.7.3 =
* Refine the Quotum administration, import/export workflow, and translation support.
* Improve compatibility with active-plugin replacements and preserve existing data.
* Verify widget, shortcode, and Next Quote functionality.

= 0.7.2 =
* Improve import feedback and administration navigation.

= 0.7.1 =
* Improve CSV import status handling and administration labels.

= 0.7.0 =
* Establish a separate Quotum plugin and database table, independent of Quotes Library.


= 0.6.20 =
* Rename the plugin to Quotum and adopt the quotum text domain.
* Move administration JavaScript into enqueued files.
* Adjust the administration menu position and clarify activation of sample quotations.
* Preserve existing quotation data, widget settings, shortcodes, and admin links.


= 0.6.19 =
* Remove the discouraged explicit translation-domain loader; retain German and French catalogs.


= 0.6.18 =
* Added bundled German and French interface translations, a gettext template, and translation loading.

= 0.6.17 =
* Fixed the tag summary database query by preparing its table identifier.

= 0.6.16 =
* Fixed loading of WordPress filesystem classes for bundled sample and uploaded CSV imports.

= 0.6.15 =
* Reduced spacing above the optional Next quote link and set its font size to 0.75em.

= 0.6.14 =
* Added optional accessible manual Next Quote link for widgets and shortcodes.
* Added public REST retrieval of active quotations, respecting shortcode tags and display settings.
* Link text is customizable per widget or via shortcode refresh_text; disabled by default.


= 0.6.13 =
* Sample quotations always import as inactive, regardless of CSV status.
* Regular CSV imports default to inactive; explicit status options remain available.
* Updated bundled sample CSV to inactive and clarified documentation.


= 0.6.12 =
* Added optional welcome notice with sample import and dismiss buttons on fresh installations.
* Added permanent sample import button to Export / Import.
* Sample import uses the existing CSV duplicate handling and never overwrites existing quotations.
* Version numbers synchronized throughout the package.


= 0.6.11 =
* Create the quotations database table automatically on plugin activation.
* Align the internal version constant with the release version.
* Document data preservation and add author identifiers in the credits.

= 0.6.10 =
* Improved prepared-query construction for WordPress.org Plugin Check compatibility.

= 0.6.5–0.6.9 =
* Improved internationalization annotations and query checks for WordPress.org review.


= 0.6.4 =
* Improved database query handling for WordPress.org Plugin Check compatibility.
* Improved output escaping in the widget and admin quotation list.
* Improved input sanitization for quotation tags.

= 0.6.3 =
* Improved internationalization of admin and widget interface strings.
* Added `Requires at least` and `Requires PHP` plugin header metadata.


= 0.6.2 =
* Updated WordPress.org plugin metadata and authorship information.

= 0.6.1 =
* Added plugin documentation in `readme.txt`.
* Clarified shortcode, widget and CSV import/export documentation.

= 0.6.0 =
* Added consistent tag normalization when quotations are saved or imported.
* Removed duplicate tags within a quotation case-insensitively.
* Standardized whitespace around comma-separated tags.

= 0.5.9 =
* Fixed tag filtering for tags stored with spaces after commas.

= 0.5.8 =
* Added a quotation count setting to the widget.

= 0.5.7 =
* Added the `count` shortcode parameter for displaying multiple random quotations.

= 0.5.6 =
* Improved the admin quotation editor layout.

= 0.5.5 =
* Improved the admin editor field sizing and spacing.

= 0.5.4 =
* Fixed public stylesheet loading after the plugin was modularized.
* Restored the intended public quotation typography.

= 0.5.3 =
* Fixed duplicate function declarations introduced during modularization.

= 0.5.2 =
* Improved the admin quotation editor.

= 0.5.1 =
* Improved responsive public quotation typography.

= 0.5.0 =
* Improved public quotation display and responsive styling.

= 0.4.9 =
* Modularized the plugin into separate include files.

= 0.4.8 =
* Improved CSV validation and displayed invalid-import errors as normal admin notices.

= 0.4.7 =
* Added CSV header validation.

= 0.4.6 =
* Improved CSV import result reporting.

= 0.4.5 =
* Added import status selection.

= 0.4.0 =
* Added CSV export and import.
* Added duplicate detection based on Quote + Author.

= 0.3.9 =
* Fixed bulk-delete selection handling.

= 0.3.8 =
* Improved bulk-action checkbox handling.

= 0.3.7 =
* Added bulk permanent deletion.

= 0.3.6 =
* Added sortable quotation-list columns.

= 0.3.x =
* Added quotation search, filtering, pagination, tag management, bulk status changes, live preview, shortcode and widget support.

= Earlier versions =
* Initial Quotum development and data migration functionality.

== Credits ==

Idea and development: Christian Ries, Bivels, Luxembourg
WordPress.org: https://profiles.wordpress.org/christian-ries/
ORCID: https://orcid.org/0000-0002-1991-5839
Development assistance: ChatGPT (OpenAI)

== Moving from another quotation plugin ==

Quotum is an independent plugin with its own database table and widget. Export quotations from your previous plugin in a compatible CSV format, install Quotum alongside it, and import the CSV using the option to preserve the CSV active status. Replace old widgets and shortcodes with the Quotum widget and `[quotum]` shortcode. Deactivate the older plugin only after verifying the new installation.

== Screenshots ==

1. Manage quotations with search, tag and status filters, bulk actions, and multilingual content.
2. Add and edit quotations with author attribution, sources, tags, publication status, and a live preview.
3. Configure the Quotum widget with options for quotation count, author and source display, vertical line, and an interactive Next Quote link.
4. Display quotations on the frontend with author attribution and an interactive Next Quote link that loads another quotation without reloading the page.
