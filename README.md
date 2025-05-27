# TYPO3 Extension `admiral_cloud_connector`

This extension connects the AdmiralCloud with TYPO3. It adds a separate `sys_file_storage` for AdmiralCloud files.
At every place `sys_file_reference` is used, you can use AdmiralCloud files.

## Installation

```bash
composer require cpsit/admiral-cloud-connector
```

Run the following SQL to install the `sys_file_storage`:

```sql
INSERT INTO `sys_file_storage` (`pid`, `deleted`, `description`, `name`, `driver`, `configuration`, `is_default`, `is_browsable`, `is_public`, `is_writable`, `is_online`, `auto_extract_metadata`, `processingfolder`) VALUES
(0, 0, 'Automatically created during the installation of EXT:admiral_cloud_connector', 'AdmiralCloud', 'AdmiralCloud', '', 0, 1, 1, 0, 1, 1, '1:/_processed_/');
```

Now create the corresponding fileMount "AdmiralCloud" for the storage.

Add following to `config/system/additional.php`:

```php
if (is_file(__DIR__ . '/custom.php')) {
    require_once __DIR__ . '/custom.php';
}
```

Create a file `config/system/custom.php` with the following content (replace credentials with yours):

```
<?php

putenv('ADMIRALCLOUD_ACCESS_SECRET=xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx');
putenv('ADMIRALCLOUD_ACCESS_KEY=xxxxxxxxxxxxxxxxxxxxxx');
putenv('ADMIRALCLOUD_CLIENT_ID=xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx');
putenv('ADMIRALCLOUD_DISABLE_FILEUPLOAD=0');
putenv('ADMIRALCLOUD_FLAG_CONFIG_ID=0');

putenv('ADMIRALCLOUD_DISABLE_FILEUPLOAD=1');
putenv('ADMIRALCLOUD_IS_PRODUCTION=1');
putenv('ADMIRALCLOUD_IMAGE_CONFIG_ID=238');
putenv('ADMIRALCLOUD_IMAGE_PNG_CONFIG_ID=321');
putenv('ADMIRALCLOUD_VIDEO_CONFIG_ID=239');
putenv('ADMIRALCLOUD_DOCUMENT_CONFIG_ID=240');
putenv('ADMIRALCLOUD_AUDIO_CONFIG_ID=241');
putenv('ADMIRALCLOUD_FLAG_CONFIG_ID=10');
putenv('ADMIRALCLOUD_IFRAMEURL=https://t3intpoc.admiralcloud.com/');

$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['admiral_cloud_connector'] = [
    'frontend' => \TYPO3\CMS\Core\Cache\Frontend\PhpFrontend::class,
    'backend' => \TYPO3\CMS\Core\Cache\Backend\FileBackend::class,
    'groups' => [
        'all',
        'system',
    ],
    'options' => [
        'defaultLifetime' => 0,
    ],
];
```

Create a backend user with e-mail, first name, last name and security group the user has in AdmiralCloud.
The email address must be the same the user is using in AdmiralCloud. If the User is admin, the security group is
ignored but must be set to random number (e.g. 13).

## TYPO3 editor permissions

To enable editors for AdmiralCloud functions, please add at least the following permission:

### Mounts & Workspaces

* Add "AdmiralCloud" to the list of accessible FileMounts
* File operation permissions / File: check permission for [addFileViaAdmiralCloud]

### optional

Allow cropping tool for AdmiralCloud images: Check permission for `tx_admiralcloudconnector_crop` on tab
"Access Lists" / "Allowed excludefields" in "File Reference".

### Known bugs

* Using Admiral Cloud may need to increase some apache values like `post_max_size=128M` and `max_input_vars=2500`

## ToDo

* Add information about authentification / security groups to documentation
