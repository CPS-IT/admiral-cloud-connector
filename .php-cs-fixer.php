<?php

declare(strict_types=1);

$config = \TYPO3\CodingStandards\CsFixerConfig::create();
$config->setParallelConfig(\PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect());
$config->setHeader('This file is part of the TYPO3 CMS extension "admiral_cloud_connector".');
$config->getFinder()
    ->in(__DIR__)
;

return $config;
