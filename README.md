# JL WP Plugins Pack

Custom WordPress content utilities for Jason Lamb sites.

- Website: https://jasonlamb.me
- GitHub repository: https://github.com/jasrasr/jl-wp-plugins-pack
- Git Updater: https://github.com/afragen/git-updater
- Git Updater website: https://git-updater.com/

## Features

### Bulk missing excerpts

- Adds `Tools -> JL WP Plugins Pack`.
- Finds published posts/pages with empty excerpts.
- Generates excerpts from existing content.
- Includes dry-run mode and batch controls.

### Automatic excerpts

- Generates a 35-word excerpt when a normal post is saved with an empty excerpt.
- Does not overwrite manual excerpts.

### Hashtag linking

- Links hashtags such as `#PowerShell` to matching WordPress tag archives.
- Appends hashtag words as WordPress tags when a post is saved.
- Preserves manually assigned tags.
- Avoids existing links, code blocks, preformatted blocks, scripts, styles, and comments.

### GitHub PowerShell drafts

- Adds `Tools -> JL GitHub Drafts`.
- Monitors `jasrasr/powershell`, branch `main`, for new `.ps1` files.
- Runs weekly by default, with a daily option.
- Includes a manual **Save and Run Check Now** button.
- Uses a safe first-run baseline, so existing scripts do not create a flood of drafts.
- Creates drafts only for newly added scripts.
- Refreshes an already-linked draft or pending post when its source script changes.
- Does not create a new draft merely because an old baseline script was modified.
- Does not overwrite published posts.
- Does not copy or execute PowerShell code.
- Links to the original GitHub file so the latest source stays authoritative.

The header parser supports:

```powershell
# Filename: <ScriptName>.ps1
# Revision : 1.0.0
# Description : <Short description of what this script does>
# Author : Jason Lamb (with help from Codex)
# Created Date : <YYYY-MM-DD>
# Modified Date : <YYYY-MM-DD>
# Changelog :
# 1.0.0 initial release
```

## Configure GitHub PowerShell drafts

1. In WordPress admin, open:

   ```text
   Tools -> JL GitHub Drafts
   ```

2. Choose weekly or daily scans and save the settings.
3. Click **Save and Run Check Now**.
4. The first scan records all current `.ps1` files as the baseline and creates no drafts.
5. Add a new `.ps1` file to `jasrasr/powershell`.
6. Run another manual check or wait for WP-Cron.
7. Review the generated post under `Posts -> All Posts`.

## WP-Cron behavior

WP-Cron is request-driven. A weekly or daily event runs when WordPress receives traffic after the scheduled time; it is not an exact background clock.

For exact timing, configure a hosting cron job to request `wp-cron.php`.

## GitHub API behavior

The monitored repository is public, so no GitHub token is required.

Each scan performs:

- One repository-tree API request.
- One file-content API request for each new script or linked draft that needs refreshing.
- At most 20 changed/new files per scan to protect shared hosting and stay within unauthenticated GitHub API limits.

4. **Scheduled AI-assisted blog posts**
   - Adds a scheduled AI blog post generator under `Tools -> JL AI Posts`.
   - Uses WP-Cron to generate one post per run.
   - Supports hourly, twice-daily, daily, and weekly schedules.
   - Lets you choose the WordPress author account used for generated posts.
   - Saves posts as draft, pending review, or publish depending on the configured policy and the selected author's WordPress capabilities.
   - Stores the last run status and a link to the most recent generated post.
   - Supports a disclosure footer for AI-assisted content.

## Repository layout

```text
jl-wp-plugins-pack/
├── jl-wp-plugins-pack.php
├── README.md
├── CHANGELOG.md
└── includes/
    ├── class-jl-wp-plugins-pack.php
    ├── class-jl-github-powershell-drafts.php
    └── class-jl-wp-plugins-pack-ai-posts.php
```

## Install on a new WordPress site

### Git Updater

1. Install and activate Git Updater:
   - https://github.com/afragen/git-updater
2. Install this repository through Git Updater:
   - https://github.com/jasrasr/jl-wp-plugins-pack
3. Activate **JL WP Plugins Pack**.
4. Open `Tools -> JL WP Plugins Pack` for excerpt and hashtag tools.
5. Open `Tools -> JL GitHub Drafts` for GitHub script monitoring.

### Manual ZIP upload

1. Download or build `jl-wp-plugins-pack.zip`.
2. Open `Plugins -> Add New -> Upload Plugin`.
3. Upload and activate the plugin.
4. Open `Tools -> JL AI Posts` for scheduled AI post setup.

## Scheduled AI posts setup

Use this if you want the plugin to draft or publish posts automatically.

1. Create a separate WordPress user for the AI workflow.

   Recommended first setup:

   ```text
   Username: ai-author
   Role: Contributor
   ```

   A Contributor-style user can submit pending posts but cannot publish. That is the safest first version.

