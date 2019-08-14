<?php
/**
 * Orbitvu PHP eCommerce Orbitvu DB drivers
 * @Copyright: Orbitvu Sp. z o.o. is the owner of full rights to this code
 */

final class OrbitvuDatabaseInterface {
    
    /**
     * Database driver instance
     * @var instance
     */
    private $database;
    
    /**
     * Database tables prefix
     * @var string
     */
    private $db_prefix = '';
    
    /**
     * Orbitvu tables previx
     * @var string
     */
    private $db_orbitvu = 'orbitvu_';
    
    /**
     * Debug mode
     * @var boolean
     */
    private $debug = false;
    
    /**
     * Unit tests mode
     * @var boolean
     */
    private $test = false;
    
    /**
     * Test class instance
     * @var instance
     */
    private $testclass = false;
    
    /**
     * Configuration vars and values
     * @var stdClass|boolean
     */
    public $Config = false;

    /**
     * SUN API connection instance
     * @var instance
     */
    public $Connect;

    /**
     * Manage the database
     * @param string $prefix Database prefix
     * @param boolean $debug Debug mode
     * @param boolean $test Unit tests mode
     * @param boolean $testinstance Unit tests instance.
     */
    public function __construct($prefix = '', $debug = false, $test = false, $testinstance = false) {
        //-------------------------------------------------------------------------------------------------------
        require_once(__DIR__.'/OrbitvuDatabaseDriver.php');
        //-------------------------------------------------------------------------------------------------------
        $this->debug = $debug;
        $this->test = $test;
        //-------------------------------------------------------------------------------------------------------
        $this->database = new OrbitvuDatabaseDriver();
        //---------------------------------------------------------------------------------------------------
        if (!empty($prefix)) $this->db_prefix = $prefix;
        else $this->db_prefix = $this->database->GetPrefix();
        
        if ($this->is_sh()) {
            $this->db_orbitvu .= 'sh_'; 
        }
        //---------------------------------------------------------------------------------------------------
        $this->Config = $this->GetConfiguration();
        /*
         * Test instance
         */
        if ($this->test) {
            $this->testclass = $testinstance;
            
            /*
             * Run test bot!
             */
            $this->run_tests();
        }
        //-------------------------------------------------------------------------------------------------------
    }
    
    /**
     * Set Orbitvu SUN connection instance
     * @param instance $connect
     */
    public function SetSUNConnection($connect) {
        //----------------------------------------------------------
        $this->Connect = $connect;      
        //----------------------------------------------------------
    }
    
    /**
     * Synchronize all products
     * @return boolean
     */
    public function SynchronizeAllProducts() {
        //----------------------------------------------------------
        return $this->SynchronizeAllPresentations($this->database->SynchronizeAllProducts());
        //----------------------------------------------------------
    }
    
    /**
     * @see OrbitvuDatabaseDriver.php\GetLocalSessionKey()
     * @return string
     */
    public function GetLocalSessionKey() {
        //----------------------------------------------------------
        return $this->database->GetLocalSessionKey();
        //----------------------------------------------------------
    }
    
    /**
     * @see OrbitvuDatabaseDriver.php\GetRemoteAuthorizationUrl()
     * @return string
     */
    public function GetRemoteAuthorizationUrl() {
        //----------------------------------------------------------
        return $this->database->GetRemoteAuthorizationUrl();
        //----------------------------------------------------------
    }
    
    /**
     * @see OrbitvuDatabaseDriver.php\GetRemoteUploadUrl()
     * @return string
     */
    public function GetRemoteUploadUrl() {
        //----------------------------------------------------------
        return $this->database->GetRemoteUploadUrl();
        //----------------------------------------------------------
    }
    
    /**
     * Get all configuration vars and values. 
     * Update local values to store values
     * @return stdClass
     */
    public function GetConfiguration() {
        //----------------------------------------------------------
        if ($this->Config != false) return $this->Config;
        //----------------------------------------------------------
        if ($this->test) {
            $this->testclass->AppendTest('GetConfiguration', array('type' => 'function_check'), array('file' => __FILE__, 'line' => __LINE__, 'function' => __FUNCTION__));
        }
        //----------------------------------------------------------
        $db_query = '
            SELECT *
            FROM `'.$this->db_prefix.$this->db_orbitvu.'configuration`	
        ';

        //---------------------------------------------------------------------
        /**/	$this->return_sql_debug(__FUNCTION__, $db_query);
        //---------------------------------------------------------------------

        try {
            $query = $this->database->FetchAll($db_query);
        }
        catch (Exception $e) {
            $this->Install();
            
            //----------------------------------------------------------
            /**/	$this->return_debug(array(
            /**/		'function' 	=> 'Install',
            /**/		'response'	=> 'Database installed!'
            /**/	));
            //----------------------------------------------------------
            
            $query = $this->database->FetchAll($db_query);
        }

        /*
         * Synchronize config with current store configuration
         */
        $store_config = $this->database->GetConfiguration();

        //-------------------------------------------------------------------------------------------------------
        $conf = new stdClass();
        $i = 0;
        foreach ($query as $q) {
            $conf->$q['var'] = $q['value'];

            if (isset($store_config[$q['var']]) && $store_config[$q['var']] != $conf->$q['var']) {
                $this->SetConfiguration($q['var'], $store_config[$q['var']]);
                $conf->$q['var'] = $store_config[$q['var']];
            }
            $i++;
        }

        //----------------------------------------------------------
        /**/	$this->return_debug(array(
        /**/		'function' 		=> __FUNCTION__,
        /**/		'configuration'	=> $conf
        /**/	));
        //----------------------------------------------------------

        //-------------------------------------------------------------------------------------------------------
        return $conf;
        //-------------------------------------------------------------------------------------------------------
    }

    /**
     * Set local configuration value
     * or add new var and value
     * @param string $var
     * @param string $value
     * @return boolean
     */
    public function SetConfiguration($var, $value) {
        //-------------------------------------------------------------------------------------------------------
        $db_query = '
            INSERT INTO 
                `'.$this->db_prefix.$this->db_orbitvu.'configuration`
                (`var`, `value`, `type`)

            VALUES (
                \''.$var.'\',
                \''.$this->database->Escape($value).'\',
                \'main\'
            )

            ON DUPLICATE KEY 
            
                UPDATE
                    `value` = \''.($value).'\'
        ';

        //---------------------------------------------------------------------
        /**/	$this->return_sql_debug(__FUNCTION__, $db_query);
        //---------------------------------------------------------------------

        $query = $this->database->Query($db_query);
        
        //-------------------------------------------------------------------------------------------------------
        $this->Log(0, '_configuration', 'update', $var.'='.$value, 'auto');
        //-------------------------------------------------------------------------------------------------------
        return true;
        //-------------------------------------------------------------------------------------------------------
    }
    
    /**
     * Set store configuration
     * @param string $var
     * @param string $value
     * @return boolean
     */
    public function SetConfigurationParent($var, $value) {
        //-------------------------------------------------------------------------------------------------------
        return $this->database->SetConfiguration($var, $value);
        //-------------------------------------------------------------------------------------------------------
    }
    
    /**
     * Unlink (free) presentation from product
     * @param integer $product_id Store product ID from database
     * @return boolean
     */   
    public function FreeProduct($product_id) {	
        //-------------------------------------------------------------------------------------------------------
        $db_query = '
            SELECT *
            FROM `'.$this->db_prefix.$this->db_orbitvu.'products_presentations`

            WHERE
                `product_id` = '.intval($product_id).' 

            LIMIT 1
        ';

        //---------------------------------------------------------------------
        /**/	$this->return_sql_debug(__FUNCTION__, $db_query);
        //---------------------------------------------------------------------

        $query = $this->database->FetchAll($db_query);
        //-------------------------------------------------------------------------------------------------------
        $product = $query[0];  
        //-------------------------------------------------------------------------------------------------------
        
        /*
         * Free items
         */
        //-------------------------------------------------------------------------------------------------------
        $db_query = '
            DELETE
            FROM `'.$this->db_prefix.$this->db_orbitvu.'products_presentations_items`

            WHERE
                `_presentations_id` = '.intval($product['id']).' 
        ';

        //---------------------------------------------------------------------
        /**/	$this->return_sql_debug(__FUNCTION__, $db_query);
        //---------------------------------------------------------------------

        $query = $this->database->Query($db_query);
        
        /*
         * Add history
         */
        //-------------------------------------------------------------------------------------------------------
        $db_query = '
            INSERT INTO
                `'.$this->db_prefix.$this->db_orbitvu.'products_presentations_history`
                (`product_id`, `orbitvu_id`, `unlink_date`)
            
            VALUES (
                '.intval($product['product_id']).',
                '.intval($product['orbitvu_id']).',
                NOW()
            )  
        ';

        //---------------------------------------------------------------------
        /**/	$this->return_sql_debug(__FUNCTION__, $db_query);
        //---------------------------------------------------------------------

        $query = $this->database->Query($db_query);
        
        /*
         * Free product
         */
        //-------------------------------------------------------------------------------------------------------
        $db_query = '
            DELETE FROM
                `'.$this->db_prefix.$this->db_orbitvu.'products_presentations`
            
            WHERE
                `id` = '.intval($product['id']).'
                   
            LIMIT 1
        ';

        //---------------------------------------------------------------------
        /**/	$this->return_sql_debug(__FUNCTION__, $db_query);
        //---------------------------------------------------------------------

        $query = $this->database->Query($db_query);
        
        //-------------------------------------------------------------------------------------------------------
        $this->Log($product['id'], '_presentations', 'delete');
        //-------------------------------------------------------------------------------------------------------
        return true;
        //-------------------------------------------------------------------------------------------------------
    }
    
