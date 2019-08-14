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
     * XML Handler
     * @var SimpleXMLElement
     */
    private $remote_xml;

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
        $this->Connect->PresentationsPath = property_exists($this->Config, 'presentations_path') ? $this->Config->presentations_path : '';
        //-------------------------------------------------------------------------------------------------------
        $this->driver->SetSUNConnection($this->Connect);
        //-------------------------------------------------------------------------------------------------------
    }
    
    /**
     * Get presentations path
     * @return string
     */
    public function GetPresentationsPath() {
        //-------------------------------------------------------------------------------------------------------
        return $this->Connect->PresentationsPath;
        //-------------------------------------------------------------------------------------------------------
    }
    
    /**
     * Install presentation from file
     * @param string $file Presentation archive file (*.ovus, *.zip)
     * @param boolean $print_output Print console-like output messages
     * @return string
     */
    public function InstallPresentation($file, $print_output = true) {
        //-------------------------------------------------------------------------------------------------------
        return $this->Connect->InstallPresentation($file, $print_output);
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
            
            $this->UpdateConfiguration('access_token', $account->key);
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
     * Renders XML content
     * @param SimpleXMLElement $xml
     */
    private function render_xml() {
        //----------------------------------------------------------------------
        header('Content-Type: text/xml; charset=utf-8');
        print $this->remote_xml->asXML();
        exit();
        //----------------------------------------------------------------------
    }

    /**
     * Authorization
     * @param $_POST['username'] @hidden
     * @param $_POST['password'] @hidden
     * @param object $ov_xml XML element
     * @return string
     */
    private function do_remote_authorize_action($ov_xml) {
        //----------------------------------------------------------------------
        if (count($_POST) > 0) {
            //-------------------------------------------------------------------------------------------------------
            if ($key = $this->Connect->LogInSun(filter_var($_POST['username']), filter_var($_POST['password']))) {
                if ($this->Connect->CheckAccessToken($key)) {

                    /**
                     * Generate token
                     * Set cookie
                     * Provide upload URL (expires)
                     */
                    //-------------------------------------------------------------------------------------------------------
                    $token = md5(md5($_POST['username'].' '.$_POST['password'].' '.date('Y-m-d H:i:s').' '.mt_rand(1000, 10000)));

                    session_start();
                    
                    $_SESSION['orbitvu_token'] = $token;
                    setcookie('orbitvuid', $token, time()+3600*3);
                    $upload_url = str_replace('&', '&amp;', $this->GetRemoteUploadUrl().'&ov_token='.$token);
                    //-------------------------------------------------------------------------------------------------------
                    $ov_xml->addChild('code', 0);
                    $ov_xml->addChild('message', 'Authorization succeeded!');
                    $ov_xml->addChild('data', $upload_url);
                    //-------------------------------------------------------------------------------------------------------
                }
                else {
                    //-------------------------------------------------------------------------------------------------------
                    $ov_xml->addChild('code', 4);
                    $ov_xml->addChild('message', 'Authorization failed. Permissions denied!');
                    header('HTTP/1.0 401 Unauthorized');
                    //-------------------------------------------------------------------------------------------------------
                }
            }
            else {
                //-------------------------------------------------------------------------------------------------------
                $ov_xml->addChild('code', 1);
                $ov_xml->addChild('message', 'Authorization failed. Wrong username/password!');
                header('HTTP/1.0 401 Unauthorized');
                //-------------------------------------------------------------------------------------------------------
            }
        }
        else {
            $ov_xml->addChild('code', 3);
            $ov_xml->addChild('message', 'Wrong HTTP method!');
            header('HTTP/1.0 501 Not implemented');
        }
        
        return $this->render_xml();
        //----------------------------------------------------------------------
    }
    
    /**
     * Upload
     * @param $_FILES['path'] @hidden
     * @param $_COOKIE['orbitvuid'] @hidden
     * @param $_SESSION['orbitvu_token'] @hidden
     * @param $_GET['ov_token'] @hidden
     * @param object $ov_xml XML handler
     * @return string
     */
    private function do_remote_upload_action($ov_xml) {
        //----------------------------------------------------------------------
        $no_errors = true;
        
        if (count($_POST) > 0 || count($_FILES) > 0) {
            if ($_SESSION['orbitvu_token'] != $_GET['ov_token']) {
                //-------------------------------------------------------------------------------------------------------
                $ov_xml->addChild('code', 2);
                $ov_xml->addChild('message', 'Authorization failed!');
                header('HTTP/1.0 401 Unauthorized');
                $no_errors = false;
                //-------------------------------------------------------------------------------------------------------
            }
            else if (!isset($_FILES['path']['tmp_name']) || (!stristr($_FILES['path']['name'], '.zip') && !stristr($_FILES['path']['name'], '.ovus'))) {
                //-------------------------------------------------------------------------------------------------------
                $ov_xml->addChild('code', 1);
                $ov_xml->addChild('message', 'Ovus file not provided or not valid!');
                header('HTTP/1.0 400 Bad request');
                $no_errors = false;
                //-------------------------------------------------------------------------------------------------------
            }
            else if (!$this->IsConnected()) {
                //-------------------------------------------------------------------------------------------------------
                $ov_xml->addChild('code', 6);
                $ov_xml->addChild('message', 'DEMO version - upload not permitted.');
                header('HTTP/1.0 401 Unauthorized');
                $no_errors = false;
                //-------------------------------------------------------------------------------------------------------
            }
            else {
                //----------------------------------------------------------------------
                $archive_name = $this->GetPresentationsPath().$_FILES['path']['name'];
                $file_contents = file_get_contents($file_contents);
                
                if (!empty($file_contents)) {
                    //-------------------------------------------------------------------------------------------------------
                    $ov_xml->addChild('code', 1);
                    $ov_xml->addChild('message', 'Ovus file not valid!');
                    header('HTTP/1.0 400 Bad request');
                    $no_errors = false;
                    //-------------------------------------------------------------------------------------------------------
                }
                else {
                    try {
                        if (copy($_FILES['path']['tmp_name'], $archive_name)) {
                            //-------------------------------------------------------------------------------------------------------
                            $this->InstallPresentation($archive_name, false);
                            //-------------------------------------------------------------------------------------------------------
                            $ov_xml->addChild('code', 0);
                            $ov_xml->addChild('message', 'Upload succeed!');
                            //-------------------------------------------------------------------------------------------------------
                            setcookie('orbitvuid', '', time()-3600);
                            unset($_SESSION['orbitvu_token']);
                            $no_errors = false;
                            //-------------------------------------------------------------------------------------------------------
                        }
                        else if ($_FILES['path']['error'] == '1') {
                            //-------------------------------------------------------------------------------------------------------
                            $ov_xml->addChild('code', 5);
                            $ov_xml->addChild('message', 'No space available or file too big! Change `upload_max_filesize` value in your server php.ini file.');
                            header('HTTP/1.0 400 Bad request');
                            $no_errors = false;
                            //-------------------------------------------------------------------------------------------------------
                        }
                        else {
                            //-------------------------------------------------------------------------------------------------------
                            $ov_xml->addChild('code', 4);
                            $ov_xml->addChild('message', 'Upload error! Error code: '.intval($_FILES['path']['error']));
                            header('HTTP/1.0 400 Bad request');
                            $no_errors = false;
                            //-------------------------------------------------------------------------------------------------------
                        }
                    }
                    catch (Exception $e) {
                        //-------------------------------------------------------------------------------------------------------
                        $ov_xml->addChild('code', 4);
                        $ov_xml->addChild('message', $e->getMessage());
                        header('HTTP/1.0 400 Bad request');
                        $no_errors = false;
                        //-------------------------------------------------------------------------------------------------------
                    }
                }
                //----------------------------------------------------------------------
            }
        }
        else {
            //-------------------------------------------------------------------------------------------------------
            $ov_xml->addChild('code', 3);
            $ov_xml->addChild('message', 'Wrong HTTP method!');
            header('HTTP/1.0 501 Not implemented');
            $no_errors = false;
            //-------------------------------------------------------------------------------------------------------
        }
        //----------------------------------------------------------------------
        if ($no_errors) {
            //-------------------------------------------------------------------------------------------------------
            $ov_xml->addChild('code', 4);
            $ov_xml->addChild('message', 'Other no permissions related error!');
            header('HTTP/1.0 401 Unauthorized');
            //-------------------------------------------------------------------------------------------------------
        }
        //----------------------------------------------------------------------
        return $this->render_xml();
        //----------------------------------------------------------------------
    }    
    
    /**
     * Abstract layer for all remote actions for ALL platforms
     * @param $_GET['ov_key'] 
     * @param $_GET['ov_action']
     * @return type
     */
    public function StartRemoteListener() {
        //-------------------------------------------------------------------------------------------------------
        $this->remote_xml = new SimpleXMLElement('<ovs_response/>');
        //$ovs_response = $this->remote_xml->addChild('ovs_response');
        //-------------------------------------------------------------------------------------------------------
        
        /**
         * Security first
         */
        //-------------------------------------------------------------------------------------------------------
        if (!$_GET['ov_key'] || $_GET['ov_key'] != $this->GetLocalSessionKey()) {
            
            $this->remote_xml->addChild('code', 4);
            //$ovs_response->addChild('code', 4);
            $this->remote_xml->addChild('message', 'Authorization URL is not complete, not valid or expired.');
            //$ovs_response->addChild('message', 'Authorization URL is not complete, not valid or expired.');
            header('HTTP/1.0 401 Unauthorized');
            
            return $this->render_xml();
        }
        //-------------------------------------------------------------------------------------------------------
        
        /**
         * Session OK?
         * All right...
         */
        //-------------------------------------------------------------------------------------------------------
        if ($_GET['ov_action'] == 'upload') {
            //return $this->do_remote_upload_action($ovs_response);
            return $this->do_remote_upload_action($this->remote_xml);
            
        }
                
        //return $this->do_remote_authorize_action($ovs_response);
        return $this->do_remote_authorize_action($this->remote_xml);
        //-------------------------------------------------------------------------------------------------------
    }
    
    /**
     * Resize Image. Process image and generate other size in $target_path
     * @param string $file Original image file
     * @param integer|null $width New image width (null = auto)
     * @param integer|null $height New image height (null = auto)
     * @param string $target_path Output images path (cached processed versions)
     * @param string $scale_type[=crop] Scaling type crop|stretch|auto 
     * @return string Path to new file
     */
    public function ResizeImage($file, $width = null, $height = null, $target_path = null, $scale_type = 'auto', $background = 'white') {
        //-------------------------------------------------------------------------------------------------------
        /**
         * Original file directory parsing
         */
        //-------------------------------------------------------------------------------------------------------
        $file_name = explode('/', $file);
        $file_name = $file_name[count($file_name)-1];
        
        if ($target_path === null) {
            $target_path = str_replace($file_name, '', $file);
        }
        
        $fname = $target_path.str_ireplace(array('.jpg', '.png', '.gif', '.jpeg'), '', $file_name);
        
        //-------------------------------------------------------------------------------------------------------

        /**
         * New dimensions
         */
        //-------------------------------------------------------------------------------------------------------
        if ($width === null) {
            $width = '*';
        }
        if ($height === null) {
            $height = '*';
        }
        //-------------------------------------------------------------------------------------------------------
	
        /**
         * Get image details
         * Create new image with proper type
         */
        //-------------------------------------------------------------------------------------------------------
	list($w, $h, $type) = getimagesize($file); 
        
	switch ($type){ 
            case IMAGETYPE_GIF: 
                $src_im = imagecreatefromgif($file);  
            break; 
            case IMAGETYPE_PNG: 
                $src_im = imagecreatefrompng($file);  
            break; 
            default:			
                $src_im = imagecreatefromjpeg($file); 
            break;
	}
	
        /**
         * Image dimensions
         */
        //-------------------------------------------------------------------------------------------------------
        $tw = $width;
        $th = $height;
        if ($tw == '*') {
            $tw = $th / $h * $w;
        }
        if ($th == '*') {
            $th = $tw / $w * $h;
        }
        
        /**
         * Check if file already exists.
         * If so, return file path
         */
        //-------------------------------------------------------------------------------------------------------
        $final_path = $fname.'_'.round($tw).'_'.round($th).'.jpg';
        
        if (file_exists($final_path)) {
            return $final_path;
        }
        
        /**
         * Image resizing
         */
        //-------------------------------------------------------------------------------------------------------
        $dst_im = imagecreatetruecolor($tw, $th); 

        if ($background == 'white') {
            $background = 16777215;
        }
        else {
            $background = 0;
        }
        imagefill($dst_im, 0, 0, $background); 
        
        switch ($scale_type) {
            case 'crop':
                $nw = ceil($w / $h > $tw / $th ? $th * ($w / $h) : $tw);
                $nh = ceil($w / $h > $tw / $th ? $th : $tw / ($w / $h));
                $temp_gdim = imagecreatetruecolor($nw, $nh);
                imagecopyresampled($temp_gdim, $src_im, 0, 0, 0, 0, $nw, $nh, $w, $h);
                imagecopy($dst_im, $temp_gdim, 0, 0, ($nw - $tw) / 2, ($nh - $th) / 2, $tw, $th);
                imagedestroy($temp_gdim);
            break;
            case 'stretch':
                imagecopyresampled($dst_im, $src_im, 0, 0, 0, 0, $tw, $th, $w, $h);
            break;
            default:
                $nw = ceil($w > $tw || $h > $th ? ($th < $tw ? $th / ($h / $w) : $tw) : $w);
                $nh = ceil($w > $tw || $h > $th ? ($th > $tw ? $tw * ($h / $w) : $th) : $h);
                imagecopyresampled($dst_im, $src_im, ($tw - $nw) / 2, ($th - $nh) / 2, 0, 0, $nw, $nh, $w, $h); 
            break;
        }
        //-------------------------------------------------------------------------------------------------------
		
        /**
         * Use imagejpeg as one format and better quality
         */
        //-------------------------------------------------------------------------------------------------------
        imagejpeg($dst_im, $final_path, 100); 
        imagedestroy($dst_im);
        
        if (!file_exists($final_path)) {
            throw new Exception('Orbitvu: Image file cannot be saved. Make sure you have set write permissions to parent directory of file: '.$final_path.'');
        }
        //-------------------------------------------------------------------------------------------------------
	return $final_path;
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
        $p_items = $this->GetProductPresentationItems($presentation_id);
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
     * Clear Cache
     * @return boolean
     */
    public function ClearCache() {
        //-------------------------------------------------------------------------------------------------------
        return $this->driver->SetConfiguration('last_flushed_cache', date('Y-m-d'));
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
     * @see OrbitvuDatabaseDriver.php\GetLocalSessionKey()
     * @return string
     */
    public function GetLocalSessionKey() {
        //-------------------------------------------------------------------------------------------------------
        return $this->driver->GetLocalSessionKey();        
        //-------------------------------------------------------------------------------------------------------
    }
    
    /**
     * @see OrbitvuDatabaseDriver.php\GetRemoteAuthorizationUrl()
     * @return string
     */
    public function GetRemoteAuthorizationUrl() {
        //-------------------------------------------------------------------------------------------------------
        return $this->driver->GetRemoteAuthorizationUrl();
        //-------------------------------------------------------------------------------------------------------
    }
    
    /**
     * @see OrbitvuDatabaseDriver.php\GetRemoteUploadUrl()
     * @return string
     */
    public function GetRemoteUploadUrl() {
        //-------------------------------------------------------------------------------------------------------
        return $this->driver->GetRemoteUploadUrl();
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
        return $this->driver->SynchronizeAllProducts();
        //-------------------------------------------------------------------------------------------------------
    }

    /**
     * Get associated products of a given product     *
     * @param $product_id
     * @return mixed
     */
    public function GetAssociatedProducts($product_id) {
        return $this->driver->GetAssociatedProducts($product_id);
    }
}

?>