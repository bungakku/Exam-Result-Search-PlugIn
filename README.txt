# Exam Result Manager

**Contributors:** bungakku  
**Tags:** exam, result, marks, student, marksheet, print, CSV import, GitHub updater  
**Requires at least:** 5.0  
**Tested up to:** 6.4  
**Stable tag:** 4.5.1  
**License:** GPLv2 or later  
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html  

A complete WordPress plugin to manage student exam results with subject-wise marks, automatic grade calculation, CSV import, and printable marksheets. **Includes built‑in GitHub updater** – you'll receive update notifications directly in your WordPress admin when a new version is released.

## Description

Exam Result Manager allows you to:

- Store student exam results with detailed subject-wise marks (Internal, External, Practical).
- Automatically calculate subject totals, overall total, and grades.
- Display results on the frontend using a shortcode `[exam_result_search]` with search by Roll No, Class, Semester, and Year.
- Print a clean, professional marksheet for each student (with logo and institute name).
- Import results from CSV files in bulk.
- Fully responsive and mobile-friendly.
- **Auto‑update** via GitHub – the plugin checks for new releases and shows a notification on the Plugins page.

## Installation

1. Upload the plugin folder to `/wp-content/plugins/` or install via the WordPress admin.
2. Activate the plugin through the 'Plugins' menu.
3. Go to **Exam Results** → **Add New** to manually add results.
4. Use the shortcode `[exam_result_search]` on any page or post.
5. Customize institute name, logo, and max marks per component under **Exam Results** → **Marksheet Settings**.

## Usage

### Shortcode

Place `[exam_result_search]` on any page. It renders a search form and displays matching results.

### CSV Import Format

The CSV must have the following columns (order matters):

- **Roll No**, **Name**, **Class**, **Section**, **Semester**, **Year**,  
  then for each subject: **Code**, **Subject Name**, **Internal**, **External**, **Practical**.

Example:

```csv
101,Onisimus Lanamai,10,A,1st,2024-2025,MATH,Mathematics,8,65,18,SCI,Science,7,58,15