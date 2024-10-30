<?php

if ( !trait_exists( "i_fv_Singleton" ) ) {
    trait i_fv_Singleton
    {
        /**
         * @return static
         */
        public static function instance()
        {
            if ( ! static::$_instance  ) {
                static::$_instance = new static();
            }
            return static::$_instance;
        }

        protected static $_instance = null;

        protected function _clone()
        {
        }

        protected function _wakeup()
        {
        }
    }
}