2. Open:

   ```text
   WordPress Admin -> Tools -> JL AI Posts
   ```

3. Add your OpenAI API key in the plugin settings page.

   This is the normal setup for most WordPress users. The key is stored in the WordPress database as part of the plugin option, not inside the plugin files. A Git Updater plugin update replaces plugin files but does not overwrite this saved WordPress option.

   Leave the API key field blank on future saves to keep the existing stored key.

   Advanced optional method: a site administrator can define the key in `wp-config.php` outside the plugin repo:

   ```php
   define('JL_WP_PLUGINS_PACK_OPENAI_API_KEY', 'replace-with-your-api-key');
   ```

   If that constant is defined, it overrides the key stored in plugin settings. This is useful for managed or locked-down sites, but it should not be required for normal users.

4. Configure **Scheduled AI Blog Posts**:
   - Enable schedule.
   - Choose Weekly unless you have a good reason to post more often.
   - Select the AI author.
   - Choose the status policy.
   - Add topic instructions.
   - Save settings.

5. Use **Generate One AI Post Now** for the first test.

6. Review the generated post before enabling auto-publishing.

### Status policy behavior

| Policy | Result |
|---|---|
| Always save as Draft | Creates a draft regardless of author role. |
| Always save as Pending Review | Creates a pending review post. |
| Role-based | Publishes only if the selected author has `publish_posts`; otherwise saves pending review. |
| Publish if allowed | Publishes only if the selected author has `publish_posts`; otherwise saves pending review. |

For an AI user with the Contributor role, use **Pending Review** or **Role-based**. For an Author role, **Role-based** can publish automatically.

### WP-Cron note

The schedule uses WP-Cron. WP-Cron runs when the site receives visits and the scheduled time has passed. If you need reliable exact timing on Hostinger, configure a host cron job to call `wp-cron.php`.

## New website install - manual upload

### SSH/Git

```bash
cd public_html/wp-content/plugins
git clone https://github.com/jasrasr/jl-wp-plugins-pack.git jl-wp-plugins-pack
```

To update:

```bash
cd public_html/wp-content/plugins/jl-wp-plugins-pack
git pull
```

## Git Updater headers

The main plugin file includes:

```php
GitHub Plugin URI: https://github.com/jasrasr/jl-wp-plugins-pack
Primary Branch: main
```

## Release workflow

1. Change the code.
2. Bump `Version:` in `jl-wp-plugins-pack.php`.
3. Update `JL_WP_PLUGINS_PACK_VERSION`.
4. Update `CHANGELOG.md`.
5. Update `README.md` when behavior changes.
6. Commit and push.

Example:

```bash
git add .
git commit -m "Add GitHub PowerShell draft generator"
git push
```

Optional tag:

```bash
git tag v1.2.0
git push origin v1.2.0
```

## Safety notes

- Back up WordPress before installing major updates.
- Test the first scan manually.
- Generated posts remain drafts.
- PowerShell code is never executed by WordPress.
- Published posts are not automatically overwritten.
- Do not commit API keys, passwords, tokens, or local config files.
- Keep this plugin in a public repo only if you are comfortable with the source being public.
- Normal users can store the API key in the plugin settings page; future plugin updates should not overwrite that saved option.
- Site administrators can optionally use the `JL_WP_PLUGINS_PACK_OPENAI_API_KEY` constant in `wp-config.php` for managed environments.
- Use a separate WordPress user for AI-generated posts.
- Start with drafts or pending review before auto-publishing.
- Test on a staging or low-risk site before production.
- Back up the database before running bulk excerpt updates.

## Suggested `.gitignore`

```gitignore
.env
.env.*
*.log
.DS_Store
Thumbs.db
node_modules/
vendor/
.codex/
```

Do not ignore `.agents/` if you use it for shared Codex/agent project instructions.

## Jason Lamb Links

- Website: https://jasonlamb.me
- GitHub Repository: https://github.com/jasrasr/jl-wp-plugins-pack
- Git Updater: https://github.com/afragen/git-updater

## New WordPress Site Implementation

1. Create or confirm the GitHub repository:
   - https://github.com/jasrasr/jl-wp-plugins-pack

2. Install Git Updater on WordPress:
   - Project: https://github.com/afragen/git-updater
   - Site: https://git-updater.com/

3. Install this Plugin:
   - WordPress Admin -> Plugins -> Add New, or use Git Updater
   - Or use SSH/Git in the appropriate WordPress folder.

4. Confirm expected install path:
   - Plugin: /wp-content/plugins/jl-wp-plugins-pack/

5. Activate:
   - Plugin: WordPress Admin -> Plugins -> JL WP Plugins Pack -> Activate

6. Future updates:
   - Edit files locally.
   - Bump the plugin version constants/header.
   - Commit and push to GitHub.
   - Update from WordPress Admin using Git Updater.
