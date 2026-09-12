<?php
/**
 * Plugin Name: Nexus Arcade Articles
 * Description: Exposes a featured_image_url field on standard posts (mirroring
 * the product one) so the Astro frontend can show article thumbnails without a
 * second request per article.
 */

add_action('rest_api_init', function () {
    register_rest_field('post', 'featured_image_url', [
        'get_callback' => function ($post) {
            $image_id = get_post_thumbnail_id($post['id']);
            if (!$image_id) {
                return null;
            }
            $url = wp_get_attachment_image_url($image_id, 'medium_large');
            return $url ?: null;
        },
        'schema' => [
            'type' => ['string', 'null'],
        ],
    ]);
});
