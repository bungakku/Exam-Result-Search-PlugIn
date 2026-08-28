=== Exam Result Manager ===
Contributors: Biswajit
Tags: exam, result, marks, student, marksheet, print, CSV import, GitHub updater
Requires at least: 5.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 4.7.42
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage student exam results with subject-wise marks, automatic grading, CSV import, and printable marksheets.

== Description ==

Exam Result Manager allows you to:

* Choose a theme color -- applied to borders, headings, buttons, and table headers on both the website and printed marksheets. A complementary lighter shade is generated automatically for gradients on the website.
* Store student exam results with detailed subject-wise marks (Internal, External, Practical).
* Automatically calculate subject totals, overall total, and grades.
* Display results on the frontend using a shortcode `[exam_result_search]` with search by Roll No, Class, Semester, and Year.
* Show a fully customizable institute header (logo, name, and optional tagline) above every result card and on the printed marksheet -- logo position (left/right/above title), header alignment (left/center/right), logo size, logo-to-title spacing, and independent title/tagline font sizes are all adjustable from one settings page, and apply identically on the website and on the printout.
* Print a clean, professional marksheet for each student (with logo and institute name).
* Import results from CSV files in bulk.
* Fully responsive and mobile-friendly.
* Auto-update via GitHub -- the plugin checks for new releases and shows a notification on the Plugins page, with a manual "Check for updates" link and visible error messages if the check fails.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install via the WordPress admin.
2. Activate the plugin through the 'Plugins' menu.
3. Go to Exam Results -> Add New to manually add results.
4. Use the shortcode `[exam_result_search]` on any page or post.
5. Customize institute name, tagline, logo, logo position/size, header alignment, font sizes, and max marks per component under Exam Results -> Marksheet Settings.

== Usage ==

= Shortcode =

Place `[exam_result_search]` on any page. It renders a search form and displays matching results.

= Marksheet Settings =

Under Exam Results -> Marksheet Settings you can configure:

* Institute Name -- shown on every result card and printed marksheet.
* Tagline (optional) -- a short line shown below the institute name wherever it appears. Leave blank to hide it.
* Description (optional) -- an additional supporting line shown below the tagline, in a smaller font. Leave blank to hide it.
* Subject Column Label -- choose "Subject Code" or "Sl. No." for the subject-row column, matching your institute's own terminology. Applied consistently in the admin entry form, the result table, and the printed marksheet.
* Institute Name Watermark on Printouts -- optional, off by default. Shows a subtle watermark behind the marks table on the printed marksheet only (not the website display). Watermark Source lets you choose Text (repeats in a tiled pattern; uses a dedicated Watermark Text field, or falls back to the Institute Name if left blank) or Institute Logo (appears once, centered, automatically scaled to fit the marks table without distortion -- not tiled). Watermark Opacity applies to both. Watermark Logo Size (a percentage of the auto-fit area) and Watermark Text Size/spacing are source-specific. A transparent PNG logo is recommended for the logo watermark, since a non-transparent one will appear as a solid block rather than a subtle watermark.
* Institute Logo (URL) -- upload via the media library or paste a URL.
* Logo Width -- in pixels (20-400). Height scales automatically.
* Logo Position -- Left of title, Right of title, or Above title.
* Logo - Title Gap -- spacing in pixels (0-200) between the logo and the institute name/tagline block (horizontal when the logo is left/right, vertical when it is above).
* Header Alignment -- Left, Center, or Right. Controls the title/tagline text alignment, and also where the whole header group sits (and where the logo sits when it's positioned above the title).
* Title Font Size -- in pixels (10-72).
* Tagline Font Size -- in pixels (8-48).
* Delete Data on Uninstall -- off by default. Only enable if you want exam results and settings permanently deleted when the plugin is removed.

A live preview on the settings page shows exactly how the header will look as you adjust these fields -- the same layout is then used on the frontend search-result cards and on the printed marksheet, so what you see in the preview is what visitors and printouts will show.

= Updating via GitHub =

The plugin checks `https://github.com/bungakku/Exam-Result-Search-PlugIn/releases/latest` on WordPress's normal update schedule. If you've just published a release and don't see it yet:

* Use the Check for updates link on the plugin's row on the Plugins page (forces an immediate re-check), or
* Visit Dashboard -> Updates and click Check Again.

If the check fails (e.g. no release published yet, or a network/API issue), a notice explaining why is shown at the top of the Plugins page instead of failing silently.

= CSV Import Format =

The CSV must have the following columns (order matters):

Roll No, Name, Class, Section, Semester, Year, then for each subject: Code, Subject Name, Internal, External, Practical.

Example:

`101,Onisimus Lanamai,10,A,1st,2024-2025,MATH,Mathematics,8,65,18,SCI,Science,7,58,15`

For very large files (roughly 1,000+ rows), consider splitting into smaller batches to reduce the chance of a server timeout on shared hosting.

== Changelog ==

Detailed tracking begins at 4.7.2; earlier versions (4.7.1 and prior) predate this changelog.

= 4.7.42 =
* Fixed the Institute Logo print watermark still not displaying at all after the 4.7.41 fix. Root cause: an SVG used as a CSS background-image runs in a browser-enforced restricted context where an embedded image reference is not permitted to fetch a separate external resource -- referencing the logo by its plain URL inside the watermark SVG was therefore never allowed to load, regardless of sizing or container fixes. The logo's own file bytes are now read (from this site's uploads folder directly, or fetched once and cached otherwise) and embedded directly inside the watermark SVG as a self-contained data URI, leaving no external reference to block. No change to the Text watermark, which never had this issue.

