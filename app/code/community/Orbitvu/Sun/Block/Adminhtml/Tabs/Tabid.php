<?php
/**
 * @category    Orbitvu
 * @package     Orbitvu_Sun
 * @copyright   Copyright (C) 2014 Orbitvu (http://www.orbitvu.com)
 * @license     http://www.orbitvu.com/plugins/license
 * @version     1.0.0
 */

class Orbitvu_Sun_Block_Adminhtml_Tabs_Tabid extends Mage_Adminhtml_Block_Widget {
	
    /*
     * Set template "orbitvu.phtml" right ahead!
     */
    public function __construct() {
    	//------------------------------------------------------------------------------------------------------------------
        parent::__construct();
        
        $this->setTemplate('sun/orbitvu.phtml');    
        //------------------------------------------------------------------------------------------------------------------    
    }
}

?>