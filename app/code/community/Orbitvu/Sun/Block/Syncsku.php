<?php
/**
 * @category    Orbitvu
 * @package     Orbitvu_Sun
 * @copyright   Copyright (C) 2014 Orbitvu (http://www.orbitvu.com)
 * @license     http://www.orbitvu.com/plugins/license
 * @version     1.0.0
 */

class Orbitvu_Sun_Block_Syncsku
{
    
    /*
     * Options list
     */
    public function OrbitvuSyncskuOptions() {
        return array(
            'false' => Mage::helper('adminhtml')->__('Disable'),
            'true_ifempty' => Mage::helper('adminhtml')->__('Enable, if remote SKU is empty'),
            'true' => Mage::helper('adminhtml')->__('Enable')
        );
    }
    
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray() {
        $array = $this->OrbitvuSyncskuOptions();
        
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
        return $this->OrbitvuSyncskuOptions();
    }
}
?>