= 4.7.41 =
* Fixed the Institute Logo print watermark appearing clipped, skewed, or pixelated instead of cleanly filling the marks table. Root cause: a `<table>` element with `border-collapse: collapse` does not reliably expose its full rendered box as a background-paint area across browsers/print engines, so `background-size: contain` was being sized against the wrong area. The watermark background now paints on a plain wrapping div around the table instead of the table element itself -- a reliable, standard CSS container -- with the table's existing (semi-)transparent row backgrounds showing it through exactly as before. No change to the Text watermark's tiled appearance.

= 4.7.40 =
* Fixed the Institute Logo print watermark (added in 4.7.39) still tiling/repeating across the marks table instead of appearing once, auto-fit and centered as intended -- the print stylesheet applied the same repeating background rule to both watermark sources regardless of which was selected. Now branches per source: Institute Logo displays no-repeat, centered, scaled to fit; Text keeps its original tiled/repeating display, unchanged.

= 4.7.39 =
* Added a Watermark Source setting (Text / Institute Logo) for the print watermark. Text keeps its existing tiled/repeating behavior unchanged. Institute Logo shows a single, non-repeating instance of the configured logo, centered and automatically scaled (via a new Watermark Logo Size percentage) to fit within the marks table without distortion or cropping. Watermark Opacity is shared between both sources; Watermark Text Size and the horizontal/vertical spacing controls remain Text-only. Note: a non-transparent logo (e.g. with a white background) will appear as a solid block rather than a subtle watermark -- use a transparent PNG for a clean result.

= 4.7.38 =
* Fixed low watermark spacing values (1-5px) producing little to no visible difference. Horizontal spacing's minimum was still floored at 10px (only vertical had been lowered previously); separately, both tile-size formulas had oversized internal safety floors/baselines that could mask small spacing changes for typical watermark text. Both are now tuned to be properly responsive across their full range.

= 4.7.37 =
* Added a Theme Color setting (free-form color picker), replacing the fixed blue used for borders, headings, buttons, and table headers on the website and printed marksheets. Grade colors, table-header cell borders, and the print watermark are intentionally unaffected.

= 4.7.36 =
* Lowered the minimum allowed Watermark Vertical Spacing from 5px to 1px, for a denser row-to-row repeat when wanted.

= 4.7.35 =
* Added an independent Watermark Vertical Spacing control, decoupled from horizontal spacing (previously vertical density was derived automatically from the horizontal value).

