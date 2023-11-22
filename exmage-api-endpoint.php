<?php
/**
 * Plugin Name: Exmage API Endpoint
 * Description: Adds a custom REST API endpoint to listen for post_id and URL to attach a remote image to a post.
 * Version: 1.0
 * Author: Justin Phelan
 */

function register_custom_exmage_endpoint() {
    register_rest_route('exmage/v1', '/listen', [
        'methods' => 'POST',
        'callback' => 'handle_custom_exmage_endpoint',
        'permission_callback' => 'exmage_custom_api_permission_check'
    ]);
    register_rest_route('exmage/v1', '/gallery', [
        'methods' => 'POST',
        'callback' => 'handle_custom_exmage_gallery_endpoint',
        'permission_callback' => 'exmage_custom_api_permission_check'
    ]);
}
add_action('rest_api_init', 'register_custom_exmage_endpoint');

function exmage_custom_api_permission_check(WP_REST_Request $request) {
    // Check if user is logged in
    if (!is_user_logged_in()) {
        return new WP_Error('rest_forbidden', 'You are not currently logged in.', ['status' => 401]);
    }

    // Check if the Authorization header is present
    if (empty($request->get_header('authorization'))) {
        return new WP_Error('rest_forbidden', 'Application Passwords authentication required.', ['status' => 401]);
    }

    // Optional: Add further capability checks if needed
    // e.g., if (current_user_can('some_capability'))

    return true;
}


function handle_custom_exmage_endpoint(WP_REST_Request $request) {
    // Get parameters from the request
    $post_id = $request->get_param('post_id');
    $url = $request->get_param('url');

    // Ensure both parameters are set
    if (!$post_id || !$url) {
        return new WP_Error('missing_parameter', 'post_id and url are required.', ['status' => 400]);
    }

    // Call your custom code
	$parse_url  = wp_parse_url( $url );
	$image_id   = "{$parse_url['host']}{$parse_url['path']}";
    $external_image = EXMAGE_WP_IMAGE_LINKS::add_image($url, $image_id, $post_id);
    if($external_image) {
        $attached = "failed";
        if(set_post_thumbnail($post_id, $external_image['id'])) {
            $attached = "success";
            $external_image['product'] = $post_id;
        }
    }


    // Respond back to the request
    return [
        'media' => 'success',
        'attached' => $attached,
        'product' => $post_id,
        'external_image' => $external_image
    ];
}

function handle_custom_exmage_gallery_endpoint(WP_REST_Request $request) {
    // Get parameters from the request
    $post_id = $request->get_param('post_id');
    $url = $request->get_param('url');

    // Ensure both parameters are set
    if (!$post_id || !$url) {
        return new WP_Error('missing_parameter', 'post_id and url are required.', ['status' => 400]);
    }

    // Call your custom code
    $parse_url  = wp_parse_url( $url );
    $image_id   = "{$parse_url['host']}{$parse_url['path']}";
    $external_image = EXMAGE_WP_IMAGE_LINKS::add_image($url, $image_id, '');
    if($external_image) {
        // Fetch the current product gallery images
        $existing_images = get_post_meta($post_id, '_product_image_gallery', true);
        $gallery_images = !empty($existing_images) ? explode(',', $existing_images) : [];

        // add your new media IDs
        array_push($gallery_images, $external_image['id']);

        // Remove any duplicate IDs just in case
        $gallery_images = array_unique($gallery_images);

        // Update the product gallery
        update_post_meta($post_id, '_product_image_gallery', implode(',', $gallery_images));
    }


    // Respond back to the request
    return [
        'media' => 'success',
        'attached' => $gallery_images,
        'product' => $post_id,
        'external_image' => $external_image
    ];
}
