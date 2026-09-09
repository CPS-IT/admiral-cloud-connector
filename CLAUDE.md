# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A TYPO3 CMS extension (`admiral_cloud_connector`, PHP namespace `CPSIT\AdmiralCloudConnector`) that integrates
the AdmiralCloud DAM as a FAL (File Abstraction Layer) storage driver. It lets editors reference AdmiralCloud
assets anywhere `sys_file_reference` is used, backed by a dedicated `sys_file_storage` with driver key
`AdmiralCloud`. Currently targets TYPO3 v14.3 and PHP 8.2+ only.

## Commands

All commands run through Composer scripts (no Makefile/npm):

```bash
composer lint              # composer.json + editorconfig + PHP-CS-Fixer, all in dry-run
composer fix                # auto-fix composer.json normalization, editorconfig, PHP-CS-Fixer
composer sca:php            # PHPStan analysis (level 8, config in phpstan.neon)
composer analyze:dependencies # shipmonk/composer-dependency-analyser (unused/shadow deps)
composer migration:rector   # run Rector (TYPO3 upgrade rules); add `-- --dry-run` to preview only
composer docs:build         # render ReST docs via Docker (ghcr.io/typo3-documentation/render-guides)
```

Run a single tool directly for faster iteration, e.g.:
```bash
vendor/bin/phpstan analyse -c phpstan.neon Classes/Resource/AdmiralCloudDriver.php
vendor/bin/php-cs-fixer fix Classes/Resource/AdmiralCloudDriver.php
vendor/bin/rector process Classes/Resource/AdmiralCloudDriver.php --dry-run
```

**There is no automated test suite in this repository** (no PHPUnit config, no `Tests/` directory). Verifying
changes means: PHPStan level 8 must pass, Rector migration rules must be clean, and CGL (coding guidelines
linting) must pass — this mirrors exactly what `.github/workflows/cgl.yaml` runs on every push/PR to `master`.

Local dev environment is DDEV (`.ddev/config.yaml`, TYPO3 project type, PHP 8.3, MariaDB, nginx-fpm) — start
with `ddev start`.

## Architecture

### FAL driver integration

`Resource/AdmiralCloudDriver.php` implements TYPO3's `DriverInterface` for the `AdmiralCloud` storage driver
key. It has **no real filesystem** underneath — write operations (`createFolder`, `addFile`, `renameFile`,
`copyFileWithinStorage`, etc.) all throw `NotImplementedException`, because AdmiralCloud is a read-only CDN
source from TYPO3's point of view. File identifiers are purely numeric AdmiralCloud asset IDs; there are no
real folders (only one virtual root folder).

Three core TYPO3 resource classes are **XClassed** in `ext_localconf.php` to special-case AdmiralCloud-backed
files while remaining drop-in compatible with vanilla FAL/`sys_file_reference` everywhere else:
- `Resource/File.php` → xclasses `TYPO3\CMS\Core\Resource\File`
- `Resource/FileReference.php` → xclasses `TYPO3\CMS\Core\Resource\FileReference`
- `Resource/ProcessedFile.php` → xclasses `TYPO3\CMS\Core\Resource\ProcessedFile`

`Resource/Asset.php` (+ `Resource/AssetFactory.php`) is the domain model wrapping an AdmiralCloud identifier: it
resolves asset type (`image`/`video`/`document`/`audio`) from mime type, fetches metadata via
`AdmiralCloudService`, resolves public/thumbnail URLs, and downloads local thumbnails for processing. Two
traits wire shared plumbing into driver/asset classes: `Traits/AssetFactory` (get-or-create an `Asset` by
identifier) and `Traits/AdmiralCloudStorage` (locate the one `ResourceStorage` using the `AdmiralCloud` driver,
plus indexer/repository accessors).

`Resource/Index/Extractor.php` implements core's `ExtractorInterface`, restricted to the `AdmiralCloud` driver
(`getDriverRestrictions()`), and pulls metadata (title, description, alternative, copyright, keywords,
height/width for images/documents) from the `Asset` into `sys_file_metadata` during TYPO3's file indexing.

### API / auth layer

`Api/AdmiralCloudApi.php` + `Api/AdmiralCloudApiFactory.php` talk to the AdmiralCloud REST API over Guzzle.
`Api/Oauth/Credentials.php` and `Api/Signature/AdmiralCloudSignature.php` handle request signing. Credentials
and per-environment config (API/auth/image/CDN URLs, per-asset-type player "config IDs", metadata field
overrides, production vs. dev endpoints) all come from environment variables (`ADMIRALCLOUD_*`), centralized in
`Utility/ConfigurationUtility.php` — this is the single place that knows how to build every AdmiralCloud URL and
read every env-driven setting. Environment variables are expected to be set via
`config/system/custom.php` (see README for the full list, e.g. `ADMIRALCLOUD_ACCESS_KEY`,
`ADMIRALCLOUD_CLIENT_ID`, `ADMIRALCLOUD_IS_PRODUCTION`).

