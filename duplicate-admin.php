<?php
/*
Plugin Name: Duplicate Admin
Description: Adds a Duplicate Admin menu item that shows a list of duplicate media items and the posts they're linked to.
Version: 1.0
Author: Jon Skinner
*/

use DeliciousBrains\WPMDB\Common\Error\ErrorLog;

if (!defined('ABSPATH')) {
    exit;
}

class DuplicateAdmin
{
    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_post_delete_detached_media', array($this, 'delete_detached_media'));
        add_action('wp_ajax_delete_detached_media', array($this, 'delete_detached_media'));
        add_action('wp_ajax_nopriv_delete_detached_media', array($this, 'delete_detached_media'));
        add_action('wp_ajax_delete_single_attachment', array($this, 'delete_single_attachment'));
        add_action('wp_ajax_nopriv_delete_single_attachment', array($this, 'delete_single_attachment'));
        add_action('wp_ajax_delete_batch_attachments', array($this, 'delete_batch_attachments'));
        add_action('wp_ajax_nopriv_delete_batch_attachments', array($this, 'delete_batch_attachments'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    public function enqueue_scripts()
    {
        wp_enqueue_script('jquery');
        wp_enqueue_style('duplicate-admin', plugin_dir_url(__FILE__) . 'styles.css');

        // Localize script to pass AJAX URL and nonce
        wp_enqueue_script('duplicate-admin-ajax', plugin_dir_url(__FILE__) . 'duplicate-admin.js', array('jquery'), null, true);
        wp_localize_script('duplicate-admin-ajax', 'my_ajax_object', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'security' => wp_create_nonce('my_ajax_nonce')
        ));
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
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="delete_detached_media">
                <?php wp_nonce_field('delete_detached_media_nonce', 'delete_detached_media_nonce_field'); ?>
                <label for="numberposts">Number of detached media items to delete:</label>
                <input type="number" name="numberposts" id="numberposts" value="10" min="1">
                <input type="submit" class="button button-primary" value="Delete Detached Media Items">
            </form>
            <table class="widefat fixed" cellspacing="0">
                <thead>
                    <tr>
                        <th>Post ID</th>
                        <th>Post Title</th>
                        <th>Attachment ID</th>
                        <th>
                            <button class="delete-all-attachments">Delete all duplicates</button>
                            <button class="delete-all-detached">Delete all detached</button>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $paged = isset($_GET['paged']) ? intval($_GET['paged']) : 1;

                    $args = array(
                        'post_type' => 'product',
                        'posts_per_page' => -1,
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

                    ?>
                                        <tr>
                                            <td class="post-id-cell"><?php echo esc_html($product->get_id()); ?></td>
                                            <td><?php echo esc_html($product->get_name()); ?></td>
                                            <?php
                                            $thumb = get_post_thumbnail_id($product->get_id());
                                            if ($thumb != $attachment->ID) {
                                                echo '<td class="duplicate">' . esc_html($meta_data["file"]) . '</td>';
                                            } else {
                                                echo '<td>' . esc_html($meta_data["file"]) . '</td>';
                                            } ?>
                                            <td><button class="delete-attachment" data-attachment-id="<?php echo $attachment->ID; ?>">Delete file</button></td>
                                        </tr>
                    <?php
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
        $numberposts = isset($_POST['numberposts']) ? intval($_POST['numberposts']) : 10;

        // Get all media items
        $args = array(
            'post_type' => 'attachment',
            'post_status' => 'any',
            'posts_per_page' => $numberposts,
            'post_parent' => 0,
        );

        $attachments = get_posts($args);

        foreach ($attachments as $attachment) {
            // Delete the attachment and its thumbnails
            $this->initiateDelete($attachment->ID);
        }


        if (wp_doing_ajax()) {
            // AJAX response
            wp_send_json_success(__('Detached media items deleted successfully', 'textdomain'));
        } else {
            // Non-AJAX response: redirect back to the admin page with a success message
            wp_redirect(add_query_arg('deleted', 'true', admin_url('admin.php?page=duplicate-admin')));
            exit;
        }
    }

    public function delete_single_attachment()
    {
        check_ajax_referer('my_ajax_nonce', 'security');

        $attachmentId = $_POST['attachmentId'];

        $this->initiateDelete($attachmentId);
    }

    public function delete_batch_attachments()
    {
        $attachmentIds = $_POST['attachmentIds'];
        foreach ($attachmentIds as $attachmentId) {
            $this->initiateDelete($attachmentId);
        }
    }

    public function initiateDelete($attachmentId)
    {
        wp_delete_attachment($attachmentId, true);
    }
}

// Instantiate the class
new DuplicateAdmin();
?>