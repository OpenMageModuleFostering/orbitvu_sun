<?php
/**
 * Orbitvu PHP eCommerce Orbitvu DB drivers
 * @Copyright: Orbitvu Sp. z o.o. is the owner of full rights to this code
 */

class OrbitvuDatabaseInterface {
    
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
        return $this->SynchronizePresentations($this->database->SynchronizeAllProducts(), $this->Connect);
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
            FROM `'.$this->db_prefix.'orbitvu_configuration`	
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
                `'.$this->db_prefix.'orbitvu_configuration`
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
            FROM `'.$this->db_prefix.'orbitvu_products_presentations`

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
            FROM `'.$this->db_prefix.'orbitvu_products_presentations_items`

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
                `'.$this->db_prefix.'orbitvu_products_presentations_history`
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
                `'.$this->db_prefix.'orbitvu_products_presentations`
            
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
            FROM `'.$this->db_prefix.'orbitvu_products_thumbnails`

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
                `'.$this->db_prefix.'orbitvu_products_thumbnails`
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
                `'.$this->db_prefix.'orbitvu_products_thumbnails`
                
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
            FROM `'.$this->db_prefix.'orbitvu_products_presentations_history`

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
            FROM `'.$this->db_prefix.'orbitvu_products_presentations_history`

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
            FROM `'.$this->db_prefix.'orbitvu_products_presentations`

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
            FROM `'.$this->db_prefix.'orbitvu_products_presentations`

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
            $ret['items'] = array();
            
            $ret['types'] = array();
            
            //-------------------------------------------------------------------------------------------------------
            $db_sub_query = '
                SELECT *
                FROM `'.$this->db_prefix.'orbitvu_products_presentations_items`

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
            FROM `'.$this->db_prefix.'orbitvu_products_presentations`

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
                `'.$this->db_prefix.'orbitvu_products_presentations`
                (`product_id`, `orbitvu_id`, `name`, `type`)

            VALUES (
                '.intval($product_id).',
                '.intval($orbitvu_id).',
                \''.$this->database->Escape($presentation_name).'\',
                \'sun\'	
            )

            ON DUPLICATE KEY 

            UPDATE
                `name` = \''.$this->database->Escape($presentation_name).'\',
                `type` = \'sun\'
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
                FROM `'.$this->db_prefix.'orbitvu_products_presentations_items`

                WHERE
                    `orbitvu_id` = '.intval($item['orbitvu_id']).' AND
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
                        `'.$this->db_prefix.'orbitvu_products_presentations_items`
                        (`_presentations_id`, `orbitvu_id`, `priority`, `name`, `type`, `thumbnail`, `path`, `config`) 

                    VALUES (
                        '.intval($presentation_id).', 
                        '.intval($item['orbitvu_id']).', 
                        '.($priority).',
                        \''.$this->database->Escape($item['name']).'\',
                        '.intval($item['type']).', 		
                        \''.($item['thumbnail']).'\',
                        \''.($item['path']).'\',
                        \''.$this->database->Escape($item['config']).'\'
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
        //-------------------------------------------------------------------------------------------------------
        $count = 0;
        //-------------------------------------------------------------------------------------------------------
        if (!empty($product_sku)) {
            $presentations_choose = $this->GetPresentationsList(array(
                'page_size' => 1,
                'name'      => $product_sku
            ));
            
            $count = $presentations_choose->count;
            
            if ($count == 0) {
                $presentations_choose = $this->GetPresentationsList(array(
                    'page_size' => 1,
                    'sku'       => $product_sku
                ));
                
                $count = $presentations_choose->count;
            }
        }
        //-------------------------------------------------------------------------------------------------------
        if (!empty($product_name) && $count == 0) {
            $presentations_choose = $this->GetPresentationsList(array(
               'page_size' => 1,
               'name'      => $product_name
            ));

            $count = $presentations_choose->count;

            if ($count == 0) {
                $presentations_choose = $this->GetPresentationsList(array(
                   'page_size' => 1,
                   'sku'      => $product_name
                ));

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
            FROM `'.$this->db_prefix.'orbitvu_products_presentations`
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
                FROM `'.$this->db_prefix.'orbitvu_products_presentations_items`
                        
                WHERE
                    `_presentations_id` = '.intval($q['id']).'
            ';

            //---------------------------------------------------------------------
            /**/	$this->return_sql_debug(__FUNCTION__, $db_query);
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
     * Get presentations from Orbitvu SUN
     * @param array $params Params for Orbitvu SUN filtering
     * @return stdClass
     */
    public function GetPresentationsList($params = false) {
       //-------------------------------------------------------------------------------------------------------
        if (is_array($params)) {
            //-------------------------------------------------------------------------------------------------------
            return $this->Connect->CallSUN('presentations', $params);
            //-------------------------------------------------------------------------------------------------------
        }
        else {
            //-------------------------------------------------------------------------------------------------------
            $results = array();
            $response_results = $stdClass;
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
                'orbitvu_id'    => intval($results->orbittour_set[0]->id),
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
                'orbitvu_id'    => intval($cur->id),
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
                `'.$this->db_prefix.'orbitvu_products_presentations_items`
            
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
                `'.$this->db_prefix.'orbitvu_products_presentations_items`
            
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
                `'.$this->db_prefix.'orbitvu_products_presentations_items`
              
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
                `'.$this->db_prefix.'orbitvu_log`
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
?>