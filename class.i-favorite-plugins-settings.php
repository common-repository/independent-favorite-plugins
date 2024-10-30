<?php
if ( !class_exists( "i_Favorite_Plugins_Settings" ) ) {

    class i_Favorite_Plugins_Settings
    {
        use i_fv_Singleton;// use Singleton pattern
        
        //Key capabilities
        public $key_capability = 'activate_plugins'; //capability for add/remove favorite plugins
        public $key_capability_page = 'activate_plugins';//capability for use the plugin's page
        public $key_capability_admin = 'activate_plugins';//capability for admin the plugin

        public $name_opt = 'i_fv_admin_options_name';
        public $default_options = array(
            'icon' => array( 'path-like' => 'images/stars-2x.png', 'path-unlike' => 'images/stars-2x.png', 'height' => 35, 'width' => 42 ),
            'ftp' => array( 'enable' => false, 'server' => 'your server', 'username' => 'your username', 'password' => 'your password', 'file_name' => 'favorites.txt' ), // manage ftp access to the file
        );

        public $is_active_for_network = false;
        public $is_remote_access = false;
        public $is_active = false;

        //Default file with favorite plugins
        public $file_name = 'files/favorites.txt'; // file name for saving the list of favorite plugins. Used if ftp is disable.
        public $file_critical_section = 'files/critical-section.txt';
        public $log_file = 'files/log.txt';

        public $plugin_name;
        public $plugin_display_name = '"Independent Favorite Plugins"';
        public $plugin_dir;
        public $plugins_all_dir; // absolute path to the plugins directory

        public $arm_version ='1.1';

        public $options;

        public $lang_domain='independent_favorite_plugins';

        protected function __construct()
        {
            $this->default_options['icon']['path-like'] = plugins_url( $this->default_options['icon']['path-like'], __FILE__ );
            $this->default_options['icon']['path-unlike'] = plugins_url( $this->default_options['icon']['path-unlike'], __FILE__ );

            $this->plugin_name = plugin_basename( __DIR__ . '/independent-favorite-plugins.php' );
            $this->plugin_dir = plugin_dir_path( __FILE__ );
            $this->plugins_all_dir = dirname( dirname( __FILE__ ) );

            $this->file_critical_section = $this->plugin_dir . $this->file_critical_section;

            $this->log_file = $this->plugin_dir . $this->log_file;

            if ( !function_exists( 'is_plugin_active_for_network' ) ) {
                require_once(ABSPATH . '/wp-admin/includes/plugin.php');
            }

            $this->is_active_for_network = is_plugin_active_for_network( $this->plugin_name );
            $this->is_active = is_plugin_active( $this->plugin_name ) || $this->is_active_for_network;

            $this->check_options();
            $this->options = get_option( $this->name_opt, $this->default_options );
            if ( $this->options['ftp']['enable'] ) {
                $this->is_remote_access = true;
                $this->file_name = 'ftp://' . $this->options['ftp']['username'] . ':' . $this->options['ftp']['password'] . '@' . $this->options['ftp']['server'] . '/' . $this->options['ftp']['file_name'];
            } else {
                $this->file_name = $this->plugin_dir . $this->file_name;
                $this->is_remote_access = false;
            }
        }

        public function check_options()
        {
            if ( get_option( $this->name_opt ) === false ) {
                add_option( $this->name_opt, $this->default_options );
            } elseif ( !is_array( get_option( $this->name_opt ) ) ) {
                update_option( $this->name_opt, $this->default_options );
            }
        }
    }
}//!End class i_Favorite_Plugins_Settings