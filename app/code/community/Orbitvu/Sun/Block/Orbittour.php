<?php
/**
 * @category    Orbitvu
 * @package     Orbitvu_Sun
 * @copyright   Copyright (C) 2014 Orbitvu (http://www.orbitvu.com)
 * @license     http://www.orbitvu.com/plugins/license
 * @version     1.0.0
 */

class Orbitvu_Sun_Block_Orbittour
{
    
    /*
     * Options list
     */
    public function OrbitvuOrbittourOptions() {
        return array(
            'right_views'     => Mage::helper('adminhtml')->__('Views to the right'),
            'default'         => Mage::helper('adminhtml')->__('Views at the bottom'),
            'noviews'         => Mage::helper('adminhtml')->__('No views'),
            'sun'             => Mage::helper('adminhtml')->__('Use Orbitvu SUN setting')
        );
    }
    
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray() {
        $array = $this->OrbitvuOrbittourOptions();
        
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
        return $this->OrbitvuOrbittourOptions();
    }
}
?>