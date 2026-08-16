# Exam Result Manager

**Contributors:** Biswajit  
**Tags:** exam, result, marks, student, marksheet, print, CSV import, GitHub updater  
**Requires at least:** 5.0  
**Tested up to:** 6.7  
**Stable tag:** 4.7.23  
**License:** GPLv2 or later  
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html  

A complete WordPress plugin to manage student exam results with subject-wise marks, automatic grade calculation, CSV import, and printable marksheets. **Includes built‑in GitHub updater** – you'll receive update notifications directly in your WordPress admin when a new version is released.

## Description

Exam Result Manager allows you to:

- Store student exam results with detailed subject-wise marks (Internal, External, Practical).
- Automatically calculate subject totals, overall total, and grades.
- Display results on the frontend using a shortcode `[exam_result_search]` with search by Roll No, Class, Semester, and Year.
- Show a fully customizable institute header (logo, name, and optional tagline) above every result card and on the printed marksheet — logo position (left/right/above title), header alignment (left/center/right), logo size, logo-to-title spacing, and independent title/tagline font sizes are all adjustable from one settings page, and apply identically on the website and on the printout.
- Print a clean, professional marksheet for each student (with logo and institute name).
- Import results from CSV files in bulk.
- Fully responsive and mobile-friendly.
- **Auto‑update** via GitHub – the plugin checks for new releases and shows a notification on the Plugins page, with a manual "Check for updates" link and visible error messages if the check fails.

## Installation

1. Upload the plugin folder to `/wp-content/plugins/` or install via the WordPress admin.
2. Activate the plugin through the 'Plugins' menu.
3. Go to **Exam Results** → **Add New** to manually add results.
4. Use the shortcode `[exam_result_search]` on any page or post.
5. Customize institute name, tagline, logo, logo position/size, header alignment, font sizes, and max marks per component under **Exam Results** → **Marksheet Settings**.

## Usage

### Shortcode

Place `[exam_result_search]` on any page. It renders a search form and displays matching results.

### Marksheet Settings

Under **Exam Results** → **Marksheet Settings** you can configure:

- **Institute Name** – shown on every result card and printed marksheet.
- **Tagline (optional)** – a short line shown below the institute name wherever it appears. Leave blank to hide it.
- **Institute Logo (URL)** – upload via the media library or paste a URL.
- **Logo Width** – in pixels (20–400). Height scales automatically.
- **Logo Position** – Left of title, Right of title, or Above title.
- **Logo – Title Gap** – spacing in pixels (0–200) between the logo and the institute name/tagline block (horizontal when the logo is left/right, vertical when it is above).
- **Header Alignment** – Left, Center, or Right. Controls the title/tagline text alignment, and also where the whole header group sits (and where the logo sits when it's positioned above the title).
- **Title Font Size** – in pixels (10–72).
- **Tagline Font Size** – in pixels (8–48).

A live preview on the settings page shows exactly how the header will look as you adjust these fields — the same layout is then used on the frontend search-result cards and on the printed marksheet, so what you see in the preview is what visitors and printouts will show.

### Updating via GitHub

The plugin checks `https://github.com/bungakku/Exam-Result-Search-PlugIn/releases/latest` on WordPress's normal update schedule. If you've just published a release and don't see it yet:

- Use the **Check for updates** link on the plugin's row on the Plugins page (forces an immediate re-check), or
- Visit **Dashboard → Updates** and click **Check Again**.

If the check fails (e.g. no release published yet, or a network/API issue), a notice explaining why is shown at the top of the Plugins page instead of failing silently.

### CSV Import Format

The CSV must have the following columns (order matters):

- **Roll No**, **Name**, **Class**, **Section**, **Semester**, **Year**,  
  then for each subject: **Code**, **Subject Name**, **Internal**, **External**, **Practical**.

Example:

```csv
101,Onisimus Lanamai,10,A,1st,2024-2025,MATH,Mathematics,8,65,18,SCI,Science,7,58,15
```

For very large files (roughly 1,000+ rows), consider splitting into smaller batches to reduce the chance of a server timeout on shared hosting.
