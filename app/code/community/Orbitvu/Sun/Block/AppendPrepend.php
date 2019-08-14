<?php
/**
 * @category    Orbitvu
 * @package     Orbitvu_SelfHosted
 * @copyright   Copyright (C) 2014 Orbitvu (http://www.orbitvu.com)
 * @license     http://www.orbitvu.com/plugins/license
 * @version     1.0.0
 */

class Orbitvu_Sun_Block_AppendPrepend
{
    
    /*
     * Options list
     */
    public function OrbitvuAppendPrependOptions() {
        return array(
            'append'        => Mage::helper('adminhtml')->__('Append'),
            'prepend'       => Mage::helper('adminhtml')->__('Prepend')
        );
    }
    
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray() {
        $array = $this->OrbitvuAppendPrependOptions();
        
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
        return $this->OrbitvuAppendPrependOptions();
    }
}
?>