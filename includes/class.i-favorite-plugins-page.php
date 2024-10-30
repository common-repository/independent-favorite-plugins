<?php

if ( !class_exists( "i_Favorite_Plugins_Page" ) ) {
    /**
    * Class for export favorite plugins page
    */
    class i_Favorite_Plugins_Page
    {
        use i_fv_Singleton;// use Singleton pattern

        protected $_plugin;//exemplar of Favorite_Plugins class
        protected $_settings; //plugin settings
        protected $_logs;

        protected function __construct()
        {
            $this->_plugin = Independent_Favorite_Plugins::instance();
            $this->_settings = i_Favorite_Plugins_Settings::instance();

            if ( isset($_POST[ 'i_fv_checked' ]) )
                add_action( 'admin_init', array( $this, 'download_zip_file' ) );
            add_action( 'admin_menu', array( $this, 'create_menu' ) );
            add_action( 'network_admin_menu', array( $this, 'create_menu' ) );

        } // End constructor

        function create_menu()
        {
            $t = array(
                'page-title' => __( 'Favorite Plugins', $this->_settings->lang_domain ),
                'menu-title' => __( 'Export favorite plugins', $this->_settings->lang_domain ),
            );
            add_management_page( $t[ 'page-title' ], $t[ 'menu-title' ], $this->_settings->key_capability_page, "i-export-favorite-plugins" , array( $this, 'plugin_page' ) );
        }

        // create export plugin page
        function plugin_page()
        {
            if ( !current_user_can( $this->_settings->key_capability_page ) )
                wp_die( __( 'You do not have permission to access this page.' ) );
            if ( isset($_GET[ 'unfavorite_plugin' ]) && wp_verify_nonce( $_GET[ '_wpnonce' ], 'delete_plugin' ) ) {
                $this->_plugin->delete_plugin_favorite( $_GET[ 'unfavorite_plugin' ] );
            }
            $this->export();
        }

        /**
         * Download for the user a zip archive with the files selected favorite plugins
         *
         * @since 1.0
         *
         * @return null
         */
        public function download_zip_file()
        {
            if ( !current_user_can( $this->_settings->key_capability_page ) )
                wp_die( __( 'You do not have permission to access this page.' ) );
            if ( isset($_POST[ 'i_fv_checked' ]) ) {
                if ( !isset($_POST[ 'export' ]) || !wp_verify_nonce( $_POST[ 'export' ], 'i_fv_export_plugins' ) ) {
                    return;
                }
                $filename = $this->_plugin->export_zip( $_POST[ 'i_fv_checked' ] );
                header( "Pragma: public" );
                header( "Expires: 0" );
                header( "Cache-Control: must-revalidate, post-check=0, pre-check=0" );
                header( "Cache-Control: public" );
                header( "Content-Description: File Transfer" );
                header( "Content-type: application/octet-stream" );
                header( "Content-Disposition: attachment; filename=\"export.zip\"" );
                header( "Content-Transfer-Encoding: binary" );
                header( "Content-Length: " . filesize( $filename ) );
                flush();
                readfile( $filename );
                unlink( $filename );
            }
        }

        /**
         * Prepare a list of favorite plugins for export and display it
         *
         * @since 1.0
         *
         * @return null
         */
        public function export()
        {
            $plugins = $this->_plugin->get_favorites();
            foreach ( $plugins as $plugin => &$data ) {
                $path = $this->_settings->plugins_all_dir .'/'. $plugin;
                $data[ 'disable' ] = true;
                //exclude non-existing plugins
                if ( !file_exists( $path ) ) {
                    $data[ 'availability' ] = false;
                } else {
                    $data = get_plugin_data( $path );
                    $data[ 'availability' ] = true;
                }
            }

            $this->display_fv_plugins_list_table( $plugins );
        }

        /**
         * Display a favorite plugins export form
         *
         * @since 1.0
         *
         * @param array $items Array of the plugins with plugins data         *
         * @return null
         */
        protected function display_fv_plugins_list_table( $items )
        {
            ?>
            <div class="wrap">
            <h2><?php _e( 'Export favorite plugins', $this->_settings->lang_domain ); ?></h2>


            <form method="post" action="">

            <?php wp_nonce_field( 'i_fv_export_plugins', 'export' ); ?>

            <p class="submit">
                <input type="submit" class="button-primary" value="<?php _e( 'Export' ); ?>"/>
            </p>
            <table class="wp-list-table widefat plugins">
            <thead>
            <tr>
                <th scope="col" id="cb" class="manage-column column-cb check-column" style=""><label
                        class="screen-reader-text"
                        for="cb-select-all-1"><?php _e( 'Select all' ); ?></label><input
                        id="cb-select-all-1" type="checkbox" checked></th>
                <th scope="col" id="name" class="manage-column column-name"
                    style=""><?php _e( 'Plugin' ); ?></th>
                <th scope="col" id="description" class="manage-column column-description"
                    style=""><?php _e( 'Description' ); ?></th>
                <th scope="col" id="description" class="manage-column column-description"
                    style=""><?php _e( 'Availability', $this->_settings->lang_domain ); ?></th>
            </tr>
            </thead>
            <?php
            foreach ( $items as $plugin_file => $plugin_data ) {
                ?>
                <tr valign="top" class="media-uploader">
                    <th scope='row' class='check-column'><input
                            type='checkbox' <?php checked( $plugin_data[ 'availability' ], true ); ?>
                            name='i_fv_checked[]'
                            value='<?php echo esc_attr( $plugin_file ); ?>'
                            <?php disabled( $plugin_data[ 'availability' ], false ); ?>
                            />
                    </th>
                    <td class='plugin-title'><? echo $plugin_data[ 'Title' ] ?>
                    </td>
                    <td class='column-description desc'>
                        <div class='plugin-description'><?php echo $plugin_data[ 'Description' ] ?></div>

                </div>
                </td>
                <td class='column-availability'>
                    <div class='plugin-availability'><?php
                        if ( $plugin_data[ 'availability' ] )
                            _e( 'Available for export', $this->_settings->lang_domain );
                        else {
                            _e( 'Not present on this website', $this->_settings->lang_domain );
                            echo '. <a href="' .
                                wp_nonce_url( $_SERVER[ 'REQUEST_URI' ] . '&unfavorite_plugin=' . esc_attr( $plugin_file ),
                                    'delete_plugin' ) . '">' . __( 'Remove from favorites' ) . '</a>';
                        }
                        ?></div>
                    </div>
                </td>
                </tr>
            <?php
            }
            ?>
            </table>
            <p class="submit">
                <input type="submit" class="button-primary" value="<?php _e( 'Export' ) ?>"/>
            </p>
            </form>
            </div><?php
        }
    } // End Favorite_Plugins_Page
} // End if (!class_exists("i_Favorite_Plugins_Page"))

?>