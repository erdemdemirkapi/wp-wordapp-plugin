<?php
/**
 * Author: Sheroz Khaydarov <sheroz@wordapp.io>
 * Date: 20/03/2017 Time: 08:29
 */

function wa_pdx_filter_pre_get_posts( $query )
{
    if (PDX_LOG_ENABLE)
    {
        $log  = "wa_pdx_filter_pre_get_posts(): Phase 1 passed\n";
        $log  .= "is_main_query(): " . $query->is_main_query() . "\n";
        $log  .= "is_preview(): " . $query->is_preview() . "\n";
        $log  .= "is_singular(): " . ($query->is_singular() ? '1':'0') . "\n";
        file_put_contents(PDX_LOG_FILE, $log, FILE_APPEND);
    }

    if ($query->is_main_query() && $query->is_preview() && $query->is_singular()) {
        add_filter( 'posts_results', 'wa_pdx_filter_posts_results', 10, 2 );
        if (PDX_LOG_ENABLE)
        {
            $log  = "wa_pdx_filter_pre_get_posts(): Phase 2 passed\n";
            file_put_contents(PDX_LOG_FILE, $log, FILE_APPEND);
        }
    }

    if (PDX_LOG_ENABLE)
    {
        $log  = "wa_pdx_filter_pre_get_posts(): End\n";
        file_put_contents(PDX_LOG_FILE, $log, FILE_APPEND);
    }

    return $query;
}

function wa_pdx_filter_posts_results( $posts )
{
    if (PDX_LOG_ENABLE)
    {
        $log  = "wa_pdx_filter_posts_results(): Phase 1 passed\n";
        file_put_contents(PDX_LOG_FILE, $log, FILE_APPEND);
    }

    remove_filter( 'posts_results', 'wa_pdx_filter_posts_results', 10 );

    if (empty($posts))
        return $posts;

    if (sizeof($posts) != 1)
        return $posts;

    $wa_pat = $_GET['wa_pat'];
    if (empty($wa_pat))
    {
        if (PDX_LOG_ENABLE)
        {
            $log = "Invalid wa_pat parameter\n";
            file_put_contents(PDX_LOG_FILE, $log, FILE_APPEND);
        }
        return $posts;
    }

    $cfg = get_option(PDX_CONFIG_OPTION_KEY);
    if (empty($cfg))
    {
        if (PDX_LOG_ENABLE)
        {
            $log  = "wa_pdx_filter_posts_results(): Empty configuration\n";
            file_put_contents(PDX_LOG_FILE, $log, FILE_APPEND);
        }
        return $posts;
    }

    $preview_token = $cfg['preview_token'];
    if (empty($preview_token))
    {
        if (PDX_LOG_ENABLE)
        {
            $log  = "wa_pdx_filter_posts_results(): Invalid configuration\n";
            file_put_contents(PDX_LOG_FILE, $log, FILE_APPEND);
        }
        return $posts;
    }

    $post_id = $posts[0]->ID;
    if (!wa_pdx_check_preview_access_token ($post_id, $wa_pat, $preview_token))
    {
        if (PDX_LOG_ENABLE)
        {
            $log  = "wa_pdx_filter_posts_results(): Not authorized\n";
            file_put_contents(PDX_LOG_FILE, $log, FILE_APPEND);
        }
        return $posts;
    }

    $posts[0]->post_status = 'publish';

    // Disable comments and pings for this post.
    add_filter('comments_open', '__return_false');
    add_filter('pings_open', '__return_false');

    if (PDX_LOG_ENABLE)
    {
        $log  = "wa_pdx_filter_posts_results(): Process completed.\n";
        file_put_contents(PDX_LOG_FILE, $log, FILE_APPEND);
    }
    return $posts;
}

function wa_pdx_generate_preview_access_token ($post_id, $preview_token)
{
    // pat means: Preview Access Token
    // pat should be random for every function call to avoid browser cache
    // and must be verifiable by preview_token

    // May be needs to improve for security reasons
    // ex. we can  pat = ($salt XOR $preview_token) + $salt
    // OR use hash with post related stuff to prevent predictions
    $salt = wa_pdx_random_hex_string(16);
    $wa_pat = $preview_token . $salt;

    return $wa_pat;
}

function wa_pdx_check_preview_access_token ($post_id, $wa_pat, $preview_token)
{
    return substr($wa_pat, 0, strlen($preview_token)) === $preview_token;
}

function wa_pdx_generate_preview_url ($post_id)
{
    $params = array();
    $post_status = get_post_status($post_id);
    if ($post_status !== 'publish')
        $params[] = 'preview=true';

    $cfg = get_option(PDX_CONFIG_OPTION_KEY);
    if (empty($cfg))
        wa_pdx_send_response('Invalid Configuration');

    $preview_token = $cfg['preview_token'];
    $wa_pat = wa_pdx_generate_preview_access_token ($post_id, $preview_token);
    $params[] = "wa_pat=$wa_pat";
    $post_url = get_permalink($post_id);
    return wa_pdx_add_url_params($post_url , $params);
}

function wa_pdx_op_prepare_preview ($params)
{
    $preview_url = null;
    $post_url = $params['url'];

    if(empty($post_url))
        $post_id = wa_pdx_content_add ($params);
    else
        $post_id = wa_pdx_content_update ($params);

    if (empty($post_id))
        wa_pdx_send_response('Invalid Post ID');

    $preview_url = wa_pdx_generate_preview_url ($post_id);
    $post_url = get_permalink($post_id);

    $data = array (
        'url' => $post_url,
        'preview_url' => $preview_url
    );

    wa_pdx_send_response($data, true);
}