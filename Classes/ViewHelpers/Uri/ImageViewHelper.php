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

namespace CPSIT\AdmiralCloudConnector\ViewHelpers\Uri;

use CPSIT\AdmiralCloudConnector\Service\AdmiralCloudService;
use CPSIT\AdmiralCloudConnector\Utility\ImageUtility;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileType;
use TYPO3\CMS\Extbase\Service\ImageService;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\Exception;

final class ImageViewHelper extends AbstractViewHelper
{
    private static bool $delegateArgumentsProcessed = false;

    public function __construct(
        private readonly AdmiralCloudService $admiralCloudService,
        private readonly \TYPO3\CMS\Fluid\ViewHelpers\Uri\ImageViewHelper $delegate,
        private readonly ImageService $imageService,
    ) {}

    public function initializeArguments(): void
    {
        $this->registerArgument('txAdmiralCloudCrop', 'string', 'AdmiralCloud crop information', false, '');
        $this->registerArgument('src', 'string', 'src', false, '');
        $this->registerArgument('treatIdAsReference', 'bool', 'given src argument is a sys_file_reference record', false, false);
        $this->registerArgument('image', 'object', 'image');
        $this->registerArgument('width', 'string', 'width of the image. This can be a numeric value representing the fixed width of the image in pixels. But you can also perform simple calculations by adding "m" or "c" to the value. See imgResource.width for possible options.');
        $this->registerArgument('height', 'string', 'height of the image. This can be a numeric value representing the fixed height of the image in pixels. But you can also perform simple calculations by adding "m" or "c" to the value. See imgResource.width for possible options.');
        $this->registerArgument('maxWidth', 'int', 'maximum width of the image');
        $this->registerArgument('maxHeight', 'int', 'maximum height of the image');
    }

    public function prepareArguments(): array
    {
        // Prepare arguments from delegate only once, because final argument definitions are cached anyways
        if (!self::$delegateArgumentsProcessed) {
            foreach ($this->delegate->prepareArguments() as $name => $argumentDefinition) {
                if (!isset($this->argumentDefinitions[$name])) {
                    $this->argumentDefinitions[$name] = $argumentDefinition;
                }
            }

            self::$delegateArgumentsProcessed = true;
        }

        return parent::prepareArguments();
    }

    public function render(): string
    {
        $src = (string)$this->arguments['src'];
        $imageArgument = $this->arguments['image'];
        $treatIdAsReference = (bool)$this->arguments['treatIdAsReference'];

        if (($src === '' && $imageArgument === null) || ($src !== '' && $imageArgument !== null)) {
            throw new Exception('You must either specify a string src or a File object.', 1460976233);
        }

        $image = $this->imageService->getImage($src, $imageArgument, $treatIdAsReference);

        if (!($image instanceof File) && is_callable([$image, 'getOriginalFile'])) {
            $originalFile = $image->getOriginalFile();
        } else {
            $originalFile = $image;
        }

        // Render admiral cloud image uri
        if ($originalFile instanceof \CPSIT\AdmiralCloudConnector\Resource\File &&
            $originalFile->getType() === FileType::IMAGE->value &&
            str_starts_with($originalFile->getMimeType(), 'admiralCloud/')
        ) {
            $crop = $this->arguments['txAdmiralCloudCrop'];

            if ($crop) {
                $originalFile->setTxAdmiralCloudConnectorCrop($this->arguments['txAdmiralCloudCrop']);
            }

            $dimensions = ImageUtility::calculateDimensions(
                $image,
                $this->arguments['width'],
                $this->arguments['height'],
                $this->arguments['maxWidth'],
                $this->arguments['maxHeight'],
            );

            return $this->admiralCloudService->getImagePublicUrl($image, $dimensions->width, $dimensions->height);
        }

        // Delegate rendering to parent view helper
        return $this->renderingContext->getViewHelperInvoker()->invoke(
            $this->delegate,
            $this->arguments,
            $this->renderingContext,
        );
    }
}
