# Changelog

## [0.3a] - 2026-02-02
### Added
- **Aggressive Mode**: New "Deep Space" optimization tier (Experimental).
- **Inner Wrapper Removal**: Optionally removes `.elementor-inner` divs.
- **Deep Flattening**: Merges redundant nested containers (parent with single child).

## [0.2a] - 2026-01-31
### Added
- **Settings Page**: Added `Settings > DOM Optimiser` in WP Admin to control feature flags.
- **Advanced Safety**: New `scan_scripts_for_ids` logic detects IDs used in inline JavaScript and protects them from removal.
- **Refactor**: Split logic into `includes/class-settings.php` and `includes/class-optimiser.php`.

## [0.1a] - 2024-05-23
### Added
- **Core Pruning Engine**: Filters frontend output buffer via `template_redirect`.
- **Wrapper Collapse**: Removes `elementor-column-wrap`, `elementor-widget-wrap`, `elementor-widget-container` if safe.
- **Ghost Node Removal**: Detects and removes empty empty nodes with no classes/IDs/styles.
- **Safety Rails**: `id`, `data-*`, `aria-*`, and user-defined class protection logic.
