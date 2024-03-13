# Shoplic WP Bridge React

Shoplic WP Bridge React is a plugin designed to facilitate the use of React-based components within the WordPress environment. This plugin enables the rendering of React components on web pages by utilizing WordPress's shortcode.

[Usage Example](https://github.com/shoplic-kr/shoplic-wp-bridge-react-example-theme)

## Attention

- In live mode, it is crucial to set `define('WP_ENVIRONMENT_TYPE', 'production');` in your `wp-config.php` file, or delete `define('WP_ENVIRONMENT_TYPE', 'local');`.

## Key Features

- **Integration with React Components**: Connects and renders React components via WordPress's shortcode.
- **Support for Development and Production Modes**: Offers live changes through HMR (Hot Module Replacement) during development and uses optimized assets (css, js) in production mode.

## Getting Started

### Prerequisites
- PHP 7.4 or higher
- Vite5 or higher (errors occur with Vite v4)
- WordPress 5.9 or higher

### Installation

1. Upload the plugin files to the WordPress plugin directory (`/wp-content/plugins/`).
2. Navigate to the plugin menu in your WordPress admin dashboard and activate the 'Shoplic WP Bridge React' plugin.

## How to Use

### Registering a Shortcode

Utilize the `shoplic_wp_bridge_react()->addShortcode` function to register a shortcode for connecting a React component. This function takes several arguments.

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

- [Required] `absolute_dist_path`: Enter the full path of the directory where the files will be stored upon build.
    - If using React within a theme, please use `get_template_directory() . '/my-react-app/dist'`.
    - If developing inside a plugin, use `plugin_dir_path(__FILE__) . 'my-react-app/dist`.
- [Optional] `localhost_url`: Enter the localhost address assigned by Vite in dev mode, like `https://localhost:5713`. The default is `https://localhost:5713`.
- [Required] `shortcode_name`: The name of the shortcode to register.
- [Required] `props`: The props to pass from PHP to the React App connected via the shortcode.
- [Required] `props.root_id`: The ID of the HTML element where the React component will be rendered.
- [Required] `props.object_name`: The name of the object that will be passed to JS through WordPress's wp_localize_script function.
- `props.hello`: You can add custom values to the props.
- [Required] `entry_file_name`: The path to the entry file of the React component. Include the entire path from src to the final entry file.

### Creating a React Project (Using Vite)

#### Project Creation
```
yarn create vite
```

#### Configuring vite.config.ts
```ts
import react from '@vitejs/plugin-react'
import tsconfigPaths from 'vite-tsconfig-paths' // Only include if using TypeScript
import {defineConfig} from 'vite'

// https://vitejs.dev/config/
export default defineConfig({
    build: {
        assetsDir: 'assets',
        emptyOutDir: true, // Deletes the outDir folder during build (outDir is typically set to 'dist')
        manifest: true, // [Required] Generates a manifest file.
        rollupOptions: {
            input: [ // [Required] List your entry files here. One entry point per shortcode.
                './src/hello-world/hello-world.tsx',
                './src/main-slider/main-slider.tsx',
            ]
        },
        sourcemap: true, // Determines whether to output a sourcemap.
    },
    plugins: [
        react(),
        tsconfigPaths(), // Only include if using TypeScript
    ],
    publicDir: false, // By default, Vite copies files from publicDir to outDir during build. This disables that behavior.
})
```

#### src/main-slider/main-slider.tsx
```tsx
import 'vite/modulepreload-polyfill' // Important: must import this polyfill
import {createRoot} from 'react-dom/client'
import MainSlider from './MainSlider'

// Only set up if using TypeScript
declare global {
    const main_slider_props: { // main_slider_props should match the name assigned in props.object_name.
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

### Using attributes in the shortcode
```
[main_slider slide_count=5]
```
Used like this, in `main-slider.tsx`, the props will be passed as follows:
```tsx
declare global {
    const main_slider_props: { // main_slider_props should match the name assigned in props.object_name.
        root_id: string,
        slider_speed: string,
        slide_count: string,
    }
}

const {root_id, slider_speed, slide_count} = main_slider_props;

The root = document.getElementById(root_id)
if (root) {
    createRoot(root).render(<MainSlider speed={Number(slider_speed ?? 1000)} count={Number(slide_count ?? 0)} />)
}
```

### Specifying Development and Live Modes
Define in your wp-config.php file as follows:

```php
// For development mode
define('WP_ENVIRONMENT_TYPE', 'local');

// Live mode does not need to be specified separately, or you can define it as follows
define('WP_ENVIRONMENT_TYPE', 'production');
```