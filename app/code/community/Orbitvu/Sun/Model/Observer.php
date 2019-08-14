<?php
/**
 * @category    Orbitvu
 * @package     Orbitvu_Sun
 * @copyright   Copyright (C) 2014 Orbitvu (http://www.orbitvu.com)
 * @license     http://www.orbitvu.com/plugins/license
 * @version     1.0.0
 */

class Orbitvu_Sun_Model_Observer {
    // Mark as Singleton (for Magento)
    static protected $_singletonFlag = false;
    
    public $_Orbitvu;
    //-------------------------------------------------------------------------------------------------------
    public function ExtendOrbitvu($return = true) {
    	$helpers = __DIR__.'/../controllers/OrbitvuAdmin.php';
    	include_once($helpers);
    	
    	$this->_Orbitvu = new OrbitvuAdmin(false);
    	
    	//-------------------------------------------------------------------------------------------------------
    	if ($return) {
            return $this->_Orbitvu;
    	}
    	//-------------------------------------------------------------------------------------------------------
    }
    //------------------------------------------------------------------------------------------------------- 
    public function UpdateThumbnails($product_id) {
        //-------------------------------------------------------------------------------------------------------
        $this->ExtendOrbitvu();
        //-------------------------------------------------------------------------------------------------------
        $CurrentProduct = Mage::getModel('catalog/product')->load($product_id);
        $ProductHelper = Mage::helper('catalog/product');
        $medias = $CurrentProduct->getMediaGalleryImages();
        $orbithumbs = $this->_Orbitvu->GetProductThumbnails($product_id);
        //-------------------------------------------------------------------------------------------------------
        $o_thumbs_count = intval(count($orbithumbs)); // = 1 or 0
        $m_thumbs_count = intval(count($medias));
        $n = $m_thumbs_count - $o_thumbs_count;
        //-------------------------------------------------------------------------------------------------------
        if ($o_thumbs_count > 0) {
            $this->DeleteThumbnail($product_id, $orbithumbs[0]);
            $o_thumbs_count = 0;
        }
        //-------------------------------------------------------------------------------------------------------
        if ($m_thumbs_count > 1 && $o_thumbs_count == 0) {
            // magento own images - do nothing
        }
        else if ($o_thumbs_count == 0 && $m_thumbs_count == 0) {
            //-------------------------------------------------------------------------------------------------------
            try {
                $new_thumbnail = $this->AddThumbnail($product_id);
                if (!empty($new_thumbnail)) {
                    $this->_Orbitvu->SetProductThumbnail($product_id, $new_thumbnail);
                }
            }
            catch (Exception $e) {}
            //-------------------------------------------------------------------------------------------------------
        }
        //-------------------------------------------------------------------------------------------------------
        return true;
        //-------------------------------------------------------------------------------------------------------
    }
    
    public function AddThumbnail($product_id) {
        //-------------------------------------------------------------------------------------------------------
        $presentation = $this->_Orbitvu->GetProductPresentation($product_id, true);
        //-------------------------------------------------------------------------------------------------------
        if (count($presentation['items']) <= 0) {
            return false;
        }
        //-------------------------------------------------------------------------------------------------------
        $file = 'http:'.$presentation['items'][0]['thumbnail'];
        
        if (stristr($file, '.png')) {
            $ext = 'png';
        }
        else {
            $ext = 'jpeg';
        }
        //-------------------------------------------------------------------------------------------------------
        $sun = file_get_contents($file);
        //-------------------------------------------------------------------------------------------------------
        $media_api = Mage::getModel('catalog/product_attribute_media_api');
        //-------------------------------------------------------------------------------------------------------
        $newImage = array(
            'file' => array(
                'name'      => md5(date('H:i:s Y-m-d').'Orbitvu Thumbnail'.mt_rand(0, 999999)),
                'content'   => base64_encode($sun),
                'mime'      => 'image/'.$ext
            ),
            'label'    => 'Orbitvu Thumbnail',
            'position' => 1,
            'types'    => array('thumbnail', 'small_image', 'image'),
            'exclude'  => 1
        );
        //-------------------------------------------------------------------------------------------------------
        return $media_api->create($product_id, $newImage);
        //-------------------------------------------------------------------------------------------------------
    }
    
    public function DeleteThumbnail($product_id, $orbitvu_entry) {
        //-------------------------------------------------------------------------------------------------------
        $mediaApi = Mage::getModel('catalog/product_attribute_media_api');
        $items = $mediaApi->items($product_id);
        $this->_Orbitvu->DeleteProductThumbnail($product_id);
        //-------------------------------------------------------------------------------------------------------
        foreach ($items as $item) {
            if ($item['file'] == $orbitvu_entry['thumbnail']) {
                $mediaApi->remove($product_id, $item['file']);
                $fileName = Mage::getBaseDir('media').'/catalog/product'.$item['file'];

                if (file_exists($fileName)) {
                    unlink($fileName);
                }
            }
        }
        //-------------------------------------------------------------------------------------------------------
    }
    
    /*
     * Make synchronization
     */
    public function SynchronizeAllProducts() {
        //------------------------------------------------------------------------------------------------------- 
        $products = $this->_Orbitvu->SynchronizeAllProducts();
        foreach ($products as $product) {
            $this->UpdateThumbnails($product['product_id']);
        }
        //------------------------------------------------------------------------------------------------------- 
    }
    //------------------------------------------------------------------------------------------------------- 
    /*
     * Simple redirect
     */
    public function RedirectAlias($url) {
        //------------------------------------------------------------------------------------------------------- 
        $srv = Mage::getUrl($url);
        return Mage::app()->getResponse()->setHeader('Location', $srv)->sendHeaders();  
        //------------------------------------------------------------------------------------------------------- 
    }
    //------------------------------------------------------------------------------------------------------- 
    /*
     * Own templates
     */
    public function UseTemplate($ar, $template) {
        //------------------------------------------------------------------------------------------------------- 
        return $this->_Orbitvu->UseTemplate($ar, $template);
        //------------------------------------------------------------------------------------------------------- 
    }
    //------------------------------------------------------------------------------------------------------- 
    /*
     * Save product
     */
    public function saveProductTabData(Varien_Event_Observer $observer) {
    	//------------------------------------------------------------------------------------------------------- 
        if (!self::$_singletonFlag) {
            self::$_singletonFlag = true;

            //-------------------------------------------------------------------------------------------------------
            $this->ExtendOrbitvu();
            //-------------------------------------------------------------------------------------------------------
            
            /*
             * Update Orbitvu thumbnails
             */
            //-------------------------------------------------------------------------------------------------------
            if (Mage::app()->getRequest()->getParam('sun') != 'update') {
                $product_id = Mage::registry('product')->getId();
                $this->UpdateThumbnails($product_id);
            }
            //-------------------------------------------------------------------------------------------------------
            
            /*
             * Force SUN synchro
             */
            //-------------------------------------------------------------------------------------------------------
            if (Mage::app()->getRequest()->getPost('synchronize_sun') == 'true') {
            	//-------------------------------------------------------------------------------------------------------
            	$this->SynchronizeAllProducts();
            	//-------------------------------------------------------------------------------------------------------
            }
            //------------------------------------------------------------------------------------------------------- 
        }
        //------------------------------------------------------------------------------------------------------- 
    }
}

?>