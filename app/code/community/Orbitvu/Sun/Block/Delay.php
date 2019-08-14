<?php
/**
 * @category    Orbitvu
 * @package     Orbitvu_Sun
 * @copyright   Copyright (C) 2014 Orbitvu (http://www.orbitvu.com)
 * @license     http://www.orbitvu.com/plugins/license
 * @version     1.0.0
 */

class Orbitvu_Sun_Block_Delay
{
    
    /*
     * Options list
     */
    public function OrbitvuDelayOptions() {
        return array(
            '0'   => Mage::helper('adminhtml')->__('No delay'),
            '1'   => Mage::helper('adminhtml')->__('0,1 second'),
            '2'   => Mage::helper('adminhtml')->__('0,2 second'),
            '3'   => Mage::helper('adminhtml')->__('0,3 second'),
            '4'   => Mage::helper('adminhtml')->__('0,4 second'),
            '5'   => Mage::helper('adminhtml')->__('half a second')
        );
    }
    
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray() {
        $array = $this->OrbitvuDelayOptions();
        
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
        return $this->OrbitvuDelayOptions();
    }
}
?>