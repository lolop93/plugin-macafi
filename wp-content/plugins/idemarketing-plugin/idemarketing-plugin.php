<?php


/*
Plugin Name: idemarketing-plugin
Description: Adds a custom API endpoint to retrieve all registered users.
Author: Manuel Prieto Macias
Version: 1.0
*/

// Register the custom API endpoints
function idemarketing_plugin_custom_api_endpoints_init() {
    register_rest_route('idemarketing/v1', '/users', array(
        'methods'  => 'GET',
        'callback' => 'idemarketing_plugin_custom_api_get_users_endpoint_callback',
    ));

    register_rest_route('idemarketing/v1', '/sync-users', array(
        'methods'  => 'POST',
        'callback' => 'idemarketing_plugin_custom_api_sync_users_callback',
    ));
}
add_action('rest_api_init', 'idemarketing_plugin_custom_api_endpoints_init');

// Custom API endpoint callback function
function idemarketing_plugin_custom_api_get_users_endpoint_callback($request) {
    // Get all registered users
    $users = get_users();

    // Prepare the response
    $response = array();
    foreach ($users as $user) {
        if (in_array("vip", $user->roles)) {
            $user_data = array(
                'ID'             => $user->ID,
                'user_login'     => $user->user_login,
                'user_email'     => $user->user_email,
                'display_name'   => $user->display_name,
                'roles'          => $user->caps,
                'vip'            => in_array("vip", $user->roles),
                // Include any additional user data you need
            );
            $response[] = $user_data;
        }
    }

    // Return the response
    return rest_ensure_response($response);
}

// Custom API endpoint callback function to sync users
function idemarketing_plugin_custom_api_sync_users_callback($request) {
    // Prepare the API request data
    $api_url = 'https://macarfi.twentic.com/users/sync';

    $api_headers = array(
        'Authorization'    => 'Bearer 1|9ToWpO3FxJyrTAJyNkIsM16eHhkOqqwaCeYQVrH5',
        'X-Requested-With' => 'XMLHttpRequest',
        'Content-Type'     => 'application/json'
    );

    // Send the API request
    $response = wp_remote_post($api_url, array(
        'method'  => 'POST',
        'headers' => $api_headers,
    ));

    // Check if the API request was successful
    if (is_wp_error($response)) {
        return rest_ensure_response(array('error' => 'API request failed'));
    }

    // Get the API response data
    $api_response = json_decode(wp_remote_retrieve_body($response), true);

    // Check if the API response contains users
    if (isset($api_response['users']) && is_array($api_response['users'])) {
        // Loop through the API response users
        foreach ($api_response['users'] as $api_user) {
            // Check if the user already exists in WordPress
            $existing_user = get_user_by('email', $api_user['original_email']);
            if ($existing_user) {
                // Update the existing user
                $user_id = $existing_user->ID;
                $user_data = array(
                    'ID'           => $user_id,
                    'user_login'   => $api_user['username'],
                    'user_email'   => $api_user['email'],
                    'display_name' => $api_user['name'] . ' ' . $api_user['surname'],
                    // Update any additional user data as needed
                );
                wp_update_user($user_data);
            } else {
                // Create a new user
                $new_user = array(
                    'user_login'   => $api_user['username'],
                    'user_email'   => $api_user['email'],
                    'user_pass'    => base64_decode($api_user['password']),
                    'display_name' => $api_user['name'] . ' ' . $api_user['surname'],
                    // Add any additional user data as needed
                );
                $user_id = wp_insert_user($new_user);
            }

            // Assign roles to the user
            $user = new WP_User($user_id);
            $user->set_role('vip'); // Replace 'vip' with the desired role

            // Update any additional user data as needed
            // ...

            // Update the user in the API response with the WordPress user ID
            $api_user['wordpress_user_id'] = $user_id;
        }
    }

    // Return the updated API response
    return rest_ensure_response($api_response);
}



/*function scripts() {
    // Cargar archivo de estilo personalizado
    wp_enqueue_style( 'idemarketing-css', plugin_dir_url( __FILE__ ) . 'assets/idemarketing.css', array(), '1.0' );
    //wp_enqueue_style( 'font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css', array(), '5.15.3' );

    // Cargar archivo de JS personalizado
    wp_enqueue_script( 'idemarketing-js', plugin_dir_url( __FILE__ ) . 'assets/idemarketing.js', array('jquery'), '1.0', true );
}

add_action( 'wp_enqueue_scripts', 'scripts' );*/

?>

