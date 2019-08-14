<?php
/**
 * @category    Orbitvu
 * @package     Orbitvu_Sun
 * @copyright   Copyright (C) 2014 Orbitvu (http://www.orbitvu.com)
 * @license     http://www.orbitvu.com/plugins/license
 * @version     1.0.0
 */

class Orbitvu_Sun_Block_Html5
{
    
    /*
     * Options list
     */
    public function OrbitvuHtml5Options() {
        return array(
            'yes'        => Mage::helper('adminhtml')->__('Always use HTML5'),
            'no'         => Mage::helper('adminhtml')->__('Use Flash if available, otherwise use HTML5')
        );
    }
    
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray() {
        $array = $this->OrbitvuHtml5Options();
        
        $ret = array();
        foreach ($array as $key => $value) {
            $ret[] = array(
                'value' => $key,
                'label' => $value               
            );
        }
        
        return $ret;
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray() {
        return $this->OrbitvuHtml5Options();
    }
}
?>