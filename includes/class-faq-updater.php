<?php
defined( 'ABSPATH' ) || exit;

/**
 * FAQM_Updater
 *
 * Zelfstandige update-checker die WordPress updates laat ophalen uit de
 * GitHub-releases van deze plugin. Geen externe library nodig.
 *
 * Werking:
 * - Leest de laatste release via de GitHub API (gecachet in een transient).
 * - Is het release-versienummer hoger dan de geïnstalleerde versie, dan
 *   meldt WordPress een update onder Plugins → Updates, met één klik bijwerken.
 * - Download: bij voorkeur een aan de release toegevoegde .zip-asset; anders
 *   valt hij terug op de automatische "Source code (zip)" (zipball).
 * - upgrader_source_selection zorgt dat de uitgepakte map altijd de juiste
 *   pluginmapnaam houdt (voorkomt de "dubbele map"-situatie).
 *
 * Publieke repo: er is geen token nodig.
 * Private repo: definieer FAQM_GITHUB_TOKEN in wp-config.php (fine-grained
 * token met alleen Contents: Read op deze repo).
 */
class FAQM_Updater {

    /** GitHub "owner/repo" */
    const REPO = 'krachtinternetmarketing/kim-faq-manager';

    /** Cache-duur van de release-check (in seconden) */
    const CACHE_TTL = 6 * HOUR_IN_SECONDS;

    private static string $plugin_file = '';   // bijv. "kim-faq-manager/faq-manager.php"
    private static string $plugin_slug = '';   // bijv. "kim-faq-manager"

    public static function init(): void {
        // Basename t.o.v. de plugins-map, bepaald vanuit het hoofdbestand.
        self::$plugin_file = plugin_basename( FAQM_PATH . 'faq-manager.php' );
        self::$plugin_slug = dirname( self::$plugin_file );

        add_filter( 'pre_set_site_transient_update_plugins', [ __CLASS__, 'inject_update' ] );
        add_filter( 'plugins_api',                           [ __CLASS__, 'plugin_info' ], 20, 3 );
        add_filter( 'upgrader_source_selection',             [ __CLASS__, 'fix_source_dir' ], 10, 4 );
        add_action( 'upgrader_process_complete',             [ __CLASS__, 'clear_cache' ], 10, 2 );
    }

    /** Transient-sleutel voor de gecachte release-data. */
    private static function cache_key(): string {
        return 'faqm_update_' . md5( self::REPO );
    }

