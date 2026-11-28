# Changelog

## 2026-11-27 - 1.5.7

- Refresh the pack icon using the site's blue J and red L logo as the visual reference.
- Escape Site Utilities page dropdown markup and YOURLS compatibility output reported by the 1.5.6 Plugin Check scan.
- Sanitize Site Utilities, Post Visibility, and bulk excerpt form arrays; keep nonce verification next to the bulk excerpt request handling.
- Prepare the Hide Posts import table as a WordPress SQL identifier and escape its name in the table existence check. This requires WordPress 6.2 or newer.

## 2026-11-27 - 1.5.6

- Add 128-pixel and 256-pixel JL plugin icons for Git Updater artwork.
- Escape the final AI Posts dropdown markup through a restricted HTML allowlist, resolving four Plugin Check output escaping findings reported against the installed 1.5.3 copy.
- Validate public poll choices against the server-side count captured when the shortcode renders; sanitize administrator vote totals. Explain the prepared, cursor-based Ask My Writing query to the static SQL checker.
- Build the install ZIP without development dotfiles and regression tests. The GitHub `Update URI` remains because this distribution uses GitHub based updates; WordPress.org submissions require a separate update strategy.

## 2026-11-27 - 1.5.5

- Add disabled-by-default Konami Code, YOURLS short-link, custom 404, and combined post visibility utilities under JL Site Utilities.
- Add explicit imports for the original plugins' saved WordPress options, existing YOURLS post metadata, Search Exclude 2.x groups and older IDs, and Hide Posts rules. Original plugin data is not removed.
- Match Hide Posts 2.1.3 sitemap and Yoast visibility rules, and preserve REST edit access to hidden posts.
- Pause each replacement while its original plugin is active, and document staged migration and known feature limits.

## 2026-11-26 - 1.5.4

- Replace the bulk excerpt dry run and immediate write with a reviewable preview.
- Add editable excerpt fields, row and batch approval, selection, and regeneration controls.
- Recheck post eligibility and source content before saving an approved excerpt.

## 2026-11-25 - 1.5.3

- Group Bulk Excerpts, GitHub Drafts, AI Posts, Ask My Writing, Polls, and Webstats under the **JL WP Plugins Pack** admin menu.
- Add opt-in tracking of public page views and clicks under **JL WP Plugins Pack → JL Webstats**.
- Document how to configure the central Webstats collector and enable tracking in WordPress.

## 2026-11-01 - 1.5.2

- Let administrators enter option names in voting order for polls whose older vote totals have no saved labels.
- Preserve manually entered names when the poll shortcode renders again.

## 2026-11-01 - 1.5.1

- Show poll option names beside editable vote totals, including choices with zero votes, after the shortcode has rendered.
- Rename the Tools menu item to JL Ask My Writing.
- Bump the plugin version so Git Updater can detect these changes.

## 2026-11-01 - 1.5.0

- Added administrator controls to edit individual poll choice totals or reset a single poll.
- Preserved other poll totals when adjusting or clearing one poll.

## 2026-11-01 - 1.4.1

- Bumped the plugin version so WordPress and Git Updater detect the merged poll button styling update.

All notable changes to this project should be documented in this file.

## 2026-11-01 - 1.4.0

- Added the customizable [jl_poll] shortcode with unique poll IDs, custom questions, and 2–8 choices.
- Kept [jl_baby_gender_poll] as a Boy/Girl shortcut.
- Added aggregate totals and reset controls under Tools -> JL Polls.
- Documented the poll shortcode and its data handling.

## Unreleased

- Resumed Ask My Writing index builds from the last processed post ID so edits to earlier posts cannot skip later posts.
- Recovered uncommitted index bytes before retrying and checkpointed completed batches before publishing the index.
- Preserved word boundaries when extracting text across HTML line breaks.

## 2026-10-27 - 1.3.0

- Added an Ask My Writing page at `/ask/` with a conversational question form and links to source posts/pages.
- Added `Tools -> Ask My Writing` for index status, answer model settings, and resumable manual index builds.
- Added passage extraction and JSONL storage under `public_html/ask-data/`, including direct-access denial files and per-site subdirectories on multisite.
- Added semantic search with OpenAI `text-embedding-3-small` and grounded answers using the existing JL AI Posts API key.
- Added public search request limits; administrators can bypass the limit while signed in.
- Kept generated search data outside the plugin repository so plugin updates do not replace it.
- Excluded password-protected posts and rechecked source visibility before answering.
- Serialized concurrent index builds and per-IP request-limit updates.
- Used a data directory name that does not shadow the `/ask/` WordPress page route.

## 2026-07-02 - 1.2.0

- Added scheduled AI-assisted blog post generation under `Tools -> JL AI Posts`.
- Added WP-Cron scheduling controls for hourly, twice-daily, daily, and weekly generation.
- Added AI author selection and status policy handling for draft, pending review, role-based publishing, and publish-if-allowed workflows.
- Added OpenAI API key support through either the `JL_WP_PLUGINS_PACK_OPENAI_API_KEY` constant or a stored WordPress option.
- Added manual "Generate One AI Post Now" testing and last-run status logging.
- Added README setup and safety notes for AI-generated posts.
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
