# Changelog

All notable changes to this project should be documented in this file.

## 2026-06-17 - 1.2.0

- Added GitHub PowerShell script monitoring under `Tools -> JL GitHub Drafts`.
- Added weekly scans with an optional daily schedule.
- Added manual GitHub checks.
- Added a safe first-run baseline so existing `.ps1` files do not generate drafts.
- Added parsing for Jason's standard PowerShell script header.
- Added draft posts containing header metadata and a live link to the original GitHub file.
- Added refresh behavior for linked draft and pending posts when the source changes.
- Existing baseline scripts do not create new drafts merely because they were edited.
- Published posts are not overwritten.
- Limited each scan to 20 new or changed scripts for shared-hosting safety.
- Added activation/deactivation handling for WP-Cron.

## 2026-06-17 - 1.1.5

- Bumped the plugin version to `1.1.5` to verify the Git Updater update flow again after the `1.1.4` rename release.

## 2026-06-13 - 1.1.4

- Renamed the user-facing plugin name from `JL Content Tools` to `JL WP Plugins Pack`.
- Updated the WordPress admin page label and README examples to use `JL WP Plugins Pack`.
- Bumped the plugin version to `1.1.4` so Git Updater can detect the renamed display-name update.

## 2026-06-13 - 1.1.3

- Bumped the plugin version to `1.1.3` after manually syncing the renamed plugin files to the live Hostinger install.

## 2026-06-13 - 1.1.2

- Bumped the plugin version to `1.1.2` so Git Updater can detect a newer release than the installed `1.1.1` build.

## 2026-06-13

- Renamed the plugin slug from `jl-content-tools` to `jl-wp-plugins-pack`.
- Renamed the plugin entry file to `jl-wp-plugins-pack.php`.
- Renamed the main include file to `includes/class-jl-wp-plugins-pack.php`.
- Updated the plugin text domain, constants, class name, admin page slug, and asset handles to match the new slug.
- Updated Git Updater headers and repository URLs to use `jasrasr/jl-wp-plugins-pack`.
- Updated README install, clone, workflow, and WordPress implementation notes to use the new plugin slug and plugin-only scope.
