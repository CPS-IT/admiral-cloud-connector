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

namespace CPSIT\AdmiralCloudConnector\Form\Element;

use CPSIT\AdmiralCloudConnector\Resource\File;
use CPSIT\AdmiralCloudConnector\Traits\AdmiralCloudStorage;
use TYPO3\CMS\Backend\Form\Element\AbstractFormElement;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Resource\FileType;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Core\Utility\StringUtility;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Core\View\ViewInterface;

class AdmiralCloudImageManipulationElement extends AbstractFormElement
{
    use AdmiralCloudStorage;

    /**
     * Default element configuration
     */
    protected static array $defaultConfig = [
        'file_field' => 'uid_local',
    ];

    /**
     * Default field information enabled for this element.
     */
    protected $defaultFieldInformation = [
        'tcaDescription' => [
            'renderType' => 'tcaDescription',
        ],
    ];

    /**
     * Default field wizards enabled for this element.
     */
    protected $defaultFieldWizard = [
        'localizationStateSelector' => [
            'renderType' => 'localizationStateSelector',
        ],
        'otherLanguageContent' => [
            'renderType' => 'otherLanguageContent',
            'after' => [
                'localizationStateSelector',
            ],
        ],
        'defaultLanguageDifferences' => [
            'renderType' => 'defaultLanguageDifferences',
            'after' => [
                'otherLanguageContent',
            ],
        ],
    ];

    protected ViewInterface $templateView;

    public function __construct(
        protected readonly ResourceFactory $resourceFactory,
        protected readonly UriBuilder $uriBuilder,
        ViewFactoryInterface $viewFactory,
    ) {
        $data = new ViewFactoryData(
            partialRootPaths: ['EXT:admiral_cloud_connector/Resources/Private/Partials/ImageManipulation/'],
            layoutRootPaths: ['EXT:admiral_cloud_connector/Resources/Private/Layouts/ImageManipulation/'],
            templatePathAndFilename: 'EXT:admiral_cloud_connector/Resources/Private/Templates/ImageManipulation/ImageManipulationElement.html',
        );

        $this->templateView = $viewFactory->create($data);
    }

    public function render(): array
    {
        $resultArray = $this->initializeResultArray();
        $parameterArray = $this->data['parameterArray'];
        $config = $this->populateConfiguration($parameterArray['fieldConf']['config']);

        $file = $this->getFile($this->data['databaseRow'], $config['file_field']);

        // Early return in case we do not find a file or it isn't an image or does not come from AdmiralCloud
        if ($file === null
            || !$file->isType(FileType::IMAGE)
            || $file->getStorage()->getUid() !== $this->getAdmiralCloudStorage()->getUid()
        ) {
            return $resultArray;
        }

        // If sys_file_reference is new, add crop information from BE session.
        // Crop information was stored in \CPSIT\AdmiralCloudConnector\Controller\Backend\BrowserController
        if ($this->data['command'] === 'new') {
            $sessionData = $this->getBackendUser()->getSessionData('admiralCloud') ?? [];
            if (!empty($sessionData['cropInformation'][$file->getUid()])) {
                $parameterArray['itemFormElValue'] = json_encode($sessionData['cropInformation'][$file->getUid()]);
                unset($sessionData['cropInformation'][$file->getUid()]);
                $this->getBackendUser()->setAndSaveSessionData('admiralCloud', $sessionData);
            }
        }

        $fieldInformationResult = $this->renderFieldInformation();
        $fieldInformationHtml = $fieldInformationResult['html'];
        $resultArray = $this->mergeChildReturnIntoExistingResult($resultArray, $fieldInformationResult, false);

        $fieldControlResult = $this->renderFieldControl();
        $fieldControlHtml = $fieldControlResult['html'];
        $resultArray = $this->mergeChildReturnIntoExistingResult($resultArray, $fieldControlResult, false);

        $fieldWizardResult = $this->renderFieldWizard();
        $fieldWizardHtml = $fieldWizardResult['html'];
        $resultArray = $this->mergeChildReturnIntoExistingResult($resultArray, $fieldWizardResult, false);
        $cropParams = [
            'mediaContainerId' => $file->getIdentifier(),
            'embedLink' => $file->getTxAdmiralCloudConnectorLinkhash(),
            'irreObject' => StringUtility::getUniqueId('admiralCloud-image-manipulation-element-'),
        ];
        $arguments = [
            'fieldInformation' => $fieldInformationHtml,
            'fieldControl' => $fieldControlHtml,
            'fieldWizard' => $fieldWizardHtml,
            'isAllowedFileExtension' => in_array(
                strtolower($file->getExtension()),
                GeneralUtility::trimExplode(',', strtolower((string)$config['allowedExtensions']), true),
                true,
            ),
            'image' => $file,
            'formEngine' => [
                'field' => [
                    'value' => $parameterArray['itemFormElValue'],
                    'name' => $parameterArray['itemFormElName'],
                    'id' => StringUtility::getUniqueId('admiralCloud-image-manipulation-element-'),
                ],
                'validation' => '[]',
            ],
            'cropUrl' => $this->uriBuilder->buildUriFromRoute('admiral_cloud_browser_crop', $cropParams),
        ];
        $this->templateView->assignMultiple($arguments);
        $resultArray['html'] = $this->templateView->render();

        return $resultArray;
    }

    /**
     * @param array<string, mixed> $row
     */
    protected function getFile(array $row, string $fieldName): ?File
    {
        $file = null;
        $fileUid = !empty($row[$fieldName]) ? $row[$fieldName] : null;

        if (is_array($fileUid) && isset($fileUid[0]['uid'])) {
            $fileUid = $fileUid[0]['uid'];
        }

        if (MathUtility::canBeInterpretedAsInteger($fileUid)) {
            try {
                /** @var File $file */
                $file = $this->resourceFactory->getFileObject((int)$fileUid);
            } catch (FileDoesNotExistException|\InvalidArgumentException) {
            }
        }

        return $file;
    }

    /**
     * @param array $baseConfiguration
     * @return array
     */
    protected function populateConfiguration(array $baseConfiguration): array
    {
        $defaultConfig = self::$defaultConfig;

        $config = array_replace_recursive($defaultConfig, $baseConfiguration);

        // By default we allow all image extensions that can be handled by the GFX functionality
        if (($config['allowedExtensions'] ?? null) === null) {
            $config['allowedExtensions'] = implode(
                ', ',
                GeneralUtility::trimExplode(',', $GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext'], true)
            );
        }

        return $config;
    }
}