    /**
     * Get our store thumbnails
     * @param integer $product_id Store product ID from database
     * @return array
     */
    public function GetProductThumbnails($product_id) {
        //-------------------------------------------------------------------------------------------------------
        $db_query = '
            SELECT *
            FROM `'.$this->db_prefix.$this->db_orbitvu.'products_thumbnails`

            WHERE
                `product_id` = '.intval($product_id).'
        ';

        //---------------------------------------------------------------------
        /**/	$this->return_sql_debug(__FUNCTION__, $db_query);
        //---------------------------------------------------------------------
        
        $query = $this->database->FetchAll($db_query);
        
        //-------------------------------------------------------------------------------------------------------
        return $query;
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
        $db_query = '
            INSERT INTO 
                `'.$this->db_prefix.$this->db_orbitvu.'products_thumbnails`
                (`product_id`, `thumbnail`)
                
            VALUES (
                '.intval($product_id).',
                \''.$thumbnail.'\'
            )
        ';

        //---------------------------------------------------------------------
        /**/	$this->return_sql_debug(__FUNCTION__, $db_query);
        //---------------------------------------------------------------------
        
        try {
            $query = $this->database->Query($db_query);
        }
        catch(Exception $e) {}
        
        //-------------------------------------------------------------------------------------------------------
        return $query;
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
        $db_query = '
            DELETE FROM
                `'.$this->db_prefix.$this->db_orbitvu.'products_thumbnails`
                
            WHERE
                `product_id` = '.intval($product_id).'
                '.(!empty($thumbnail) ? ' AND `thumbnail` = \''.$thumbnail.'\' ' : '').'
            
            LIMIT 1
        ';

        //---------------------------------------------------------------------
        /**/	$this->return_sql_debug(__FUNCTION__, $db_query);
        //---------------------------------------------------------------------
        
        $query = $this->database->Query($db_query);
        
