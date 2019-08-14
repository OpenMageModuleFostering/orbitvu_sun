<?php
/**
 * @category    Orbitvu
 * @package     Orbitvu_Sun
 * @copyright   Copyright (C) 2014 Orbitvu (http://www.orbitvu.com)
 * @license     http://www.orbitvu.com/plugins/license
 * @version     1.0.0
 */

class Orbitvu_Sun_Block_KeyComment
{
    /**
     * Parsed comment text
     * @return type string
     */
    public function getCommentText() { 
        return Mage::helper('catalog')->__('You need <a href="http://orbitvu.co" target="_blank">Orbitvu SUN</a> account and License Key to use Orbitvu extension.').'<br />'.'<a href="'.Mage::helper('adminhtml')->getUrl('*/catalog_product/index/sun/show_welcome').'">'.Mage::helper('catalog')->__('Register').'</a> '.Mage::helper('catalog')->__('trial account or').' <a href="'.Mage::helper('adminhtml')->getUrl('*/catalog_product/index/sun/show_welcome').'">'.Mage::helper('catalog')->__('use DEMO License Key').'</a>.';
    }
}
?>