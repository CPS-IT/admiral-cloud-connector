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

namespace CPSIT\AdmiralCloudConnector\Controller\Backend;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\LinkHandling\LinkService;
use TYPO3\CMS\Core\LinkHandling\TypoLinkCodecService;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;

#[AsController]
class LinkBrowserController extends AbstractLinkBrowserController
{
    public function __construct(
        protected readonly HashService $hashService,
        protected readonly LinkService $linkService,
        protected readonly TypoLinkCodecService $typoLinkCodecService,
    ) {}

    /**
     * Initialize $this->currentLinkParts
     */
    protected function initCurrentUrl(): void
    {
        $currentLink = isset($this->parameters['currentValue']) ? trim($this->parameters['currentValue']) : '';
        /** @var array<string, string> $currentLinkParts */
        $currentLinkParts = $this->typoLinkCodecService->decode($currentLink);
        $currentLinkParts['params'] = $currentLinkParts['additionalParams'];

        unset($currentLinkParts['additionalParams']);

        if (!empty($currentLinkParts['url'])) {
            $data = $this->linkService->resolve($currentLinkParts['url']);
            $currentLinkParts['type'] = $data['type'];
            unset($data['type']);
            $currentLinkParts['url'] = $data;
        }

        $this->currentLinkParts = $currentLinkParts;

        parent::initCurrentUrl();
    }

    protected function initDocumentTemplate(): void
    {
        if (!$this->areFieldChangeFunctionsValid() && !$this->areFieldChangeFunctionsValid(true)) {
            $this->parameters['fieldChangeFunc'] = [];
        }

        unset($this->parameters['fieldChangeFunc']['alert']);

        if (($this->parameters['fieldChangeFuncType'] ?? null) === 'items') {
            $this->pageRenderer->getJavaScriptRenderer()->addJavaScriptModuleInstruction(
                JavaScriptModuleInstruction::create('@typo3/backend/form-engine-link-browser-adapter.js')
                    // @todo use a proper constructor when migrating to TypeScript
                    ->invoke('setOnFieldChangeItems', $this->parameters['fieldChangeFunc'])
            );
        }
    }

    /**
     * @see \TYPO3\CMS\Backend\Controller\LinkBrowserController::encodeTypoLink()
     */
    public function encodeTypoLink(ServerRequestInterface $request): ResponseInterface
    {
        $typoLinkParts = $request->getQueryParams();

        if (isset($typoLinkParts['params'])) {
            $typoLinkParts['additionalParams'] = $typoLinkParts['params'];
            unset($typoLinkParts['params']);
        }

        $typoLink = $this->typoLinkCodecService->encode($typoLinkParts);

        return new JsonResponse(['typoLink' => $typoLink]);
    }

    /**
     * Determines whether submitted field change functions are valid
     * and are coming from the system and not from an external abuse.
     *
     * @param bool $handleFlexformSections Whether to handle flexform sections differently
     * @return bool Whether the submitted field change functions are valid
     */
    protected function areFieldChangeFunctionsValid(bool $handleFlexformSections = false): bool
    {
        if (isset($this->parameters['fieldChangeFunc'], $this->parameters['fieldChangeFuncHash']) && is_array($this->parameters['fieldChangeFunc'])) {
            $matches = [];
            $pattern = '#\\[el]\\[(([^]-]+-[^]-]+-)(idx\\d+-)([^]]+))]#i';
            $fieldChangeFunctions = $this->parameters['fieldChangeFunc'];

            // Special handling of flexform sections:
            // Field change functions are modified in JavaScript, thus the hash is always invalid
            if ($handleFlexformSections && preg_match($pattern, (string)$this->parameters['itemName'], $matches)) {
                $originalName = $matches[1];
                $cleanedName = $matches[2] . $matches[4];
                $fieldChangeFunctions = $this->strReplaceRecursively(
                    $originalName,
                    $cleanedName,
                    $fieldChangeFunctions
                );
            }

            return hash_equals(
                $this->hashService->hmac(serialize($fieldChangeFunctions), 'backend-link-browser'),
                $this->parameters['fieldChangeFuncHash'],
            );
        }

        return false;
    }

    protected function strReplaceRecursively(string $search, string $replace, array $array): array
    {
        foreach ($array as &$item) {
            if (is_array($item)) {
                $item = $this->strReplaceRecursively($search, $replace, $item);
            } else {
                $item = str_replace($search, $replace, $item);
            }
        }

        return $array;
    }

    protected function getCurrentPageId(): int
    {
        $pageId = 0;
        $browserParameters = $this->parameters;

        if (isset($browserParameters['pid'])) {
            $pageId = $browserParameters['pid'];
        } elseif (isset($browserParameters['itemName'])) {
            // parse data[<table>][<uid>]
            if (preg_match('~data\[([^]]*)]\[([^]]*)]~', $browserParameters['itemName'], $matches) === 1) {
                $recordArray = BackendUtility::getRecord($matches[1], $matches[2]);

                if (is_array($recordArray)) {
                    $pageId = $recordArray['pid'];
                }
            }
        }

        return (int)BackendUtility::getTSCpidCached($browserParameters['table'], $browserParameters['uid'], $pageId)[0];
    }

    public function getConfiguration(): array
    {
        $tsConfig = BackendUtility::getPagesTSconfig($this->getCurrentPageId());

        return $tsConfig['TCEMAIN.']['linkHandler.']['page.']['configuration.'] ?? [];
    }
}
