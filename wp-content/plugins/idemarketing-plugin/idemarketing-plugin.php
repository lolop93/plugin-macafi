<?php


/*
Plugin Name: idemarketing-plugin
Description: Adds a custom API endpoint to retrieve all registered users.
Author: Manuel Prieto Macias
Version: 1.0
*/

// Register the custom API endpoints
function idemarketing_plugin_custom_api_endpoints_init() {
    /*register_rest_route('idemarketing/v1', '/users', array(
        'methods'  => 'GET',
        'callback' => 'idemarketing_plugin_custom_api_get_users_endpoint_callback',
    ));*/

    register_rest_route('idemarketing/v1', '/sync-users', array(
        'methods'  => 'POST',
        'callback' => 'idemarketing_plugin_custom_api_sync_users_callback',
    ));
}
add_action('rest_api_init', 'idemarketing_plugin_custom_api_endpoints_init');

// Custom API endpoint callback function
/*function idemarketing_plugin_custom_api_get_users_endpoint_callback($request) {
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
}*/

// Custom API endpoint callback function
function idemarketing_plugin_custom_api_sync_users_callback($request) {
    // Get the request body
    $request_body = json_decode($request->get_body(), true);

    // Check if the request body contains users
    if (isset($request_body['users']) && is_array($request_body['users'])) {
        // Loop through the users in the request body
        foreach ($request_body['users'] as $user_data) {
            // Check if the user already exists in WordPress based on the original_email
            $existing_user = get_user_by('email', $user_data['original_email']);

            if ($existing_user) {
                // Update the existing user
                $user_id = $existing_user->ID;
                $user_login = $user_data['username'];
                $user_email = $user_data['email'];
                $display_name = $user_data['name'] . ' ' . $user_data['surname'];

                // Update user data
                $user_data_update = array(
                    'ID'           => $user_id,
                    'user_login'   => $user_login,
                    'user_email'   => $user_email,
                    'display_name' => $display_name,
                    'first_name' => $user_data['name'],
                    'last_name' => $user_data['surname'],
                    // Update any additional user data as needed
                );

                // Check if the password is provided for an update
                if (isset($user_data['password'])) {
                    $user_data_update['user_pass'] = base64_decode($user_data['password']);
                }

                wp_update_user($user_data_update);
            } else {
                // Create a new user
                $user_login = $user_data['username'];
                $user_email = $user_data['email'];
                $user_password = base64_decode($user_data['password']);
                $display_name = $user_data['name'] . ' ' . $user_data['surname'];

                // Create user data
                $user_data_create = array(
                    'user_login'   => $user_login,
                    'user_email'   => $user_email,
                    'user_pass'    => $user_password,
                    'display_name' => $display_name,
                    'first_name' => $user_data['name'],
                    'last_name' => $user_data['surname'],
                    // Add any additional user data as needed
                );

                $user_id = wp_insert_user($user_data_create);
            }

            // Update any additional user data as needed
            // ...
        }
    }

    // Return a success response
    return rest_ensure_response(array('message' => 'Users synchronized successfully.'));
}






/**  Esto devuelve los usuarios cuando se crean o se modifican*/

// Función para enviar un nuevo usuario al endpoint
function send_new_user_to_endpoint($user_id) {
    // Obtener los datos del usuario creado
    $user = get_userdata($user_id);
    $token = '1|9ToWpO3FxJyrTAJyNkIsM16eHhkOqqwaCeYQVrH5'; // Token de autorización

    // Verificar si el usuario tiene el rol "VIP" para establecer el valor de "partner"
    $partner = in_array('vip', (array) $user->roles);

    /* MUY IMPORTANTE, cuando un usuario se modifica desde el back, no se detectan los cambios de roles por tanto cuando se modifique un usuario*/

    // Construir el array de datos del usuario para enviar en el body de la solicitud
    $user_data = array(
        'name' => $user->first_name,
        'surname' => $user->last_name,
        'email' => $user->user_email,
        'password' => base64_encode($user->user_pass),
        'passwordMD5' => $user->user_pass,
        'original_email' => $user->user_email,
        'username' => $user->user_login,
        'business_name' => '',
        'country' => '',
        'zip_code' => '',
        'phone' => '',
        'newsletter' => '',
        'partner' => $partner,
        'member' => true
    );

    $body = array('users' => array($user_data)); // Construir el cuerpo de la solicitud

    // Configurar los encabezados de la solicitud
    $headers = array(
        'Authorization' => 'Bearer ' . $token,
        'X-Requested-With' => 'XMLHttpRequest',
        'Content-Type' => 'application/json'
    );

    // Realizar la solicitud HTTP POST
    $response = wp_remote_post('https://macarfi.twentic.com/api/users/sync', array(
        'method' => 'POST',
        'headers' => $headers,
        'body' => json_encode($body)
    ));

    // Verificar la respuesta de la solicitud
    if (is_wp_error($response)) {
        // Ocurrió un error al realizar la solicitud
        error_log('Error al enviar el usuario al endpoint: ' . $response->get_error_message());
    } else {
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        // Verificar el código de respuesta y registrar el resultado en los registros
        if ($response_code === 200) {
            // La solicitud fue exitosa
            error_log('Usuario enviado al endpoint correctamente. Respuesta: ' . $response_body);
        } else {
            // La solicitud no tuvo éxito
            error_log('Error al enviar el usuario al endpoint. Código de respuesta: ' . $response_code);
        }
    }
}

// Hook para la creación de usuarios
add_action('user_register', 'send_new_user_to_endpoint');


