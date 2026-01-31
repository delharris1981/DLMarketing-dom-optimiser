# Changelog

## [0.1a] - 2024-05-23
### Added
- **Core Pruning Engine**: Filters frontend output buffer via `template_redirect`.
- **Wrapper Collapse**: Removes `elementor-column-wrap`, `elementor-widget-wrap`, `elementor-widget-container` if safe.
- **Ghost Node Removal**: Detects and removes empty empty nodes with no classes/IDs/styles.
- **Safety Rails**: `id`, `data-*`, `aria-*`, and user-defined class protection logic.
