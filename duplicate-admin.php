<?php
/*
Plugin Name: Duplicate Admin
Description: Adds a Duplicate Admin menu item that shows a list of duplicate media items and the posts they're linked to.
Version: 1.0
Author: Jon Skinner
*/

if (!defined('ABSPATH')) {
    exit;
}

class DuplicateAdmin
{
    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_post_delete_detached_media', array($this, 'delete_detached_media'));
        add_action('wp_ajax_my_ajax_action', array($this, 'handle_ajax_request'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    public function enqueue_scripts()
    {
        wp_enqueue_script('jquery');
        wp_enqueue_style('duplicate-admin', plugin_dir_url(__FILE__) . 'styles.css');
    }

    public function add_admin_menu()
    {
        add_menu_page(
            'Duplicate Admin',
            'Duplicate Admin',
            'manage_options',
            'duplicate-admin',
            array($this, 'display_media_items'),
            'dashicons-admin-media',
            6
        );
    }

    public function display_media_items()
    {
?>
        <div class="wrap">
            <h1>Media Items Linked to Posts</h1>
            <form method="post" action="' . admin_url('admin-post.php') . '">
                <input type="hidden" name="action" value="delete_detached_media">
                <?php

                wp_nonce_field('delete_detached_media_nonce', 'delete_detached_media_nonce_field');
                ?>
                <label for="numberposts">Number of detached media items to delete:</label>
                <input type="number" name="numberposts" id="numberposts" value="10" min="1">
                <input type="submit" class="button button-primary" value="Delete Detached Media Items">
            </form>
            <button id="ajax-button" class="button button-primary">Delete Duplicate Media Items</button>
            <table class="widefat fixed" cellspacing="0">
                <thead>
                    <tr>
                        <th>Post ID</th>
                        <th>Post Title</th>
                        <th>Attachment ID</th>
                    </tr>
                </thead>
                <tbody>

                    <?php
                    $paged = isset($_GET['paged']) ? intval($_GET['paged']) : 1;

                    $args = array(
                        'post_type' => 'product',
                        'posts_per_page' => 10,
                        'paged' => $paged,
                        'meta_query' => array(
                            array(
                                'key' => '_thumbnail_id',
                                'compare' => 'EXISTS',
                            ),
                        ),
                    );

                    $loop = new WP_Query($args);

                    if ($loop->have_posts()) {
                        while ($loop->have_posts()) : $loop->the_post();

                            $product = wc_get_product($loop->post->ID);

                            if ($product) {

                                $args = array(
                                    'post_parent'    => $product->get_id(),
                                    'post_type'      => 'attachment',
                                    'numberposts'    => -1,
                                    'orderby'        => 'date',
                                    'order'          => 'DESC',
                                );

                                $attachments = get_posts($args);

                                if ($attachments) {
                                    foreach ($attachments as $attachment) {
                                        $meta_data = wp_get_attachment_metadata($attachment->ID, false);
                                        $filename = esc_html($meta_data["file"]);

                                        // Use a regular expression to remove the `-num.ext` part
                                        $cleanedFilename = preg_replace('/-\d+\.[^.]+$/', '', $filename);
                                        
                                        
                    ?>

                                        <tr>
                                            <td class="post-id-cell"><?php echo esc_html($product->get_id()); ?></td>
                                            <td><?php echo esc_html($product->get_name()); ?></td>

                        <?php
                                        if (preg_match('/-\d+\.jpeg$/', $meta_data["file"]) || preg_match('/-\d+\.jpg$/', $meta_data["file"]) || preg_match('/-\d+\.png$/', $meta_data["file"])) {
                                            echo '<td class="duplicate">' . esc_html($meta_data["file"]) . '</td>';
                                        } else {
                                            echo '<td>' . esc_html($meta_data["file"]) . '</td>';
                                        }

                                        echo '</tr>';
                                    }
                                }
                            }
                        endwhile;
                    } else {
                        echo '<tr><td colspan="3">No media items found.</td></tr>';
                    }

                        ?>


                </tbody>
            </table>
        </div>
<?php

        echo paginate_links(array(
            'base' => add_query_arg('paged', '%#%'),
            'total'   => $loop->max_num_pages,
            'current' => $paged,
            'mid_size' => 2,
            'prev_text' => __('Previous', 'textdomain'),
            'next_text' => __('Next', 'textdomain'),
        ));

        wp_reset_postdata();
    }

    public function delete_detached_media()
    {
        // Check nonce for security
        if (!isset($_POST['delete_detached_media_nonce_field']) || !wp_verify_nonce($_POST['delete_detached_media_nonce_field'], 'delete_detached_media_nonce')) {
            wp_die('Nonce verification failed');
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die('You do not have sufficient permissions to access this page.');
        }

        // Get all media items
        $args = array(
            'post_type' => 'attachment',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'post_parent' => 0,
        );

        $attachments = get_posts($args);

        foreach ($attachments as $attachment) {
            // Delete the attachment and its thumbnails
            wp_delete_attachment($attachment->ID, true);
        }

        // Redirect back to the admin page with a success message
        wp_redirect(admin_url('admin.php?page=duplicate-admin&deleted=1'));
        exit;
    }

    public function handle_ajax_request()
    {
        check_ajax_referer('my_ajax_nonce', 'security');

        // Your PHP logic here
        $response = 'AJAX action handled successfully';

        echo $response;
        wp_die();
    }

    public function initiateDelete($attachment, $product)
    {
        $meta_data = wp_get_attachment_metadata($attachment->ID, false);
        if (preg_match('/-\d+\.jpeg$/', $meta_data["file"]) || preg_match('/-\d+\.jpg$/', $meta_data["file"]) || preg_match('/-\d+\.png$/', $meta_data["file"])) {
            wp_delete_attachment($attachment->ID, true);
        } else {
            set_post_thumbnail($product->get_id(), $attachment->ID);
        }
    }
}

// Instantiate the class
new DuplicateAdmin();
