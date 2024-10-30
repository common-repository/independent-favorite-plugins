<?php

/**
 * Critical section class
 * This file can be used separate of wordpress, so it isn't under wordpress GPL license. It's under license what you purchase from author.
 */
if ( !class_exists( "i_fv_Critical_section" ) ) {
    class i_fv_Critical_section
    {
        private $_fp;//file pointer to the critical section
        private $_file_name = 'critical_section.txt';
        private $_max_attempt = 20;//number of attempts to access to the critical section
        private $_interval = 10; //interval between attempts to access to the critical section in ms


        function __construct( $data )
        {
            if ( isset($data['file_name']) ) $this->_file_name = $data['file_name'];
        }

        /**
         * Grab the critical section
         *
         * @throws Exception if can't critical section file
         * @return bool true if case of success.
         *
         */
        function begin()
        {
            if ( !@file_exists( $this->_file_name ) )
                $this->_fp = @fopen( $this->_file_name, 'w' );
            else
                $this->_fp = @fopen( $this->_file_name, 'r' );
            if ( !$this->_fp ) throw new Exception( "Class " . __CLASS__ . " can't open file {$this->_file_name}" );
            for ( $i = 0; $i < $this->_max_attempt; $i++ ) {
                if ( flock( $this->_fp, LOCK_EX ) ) {
                    return true;
                }
                usleep( $this->_interval );
            }
            fclose( $this->_fp );
            return false;
        }

        /**
         * Release the critical section
         *
         * @return bool true if case of success.
         */
        function end()
        {
            flock( $this->_fp, LOCK_UN );
            fclose( $this->_fp );
        }

    }
}