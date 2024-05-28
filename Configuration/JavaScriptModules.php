<?php
return [
    'dependencies' => [
        'core',
        'backend',
    ],
    'tags' => [
        'backend.form',
    ],
    'imports' => [
        '@cpsit/admiral-cloud-connector/' => 'EXT:admiral_cloud_connector/Resources/Public/JavaScript/',
        '@cpsit/admiral-cloud-connector-backend/' => 'EXT:admiral_cloud_connector/Resources/Public/Backend/Js/',
    ],
];
