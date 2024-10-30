<?php

if (!class_exists("i_Favorite_Plugins_Admin")) {

    class i_Favorite_Plugins_Admin
    {
        use i_fv_Singleton;// use Singleton pattern

        protected $_plugin;// exemplar of i_Favorite_Plugins class
        protected $_settings;//single instance of settings
        protected $_logs;// single object of log

        protected function __construct()
        {
            $this->_settings = i_Favorite_Plugins_Settings::instance();
            $this->_plugin = Independent_Favorite_Plugins::instance();
            $this->_logs = i_Favorite_Plugins_Log::instance();
            
            add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
            add_action('admin_menu', array($this, 'create_menu'));
            add_action('network_admin_menu', array($this, 'create_menu'));

        } // End constructor

        function create_menu()
        {
            $t = array(
                'page-title' => __('Favorite Plugins Settings', $this->_settings->lang_domain),
                'menu-title' => __('Favorite Plugins Settings', $this->_settings->lang_domain),
            );
            if (!$this->_settings->is_active_for_network) {
                add_options_page($t['page-title'], $t['menu-title'], $this->_settings->key_capability_admin, "i-favorite-plugins-config" , array($this, 'settings_page'));
                add_action('admin_init', array($this, 'register_settings'));
                if (current_user_can($this->_settings->key_capability_admin))
                    add_filter('plugin_action_links_' . $this->_settings->plugin_name, array($this, 'admin_plugin_settings_link'));
            } else {
                //there are not options page in newtwork
                add_submenu_page('settings.php', $t['page-title'], $t['menu-title'], $this->_settings->key_capability_admin, "i-favorite-plugins-config", array($this, 'settings_page'));
                if (current_user_can($this->_settings->key_capability_admin))
                    add_filter('network_admin_plugin_action_links_' . $this->_settings->plugin_name, array($this, 'admin_plugin_network_settings_link'));

            }
        }

        function admin_plugin_settings_link($links)
        {
            $args = array('page' => plugin_basename(__FILE__));
            $url = add_query_arg($args, admin_url('options-general.php'));
            $settings_link = '<a href="' . esc_url($url) . '">' . __('Settings', $this->_settings->lang_domain) . '</a>';
            array_unshift($links, $settings_link);
            return $links;
        }

        function admin_plugin_network_settings_link($links)
        {

            $args = array('page' => plugin_basename(__FILE__));
            $url = add_query_arg($args, network_admin_url('settings.php'));
            $settings_link = '<a href="' . esc_url($url) . '">' . __('Settings', $this->_settings->lang_domain) . '</a>';
            array_unshift($links, $settings_link);
            return $links;
        }

        function admin_scripts()
        {
            global $pagenow;
            if (in_array($pagenow, array('options-general.php', 'settings.php')) && $_GET['page'] = plugin_basename(__FILE__)) {
                wp_enqueue_media();
                wp_register_script('upload-media-js', plugins_url('/js/uploader-media.js', $this->_settings->plugin_name), array('jquery'));
                wp_enqueue_script('upload-media-js');
            }
        }

        function register_settings()
        {

            register_setting('i-fv-settings-group', $this->_settings->name_opt, array($this, 'sanitize_options'));
        }

        public function sanitize_options($input)
        {
            $icon = $input['icon'];
            $icon['width'] = intval($icon['width']);
            $icon['height'] = intval($icon['height']);
            $icon['path-like'] = esc_url_raw($icon['path-like']);
            $icon['path-unlike'] = esc_url_raw($icon['path-unlike']);
            $input['icon'] = $icon;
            if (array_key_exists('ftp', $input) && $input ['ftp']) {
                $ftp = $input['ftp'];
                $ftp['enable'] = true;
                $ftp['username'] = sanitize_text_field($ftp['username']);
                $ftp['password'] = sanitize_text_field($ftp['password']);
                $ftp['server'] = ($ftp['server']);
                $ftp['file_name'] = sanitize_text_field($ftp['file_name']);

                //check ftp file
                if (!$this->check_ftp_file($ftp)) {
                    $this->_logs->log("Plugin cannot open or create file during saving ftp settings. Please check your settings of ftp connection and/or settings of the php server. FTP server must support passive mode in " . __FILE__ . ':' . __LINE__);
                    if (!is_network_admin()) {
                        foreach ($this->_logs->errors as $error) add_settings_error('i_fv_ftp_setting_error', esc_attr('settings_updated'), $error);
                        $input = get_option($this->_settings->name_opt);
                        return $input;
                    } else return false;
                }
            } else {
                $ftp = $this->_settings->default_options['ftp'];
                $ftp['enable'] = false;
                $this->_plugin->init_favorite_list_data();
            }
            $input['ftp'] = $ftp;
            if (is_network_admin() && !count($this->_logs->errors)) $this->_logs->messages[] = __('Settings saved.');
            return $input;
        }

        //received options already safe
        public function settings_page()
        {
            if (!current_user_can($this->_settings->key_capability_admin))
                wp_die(__('You do not have permission to access this page.'));
            if (isset($_POST[$this->_settings->name_opt]))
                $this->update_network_options();
            $options = $this->_settings->options;
            if (is_network_admin())
                $page = '';
            else
                $page = 'options.php';

            ?>
            <div class="wrap">
                <?php
                $this->_logs->show_all_messages();
                ?>
                <h2><?php _e('Favorite plugins', $this->_settings->lang_domain); ?></h2>

                <form method="post" action="<?php echo $page; ?>">
                    <?php if (is_network_admin()) wp_nonce_field('update_network_options', 'network_options');

                    settings_fields('i-fv-settings-group'); ?>

                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row"><?php _e('Icon for like', $this->_settings->lang_domain); ?>:
                            <td class="media-uploader"><input class="input-upload-image" type="hidden" size="36"
                                                              name="<?php echo $this->_settings->name_opt; ?>[icon][path-like]"
                                                              value="<?php echo $options['icon']['path-like']; ?>"/>
                                <img src="<?php echo $options['icon']['path-like']; ?>"/>
                                <br/><input class="button upload-image-button" type="button"
                                            value="<?php _e('Choose image', $this->_settings->lang_domain); ?>"
                                            data-upload-image="<?php _e('Upload image', $this->_settings->lang_domain); ?>"
                                            data-upload-image="<?php _e('Upload image', $this->_settings->lang_domain); ?>"/>
                            </td>
                            </th>
                            <th scope="row"><?php _e('Icon for unlike', $this->_settings->lang_domain); ?>:
                            <td class="media-uploader"><input class="input-upload-image" type="hidden" size="36"
                                                              name="<?php echo $this->_settings->name_opt; ?>[icon][path-unlike]"
                                                              value="<?php echo $options['icon']['path-unlike']; ?>"/>
                                <img src="<?php echo $options['icon']['path-unlike']; ?>"/>
                                <br/><input class="button upload-image-button" type="button"
                                            value="<?php _e('Choose image', $this->_settings->lang_domain); ?>"
                                            data-upload-image="<?php _e('Upload image', $this->_settings->lang_domain); ?>"
                                            data-upload-image="<?php _e('Upload image', $this->_settings->lang_domain); ?>"/>
                            </td>
                            </th>
                        </tr>
                        <tr valign="top">
                            <th scope="rowgroup"><?php _e('Size of the icon', $this->_settings->lang_domain); ?>
                            </th>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><?php _e('Width'); ?>:
                            <td>
                                <input type="number" id="width_icon"
                                       name="<?php echo $this->_settings->name_opt; ?>[icon][width]"
                                       value="<?php echo $options['icon']['width']; ?>"> px
                            </td>
                            </th>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><?php _e('Height'); ?>:
                            <td>
                                <input type="number" id="height_icon"
                                       name="<?php echo $this->_settings->name_opt; ?>[icon][height]"
                                       value="<?php echo $options['icon']['height']; ?>"> px
                            </td>
                            </th>
                            <th>
                            <td></td>
                            </th>
                            <th>
                            <td></td>
                            </th>
                        </tr>
                        <tr>
                            <td colspan="3">
                                <p class="help">
                                    <?php _e('For use image sprite specify the same image for both icons', $this->_settings->lang_domain) ?>
                                </p>
                            </td>
                        </tr>
                        <tr></tr>
                        <tr valign="top">
                            <th scope="rowgroup"><input
                                    type="checkbox" <?php checked(true, $options['ftp']['enable']); ?>
                                    name="<?php echo $this->_settings->name_opt; ?>[ftp][enable]"
                                    value="<?php echo $this->_settings->name_opt; ?>[ftp][enable]"
                                    onclick="jQuery('.ftp input').attr('disabled',!this.checked)"><?php _e('Enable ftp', $this->_settings->lang_domain); ?>

                            </th>
                        </tr>
                        <tr valign="top" class="ftp">
                            <th scope="row"><?php _e('Ftp server', $this->_settings->lang_domain); ?>:
                            <td>
                                <input type="text" id="width_icon"
                                       name="<?php echo $this->_settings->name_opt; ?>[ftp][server]"
                                       value="<?php echo $options['ftp']['server']; ?>"
                                    <?php disabled($options['ftp']['enable'], false); ?> >
                            </td>
                            </th>
                            <th scope="row"><?php _e('Username'); ?>:
                            <td>
                                <input type="text" id="width_icon"
                                       name="<?php echo $this->_settings->name_opt; ?>[ftp][username]"
                                       value="<?php echo $options['ftp']['username']; ?>"
                                    <?php disabled($options['ftp']['enable'], false); ?> >

                            </td>
                            </th>
                            <th scope="row"><?php _e('Password'); ?>:
                            <td>
                                <input type="password" id="width_icon"
                                       name="<?php echo $this->_settings->name_opt; ?>[ftp][password]"
                                       value="<?php echo $options['ftp']['password']; ?>"
                                    <?php disabled($options['ftp']['enable'], false); ?> >
                            </td>
                            </th>
                            <th scope="row"><?php _e('File name', $this->_settings->lang_domain); ?>:
                            <td>
                                <input type="text" id="width_icon"
                                       name="<?php echo $this->_settings->name_opt; ?>[ftp][file_name]"
                                       value="<?php echo $options['ftp']['file_name']; ?>"
                                    <?php disabled($options['ftp']['enable'], false); ?> >
                            </td>
                            </th>
                        </tr>
                    </table>

                    <p class="submit">
                        <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>"/>
                    </p>

                </form>
            </div>
            <?php
        }

        /**
         * Update plugin options in network mode         *
         *
         * @since 1.0
         *
         * @return null
         */
        protected function update_network_options()
        {
            if (
                !isset($_POST['network_options'])
                || !wp_verify_nonce($_POST['network_options'], 'update_network_options')
            ) return;
            $options = $_POST[$this->_settings->name_opt];
            $options = $this->sanitize_options($options);
            $this->_settings->options = $options;
            if (!count($this->_logs->errors))
                update_option($this->_settings->name_opt, $options);
        }

        /**
         * Check ftp file with list of favorite plugins. If file not exists create it
         * If the file does not contain information in the properly format clears it
         *
         * @since 1.0
         *
         * @param array $ftp Params of ftp connection
         * @return bool True if connect was success
         */
        protected function check_ftp_file($ftp)
        {
            $this->_settings->file_name = 'ftp://' . $ftp['username'] . ':' . $ftp['password'] . '@' . $ftp['server'] . '/' . $ftp['file_name'];
            $success = true;
            try {
                $this->_plugin->init_favorite_list_data();
            } catch (Exception $e) {
                $this->_logs->log($e);
                $this->_logs->errors[] = __('Incorrect ftp setting', $this->_settings->lang_domain);
                $success = false;
            }
            return $success;
        }

    } // End i_Favorite_Plugins_Admin
} // End if (!class_exists("i_Favorite_Plugins_Admin"))

?>