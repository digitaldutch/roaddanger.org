# Changelog

9 october 2026 v745:
- Code: admin/createdatabase.sql updated to match the current database structure.

30 september 2026 v744:
- Filter close buttons now have a round background when hovering over them.

26 september 2026 v743:
- Statistics General:
  - Health and persons filter options added
- Filter:
  - Added year options (2026, 2025, etc).
  - Fixed: "Last x years" filter in research did not return exactly 2 years, it included the complete first year.
  - Code: SQL improved to use indices for filtering on a year.

26 september 2026 v742:
- Fixed: Webpage layout where navigation or filter sections were sometimes visible
- Fixed: French plural for crashes fixed from "crash" to "crashs"

26 september 2026 v741:
- General Statistics page now has a filter option
- Custom date filter now shows the Start en End dates on the filter status bar
- Fixed: Dead_(multiple) translation error in Dutch
- Added CHANGELOG.md file

2 August 2026 v739:
- Chromium: no-sandbox option again added as it is required on new server.

29 July 2026 v738:
- Chromium: --no-sandbox option removed as we run on a new server
- Improved documentation for installation

15 May 2026 v737:
- Questionnaire results:
- Bars now show number of crashes for each segment score.

7 May 2026 v736:
- Answer questionnaire questions form can now be closed by clicking on the background.