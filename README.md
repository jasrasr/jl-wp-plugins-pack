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

## Repository layout

```text
jl-wp-plugins-pack/
├── jl-wp-plugins-pack.php
├── README.md
├── CHANGELOG.md
└── includes/
    ├── class-jl-wp-plugins-pack.php
    └── class-jl-github-powershell-drafts.php
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
- Do not commit API keys, passwords, or tokens.