= 4.7.34 =
* Added opacity, text size, and spacing (repeat density) controls for the print watermark. Tile size is now computed from the text/font size so longer institute names don't get clipped.

= 4.7.33 =
* Fixed the print watermark (and existing table-header/summary-box colors) potentially not appearing in print/PDF output -- browsers can suppress background colors/images when printing unless explicitly forced. Also raised watermark opacity slightly for more reliable visibility.

= 4.7.32 =
* Added an optional institute name watermark behind the marks table on printed marksheets, off by default. Also made the table's alternating-row shading semi-transparent so the watermark shows through evenly (negligible visual difference on its own).

= 4.7.31 =
* Added a "Subject Column Label" setting to choose between "Subject Code" and "Sl. No." -- applied consistently in the admin entry form, results table, and printed marksheet.

= 4.7.30 =
* Added an optional Description line below the Tagline (smaller font, configurable size), shown consistently on search results and printed marksheets.

= 4.7.29 =
* Added defensive CSV/Excel formula-injection sanitization on text fields (Roll No, Name, Class, Section, Semester, Year, Subject Code/Name), applied consistently across manual entry, CSV import, and search. Preemptive -- no export feature exists yet, but stored data is now safe if one is added later.

= 4.7.28 =
* Guarded against a CSV import crash if the uploaded file's temp handle ever fails to open.

= 4.7.27 =
* Added a "Requires PHP: 8.0" header, matching what the plugin is actually built and tested for.

= 4.7.26 =
* Converted readme.txt to the standard WordPress plugin-readme format (readme.md remains Markdown for GitHub).

= 4.7.25 =
* Added a subtle hover/focus lift to the Search and Print buttons.

= 4.7.24 =
* Color-coded grades (A+/A green, F red) on the results card.
* Removed unused CSS custom properties.

= 4.7.23 =
* Removed a dead `wp_localize_script` call.

= 4.7.22 =
* Consolidated duplicate grade-threshold logic into one method.

= 4.7.21 =
* Removed the temporary update-notice diagnostic added in 4.7.16, its purpose served.

= 4.7.20 =
* Added accessible labels to subject-row inputs in the admin editor.

= 4.7.19 =
* Fixed the update-available notice persisting after updating, by making the version comparison immune to PHP opcode-cache staleness.

= 4.7.18 =
* Fixed a hardcoded, non-translatable string in the printed marksheet subtitle.

= 4.7.17 =
* Added bounds validation so marks can no longer exceed the configured maximum.

= 4.7.16 =
* Added a temporary diagnostic notice to investigate the update-notice issue (removed in 4.7.21).

= 4.7.15 =
* Fixed uninstall permanently destroying all data; added an opt-in "Delete Data on Uninstall" setting, off by default.

= 4.7.14 =
* Attempted fix for update-notice persistence via opcode-cache invalidation on update completion (fully resolved in 4.7.19).

= 4.7.13 =
* Added a time-limit safety net for large CSV imports.

= 4.7.12 =
* Faster public search via a composite lookup key, with automatic backfill migration and a safety-net fallback.

= 4.7.11 =
* Fixed the institute logo still being missing from printouts, and an "about:blank" print footer (supersedes 4.7.9).

= 4.7.10 =
* Added rate limiting to the public search and print endpoints.

= 4.7.9 =
* Attempted fix for the institute logo missing from printed marksheets (superseded by 4.7.11).

= 4.7.8 =
* Fixed a redundant double-save when auto-titling a new result.

= 4.7.7 =
* Fixed the plugin auto-deactivating on every update, caused by a GitHub zipball folder-name mismatch.

= 4.7.6 =
* Hardened the subject-array save loop against malformed/mismatched POST data.

= 4.7.5 =
* Fixed draft/unpublished results being printable by guessing a post ID.

= 4.7.4 =
* Fixed Delete in Result Manager permanently destroying results instead of moving to Trash.

= 4.7.3 =
* Updated Author URI to GitHub profile; added a Plugin URI header.

= 4.7.2 =
* Fixed a fatal PHP 8 error when printing legacy-format exam results.
