<?php
/**
 * Plugin Name: Bluesky Simple
 * Description: Muestra las publicaciones de un usuario de Bluesky utilizando la API oficial mediante un shortcode.
 * Version: 1.1
 * Author: angel@bandin.com
 * License: GPL2
 */

/* ================================================
   1. Agregar la página de opciones al administrador
   ================================================ */
function bluesky_feed_admin_menu() {
    add_options_page(
        'Configuración Bluesky Feed',   // Título de la página
        'Bluesky Feed',                   // Título del menú
        'manage_options',                 // Capacidad para acceder
        'bluesky-feed',                   // Slug de la página
        'bluesky_feed_options_page'       // Función que muestra el contenido
    );
}
add_action('admin_menu', 'bluesky_feed_admin_menu');

/* ================================================
   2. Registrar y definir los campos de configuración
   ================================================ */
function bluesky_feed_settings_init() {
    register_setting('blueskyFeed', 'bluesky_feed_options');

    add_settings_section(
        'bluesky_feed_section',                   // ID de la sección
        'Configuración de Bluesky Feed',          // Título de la sección
        'bluesky_feed_section_callback',          // Callback de descripción
        'blueskyFeed'                             // Página en la que se muestra
    );

    add_settings_field(
        'bluesky_feed_user',                      // ID del campo
        'Usuario de Bluesky',                     // Título del campo
        'bluesky_feed_user_render',               // Callback que dibuja el campo
        'blueskyFeed',                            // Página en la que se muestra
        'bluesky_feed_section'                    // Sección a la que pertenece
    );
    
    add_settings_field(
        'bluesky_feed_count',
        'Cantidad de publicaciones a mostrar',
        'bluesky_feed_count_render',
        'blueskyFeed',
        'bluesky_feed_section'
    );
}
add_action('admin_init', 'bluesky_feed_settings_init');

function bluesky_feed_user_render() {
    $options = get_option('bluesky_feed_options');
    ?>
    <input type='text' name='bluesky_feed_options[bluesky_feed_user]' value='<?php echo isset($options['bluesky_feed_user']) ? esc_attr($options['bluesky_feed_user']) : ''; ?>'>
    <?php
}

function bluesky_feed_count_render() {
    $options = get_option('bluesky_feed_options');
    ?>
    <input type='number' name='bluesky_feed_options[bluesky_feed_count]' value='<?php echo isset($options['bluesky_feed_count']) ? intval($options['bluesky_feed_count']) : 5; ?>'>
    <?php
}

function bluesky_feed_section_callback() {
    echo 'Introduce los parámetros para mostrar las publicaciones de Bluesky.';
}