`Service/AdmiralCloudService.php` sits between `Asset`/the driver and the API layer, resolving public URLs per
asset type, thumbnails, and media info lookups. `Service/MetadataService.php` handles bulk-syncing metadata
from AdmiralCloud back into TYPO3 (`updateAll()` / `updateLastChangedMetadata()`), invoked by the
`admiral-cloud:update-metadata` CLI command (`Command/UpdateMetadataCommand.php`, action type via
`Command/UpdateActionType.php` enum). This command **replaced** the old Scheduler-task-based approach
(`Classes/Task/*`, deleted) — metadata sync is now a console command, runnable via cron/CLI instead of the
TYPO3 Scheduler.

### Backend integration points

- `EventListener/FilesControlListener.php` (+ `EventListener/AdmiralCloudFileControl.php` enum) — listens to
  core's `CustomFileSelectorsEvent` to inject AdmiralCloud "browse"/"upload" action buttons into file reference
  fields. Replaced the old `Backend/FilesControlContainer.php` FormEngine `nodeRegistry` override, removed in
  the v14 migration.
- `EventListener/FileIndexListener.php` — listens to `AfterFileUpdatedInIndexEvent` and propagates
  extension-managed columns (`tx_admiralcloudconnector_crop`, `tx_admiralcloudconnector_linkhash`) from the
  XClassed `File` object back into `sys_file` plus the reference index.
- `Backend/AdmiralCloudConnectorLinkHandler.php` — registers a TYPO3 link handler so editors can pick
  AdmiralCloud assets from the link browser (RTE, TypoLink fields).
- `Backend/ToolbarItems/AdmiralCloudToolbarItem.php` + `Controller/Backend/ToolbarController.php` — backend
  toolbar dropdown menu.
- `Controller/Backend/BrowserController.php` — backend AJAX endpoints backing the AdmiralCloud file browser
  iframe/modal.
- `Form/Element/AdmiralCloudImageManipulationElement.php` — custom FormEngine element
  (`admiralCloudImageManipulation`) for image manipulation/cropping on AdmiralCloud images, replacing TYPO3's
  default crop UI for this driver.
- `EventListener/InstallListener.php` — reacts to extension install/setup lifecycle events.

### Frontend delivery

- `Http/Middleware/AdmiralCloudMiddleware.php` (registered in `Configuration/RequestMiddlewares.php`) —
  PSR-15 middleware, likely handling direct file delivery/download requests for AdmiralCloud assets.
- `Http/Middleware/ReadableLinkResolver.php` — resolves human-readable/redirect links to AdmiralCloud assets.
- `Resource/Rendering/AssetRenderer.php` — registered against TYPO3's `RendererRegistry` in `ext_localconf.php`
  to render `<img>`/`<video>`/etc. tags for AdmiralCloud files, analogous to core's file renderers.
- `ViewHelpers/ImageViewHelper.php` and `ViewHelpers/Uri/ImageViewHelper.php` — Fluid ViewHelpers overriding
  image rendering/URI generation so templates using core's `f:image`/`f:uri.image` transparently work with
  AdmiralCloud-backed files.

### Templates

Fluid templates live under `Resources/Private/{Layouts,Templates}/...` and use the `.fluid.html` extension
(the older `.html` variants are being removed as part of the v14 migration — don't reintroduce plain `.html`
templates).

### Exceptions

All extension-specific exceptions live in `Classes/Exception/` and extend a common base
(`AdmiralCloudConnectorException`); driver methods that are structurally unsupported (folder/file mutation on
what is really just a CDN reference) throw `NotImplementedException` with a unique numeric error code per call
site — follow that convention (unique code, descriptive message, `sprintf('Method %s::%s() is not implemented', self::class, __METHOD__)`-style) when adding new unsupported driver methods.

## Coding conventions

- Strict types everywhere (`declare(strict_types=1);`), PHP 8.2+ features expected (enums, readonly classes,
  first-class match expressions, `never` return type for throw-only methods).
- Constructor property promotion + constructor injection (autowired via `Configuration/Services.yaml`, which
  autoconfigures/autowires everything under `Classes/*`); avoid `@inject`-annotation-style injection.
- PHPStan runs at level 8 against `Classes/` and `Configuration/` — keep return/param types precise; use
  `/* @phpstan-ignore ... */` comments sparingly and only where TYPO3 core interfaces force an imprecise type.
  Rector migration rules (`rector.php`) also cross-check `Classes/`, `Configuration/`, `ext_*.php` for TYPO3
  14-compatible code — run `composer migration:rector -- --dry-run` after any core-API-facing change.
