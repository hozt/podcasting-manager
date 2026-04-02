<?php
/**
 * Plugin Name: Podcasting Manager
 * Description: Manage podcasts with admin interface and GraphQL support.
 * Version:     1.0.8
 * Author:      Jeffrey Haug
 * Author URI:  https://hozt.com
 *
 * Provides a custom post type for podcast episodes, a settings page for
 * podcast-level metadata, per-episode meta boxes, and WPGraphQL integration
 * so headless/decoupled frontends can query all podcast data via GraphQL.
 */

// Prevent direct file access outside of WordPress.
if (!defined('ABSPATH')) exit;

class PodcastingManager {

    /**
     * Boot the plugin by registering all WordPress hooks.
     *
     * Hook order matters here:
     *  - 'init'              fires early enough to register the post type before
     *                        rewrite rules or REST/GraphQL schema are built.
     *  - 'admin_menu'        adds the settings page to Settings > Podcast Settings.
     *  - 'admin_init'        registers option names so WordPress can validate and
     *                        save them via the Options API.
     *  - 'add_meta_boxes'    attaches the episode-details meta box to the podcast
     *                        edit screen.
     *  - 'save_post'         persists episode meta when the editor saves.
     *  - 'graphql_register_types' extends the WPGraphQL schema with podcast-specific
     *                        types and fields (requires WPGraphQL plugin).
     *  - 'admin_enqueue_scripts' loads jQuery UI Datepicker on post edit screens so
     *                        the episode date field has a calendar picker.
     */
    public function __construct() {
        add_action('admin_menu',            [$this, 'add_admin_menu']);
        add_action('admin_init',            [$this, 'register_settings']);
        add_action('init',                  [$this, 'register_post_type']);
        add_action('add_meta_boxes',        [$this, 'add_meta_boxes']);
        add_action('save_post',             [$this, 'save_meta_box_data']);
        add_action('graphql_register_types',[$this, 'register_graphql_fields']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    }

    // -------------------------------------------------------------------------
    // Admin Settings
    // -------------------------------------------------------------------------

    /**
     * Register the "Podcast Settings" sub-page under WordPress Settings menu.
     * Requires the 'manage_options' capability (i.e. Administrator role).
     */
    public function add_admin_menu() {
        add_options_page(
            'Podcast Settings',          // <title> tag
            'Podcast Settings',          // Menu label
            'manage_options',            // Required capability
            'podcast-settings',          // Menu slug
            [$this, 'settings_page']     // Render callback
        );
    }

    /**
     * Register every podcast option with the WordPress Settings API.
     *
     * Each option is bound to a sanitize callback so that user-supplied values
     * are cleaned before being written to the database. URL fields use
     * esc_url_raw() (safe for storage), text fields use sanitize_text_field(),
     * and the description field uses wp_kses_post() to allow safe HTML.
     *
     * All options belong to the 'podcast_settings' option group so that
     * settings_fields('podcast_settings') in the form renders the correct
     * nonce and hidden inputs.
     */
    public function register_settings() {
        $settings = [
            // General podcast metadata
            'podcast_name'             => 'sanitize_text_field',
            'podcast_description'      => 'wp_kses_post',        // HTML allowed
            'podcast_image'            => 'esc_url_raw',
            'podcast_donation_link'    => 'esc_url_raw',
            'podcast_location'         => 'sanitize_text_field',
            'podcast_hosts'            => 'sanitize_text_field',
            'podcast_explicit_rating'  => 'sanitize_text_field',
            'podcast_series'           => 'sanitize_text_field',
            'podcast_owner'            => 'sanitize_text_field',
            'podcast_license'          => 'sanitize_text_field',
            'podcast_trailer'          => 'esc_url_raw',
            'podcast_update_frequency' => 'sanitize_text_field',
            'podcast_categories'       => 'sanitize_text_field',
            'podcast_keywords'         => 'sanitize_text_field',
            // Platform directory links
            'podcast_apple_link'            => 'esc_url_raw',
            'podcast_podcasting_index_link' => 'esc_url_raw',
            'podcast_spotify_link'          => 'esc_url_raw',
            'podcast_amazon_link'           => 'esc_url_raw',
            'podcast_iheart_link'           => 'esc_url_raw',
        ];

        foreach ($settings as $setting => $callback) {
            register_setting('podcast_settings', $setting, ['sanitize_callback' => $callback]);
        }
    }

    /**
     * Render the Podcast Settings admin page.
     *
     * The form posts to options.php (the core WordPress options handler).
     * settings_fields() outputs the option group nonce and hidden fields.
     * Fields are split into two visual sections:
     *   1. General metadata (name, description, image, etc.)
     *   2. Platform directory links (Apple, Spotify, etc.)
     */
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1>Podcast Settings</h1>
            <form method="post" action="options.php">
                <?php settings_fields('podcast_settings'); ?>

                <table class="form-table">
                    <?php
                    // General podcast metadata fields
                    $main_fields = [
                        'podcast_name'             => 'Podcast Name',
                        'podcast_description'      => 'Description',
                        'podcast_image'            => 'Image URL',
                        'podcast_donation_link'    => 'Donation Link',
                        'podcast_location'         => 'Location',
                        'podcast_hosts'            => 'Hosts',
                        'podcast_explicit_rating'  => 'Explicit Rating',
                        'podcast_series'           => 'Series',
                        'podcast_owner'            => 'Owner',
                        'podcast_license'          => 'License',
                        'podcast_trailer'          => 'Trailer URL',
                        'podcast_update_frequency' => 'Update Frequency',
                        'podcast_categories'       => 'Categories',
                        'podcast_keywords'         => 'Keywords',
                    ];

                    foreach ($main_fields as $field => $label) {
                        $value = esc_attr(get_option($field));
                        printf(
                            '<tr>
                                <th scope="row"><label for="%1$s">%2$s</label></th>
                                <td><input type="text" id="%1$s" name="%1$s" value="%3$s" class="regular-text"></td>
                            </tr>',
                            esc_attr($field), esc_html($label), esc_attr($value)
                        );
                    }
                    ?>
                </table>

                <h2>Podcast Links</h2>

                <table class="form-table">
                    <?php
                    // Platform directory link fields (type="url" for browser validation)
                    $link_fields = [
                        'podcast_apple_link'            => 'Apple Podcasts Link',
                        'podcast_podcasting_index_link' => 'Podcasting Index Link',
                        'podcast_spotify_link'          => 'Spotify Link',
                        'podcast_amazon_link'           => 'Amazon Link',
                        'podcast_iheart_link'           => 'iHeart Link',
                    ];

                    foreach ($link_fields as $field => $label) {
                        $value = esc_attr(get_option($field));
                        printf(
                            '<tr>
                                <th scope="row"><label for="%1$s">%2$s</label></th>
                                <td><input type="url" id="%1$s" name="%1$s" value="%3$s" class="regular-text"></td>
                            </tr>',
                            esc_attr($field), esc_html($label), esc_attr($value)
                        );
                    }
                    ?>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Custom Post Type
    // -------------------------------------------------------------------------

    /**
     * Register the 'podcast' custom post type.
     *
     * The post type is public so it gets its own URL structure and appears in
     * the admin sidebar. WPGraphQL-specific keys expose it in the GraphQL
     * schema under the 'podcast' / 'podcasts' names and attach it to the
     * ContentNode and NodeWithTitle interfaces so standard title/content
     * fields are available without extra registration.
     */
    public function register_post_type() {
        register_post_type('podcast', [
            'public'  => true,
            'label'   => 'Podcasts',
            'labels'  => [
                'singular_name' => 'Podcast',
                'add_new_item'  => 'Add New Podcast',
            ],
            // Built-in supports: title, body editor, featured image, excerpt,
            // and arbitrary custom fields (accessible via the meta box below).
            'supports'            => ['title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'],
            // WPGraphQL registration
            'show_in_graphql'     => true,
            'graphql_single_name' => 'podcast',
            'graphql_plural_name' => 'podcasts',
            'graphql_interfaces'  => ['ContentNode', 'NodeWithTitle'],
        ]);
    }

    // -------------------------------------------------------------------------
    // Episode Meta Box
    // -------------------------------------------------------------------------

    /**
     * Register the "Podcast Details" meta box on the podcast edit screen.
     * Placed in the 'normal' context at 'high' priority so it appears near
     * the top of the page, below the title.
     */
    public function add_meta_boxes() {
        add_meta_box(
            'podcast_meta',         // Unique ID
            'Podcast Details',      // Box title
            [$this, 'render_meta_box'],
            'podcast',              // Post type
            'normal',
            'high'
        );
    }

    /**
     * Render the episode details meta box fields.
     *
     * Outputs a nonce for CSRF protection and a text input for each episode
     * field. The transcript field uses the same single-line input as the
     * others; consider swapping it for a <textarea> if transcripts are long.
     *
     * Meta keys are stored with a leading underscore (e.g. '_episode_number')
     * so WordPress hides them from the default Custom Fields UI.
     *
     * @param WP_Post $post The current post object.
     */
    public function render_meta_box($post) {
        wp_nonce_field('podcast_episode_meta', 'podcast_episode_meta_nonce');

        $fields = [
            'episode_number' => 'Episode Number',
            'mp3_file'       => 'MP3 URL',
            'episode_date'   => 'Episode Date',
            'episode_length' => 'Length (seconds)',
            'file_size'      => 'File Size (bytes)',
            'transcript'     => 'Transcript',
        ];

        foreach ($fields as $field => $label) {
            $value = get_post_meta($post->ID, "_$field", true);
            echo "<p><label for='$field'>$label</label><br>
            <input type='text' class='widefat' name='$field' id='$field' value='".esc_attr($value)."'></p>";
        }
    }

    /**
     * Persist episode meta when the post is saved.
     *
     * Security checks (in order):
     *  1. Nonce verification — guards against CSRF.
     *  2. Autosave bail-out — prevents wiping data during WordPress autosaves.
     *  3. Capability check — ensures the current user can edit this post.
     *
     * Each field is sanitized with a dedicated callback before being written
     * to post meta. Integer fields (episode_number, episode_length, file_size)
     * are cast via intval(); URL fields use esc_url_raw(); the transcript
     * allows safe HTML via wp_kses_post().
     *
     * @param int $post_id The ID of the post being saved.
     */
    public function save_meta_box_data($post_id) {
        // Verify the nonce to prevent CSRF attacks.
        if (!isset($_POST['podcast_episode_meta_nonce']) ||
            !wp_verify_nonce($_POST['podcast_episode_meta_nonce'], 'podcast_episode_meta')) {
            return;
        }

        // Skip autosaves — WordPress fires save_post during autosave, but we
        // don't want to process potentially incomplete form data at that point.
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

        // Confirm the current user has permission to edit this post.
        if (!current_user_can('edit_post', $post_id)) return;

        $fields = [
            'episode_number' => 'intval',
            'mp3_file'       => 'esc_url_raw',
            'episode_date'   => 'sanitize_text_field',
            'episode_length' => 'intval',
            'file_size'      => 'intval',
            'transcript'     => 'wp_kses_post',
        ];

        foreach ($fields as $field => $cb) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, "_$field", call_user_func($fields[$field], $_POST[$field]));
            }
        }
    }

    // -------------------------------------------------------------------------
    // WPGraphQL Integration
    // -------------------------------------------------------------------------

    /**
     * Extend the WPGraphQL schema with podcast-specific types and fields.
     *
     * Three registrations are made:
     *
     * 1. PodcastSettings object type — a flat type holding all global podcast
     *    settings (name, description, platform links, etc.) read from the
     *    WordPress Options table.
     *
     * 2. RootQuery.podcastSettings field — exposes PodcastSettings at the top
     *    level of every GraphQL query so clients can fetch global settings
     *    alongside episode data in a single request.
     *
     * 3. Per-episode fields on the 'podcast' type — registers episodeNumber,
     *    mp3File, episodeDate, episodeLength, fileSize, and transcript as
     *    first-class GraphQL fields resolved from post meta. This makes them
     *    available on any podcast node returned by WPGraphQL (e.g. inside a
     *    podcasts { nodes { ... } } query).
     *
     * Requires the WPGraphQL plugin to be active; the 'graphql_register_types'
     * action will not fire if WPGraphQL is not installed.
     */
    public function register_graphql_fields() {

        // --- Global podcast settings type ---
        register_graphql_object_type('PodcastSettings', [
            'fields' => [
                'name'                      => ['type' => 'String'],
                'description'               => ['type' => 'String'],
                'image'                     => ['type' => 'String'],
                'donationLink'              => ['type' => 'String'],
                'location'                  => ['type' => 'String'],
                'hosts'                     => ['type' => 'String'],
                'explicitRating'            => ['type' => 'String'],
                'series'                    => ['type' => 'String'],
                'owner'                     => ['type' => 'String'],
                'license'                   => ['type' => 'String'],
                'trailer'                   => ['type' => 'String'],
                'updateFrequency'           => ['type' => 'String'],
                'categories'                => ['type' => 'String'],
                'keywords'                  => ['type' => 'String'],
                // Platform directory links
                'podcastAppleLink'          => ['type' => 'String'],
                'podcastPodcastingIndexLink'=> ['type' => 'String'],
                'podcastSpotifyLink'        => ['type' => 'String'],
                'podcastAmazonLink'         => ['type' => 'String'],
                'podcastIheartLink'         => ['type' => 'String'],
            ]
        ]);

        // --- Root-level query field that returns all podcast settings ---
        register_graphql_field('RootQuery', 'podcastSettings', [
            'type'        => 'PodcastSettings',
            'description' => 'Global podcast settings stored in WordPress options.',
            'resolve'     => function () {
                return [
                    'name'                       => get_option('podcast_name'),
                    'description'                => get_option('podcast_description'),
                    'image'                      => get_option('podcast_image'),
                    'donationLink'               => get_option('podcast_donation_link'),
                    'location'                   => get_option('podcast_location'),
                    'hosts'                      => get_option('podcast_hosts'),
                    'explicitRating'             => get_option('podcast_explicit_rating'),
                    'series'                     => get_option('podcast_series'),
                    'owner'                      => get_option('podcast_owner'),
                    'license'                    => get_option('podcast_license'),
                    'trailer'                    => get_option('podcast_trailer'),
                    'updateFrequency'            => get_option('podcast_update_frequency'),
                    'categories'                 => get_option('podcast_categories'),
                    'keywords'                   => get_option('podcast_keywords'),
                    'podcastAppleLink'           => get_option('podcast_apple_link'),
                    'podcastPodcastingIndexLink' => get_option('podcast_podcasting_index_link'),
                    'podcastSpotifyLink'         => get_option('podcast_spotify_link'),
                    'podcastAmazonLink'          => get_option('podcast_amazon_link'),
                    'podcastIheartLink'          => get_option('podcast_iheart_link'),
                ];
            }
        ]);

        // --- Per-episode fields added to every podcast post node ---
        register_graphql_fields('podcast', [
            'episodeNumber' => [
                'type'    => 'Int',
                'resolve' => fn($post) => (int)get_post_meta($post->ID, '_episode_number', true),
            ],
            'mp3File' => [
                'type'    => 'String',
                'resolve' => fn($post) => get_post_meta($post->ID, '_mp3_file', true),
            ],
            'episodeDate' => [
                'type'    => 'String',
                'resolve' => fn($post) => get_post_meta($post->ID, '_episode_date', true),
            ],
            'episodeLength' => [
                'type'    => 'Int',  // Duration in seconds
                'resolve' => fn($post) => (int)get_post_meta($post->ID, '_episode_length', true),
            ],
            'fileSize' => [
                'type'    => 'Int',  // Size in bytes
                'resolve' => fn($post) => (int)get_post_meta($post->ID, '_file_size', true),
            ],
            'transcript' => [
                'type'    => 'String',
                'resolve' => fn($post) => get_post_meta($post->ID, '_transcript', true),
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // Admin Scripts
    // -------------------------------------------------------------------------

    /**
     * Enqueue jQuery UI Datepicker on post edit screens.
     *
     * Only loaded on post.php and post-new.php to avoid unnecessary asset
     * loading on other admin pages. The inline script initialises any input
     * with the 'podcast-datepicker' class, storing the date as yyyy-mm-dd so
     * it is consistent with the ISO 8601 format expected by podcast feeds.
     *
     * Note: the episode date input in render_meta_box does not currently carry
     * the 'podcast-datepicker' class — add class='podcast-datepicker' to that
     * input to activate the calendar picker.
     *
     * @param string $hook The current admin page hook (e.g. 'post.php').
     */
    public function enqueue_admin_scripts($hook) {
        if (!in_array($hook, ['post.php', 'post-new.php'])) return;

        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_style('jquery-ui', '//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');

        // Initialise all inputs with the podcast-datepicker class.
        wp_add_inline_script(
            'jquery-ui-datepicker',
            'jQuery(".podcast-datepicker").datepicker({dateFormat: "yy-mm-dd"});'
        );
    }

}

// Instantiate the plugin. WordPress calls the constructor and registers all hooks.
new PodcastingManager();
