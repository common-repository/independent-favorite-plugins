<?php

if ( !class_exists( "Independent_Favorite_Plugins" ) ) {
    class Independent_Favorite_Plugins
    {
        use i_fv_Singleton;// use Singleton pattern

        protected $_settings; //settings
        protected $_favorites = array();//list of favorite plugins

        protected $_logs;     //object for logging and displaying messages and errors
        
        protected $_critical_section; //semaphore

        /**
         * Constructor
         *
         * @since 1.0
         *
         * @access protected
         * @return Independent_Favorite_Plugins
         */
        protected function __construct()
        {
            // init data
            $this->_settings = i_Favorite_Plugins_Settings::instance();
            $this->_logs = i_Favorite_Plugins_Log::instance();

            $this->_critical_section = new i_fv_Critical_Section( array( 'file_name' => $this->_settings->file_critical_section ) );
            // register hooks
            register_activation_hook( $this->_settings->plugin_name, array( $this, 'activate' ) );
            register_uninstall_hook( $this->_settings->plugin_name, array( __CLASS__, 'uninstall' ) );
            add_action( 'init', array( $this, 'init_hooks' ) );

            // register plugin scripts and style sheets
            add_action( 'admin_enqueue_scripts', array( $this, 'register_plugin_scripts' ) );
            add_action( 'wp_ajax_i_fv_add_plugin', array( $this, 'ajax_add_plugin2favorite' ) );
            add_action( 'wp_ajax_i_fv_delete_plugin', array( $this, 'ajax_delete_plugin_favorite' ) );
        } // End constructor

        /**
         * Ajax function for add the plugin to favorite plugins
         *
         * @since 1.0
         *
         * @return null
         */
        public function ajax_add_plugin2favorite()
        {

            if ( !current_user_can( $this->_settings->key_capability ) ) {
                $this->_logs->log( "You do not have permission to add plugin to favorites. You need {$this->_settings->key_capability} permission" );
                wp_die( __( 'You do not have permission to access this page.' ) );
            }
            $file = $this->decode_and_safe_file_path( $_POST['file'] );
            $success = $this->add_plugin2favorite( $file );
            if ( !$success ) {
                $this->_logs->log( "Cannot add plugin to favorites in " . __FILE__ . ':' . __LINE__ );
            }
        }

        /**
         * Ajax function for remove the plugin from favorite plugins
         *
         * @since 1.0
         *
         * @return null
         */
        public function ajax_delete_plugin_favorite()
        {
            echo $this->_settings->key_capability;
            if ( !current_user_can( $this->_settings->key_capability ) ) {
                $this->_logs->log( "You do not have permission to delete plugin from favorites. You need {$this->_settings->key_capability} permission" );
                wp_die( __( 'You do not have permission to access this page.' ) );
            }
            $file = $this->decode_and_safe_file_path( $_POST['file'] );
            $success = $this->delete_plugin_favorite( $file );
            if ( !$success ) {
                $this->_logs->log( "Cannot delete plugin from favorites in " . __FILE__ . ':' . __LINE__ );
            }
        }

        /**
         * Add the plugin to the favorites plugins. If the plugin is favorite, do nothing
         *
         * @since 1.0
         *
         * @param string $file The name of the plugin file
         * @return bool True if the operation was success. False, in case of error
         */
        public function add_plugin2favorite( $file )
        {
            try {
                if ( !$this->_critical_section->begin() ) {
                    $this->_logs->log( "Cannot grab the critical section  {$this->_settings->file_critical_section}" );
                    return false;
                }
            } catch ( Exception $e ) {
                $this->_logs->log( "Error Exception: $e" );
                return false;
            }

            $file = wp_strip_all_tags( $file );
            $favorites = $this->get_favorites();
            $success = true;
            if ( !array_key_exists( $file, $favorites ) ) {
                $favorites[$file] = 1;
                $success = $this->update_favorites( $favorites );
            }
            $this->_critical_section->end();
            return $success;
        }

        /**
         * Delete plugin from the favorites plugins. If the plugin isn't favorite, do nothing
         *
         * @since 1.0
         *
         * @param string $file The name of the plugin file
         * @return bool True if the operation was success. False, in case of error
         */
        public function delete_plugin_favorite( $file )
        {
            if ( !$this->_critical_section->begin() ) {
                $this->_logs->log( "Cannot grab the critical section  {$this->_settings->file_critical_section}" );
                return false;
            }

            $file = wp_strip_all_tags( $file );
            $favorites = $this->get_favorites();
            $success = true;
            if ( array_key_exists( $file, $favorites ) ) {
                unset($favorites[$file]);
                $success = $this->update_favorites( $favorites );
            }
            $this->_critical_section->end();
            return $success;
        }

        /**
         * Activate plugin         *
         *
         * @since 1.0
         *
         * @return void
         */
        public function activate()
        {
            $this->_settings->check_options();
            $this->init_favorite_list_data();
        }

        /**
         * Uninstall plugin
         *
         * @since 1.0
         *
         * @return null
         */
        static public function uninstall()
        {
            $settings = i_Favorite_Plugins_Settings::instance();
            if ( !is_multisite() ) {
                delete_option( $settings->name_opt );
            } else    //if multisite clean up sub-sites
            {
                global $wpdb;
                $blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" );
                $original_blog_id = get_current_blog_id();

                foreach ( $blog_ids as $blog_id ) {
                    switch_to_blog( $blog_id );
                    delete_option( $settings->name_opt );
                }
                switch_to_blog( $original_blog_id );
            }
        }


        /**
         * Export files of pointed plugins to zip archive.
         * Php server must success ZipArchive
         *
         * @since 1.0
         *
         * @param array $list List of plugins for export to zip
         *
         * @throws Exception in case if the plugin from list can not be opened
         * @return string Relative filename of the zip archive regarding plugins directory
         */
        public function export_zip( $list )
        {
            $zip_path = $this->_settings->plugin_dir.'files/export.zip';
            chdir( $this->_settings->plugins_all_dir );
            $path = $zip_path;
            $zip = new ZipArchive();
            if ( $zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true ) {
                $this->_logs->log( "Plugin " . $this->_settings->plugin_name . " can not open or create file {$zip_path} in " . __FILE__ . ':' . __LINE__ );
                throw new Exception( "Plugin " . $this->_settings->plugin_display_name . " can not open or create file {$zip_path}" );
            }
            foreach ( $list as $plugin ) {
                $files = get_plugin_files( $plugin );
                foreach ( $files as $file ) {
                    if ( ($file != $path) && is_file( $file ) ) {
                        $zip->addFile( $file );
                    }
                }
            }
            if ( !$zip->close() ) {
                $this->_logs->log( "Plugin " . $this->_settings->plugin_name . " cannot save archive {$zip_path} in " . __FILE__ . ':' . __LINE__ );
                throw new Exception( "Plugin " . $this->_settings->plugin_display_name . " cannot save archive {$zip_path}" );
            }
            return $zip_path;
        }

        /**
         * Check if the plugin is favorite
         *
         * @since 1.0
         *
         * @param string $file The name of the plugin file
         * @return bool true if the plugin is favorite
         */
        public function is_favorite( $file )
        {
            $favorites = $this->get_favorites();
            return array_key_exists( $file, $favorites );
        }


        /**
         * Register some hooks depending of the current page         *
         *
         */
        public function init_hooks()
        {
            $lang_dir = dirname( $this->_settings->plugin_name ) . '/' . 'languages';
            load_plugin_textdomain( $this->_settings->lang_domain, false, $lang_dir );
            global $pagenow;
            if ( ($pagenow == 'plugins.php') && ($this->_settings->is_active) ) {
                // check access to the favorite plugins list
                if ( !$this->check_access2fv_list() ) {
                    $this->_logs->show_all_messages();
                    return;
                }
                //register hooks
                if ( isset($_GET['plugin_status']) && ($_GET['plugin_status'] == 'favorites') ) {
                    add_filter( 'all_plugins', array( $this, 'filter_plugin_list' ) );

                }
                if ( is_network_admin() ) {
                    add_filter( 'views_plugins-network', array( $this, 'add_favorite_filter_link' ) );
                } else {
                    add_filter( 'views_plugins', array( $this, 'add_favorite_filter_link' ) );
                }

                add_filter( 'plugin_row_meta', array( $this, 'add_favorite_button' ), 10, 2 );
            }
        }

        /**
         * Register and enqueue scripts and styles sheets
         *
         * @since 1.0
         *
         * @return null
         */
        public function register_plugin_scripts()
        {
            global $pagenow;
            if ( $pagenow == 'plugins.php' ) {
                wp_register_style( 'independent-favorite_plugins', plugins_url( 'css/plugin.css', $this->_settings->plugin_name ) );
                wp_enqueue_style( 'independent-favorite_plugins' );

                wp_enqueue_script( 'ajax-script', plugins_url( '/js/functions.js', $this->_settings->plugin_name ), array( 'jquery' ), false , $this->_settings->arm_version );
            }
        }

        /**
         * Filter favorite plugins in plugins list
         *
         * @since 1.0
         *
         * @param array $plugins Plugins list
         * @return array List of favorite plugins
         */
        public function filter_plugin_list( $plugins )
        {
            $new_list = array();
            //array_filter isn't suitable
            foreach ( $plugins as $name => $plugin ) {
                if ( $this->is_favorite( $name ) ) {
                    $new_list[$name] = $plugin;
                }
            }
            return $new_list;
        }

        /**
         * Add favorite filter link to the plugins filter links (html)
         *
         * @since 1.0
         *
         * @param array $links Plugins filter links
         * @return array Plugins filter links
         */
        public function add_favorite_filter_link( $links )
        {
            $new_link = $this->favorite_filter_link();
            if ( isset($_GET['plugin_status']) && ($_GET['plugin_status'] == 'favorites') ) {
                foreach ( $links as $key => &$link ) {
                    $link = str_replace( 'current', '', $link );
                    $link = str_replace( '<span ', '<span hidden ', $link );
                }
            }
            if ( $new_link == '' ) {
                return $links;
            }
            array_push( $links, $new_link );
            return $links;
        }

        /**
         * Add favorite button in the plugin_row_meta information (html)
         *
         * @since 1.0
         *
         * @param array $links Default links for the plugin
         * @param string $file The name of the plugin file
         * @return array The array of the links for the plugin_row_meta information
         */
        public function add_favorite_button( $links, $file )
        {
            $new_link = $this->favorite_button( $file );
            array_push( $links, $new_link );
            return $links;
        }

        /**
         * Return html code of favorite filter link
         *
         * @since 1.0
         *
         * @return string html code
         */
        protected function favorite_filter_link()
        {
            $favorites = array_intersect_key( $this->get_favorites(), get_plugins() );
            $count = count( $favorites );
            if ( !$count ) {
                return '';
            }
            if ( isset($_GET['plugin_status']) && ($_GET['plugin_status'] == 'favorites') ) {
                $html = "<li class='favorites'><a class='current' href='plugins.php?plugin_status=favorites'>" . __( 'Favorites', $this->_settings->lang_domain ) . " <span class='count'>({$count})</span></a></li>";
            } else {
                $html = "<li class='favorites'><a href='plugins.php?plugin_status=favorites'>" . __( 'Favorites', $this->_settings->lang_domain ) . " <span class='count'>({$count})</span></a></li>";
            }
            return $html;
        }

        /**
         * Return html code of favorite button
         *
         * @since 1.0
         *
         * @param string $file The name of the plugin file
         * @return string
         */
        protected function favorite_button( $file )
        {
            $options = $this->_settings->options;
            $img = $options['icon'];
            //$this->_logs->show_message("icon".$options['icon']['path-like']);
            $esc_file = esc_attr( urlencode( $file ) );
            $html = <<<TAG
<div class='fv-like-holder' style='height:{$img['height']}px;width:{$img['width']}px;background-image: url("{$img['path-unlike']}");
                    ' onclick='i_fv_toggle(this)' data-plugin='{$esc_file}'>
                     <div class='like' style='height:{$img['height']}px;width:{$img['width']}px; background-image: url("{$img['path-like']}");
TAG;
            if ( $this->is_favorite( $file ) ) {
                $html .= "'></div></div>";
            } else {
                $html .= "display:none'></div></div>";
            }
            return $html;
        }

        /**
         * Get the list of favorite plugins
         *
         * @since 1.0
         *
         * @return array LIst of favorite plugins
         * @note I hope that php optimize this. So I don't use returning by reference
         */
        public function get_favorites()
        {
            if ( count( $this->_favorites ) ) return $this->_favorites;

            $str = @file_get_contents( $this->_settings->file_name );
            if ( $str === false ) {
                $this->_logs->show_message( sprintf( __( "Plugin %s cannot read the file %s.", $this->_settings->lang_domain ), $this->_settings->plugin_display_name, $this->safe_ftp_file_name( $this->_settings->file_name ) ), true );
                $this->_logs->log( "Cannot read content from the file {$this->_settings->file_name} in " . __FILE__ . ':' . __LINE__ );
            }
            if ( $str ) {
                $this->_favorites = json_decode( $str, true );
            } else {
                $this->_favorites = array();
            }

            /**
             * Filter hook for get the list of favorite plugins
             *
             * @since 1.0
             *
             * @param array $new_list The list of favorite plugins
             * @return array List of favorite plugins
             */
            $this->_favorites = apply_filters( 'i_fv_get_favorites', $this->_favorites );
            return $this->_favorites;
        }

        /**
         * Update the list of favorite plugins
         *
         * @since 1.0
         *
         * @param array $new_list New list of favorite plugins
         * @return bool true, if the operation was success
         *
         * @note I hope that php optimize this. So I don't use returning and passing by reference
         */
        protected function update_favorites( $new_list )
        {
            /**
             * Filter the list of favorite plugins for update before getting plugin data
             *
             * @since 1.0
             *
             * @param array $new_list A new list of favorite plugins without those data
             * @return array New List of favorite plugins
             */
            $new_list = apply_filters( 'i_fv_update_favorites_before', $new_list );
            foreach ( $new_list as $plugin => &$el ) {
                $file = $this->_settings->plugins_all_dir . '/' . $plugin;
                if ( file_exists( $file ) ) {
                    $el = get_plugin_data( $file );
                }
                $el['favorite_plugin'] = true; //for checking format list
            }

            /**
             * Filter hook for update list of favorite plugins after getting those plugin data
             *
             * @since 1.0
             *
             * @return array
             */
            $new_list = apply_filters( 'i_fv_update_favorites_after', $new_list );

            $new_list = json_encode( $new_list );
            $stream_options = array( 'ftp' => array( 'overwrite' => true ) );
            $stream = stream_context_create( $stream_options );
            $success = @file_put_contents( $this->_settings->file_name, $new_list, 0, $stream );
            if ( !$success ) {
                $this->_logs->show_message( sprintf( __( "Plugin %s cannot put content to the file %s", $this->_settings->lang_domain ), $this->_settings->plugin_name, $this->safe_ftp_file_name( $this->_settings->file_name ) ), true );
                $this->_logs->log( "Cannot put content to the file {$this->_settings->file_name} in " . __FILE__ . ':' . __LINE__ );
            } else {
                $this->_favorites = $new_list;
            }
            return $success;
        }

        /**
         * Init list of favorites plugins
         *
         * @since 1.0
         *
         * @return null
         *
         * @throws Exception if can not access to the file. Reset file if there are not list of favorite plugins
         */
        public function init_favorite_list_data()
        {
            // check file content format
            if ( (@file_exists( $this->_settings->file_name )) && !$this->is_list_of_favorite_plugins( @file_get_contents( $this->_settings->file_name ) ) ) {
                @unlink( $this->_settings->file_name );
            }

            if ( !@file_exists( $this->_settings->file_name ) ) {
                $fp = @fopen( $this->_settings->file_name, 'w' );
                if ( !$fp ) {
                    $this->_logs->log( "Plugin " . $this->_settings->plugin_name . " cannot open or create file {$this->_settings->file_name} in " . __FILE__ . ':' . __LINE__ );
                    throw new Exception( sprintf( __( "Plugin %s cannot open or create file %s" ), $this->_settings->plugin_display_name, $this->safe_ftp_file_name( $this->_settings->file_name ) ) );
                }
                fclose( $fp );
            }
            if ( !$this->check_access2fv_list() ) {
                $this->_logs->log( "Plugin " . $this->_settings->plugin_name . " cannot read or write file {$this->_settings->file_name} on activate in " . __FILE__ . ':' . __LINE__ );
                foreach ( $this->_logs->errors as $error ) {
                    $this->_logs->show_message( $error, true );
                    throw new Exception( $error );
                }
            }
        }


        /**
         * Check access to the list of favorites plugin
         *
         * @since 1.0
         *
         * @return bool True if access was successful
         */
        public function check_access2fv_list()
        {
            $file_name = $this->_settings->file_name;
            $cont = @file_get_contents( $file_name );
            if ( $cont === false ) {
                $this->_logs->log( "cannot read content from the file {$file_name} during check file access in " . __FILE__ . ':' . __LINE__ );
                $this->_logs->errors[] = (sprintf( __( "Plugin %s cannot read the file \"%s\". Please check access to the file or choose another file.", $this->_settings->lang_domain ), $this->_settings->plugin_display_name, $this->safe_ftp_file_name( $file_name ) ));
                return false;
            }
            $stream_options = array( 'ftp' => array( 'overwrite' => true ) );
            $stream = stream_context_create( $stream_options );
            $success = @file_put_contents( $file_name, $cont, 0, $stream );
            if ( $success === false ) {
                $this->_logs->log( "Plugin cannot write to the file {$file_name} during check file access in " . __FILE__ . ':' . __LINE__ );
                $this->_logs->errors[] = (sprintf( __( "Plugin %s cannot put content to the file \"%s\"", $this->_settings->lang_domain ), $this->_settings->plugin_display_name, $this->safe_ftp_file_name( $file_name ) ));
                return false;
            }
            return true;
        }


        /**
         * Check if content has list of favorite plugins in properly format
         *
         * @since 1.0
         *
         * @param string $content json encode list of favorite plugins
         * @return bool True content have list in properly format
         */
        protected function is_list_of_favorite_plugins( $content )
        {
            if ( $content == '' ) {
                return true;
            }
            $list = @json_decode( $content, true );
            if ( $list === false ) {
                return false;
            }
            if ( !is_array( $list ) ) {
                return false;
            }
            foreach ( $list as $el ) {
                if ( !array_key_exists( 'favorite_plugin', $el ) ) {
                    return false;
                }
            }
            return true;
        }

        /**
         * Hides username and password from ftp file name for safe display
         *
         * @since 1.0
         *
         * @param string $file_name File name
         * @return string file name
         */
        public function safe_ftp_file_name( $file_name )
        {
            return preg_replace( '|ftp://.*@|', 'ftp://…@', $file_name );
        }

        public function decode_and_safe_file_path( $file_name )
        {
            $file_name = urldecode( sanitize_file_name( $file_name ) );
            $file_name = str_replace( '252F', '/', $file_name );
            return $file_name;
        }

    } // End Independent_Favorite_Plugins
} // End if (!class_exists("Independent_Favorite_Plugins"))
