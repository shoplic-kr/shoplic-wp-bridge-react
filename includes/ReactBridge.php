<?php
namespace Shoplic\WPBridgeReact;

use Exception;

const SHOPLIC_WP_BRIDGE_REACT_VITE_CLIENT_SCRIPT_HANDLE = SHOPLIC_WP_BRIDGE_REACT . '-vite-client-script';

/**
 * Vite와 React를 WordPress에서 사용하기 위한 브리지 클래스입니다.
 */
class ReactBridge {
    /**
     * 싱글톤 인스턴스
     * @var ReactBridge|null
     */
    private static ?ReactBridge $instance = null;

    /**
     * vite가 생성한 매니페스트 파일의 경로
     * @var string
     */
    public string $manifestPath = '';

    /**
     * vite로 빌드된 파일들이 위치하는 디렉토리의 절대 경로
     * @var string
     */
    public string $absoluteDistPath = '';

    /**
     * vite (yarn start) 로컬 서버의 URL
     * @var string
     */
    public string $localhostUrl = 'https://localhost:5173';

    /**
     * vite로 빌드된 파일들의 URL 경로.
     * enqueue_script에 사용됩니다
     * @var string
     */
    public string $distUrl = '';

    /**
     * 등록된 핸들러들의 목록
     * enqueue후에 <script ... /> 에 'type'을 줄때 주로 사용됩니다.
     * @var array
     */
    public array $handles = [];

    /**
     * 로드된 매니페스트
     * @var array
     */
    public array $manifest = [];

    /**
     * 싱글톤 인스턴스를 반환합니다.
     *
     * @param string $localhostUrl 로컬 서버의 URL
     * @param string $absoluteDistPath 빌드된 자산의 절대 경로
     * @return ReactBridge 인스턴스
     */
    static public function getInstance(string $localhostUrl, string $absoluteDistPath): ReactBridge
    {
        if (is_null(self::$instance)) {
            self::$instance = new self($localhostUrl, $absoluteDistPath);
        }

        return self::$instance;
    }

    /**
     * ReactBridge 생성자입니다.
     *
     * @param string $localhostUrl 로컬 서버의 URL
     * @param string $absoluteDistPath 빌드된 파일들이 있는 디렉토리의 절대 경로
     */
    public function __construct(string $localhostUrl, string $absoluteDistPath)
    {
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

    /**
     * 초기화 메서드입니다. 개발 환경이나 프로덕션 환경에 따라 적절한 자산을 로드합니다.
     */
    public function init(): void
    {
        isDevEnv() ? $this->initDev() : $this->initProd();

        // dev , prod 모드에 상관없이 script tag의 type을 module로 바꿔주기 위한 filter 입니다.
        add_filter('script_loader_tag', [$this, 'filterChangeType'], 999, 3);
    }

    /**
     * 개발 환경에서 사용될 자산을 로드합니다.
     */
    public function initDev(): void
    {
        $this->loadViteDevAssets();
    }

    /**
     * 프로덕션 환경에서 사용될 자산을 로드합니다.
     */
    public function initProd(): void
    {
        try {
            $this->manifest = $this->loadManifest() ?? [];
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log($e->getMessage());
            }
        }
    }

    /**
     * WordPress에서 사용할 shortcode를 등록합니다.
     *
     * @param array $args Shortcode와 관련된 설정 배열
     * @return ReactBridge Chaining을 위한 인스턴스
     */
    public function addShortcode($args = []): ReactBridge
    {
        $shortcode_name = $args['shortcode_name'];
        $props = $args['props'];
        $entry_file_name = $args['entry_file_name'];

        if (shortcode_exists($shortcode_name)) {
            throw new Exception("Shortcode - '{$shortcode_name}'은(는) 이미 존재합니다.");
        }

        // props should have root_id
        $root_id = $props['root_id'];
        if (!$root_id) {
            throw new Exception("'root_id'가 지정되지 않았습니다.");
        }

        $self = $this;

        add_shortcode($shortcode_name, function($attrs = []) use ($self, $shortcode_name, $props, $root_id, $entry_file_name) {
            // shortcode에 attrs를 쓰는경우([my_shortcode test=1]) js 세계로 넘겨주기 위해 props와 합칠 수 있도록 filter를 열어둔다
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

        return $this;
    }

    /**
     * 지정된 root_id를 가진 컨테이너를 렌더링합니다.
     *
     * @param string $root_id 렌더링될 컴포넌트의 root ID
     * @return string 렌더링된 HTML 문자열
     */
    public function render($root_id): string
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

    /**
     * 프로덕션 환경의 assets을 로드합니다.
     *
     * @param string $entry_file_name 엔트리 파일 이름 (src이후의 상대 경로를 포함한 파일 이름)
     * @param string $entry_handle WordPress에서 사용될 핸들 이름
     * @param array $dependencyScripts 의존성 스크립트 배열
     * @param array $dependencyStyles 의존성 스타일 배열
     */
    public function enqueueProductionAssets(string $entry_file_name, string $entry_handle, array $dependencyScripts = [], array $dependencyStyles = []): void
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

    /**
     * 개발 환경에 사용되는 파일(js)들을 로드합니다
     *
     * @param string $entry_file_name 엔트리 파일 이름
     * @param string $handle WordPress에서 사용될 핸들 이름
     * @param array $deps 의존성 배열
     */
    public function enqueueDevAssets(string $entry_file_name, string $handle, array $deps = []): void
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

    /**
     * 매니페스트 파일을 로드합니다.
     *
     * @return array|null 매니페스트 파일의 내용
     */
    public function loadManifest(): array
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

    /**
     * 개발 환경에서 필요한 Vite 클라이언트 스크립트를 등록합니다.
     */
    public function loadViteDevAssets(): void
    {
        $this->registerViteClientScript();
        $this->injectReactRefreshScript();
    }

    /**
     * Vite 클라이언트 스크립트를 WordPress에 등록합니다.
     */
    public function registerViteClientScript(): void
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

    /**
     * React Fast Refresh 스크립트를 페이지에 주입합니다.
     */
    public function injectReactRefreshScript(): void
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

    /**
     * React Fast Refresh 스크립트의 내용을 반환합니다.
     *
     * @return string React Fast Refresh 스크립트
     */
    public function getReactRefreshScript(): string
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

    /**
     * 스크립트 태그의 type 속성을 module로 변경합니다.
     *
     * @param string $tag 처리할 스크립트 태그의 HTML 문자열
     * @param string $handle 스크립트의 핸들 이름
     * @return string 수정된 스크립트 태그의 HTML 문자열
     */
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