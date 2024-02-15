<?php
namespace Shoplic\WPBridgeReact;

use Exception;

const SHOPLIC_WP_BRIDGE_REACT_VITE_CLIENT_SCRIPT_HANDLE = SHOPLIC_WP_BRIDGE_REACT . '-vite-client-script';

class ReactBridge {
    private static ?ReactBridge $instance = null;

    public string $manifestPath = '';
    public string $absoluteDistPath = '';
    public string $localhostUrl = 'https://localhost:5173';
    public string $distUrl = '';

    public array $handles = [];
    public array $manifest = [];

    static public function getInstance($localhostUrl, $absoluteDistPath): ReactBridge
    {
        if (is_null(self::$instance)) {
            self::$instance = new self($localhostUrl, $absoluteDistPath);
        }

        return self::$instance;
    }

    // buildPath는 fullPath여야 합니다 /wp-content/themes/mytheme/build/assets/... 이런식으로
    // 혹은 /wp-content/plugins/myplugin/build/assets/... 이런식으로
    public function __construct($localhostUrl, $absoluteDistPath)
    {
        // @TODO: 보통 이렇게 하는지 검사가 필요하다
        if (!is_null(self::$instance)) {
            return self::$instance;
        }

        $this->absoluteDistPath = rtrim($absoluteDistPath, '/');
        $this->distUrl = absolutePathToUrl($this->absoluteDistPath);
        $this->manifestPath = $absoluteDistPath . '/.vite/manifest.json'; // this is for vite5
        $this->localhostUrl = rtrim($localhostUrl, '/');
        
        // script 때문에 여기서 바로 init을 호출하면 안되고, hook에 등록해줘야 합니다
        add_action( 'init', [$this, 'init'] );
    }

    public function init()
    {
        isDevEnv() ? $this->initDev() : $this->initProd();

        // dev , prod 모드에 상관없이 script tag의 type을 module로 바꿔주기 위한 filter 입니다.
        add_filter('script_loader_tag', [$this, 'filterChangeType'], 999, 3);
    }

    public function initDev()
    {
        $this->loadViteDevAssets();
    }

