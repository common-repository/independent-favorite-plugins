<?php
if ( !class_exists( "i_Favorite_Plugins_Log" ) ) {
    class i_Favorite_Plugins_Log
    {
        use i_fv_Singleton;// use Singleton pattern

        public $errors = array();
        public $messages = array();

        protected $_settings;

        protected function __construct()
        {
            $this->_settings = i_Favorite_Plugins_Settings::instance();
        }

        // function for log errors
        public function log( $text )
        {
            error_log( date( DATE_RSS ) . ' ' . $text . "\n", 3, $this->_settings->log_file );
        }


        /**
         * Show plugin message
         *
         * @since 1.0
         *
         * @param string $message Message text
         * @param bool $error_style Is message show as error
         * @return null
         */
        public function show_message( $message, $error_style = false )
        {
            if ( $message ) {
                if ( $error_style ) {
                    echo '<div id="message" class="error" >';
                } else {
                    echo '<div id="message" class="updated fade">';
                }
                echo $message . '</div>';
            }
        }

        /**
         * Show all plugin messages
         *
         * @since 1.0
         *
         * @return null
         */
        public function show_all_messages()
        {
            foreach ( $this->errors as $message ) {
                $this->show_message( $message, true );
            }
            foreach ( $this->messages as $message ) {
                $this->show_message( $message );
            }
        }
        
    }
}//!End class i_Favorite_Plugins_Log