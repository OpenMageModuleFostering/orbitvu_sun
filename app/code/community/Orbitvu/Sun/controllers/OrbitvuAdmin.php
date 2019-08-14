<?php
/**
 * Orbitvu PHP  Orbitvu eCommerce administration
 * @Copyright: Orbitvu Sp. z o.o. is the owner of full rights to this code
 */

final class OrbitvuAdmin {
    
    /**
     * Database driver instance
     * @var instance 
     */
    private $driver;
    
    /**
     * Is the extension connected?
     * @var bool
     */
    private $work = true;

    /**
     * Configuration vars and values
     * @var stdClass
     */
    public $Config;

    /**
     * SUN API connection instance
     * @var instance
     */
    public $Connect;

    /**
     * Include all classes
     * @param boolean $debug Debug mode
     * @param boolean $test Unit tests mode
     * @param boolean $testinstance Unit tests instance.
     */
    public function __construct($debug = false, $test = false, $testinstance = false) {
        //-------------------------------------------------------------------------------------------------------
        require_once(__DIR__.'/OrbitvuDebugger.php');
        require_once(__DIR__.'/OrbitvuDatabaseInterface.php');
        require_once(__DIR__.'/OrbitvuConnect.php');
        //-------------------------------------------------------------------------------------------------------
        /*
         * Let's do the magic
         */
        //-------------------------------------------------------------------------------------------------------
        $this->driver = new OrbitvuDatabaseInterface('', $debug, $test, $testinstance);
        $this->Config = $this->driver->GetConfiguration();
        //-------------------------------------------------------------------------------------------------------
        $this->Connect = new Orbitvu($this->Config->access_token, $debug, $test, $testinstance);
        //-------------------------------------------------------------------------------------------------------
        $this->Connect->IntroduceYourself($this->driver->GetVersion()); 
        if (!$this->Connect->IsConnected()) {
            $this->work = false;
        }
        $this->Connect->ViewerPath = $this->Config->viewers_path;
        $this->Connect->DownloadPath = $this->Config->temp_path;
        //-------------------------------------------------------------------------------------------------------
        $this->driver->SetSUNConnection($this->Connect);
        //-------------------------------------------------------------------------------------------------------
    }
    
    /**
     * Own simple, template parser. 
     * Usign every store script template engine doesn't make practical sense. 
     * Especially most of them doesn't have a template engine.
     * @param array $ar template array to replace in $template
     * @param string $template template
     * @return string
     */
    public function UseTemplate($ar, $template) {
        //-------------------------------------------------------------------------------------------------------
        $keys = array_map(function($str) {
            return '{'.$str.'}';
        }, array_keys($ar));
        //-------------------------------------------------------------------------------------------------------
        return str_replace($keys, array_values($ar), $template);
        //-------------------------------------------------------------------------------------------------------
    }

    /**
     * Register an account
     * @param string $email Email to register
     * @return stdClass
     */
    public function CreateAccount($email) {
        //-------------------------------------------------------------------------------------------------------
        try {
            //-------------------------------------------------------------------------------------------------------
            $account = $this->Connect->CreateAccount($email);
            
            $this->driver->UpdateConfiguration('access_token', $account->key);
            //-------------------------------------------------------------------------------------------------------
            return $account;
            //-------------------------------------------------------------------------------------------------------
        }
        catch (Exception $e) {
            //-------------------------------------------------------------------------------------------------------
            $ret = new stdClass();
            $ret->error = $e->getMessage();
            $ret->status = 'BAD';
            //-------------------------------------------------------------------------------------------------------
            return $ret;
            //-------------------------------------------------------------------------------------------------------
        }
        //-------------------------------------------------------------------------------------------------------
    }
    
    /**
     * Set extension access token to default
     * @return boolean
     */
    public function SetDemoAccessToken() {
        //-------------------------------------------------------------------------------------------------------
        return $this->UpdateConfiguration('access_token', $this->Connect->GetDemoAccessToken());
        //-------------------------------------------------------------------------------------------------------
    }

