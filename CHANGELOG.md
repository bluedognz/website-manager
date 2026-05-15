# Changelog

All notable changes to Blue Dog Website Manager are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [1.0.10] — 2026-05-15

### Fixed
- Plugin description now displays correctly in WP Plugins list under white label mode (was blank)

## [1.0.9] — 2026-05-15

### Fixed
- Plugin always appears under Tools menu regardless of white label mode — never in main admin sidebar
- Plugin name now correctly renamed in WP Plugins list for non-owner admins when white label is active

## [1.0.8] — 2026-05-15

### Added
- "Open with Page Builder ↗" link next to the active template in the BB Dashboard module

### Changed
- Plugin removed from main admin sidebar — now accessible via Tools only
- Updated plugin description

## [1.0.7] — 2026-05-15

### Added
- Disable Admin Email Verification Check module (suppresses WP 5.2+ admin email prompt)
- Auto-Set Image Metadata on Upload module (title, alt text, caption, description from filename)
- Microthemer: Retain Styles When Deactivated module (enqueues Microthemer CSS when plugin is inactive)

### Fixed
- Import/Export modals now available on the Settings tab as well as Modules
- Plugin layout centred correctly (`margin: 0 auto`)
- Settings page save no longer overwrites `bb_dashboard_id` with zero
- Settings page CSS not loading due to hook name being derived from sanitised menu title rather than slug
- BB dashboard widget rendered but invisible — WordPress marks new postboxes with empty titles as closed; fixed by providing a title and forcing open state via JS
- Dashboard template now renders full-width by hiding unused postbox containers

## [1.0.6] — 2026-05-14

### Changed
- Architectural fix to card/gear/toggle HTML structure
- Settings page styling reworked to match Modules page

## [1.0.0] — 2026-05-14

### Added
- Initial release
- Replace Dashboard with Beaver Builder Template module
- Disable Comments module
- Disable Automatic Additional Image Sizes module
- Enable SVG Upload Support module
- White label mode (custom display name, owner-only access, user hiding)
- Export/Import settings via base64 string
- Auto-updates via GitHub using Plugin Update Checker (PUC)
- Tools menu entry
