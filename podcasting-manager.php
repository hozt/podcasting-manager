<?php
/**
 * Plugin Name: Podcasting Manager
 * Description: Manage podcasts with admin interface and GraphQL support.
 * Version:     1.0.8
 * Author: Jeffrey Haug
 */

if (!defined('ABSPATH')) exit;

class PodcastingManager {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('init', [$this, 'register_post_type']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post', [$this, 'save_meta_box_data']);
        add_action('graphql_register_types', [$this, 'register_graphql_fields']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    }

    public function add_admin_menu() {
        add_options_page('Podcast Settings', 'Podcast Settings', 'manage_options', 'podcast-settings', [$this, 'settings_page']);
    }

    public function register_settings() {
        $settings = [
            'podcast_name' => 'sanitize_text_field',
            'podcast_description' => 'wp_kses_post',
            'podcast_image' => 'esc_url_raw',
            'podcast_donation_link' => 'esc_url_raw',
            'podcast_location' => 'sanitize_text_field',
            'podcast_hosts' => 'sanitize_text_field',
            'podcast_explicit_rating' => 'sanitize_text_field',
            'podcast_series' => 'sanitize_text_field',
            'podcast_owner' => 'sanitize_text_field',
            'podcast_license' => 'sanitize_text_field',
            'podcast_trailer' => 'esc_url_raw',
            'podcast_update_frequency' => 'sanitize_text_field',
            'podcast_categories' => 'sanitize_text_field',
            'podcast_keywords' => 'sanitize_text_field',
            'podcast_apple_link' => 'esc_url_raw',
            'podcast_podcasting_index_link' => 'esc_url_raw',
            'podcast_spotify_link' => 'esc_url_raw',
            'podcast_amazon_link' => 'esc_url_raw',
            'podcast_iheart_link' => 'esc_url_raw'
        ];

        foreach ($settings as $setting => $callback) {
            register_setting('podcast_settings', $setting, ['sanitize_callback' => $callback]);
        }
    }

    public function settings_page() {
        ?>
        <div class="wrap">
            <h1>Podcast Settings</h1>
            <form method="post" action="options.php">
                <?php settings_fields('podcast_settings'); ?>

                <table class="form-table">
                    <?php
                    $main_fields = [
                        'podcast_name' => 'Podcast Name',
                        'podcast_description' => 'Description',
                        'podcast_image' => 'Image URL',
                        'podcast_donation_link' => 'Donation Link',
                        'podcast_location' => 'Location',
                        'podcast_hosts' => 'Hosts',
                        'podcast_explicit_rating' => 'Explicit Rating',
                        'podcast_series' => 'Series',
                        'podcast_owner' => 'Owner',
                        'podcast_license' => 'License',
                        'podcast_trailer' => 'Trailer URL',
                        'podcast_update_frequency' => 'Update Frequency',
                        'podcast_categories' => 'Categories',
                        'podcast_keywords' => 'Keywords'
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
                    $link_fields = [
                        'podcast_apple_link' => 'Apple Podcasts Link',
                        'podcast_podcasting_index_link' => 'Podcasting Index Link',
                        'podcast_spotify_link' => 'Spotify Link',
                        'podcast_amazon_link' => 'Amazon Link',
                        'podcast_iheart_link' => 'iHeart Link'
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

    public function register_post_type() {
        register_post_type('podcast', [
            'public' => true,
            'label'  => 'Podcasts',
            'labels' => [
                'singular_name' => 'Podcast',
                'add_new_item' => 'Add New Podcast',
            ],
            'supports' => ['title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'],
            'show_in_graphql' => true,
            'graphql_single_name' => 'podcast',
            'graphql_plural_name' => 'podcasts',
            'graphql_interfaces' => ['ContentNode', 'NodeWithTitle'],
        ]);
    }

    public function add_meta_boxes() {
        add_meta_box('podcast_meta', 'Podcast Details', [$this, 'render_meta_box'], 'podcast', 'normal', 'high');
    }

    public function render_meta_box($post) {
        wp_nonce_field('podcast_episode_meta', 'podcast_episode_meta_nonce');
        $fields = [
            'episode_number' => 'Episode Number',
            'mp3_file' => 'MP3 URL',
            'episode_date' => 'Episode Date',
            'episode_length' => 'Length (seconds)',
            'file_size' => 'File Size (bytes)',
            'transcript' => 'Transcript',
        ];
        foreach ($fields as $field => $label) {
            $value = get_post_meta($post->ID, "_$field", true);
            echo "<p><label for='$field'>$label</label><br>
            <input type='text' class='widefat' name='$field' id='$field' value='".esc_attr($value)."'></p>";
        }
    }

    public function save_meta_box_data($post_id) {
        if (!isset($_POST['podcast_episode_meta_nonce']) || !wp_verify_nonce($_POST['podcast_episode_meta_nonce'], 'podcast_episode_meta')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $fields = ['episode_number' => 'intval', 'mp3_file' => 'esc_url_raw',
                   'episode_date' => 'sanitize_text_field', 'episode_length' => 'intval',
                   'file_size' => 'intval', 'transcript' => 'wp_kses_post'];

        foreach ($fields as $field => $cb) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, "_$field", call_user_func($fields[$field], $_POST[$field]));
            }
        }
    }

    public function register_graphql_fields() {

        register_graphql_object_type('PodcastSettings', [
            'fields' => [
                'podcastName' => ['type' => 'String'],
                'podcastDescription' => ['type' => 'String'],
                'podcastImage' => ['type' => 'String'],
                'podcastDonationLink' => ['type' => 'String'],
                'podcastLocation' => ['type' => 'String'],
                'podcastHosts' => ['type' => 'String'],
                'podcastExplicitRating' => ['type' => 'String'],
                'podcastSeries' => ['type' => 'String'],
                'podcastOwner' => ['type' => 'String'],
                'podcastLicense' => ['type' => 'String'],
                'podcastTrailer' => ['type' => 'String'],
                'podcastUpdateFrequency' => ['type' => 'String'],
                'podcastCategories' => ['type' => 'String'],
                'podcastKeywords' => ['type' => 'String'],
                // added link fields below
                'podcastAppleLink' => ['type' => 'String'],
                'podcastPodcastingIndexLink' => ['type' => 'String'],
                'podcastSpotifyLink' => ['type' => 'String'],
                'podcastAmazonLink' => ['type' => 'String'],
                'podcastIheartLink' => ['type' => 'String'],
            ]
        ]);

        register_graphql_field('RootQuery', 'podcastSettings', [
            'type' => 'PodcastSettings',
            'description' => 'Podcast settings data',
            'resolve' => function () {
                return [
                    'podcastName' => get_option('podcast_name'),
                    'podcastDescription' => get_option('podcast_description'),
                    'podcastImage' => get_option('podcast_image'),
                    'podcastDonationLink' => get_option('podcast_donation_link'),
                    'podcastLocation' => get_option('podcast_location'),
                    'podcastHosts' => get_option('podcast_hosts'),
                    'podcastExplicitRating' => get_option('podcast_explicit_rating'),
                    'podcastSeries' => get_option('podcast_series'),
                    'podcastOwner' => get_option('podcast_owner'),
                    'podcastLicense' => get_option('podcast_license'),
                    'podcastTrailer' => get_option('podcast_trailer'),
                    'podcastUpdateFrequency' => get_option('podcast_update_frequency'),
                    'podcastCategories' => get_option('podcast_categories'),
                    'podcastKeywords' => get_option('podcast_keywords'),
                    'podcastAppleLink' => get_option('podcast_apple_link'),
                    'podcastPodcastingIndexLink' => get_option('podcast_podcasting_index_link'),
                    'podcastSpotifyLink' => get_option('podcast_spotify_link'),
                    'podcastAmazonLink' => get_option('podcast_amazon_link'),
                    'podcastIheartLink' => get_option('podcast_iheart_link'),
                ];
            }
        ]);

        register_graphql_fields('podcast', [
            'episodeNumber' => [
                'type' => 'Int',
                'resolve' => fn($post) => (int)get_post_meta($post->ID, '_episode_number', true),
            ],
            'mp3File' => [
                'type' => 'String',
                'resolve' => fn($post) => get_post_meta($post->ID, '_mp3_file', true),
            ],
            'episodeDate' => [
                'type' => 'String',
                'resolve' => fn($post) => get_post_meta($post->ID, '_episode_date', true),
            ],
            'episodeLength' => [
                'type' => 'Int',
                'resolve' => fn($post) => (int)get_post_meta($post->ID, '_episode_length', true),
            ],
            'fileSize' => [
                'type' => 'Int',
                'resolve' => fn($post) => (int)get_post_meta($post->ID, '_file_size', true),
            ],
            'transcript' => [
                'type' => 'String',
                'resolve' => fn($post) => get_post_meta($post->ID, '_transcript', true),
            ],
        ]);
    }

    public function enqueue_admin_scripts($hook) {
        if (!in_array($hook, ['post.php', 'post-new.php'])) return;
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_style('jquery-ui', '//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
        wp_add_inline_script('jquery-ui-datepicker', 'jQuery(".podcast-datepicker").datepicker({dateFormat: "yy-mm-dd"});');
    }

}

new PodcastingManager();
