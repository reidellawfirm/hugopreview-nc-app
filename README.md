# Hugo Preview for Nextcloud

**Version:** 1.0.0
**Author:** Schuyler "Rocky" Reidel, Reidel Law Firm (schuyler@reidellawfirm.com)
**License:** GPL-3.0-or-later

Hugo Preview builds Hugo static sites from folders in Nextcloud Files and displays the result inside Nextcloud. It is an independent Nextcloud app and does not require CRM Hub.

## Features

- Select one or more Hugo projects from Nextcloud folders.
- Build and preview sites without leaving Nextcloud.
- Live rebuilds while editing Markdown in Nextcloud Text.
- Handles attachments, callouts, task lists, and relative links.
- Per-user project configuration.

## Requirements

- Nextcloud 34 or 35.
- PHP 8.1 or later.
- The `hugo` executable installed and available to the Nextcloud PHP process.

## Installation

Install the signed archive into `apps/` or `custom_apps/`, then enable **Hugo Preview** in Nextcloud Apps. No CRM Hub dependency is required.

## Migration from CRM Hub 0.2.x

Earlier CRM Hub releases included a Hugo module. That module has been extracted into this app.

- Install Hugo Preview **before** upgrading CRM Hub to 0.3.0 if you want to keep using Hugo.
- CRM Hub 0.3.0 removes the Hugo module and its routes; Hugo data is not automatically migrated.
- Existing Hugo project definitions were stored in CRM Hub's `projects` application configuration key. Back up that key before upgrading if you need to preserve the project list.
- Generated preview directories were stored under the old app's `img/preview_<id>` directory. Treat those as disposable build output; rebuild them from the original Nextcloud source folders after setting up the standalone app.

## Development

```bash
npm ci --legacy-peer-deps
npm run build
```

The app ID is `hugopreview_nc_app`. The intended public repository is `https://github.com/reidellawfirm/hugopreview-nc-app`.
