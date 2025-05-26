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

namespace CPSIT\AdmiralCloudConnector\Resource\Rendering;

use CPSIT\AdmiralCloudConnector\Exception\InvalidAssetException;
use CPSIT\AdmiralCloudConnector\Service\AdmiralCloudService;
use CPSIT\AdmiralCloudConnector\Service\TagBuilderService;
use CPSIT\AdmiralCloudConnector\Traits\AssetFactory;
use CPSIT\AdmiralCloudConnector\Utility\PermissionUtility;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\Rendering\FileRendererInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\TagBuilder;

class AssetRenderer implements FileRendererInterface
{
    use AssetFactory;

    public function __construct(
        \CPSIT\AdmiralCloudConnector\Resource\AssetFactory $assetFactory,
        protected readonly AdmiralCloudService $admiralCloudService,
        protected readonly TagBuilderService $tagBuilderService,
    ) {
        $this->assetFactory = $assetFactory;
    }

    public function getPriority(): int
    {
        return 15;
    }

    public function canRender(FileInterface $file): bool
    {
        try {
            if (str_starts_with($file->getMimeType(), 'admiralCloud/')) {
                $asset = $this->getAsset($file->getIdentifier());

                return $asset->isImage() || $asset->isDocument() || $asset->isAudio() || $asset->isVideo();
            }
        } catch (InvalidAssetException) {
        }

        return false;
    }

    /**
     * Render for given File(Reference) HTML output
     *
     * @param int|string $width TYPO3 known format; examples: 220, 200m or 200c
     * @param int|string $height TYPO3 known format; examples: 220, 200m or 200c
     */
    public function render(
        FileInterface $file,
        $width,
        $height,
        array $options = [],
        ?TagBuilder $tag = null,
    ): string {
        if (!($file instanceof File) && is_callable([$file, 'getOriginalFile'])) {
            $originalFile = $file->getOriginalFile();
        } else {
            $originalFile = $file;
        }

        $asset = $this->getAsset($originalFile->getIdentifier());

        return match (true) {
            $asset->isImage(), $asset->isDocument() => $this->renderImageTag($file, $width, $height, $options, $tag),
            $asset->isVideo() => $this->renderVideoTag($file, $width, $height, $options),
            $asset->isAudio() => $this->renderAudioTag($file, $width, $height, $options),
            default => throw new InvalidAssetException('No rendering implemented for this asset.', 1558540658478),
        };
    }

    protected function renderVideoTag(FileInterface $file, int|string $width, int|string $height, array $options = []): string
    {
        return $this->getPlayerHtml($file, $width, $height, $options);
    }

    protected function renderAudioTag(FileInterface $file, int|string $width, int|string $height, array $options = []): string
    {
        return $this->getPlayerHtml($file, $width, $height, $options);
    }

    protected function renderImageTag(
        FileInterface $file,
        int|string $width,
        int|string $height,
        array $options = [],
        ?TagBuilder $imageTag = null,
    ): string {
        $tag = $imageTag ?: $this->getTagBuilder('img', $options);

        $tag->addAttribute(
            'src',
            $this->admiralCloudService->getImagePublicUrl($file, (int)$width, (int)$height),
        );

        if ((int)$width > 0) {
            $tag->addAttribute('width', !empty($width) ? $width : null);
        }
        if ((int)$height > 0) {
            $tag->addAttribute('height', !empty($height) ? $height : null);
        }

        // The alt-attribute is mandatory to have valid html-code, therefore add it even if it is empty
        if ($tag->hasAttribute('alt') === false) {
            $tag->addAttribute('alt', $file->getProperty('alternative'));
        }
        if ($tag->hasAttribute('title') === false) {
            $tag->addAttribute('title', $file->getProperty('title'));
        }

        return $tag->render();
    }

    protected function getPlayerHtml(FileInterface $file, int|string $width, int|string $height, array $options = []): string
    {
        if (is_callable([$file, 'getOriginalFile'])) {
            $originalFile = $file->getOriginalFile();
        } else {
            $originalFile = $file;
        }

        $fe_group = PermissionUtility::getPageFeGroup();

        if (!$fe_group && $file->getProperty('tablenames') === 'tt_content' && $file->getProperty('uid_foreign')) {
            $fe_group = PermissionUtility::getContentFeGroupFromReference($file->getProperty('uid_foreign'));
        }

        $tag = $this->getTagBuilder('iframe', $options);

        if ($fe_group) {
            $tag->addAttribute('src', $this->admiralCloudService->getPlayerPublicUrl($originalFile, $fe_group));
        } else {
            $tag->addAttribute('src', $this->admiralCloudService->getPlayerPublicUrl($originalFile));
        }

        $tag->addAttribute('allowfullscreen', true);
        $tag->forceClosingTag(true);

        if ((int)$width > 0) {
            $tag->addAttribute('width', !empty($width) ? $width : null);
        }

        if ((int)$height > 0) {
            $tag->addAttribute('height', !empty($height) ? $height : null);
        }

        if ($tag->hasAttribute('title') === false) {
            $tag->addAttribute('title', $file->getProperty('title'));
        }

        return $tag->render();
    }

    protected function getTagBuilder(string $type, array $options): TagBuilder
    {
        $tag = $this->tagBuilderService->getTagBuilder($type);

        $this->tagBuilderService->initializeAbstractTagBasedAttributes($tag, $options);

        return $tag;
    }
}
