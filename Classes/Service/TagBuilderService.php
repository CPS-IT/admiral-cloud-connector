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

namespace CPSIT\AdmiralCloudConnector\Service;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\TagBuilder;

readonly class TagBuilderService
{
    public function getTagBuilder(string $name = '', string $content = ''): TagBuilder
    {
        return GeneralUtility::makeInstance(TagBuilder::class, $name, $content);
    }

    public function initializeAbstractTagBasedAttributes(TagBuilder $tagBuilder, array $arguments): TagBuilder
    {
        if (is_array($arguments['additionalAttributes'] ?? null)) {
            $tagBuilder->addAttributes($arguments['additionalAttributes']);
        }

        if (is_array($arguments['data'] ?? null)) {
            foreach ($arguments['data'] as $dataAttributeKey => $dataAttributeValue) {
                $tagBuilder->addAttribute('data-' . $dataAttributeKey, $dataAttributeValue);
            }
        }

        $this->initializeUniversalTagAttributes($tagBuilder, $arguments);

        return $tagBuilder;
    }

    public function initializeUniversalTagAttributes(
        TagBuilder $tagBuilder,
        array $arguments,
        array $universalTagAttributes = ['class', 'dir', 'id', 'lang', 'style', 'title', 'accesskey', 'tabindex', 'onclick'],
    ): void {
        foreach ($universalTagAttributes as $attributeName) {
            if (($arguments[$attributeName] ?? '') !== '') {
                $tagBuilder->addAttribute($attributeName, $arguments[$attributeName]);
            }
        }
    }
}
