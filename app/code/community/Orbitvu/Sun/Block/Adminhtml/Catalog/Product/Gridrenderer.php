<?php
/**
 * @category    Orbitvu
 * @package     Orbitvu_Sun
 * @copyright   Copyright (C) 2014 Orbitvu (http://www.orbitvu.com)
 * @license     http://www.orbitvu.com/plugins/license
 * @version     1.0.0
 */

class Orbitvu_Sun_Block_Adminhtml_Catalog_Product_Gridrenderer extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract {
    
    /*
     * Render cell value for column "Orbitvu"
     * No time and no need for external CSS etc.
     */
    public function render(Varien_Object $row) { 
        //------------------------------------------------------------------------------------------------------------------
        $product_id = $row->getId();
        //------------------------------------------------------------------------------------------------------------------
        $request = Mage::app()->getRequest();
    	$observer = Mage::getSingleton('sun/observer');
        $_Orbitvu = $observer->ExtendOrbitvu();
        //------------------------------------------------------------------------------------------------------------------
        $presentation = $_Orbitvu->GetProductPresentation($product_id);
        $types = $presentation['types'];
        //------------------------------------------------------------------------------------------------------------------
        $out = '<div style="width: 100px; min-height: 16px; text-align: center; clear: both; overflow: hidden; zoom: 1.0; position: relative;">';
        //------------------------------------------------------------------------------------------------------------------
        $dot = ' display; block; position: absolute; top: 0; left: 0; width: 16px; height: 17px; background-repeat: no-repeat; ';
        //------------------------------------------------------------------------------------------------------------------
        /*
         * Linked icon
         * Presentation thumbnail
         * Presentation types icon
         */
        if ($presentation['id'] > 0) { 
            //------------------------------------------------------------------------------------------------------------------
            $thumbnail = $presentation['items'][0]['thumbnail'];
            foreach ($presentation['items'] as $item) {
                if ($item['status'] == 'active') {
                    $thumbnail = $item['thumbnail'];
                    break;
                }
            }
            //------------------------------------------------------------------------------------------------------------------
            $out .= '<div style="width: 100%; height: 100%;  background: white; clear: both; overflow: hidden; zoom: 1.0; border-radius: 5px;"><span style="'.$dot.' background-position: 0 -108px; background-color: rgba(255, 255, 255, 0.5); border-bottom-right-radius: 5px; background-image: url('.Mage::getBaseUrl('media').'orbitvu/blue.png);" title="'.$this->__('Linked').'"></span>
                
                <div style="clear: both; overflow: hidden; zoom: 1.0;"><img src="'.$thumbnail.'?max_width=100&max_height=50" style="max-width: 100px; border-radius: 5px; border: 2px solid white;" alt="" /></div>';
            
            $out .= '<div style="position: absolute; bottom: 0; right: 0;">';
            //------------------------------------------------------------------------------------------------------------------
            foreach ($types as $type) {
                //------------------------------------------------------------------------------------------------------------------
                switch ($type) {
                    case '0':
                        $text   = $this->__('Orbittour');
                        $bg     = '0 -92px';
                    break;
                    case '1':
                        $text   = $this->__('360&deg;');
                        $bg     = '-80px -108px';
                    break;
                    default:
                        $text   = $this->__('2D');
                        $bg     = '-16px -92px';
                    break;
                }
                
                $out .= '
                    <span style="float: left; width: 16px; height: 16px; margin: 0 -2px -2px 4px; background-position: '.$bg.'; background-color: rgba(255, 255, 255, 0.5); background-image: url('.Mage::getBaseUrl('media').'orbitvu/blue.png); background-repeat: no-repeat; border-radius: 5px; border: 2px solid white;" class="orbitvu_tooltip" title="'.$text.'"></span>
                ';
                //------------------------------------------------------------------------------------------------------------------
            }
            //------------------------------------------------------------------------------------------------------------------
            $out .= '</div></div>';
        }
        else {
            //------------------------------------------------------------------------------------------------------------------
            /*
             * Unlinked icon
             */
            if ($_Orbitvu->IsProductUnlinked($product_id)) {
                //------------------------------------------------------------------------------------------------------------------
                $out .= '<span style="'.$dot.' background-position: -16px -108px; background-image: url('.Mage::getBaseUrl('media').'orbitvu/black.png);" title="'.$this->__('Unlinked').'"></span>';
                //------------------------------------------------------------------------------------------------------------------
            }
            /*
             * Not linked icon
             */
            else {
                //------------------------------------------------------------------------------------------------------------------
                $out .= '<span style="'.$dot.' background-position: -16px -108px; background-image: url('.Mage::getBaseUrl('media').'orbitvu/black.png);" title="'.$this->__('Not linked').'"></span>';
                //------------------------------------------------------------------------------------------------------------------
            }
            //------------------------------------------------------------------------------------------------------------------
        }
        //------------------------------------------------------------------------------------------------------------------
        $out .= '</div>';
        
        return $out;
        //------------------------------------------------------------------------------------------------------------------
    }
    
}
?>