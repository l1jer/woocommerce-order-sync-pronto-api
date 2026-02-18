<?php

declare(strict_types=1);

/**
 * WCOSPA GitHub Updater
 *
 * Checks the GitHub repository for a newer plugin release and integrates with
 * WordPress's native update notification UI — no third-party plugin required.
 *
 * How it works:
 *  1. Hooks into the WordPress transient that drives the Plugins > Updates screen.
 *  2. Calls the GitHub Releases API once per 12 hours (cached via transient).
 *  3. Compares the latest release tag (e.g. "v1.6.12") with the installed version.
 *  4. If a newer version exists, injects a synthetic update record so WordPress
 *     displays the standard "Update available" notice and the "View version x.x
 *     details" thickbox link.
 *  5. When an admin clicks "Update now", WordPress downloads the release ZIP
 *     directly from GitHub and replaces the plugin files.
 *
 * Configuration:
 *  - WCOSPA_GITHUB_REPO  — "owner/repository-name" (set in wcospa.php or wp-config.php)
 *  - WCOSPA_GITHUB_TOKEN — optional Personal Access Token for private repos or to
 *                          avoid GitHub API rate limits (60 req/hour unauthenticated,
 *                          5 000 req/hour authenticated). Set in wp-config.php.
 *
 * @package WCOSPA
 * @version 1.6.12
 */
class WCOSPA_Updater
{
    /**
     * Default GitHub repository (owner/repo).
     * Override by defining WCOSPA_GITHUB_REPO in wp-config.php.
     */
    const DEFAULT_GITHUB_REPO = 'jerryliaustin/woocommerce-order-sync-pronto-api';

    /**
     * Transient key for cached release data.
     */
    const TRANSIENT_KEY = 'wcospa_github_release_check';

    /**
     * Cache duration in seconds (12 hours).
     */
    const CACHE_TTL = 43200;

    /**
     * WordPress plugin basename, e.g. woocommerce-order-sync-pronto-api/wcospa.php
     */
    private static string $plugin_basename = '';

    /**
     * Register all hooks required for the update flow.
     */
    public static function init(): void
    {
        self::$plugin_basename = plugin_basename(WCOSPA_PATH . '../wcospa.php');

        // Inject update data into the WordPress update transient
        add_filter('pre_set_site_transient_update_plugins', [__CLASS__, 'check_for_update']);

        // Provide plugin info for the "View version details" thickbox
        add_filter('plugins_api', [__CLASS__, 'plugin_info'], 10, 3);

        // Clean local cache after a successful update so the next check is fresh
        add_action('upgrader_process_complete', [__CLASS__, 'after_update'], 10, 2);
    }

    /**
     * Compare installed version against the latest GitHub release.
     * Called by WordPress when it checks for plugin updates.
     *
     * @param  object $transient  The update_plugins transient object.
     * @return object             Modified transient with our update info if applicable.
     */
    public static function check_for_update(object $transient): object
    {
        if (empty($transient->checked)) {
            return $transient;
        }

        $release = self::get_latest_release();
        if (!$release || empty($release['tag_name'])) {
            return $transient;
        }

        // Strip leading 'v' from tag, e.g. "v1.6.12" → "1.6.12"
        $latest_version = ltrim($release['tag_name'], 'v');

        if (version_compare($latest_version, WCOSPA_VERSION, '>')) {
            WCOSPA_Logger::info(sprintf(
                'GitHub Updater: new version %s available (installed: %s)',
                $latest_version,
                WCOSPA_VERSION
            ));

            $transient->response[self::$plugin_basename] = (object) [
                'slug'        => dirname(self::$plugin_basename),
                'plugin'      => self::$plugin_basename,
                'new_version' => $latest_version,
                'url'         => $release['html_url'] ?? '',
                'package'     => $release['zipball_url'] ?? '',
                'icons'       => [],
                'banners'     => [],
                'tested'      => '',
                'requires_php' => '8.2',
            ];
        } else {
            WCOSPA_Logger::debug(sprintf(
                'GitHub Updater: installed version %s is up to date (latest: %s)',
                WCOSPA_VERSION,
                $latest_version
            ));
        }

        return $transient;
    }

    /**
     * Supply plugin details for the "View version x.x details" thickbox modal.
     *
     * @param  false|object|array $result  The result object/array (or false).
     * @param  string             $action  The API action being performed.
     * @param  object             $args    Arguments passed to the API.
     * @return false|object                Plugin info object, or false to pass through.
     */
    public static function plugin_info(false|object|array $result, string $action, object $args): false|object|array
    {
        if ($action !== 'plugin_information') {
            return $result;
        }

        if (!isset($args->slug) || $args->slug !== dirname(self::$plugin_basename)) {
            return $result;
        }

        $release = self::get_latest_release();
        if (!$release) {
            return $result;
        }

        $latest_version = ltrim($release['tag_name'] ?? '', 'v');

        return (object) [
            'name'          => 'WooCommerce Order Sync Pronto API',
            'slug'          => dirname(self::$plugin_basename),
            'version'       => $latest_version,
            'author'        => 'Jerry Li',
            'homepage'      => 'https://github.com/' . self::get_repo(),
            'download_link' => $release['zipball_url'] ?? '',
            'sections'      => [
                'description' => 'WooCommerce integration with the Pronto API for automated order synchronisation, shipment tracking, and status management.',
                'changelog'   => self::format_release_body($release['body'] ?? ''),
            ],
            'requires_php'  => '8.2',
            'last_updated'  => $release['published_at'] ?? '',
        ];
    }

