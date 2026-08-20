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

namespace CPSIT\AdmiralCloudConnector\Command;

use CPSIT\AdmiralCloudConnector\Service\MetadataService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'admiral-cloud:update-metadata',
    description: 'Update metadata for AdmiralCloud files',
)]
final class UpdateMetadataCommand extends Command
{
    private SymfonyStyle $io;

    public function __construct(
        private readonly MetadataService $metadataService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'actionType',
            InputArgument::OPTIONAL,
            sprintf(
                'Update metadata from AdmiralCloud; available options: %s',
                $this->decorateActionTypes(),
            ),
            UpdateActionType::LastChanged->value,
        );
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $actionTypeArgument = $input->getArgument('actionType');
        $actionType = UpdateActionType::tryFrom($actionTypeArgument);

        if ($actionType === null) {
            $this->io->error(
                sprintf(
                    'Invalid action type "%s" given, please use one of the available action types.',
                    $actionTypeArgument,
                ),
            );
            $this->io->comment('Available action types: ' . $this->decorateActionTypes());

            return self::FAILURE;
        }

        match ($actionType) {
            UpdateActionType::All => $this->metadataService->updateAll(),
            UpdateActionType::LastChanged => $this->metadataService->updateLastChangedMetadata(),
        };

        $this->io->success(
            sprintf(
                'Successfully updated metadata for %s files from AdmiralCloud.',
                match ($actionType) {
                    UpdateActionType::All => 'all',
                    UpdateActionType::LastChanged => 'last changed',
                },
            ),
        );

        return self::SUCCESS;
    }

    private function decorateActionTypes(): string
    {
        return implode(
            ', ',
            array_map(
                static fn(string $value) => sprintf('<info>%s</info>', $value),
                UpdateActionType::values(),
            ),
        );
    }
}
