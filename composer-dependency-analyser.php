<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "admiral_cloud_connector".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

$configuration = new Configuration();
$configuration
    ->ignoreErrorsOnPackages(
        [
            'typo3/cms-filemetadata',
            'typo3/cms-redirects',
        ],
        [ErrorType::UNUSED_DEPENDENCY],
    )
    ->ignoreErrorsOnPackages(
        [
            'typo3/cms-rte-ckeditor',
        ],
        [ErrorType::DEV_DEPENDENCY_IN_PROD],
    )
;

return $configuration;