    /**
     * Haal de laatste release op van GitHub (gecachet).
     * @return array{version:string,zip:string,html_url:string,body:string,published:string}|null
     */
    private static function get_latest_release( bool $force = false ): ?array {
        $key = self::cache_key();

        if ( ! $force ) {
            $cached = get_transient( $key );
            if ( is_array( $cached ) ) {
                return $cached ?: null; // lege array = "geen release / fout", ook cachen
            }
        }

        $url  = 'https://api.github.com/repos/' . self::REPO . '/releases/latest';
        $args = [
            'timeout' => 15,
            'headers' => [
                'Accept'     => 'application/vnd.github+json',
                'User-Agent' => 'kim-faq-manager-updater',
            ],
        ];
        if ( defined( 'FAQM_GITHUB_TOKEN' ) && FAQM_GITHUB_TOKEN ) {
            $args['headers']['Authorization'] = 'Bearer ' . FAQM_GITHUB_TOKEN;
        }

        $response = wp_remote_get( $url, $args );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            set_transient( $key, [], self::CACHE_TTL ); // fout: even niet blijven proberen
            return null;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $data ) || empty( $data['tag_name'] ) ) {
            set_transient( $key, [], self::CACHE_TTL );
            return null;
        }

        // Versie uit de tag (accepteer zowel "3.12.0" als "v3.12.0")
        $version = ltrim( (string) $data['tag_name'], 'vV' );

        // Downloadbron: eerst een toegevoegde .zip-asset, anders de zipball.
        $zip = $data['zipball_url'] ?? '';
        if ( ! empty( $data['assets'] ) && is_array( $data['assets'] ) ) {
            foreach ( $data['assets'] as $asset ) {
                if ( ! empty( $asset['browser_download_url'] )
                    && substr( (string) $asset['name'], -4 ) === '.zip' ) {
                    $zip = $asset['browser_download_url'];
                    break;
                }
            }
        }

        $release = [
            'version'   => $version,
            'zip'       => (string) $zip,
            'html_url'  => (string) ( $data['html_url'] ?? '' ),
            'body'      => (string) ( $data['body'] ?? '' ),
            'published' => (string) ( $data['published_at'] ?? '' ),
        ];

        set_transient( $key, $release, self::CACHE_TTL );
        return $release;
    }

    /**
     * Injecteer de update in de WordPress update-transient.
     */
    public static function inject_update( $transient ) {
        if ( ! is_object( $transient ) ) {
            return $transient;
        }

        $release = self::get_latest_release();
        if ( ! $release || empty( $release['zip'] ) ) {
            return $transient;
        }

        if ( version_compare( $release['version'], FAQM_VERSION, '<=' ) ) {
            return $transient; // geen nieuwere versie
        }

        $item = (object) [
            'id'          => self::REPO,
            'slug'        => self::$plugin_slug,
            'plugin'      => self::$plugin_file,
            'new_version' => $release['version'],
            'url'         => $release['html_url'],
            'package'     => $release['zip'],
            'tested'      => '',
            'icons'       => [],
            'banners'     => [],
        ];

        if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
            $transient->response = [];
        }
        $transient->response[ self::$plugin_file ] = $item;

        return $transient;
    }

    /**
     * Vul het "Details bekijken"-venster van de plugin.
     */
    public static function plugin_info( $result, $action, $args ) {
        if ( $action !== 'plugin_information' ) {
            return $result;
        }
        if ( empty( $args->slug ) || $args->slug !== self::$plugin_slug ) {
            return $result;
        }

        $release = self::get_latest_release();
        if ( ! $release ) {
            return $result;
        }

        $changelog = trim( $release['body'] ) !== ''
            ? nl2br( esc_html( $release['body'] ) )
            : esc_html__( 'Zie de release op GitHub voor de wijzigingen.', 'faq-manager' );

        return (object) [
            'name'          => 'Kracht Internet Marketing - FAQ Manager',
            'slug'          => self::$plugin_slug,
            'version'       => $release['version'],
            'author'        => '<a href="https://www.krachtinternetmarketing.nl">Kracht Internet Marketing</a>',
            'homepage'      => $release['html_url'],
            'download_link' => $release['zip'],
            'sections'      => [
                'changelog' => $changelog,
            ],
        ];
    }

    /**
     * Zorg dat de uitgepakte map de juiste pluginmapnaam heeft.
     * GitHub-zipballs pakken uit naar bijv. "owner-repo-<hash>/"; WordPress
     * zou de plugin dan onder een verkeerde map installeren. We hernoemen de
     * bronmap naar de bestaande pluginslug.
     */
    public static function fix_source_dir( $source, $remote_source, $upgrader, $hook_extra = [] ) {
        if ( empty( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== self::$plugin_file ) {
            return $source;
        }

        global $wp_filesystem;
        if ( ! $wp_filesystem ) {
            return $source;
        }

        $desired = trailingslashit( $remote_source ) . self::$plugin_slug . '/';
        if ( trailingslashit( $source ) === $desired ) {
            return $source;
        }

        if ( $wp_filesystem->move( $source, $desired, true ) ) {
            return $desired;
        }

        return $source;
    }

    /**
     * Leeg de cache na een update, zodat de volgende check vers is.
     */
    public static function clear_cache( $upgrader, $hook_extra ): void {
        if ( ! is_array( $hook_extra ) ) {
            return;
        }
        if ( ( $hook_extra['action'] ?? '' ) === 'update'
            && ( $hook_extra['type'] ?? '' ) === 'plugin' ) {
            delete_transient( self::cache_key() );
        }
    }
}
