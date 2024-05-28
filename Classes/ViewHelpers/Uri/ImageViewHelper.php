<?php
namespace CPSIT\AdmiralCloudConnector\ViewHelpers\Uri;

/*                                                                        *
 * This script is part of the TYPO3 project - inspiring people to share!  *
 *                                                                        *
 * TYPO3 is free software; you can redistribute it and/or modify it under *
 * the terms of the GNU General Public License version 2 as published by  *
 * the Free Software Foundation.                                          *
 *                                                                        *
 * This script is distributed in the hope that it will be useful, but     *
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHAN-    *
 * TABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General      *
 * Public License for more details.                                       *
 *                                                                        */

use CPSIT\AdmiralCloudConnector\Resource\Rendering\AssetRenderer;
use CPSIT\AdmiralCloudConnector\Service\AdmiralCloudService;
use CPSIT\AdmiralCloudConnector\Utility\ImageUtility;
use Psr\Http\Message\RequestInterface;
use TYPO3\CMS\Core\Imaging\ImageManipulation\CropVariantCollection;
use TYPO3\CMS\Core\Resource\Exception\ResourceDoesNotExistException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Service\ImageService;
use TYPO3\CMS\Fluid\Core\Rendering\RenderingContext;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\Exception;
use TYPO3Fluid\Fluid\Core\ViewHelper\Traits\CompileWithRenderStatic;

/**
 * Resizes a given image (if required) and returns its relative path.
 *
 * Examples
 * ========
 *
 * Default
 * -------
 *
 * ::
 *
 *    <f:uri.image src="EXT:myext/Resources/Public/typo3_logo.png" />
 *
 * Results in the following output within TYPO3 frontend:
 *
 * ``typo3conf/ext/myext/Resources/Public/typo3_logo.png``
 *
 * and the following output inside TYPO3 backend:
 *
 * ``../typo3conf/ext/myext/Resources/Public/typo3_logo.png``
 *
 * Image Object
 * ------------
 *
 * ::
 *
 *    <f:uri.image image="{imageObject}" />
 *
 * Results in the following output within TYPO3 frontend:
 *
 * ``fileadmin/images/image.png``
 *
 * and the following output inside TYPO3 backend:
 *
 * ``fileadmin/images/image.png``
 *
 * Inline notation
 * ---------------
 *
 * ::
 *
 *    {f:uri.image(src: 'EXT:myext/Resources/Public/typo3_logo.png', minWidth: 30, maxWidth: 40)}
 *
 * ``typo3temp/assets/images/[b4c0e7ed5c].png``
 *
 * Depending on your TYPO3s encryption key.
 *
 * Non existing image
 * ------------------
 *
 * ::
 *
 *    <f:uri.image src="NonExistingImage.png" />
 *
 * ``Could not get image resource for "NonExistingImage.png".``
 */
class ImageViewHelper extends AbstractViewHelper
{
    use CompileWithRenderStatic;

    /**
     * Initialize arguments
     */
    public function initializeArguments()
    {
        $this->registerArgument('txAdmiralCloudCrop', 'string', 'AdmiralCloud crop information', false, '');

        $this->registerArgument('src', 'string', 'src', false, '');
        $this->registerArgument('treatIdAsReference', 'bool', 'given src argument is a sys_file_reference record', false, false);
        $this->registerArgument('image', 'object', 'image');
        $this->registerArgument('crop', 'string|bool|array', 'overrule cropping of image (setting to FALSE disables the cropping set in FileReference)');
        $this->registerArgument('cropVariant', 'string', 'select a cropping variant, in case multiple croppings have been specified or stored in FileReference', false, 'default');
        $this->registerArgument('fileExtension', 'string', 'Custom file extension to use');

        $this->registerArgument('width', 'string', 'width of the image. This can be a numeric value representing the fixed width of the image in pixels. But you can also perform simple calculations by adding "m" or "c" to the value. See imgResource.width for possible options.');
        $this->registerArgument('height', 'string', 'height of the image. This can be a numeric value representing the fixed height of the image in pixels. But you can also perform simple calculations by adding "m" or "c" to the value. See imgResource.width for possible options.');
        $this->registerArgument('minWidth', 'int', 'minimum width of the image');
        $this->registerArgument('minHeight', 'int', 'minimum height of the image');
        $this->registerArgument('maxWidth', 'int', 'maximum width of the image');
        $this->registerArgument('maxHeight', 'int', 'maximum height of the image');
        $this->registerArgument('absolute', 'bool', 'Force absolute URL', false, false);
    }

