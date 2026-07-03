# Changelog

All notable changes to this project should be documented in this file.

## 2026-07-02 - 1.2.0

- Added scheduled AI-assisted blog post generation under `Tools -> JL AI Posts`.
- Added WP-Cron scheduling controls for hourly, twice-daily, daily, and weekly generation.
- Added AI author selection and status policy handling for draft, pending review, role-based publishing, and publish-if-allowed workflows.
- Added OpenAI API key support through either the `JL_WP_PLUGINS_PACK_OPENAI_API_KEY` constant or a stored WordPress option.
- Added manual "Generate One AI Post Now" testing and last-run status logging.
- Added README setup and safety notes for AI-generated posts.

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