    /**
     * Get presentation items types
     * @return array
     */
    public function GetPresentationTypes($result) {
        //-------------------------------------------------------------------------------------------------------
        $types = array();
        //-------------------------------------------------------------------------------------------------------
        if ($result->has_orbittour == 1) {
            $types[] = '0';
        }
        //-------------------------------------------------------------------------------------------------------
        for ($k = 0, $m = count($result->presentationcontent_set); $k < $m; $k++) {
            $cur_type = $result->presentationcontent_set[$k]->type;

            if (!in_array($cur_type, $types)) {
                $types[] = $cur_type;
            }
        }
        //-------------------------------------------------------------------------------------------------------
        rsort($types);
        //-------------------------------------------------------------------------------------------------------
        return $types;
        //-------------------------------------------------------------------------------------------------------
    }
    
    /**
     * Get product presentation items
     * @return array
     */
    public function GetProductPresentationItems($orbitvu_id) {
        //-------------------------------------------------------------------------------------------------------
        return $this->driver->GetProductPresentationItems($orbitvu_id);
        //-------------------------------------------------------------------------------------------------------
    }
    
    /**
     * Synchronize one product presentation
     * @return array
     */
    public function SynchronizeProductPresentation($product_id, $presentation_id, $presentation_name) {
        //-------------------------------------------------------------------------------------------------------
        $this->SetProductPresentation($product_id, $presentation_id, $presentation_name); 
        $p_items = $this->GetProductPresentationItems($presentation_id, $this->GetConfiguration('sync_order'));
        $this->SetProductPresentationItems($product_id, $presentation_id, $presentation_name, $p_items);
        //-------------------------------------------------------------------------------------------------------
        return true;
        //-------------------------------------------------------------------------------------------------------
    }
       
    /**
     * Set SUN presentation SKU
     * @param type $presentation_id Presentation ID from database
     * @param type $new_sku Presentation new SKU
     * @param type $current_sku Presentation current SKU to update
     * @param boolean $update Force SKU update
     * @return boolean
     */
    public function SetSunPresentationSku($presentation_id, $new_sku, $current_sku, $update = false) {
        //-------------------------------------------------------------------------------------------------------
        if (!$update && (($this->GetConfiguration('auto_sync_sku') == 'true_ifempty' && empty($current_sku)) || $this->GetConfiguration('auto_sync_sku') == 'true')) {
            $update = true;
        }
        //-------------------------------------------------------------------------------------------------------
        if ($update) {
            return $this->Connect->CallSUN('presentations/'.$presentation_id.'/sku', array('sku' => $new_sku), true, true);
        }
        //------------------------------------------------------------------------------------------------------- 
        return false;
        //------------------------------------------------------------------------------------------------------- 
    }
    
    /**
     * Are we connected to the Orbitvu SUN?
     * @return bool
     */
    public function IsConnected() {
        //-------------------------------------------------------------------------------------------------------
        return $this->work;
        //-------------------------------------------------------------------------------------------------------
    }
    
    /**
     * Check for available updates
     * @return stdClass|boolean
     */
    public function CheckForUpdates() {
        //-------------------------------------------------------------------------------------------------------
        return $this->Connect->CheckForUpdates();
        //-------------------------------------------------------------------------------------------------------
    }
    
    /**
     * License days counter
     * @return integer
     */
    public function GetSupportDays() {
        //-------------------------------------------------------------------------------------------------------
        return $this->Connect->GetSupportDays();
        //-------------------------------------------------------------------------------------------------------
    }
    
    /**
     * Check if license is demo
     * @return boolean
     */
    public function IsDemo() {
        //-------------------------------------------------------------------------------------------------------
        return $this->Connect->IsDemo();
        //-------------------------------------------------------------------------------------------------------
    }   
    
    //-------------------------------------------------------------------------------------------------------
    //-------------------------------------------------------------------------------------------------------
    //-------------------------------------------------------------------------------------------------------
    // Forward queries to included driver
    //-------------------------------------------------------------------------------------------------------
    //-------------------------------------------------------------------------------------------------------
    //-------------------------------------------------------------------------------------------------------
        
    /**
     * Update both extension and store configuration
     * @param string $var Configuration var to update
     * @param string $value New value
     * @return boolean
     */
    public function UpdateConfiguration($var, $value) {
        //-------------------------------------------------------------------------------------------------------
        $this->driver->SetConfiguration($var, $value);
        $this->driver->SetConfigurationParent($var, $value);
        //-------------------------------------------------------------------------------------------------------
    }
        