    /**
     * Resizes the image (if required) and returns its path. If the image was not resized, the path will be equal to $src
     *
     * @param array $arguments
     * @param \Closure $renderChildrenClosure
     * @param RenderingContextInterface $renderingContext
     * @return string
     * @throws Exception
     */
    public static function renderStatic(array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext): string
    {
        $src = $arguments['src'];
        $imageArgument = $arguments['image'];
        $treatIdAsReference = $arguments['treatIdAsReference'];

        if(version_compare(\TYPO3\CMS\Core\Utility\VersionNumberUtility::getCurrentTypo3Version(), '10.4.0', '<')){
            if (($src === null && $imageArgument === null) || ($src !== null && $imageArgument !== null)) {
                throw new Exception('You must either specify a string src or a File object.', 1460976233);
            }
        } else {
            if (($src === '' && $imageArgument === null) || ($src !== '' && $imageArgument !== null)) {
                throw new Exception('You must either specify a string src or a File object.', 1460976233);
            }
        }

        $imageService = self::getImageService();
        $image = $imageService->getImage($src, $imageArgument, $treatIdAsReference);

        if (!($image instanceof File) && is_callable([$image, 'getOriginalFile'])) {
            $originalFile = $image->getOriginalFile();
        } else {
            $originalFile = $image;
        }

        if ($originalFile->getType() === File::FILETYPE_IMAGE
            && str_starts_with($originalFile->getMimeType(), 'admiralCloud/')) {
            $crop = $arguments['txAdmiralCloudCrop'];

            if ($crop) {
                $originalFile->setTxAdmiralCloudConnectorCrop($arguments['txAdmiralCloudCrop']);
            }

            $dimensions = ImageUtility::calculateDimensions(
                $image,
                $arguments['width'],
                $arguments['height'],
                $arguments['maxWidth'],
                $arguments['maxHeight']
            );

            /** @var AdmiralCloudService $admiralCloudService */
            $admiralCloudService = GeneralUtility::makeInstance(AdmiralCloudService::class);
            return $admiralCloudService->getImagePublicUrl($image, $dimensions->width, $dimensions->height);
        }

        //parent::renderStatic($arguments, $renderChildrenClosure, $renderingContext);
        $src = (string)$arguments['src'];
        $image = $arguments['image'];
        $treatIdAsReference = (bool)$arguments['treatIdAsReference'];
        $cropString = $arguments['crop'];
        $absolute = $arguments['absolute'];

        if (($src === '' && $image === null) || ($src !== '' && $image !== null)) {
            throw new Exception(self::getExceptionMessage('You must either specify a string src or a File object.', $renderingContext), 1460976233);
        }

        if ((string)$arguments['fileExtension'] && !GeneralUtility::inList($GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext'], (string)$arguments['fileExtension'])) {
            throw new Exception(
                self::getExceptionMessage(
                    'The extension ' . $arguments['fileExtension'] . ' is not specified in $GLOBALS[\'TYPO3_CONF_VARS\'][\'GFX\'][\'imagefile_ext\']'
                    . ' as a valid image file extension and can not be processed.',
                    $renderingContext
                ),
                1618992262
            );
        }

        try {
            $imageService = self::getImageService();
            $image = $imageService->getImage($src, $image, $treatIdAsReference);

            if ($cropString === null && $image->hasProperty('crop') && $image->getProperty('crop')) {
                $cropString = $image->getProperty('crop');
            }

            // CropVariantCollection needs a string, but this VH could also receive an array
            if (is_array($cropString)) {
                $cropString = json_encode($cropString);
            }

            $cropVariantCollection = CropVariantCollection::create((string)$cropString);
            $cropVariant = $arguments['cropVariant'] ?: 'default';
            $cropArea = $cropVariantCollection->getCropArea($cropVariant);
            $processingInstructions = [
                'width' => $arguments['width'],
                'height' => $arguments['height'],
                'minWidth' => $arguments['minWidth'],
                'minHeight' => $arguments['minHeight'],
                'maxWidth' => $arguments['maxWidth'],
                'maxHeight' => $arguments['maxHeight'],
                'crop' => $cropArea->isEmpty() ? null : $cropArea->makeAbsoluteBasedOnFile($image),
            ];
            if (!empty($arguments['fileExtension'])) {
                $processingInstructions['fileExtension'] = $arguments['fileExtension'];
            }

            $processedImage = $imageService->applyProcessingInstructions($image, $processingInstructions);
            return $imageService->getImageUri($processedImage, $absolute);
        } catch (ResourceDoesNotExistException $e) {
            // thrown if file does not exist
            throw new Exception(self::getExceptionMessage($e->getMessage(), $renderingContext), 1509741907, $e);
        } catch (\UnexpectedValueException $e) {
            // thrown if a file has been replaced with a folder
            throw new Exception(self::getExceptionMessage($e->getMessage(), $renderingContext), 1509741908, $e);
        } catch (\InvalidArgumentException $e) {
            // thrown if file storage does not exist
            throw new Exception(self::getExceptionMessage($e->getMessage(), $renderingContext), 1509741910, $e);
        }
    }

    protected static function getExceptionMessage(string $detailedMessage, RenderingContextInterface $renderingContext): string
    {
        /** @var RenderingContext $renderingContext */
        $request = $renderingContext->getRequest();
        if ($request instanceof RequestInterface) {
            $currentContentObject = $request->getAttribute('currentContentObject');
            if ($currentContentObject instanceof ContentObjectRenderer) {
                return sprintf('Unable to render image uri in "%s": %s', $currentContentObject->currentRecord, $detailedMessage);
            }
        }
        return sprintf('Unable to render image uri: %s', $detailedMessage);
    }

    /**
     * Return an instance of ImageService using object manager
     *
     * @return ImageService
     */
    protected static function getImageService(): ImageService
    {
        return GeneralUtility::makeInstance(ImageService::class);
    }
}