    /**
     * After a successful update, delete the cached release data so the next
     * check fetches fresh data from GitHub.
     *
     * @param \WP_Upgrader $upgrader  The upgrader instance.
     * @param array        $hook_extra Extra information about the upgrade action.
     */
    public static function after_update(\WP_Upgrader $upgrader, array $hook_extra): void
    {
        if (
            isset($hook_extra['action'], $hook_extra['type'], $hook_extra['plugins']) &&
            $hook_extra['action'] === 'update' &&
            $hook_extra['type'] === 'plugin' &&
            in_array(self::$plugin_basename, $hook_extra['plugins'], true)
        ) {
            delete_transient(self::TRANSIENT_KEY);
            WCOSPA_Logger::info('GitHub Updater: plugin updated — release cache cleared');
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Fetch the latest release from the GitHub API (cached for CACHE_TTL seconds).
     *
     * @return array|null Decoded release JSON, or null on failure.
     */
    private static function get_latest_release(): ?array
    {
        $cached = get_transient(self::TRANSIENT_KEY);
        if ($cached !== false) {
            return $cached ?: null;
        }

        $api_url = sprintf(
            'https://api.github.com/repos/%s/releases/latest',
            self::get_repo()
        );

        $args = [
            'timeout'    => 15,
            'user-agent' => 'WCOSPA-Updater/' . WCOSPA_VERSION . '; WordPress/' . get_bloginfo('version'),
            'headers'    => ['Accept' => 'application/vnd.github+json'],
        ];

        // Attach token if provided (avoids rate-limit issues and enables private repos)
        $token = defined('WCOSPA_GITHUB_TOKEN') ? WCOSPA_GITHUB_TOKEN : '';
        if (!empty($token)) {
            $args['headers']['Authorization'] = 'Bearer ' . $token;
        }

        $response = wp_remote_get($api_url, $args);

        if (is_wp_error($response)) {
            WCOSPA_Logger::warning(sprintf(
                'GitHub Updater: API request failed — %s',
                $response->get_error_message()
            ));
            // Cache empty result briefly (5 minutes) to avoid hammering GitHub on errors
            set_transient(self::TRANSIENT_KEY, [], 300);
            return null;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            WCOSPA_Logger::warning(sprintf(
                'GitHub Updater: API returned HTTP %d for %s',
                $code,
                $api_url
            ));
            set_transient(self::TRANSIENT_KEY, [], 300);
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $release = json_decode($body, true);

        if (!is_array($release) || empty($release['tag_name'])) {
            WCOSPA_Logger::warning('GitHub Updater: unexpected API response format');
            set_transient(self::TRANSIENT_KEY, [], 300);
            return null;
        }

        set_transient(self::TRANSIENT_KEY, $release, self::CACHE_TTL);
        return $release;
    }

    /**
     * Return the configured GitHub repository slug (owner/repo).
     */
    private static function get_repo(): string
    {
        return defined('WCOSPA_GITHUB_REPO') ? WCOSPA_GITHUB_REPO : self::DEFAULT_GITHUB_REPO;
    }

    /**
     * Convert a GitHub release body (Markdown) to basic HTML for the thickbox.
     * Handles the most common Markdown constructs without a full parser.
     *
     * @param  string $markdown Raw release notes Markdown.
     * @return string           Simple HTML string safe for thickbox display.
     */
    private static function format_release_body(string $markdown): string
    {
        if (empty($markdown)) {
            return '<p>No release notes provided.</p>';
        }

        $html = esc_html($markdown);

        // Headings
        $html = preg_replace('/^### (.+)$/m', '<h4>$1</h4>', $html);
        $html = preg_replace('/^## (.+)$/m', '<h3>$1</h3>', $html);
        $html = preg_replace('/^# (.+)$/m', '<h2>$1</h2>', $html);

        // Bold and inline code
        $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);
        $html = preg_replace('/`(.+?)`/', '<code>$1</code>', $html);

        // List items
        $html = preg_replace('/^- (.+)$/m', '<li>$1</li>', $html);

        // Wrap consecutive <li> blocks in <ul>
        $html = preg_replace('/(<li>.*<\/li>\n?)+/s', '<ul>$0</ul>', $html);

        // Paragraphs (blank-line separated blocks not already in a tag)
        $html = preg_replace('/\n{2,}/', '</p><p>', $html);
        $html = '<p>' . $html . '</p>';

        // Clean up empty paragraphs
        $html = preg_replace('/<p>\s*<\/p>/', '', $html);

        return $html;
    }
}