    public function initProd()
    {
        try {
            $this->manifest = $this->loadManifest() ?? [];
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log($e->getMessage());
            }
            return null;
        }
    }

    public function addShortcode($args = [])
    {
        $shortcode_name = $args['shortcode_name'];
        $props = $args['props'];
        $entry_file_name = $args['entry_file_name'];

        // check if the shortcode name is already registered
        if (shortcode_exists($shortcode_name)) {
            // @TODO: throw error
            return;
        }

        // props should have root_id
        $root_id = $props['root_id'];
        if (!$root_id) {
            // @TODO: throw error
            return;
        }

        $self = $this;

        add_shortcode($shortcode_name, function($attrs = []) use ($self, $shortcode_name, $props, $root_id, $entry_file_name) {
            // attrs를 쓰는경우 js 세계로 넘겨주기 위해 props와 합칠 수 있도록 filter를 열어둔다
            $props = apply_filters($shortcode_name . '_props_filter', $props, $attrs);
            $entry_handle = fileNameToHandle($entry_file_name);

            // 여기다, 숏코드를 사용하는 시점에 해당 숏코드의 entry_file_name을 바탕으로 script를 enqueue 한다.
            if (isDevEnv()) {
                $self->enqueueDevAssets($entry_file_name, $entry_handle);
            } else {
                $deps = $props['dependency_scripts'] ?? [];
                $self->enqueueProductionAssets($entry_file_name, $entry_handle, $deps);
            }

            $object_name = isset($props['object_name']) ? $props['object_name'] : $entry_handle . '_props';
            wp_localize_script($entry_handle, $object_name, $props);

            return $self->render($root_id);
        });
    }

    public function render($root_id)
    {
        ob_start(); ?>

        <div id="<?php echo $root_id; ?>" data-wp-bridge-react-component-root="true">
            <noscript>JavaScript를 실행할 수 있는 브라우저가 필요합니다.</noscript>
            <?php if (isDevEnv()): ?>
                <p>
                    현재 개발 모드로 동작 중입니다. 혹시
                    <code style="background-color: #e0e0ee; border-radius: 4px; padding: 4px 8px;">yarn run dev</code>
                    실행을 잊으셨나요?
                </p>
            <?php endif ?>
        </div>

        <?php
        return ob_get_clean();
    }

    public function enqueueProductionAssets($entry_file_name, $entry_handle, $dependencyScripts = [], $dependencyStyles = []): void
    {   
        if (!isset($this->handles[$entry_handle])) {
            $this->handles[$entry_handle] = true;

            $key = "src/{$entry_file_name}";
            
            $isEntry = $this->manifest[$key]->isEntry;
            if (!$isEntry) return; // entry가 아닌 자원을 enqueue할 필요가 없다.

            $file = $this->manifest[$key]->file;
            $imports = $this->manifest[$key]->imports ?? [];
            $cssItems = $this->manifest[$key]->css ?? [];

            if (empty($file)) {
                // @TODO throw error
                return;
            }

            $entry_path = $this->distUrl . '/' . $file;
            wp_enqueue_script($entry_handle, $entry_path, $dependencyScripts, null, true);
            
            // enqueue imported js
            foreach ($imports as $import) {
                $import_handle = fileNameToHandle($import);
                if (!isset($this->handles[$import_handle])) {
                    $this->handles[$import_handle] = true;
                    $importPathUrl = $this->distUrl . '/' . $this->manifest[$import]->file;
                    wp_enqueue_script($import_handle, $importPathUrl, $dependencyScripts, null, true);
                }
            }

            // enqueue imported css
            foreach ($cssItems as $cssItem) {
                $css_handle = fileNameToHandle($cssItem);
                wp_enqueue_style($css_handle, $this->distUrl . '/' . $cssItem, $dependencyStyles, null);
            }
        }
    }

    public function enqueueDevAssets($entry_file_name, $handle, $deps = []): void
    {
        if (!isset($this->handles[$handle])) {
            $this->handles[$handle] = true;

            $path = $this->localhostUrl . "/src/$entry_file_name";
            
            wp_enqueue_script(
                $handle,
                $path,
                array_merge(
                    [
                        'wp-i18n',
                        SHOPLIC_WP_BRIDGE_REACT_VITE_CLIENT_SCRIPT_HANDLE
                    ],
                    $deps
                ),
                null,
                ['in_footer' => true]
            );

            wp_add_inline_script(
                $handle,
                "console.info('$entry_file_name is running in development mode.')"
            );
        }
    }

    public function loadManifest()
    {
        $manifest = null;
        $filePath = $this->manifestPath;

        if (is_file($filePath) && is_readable($filePath)) {
            $manifest = (array) wp_json_file_decode($filePath, true) ?? [];
        } else {
            throw new Exception( esc_html( sprintf( '[Vite] No manifest found in %s.', $filePath ) ) );
        }        

        return $manifest;
    }

    public function loadViteDevAssets()
    {
        $this->registerViteClientScript();
        $this->injectReactRefreshScript();
    }

    public function registerViteClientScript()
    {
        $handle = SHOPLIC_WP_BRIDGE_REACT_VITE_CLIENT_SCRIPT_HANDLE;
        if (!isset($this->handles[$handle])) {
            $this->handles[$handle] = true;

            $src = $this->localhostUrl . '/@vite/client';
            $deps = array();
            $ver = '1.0.0';
            $in_footer = true;
            wp_register_script( $handle, $src, $deps, $ver, $in_footer );
        }
    }

    public function injectReactRefreshScript()
    {
        $handle = SHOPLIC_WP_BRIDGE_REACT_VITE_CLIENT_SCRIPT_HANDLE;

        $refreshScript = $this->getReactRefreshScript();
        wp_add_inline_script($handle, $refreshScript, 'before'); // 중요: after로 하면 오류가 난다

        // @TODO: 다른 곳에서 이미 필터를 걸어줘서 module을 넣어 줬는데도 inline_script는 좀 다른가?
        // 이렇게 굳이 한번 더 해줘야 하는건가?
        add_filter(
            'wp_inline_script_attributes',
            function ( array $attributes ) use ( $handle ): array {
                if ( isset( $attributes['id'] ) && $attributes['id'] === $handle . "-js-before" ) {
                    $attributes['type'] = 'module';
                }
                return $attributes;
            }
        );
    }

    public function getReactRefreshScript()
    {
        // 출력 버퍼링 시작
        ob_start();
    
        // 변수를 사용하여 스크립트 출력
        // refer: https://vitejs.dev/guide/backend-integration.html
        echo "import RefreshRuntime from '{$this->localhostUrl}/@react-refresh';";
        echo "RefreshRuntime.injectIntoGlobalHook(window);";
        echo "window.RefreshReg = () => {};";
        echo "window.RefreshSig = () => (type) => type;";
        echo "window.__vite_plugin_react_preamble_installed__ = true;";
    
        return ob_get_clean();
    }

    public function filterChangeType(string $tag, string $handle): string
    {   
        if (isset($this->handles[$handle])) {
            // <script> tag can be found more than once if wp_add_inline_script() is called.
            $lastPos = 0;
            $scripts = [];

            do {
                $pos = strpos($tag, '<script ', $lastPos + 1);
                if ($pos > $lastPos) {
                    $scripts[] = trim(substr($tag, $lastPos, $pos - $lastPos));
                    $lastPos   = $pos;
                }
            } while ($pos !== false);

            $rest = trim(substr($tag, $lastPos));
            if (str_starts_with($rest, '<script')) {
                $scripts[] = trim($rest);
                $rest      = '';
            }

            foreach ($scripts as &$script) {
                if (str_starts_with($script, '<script ')) {
                    $attrs = substr($script, 6, strpos($script, '>') - 6);
                    if (!str_contains($attrs, 'src=')) {
                        continue;
                    }

                    $replace = '<script ';
                    $type    = false;

                    preg_match_all(
                        '/(\w+)=["\']?((?:.(?!["\']?\s+\S+=|\s*\/?[>"\']))+.)["\']?/',
                        $attrs,
                        $matches,
                        PREG_SET_ORDER
                    );

                    foreach ($matches as $match) {
                        if ('type' === $match[1]) {
                            $replace .= " type='module'";
                            $type    = true;
                        } else {
                            $replace .= " $match[0]";
                        }
                    }

                    if (!$type) {
                        $replace .= " type='module'";
                    }

                    $replace .= '></script>' . PHP_EOL;

                    $script = $replace;
                }
            }

            $tag = implode(PHP_EOL, $scripts) . $rest . PHP_EOL;
        }

        return $tag;
    }
}