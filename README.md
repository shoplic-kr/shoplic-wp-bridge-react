# Shoplic WP Bridge React

Shoplic WP Bridge React는 WordPress 환경에서 React 기반의 컴포넌트를 쉽게 사용할 수 있도록 도와주는 플러그인입니다. 이 플러그인을 통해 WordPress의 shortcode를 활용하여 React 컴포넌트를 웹 페이지에 직접 렌더링할 수 있습니다.

[사용 에제](https://gitlab.com/byeongin_shoplic/shoplic-wp-bridge-react-example)

## 주의

- 라이브 모드에서는 `wp-config.php`파일에 꼭 `define('WP_ENVIRONMENT_TYPE', 'production');` 로 설정해 주시거나, `define('WP_ENVIRONMENT_TYPE', 'local');`를 삭제해 주세요.

## 주요 기능

- **React 컴포넌트와의 연결**: WordPress의 shortcode를 통해 React 컴포넌트를 연결하고 렌더링합니다.
- **개발 모드와 프로덕션 모드 지원**: 개발 중에는 HMR(Hot Module Replacement)을 통해 실시간으로 변경 사항을 반영할 수 있고, 프로덕션 모드에서는 최적화된 자산을 사용합니다.

## 시작하기

### 필요 조건

이 플러그인을 사용하기 위해서는 PHP 7.4 이상이 필요합니다. 또한, WordPress 환경이 구성되어 있어야 합니다.

### 설치 방법

1. 플러그인 파일을 WordPress 플러그인 디렉토리(`/wp-content/plugins/`)에 업로드합니다.
2. WordPress 관리자 대시보드에서 플러그인 메뉴로 이동한 후, 'Shoplic WP Bridge React' 플러그인을 활성화합니다.

## 사용 방법

### Shortcode 등록

`shoplic_wp_bridge_react(...)->addShortcode` 함수를 사용하여 React 컴포넌트와 연결할 shortcode를 등록할 수 있습니다. 이 함수는 여러 가지 인자값을 받습니다.

```php
shoplic_wp_bridge_react($localhostUrl, $absoluteDistPath)->addShortcode([
    'shortcode_name' => 'hello_world', // shortcode 이름
    'props' => [
        'object_name' => 'hello_world_props',
        'root_id' => 'hello-world-root-id', // React 컴포넌트를 렌더링할 HTML 요소의 ID
    ],
    'entry_file_name' => 'hello-world/hello-world.tsx', // 엔트리 파일 경로
]);
```

- $localhostUrl: `https://localhost:5713` 처럼 vite에서 할당해주는 localhost 주소를 입력해주세요.
- $absoluteDistPath: `__DIR__ . '/my-react-app/dist'` 처럼 full path를 입력해주세요.
- `shortcode_name`: 등록할 shortcode의 이름입니다.
- `props`: React 컴포넌트로 전달될 props입니다. `root_id`는 필수적으로 포함되어야 합니다.
- `entry_file_name`: React 컴포넌트의 엔트리 파일 경로입니다. src를 기준으로 최종 엔트리 파일까지의 경로를 모두 적어주세요.
- `props.object_name`: wordpress의 wp_localize_script 함수를 통해 js에 전달할 props의 객체 이름


### React 프로젝트 생성 (Vite 사용)

#### 프로젝트 생성
```
yarn create vite
```

#### vite.config.ts 설정
```ts
import react from '@vitejs/plugin-react'
import tsconfigPaths from 'vite-tsconfig-paths' // typescript를 사용하는 경우에만 넣어주세요
import {defineConfig} from 'vite'

// https://vitejs.dev/config/
export default defineConfig({
    build: {
        assetsDir: 'assets',
        emptyOutDir: true, // 빌드시 outDir 폴더를 삭제합니다(기본적으로 outDir는 'dist'로 설정되어 있습니다)
        manifest: true, // [필수] manifest파일을 생성합니다. 
        rollupOptions: {
            input: [ // [필수] entry 파일들을 나열해 줍니다. 숏코드 하나당 엔트리 포인트 한개가 매칭됩니다.
                './src/hello-world/hello-world.tsx',
                './src/main-slider/main-slider.tsx',
            ]
        },
        sourcemap: true, // 소스맵을 출력할지 여부를 결정합니다.
    },
    plugins: [
        react(),
        tsconfigPaths(), // typescript를 사용하는 경우에만 넣어주세요
    ],
    publicDir: false, // 기본적으로 Vite는 빌드할 때 publicDir에서 outDir로 파일을 복사합니다. 이를 비활성화하기 위해 false로 설정해줍니다.
})
```

#### src/hello-world/hello-world.tsx
```tsx
import 'vite/modulepreload-polyfill' // 중요 polyfill을 import해주어야 합니다
import {createRoot} from 'react-dom/client'
import MainSlider from './MainSlider'

// typescript를 사용하는 경우에만 설정해주세요
declare global {
    const main_slider_props: { // main_slider_props는 props.object_name에 할당한 이름과 동일한 이름을 사용해야 합니다.
        root_id: string
    }
}

const {root_id} = main_slider_props;

const root = document.getElementById(root_id)
if (root) {
    createRoot(root).render(<MainSlider />)
}
```

### 개발모드와 라이브모드 지정
wp-config.php에 아래와 같이 지정합니다.

```php
// 개발모드인 경우
define('WP_ENVIRONMENT_TYPE', 'local');

// 라이브모드는 따로 지정하지 않으셔도 되고, 혹은 아래와 같이 지정합니다
define('WP_ENVIRONMENT_TYPE', 'production');
```