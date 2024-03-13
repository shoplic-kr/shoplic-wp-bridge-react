<?php
namespace Shoplic\WPBridgeReact;

// Determine if the environment is development
function isDevEnv(): bool {
    return 'production' !== wp_get_environment_type();
}

// Determine if the environment is production
function isProduction(): bool {
    return 'production' === wp_get_environment_type();
}

// Convert a file name to a handle name
function fileNameToHandle($filename): string {
    return str_replace(['/', '.', '-'], '_', $filename);
}

// Convert an absolute path to a URL
function absolutePathToUrl($absolutePath): string {
    return str_replace(ABSPATH, home_url('/'), $absolutePath);
}
