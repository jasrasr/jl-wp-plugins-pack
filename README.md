# JL WP Plugins Pack

Custom WordPress content utilities for Jason Lamb sites.

- Website: https://jasonlamb.me
- GitHub repository for this plugin: https://github.com/jasrasr/jl-wp-plugins-pack
- Git Updater project: https://github.com/afragen/git-updater
- Git Updater website: https://git-updater.com/

## What this plugin does

JL WP Plugins Pack currently provides:

1. **Bulk missing excerpt generation**
   - Adds `Tools -> JL WP Plugins Pack` in WordPress admin.
   - Finds published posts/pages with empty manual excerpts.
   - Generates excerpts from existing content.
   - Includes dry-run mode and batch size controls.

2. **Automatic excerpt generation**
   - Runs when a normal blog post is saved.
   - Creates a 35-word excerpt when the excerpt field is empty.
   - Does not overwrite manually written excerpts.

3. **Hashtag linking**
   - Turns hashtags like `#PowerShell` into public links.
   - Links to the matching WordPress tag archive when available.
   - Falls back to site search if no matching tag exists yet.
   - Appends hashtag words as real WordPress tags on post save.
   - Preserves existing manual tags.
   - Avoids linking inside existing links, code blocks, preformatted blocks, scripts, styles, and HTML comments.

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
└── includes/
    ├── class-jl-wp-plugins-pack.php
    └── class-jl-wp-plugins-pack-ai-posts.php
```

## Required WordPress/Git Updater header

The main plugin file includes this header so Git Updater can identify the GitHub repository:

```php
GitHub Plugin URI: https://github.com/jasrasr/jl-wp-plugins-pack
Primary Branch: main
```

Git Updater expects a `GitHub Plugin URI` or `GitHub Theme URI` style header that points to the owner/repository URL.

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

Use this for the first install on a new site.

1. Download the latest plugin ZIP from GitHub releases or from your local build.
2. In WordPress admin, go to:

```text
Plugins -> Add New -> Upload Plugin
```

3. Upload `jl-wp-plugins-pack.zip`.
4. Activate **JL WP Plugins Pack**.
5. Go to:

```text
Tools -> JL WP Plugins Pack
```

6. Run the excerpt tool in **Dry Run** mode first.
7. If the preview looks good, uncheck Dry Run and process a small batch.

## New website install - SSH/Git method

Use this if the host gives you SSH access.

```bash
cd public_html/wp-content/plugins
git clone https://github.com/jasrasr/jl-wp-plugins-pack.git jl-wp-plugins-pack
```

Then activate the plugin from WordPress admin.

To update later through SSH:

```bash
cd public_html/wp-content/plugins/jl-wp-plugins-pack
git pull
```

## New website install - Git Updater method

1. Install Git Updater from the official project:

```text
https://github.com/afragen/git-updater
```

2. Activate Git Updater.
3. In WordPress admin, use Git Updater's install screen to install this plugin from:

```text
https://github.com/jasrasr/jl-wp-plugins-pack
```

4. Confirm the installed folder is:

```text
wp-content/plugins/jl-wp-plugins-pack
```

5. Activate **JL WP Plugins Pack**.
6. Future plugin updates should appear in the normal WordPress updates flow after you bump the plugin version and push changes to GitHub.

## Recommended GitHub workflow

1. Make changes locally.
2. Bump the `Version:` value in `jl-wp-plugins-pack.php`.
3. Update `JL_WP_PLUGINS_PACK_VERSION` in `jl-wp-plugins-pack.php`.
4. Update `CHANGELOG.md`.
5. Update this README if behavior changed.
6. Commit and push:

```bash
git add .
git commit -m "Update JL WP Plugins Pack"
git push
```

7. Optionally tag the release:

```bash
git tag v1.2.0
git push origin v1.2.0
```

## Safety notes

- Keep this plugin in a public repo only if you are comfortable with the source being public.
- Do not commit API keys, passwords, tokens, or local config files.
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
