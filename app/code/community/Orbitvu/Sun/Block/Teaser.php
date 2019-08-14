<?php
/**
 * @category    Orbitvu
 * @package     Orbitvu_Sun
 * @copyright   Copyright (C) 2014 Orbitvu (http://www.orbitvu.com)
 * @license     http://www.orbitvu.com/plugins/license
 * @version     1.0.0
 */

class Orbitvu_Sun_Block_Teaser
{
    
    /*
     * Options list
     */
    public function OrbitvuTeaserOptions() {
        return array(
            'autorotate'        => Mage::helper('adminhtml')->__('Auto-rotation'),
            'play'              => Mage::helper('adminhtml')->__('Play button enables rotation'),
            'static'            => Mage::helper('adminhtml')->__('No teaser (static presentation)'),
            'onerotation'       => Mage::helper('adminhtml')->__('Quick single rotation (make sense only if 360 presentation is your first gallery item)'),
            'onerotationslow'   => Mage::helper('adminhtml')->__('Slow single rotation (make sense only if 360 presentation is your first gallery item)'),
            'sun'               => Mage::helper('adminhtml')->__('Use Orbitvu SUN setting')
        );
    }
    
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray() {
        $array = $this->OrbitvuTeaserOptions();
        
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
        return $this->OrbitvuTeaserOptions();
    }
}
?>