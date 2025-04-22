<?php
/*
Plugin Name: Markdown Post Creator
Description: A plugin to create posts from uploaded Markdown files with featured images.
Version: 1.0
Author: Mirzalazuardi Hermawan
*/

// Hook into the REST API initialization
add_action('rest_api_init', 'register_custom_api_endpoints');

function register_custom_api_endpoints() {
    // Register the custom endpoint
    // http://your-site.com/wp-json/markdown-post-creator/v1/upload-markdown
    register_rest_route('markdown-post-creator/v1', '/upload-markdown', array(
        'methods'             => 'POST',
        'callback'            => 'handle_markdown_file_upload_with_image',
        'permission_callback' => function () {
            return current_user_can('edit_posts'); // Restrict access to users who can edit posts
        },
    ));
}

function handle_markdown_file_upload_with_image($request) {
    // Step 1: Check if a file was uploaded
    if (empty($_FILES['markdown_file'])) {
        return new WP_Error('missing_file', 'No file uploaded.', array('status' => 400));
    }

    $uploaded_file = $_FILES['markdown_file'];

    // Step 2: Validate the file extension
    $file_extension = pathinfo($uploaded_file['name'], PATHINFO_EXTENSION);
    if (!in_array(strtolower($file_extension), ['md', 'markdown'])) {
        return new WP_Error('invalid_file_type', 'Only Markdown files (.md or .markdown) are allowed.', array('status' => 400));
    }

    // Step 3: Validate the file size (optional)
    $max_size = 5 * 1024 * 1024; // 5MB
    if ($uploaded_file['size'] > $max_size) {
        return new WP_Error('file_too_large', 'File size exceeds the maximum limit of 5MB.', array('status' => 400));
    }

    // Step 4: Read the file content
    $file_content = file_get_contents($uploaded_file['tmp_name']);
    if (!$file_content) {
        return new WP_Error('file_read_error', 'Failed to read the file content.', array('status' => 500));
    }

    // Step 5: Convert Markdown to HTML
    $html_content = convert_markdown_to_html($file_content);

    // Step 6: Extract the first image URL from the Markdown content
    $first_image_url = extract_first_image_url_from_markdown($file_content);

    // Step 7: Extract title from the file name (optional)
    $title = sanitize_text_field(pathinfo($uploaded_file['name'], PATHINFO_FILENAME));

    // Step 8: Create the post
    $post_id = wp_insert_post(array(
        'post_title'   => $title,
        'post_content' => $html_content,
        'post_status'  => 'publish', // Set to 'draft' if you want it unpublished initially
        'post_author'  => get_current_user_id(),
        'post_type'    => 'post', // Change to 'page' or custom post type if needed
    ));

    if (is_wp_error($post_id)) {
        return new WP_Error('post_creation_failed', 'Failed to create the post.', array('status' => 500));
    }

    // Step 9: Set the first image as the featured image
    if ($first_image_url) {
        $image_id = download_and_attach_image($first_image_url, $post_id);
        if (!is_wp_error($image_id)) {
            set_post_thumbnail($post_id, $image_id);
        }
    }

    // Step 10: Return success response
    return array(
        'status'  => 'success',
        'message' => 'Post created successfully.',
        'post_id' => $post_id,
    );
}

/**
 * Converts Markdown to HTML using Parsedown or another library.
 *
 * @param string $markdown The Markdown content.
 * @return string The converted HTML content.
 */
function convert_markdown_to_html($markdown) {
    // Include the Parsedown library (you need to download and include it in your project)
    require_once plugin_dir_path(__FILE__) . 'includes/Parsedown.php';

    $parsedown = new Parsedown();
    return $parsedown->text($markdown);
}

/**
 * Extracts the first image URL from Markdown content.
 *
 * @param string $markdown The Markdown content.
 * @return string|null The first image URL or null if no image is found.
 */
function extract_first_image_url_from_markdown($markdown) {
    // Match Markdown image syntax ![alt](url)
    preg_match('/!\[.*?\]\((.*?)\)/', $markdown, $matches);
    return !empty($matches[1]) ? esc_url_raw($matches[1]) : null;
}

/**
 * Downloads an image from a URL and attaches it to a post.
 *
 * @param string $image_url The URL of the image.
 * @param int $post_id The ID of the post.
 * @return int|WP_Error The attachment ID or an error object.
 */
function download_and_attach_image($image_url, $post_id) {
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    // Download the image to the server
    $tmp_file = download_url($image_url);
    if (is_wp_error($tmp_file)) {
        return $tmp_file;
    }

    // Prepare file data
    $file_data = array(
        'name'     => basename($image_url),
        'tmp_name' => $tmp_file,
    );

    // Upload the image to the media library
    $attachment_id = media_handle_sideload($file_data, $post_id);
    if (is_wp_error($attachment_id)) {
        @unlink($tmp_file); // Clean up the temporary file
        return $attachment_id;
    }

    return $attachment_id;
}