<?php
/*
Plugin Name: Independent Favorite Plugins
Plugin URI: https://wordpress.org/plugins/independent-favorite-plugins/
Description: Add plugins to favorite. Can work with plugins which aren't present on wordpress.org or other websites
Version: 1.1
Author: Irina Gracheva
Author URI: https://profiles.wordpress.org/irina_yurievna/
Email: ourtusenka@yandex.ru
License: GPLv2 or later
*/

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

if ( is_admin() || is_network_admin() ) {


    $include_path = plugin_dir_path( __FILE__ ) . 'includes/';
    require_once( $include_path . 'trait.i-fv-singleton.php' );

    require_once( plugin_dir_path( __FILE__ ) . 'class.i-favorite-plugins-settings.php' );
    require_once( $include_path . 'class.independent-favorite-plugins.php' );
    require_once( $include_path . 'class.i-favorite-plugins-log.php' );
    require_once( $include_path . 'class.i-fv-critical-section.php' );
    require_once( $include_path . 'class.i-favorite-plugins-admin.php' );
    require_once( $include_path . 'class.i-favorite-plugins-page.php' );

    $i_favorite_plugins = Independent_Favorite_Plugins::instance();
    $i_favorite_plugins_admin = i_Favorite_Plugins_Admin::instance();
    $i_favorite_plugins_page = i_Favorite_Plugins_Page::instance();

} // End instantiate class

?>