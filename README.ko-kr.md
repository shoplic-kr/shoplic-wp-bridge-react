[![korean](https://img.shields.io/badge/lang-ko-blue.svg)](https://github.com/shoplic-kr/shoplic-wp-bridge-react/blob/main/README.ko-kr.md)
[![english](https://img.shields.io/badge/lang-en-red.svg)](https://github.com/shoplic-kr/shoplic-wp-bridge-react/blob/main/README.md)

# Shoplic WP Bridge React

Shoplic WP Bridge React는 WordPress 환경에서 React 기반 컴포넌트를 사용하기 쉽게 도와주는 플러그인입니다. 이 플러그인을 통해 WordPress의 Shortcode를 활용하여 웹 페이지에 React 컴포넌트를 렌더링할 수 있습니다.

[사용 예제](https://github.com/shoplic-kr/shoplic-wp-bridge-react-example-theme)

## 주의 사항

- 라이브 모드에서는 `wp-config.php` 파일에 `define('WP_ENVIRONMENT_TYPE', 'production');`을 설정하거나, `define('WP_ENVIRONMENT_TYPE', 'local');`을 삭제하는 것이 중요합니다.

## 주요 기능

- **React 컴포넌트와의 통합**: WordPress의 단축코드를 통해 React 컴포넌트를 연결하고 렌더링합니다.
- **개발 모드와 프로덕션 모드 지원**: 개발 중에는 HMR(핫 모듈 교체)을 통해 실시간으로 변경 사항을 반영하고, 프로덕션 모드에서는 최적화된 자산(css, js)을 사용합니다.

## 시작하기

### 필요 조건
- PHP 7.4 이상
- Vite5 이상 (Vite v4에서는 오류 발생)
- WordPress 5.9 이상

### 설치 방법

1. 플러그인 파일을 WordPress 플러그인 디렉토리(`/wp-content/plugins/`)에 업로드합니다.
2. WordPress 관리자 대시보드의 플러그인 메뉴로 이동하여 'Shoplic WP Bridge React' 플러그인을 활성화합니다.

## 사용 방법

### 단축코드 등록

`shoplic_wp_bridge_react()->addShortcode` 함수를 사용하여 React 컴포넌트를 연결할 단축코드를 등록할 수 있습니다. 이 함수는 여러 인자를 받습니다.

```php
$absoluteDistPath = get_template_directory() . '/my-react-app/dist';
shoplic_wp_bridge_react()->addShortcode([
    'shortcode_name' => 'main_slider',
    'props' => [
        'absolute_dist_path' => $absoluteDistPath,
        'object_name' => 'main_slider_props',
        'root_id' => 'main-slider-root-id',
        'slide_speed' => 5400,
    ],
    'entry_file_name' => 'main-slider/main-slider.tsx',
]);
```

- [필수] `absolute_dist_path`: 빌드 시 파일이 저장될 디렉토리의 전체 경로를 입력합니다.
    - 테마 내에서 React를 사용하는 경우 `get_template_directory() . '/my-react-app/dist'`를 사용하세요.
    - 플러그인 내에서 개발하는 경우 `plugin_dir_path(__FILE__) . 'my-react-app/dist`를 사용하세요.
- [선택] `localhost_url`: 개발 모드에서 Vite에 의해 할당된 로컬호스트 주소, 예를 들어 `https://localhost:5713`을 입력하세요. 기본값은 `https://localhost:5713`입니다.
- [필수] `shortcode_name`: 등록할 단축코드의 이름입니다.
- [필수] `props`: 단축코드를 통해 PHP에서 React 앱으로 전달될 속성입니다.
- [필수] `props.root_id`: React 컴포넌트가 렌더링될 HTML 요소의 ID입니다.
- [필수] `props.object_name`: WordPress의 wp_localize_script 함수를 통해 JS로 전달될 객체의 이름입니다.
- `props.hello`: 커스텀 값을 props에 추가할 수 있습니다.
- [필수] `entry_file_name`: React 컴포넌트의 엔트리 파일 경로입니다. src부터 최종 엔트리 파일까지의 전체 경로를 포함하세요.

### React 프로젝트 생성 (Vite 사용)

#### 프로젝트 생성
```
yarn create vite
```

#### vite.config.ts 설정
```ts
import react from '@vitejs/plugin-react'
import tsconfigPaths from 'vite-tsconfig-paths' // TypeScript를 사용하는 경우에만 포함
import {defineConfig} from 'vite'

// https://vitejs.dev/config/
export default defineConfig({
    build: {
        assetsDir: 'assets',
        emptyOutDir: true, // 빌드 시 outDir 폴더를 삭제합니다 (outDir은 일반적으로 'dist'로 설정됨)
        manifest: true, // [필수] 매니페스트 파일을 생성합니다.
        rollupOptions: {
            input: [ // [필수] 여기에 엔트리 파일을 나열합니다. 단축코드 당 하나의 엔트리 포인트.
                './src/hello-world/hello-world.tsx',
                './src/main-slider/main-slider.tsx',
            ]
        },
        sourcemap: true, // 소스맵을 출력할지 여부를 결정합니다.
    },
    plugins: [
        react(),
        tsconfigPaths(), // TypeScript를 사용하는 경우에만 포함
    ],
    publicDir: false, // 기본적으로 Vite는 빌드 시 publicDir에서 outDir로 파일을 복사합니다. 이를 비활성화하기 위해 false로 설정합니다.
})
```

#### src/main-slider/main-slider.tsx
```tsx
import 'vite/modulepreload-polyfill' // 중요: 이 폴리필을 import 해야 합니다
import {createRoot} from 'react-dom/client'
import MainSlider from './MainSlider'

// TypeScript를 사용하는 경우에만 설정
declare global {
    const main_slider_props: { // main_slider_props는 props.object_name에 할당된 이름과 일치해야 합니다.
        root_id: string,
        slide_speed: string,
    }
}

const {root_id, slide_speed} = main_slider_props;

const root = document.getElementById(root_id)
if (root) {
    createRoot(root).render(<MainSlider speed={Number(slide_speed ?? 1000)} />)
}
```

### 단축코드에서 속

성 사용
```
[main_slider slide_count=5]
```
이렇게 사용하면, `main-slider.tsx`에서 props가 다음과 같이 전달됩니다:
```tsx
declare global {
    const main_slider_props: { // main_slider_props는 props.object_name에 할당된 이름과 일치해야 합니다.
        root_id: string,
        slider_speed: string,
        slide_count: string,
    }
}

const {root_id, slider_speed, slide_count} = main_slider_props;

root = document.getElementById(root_id)
if (root) {
    createRoot(root).render(<MainSlider speed={Number(slider_speed ?? 1000)} count={Number(slide_count ?? 0)} />)
}
```

### 개발 모드와 라이브 모드 지정
wp-config.php 파일에서 다음과 같이 정의하세요:

```php
// 개발 모드인 경우
define('WP_ENVIRONMENT_TYPE', 'local');

// 라이브 모드는 별도로 지정할 필요가 없으며, 아래와 같이 정의할 수 있습니다
define('WP_ENVIRONMENT_TYPE', 'production');
```