    /**
     * Use UpdateConfiguration if you want to update store configuration too
     * @param string $var Local configuration var to update
     * @param string $value Local new value
     * @return boolean
     */
    public function SetConfiguration($var, $value) {
        //-------------------------------------------------------------------------------------------------------
        return $this->driver->SetConfiguration($var, $value);
        //------------------------------------------------------------------------------------------------------- 
    }
    
    /**
     * Switch presentation item priority
     * @param integer $product_id Store product ID from database
     * @param integer $item_id Current item ID from database
     * @param integer $item_id2 Item will be putted in order after this item
     * @return boolean
     */
    public function SwitchPresentationItemPriority($product_id, $item_id, $item_id2) {
        //-------------------------------------------------------------------------------------------------------
        return $this->driver->SwitchPresentationItemPriority($product_id, $item_id, $item_id2);
        //-------------------------------------------------------------------------------------------------------
    }
    
    /**
     * Get product presentation data
     * Get product presentation items
     * @param integer $product_id Store product ID from database
     * @param boolean $visible_only Return only items marked as visible
     * @return array
     */
    public function GetProductPresentation($product_id, $visible_only = false) {
        //-------------------------------------------------------------------------------------------------------
        return $this->driver->GetProductPresentation($product_id, $visible_only);
        //-------------------------------------------------------------------------------------------------------
    }

    /**
     * Set new product presentation
     * @param type $product_id Store product ID from database
     * @param type $orbitvu_id Orbitvu SUN presentation ID
     * @param type $orbitvu_name Orbitvu SUN presentation name
     * @return boolean
     */
    public function SetProductPresentation($product_id, $orbitvu_id, $orbitvu_name = '') {
        //-------------------------------------------------------------------------------------------------------
        return $this->driver->SetProductPresentation($product_id, $orbitvu_id, $orbitvu_name);
        //-------------------------------------------------------------------------------------------------------
    }

    /**
     * Set product presentation items
     * @param type $product_id Store product ID from database
     * @param type $orbitvu_id Orbitvu SUN presentation ID
     * @param type $orbitvu_name Orbitvu SUN presentation name
     * @param type $presentation_items Orbitvu SUN presentation items
     * @return type
     */
    public function SetProductPresentationItems($product_id, $orbitvu_id, $orbitvu_name, $presentation_items) {
        //-------------------------------------------------------------------------------------------------------
        return $this->driver->SetProductPresentationItems($product_id, $orbitvu_id, $orbitvu_name, $presentation_items);
        //-------------------------------------------------------------------------------------------------------
    }

    /**
     * Synchronize presentations
     * @param array $products_array Products array
     * @return boolean
     */
    public function SynchronizePresentations($products_array) {
        //-------------------------------------------------------------------------------------------------------
        return $this->driver->SynchronizePresentations($products_array);
        //-------------------------------------------------------------------------------------------------------
    }
    
    /**
     * Refresh presentations items
     * Delete non-existing on SUN
     * Add new from SUN
     * @return boolean
     */
    public function SynchronizePresentationsItems() {
        //-------------------------------------------------------------------------------------------------------
        return $this->driver->SynchronizePresentationsItems();
        //-------------------------------------------------------------------------------------------------------
    }
    
    /**
     * Is presentation unlinked?
     * @param integer $product_id Store product ID from database
     * @param integer $orbitvu_id Orbitvu SUN presentation ID
     * @return boolean
     */
    public function IsPresentationUnlinked($product_id, $orbitvu_id) {
        //-------------------------------------------------------------------------------------------------------
        return $this->driver->IsPresentationUnlinked($product_id, $orbitvu_id);
        //-------------------------------------------------------------------------------------------------------
    }
    
    /**
     * Is the product unlinked?
     * @param integer $product_id Store product ID from database
     * @return boolean
     */
    public function IsProductUnlinked($product_id) {
        //-------------------------------------------------------------------------------------------------------
        return $this->driver->IsProductUnlinked($product_id);
        //-------------------------------------------------------------------------------------------------------
    }

    /**
     * Unlink (free) presentation from product
     * @param integer $product_id Store product ID from database
     * @return boolean
     */    
    public function FreeProduct($product_id) {
        //-------------------------------------------------------------------------------------------------------
        return $this->driver->FreeProduct($product_id);
        //-------------------------------------------------------------------------------------------------------
    }

