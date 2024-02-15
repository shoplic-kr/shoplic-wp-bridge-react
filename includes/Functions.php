<?php

namespace {
    use Shoplic\WPBridgeReact\ReactBridge;

    // shoplic_wp_bridge_react 글로벌 함수 정의
    // ReactBridge 인스턴스를 생성하거나 기존 인스턴스를 반환
    function shoplic_wp_bridge_react($localhostUrl, $absoluteDistPath): Shoplic\WPBridgeReact\ReactBridge
    {
        return ReactBridge::getInstance($localhostUrl, $absoluteDistPath);
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
    
    function absolutePathToUrl($absolutePath): string
    {
        return str_replace(ABSPATH, home_url('/'), $absolutePath);
    }
}
