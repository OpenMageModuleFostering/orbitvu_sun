<?php
/**
 * Orbitvu PHP  Orbitvu eCommerce debugger
 * @Copyright: Orbitvu Sp. z o.o. is the owner of full rights to this code
 */

final class OrbitvuDebugger {
    
    /**
     * Debugger
     * @param array $params
     * @return string
     */
    public static function Debug($params) {
        //---------------------------------------------------------------------------------------------------
        $out = 'debug: ['.print_r($params, true).']'."\n";
        //---------------------------------------------------------------------------------------------------
        return print '<pre class="debug" onDblClick="this.style.display=\'none\';">'.$out.'</pre>';
        //---------------------------------------------------------------------------------------------------
    }
    
    /**
     * SQL Debugger
     * @param string $function
     * @param string $db_query
     * @return string
     */
    public static function DebugSQL($function, $db_query) {
        //---------------------------------------------------------------------------------------------------
        $params = array(
            'function' 	=> $function,
            'sql'		=> $db_query,
            'sql-info'	=> (empty($error) ? 'success' : $error)
        );
        //---------------------------------------------------------------------------------------------------	
        return self::Debug($params);
        //---------------------------------------------------------------------------------------------------
    }
    
}

?>