    /**
     * Get configuration value from var
     * @param string $var Var from configuration
     * @return string
     */
    public function GetConfiguration($var) {
        //-------------------------------------------------------------------------------------------------------
        return $this->Config->$var;
        //-------------------------------------------------------------------------------------------------------
    }
    
    /**
     * Update item priority
     * @param integer $item_id Store product item ID from database
     * @param integer $new_priority New priority (order) value
     * @return boolean
     */
    public function UpdatePresentationItemPriority($item_id, $new_priority) {
        //-------------------------------------------------------------------------------------------------------
        return $this->driver->UpdatePresentationItemPriority($item_id, $new_priority);
        //-------------------------------------------------------------------------------------------------------
    }
    
    /**
     * Delete presentation item
     * @param integer $item_id Store product item ID from database
     * @return boolean
     */
    public function DeletePresentationItem($item_id) {
        //-------------------------------------------------------------------------------------------------------
        return $this->driver->DeletePresentationItem($item_id);
        //-------------------------------------------------------------------------------------------------------
    }
    
    /**
     * Have the product a presentation exists?
     * @param integer $product_id Store product ID from database
     * @return boolean
     */
    public function ExistsProductPresentation($product_id) {
        //-------------------------------------------------------------------------------------------------------
        return $this->driver->ExistsProductPresentation($product_id);
        //-------------------------------------------------------------------------------------------------------
    }
    
    /**
     * Search the presentation with name and SKU
     * @param array $product Product array with name and SKU
     * @return array|boolean
     */
    public function MatchPresentation($product) {
        //-------------------------------------------------------------------------------------------------------
        return $this->driver->MatchPresentation($product);
        //-------------------------------------------------------------------------------------------------------
    }
    
    /**
     * Update item visibility status
     * @param integer $item_id Store product item ID from database
     * @param string $status New item visibility status [active / inactive]
     * @return boolean
     */
    public function UpdatePresentationItemStatus($item_id, $status = 'inactive') {
        //-------------------------------------------------------------------------------------------------------
        return $this->driver->UpdatePresentationItemStatus($item_id, $status);
        //-------------------------------------------------------------------------------------------------------
    }
    
    /**
     * Install database tables
     * @return boolean
     */
    public function Install() {
        //-------------------------------------------------------------------------------------------------------
        return $this->driver->Install();
        //-------------------------------------------------------------------------------------------------------
    }

    /**
     * Get presentations from Orbitvu SUN
     * @param array $params Params for Orbitvu SUN filtering
     * @return stdClass
     */
    public function GetPresentationsList($params = false) {
        //-------------------------------------------------------------------------------------------------------
        return $this->driver->GetPresentationsList($params);
        //-------------------------------------------------------------------------------------------------------
    }
        
    /**
     * Get our store thumbnails
     * @param integer $product_id Store product ID from database
     * @return array
     */
    public function GetProductThumbnails($product_id) {
        //-------------------------------------------------------------------------------------------------------
        return $this->driver->GetProductThumbnails($product_id);
        //-------------------------------------------------------------------------------------------------------
    }
    
    /**
     * Set store thumbnail
     * @param integer $product_id Store product ID from database
     * @param string $thumbnail Thumbnail path
     * @return array
     */
    public function SetProductThumbnail($product_id, $thumbnail) {
        //-------------------------------------------------------------------------------------------------------
        return $this->driver->SetProductThumbnail($product_id, $thumbnail);
        //-------------------------------------------------------------------------------------------------------
    }
    
    /**
     * Delete store thumbnail
     * @param integer $product_id Store product ID from database
     * @param string $thumbnail Thumbnail path
     * @return array
     */
    public function DeleteProductThumbnail($product_id, $thumbnail = '') {
        //-------------------------------------------------------------------------------------------------------
        return $this->driver->DeleteProductThumbnail($product_id, $thumbnail);
        //-------------------------------------------------------------------------------------------------------
    }

    /**
     * Synchronization
     * @return boolean
     */
    public function SynchronizeAllProducts() {
        //-------------------------------------------------------------------------------------------------------
        return $this->driver->SynchronizeAllProducts($params);
        //-------------------------------------------------------------------------------------------------------
    }
     
}

?>