        //-------------------------------------------------------------------------------------------------------
        return $query;
        //-------------------------------------------------------------------------------------------------------
    }
    
    /**
     * Is the product unlinked?
     * @param integer $product_id Store product ID from database
     * @return boolean
     */
    public function IsProductUnlinked($product_id) {
        //-------------------------------------------------------------------------------------------------------
        $db_query = '
            SELECT *
            FROM `'.$this->db_prefix.$this->db_orbitvu.'products_presentations_history`

            WHERE
                `product_id` = '.intval($product_id).'
                    
            LIMIT 1
        ';

        //---------------------------------------------------------------------
        /**/	$this->return_sql_debug(__FUNCTION__, $db_query);
        //---------------------------------------------------------------------

        $query = $this->database->FetchAll($db_query);
        $query = $query[0];
        
        //-------------------------------------------------------------------------------------------------------
        if ($query['orbitvu_id'] > 0) {
            return true;
        }
        else {
            return false;
        }
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
        $db_query = '
            SELECT *
            FROM `'.$this->db_prefix.$this->db_orbitvu.'products_presentations_history`

            WHERE
                `product_id` = '.intval($product_id).' AND
                `orbitvu_id` = '.intval($orbitvu_id).'
                    
            LIMIT 1
        ';

        //---------------------------------------------------------------------
        /**/	$this->return_sql_debug(__FUNCTION__, $db_query);
        //---------------------------------------------------------------------

        $query = $this->database->FetchAll($db_query);
        $query = $query[0];
        
        //-------------------------------------------------------------------------------------------------------
        if ($query['orbitvu_id'] > 0) {
            return true;
        }
        else {
            return false;
        }
        //-------------------------------------------------------------------------------------------------------
    }
    
    /**
     * Product presentation exists?
     * @param integer $product_id Store product ID from database
     * @return boolean
     */
    public function ExistsProductPresentation($product_id) {		
        //-------------------------------------------------------------------------------------------------------
        $db_query = '
            SELECT *
            FROM `'.$this->db_prefix.$this->db_orbitvu.'products_presentations`

            WHERE
                `product_id` = '.intval($product_id).' 

            LIMIT 1
        ';
        
        //---------------------------------------------------------------------
        /**/	$this->return_sql_debug(__FUNCTION__, $db_query);
        //---------------------------------------------------------------------

        $query = $this->database->FetchAll($db_query);
        $query = $query[0];
        //-------------------------------------------------------------------------------------------------------
        if (isset($query['type'])) {
            return true;
        }
        //-------------------------------------------------------------------------------------------------------
        return false;
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
        $db_query = '
            SELECT *
            FROM `'.$this->db_prefix.$this->db_orbitvu.'products_presentations`

            WHERE
                `product_id` = '.intval($product_id).' 

            LIMIT 1
        ';

        //---------------------------------------------------------------------
        /**/	$this->return_sql_debug(__FUNCTION__, $db_query);
        //---------------------------------------------------------------------

        $query = $this->database->FetchAll($db_query);
        $query = $query[0];
        //-------------------------------------------------------------------------------------------------------
        $ret = array();
        //-------------------------------------------------------------------------------------------------------
        if (isset($query['type'])) {
            //-------------------------------------------------------------------------------------------------------
            $ret = $query;
            
            /**
             * SelfHosted data
             */
            if ($this->is_sh() && intval($ret['orbitvu_id']) > 0) {
                $db_query_sh = '
                    SELECT *
                    FROM `'.$this->db_prefix.$this->db_orbitvu.'products_presentations_cache`

                    WHERE
                        `id` = '.intval($ret['orbitvu_id']).' 

                    LIMIT 1
                ';

                //---------------------------------------------------------------------
                /**/	$this->return_sql_debug(__FUNCTION__, $db_query_sh);
                //---------------------------------------------------------------------

                $qsh = $this->database->FetchAll($db_query_sh);
                $qsh = $qsh[0];
                
                $ret['dir'] = $qsh['dir'];
                $ret['presentation_name'] = $qsh['name'];
                $ret['content'] = json_decode($qsh['content']);
            }
            
            $ret['items'] = array();
            
            $ret['types'] = array();
            
            //-------------------------------------------------------------------------------------------------------
            $db_sub_query = '
                SELECT *
                FROM `'.$this->db_prefix.$this->db_orbitvu.'products_presentations_items`

                WHERE
                    `_presentations_id` = '.$ret['id'].'
                    '.($visible_only ? ' AND `status` = \'active\' ' : '').'

                ORDER BY
                    `priority` DESC
            ';

            //---------------------------------------------------------------------
            /**/	$this->return_sql_debug(__FUNCTION__, $db_sub_query);
            //---------------------------------------------------------------------

            $items = $this->database->FetchAll($db_sub_query);
            //-------------------------------------------------------------------------------------------------------
            foreach ($items as $qi) {
                $ret['items'][] = $qi;
                
                if (!in_array($qi['type'], $ret['types'])) {
                    $ret['types'][] = $qi['type'];
                }
            }
            //rsort($ret['items']);
            rsort($ret['types']);
            //-------------------------------------------------------------------------------------------------------
            return $ret;
            //-------------------------------------------------------------------------------------------------------
        }
        //-------------------------------------------------------------------------------------------------------
        return false;
        //-------------------------------------------------------------------------------------------------------
    }

    /**
     * Set new product presentation
     * @param type $product_id Store product ID from database
     * @param type $orbitvu_id Orbitvu SUN presentation ID
     * @param type $orbitvu_name Orbitvu SUN presentation name
     * @return boolean
     */
    public function SetProductPresentation($product_id, $orbitvu_id, $presentation_name = '', $comment = 'manual') {
        //-------------------------------------------------------------------------------------------------------
        $db_query = '
            SELECT `id`
            FROM `'.$this->db_prefix.$this->db_orbitvu.'products_presentations`

            WHERE
                `product_id` = '.intval($product_id).'

            LIMIT 1
        ';

        //---------------------------------------------------------------------
        /**/	$this->return_sql_debug(__FUNCTION__, $db_query);
        //---------------------------------------------------------------------

        $check = $this->database->FetchAll($db_query);
        $check = $check[0];
        //-------------------------------------------------------------------------------------------------------

        $db_query = '
            INSERT INTO 
                `'.$this->db_prefix.$this->db_orbitvu.'products_presentations`
                (`product_id`, `orbitvu_id`, `name`, `type`)

            VALUES (
                '.intval($product_id).',
                '.intval($orbitvu_id).',
                \''.$this->database->Escape($presentation_name).'\',
                \''.($this->is_sh() ? 'local' : 'sun').'\'	
            )

            ON DUPLICATE KEY 

            UPDATE
                `name` = \''.$this->database->Escape($presentation_name).'\',
                `type` = \''.($this->is_sh() ? 'local' : 'sun').'\'
        ';

        //---------------------------------------------------------------------
        /**/	$this->return_sql_debug(__FUNCTION__, $db_query);
        //---------------------------------------------------------------------

        $query = $this->database->Query($db_query);
        //-------------------------------------------------------------------------------------------------------
        if (!isset($check['id'])) {
            //-------------------------------------------------------------------------------------------------------
            $this->Log($this->database->GetLastInsertId(), '_presentations', 'add', $comment);
            //-------------------------------------------------------------------------------------------------------
            return $this->database->GetLastInsertId();
            //-------------------------------------------------------------------------------------------------------
        }
        else {
            //-------------------------------------------------------------------------------------------------------
            $this->Log($check['id'], '_presentations', 'skip', $comment);
            //-------------------------------------------------------------------------------------------------------
            return $check['id'];
            //-------------------------------------------------------------------------------------------------------
        }
    }

    /**
     * Set product presentation items
     * @param type $product_id Store product ID from database
     * @param type $orbitvu_id Orbitvu SUN presentation ID
     * @param type $orbitvu_name Orbitvu SUN presentation name
     * @param type $presentation_items Orbitvu SUN presentation items
     * @return type
     */
    public function SetProductPresentationItems($product_id, $orbitvu_id, $orbitvu_name, $presentation_items, $comment = 'manual') {
        //-------------------------------------------------------------------------------------------------------
        /**
         * Updating presentation id
         */
        //-------------------------------------------------------------------------------------------------------
        $presentation_id = $this->SetProductPresentation($product_id, $orbitvu_id, $orbitvu_name, 'auto');
        //-------------------------------------------------------------------------------------------------------
        /**
         * Inserting new items
         */
        $priority = 10;
        //-------------------------------------------------------------------------------------------------------
        for ($i = 0, $n = count($presentation_items); $i < $n; $i++) {
            //-------------------------------------------------------------------------------------------------------
            $item = $presentation_items[$i];
            //-------------------------------------------------------------------------------------------------------
            $db_query = '
                SELECT `id`
                FROM `'.$this->db_prefix.$this->db_orbitvu.'products_presentations_items`

                WHERE
                    `orbitvu_id` = \''.$this->database->Escape($item['orbitvu_id']).'\' AND
                    `_presentations_id` = '.intval($presentation_id).'

                LIMIT 1
            ';

            //---------------------------------------------------------------------
            /**/	$this->return_sql_debug(__FUNCTION__, $db_query);
            //---------------------------------------------------------------------

            $check = $this->database->FetchAll($db_query);
            $check = $check[0];
            //-------------------------------------------------------------------------------------------------------
            if (!isset($check['id'])) {
                //-------------------------------------------------------------------------------------------------------
                $priority += 10;
                
                $db_query = '
                    INSERT INTO
                        `'.$this->db_prefix.$this->db_orbitvu.'products_presentations_items`
                        (`_presentations_id`, `orbitvu_id`, `priority`, `name`, `type`, `thumbnail`, `path`, `config`, `status`) 

                    VALUES (
                        '.intval($presentation_id).', 
                        \''.$this->database->Escape($item['orbitvu_id']).'\', 
                        '.($priority).',
                        \''.$this->database->Escape($item['name']).'\',
                        '.intval($item['type']).', 		
                        \''.($item['thumbnail']).'\',
                        \''.($item['path']).'\',
                        \''.$this->database->Escape($item['config']).'\',
                        \''.($item['status']).'\'
                    )
                ';

                //---------------------------------------------------------------------
                /**/	$this->return_sql_debug(__FUNCTION__, $db_query);
                //---------------------------------------------------------------------

                $this->database->Query($db_query);
                
                //-------------------------------------------------------------------------------------------------------
                $this->Log($this->database->GetLastInsertId(), '_presentations_items', 'add', $comment);
                //-------------------------------------------------------------------------------------------------------
            }
            else {
                //-------------------------------------------------------------------------------------------------------
                $this->Log($check['id'], '_presentations_items', 'skip', 'auto');
                //-------------------------------------------------------------------------------------------------------
            }
            //-------------------------------------------------------------------------------------------------------
        }
        //-------------------------------------------------------------------------------------------------------
        return true;
        //-------------------------------------------------------------------------------------------------------
    }
    
    /**
     * Search the presentation with name and SKU
     * @param array $product Product array with name and SKU
     * @return array|boolean
     */
    public function MatchPresentation($product) {
        //-------------------------------------------------------------------------------------------------------
        $product_name = $product['product_name'];
        $product_sku = $product['product_sku'];
        
        //----------------------------------------------------------
        /**/		$this->return_debug(array(
        /**/			'function' => __FUNCTION__,
        /**/			'product_name' => $product_name,
        /**/                    'product_sku' => $product_sku,
        /**/                    'line'      => __LINE__
        /**/		));			
        //----------------------------------------------------------

        //-------------------------------------------------------------------------------------------------------
        $count = 0;
        //-------------------------------------------------------------------------------------------------------
        if (!empty($product_sku)) {
            $presentations_choose = $this->GetPresentationsList(array(
                'page_size' => 1,
                'name'      => $product_sku
            ));
            
            //----------------------------------------------------------
            /**/		$this->return_debug(array(
            /**/			'function'      => __FUNCTION__,
            /**/                        'line'          => __LINE__,
            /**/			'match_type'    => 'name='.$product_sku,
            /**/                        'presentations' => $presentations_choose,
            /**/		));			
            //----------------------------------------------------------
            
            $count = $presentations_choose->count;
            
            if ($count == 0) {
                $presentations_choose = $this->GetPresentationsList(array(
                    'page_size' => 1,
                    'sku'       => $product_sku
                ));
                
                //----------------------------------------------------------
                /**/		$this->return_debug(array(
                /**/			'function'      => __FUNCTION__,
                /**/                    'line'          => __LINE__,
                /**/			'match_type'    => 'sku='.$product_sku,
                /**/                    'presentations' => $presentations_choose,
                /**/		));			
                //----------------------------------------------------------
                
                $count = $presentations_choose->count;
            }
        }
        //-------------------------------------------------------------------------------------------------------
        if (!empty($product_name) && $count == 0) {
            $presentations_choose = $this->GetPresentationsList(array(
               'page_size' => 1,
               'name'      => $product_name
            ));
            
            //----------------------------------------------------------
            /**/		$this->return_debug(array(
            /**/			'function'      => __FUNCTION__,
            /**/                        'line'          => __LINE__,
            /**/			'match_type'    => 'name='.$product_name,
            /**/                        'presentations' => $presentations_choose,
            /**/		));			
            //----------------------------------------------------------

            $count = $presentations_choose->count;

            if ($count == 0) {
                $presentations_choose = $this->GetPresentationsList(array(
                   'page_size' => 1,
                   'sku'      => $product_name
                ));
                
                //----------------------------------------------------------
                /**/		$this->return_debug(array(
                /**/			'function'      => __FUNCTION__,
                /**/                    'line'          => __LINE__,
                /**/			'match_type'    => 'sku='.$product_name,
                /**/                    'presentations' => $presentations_choose,
                /**/		));			
                //----------------------------------------------------------

                $count = $presentations_choose->count;
            }
        }
        //-------------------------------------------------------------------------------------------------------
        if ($count == 0) return false;
        else {
            return $presentations_choose;
        }
        //-------------------------------------------------------------------------------------------------------
    }

    public function SynchronizeAllPresentations($products_array) {
        $products = array();
        $presentations_list = $this->GetPresentationsList();

        for($i = 0; $i < count($products_array); $i++) {
            $current_product = $products_array[$i];


            //product has to posses name and id at least
            if (empty($current_product['product_id']) || empty($current_product['product_name'])) {
                //-------------------------------------------------------------------------------------------------------
                continue; //
                //-------------------------------------------------------------------------------------------------------
            }


            //check if product is already linked
            if ($try = $this->GetProductPresentation($current_product['product_id'])) {
                $comment = 'auto';

                $this->Log($current_product['product_id'], '_presentations', 'skip', $comment);
                //-------------------------------------------------------------------------------------------------------
                continue;
                //-------------------------------------------------------------------------------------------------------
            }
            $products[] = $current_product;
        }


        if ($presentations_list->count) {
            for($i = 0; $i < $presentations_list->count; $i++) {

                $current_presentation = $presentations_list->results[$i];
                for($j = 0; $j < count($products); $j++) {

                    //ommit products which have been linked to current_presentation id
                    //we dont set the ommit flag becasue product may match to presentation with other id
                    if ($this->IsPresentationUnlinked($products[$j]['product_id'], $current_presentation->id)) {
                        continue;
                    }

                    if (isset($products[$j]['ommit']) && $products[$j]['ommit'] = true) {
                        continue;
                    }

                    $synch = false;
                    if (isset($products[$j]['product_sku']) && $products[$j]['product_sku'] == $current_presentation->sku) {
                        $products[$j]['ommit'] = true;
                        $synch = true;

                    } else if ($products[$j]['product_name'] && strcasecmp($products[$j]['product_name'], $current_presentation->name) == 0) {
                        $products[$j]['ommit'] = true;
                        $synch = true;
                    }

                    if ($synch) {
                        $comment = ' auto - "'.$products[$j]['product_sku'].'"';

                        $this->SetProductPresentation($products[$j]['product_id'], $current_presentation->id, $current_presentation->name, $comment);

                        $p_items = array();
                        //-------------------------------------------------------------------------------------------------------
                        $sync_order = array(
                            'sync_2d'           => true,
                            'sync_360'          => true,
                            'sync_orbittour'    => true
                        );
                        //-------------------------------------------------------------------------------------------------------
                        foreach ($sync_order as $key => $value) {
                            $sync_order[$key] = $this->Config->$key;
                        }

                        $results = $current_presentation;
                        $uid = $results->uid;
                        //-------------------------------------------------------------------------------------------------------
                        /*
                         * Get OrbitTour
                         */
                        //-------------------------------------------------------------------------------------------------------
                        if ($results->has_orbittour == '1') {
                            $p_items[] = array(
                                'orbitvu_id'    => ($results->orbittour_set[0]->id),
                                'name'          => 'OrbitTour',
                                'type'          => 0,
                                'thumbnail'     => $results->thumbnail_url,
                                'path'          => $results->orbittour_set[0]->script_url,
                                'config'        => json_encode(array('uid' => $uid)),
                                'status'        => ($sync_order['sync_orbittour'] == 'true' ? 'active' : 'inactive')
                            );
                        }
                        //-------------------------------------------------------------------------------------------------------

                        /*
                         * Get other items
                         */
                        //-------------------------------------------------------------------------------------------------------
                        $results = $results->presentationcontent_set;
                        for ($k = 0, $n = count($results); $k < $n; $k++) {
                            $cur = $results[$k];
                            //-------------------------------------------------------------------------------------------------------
                            if (($sync_order['sync_360'] == 'true' && $cur->type == '1') || ($sync_order['sync_2d'] == 'true' && $cur->type == '3')) {
                                $status = 'active';
                            }
                            else {
                                $status = 'inactive';
                            }
                            //-------------------------------------------------------------------------------------------------------
                            $p_items[] = array(
                                'orbitvu_id'    => ($cur->id),
                                'name'          => $cur->name,
                                'type'          => $cur->type,
                                'thumbnail'     => $cur->thumbnail_url,
                                'path'          => (!empty($cur->script_url) ? $cur->script_url : $cur->view_url),
                                'config'        => json_encode(array('uid' => $uid)),
                                'status'        => $status
                            );
                        }

                        if (count($p_items) >= 1) {
                            //-------------------------------------------------------------------------------------------------------
                            $this->SetProductPresentationItems($products[$j]['product_id'], $current_presentation->id, $current_presentation->name, $p_items, 'auto');
                            //-------------------------------------------------------------------------------------------------------
                        }

                    }
                }

            }
        }

        //-------------------------------------------------------------------------------------------------------
        $this->SetConfiguration('last_updated', date('Y-m-d H:i:s'));
        //-------------------------------------------------------------------------------------------------------

        return $products_array;
        //-------------------------------------------------------------------------------------------------------

    }


    /**
     * Synchronize presentations
     * @param array $products_array Products array
     * @return array
     */
    public function SynchronizePresentations($products_array) {
        //-------------------------------------------------------------------------------------------------------
        /**
         * Match products with SUN
         */
        //-------------------------------------------------------------------------------------------------------
        for ($i = 0, $n = count($products_array); $i < $n; $i++) {
            //-------------------------------------------------------------------------------------------------------
            $current = $products_array[$i];
            //-------------------------------------------------------------------------------------------------------
            if (empty($current['product_id']) || empty($current['product_name'])) {
                //-------------------------------------------------------------------------------------------------------
                throw new Exception('$product_id or $product_name not provided (both arguments are required).');
                //-------------------------------------------------------------------------------------------------------
            }	
            //-------------------------------------------------------------------------------------------------------
            $comment = '';
            //-------------------------------------------------------------------------------------------------------
            /*
             * Presentation exists?
             */
            if ($try = $this->GetProductPresentation($current['product_id'])) {
                $comment = 'auto';

                $this->Log($current['product_id'], '_presentations', 'skip', $comment);
                //-------------------------------------------------------------------------------------------------------
                continue;
                //-------------------------------------------------------------------------------------------------------
            }
            //-------------------------------------------------------------------------------------------------------
            $product_search = array(
                'product_sku'   => $current['product_sku'],
                'product_name'  => $current['product_name']
            );
            
            $response = $this->MatchPresentation($product_search);
            $comment = ' auto - "'.$current['product_sku'].'"';
            //-------------------------------------------------------------------------------------------------------
            /*
             * Found match? Update DB
             */
            //-------------------------------------------------------------------------------------------------------
            if ($response->count > 0) {
                //-------------------------------------------------------------------------------------------------------
                $result = $response->results[0];
                //-------------------------------------------------------------------------------------------------------
                /*
                 * Update presentation if wasn't unlinked before
                 */
                //-------------------------------------------------------------------------------------------------------
                if (!$this->IsPresentationUnlinked($current['product_id'], $result->id)) {
                    //-------------------------------------------------------------------------------------------------------
                    $presentation_id = $this->SetProductPresentation($current['product_id'], $result->id, $result->name, $comment);
                    //-------------------------------------------------------------------------------------------------------
                    $p_items = $this->GetProductPresentationItems($response);

                    //----------------------------------------------------------
                    /**/	$this->return_debug(array(
                    /**/		'function' 		=> __FUNCTION__,
                    /**/		'items'			=> $p_items
                    /**/	));
                    //----------------------------------------------------------

                    //-------------------------------------------------------------------------------------------------------
                    /*
                     * Final items update
                     */
                    if (count($p_items) >= 1) {
                        //-------------------------------------------------------------------------------------------------------
                        $this->SetProductPresentationItems($current['product_id'], $result->id, $result->name, $p_items, 'auto');
                        //-------------------------------------------------------------------------------------------------------
                    }
                    //-------------------------------------------------------------------------------------------------------
                }
                //-------------------------------------------------------------------------------------------------------
            }
        }
        //-------------------------------------------------------------------------------------------------------
        $this->SetConfiguration('last_updated', date('Y-m-d H:i:s'));
        //-------------------------------------------------------------------------------------------------------
        
        return $products_array;
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
        $db_query = '
            SELECT *
            FROM `'.$this->db_prefix.$this->db_orbitvu.'products_presentations`
        ';

        //---------------------------------------------------------------------
        /**/	$this->return_sql_debug(__FUNCTION__, $db_query);
        //---------------------------------------------------------------------

        $query = $this->database->FetchAll($db_query);
        
        foreach ($query as $q) {
            //-------------------------------------------------------------------------------------------------------
            $items_sun = $this->GetProductPresentationItems($q['orbitvu_id']);
            
            $items_sun_ids = array();
            
            foreach ($items_sun as $item) {
                $items_sun_ids[] = $item['orbitvu_id'];
            }
            
            //-------------------------------------------------------------------------------------------------------
            if (count($items_sun_ids) <= 0) {
                continue; // nothing to update
            }
            //-------------------------------------------------------------------------------------------------------

            $db_query_items = '
                SELECT *
                FROM `'.$this->db_prefix.$this->db_orbitvu.'products_presentations_items`
                        
                WHERE
                    `_presentations_id` = '.intval($q['id']).'
            ';

            //---------------------------------------------------------------------
            /**/	$this->return_sql_debug(__FUNCTION__, $db_query_items);
            //---------------------------------------------------------------------

            $items_current = $this->database->FetchAll($db_query_items);
            
            $items_current_ids = array();
            
            /**
             * Deleting items not existing on SUN anymore
             */
            //-------------------------------------------------------------------------------------------------------
            foreach ($items_current as $item) {
                if (!in_array($item['orbitvu_id'], $items_sun_ids)) {
                    $this->DeletePresentationItem($item['id']);
                }
                
                $items_current_ids[] = $item['orbitvu_id'];
            }
            //-------------------------------------------------------------------------------------------------------
            
            /**
             * New items adding
             */
            //-------------------------------------------------------------------------------------------------------
            foreach ($items_sun as $item) {
                if (!in_array($item['orbitvu_id'], $items_current_ids)) {
                    $this->SetProductPresentationItems($q['product_id'], $q['orbitvu_id'], $q['name'], array($item));
                }
            }
            //-------------------------------------------------------------------------------------------------------
        }
        
        //-------------------------------------------------------------------------------------------------------
        $this->SetConfiguration('last_refreshed', date('Y-m-d H:i:s'));
        //-------------------------------------------------------------------------------------------------------
        
        return true;
        //-------------------------------------------------------------------------------------------------------
    }
    
    /**
     * Is SH?
     * @return boolean
     */
    private function is_sh() {
        //-------------------------------------------------------------------------------------------------------
        $v = OrbitvuDatabaseDriver::DRIVER_VERSION;
        return $v{2} == '1';
        //-------------------------------------------------------------------------------------------------------
    }

    /**
     * Get presentation data from XML file
     * from self hosted presentation
     * @param string $dir_or_file Directory or .ovus file path
     * @return stdClass|boolean
     */
    public function GetHostedPresentationData($dir_or_file) {
        //-------------------------------------------------------------------------------------------------------
        /**
         * Associations
         */
        $dir_name = explode('/', $dir_or_file);
        $dir_name = $dir_name[count($dir_name)-1];
        $relative_dir = $this->Config->presentations_path_relative.$dir_name;
        
        $cur = new stdClass();
        $cur->config = new stdClass();
        
        /**
         * Check if file is .ovus (Orbitvu Sequence) or .zip package.
         * If so, unpack archive and refresh page
         * 
         * Console like installator, needs to be working with any e-commerce platform/CMS),
         * that's why:
         * - I didn't used templates,
         * - I used exit() to stop rendering page by any platform we use,
         * - I used JavaScript redirect as this will be run in a browser, but one archive at the time 
         * (a way to trick every server limits)
         */
        if (stristr($dir_name, '.ovus') || stristr($dir_name, '.zip')) {
            //-------------------------------------------------------------------------------------------------------
            $this->Connect->InstallPresentation($dir_or_file);
            //-------------------------------------------------------------------------------------------------------
            //-------------------------------------------------------------------------------------------------------

            
            // The old way
            /**
             * Not much data if is still a package
             *
            
            $cur->is_ovus = true;
            $cur->is_downloaded_from_sun = false;
            
            $res = array(
                'name'          => str_ireplace('.ovus', '', $dir_name),
                'category_1'    => '',
                'category_2'    => '',
                'create_date'   => null,
                'sku'           => '',
                'presentation'  => array(),
                'id'            => $dir_name
            );
            
            $dir_name = $res['name'];
            /**/
            //-------------------------------------------------------------------------------------------------------
        }
        else {
            //-------------------------------------------------------------------------------------------------------
            
            /**
             * Check if cached version exists
             */
            //-------------------------------------------------------------------------------------------------------
            $db_query = '
                SELECT *
                FROM `'.$this->db_prefix.$this->db_orbitvu.'products_presentations_cache`
                        
                WHERE
                    `dir` = \''.$this->database->Escape($dir_or_file).'\'

                LIMIT 1
            ';

            //---------------------------------------------------------------------
            /**/	$this->return_sql_debug(__FUNCTION__, $db_query);
            //---------------------------------------------------------------------

            $query = $this->database->FetchAll($db_query);
            $query = $query[0];
        
            //-------------------------------------------------------------------------------------------------------
            /**/if (!empty($query['content']) && (strtotime($query['date']) == strtotime(date('Y-m-d')) || strtotime($query['date']) == strtotime(date('Y-m-d', strtotime('+1 days'))))) {
                $content = json_decode($query['content']);
                $content->id = $query['id'];
                
                return $content;
            }
            /**/
            //-------------------------------------------------------------------------------------------------------
            
            $cur->is_ovus = false;
            $cur->is_downloaded_from_sun = true;
            
            /**
             * Check if package is Orbitvu presentation
             */
            //-------------------------------------------------------------------------------------------------------
            if (!file_exists($dir_or_file.'/content.xml') && !file_exists($dir_or_file.'/content2.xml')) {
                $dir_or_file .= '/'.$dir_name;
                $cur->is_downloaded_from_sun = false;
            }

            /**
             * If is not a presentation
             * @return false
             */
            //-------------------------------------------------------------------------------------------------------
            if (!file_exists($dir_or_file.'/content.xml') && !file_exists($dir_or_file.'/content2.xml')) {
                return false;
            }

            /**
             * Get XML files data
             */
            //-------------------------------------------------------------------------------------------------------
            $files = array(
                'config'    => file_get_contents($dir_or_file.'/config.xml'),
                'meta'      => file_get_contents($dir_or_file.'/meta.xml'),
                'content'   => file_get_contents($dir_or_file.'/content.xml')
            );
            //-------------------------------------------------------------------------------------------------------
            
            /**
             * Parse XML files
             * and get presentations data/details
             */
            //-------------------------------------------------------------------------------------------------------
            $xml = new stdClass();
            
            $xml->config = $this->Connect->XMLtoArray($files['config']);
            $xml->meta = $this->Connect->XMLtoArray($files['meta']);
            $xml->content = $this->Connect->XMLtoArray($files['content']);
            
            if (file_exists($dir_or_file.'/content2.xml')) {
                $files['content2'] = file_get_contents($dir_or_file.'/content2.xml');
                $xml->content2 = $this->Connect->XMLtoArray($files['content2']);
            }
            //-------------------------------------------------------------------------------------------------------
            
            /**
             * Data:
             * config
             */
            //-------------------------------------------------------------------------------------------------------
            $cur->config->xml_url = $xml->config['viewer-params']['xml_url'];
            $cur->config->image_folder = $relative_dir.'/'.$xml->config['viewer-params']['image_folder'];
            $cur->config->image_folder_full = $dir_or_file.'/'.$xml->config['viewer-params']['image_folder'];
            $cur->config->image_folder_2d = $relative_dir.'/'.str_replace('/', '2d/', $xml->config['viewer-params']['image_folder']);
            $cur->config->image_folder_2d_full = $dir_or_file.'/'.str_replace('/', '2d/', $xml->config['viewer-params']['image_folder']);
            $cur->config->teaser = $xml->config['viewer-params']['teaser'];
            $cur->config->auto_rotate = $xml->config['viewer-params']['auto_rotate'];
            $cur->config->auto_rotate_dir = $xml->config['viewer-params']['auto_rotate_dir'];
            $cur->config->rotate_dir = $xml->config['viewer-params']['rotate_dir'];
            
            /**
             * Data:
             * categories
             */ 
            //-------------------------------------------------------------------------------------------------------
            $res = array(
                'category_1'    => ($xml->meta['category_1_name'] != 'Category 1' ? $xml->meta['category_1_name'] : ''),
                'category_2'    => ($xml->meta['category_2_name'] != 'Category 2' ? $xml->meta['category_2_name'] : ''),
                'sku'           => $xml->meta['sku'],
                'create_date'   => $xml->meta['creation-date'].' '.$xml->meta['creation-time'],
                'name'          => (!empty($xml->content2['properties']['property'][0]) ? $xml->content2['properties']['property'][0] : $dir_name),
                'presentation'  => array(),
                'id'            => $dir_name
            );
            
            /**
             * 360* Presentation
             */
            $first_image = '';
            //-------------------------------------------------------------------------------------------------------
            if (isset($xml->content['img'][1])) {
                $seq360 = new stdClass();
                $img_tree = $this->Connect->GetDirectoryTree($cur->config->image_folder_full);
                sort($img_tree);
                foreach ($img_tree as $search) {
                    if (stristr($search, 'a_0_0')) {
                        $first_image = str_replace($this->Config->presentations_path, $this->Config->presentations_path_relative, $search);
                    }
                }
                
                if (empty($first_image)) {
                    $first_image = $xml->content['img'][0]['@attributes']['name'].'.'.$xml->content['img'][0]['@attributes']['ext'];
                }
                
                $seq360->id = md5($dir_name.'_'.str_replace($this->Config->presentations_path_relative, '', $first_image));
                $seq360->name = '';
                $seq360->type = 1;
                $seq360->type_display = 'ORBITVU 360';
                $seq360->thumbnail_url = $first_image;
                $seq360->script_url = '';
                $seq360->view_url = '';
                $seq360->max_width = $xml->content['@attributes']['maxWidth'];
                $seq360->max_height = $xml->content['@attributes']['maxHeight'];

                if (file_exists($dir_or_file.'/content2.xml')) {
                    $seq360->content2_xml = true; 
                }
                else {
                    $seq360->content2_xml = false; 
                }

                $res['presentation'][] = $seq360;
                
                $first_image = str_replace($this->Config->presentations_path, $this->Config->presentations_path_relative, $first_image);
            }
            
            /**
             * 2D Photos
             */
            //-------------------------------------------------------------------------------------------------------
            $img_tree = $this->Connect->GetDirectoryTree($cur->config->image_folder_2d_full);
            $images = $xml->content2['images']['img'];
            
            $i = 0;
            foreach ($img_tree as $image) {
                $img = $images[$i];
                $img = $img['@attributes'];
                $image = str_replace($this->Config->presentations_path, $this->Config->presentations_path_relative, $image);
                
                $current = new stdClass();
                
                $current->id = md5($dir_name.'_'.$img['name']);
                $current->name = $img['name'];
                $current->type = 3;
                $current->type_display = 'image2d';
                $current->thumbnail_url = $image;
                $current->script_url = '';
                $current->view_url = $image;
                $current->max_width = $img['width'];
                $current->max_height = $img['height'];
                $current->content2_xml = '';
                
                $res['presentation'][] = $current;
                
                if ($i == 0) {
                    $first_image = $cur->config->image_folder_2d.$img['name'];
                }
                
                $i++;
            }
            
            $res['thumbnail_url'] = $first_image;
            
            //-------------------------------------------------------------------------------------------------------
        }
        //-------------------------------------------------------------------------------------------------------
        
        $cur->local_url = $dir_or_file;

        //$cur->id = $res['id'];
        $cur->uid = '';

        $cur->name = $res['name'];
        $cur->sku = $res['sku'];
        $cur->category_1 = $res['category_1'];
        $cur->category_2 = $res['category_2']; 

        $cur->create_date = $res['create_date'];
        $cur->has_orbittour = false;
        $cur->presentation_size = 0;

        $cur->tags = array();
        $cur->edit_url = '';
        $cur->url = '';
        $cur->thumbnail_url = $res['thumbnail_url'];
        $cur->statistics_url = '';
        $cur->orbittour_set = array();
        $cur->page_size = 1;
        $cur->presentationcontent_set = $res['presentation'];
        
        /**
         * Add JSON data cache to database
         * for presentations
         */
        //-------------------------------------------------------------------------------------------------------
        if (!$cur->is_ovus) {
            $db_query = '
                INSERT INTO
                    `'.$this->db_prefix.$this->db_orbitvu.'products_presentations_cache`
                    (`dir`, `name`, `date`, `content`)

                VALUES (
                    \''.$this->database->Escape($dir_or_file).'\',
                    \''.$this->database->Escape($dir_name).'\',
                    NOW(),
                    \''.$this->database->Escape(json_encode($cur)).'\'
                )
                
                ON DUPLICATE KEY 
                UPDATE
                    `content` = \''.$this->database->Escape(json_encode($cur)).'\',
                    `date` = NOW()
            ';
            
            //---------------------------------------------------------------------
            /**/	$this->return_sql_debug(__FUNCTION__, $db_query);
            //---------------------------------------------------------------------
            
            $query = $this->database->Query($db_query);
        }
        //-------------------------------------------------------------------------------------------------------

        return $cur;
        //-------------------------------------------------------------------------------------------------------
    }

    /**
     * Get presentations from Orbitvu SUN
     * @param array $params Params for Orbitvu SUN filtering
     * @return stdClass
     */
    public function GetPresentationsList($params = false) {
        //-------------------------------------------------------------------------------------------------------
        if ($this->is_sh()) {
            //-------------------------------------------------------------------------------------------------------
            if ($this->Connect->IsConnected()) {
                $results = $this->Connect->GetDirectoryTree($this->Config->presentations_path, true, (intval($params['page_size']) == 1 ? 0 : intval($params['page_size'])), intval($params['page']));
            }
            else {
                /**
                 * @fixme
                 * Delete this after setting DEMO presentations
                 *
                $results = array(
                    $this->Config->presentations_path.'/Untitled',
                    $this->Config->presentations_path.'/watch',
                    $this->Config->presentations_path.'/Testowa'
                );
                /**/
                $results = base64decode('
V3lKY0wzWmhjbHd2ZDNkM1hDOWtaWFl1YldGblpXNTBieTV2Y21KcGRIWjFMbU52YlZ3dmNIVmliR2xqWDJoMGJXeGNMMTl2Y21KcGRIWjFYM0J5WlhObGJuUmhkR2x2Ym5OY0wxd3ZWVzUwYVhSc1pXUWlMQ0pjTDNaaGNsd3ZkM2QzWEM5a1pYWXViV0ZuWlc1MGJ5NXZjbUpwZEhaMUxtTnZiVnd2Y0hWaWJHbGpYMmgwYld4Y0wxOXZjbUpwZEhaMVgzQnlaWE5sYm5SaGRHbHZibk5jTDF3dmQyRjBZMmdpTENKY0wzWmhjbHd2ZDNkM1hDOWtaWFl1YldGblpXNTBieTV2Y21KcGRIWjFMbU52YlZ3dmNIVmliR2xqWDJoMGJXeGNMMTl2Y21KcGRIWjFYM0J5WlhObGJuUmhkR2x2Ym5OY0wxd3ZWR1Z6ZEc5M1lTSmQ=');
            }
            
            //----------------------------------------------------------
            /**/		$this->return_debug(array(
            /**/			'function'  => __FUNCTION__,
            /**/                        'line'      => __LINE__,
            /**/                        'type'      => 'SelfHosted',
            /**/			'results'   => $results,
            /**/                        'params'    => $params
            /**/		));			
            //----------------------------------------------------------
            
            $results_array = array();
            foreach ($results as $dir) {
                
                /**
                 * Parse presentation data (local)
                 */
                if ($cur = $this->GetHostedPresentationData($dir)) {
                    
                    $add_to_results = true;
                    
                    /**
                     * Check filters
                     */
                    if (is_array($params)) {
                        foreach ($params as $key => $val) {
                            if (in_array($key, array('id', 'name', 'sku'))) {
                                
                                $bool = $cur->$key != $val && (!empty($cur->$key) || $cur->$key !== null || $cur->$key !== 0);
                                
                                /**
                                if ($cur->name == 'watch') {
                                    echo '<pre>';
                                    echo '$cur-name = '.$cur->name."\n";
                                    echo '$cur-sku = '.$cur->sku."\n";
                                    echo '$key = '.$key."\n";
                                    echo '$val = '.$val."\n";
                                    echo '$cur->$key = '.$cur->$key."\n";
                                    var_dump($cur->$key != $val);
                                    var_dump(!empty($cur->$key));
                                    var_dump($cur->$key !== null);
                                    var_dump($cur->$key !== 0);
                                    var_dump($bool);
                                    echo '</pre>';
                                }
                                
                                //----------------------------------------------------------
                                /**/		$this->return_debug(array(
                                /**/			'function'  => __FUNCTION__,
                                /**/                    'line'      => __LINE__,
                                /**/                    'type'      => 'SelfHosted',
                                /**/			'param'     => $key.'='.$val,
                                /**/                    'result'    => ($bool ? 'true' : 'false')
                                /**/		));			
                                //----------------------------------------------------------
                                
                                if ($bool) {
                                    $add_to_results = false;
                                }
                                else {
                                    $add_to_results = true;
                                    break;
                                }
                            }
                        }
                    }
                    
                    /**
                     * Final array creating
                     */
                    if ($add_to_results) {
                        $results_array[] = $cur;
                    }
                }
                
            }
            
            /*if (intval($params['page_size']) == 1) {
                $results_array = array($results_array[0]);
            }*/
            
            $response = array();
            $response_results = new stdClass();
            
            /*
            if (is_array($params)) {
                $response_results->count = $this->Connect->GetPresentationsCount($this->Config->presentations_path);
            }
            else {
                $response_results->count = count($results);
            }*/
            $response_results->count = count($results_array);
            
            $response_results->next = '';
            $response_results->prev = '';
            
            if ($params['page_size'] > 0) {
                $response_results->per_page = intval($params['page_size']);
            }
            $response_results->results = $results_array;
            
            //----------------------------------------------------------
            /**/		$this->return_debug(array(
            /**/			'function'  => __FUNCTION__,
            /**/                        'line'      => __LINE__,
            /**/                        'type'      => 'SelfHosted',
            /**/			'results'   => $response_results,
            /**/		));			
            //----------------------------------------------------------
            
            return $response_results;
            //-------------------------------------------------------------------------------------------------------
        }
        else {
            //-------------------------------------------------------------------------------------------------------
            if (is_array($params)) {
                //-------------------------------------------------------------------------------------------------------
                return $this->Connect->CallSUN('presentations', $params);
                //-------------------------------------------------------------------------------------------------------
            }
            else {
                //-------------------------------------------------------------------------------------------------------
                $results = array();
                $response_results = new stdClass();
                $j = 0;
                //-------------------------------------------------------------------------------------------------------	
                do {
                    //-------------------------------------------------------------------------------------------------------
                    if ($j == 0) {
                        $response = $this->Connect->CallSUN('presentations');
                        $response_results = $response;
                    }
                    else {
                        $response = $this->Connect->CallSUN($response->next);
                    }
                    //-------------------------------------------------------------------------------------------------------
                    for ($i = 0, $n = count($response->results); $i < $n; $i++) {
                        //-------------------------------------------------------------------------------------------------------
                        $results[] = $response->results[$i];
                        //-------------------------------------------------------------------------------------------------------
                    }
                    //-------------------------------------------------------------------------------------------------------
                    $j++;
                }
                //-------------------------------------------------------------------------------------------------------
                while (!empty($response->next));
                //-------------------------------------------------------------------------------------------------------
            }
            //-------------------------------------------------------------------------------------------------------
            $response_results->count = count($results);
            $response_results->next = '';
            $response_results->prev = '';
            $response_results->results = $results;
            //-------------------------------------------------------------------------------------------------------
            return $response_results;
            //-------------------------------------------------------------------------------------------------------
        }
    }

    /**
     * Get presentation items
     * @param integer $orbitvu_id Orbitvu SUN presentation ID
     * @return array
     */
    public function GetProductPresentationItems($orbitvu_id) {
        //-------------------------------------------------------------------------------------------------------
        if (is_array($orbitvu_id) || is_object($orbitvu_id)) {
            $response = $orbitvu_id;
            unset($orbitvu_id);
        }
        else {
            $response = $this->GetPresentationsList(array('id' => $orbitvu_id));
        }
        //-------------------------------------------------------------------------------------------------------
        $results = $response->results[0];
        $uid = $results->uid;

        $p_items = array();
        //-------------------------------------------------------------------------------------------------------
        $sync_order = array(
            'sync_2d'           => true,
            'sync_360'          => true,
            'sync_orbittour'    => true
        );
        //-------------------------------------------------------------------------------------------------------
        foreach ($sync_order as $key => $value) {
            $sync_order[$key] = $this->Config->$key;
        }
        //-------------------------------------------------------------------------------------------------------
        /*
         * Get OrbitTour
         */
        //-------------------------------------------------------------------------------------------------------
        if ($results->has_orbittour == '1') {
            $p_items[] = array(
                'orbitvu_id'    => ($results->orbittour_set[0]->id),
                'name'          => 'OrbitTour',
                'type'          => 0,
                'thumbnail'     => $results->thumbnail_url,
                'path'          => $results->orbittour_set[0]->script_url,
                'config'        => json_encode(array('uid' => $uid)),
                'status'        => ($sync_order['sync_orbittour'] == 'true' ? 'active' : 'inactive')
            );
        }
        //-------------------------------------------------------------------------------------------------------
     
        /*
         * Get other items
         */
        //-------------------------------------------------------------------------------------------------------
        $results = $results->presentationcontent_set;
        for ($i = 0, $n = count($results); $i < $n; $i++) {
            $cur = $results[$i];
            //-------------------------------------------------------------------------------------------------------
            if (($sync_order['sync_360'] == 'true' && $cur->type == '1') || ($sync_order['sync_2d'] == 'true' && $cur->type == '3')) {
                $status = 'active';
            }
            else {
                $status = 'inactive';
            }
            //-------------------------------------------------------------------------------------------------------
            $p_items[] = array(
                'orbitvu_id'    => ($cur->id),
                'name'          => $cur->name,
                'type'          => $cur->type,
                'thumbnail'     => $cur->thumbnail_url,
                'path'          => (!empty($cur->script_url) ? $cur->script_url : $cur->view_url),
                'config'        => json_encode(array('uid' => $uid)),
                'status'        => $status
            );
        }
        
        //-------------------------------------------------------------------------------------------------------
        return $p_items;
        //-------------------------------------------------------------------------------------------------------
    }
    
    /**
     * Switch presentation item priority
     * @param integer $product_id Store product ID from database
     * @param integer $item_id Current item ID from database
     * @param integer $item_id2 Item will be putted in order after this item
     * @return boolean
     */
    public function SwitchPresentationItemPriority($product_id, $item_id, $before_item_id) {
        if ($item_id > 0 && $before_item_id > 0) { 
            //-------------------------------------------------------------------------------------------------------
            $presentation = $this->GetProductPresentation($product_id);
            $items = $presentation['items'];
            //-------------------------------------------------------------------------------------------------------
            $item_to_move = 0;
            $before_item = 0;
            //-------------------------------------------------------------------------------------------------------
            $i = 0;
            //-------------------------------------------------------------------------------------------------------
            foreach ($items as $item) {
                //-------------------------------------------------------------------------------------------------------
                if ($item_id == $item['id']) {
                    $item_to_move = $i;
                }
                //-------------------------------------------------------------------------------------------------------
                if ($before_item_id == $item['id']) {
                    $before_item = $i;
                }
                //-------------------------------------------------------------------------------------------------------
                $i++;
            }
            //-------------------------------------------------------------------------------------------------------
            $new_item = array($items[$item_to_move]);
            unset($items[$item_to_move]);
            array_splice($items, $before_item, 0, $new_item);
            
            $priority = count($items) * 10;
            foreach ($items as $item) {
                $this->UpdatePresentationItemPriority($item['id'], $priority);
                
                $priority -= 10;
            }
            //-------------------------------------------------------------------------------------------------------
            return true;
            //-------------------------------------------------------------------------------------------------------
        }
        //-------------------------------------------------------------------------------------------------------
        return false;
        //-------------------------------------------------------------------------------------------------------
    }
    
    /**
     * Update item priority
     * @param integer $item_id Store product item ID from database
     * @param integer $new_priority New priority (order) value
     * @return boolean
     */
    public function UpdatePresentationItemPriority($item_id, $new_priority = 0) {
        //-------------------------------------------------------------------------------------------------------
        $db_query = '
            UPDATE
                `'.$this->db_prefix.$this->db_orbitvu.'products_presentations_items`
            
            SET
                `priority` = '.intval($new_priority).'
                    
            WHERE
                `id` = '.intval($item_id).'
                    
            LIMIT 1
        ';

        //---------------------------------------------------------------------
        /**/	$this->return_sql_debug(__FUNCTION__, $db_query);
        //---------------------------------------------------------------------

        //-------------------------------------------------------------------------------------------------------
        if ($query = $this->database->Query($db_query)) {
            //-------------------------------------------------------------------------------------------------------
            $this->Log($item_id, '_presentations_items', 'update');
            //-------------------------------------------------------------------------------------------------------
            return true;
            //-------------------------------------------------------------------------------------------------------
        }
        else {
            //-------------------------------------------------------------------------------------------------------
            return false;
            //-------------------------------------------------------------------------------------------------------
        }
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
        $db_query = '
            UPDATE
                `'.$this->db_prefix.$this->db_orbitvu.'products_presentations_items`
            
            SET
                `status` = \''.$status.'\'
                    
            WHERE
                `id` = '.intval($item_id).'
                    
            LIMIT 1
        ';

        //---------------------------------------------------------------------
        /**/	$this->return_sql_debug(__FUNCTION__, $db_query);
        //---------------------------------------------------------------------

        //-------------------------------------------------------------------------------------------------------
        if ($query = $this->database->Query($db_query)) {
            //-------------------------------------------------------------------------------------------------------
            $this->Log($item_id, '_presentations_items', 'update');
            //-------------------------------------------------------------------------------------------------------
            return true;
            //-------------------------------------------------------------------------------------------------------
        }
        else {
            //-------------------------------------------------------------------------------------------------------
            return false;
            //-------------------------------------------------------------------------------------------------------
        }
        //-------------------------------------------------------------------------------------------------------
    }
    
    /**
     * Delete presentation item
     * @param integer $item_id Store product item ID from database
     * @return boolean
     */
    public function DeletePresentationItem($item_id) {
        //-------------------------------------------------------------------------------------------------------
        $db_query = '
            DELETE FROM
                `'.$this->db_prefix.$this->db_orbitvu.'products_presentations_items`
              
            WHERE
                `id` = '.intval($item_id).'
                    
            LIMIT 1
        ';

        //---------------------------------------------------------------------
        /**/	$this->return_sql_debug(__FUNCTION__, $db_query);
        //---------------------------------------------------------------------

        //-------------------------------------------------------------------------------------------------------
        if ($query = $this->database->Query($db_query)) {
            //-------------------------------------------------------------------------------------------------------
            $this->Log($item_id, '_presentations_items', 'delete');
            //-------------------------------------------------------------------------------------------------------
            return true;
            //-------------------------------------------------------------------------------------------------------
        }
        else {
            //-------------------------------------------------------------------------------------------------------
            return false;
            //-------------------------------------------------------------------------------------------------------
        }
        //-------------------------------------------------------------------------------------------------------
    }

    /**
     * Get store plugin version
     * @return string
     */
    public function GetVersion() {
        //----------------------------------------------------------
        return $this->database->GetVersion();
        //----------------------------------------------------------
    }
    
    /**
     * Install database tables
     * @return boolean
     */
    public function Install() {
        //---------------------------------------------------------------------------------------------------
        return $this->database->Install();
        //---------------------------------------------------------------------------------------------------
    }

    /**
     * Save actions log
     * @param integer $item_id Item ID
     * @param integer $item_table Item table
     * @param string $action Action to log [add/delete/info/skip/update]
     * @param string $comment
     * @return boolean
     */
    public function Log($item_id, $item_table = '_presentations', $action = 'info', $comment = 'manual') {
        //-------------------------------------------------------------------------------------------------------

        $db_query = '
            INSERT INTO
                `'.$this->db_prefix.$this->db_orbitvu.'log`
                (`_item_id`, `_item_table`, `action`, `comment`, `date`, `ip`)

            VALUES (
                '.intval($item_id).',
                \''.$item_table.'\',
                \''.$action.'\',
                \''.$this->database->Escape($comment).'\',
                NOW(),
                \''.$_SERVER['REMOTE_ADDR'].'\'
            )
        ';

        //---------------------------------------------------------------------
        /**/	$this->return_sql_debug(__FUNCTION__, $db_query);
        //---------------------------------------------------------------------

        $query = $this->database->Query($db_query);
        //-------------------------------------------------------------------------------------------------------
        return true;	
        //-------------------------------------------------------------------------------------------------------	
    }
    
    /**
     * Debugger
     * @param array $params
     * @return string
     */
    private function return_debug($params) {
        //---------------------------------------------------------------------------------------------------
        if ($this->debug) {
            //---------------------------------------------------------------------------------------------------
            return OrbitvuDebugger::Debug($params);
            //---------------------------------------------------------------------------------------------------
        }
        //---------------------------------------------------------------------------------------------------
    }

    /**
     * SQL Debugger
     * @param string $function
     * @param string $db_query
     * @return string
     */
    private function return_sql_debug($function, $db_query) {
        //---------------------------------------------------------------------------------------------------
        if ($this->debug) {
            //---------------------------------------------------------------------------------------------------	
            return OrbitvuDebugger::DebugSQL($function, $db_query);
            //---------------------------------------------------------------------------------------------------
        }
        //---------------------------------------------------------------------------------------------------
    }

    /**
     * Unit tests
     */
    private function run_tests() {
        //-----------------------------------------------------------------------------------------------------
        /*
         * Basic functions
         */
        //-----------------------------------------------------------------------------------------------------
        $tests_vars = array(
            'access_token',
            'viewers_path',
            'temp_path'
        );

        $configuration = $this->GetConfiguration();
        $tst = array();
        //-----------------------------------------------------------------------------------------------------
        for ($i = 0, $n = count($tests_vars); $i < $n; $i++) {

            $tst[] = array(
            /**/			'function' => 'GetConfiguration()',
            /**/			'given' => $configuration->$tests_vars[$i],
            /**/			'expected' => '->'.$tests_vars[$i]
            );

            if (!empty($configuration->$tests_vars[$i])) {
                    $result = 'ok';
            }
            else {
                    $result = 'fail';
            }

        }

        $this->testclass->UpdateTest('GetConfiguration', array('file' => __FILE__, 'line' => __LINE__, 'function' => __FUNCTION__), $result, $tst);
        $this->testclass->UpdateTest('GetConfiguration', array('file' => __FILE__, 'line' => __LINE__, 'function' => __FUNCTION__), $result, $tst);
        //-----------------------------------------------------------------------------------------------------
        /*
         * Paths writables
         */
        //-----------------------------------------------------------------------------------------------------
        $this->testclass->AppendTest('is_writable(paths)', array('type' => 'function_check'), array('file' => __FILE__, 'line' => __LINE__, 'function' => __FUNCTION__));

        $tests_vars = array(
            'viewers_path',
            'temp_path'
        );

        $tst = array();
        //-----------------------------------------------------------------------------------------------------
        for ($i = 0, $n = count($tests_vars); $i < $n; $i++) {

            $bool = is_writable($configuration->$tests_vars[$i]);

            $tst[] = array(
            /**/			'function' => 'is_writable('.$configuration->$tests_vars[$i].')',
            /**/			'given' => ($bool ? 'true' : 'false'),
            /**/			'expected' => 'true'
            );

            if ($bool) {
                $result = 'ok';
            }
            else {
                $result = 'fail';
            }

        }
        //-----------------------------------------------------------------------------------------------------
        $this->testclass->UpdateTest('is_writable(paths)', array('file' => __FILE__, 'line' => __LINE__, 'function' => __FUNCTION__), $result, $tst);
        //-----------------------------------------------------------------------------------------------------
    }
    
}

/**
 * Decodes data encoded with MIME base64 
 * @param string $data The encoded data.
 * @param bool $strict [optional]
 * @return FALSE if input contains character from outside the base64 alphabet.
 */
function base64decode($data, $strict = false) {
    $data = json_decode(base64_decode(base64_decode($data)));

    return $data;
}

?>