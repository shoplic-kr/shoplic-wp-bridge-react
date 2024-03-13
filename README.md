# Shoplic WP Bridge React

Shoplic WP Bridge React는 WordPress 환경에서 React 기반의 컴포넌트를 쉽게 사용할 수 있도록 도와주는 플러그인입니다. 이 플러그인을 통해 WordPress의 shortcode를 활용하여 React 컴포넌트를 웹 페이지에 렌더링할 수 있습니다.

[사용 에제](https://github.com/shoplic-kr/shoplic-wp-bridge-react-example-theme)

## 주의

- 라이브 모드에서는 `wp-config.php`파일에 꼭 `define('WP_ENVIRONMENT_TYPE', 'production');` 로 설정해 주시거나, `define('WP_ENVIRONMENT_TYPE', 'local');`를 삭제해 주세요.

## 주요 기능

- **React 컴포넌트와의 연결**: WordPress의 shortcode를 통해 React 컴포넌트를 연결하고 렌더링합니다.
- **개발 모드와 프로덕션 모드 지원**: 개발 중에는 HMR(Hot Module Replacement)을 통해 실시간으로 변경 사항을 반영할 수 있고, 프로덕션 모드에서는 최적화된 assets(css,js)을 사용합니다.

## 시작하기

### 필요 조건
- PHP 7.4 이상
- Vite5 이상 (vite v4는 오류가 발생합니다)
- Wordpress 5.9 이상

### 설치 방법

1. 플러그인 파일을 WordPress 플러그인 디렉토리(`/wp-content/plugins/`)에 업로드합니다.
2. WordPress 관리자 대시보드에서 플러그인 메뉴로 이동한 후, 'Shoplic WP Bridge React' 플러그인을 활성화합니다.

## 사용 방법

### Shortcode 등록

`shoplic_wp_bridge_react()->addShortcode` 함수를 사용하여 React 컴포넌트와 연결할 shortcode를 등록할 수 있습니다. 이 함수는 여러 가지 인자값을 받습니다.

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

- [필수] `absolute_dist_path`: 빌드시 파일들이 저장될 디렉토리의 full path를 입력해주세요.
    - 테마에서 사용하는 react라면 `get_template_directory() . '/my-react-app/dist'` 을 사용해주세요.
    - 플러그인 내부에서 개발중이라면, `plugin_dir_path(__FILE__) . 'my-react-app/dist` 을 사용해주세요.
- [선택] `localhost_url`: `https://localhost:5713` 처럼 dev모드에서 vite에서 할당해주는 localhost 주소를 입력해주세요. 기본값은 `https://localhost:5713` 입니다.
- [필수] `shortcode_name`: 등록할 shortcode의 이름입니다.
- [필수] `props`: shortcode와 연결된 php -> React App으로 넘겨줄 props 입니다.
- [필수] `props.root_id`: React 컴포넌트를 렌더링할 HTML 요소의 ID 입니다.
- [필수] `props.object_name`: wordpress의 wp_localize_script 함수를 통해 js에 전달할 props의 객체 이름 입니다.
- `props.hello`: 커스텀한 값을 props에 추가할 수 있습니다.
- [필수] `entry_file_name`: React 컴포넌트의 엔트리 파일 경로입니다. src를 기준으로 최종 엔트리 파일까지의 경로를 모두 적어주세요.


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

#### src/main-slider/main-slider.tsx
```tsx
import 'vite/modulepreload-polyfill' // 중요 polyfill을 import해주어야 합니다
import {createRoot} from 'react-dom/client'
import MainSlider from './MainSlider'

// typescript를 사용하는 경우에만 설정해주세요
declare global {
    const main_slider_props: { // main_slider_props는 props.object_name에 할당한 이름과 동일한 이름을 사용해야 합니다.
        root_id: string,
        slide_speed: string,
    }
}

const {root_id, slide_speed} = main_slider_props;

const root = document.getElementById(root_id)
if (root) {
    createRoot(root).render(<MainSlider speed={Number(slider_speed ?? 1000)} />)
}
```

### shortcode에서 attribute사용
```
[main_slider slide_count=5]
```
위처럼 사용하면,
`main-slider.tsx`에서 아래와 같이 props에 전달됩니다.
```tsx
declare global {
    const main_slider_props: { // main_slider_props는 props.object_name에 할당한 이름과 동일한 이름을 사용해야 합니다.
        root_id: string,
        slider_speed: string,
        slide_count: string,
    }
}

const {root_id, slider_speed, slide_count} = main_slider_props;

const root = document.getElementById(root_id)
if (root) {
    createRoot(root).render(<MainSlider speed={Number(slider_speed ?? 1000)} count={Number(slide_count ?? 0)} />)
}
```
```

### 개발모드와 라이브모드 지정
wp-config.php에 아래와 같이 지정합니다.

```php
// 개발모드인 경우
define('WP_ENVIRONMENT_TYPE', 'local');

// 라이브모드는 따로 지정하지 않으셔도 되고, 혹은 아래와 같이 지정합니다
define('WP_ENVIRONMENT_TYPE', 'production');
```