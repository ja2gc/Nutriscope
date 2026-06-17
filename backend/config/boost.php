<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Laravel Boost MCP Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the Laravel Boost MCP server, which provides tools,
    | resources, and prompts for Claude to interact with your Laravel app.
    |
    */

    'mcp' => [
        'tools' => [
            'exclude' => [
                // Add tool classes here to exclude them from the MCP server
                // 'Laravel\Boost\Mcp\Tools\SomeToolName',
            ],
            'include' => [
                // Add custom tool classes here to include them in the MCP server
            ],
        ],
        'resources' => [
            'exclude' => [],
            'include' => [],
        ],
        'prompts' => [
            'exclude' => [],
            'include' => [],
        ],
    ],

];
