<?php

namespace {
    use Shoplic\WPBridgeReact\ReactBridge;

    function shoplic_wp_bridge_react($absoluteDistPath, $localhostUrl = 'http://localhost:5713'): Shoplic\WPBridgeReact\ReactBridge
    {
        return ReactBridge::getInstance($absoluteDistPath, $localhostUrl);
    }
}

namespace Shoplic\WPBridgeReact {
    // 개발 환경 여부를 판별하는 함수
    function isDevEnv(): bool
    {
        return 'production' !== wp_get_environment_type();
    }

    // 프로덕션 환경 여부를 판별하는 함수
    function isProduction(): bool
    {
        return 'production' === wp_get_environment_type();
    }
    
    // 파일 이름을 핸들 이름으로 변환하는 함수
    function fileNameToHandle($filename): string
    {
        return str_replace(['/', '.', '-'], '_', $filename);
    }
    
    // 절대 경로를 URL로 변환하는 함수
    function absolutePathToUrl($absolutePath): string
    {
        return str_replace(ABSPATH, home_url('/'), $absolutePath);
    }
}
