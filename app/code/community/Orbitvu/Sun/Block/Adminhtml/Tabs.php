<?php
/**
 * @category    Orbitvu
 * @package     Orbitvu_Sun
 * @copyright   Copyright (C) 2014 Orbitvu (http://www.orbitvu.com)
 * @license     http://www.orbitvu.com/plugins/license
 * @version     1.0.0
 */

class Orbitvu_Sun_Block_Adminhtml_Tabs extends Mage_Adminhtml_Block_Catalog_Product_Edit_Tabs {

    private $parent;

    /*
     * Add new custom tab "Orbitvu" to admin panel
     */
    protected function _prepareLayout() {
    	//------------------------------------------------------------------------------------------------------------------
        $this->parent = parent::_prepareLayout();

        $request = Mage::app()->getRequest();
        $product_id = $request->getParam('id'); 
        
        if ($product_id > 0) {
            $this->addTab('tabid', array(
                'label'     => Mage::helper('catalog')->__('Orbitvu SUN'),
                'content'   => $this->getLayout()->createBlock('sun/adminhtml_tabs_tabid')->toHtml()
            ));
        }
        //------------------------------------------------------------------------------------------------------------------
        return $this->parent;
        //------------------------------------------------------------------------------------------------------------------
    }
}
?>