// Función para enviar la actualización de perfil de usuario al endpoint
function send_profile_update_to_endpoint($user_id, $old_user_data = null, $userdata = null) {
    // Eliminar la entrada de caché correspondiente a los datos del usuario
    wp_cache_delete($user_id, 'users');

    // Obtener los datos del usuario modificado
    $user = get_userdata($user_id);
    $token = '1|9ToWpO3FxJyrTAJyNkIsM16eHhkOqqwaCeYQVrH5'; // Token de autorización

    // Verificar si el usuario tiene el rol "VIP" para establecer el valor de "partner"
    $partner = in_array('vip', (array) $user->roles);

    // Construir el array de datos del usuario para enviar en el body de la solicitud
    $user_data = array(
        'name' => $user->first_name,
        'surname' => $user->last_name,
        'email' => $user->user_email,
        'password' => base64_encode($user->user_pass),
        'passwordMD5' => $user->user_pass,
        'original_email' => $user->user_email,
        'username' => $user->user_login,
        'business_name' => '',
        'country' => '',
        'zip_code' => '',
        'phone' => '',
        'newsletter' => '',
        'partner' => $partner ? true : '',
        'member' => ''
    );

    $body = array('users' => array($user_data)); // Construir el cuerpo de la solicitud

    // Configurar los encabezados de la solicitud
    $headers = array(
        'Authorization' => 'Bearer ' . $token,
        'X-Requested-With' => 'XMLHttpRequest',
        'Content-Type' => 'application/json'
    );

    /*error_log(print_r($user->roles, true));
    error_log(print_r($userdata, true));
    error_log(print_r($body, true));
    exit();*/

    // Realizar la solicitud HTTP POST

    $response = wp_remote_post('https://macarfi.twentic.com/api/users/sync', array(
        'method' => 'POST',
        'headers' => $headers,
        'body' => json_encode($body)
    ));

    // Verificar la respuesta de la solicitud
    if (is_wp_error($response)) {
        // Ocurrió un error al realizar la solicitud
        error_log('Error al enviar la actualización de perfil al endpoint: ' . $response->get_error_message());
    } else {
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        // Verificar el código de respuesta y registrar el resultado en los registros
        if ($response_code === 200) {
            // La solicitud fue exitosa
            error_log('Actualización de perfil enviada al endpoint correctamente. Respuesta: ' . $response_body);
        } else {
            // La solicitud no tuvo éxito
            error_log('Error al enviar la actualización de perfil al endpoint. Código de respuesta: ' . $response_code);
        }
    }

}

// Hook para la actualización del perfil de usuario desde el panel de administración
add_action('profile_update', 'send_profile_update_to_endpoint', 10, 3);

// Hook para cuando se cambia el rol del usuario
//add_action('set_user_role', 'send_profile_update_to_endpoint_roles', 10, 3);

// Hook para la actualización del perfil de usuario desde el panel del frontend
add_action('personal_options_update', 'send_profile_update_to_endpoint', 10, 3);

// Hook para la actualización del perfil de usuario desde el panel de "Mi cuenta" de WooCommerce
add_action('woocommerce_update_customer', 'send_profile_update_to_endpoint', 10, 1);


/** FIN  Esto devuelve los usuarios cuando se crean o se modifican*/














//Prueba

function send_updated_user_to_endpoint( $user_id, $role, $old_roles ) {
    // Verificar si se han realizado cambios en los roles
    if ( ! empty( array_diff( $role, $old_roles ) ) ) {
        // Eliminar la entrada de caché correspondiente a los datos del usuario
        wp_cache_delete( $user_id, 'users' );

        // Obtener los datos del usuario actualizados
        $user = get_userdata( $user_id );
        $token = '1|9ToWpO3FxJyrTAJyNkIsM16eHhkOqqwaCeYQVrH5'; // Token de autorización

        // Verificar si el usuario tiene el rol "VIP" para establecer el valor de "partner"
        $partner = in_array( 'vip', (array) $user->roles );

        // Construir el array de datos del usuario para enviar en el body de la solicitud
        $user_data = array(
            'name' => $user->first_name,
            'surname' => $user->last_name,
            'email' => $user->user_email,
            'password' => base64_encode( $user->user_pass ),
            'passwordMD5' => $user->user_pass,
            'original_email' => $user->user_email,
            'username' => $user->user_login,
            'business_name' => '',
            'country' => '',
            'zip_code' => '',
            'phone' => '',
            'newsletter' => '',
            'partner' => $partner,
            'member' => true
        );

        $body = array( 'users' => array( $user_data ) ); // Construir el cuerpo de la solicitud

        // Configurar los encabezados de la solicitud
        $headers = array(
            'Authorization' => 'Bearer ' . $token,
            'X-Requested-With' => 'XMLHttpRequest',
            'Content-Type' => 'application/json'
        );

        error_log( print_r( $user->roles, true ) );
        error_log( print_r( $user_data, true ) );
        error_log( print_r( $body, true ) );
        exit();

        // Realizar la solicitud HTTP POST
        $response = wp_remote_post( 'https://macarfi.twentic.com/api/users/sync', array(
            'method' => 'POST',
            'headers' => $headers,
            'body' => json_encode( $body )
        ));

        // Verificar la respuesta de la solicitud
        if ( is_wp_error( $response ) ) {
            // Ocurrió un error al realizar la solicitud
            error_log( 'Error al enviar la actualización de perfil al endpoint: ' . $response->get_error_message() );
        } else {
            $response_code = wp_remote_retrieve_response_code( $response );
            if ( $response_code === 200 ) {
                // La actualización de perfil se envió exitosamente
                error_log( 'Actualización de perfil enviada al endpoint con éxito.' );
            } else {
                // Hubo un error en el endpoint
                error_log( 'Error en el endpoint: ' . $response_code );
            }
        }
    }
}

// Hook para actualizar el perfil de usuario después de cambiar los roles
add_action( 'set_user_role', 'send_updated_user_to_endpoint', 10, 3 );




?>

