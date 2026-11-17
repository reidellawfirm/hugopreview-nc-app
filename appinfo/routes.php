<?php

declare(strict_types=1);

return [
    'routes' => [
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
        ['name' => 'hugoApi#projects', 'url' => '/api/projects', 'verb' => 'GET'],
        ['name' => 'hugoApi#saveProject', 'url' => '/api/projects', 'verb' => 'POST'],
        ['name' => 'hugoApi#getProject', 'url' => '/api/projects/{id}', 'verb' => 'GET'],
        ['name' => 'hugoApi#deleteProject', 'url' => '/api/projects/{id}', 'verb' => 'DELETE'],
        ['name' => 'hugoApi#buildProject', 'url' => '/api/projects/{id}/build', 'verb' => 'POST'],
        ['name' => 'hugoApi#buildAllProjects', 'url' => '/api/build/all', 'verb' => 'POST'],
    ],
];
