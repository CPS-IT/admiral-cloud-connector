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

namespace CPSIT\AdmiralCloudConnector\ViewHelpers;

use CPSIT\AdmiralCloudConnector\Resource\Rendering\AssetRenderer;
use CPSIT\AdmiralCloudConnector\Traits\AdmiralCloudStorage;
use CPSIT\AdmiralCloudConnector\Utility\ImageUtility;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Extbase\Service\ImageService;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractTagBasedViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\Exception;

final class ImageViewHelper extends AbstractTagBasedViewHelper
{
    use AdmiralCloudStorage;

    private static bool $delegateArgumentsProcessed = false;

    protected $tagName = 'img';

    public function __construct(
        private readonly AssetRenderer $assetRenderer,
        private readonly \TYPO3\CMS\Fluid\ViewHelpers\ImageViewHelper $delegate,
        private readonly ImageService $imageService,
        StorageRepository $storageRepository,
    ) {
        parent::__construct();

        $this->storageRepository = $storageRepository;
    }

    public function initializeArguments(): void
    {
        parent::initializeArguments();

        $this->registerArgument('txAdmiralCloudCrop', 'string', 'AdmiralCloud crop information', false, '');
        $this->registerArgument('src', 'string', 'a path to a file, a combined FAL identifier or an uid (int). If $treatIdAsReference is set, the integer is considered the uid of the sys_file_reference record. If you already got a FAL object, consider using the $image parameter instead', false, '');
        $this->registerArgument('treatIdAsReference', 'bool', 'given src argument is a sys_file_reference record', false, false);
        $this->registerArgument('image', 'object', 'a FAL object (\\TYPO3\\CMS\\Core\\Resource\\File or \\TYPO3\\CMS\\Core\\Resource\\FileReference)');
        $this->registerArgument('width', 'string', 'width of the image. This can be a numeric value representing the fixed width of the image in pixels. But you can also perform simple calculations by adding "m" or "c" to the value. See imgResource.width for possible options.');
        $this->registerArgument('height', 'string', 'height of the image. This can be a numeric value representing the fixed height of the image in pixels. But you can also perform simple calculations by adding "m" or "c" to the value. See imgResource.width for possible options.');
        $this->registerArgument('maxWidth', 'int', 'maximum width of the image');
        $this->registerArgument('maxHeight', 'int', 'maximum height of the image');
        $this->registerArgument('showFocus', 'bool', 'Render focus coordinates', false, false);
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

    /**
     * Resizes a given image (if required) and renders the respective img tag.
     */
    public function render(): string
    {
        $src = (string)$this->arguments['src'];
        $imageArgument = $this->arguments['image'];
        $treatIdAsReference = (bool)$this->arguments['treatIdAsReference'];

        if (($src === '' && $imageArgument === null) || ($src !== '' && $imageArgument !== null)) {
            throw new Exception('You must either specify a string src or a File object.', 1382284106);
        }

        $image = $this->imageService->getImage($src, $imageArgument, $treatIdAsReference);

        // Resolve original file of file references
        if (!($image instanceof File) && is_callable([$image, 'getOriginalFile'])) {
            $originalFile = $image->getOriginalFile();
        } else {
            $originalFile = $image;
        }

        // Render admiral cloud image
        if ($originalFile->getStorage()->getUid() === $this->getAdmiralCloudStorage()->getUid()) {
            $crop = $this->arguments['txAdmiralCloudCrop'];

            if ($crop) {
                $originalFile->setTxAdmiralCloudConnectorCrop($this->arguments['txAdmiralCloudCrop']);
            } elseif ($image instanceof FileReference) {
                $crop = $image->getProperty('tx_admiralcloudconnector_crop');
            } else {
                $crop = $originalFile->getTxAdmiralCloudConnectorCrop();
            }

            // Inject focus point values as data attributes
            if ($this->arguments['showFocus'] && $crop !== '') {
                $cropConfiguration = json_decode($crop, true);

                if (isset($cropConfiguration['focusPoint']['x'], $cropConfiguration['focusPoint']['y'])) {
                    $this->tag->addAttribute('data-focus-x', $cropConfiguration['focusPoint']['x']);
                    $this->tag->addAttribute('data-focus-y', $cropConfiguration['focusPoint']['y']);
                }
            }

            $dimensions = ImageUtility::calculateDimensions(
                $image,
                $this->arguments['width'],
                $this->arguments['height'],
                $this->arguments['maxWidth'],
                $this->arguments['maxHeight'],
            );

            return $this->assetRenderer->render($image, $dimensions->width, $dimensions->height, [], $this->tag);
        }

        // Delegate rendering to parent view helper
        return $this->renderingContext->getViewHelperInvoker()->invoke(
            $this->delegate,
            $this->arguments,
            $this->renderingContext,
        );
    }
}
