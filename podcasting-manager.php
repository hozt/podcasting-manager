<?php
/**
 * Plugin Name: Podcasting Manager
 * Description: A plugin to manage podcasts with GraphQL support
 * Version: 1.0.6
 * Author: Jeffrey Haug
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class PodcastingManager {
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('init', array($this, 'register_post_type'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_box_data'));
        add_action('graphql_register_types', array($this, 'register_graphql_fields'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    public function add_admin_menu() {
        add_options_page('Podcast Settings', 'Podcast Settings', 'manage_options', 'podcast-settings', array($this, 'settings_page'));
    }

    public function register_settings() {
        register_setting('podcast_settings', 'podcast_name', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('podcast_settings', 'podcast_description', array('sanitize_callback' => 'wp_kses_post'));
        register_setting('podcast_settings', 'podcast_image', array('sanitize_callback' => 'esc_url_raw'));
        register_setting('podcast_settings', 'podcast_donation_link', array('sanitize_callback' => 'esc_url_raw'));
        register_setting('podcast_settings', 'podcast_location', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('podcast_settings', 'podcast_hosts', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('podcast_settings', 'podcast_explicit_rating', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('podcast_settings', 'podcast_series', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('podcast_settings', 'podcast_owner', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('podcast_settings', 'podcast_license', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('podcast_settings', 'podcast_trailer', array('sanitize_callback' => 'esc_url_raw'));
        register_setting('podcast_settings', 'podcast_update_frequency', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('podcast_settings', 'podcast_categories', array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('podcast_settings', 'podcast_keywords', array('sanitize_callback' => 'sanitize_text_field'));
    }

    public function settings_page() {
        ?>
        <div class="wrap">
            <h1>Podcast Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('podcast_settings');
                do_settings_sections('podcast_settings');
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="podcast_name">Podcast Name</label></th>
                        <td><input type="text" id="podcast_name" name="podcast_name" value="<?php echo esc_attr(get_option('podcast_name')); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="podcast_description">Description</label></th>
                        <td><textarea id="podcast_description" name="podcast_description" rows="5" cols="50"><?php echo esc_textarea(get_option('podcast_description')); ?></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="podcast_image">Image URL</label></th>
                        <td><input type="url" id="podcast_image" name="podcast_image" value="<?php echo esc_url(get_option('podcast_image')); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="podcast_donation_link">Donation Link</label></th>
                        <td><input type="url" id="podcast_donation_link" name="podcast_donation_link" value="<?php echo esc_url(get_option('podcast_donation_link')); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="podcast_location">Location</label></th>
                        <td><input type="text" id="podcast_location" name="podcast_location" value="<?php echo esc_attr(get_option('podcast_location')); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="podcast_hosts">Hosts</label></th>
                        <td><input type="text" id="podcast_hosts" name="podcast_hosts" value="<?php echo esc_attr(get_option('podcast_hosts')); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="podcast_explicit_rating">Explicit Rating</label></th>
                        <td>
                            <select id="podcast_explicit_rating" name="podcast_explicit_rating">
                                <option value="clean" <?php selected(get_option('podcast_explicit_rating'), 'clean'); ?>>Clean</option>
                                <option value="explicit" <?php selected(get_option('podcast_explicit_rating'), 'explicit'); ?>>Explicit</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="podcast_series">Series</label></th>
                        <td><input type="text" id="podcast_series" name="podcast_series" value="<?php echo esc_attr(get_option('podcast_series')); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="podcast_owner">Owner</label></th>
                        <td><input type="text" id="podcast_owner" name="podcast_owner" value="<?php echo esc_attr(get_option('podcast_owner')); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="podcast_license">License</label></th>
                        <td><input type="text" id="podcast_license" name="podcast_license" value="<?php echo esc_attr(get_option('podcast_license')); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="podcast_trailer">Trailer URL</label></th>
                        <td><input type="url" id="podcast_trailer" name="podcast_trailer" value="<?php echo esc_url(get_option('podcast_trailer')); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="podcast_update_frequency">Update Frequency</label></th>
                        <td><input type="text" id="podcast_update_frequency" name="podcast_update_frequency" value="<?php echo esc_attr(get_option('podcast_update_frequency')); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="podcast_categories">Categories</label></th>
                        <td><input type="text" id="podcast_categories" name="podcast_categories" value="<?php echo esc_attr(get_option('podcast_categories')); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="podcast_keywords">Keywords</label></th>
                        <td><input type="text" id="podcast_keywords" name="podcast_keywords" value="<?php echo esc_attr(get_option('podcast_keywords')); ?>" class="regular-text"></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function register_post_type() {
        $args = array(
            'public' => true,
            'label'  => 'Podcasts',
            'labels' => array(
                'singular_name' => 'Podcast',
                'add_new_item' => 'Add New Podcast',
                'edit_item' => 'Edit Podcast',
                'new_item' => 'New Podcast',
                'view_item' => 'View Podcast',
                'view_items' => 'View Podcasts',
                'search_items' => 'Search Podcasts',
                'not_found' => 'No podcasts found',
                'not_found_in_trash' => 'No podcasts found in trash',
                'all_items' => 'All Podcasts',
                'archives' => 'Podcast Archives',
                'attributes' => 'Podcast Attributes',
                'insert_into_item' => 'Insert into podcast',
                'uploaded_to_this_item' => 'Uploaded to this podcast',
                'featured_image' => 'Podcast Image',
                'set_featured_image' => 'Set podcast image',
                'remove_featured_image' => 'Remove podcast image',
                'use_featured_image' => 'Use as podcast image',
                'filter_items_list' => 'Filter podcasts list',
                'items_list_navigation' => 'Podcasts list navigation',
                'items_list' => 'Podcasts list',
                'item_published' => 'Podcast published',
                'item_published_privately' => 'Podcast published privately',
                'item_reverted_to_draft' => 'Podcast reverted to draft',
                'item_scheduled' => 'Podcast scheduled',
                'item_updated' => 'Podcast updated',
            ),
            'supports' => array('title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'),
            'show_in_graphql' => true,
            'graphql_single_name' => 'podcast',
            'graphql_plural_name' => 'podcasts',
            'graphql_interfaces' => ['ContentNode', 'NodeWithTitle'],
        );
        register_post_type('podcast', $args);
    }

    public function add_meta_boxes() {
        add_meta_box('podcast_meta', 'Podcast Details', array($this, 'render_meta_box'), 'podcast', 'normal', 'high');
    }

    public function render_meta_box($post) {
        wp_nonce_field('podcast_episode_meta', 'podcast_episode_meta_nonce');
        $episode_number = get_post_meta($post->ID, '_episode_number', true);
        $mp3_file = get_post_meta($post->ID, '_mp3_file', true);
        $episode_date = get_post_meta($post->ID, '_episode_date', true);
        $episode_length = get_post_meta($post->ID, '_episode_length', true);
        $file_size = get_post_meta($post->ID, '_file_size', true);
        $transcript = get_post_meta($post->ID, '_transcript', true);
        ?>
        <p>
            <label for="episode_number">Episode Number:</label>
            <input type="number" id="episode_number" name="episode_number" value="<?php echo esc_attr($episode_number); ?>" min="1">
        </p>
        <p>
            <label for="mp3_file">MP3 File URL:</label>
            <input type="url" id="mp3_file" name="mp3_file" value="<?php echo esc_url($mp3_file); ?>" class="widefat">
        </p>
        <p>
            <label for="episode_date">Episode Date:</label>
            <input type="text" id="episode_date" name="episode_date" value="<?php echo esc_attr($episode_date); ?>" class="podcast-datepicker">
        </p>
        <p>
            <label for="episode_length">Episode Length (in seconds):</label>
            <input type="number" id="episode_length" name="episode_length" value="<?php echo esc_attr($episode_length); ?>" min="0">
        </p>
        <p>
            <label for="file_size">File Size (in bytes):</label>
            <input type="number" id="file_size" name="file_size" value="<?php echo esc_attr($file_size); ?>" min="0">
        </p>
        <p>
            <label for="transcript">Transcript:</label>
            <textarea name="transcript" id="transcript" rows="10" class="widefat"><?php echo wp_kses_post($transcript); ?></textarea>
        </p>
        <?php
    }

    public function save_meta_box_data($post_id) {
        if (!isset($_POST['podcast_episode_meta_nonce']) || !wp_verify_nonce($_POST['podcast_episode_meta_nonce'], 'podcast_episode_meta')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $fields = array(
            'episode_number' => 'intval',
            'mp3_file' => 'esc_url_raw',
            'episode_date' => 'sanitize_text_field',
            'episode_length' => 'intval',
            'file_size' => 'intval',
            'transcript' => 'wp_kses_post'
        );

        foreach ($fields as $field => $sanitize_callback) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, '_' . $field, $sanitize_callback($_POST[$field]));
            }
        }
    }

    public function enqueue_admin_scripts($hook) {
        if ('post.php' != $hook && 'post-new.php' != $hook) {
            return;
        }
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_style('jquery-ui-css', '//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
        wp_add_inline_script('jquery-ui-datepicker', '
            jQuery(document).ready(function($) {
                $(".podcast-datepicker").datepicker({
                    dateFormat: "yy-mm-dd"
                });
            });
        ');
    }

    public function register_graphql_fields() {
        register_graphql_fields('podcast', [
            'episodeNumber' => [
                'type' => 'Int',
                'description' => 'The episode number',
                'resolve' => function($post) {
                    return get_post_meta($post->ID, '_episode_number', true);
                }
            ],
            'mp3File' => [
                'type' => 'String',
                'description' => 'The MP3 file URL',
                'resolve' => function($post) {
                    return get_post_meta($post->ID, '_mp3_file', true);
                }
            ],
            'episodeDate' => [
                'type' => 'String',
                'description' => 'The episode date',
                'resolve' => function($post) {
                    return get_post_meta($post->ID, '_episode_date', true);
                }
            ],
            'episodeLength' => [
                'type' => 'Int',
                'description' => 'The episode length in seconds',
                'resolve' => function($post) {
                    return get_post_meta($post->ID, '_episode_length', true);
                }
            ],
            'fileSize' => [
                'type' => 'Int',
                'description' => 'The file size in bytes',
                'resolve' => function($post) {
                    return get_post_meta($post->ID, '_file_size', true);
                }
            ],
            'transcript' => [
                'type' => 'String',
                'description' => 'HTML formatted transcript',
                'resolve' => function($post) {
                    return get_post_meta($post->ID, '_transcript', true);
                }
            ]
        ]);

        register_graphql_field('RootQuery', 'podcastSettings', [
            'type' => 'PodcastSettings',
            'description' => 'Podcast settings',
            'resolve' => function() {
                return [
                    'name' => get_option('podcast_name'),
                    'description' => get_option('podcast_description'),
                    'image' => get_option('podcast_image'),
                    'donationLink' => get_option('podcast_donation_link'),
                    'location' => get_option('podcast_location'),
                    'hosts' => get_option('podcast_hosts'),
                    'explicitRating' => get_option('podcast_explicit_rating'),
                    'series' => get_option('podcast_series'),
                    'owner' => get_option('podcast_owner'),
                    'license' => get_option('podcast_license'),
                    'trailer' => get_option('podcast_trailer'),
                    'updateFrequency' => get_option('podcast_update_frequency'),
                    'categories' => get_option('podcast_categories'),
                    'keywords' => get_option('podcast_keywords')
                ];
            }
        ]);

        register_graphql_object_type('PodcastSettings', [
            'fields' => [
                'name' => ['type' => 'String'],
                'description' => ['type' => 'String'],
                'image' => ['type' => 'String'],
                'donationLink' => ['type' => 'String'],
                'location' => ['type' => 'String'],
                'hosts' => ['type' => 'String'],
                'explicitRating' => ['type' => 'String'],
                'series' => ['type' => 'String'],
                'owner' => ['type' => 'String'],
                'license' => ['type' => 'String'],
                'trailer' => ['type' => 'String'],
                'updateFrequency' => ['type' => 'String'],
                'categories' => ['type' => 'String'],
                'keywords' => ['type' => 'String']
            ]
        ]);
    }
}

new PodcastingManager();