function bluesky_feed_options_page() {
    ?>
    <div class="wrap">
        <h1>Bluesky Feed</h1>
        <form action='options.php' method='post'>
            <?php
            settings_fields('blueskyFeed');
            do_settings_sections('blueskyFeed');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

/* ================================================
   3. Crear el shortcode para mostrar el feed de Bluesky
   ================================================ */
function bluesky_feed_shortcode($atts) {
    // Obtener las opciones guardadas
    $options = get_option('bluesky_feed_options');
    $usuario = isset($options['bluesky_feed_user']) ? $options['bluesky_feed_user'] : '';
    $cantidad = isset($options['bluesky_feed_count']) ? intval($options['bluesky_feed_count']) : 5;
    
    if (!$usuario) {
        return 'Por favor, configure el nombre de usuario de Bluesky en la <a href="' . admin_url('options-general.php?page=bluesky-feed') . '">página de opciones</a>.';
    }
    
    // Se obtiene el feed de Bluesky usando la API oficial.
    $posts = bluesky_feed_fetch_posts($usuario, $cantidad);

    if ( empty($posts) ) {
        return 'No se encontraron publicaciones u ocurrió un error en la conexión con la API de Bluesky.';
    }
    
    // Se asume que cada publicación contiene los campos 'text' y 'createdAt'.
    $output = '<div class="bluesky-feed">';
	$flag = 0;
    foreach ($posts as $post) {
		
		if($flag==0){
			$flag=1;
			$output .= '<div class="bluesky-post-head">';
			$output .= '<a href="https://bsky.app/profile/'. $usuario .'" target="_blank">';
			$nombre = isset($post['post']['author']['displayName']) ? $post['post']['author']['displayName'] : '';
			$avatar = isset($post['post']['author']['avatar']) ? $post['post']['author']['avatar'] : '';
			$output .= '<div style="width:101px;display: inline-block;"><img style="width:100px;border-radius:50px;border:1px solid gray;" src="' .$avatar .'"></div>';
			$output .= '<div style="display: inline-block;"><p style="display:inline;">' .$nombre .'</p></div>';
			$output .= '</a>';
			$output .= '</div>';
			
		}
		
        //$content   = isset($post['text']) ? $post['text'] : '';
		$content   = isset($post['post']['record']['text']) ? $post['post']['record']['text'] : '';
		$uri = isset($post['post']['uri']) ? $post['post']['uri'] : false;
		$timestamp = isset($post['post']['record']['createdAt']) ? $post['post']['record']['createdAt'] : '';
		$timestamp = cambiar_fecha($timestamp);
		
		//$embed = isset($post['post']['record']['embed']) ? $post['post']['record']['embed'] : false;
		$embed = isset($post['post']['embed']) ? $post['post']['embed'] : false;
		
        $output .= '<div class="bluesky-post" style="border-bottom:1px solid #000;">';
		
		$output .= '<div style="width:101px;display: inline-block;vertical-align: top; margin-top: 10px;"><img style="width:60px;border-radius:50px;border:1px solid gray;" src="' .$avatar .'"></div>';
		$output .= '<div style="display: inline-block; max-width: calc(100% - 101px);"><small style="float:right; font-size:12px;">' . esc_html($timestamp) . '</small>';
		if ($uri){
			$output .= '<a href="' . url_post_bsk($uri) . '" style="text-decoration:none;" target="_blank">';
		}
        $output .= '<p>' . esc_html($content) . '</p>';
		if ($uri){
			$output .= '</a>';
		}
		if (false!==$embed){
			$output .= '<pre embed style="display:none;">'. print_r($embed, true) .'</pre>';
			switch ($embed['$type']){
				case "app.bsky.embed.external#view":
					$output .= '<div style="border:1px solid #000;border-radius: 9px; margin-bottom: 5px;padding:5px;" >';
					$output .= '<img src="' . $embed['external']['thumb'] . '" style="width:100%;" />';
					$output .= '<p><strong>' . $embed['external']['title'] . '</strong></p>';
					$output .= '<p>' . $embed['exernal']['description'] . '</p>';
					$output .= '<p><strong><a href="'. $embed['external']['uri'] .'">' . solo_dominio($embed['external']['uri']) . '</a></strong></p>';
					$output .= '</div>';
				break;
				
				case "app.bsky.embed.recordWithMedia#view":
					$output .= '<div style="border:1px solid #000;border-radius: 9px; margin-bottom: 5px;padding:5px;" >';
					$output .= '<img src="' . $embed['media']['external']['thumb'] . '" style="width:100%;" />';
					$output .= '<p><strong>' . $embed['media']['external']['title'] . '</strong></p>';
					$output .= '<p>' . $embed['media']['exernal']['description'] . '</p>';
					$output .= '<p><strong><a href="'. $embed['media']['external']['uri'] .'">' . solo_dominio($embed['media']['external']['uri']) . '</a></strong></p>';
					$output .= '</div>';
				break;
				
				case "app.bsky.embed.video#view":
					$output .= '<div style="border:1px solid #000;border-radius: 9px; margin-bottom: 5px;padding:5px;" >';
					// Mientras se soluciona la visibilidad del vídeo enlazamos con el post
				$output .= '<a href="'. url_post_bsk($uri) .'" target="_blank">';
					$output .= '<figure>';
					$output .= '<video style="max-width:100%;" poster="'. $embed['thumbnail'].'" src="blob:https://bsky.app/d34dc9a2-17b1-437a-b629-674ca8c3e46b">';
					$output .= '</figure>';
				$output .= '</a>';
					$output .= '</div>';
				break;
				
				case "app.bsky.embed.images#view":
					$output .= '<div style="border:1px solid #000;border-radius: 9px; margin-bottom: 5px;padding:5px;" >';
					foreach ($embed['images'] as $unimg){
						$output .= '<img style="max-width:100%;" src="'. $unimg['fullsize'] .'">';
					}
					$output .= '</div>';
				break;
				
				case "app.bsky.embed.record#view": // Post de otra cuenta
					$output .= '<div class="bluesky-sub_post-head">';
					$output .= '<a href="https://bsky.app/profile/'. $embed['record']['author']['handle'] .'" target="_blank">';
					$nombre = isset($embed['record']['author']['displayName']) ? $embed['record']['author']['displayName'] ."." : '';
					$subavatar = isset($embed['record']['author']['avatar']) ? $embed['record']['author']['avatar'] : '';
					$output .= '<div style="width:101px;display: inline-block;"><img style="width:100px;border-radius:50px;border:1px solid gray;" src="' .$subavatar .'"></div>';
					$output .= '<div style="display: inline-block;"><p style="display:inline;">' .$nombre .'</p></div>';
					$output .= '</a>';
					$output .= '</div>';
				
					foreach ($embed['record']['embeds'] as $unembed){
						if (is_array($unembed)){
							$output .= '<div style="border:1px solid #000;border-radius: 9px; margin-bottom: 5px;padding:5px;" >';
							$output .= 'tipo: ' . $unembed['$type'];
							$output .= '</div>';
						}
					}
				
				break;
				
				/*case "":
					foreach ($embed as $unembed){
						$output .= '<pre unembed style="display:none;">'. print_r($unembed, true) .'</pre>';
						if (is_array($unembed)){
							
						
							$output .= '<div style="border:1px solid #000;border-radius: 9px; margin-bottom: 5px;padding:5px;" >';
							$output .= '<img src="' . $unembed['thumb'] . '" style="width:100%;" />';
							$output .= '<p><strong>' . $unembed['title'] . '</strong></p>';
							$output .= '<p>' . $unembed['description'] . '</p>';
							$output .= '<p><strong><a href="'. $unembed['uri'] .'">' . solo_dominio($unembed['uri']) . '</a></strong></p>';
							$output .= '</div>';
						}
					}
				break;
				*/
				default:
					$output .= '<div style="border:1px solid #000;border-radius: 9px; margin-bottom: 5px;padding:5px;" >';
						$output .= ' Tipo: ' . $embed['$type'] . '';
					
					$output .= '</div>';
				
			}
		/*	
			if (is_array($embed)){
				$output .= '<pre embed style="display:none;">'. print_r($embed, true) .'</pre>';
				foreach ($embed as $unembed){
					$output .= '<pre unembed style="display:none;">'. print_r($unembed, true) .'</pre>';
					if (is_array($unembed)){
						
					
						$output .= '<div style="border:1px solid #000;border-radius: 9px; margin-bottom: 5px;padding:5px;" >';
						$output .= '<img src="' . $unembed['thumb'] . '" style="width:100%;" />';
						$output .= '<p><strong>' . $unembed['title'] . '</strong></p>';
						$output .= '<p>' . $unembed['description'] . '</p>';
						$output .= '<p><strong><a href="'. $unembed['uri'] .'">' . solo_dominio($unembed['external']['uri']) . '</a></strong></p>';
						$output .= '</div>';
					}
				}
			}else{

				$output .= '<div style="border:1px solid #000;border-radius: 9px; margin-bottom: 5px;padding:5px;" >';
				$output .= '<img src="' . $embed['external']['thumb'] . '" style="width:100%;" />';
				$output .= '<p><strong>' . $embed['external']['title'] . '</strong></p>';
				$output .= '<p>' . $embed['external']['description'] . '</p>';
				$output .= '<p><strong><a href="'. $embed['external']['uri'] .'">' . solo_dominio($embed['external']['uri']) . '</a></strong></p>';
				$output .= '</div>';
			}
		*/	
		}
		
		
		$output .= '</div>';
        
        $output .= '</div>';
		$output .= '<pre style="display:none;">'. print_r($post, true) .'</pre>';
		$output .= '<pre style="display:none;">'. print_r($post['post']['record'], true) .'</pre>';
		
    }
    $output .= '</div>';
    
    return $output;
}
add_shortcode('bluesky_feed', 'bluesky_feed_shortcode');

/* ================================================
   4. Función para obtener publicaciones usando la API de Bluesky
   ================================================ */
function bluesky_feed_fetch_posts($usuario, $cantidad) {
    // Construir la URL con los parámetros de consulta
    $endpoint = 'https://public.api.bsky.app/xrpc/app.bsky.feed.getAuthorFeed';
    $url = add_query_arg(array(
        'actor' => $usuario,
        'limit' => $cantidad,
		'filter' => 'posts_no_replies'
    ), $endpoint);

    // Realizar la solicitud GET a la API pública
    $response = wp_remote_get($url);

    // Verificar si hubo errores en la solicitud
    if (is_wp_error($response)) {
        error_log('Error al obtener el feed de Bluesky: ' . $response->get_error_message());
        return array();
    }

    // Obtener y decodificar el cuerpo de la respuesta
    $response_body = wp_remote_retrieve_body($response);
    $data = json_decode($response_body, true);

    // Verificar si la respuesta contiene el feed
    if (!isset($data['feed']) || empty($data['feed'])) {
        error_log('Respuesta inesperada de la API de Bluesky.');
        return array();
    }

    // Devolver el array de publicaciones
    return $data['feed'];
}

function cambiar_fecha($fecha){
	$arrd = explode ("T", $fecha);
	$arrd2 = explode ("-", $arrd[0]);
	$f = $arrd2[2] . "-" . $arrd2[1] . "-" . $arrd2[0];
	return $f;

}
// Recibe una URL y devuleve el dominio
function solo_dominio($url, $conwww=true){
	if ($conwww){
		$protocolos = array('http://', 'https://', 'ftp://');
	}else{
		$protocolos = array('http://', 'https://', 'ftp://', 'www.');
	}
    $url_slices = explode('/', str_replace($protocolos, '', $url));
    return $url_slices[0];
	
	
}
//crea la URL del post en BSK
function url_post_bsk($uri){
	$p = strrpos ($uri , "/");
	$l = strlen($str1);
	$url = "https://bsky.app/profile/sedra-fpfe.org/post/" . substr ($uri, -($l-$p-1));
	return $url;
}
?>
