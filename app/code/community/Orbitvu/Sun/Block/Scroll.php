<?php
/**
 * @category    Orbitvu
 * @package     Orbitvu_SelfHosted
 * @copyright   Copyright (C) 2014 Orbitvu (http://www.orbitvu.com)
 * @license     http://www.orbitvu.com/plugins/license
 * @version     1.0.0
 */

class Orbitvu_Sun_Block_Scroll
{
    
    /*
     * Options list
     */
    public function OrbitvuScrollOptions() {
        return array(
            'yes'        => Mage::helper('adminhtml')->__('Enable thumbnails scroll'),
            'no'         => Mage::helper('adminhtml')->__('Display thumbnails inline (without scroll)')
        );
    }
    
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray() {
        $array = $this->OrbitvuScrollOptions();
        
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
        return $this->OrbitvuScrollOptions();
    }
}
?>