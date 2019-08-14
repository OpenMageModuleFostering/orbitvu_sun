<?php
/**
 * Orbitvu PHP eCommerce Orbitvu DB drivers
 * @Copyright: Orbitvu Sp. z o.o. is the owner of full rights to this code
 * 
 * @for: Magento
 * 
 * This is the only class to modify for your store
 */

class OrbitvuDatabaseDriver { 
    
    /**
     * Database instance
     * @var instance 
     */
    private $db_handle;
    
    /**
     * Database last insert ID
     * @var integer
     */
    private $db_last_id = 0;
    
    /**
     * Driver version (ID)
     */
    const DRIVER_VERSION = '1.0.1';
    
    /**
     * Connect to database
     */
    public function __construct() {
        //---------------------------------------------------------------------------------------------------
        $this->db_handle = Mage::getSingleton('core/resource')->getConnection('core_write');
        //---------------------------------------------------------------------------------------------------
    }

    /**
     * Get database prefix
     * @return string
     */
    public function GetPrefix() {
        //---------------------------------------------------------------------------------------------------
        return Mage::getConfig()->getTablePrefix(); // Store tables prefix
        //---------------------------------------------------------------------------------------------------
    }

    /**
     * Make a SQL query
     * @param string $db_query SQL query
     * @return boolean|Exception
     */
    public function Query($db_query) {
        //---------------------------------------------------------------------------------------------------
        return $this->db_handle->query($db_query); // Store query function
        //---------------------------------------------------------------------------------------------------
    }
    
    /**
     * Make a SQL query and return fetched items array
     * @param string $db_query SQL query
     * @return array|Exception
     */
    public function FetchAll($db_query) {
        //---------------------------------------------------------------------------------------------------
        return $this->db_handle->fetchAll($db_query); // Store query & fetch_array function + list results as array
        //---------------------------------------------------------------------------------------------------
    }
    
    /**
     * Escape SQL query string
     * @param string $query_string SQL query
     * @return type
     */
    public function Escape($query_string) {
        //---------------------------------------------------------------------------------------------------
        //return mysql_escape_string(str_replace("'", "\'", $query_string));
        $query_string = Mage::getSingleton('core/resource')->getConnection('default_write')->quote(str_replace("'", "\'", $query_string));
        $query_string = substr($query_string, 1, count($query_string) - 2);
        return $query_string;
        //---------------------------------------------------------------------------------------------------
    }

    /**
     * Return store last insert ID
     * @return integer
     */
    private function last_insert_id() {
        //---------------------------------------------------------------------------------------------------
        return $this->db_handle->lastInsertId(); // Store SQL last_insert_id
        //---------------------------------------------------------------------------------------------------
    }
    
    /**
     * SQL last insert ID
     * @return integer
     */
    public function GetLastInsertId() {
        //---------------------------------------------------------------------------------------------------
        $last_id = intval($this->last_insert_id()); // Store SQL last_insert_id
        //---------------------------------------------------------------------------------------------------
        if ($last_id > 0 && $last_id != $this->db_last_id) {
            $this->db_last_id = $last_id;
        }
        //---------------------------------------------------------------------------------------------------
        return $this->db_last_id;
        //---------------------------------------------------------------------------------------------------
    }

    /**
     * Get store configuration
     * @return array
     */
    public function GetConfiguration() {
        //---------------------------------------------------------------------------------------------------
        $config = array(
            'access_token'          => Mage::getStoreConfig('orbitvu/api/access_token'),
            'auto_sync'             => $this->make_boolean(Mage::getStoreConfig('orbitvu/synchro/auto_sync')),
            'sync_2d'               => $this->make_boolean(Mage::getStoreConfig('orbitvu/items/sync_2d')),
            'sync_360'              => $this->make_boolean(Mage::getStoreConfig('orbitvu/items/sync_360')),
            'language'              => $this->make_boolean(Mage::getStoreConfig('orbitvu/advanced/language')),
            'auto_sync_sku'         => Mage::getStoreConfig('orbitvu/synchro/auto_sync_sku'),
            'width'                 => Mage::getStoreConfig('orbitvu/layout/width'),  
            'height'                => Mage::getStoreConfig('orbitvu/layout/height'),  
            'border_color'          => Mage::getStoreConfig('orbitvu/layout/border_color'),  
            'img_width'             => Mage::getStoreConfig('orbitvu/layout/img_width'),  
            'img_height'            => Mage::getStoreConfig('orbitvu/layout/img_height'),  
            'img_width_zoom'        => Mage::getStoreConfig('orbitvu/layout/img_width_zoom'),  
            'img_height_zoom'       => Mage::getStoreConfig('orbitvu/layout/img_height_zoom'),
            'scroll'                => Mage::getStoreConfig('orbitvu/layout/scroll'),
            'img_width_tn'          => Mage::getStoreConfig('orbitvu/layout/img_width_tn'),  
            'img_height_tn'         => Mage::getStoreConfig('orbitvu/layout/img_height_tn'),  
            'img_tn_margin'         => Mage::getStoreConfig('orbitvu/layout/img_tn_margin'),
            'img_tn_padding'        => Mage::getStoreConfig('orbitvu/layout/img_tn_padding'),
            'button_width'          => Mage::getStoreConfig('orbitvu/layout/button_width'),  
            'button_height'         => Mage::getStoreConfig('orbitvu/layout/button_height'),  
            'button_opacity'        => Mage::getStoreConfig('orbitvu/layout/button_opacity'),   
            'hover_mode'            => $this->make_boolean(Mage::getStoreConfig('orbitvu/mode/hover_mode')),
            'hover_delay'           => $this->make_boolean(Mage::getStoreConfig('orbitvu/mode/hover_delay')),
            'teaser'                => $this->make_boolean(Mage::getStoreConfig('orbitvu/mode/teaser')),
            'html5'                 => $this->make_boolean(Mage::getStoreConfig('orbitvu/mode/html5')),
            'orbittour_thumbnails'  => Mage::getStoreConfig('orbitvu/mode/orbittour_thumbnails')
        );
        //---------------------------------------------------------------------------------------------------
        return $config;
        //---------------------------------------------------------------------------------------------------
    }      
    
    /**
     * Set store configuration. 
     * Do not append local plugin configuration directly
     * @param string $var Var from configuration
     * @param string $value New vakue
     * @return boolean
     */
    public function SetConfiguration($var, $value) {
        //--------------------------------------------------------------------------------------------------- 
        $config = new Mage_Core_Model_Config();
        $config->saveConfig('orbitvu/api/'.$var, $value, 'default', 0);
        //---------------------------------------------------------------------------------------------------
        return true;
        //---------------------------------------------------------------------------------------------------
    }
    
    /**
     * Get store plugin version
     * @return string
     */
    public function GetVersion() {
        //---------------------------------------------------------------------------------------------------
        return (string) Mage::getConfig()->getNode()->modules->Orbitvu_Sun->version; 
        //---------------------------------------------------------------------------------------------------
    }
    
    /**
     * Get all products with ORM and prepare to synchronize
     * @return array
     */
    public function SynchronizeAllProducts() {
        //-------------------------------------------------------------------------------------------------------
        $storeId    = Mage::app()->getStore()->getId();  
        $product    = Mage::getModel('catalog/product');
        //-------------------------------------------------------------------------------------------------------
        $products = $product->getCollection()
                            ->addAttributeToSelect(array(
                                'name',
                                'sku'
                            ))
                            ->addAttributeToFilter('status', '1');
        //-------------------------------------------------------------------------------------------------------
        $products_array = array();
        foreach ($products as $q) {
            $products_array[] = array(
                'product_id'	=> $q->getId(),
                'product_name'	=> $q->getName(),
                'product_sku'	=> $q->getSku()
            );
        }
        //-------------------------------------------------------------------------------------------------------
        return $products_array;
        //-------------------------------------------------------------------------------------------------------   
    }

    /**
     * Install database tables
     * @return boolean
     */
    public function Install() {
        //-------------------------------------------------------------------------------------------------------
        $prefix = $this->GetPrefix();
        //---------------------------------------------------------------------------------------------------
        $dump = "
    
            INSERT IGNORE INTO `".$prefix."core_config_data` 
                (`scope`, `scope_id`, `path`, `value`) 
            VALUES
                ('default', 0, 'orbitvu/api/access_token', '1567f2b4a02a8bfc5d8aacf0f44b16157e149d29'),
                ('default', 0, 'orbitvu/mode/orbittour_thumbnails', 'right_views'),
                ('default', 0, 'orbitvu/mode/html5', 'yes'),
                ('default', 0, 'orbitvu/mode/hover_delay', '2'),
                ('default', 0, 'orbitvu/mode/teaser', 'autorotate'),
                ('default', 0, 'orbitvu/mode/hover_mode', '1'),
                ('default', 0, 'orbitvu/layout/button_opacity', '1'),
                ('default', 0, 'orbitvu/layout/button_height', '53px'),
                ('default', 0, 'orbitvu/layout/button_width', '30px'),
                ('default', 0, 'orbitvu/layout/scroll', 'yes'),
                ('default', 0, 'orbitvu/layout/img_tn_margin', '3px'),
                ('default', 0, 'orbitvu/layout/img_tn_padding', '2px'),
                ('default', 0, 'orbitvu/layout/img_height_tn', '50px'),
                ('default', 0, 'orbitvu/layout/img_width_tn', '75px'),
                ('default', 0, 'orbitvu/layout/img_height_zoom', '768px'),
                ('default', 0, 'orbitvu/layout/img_width_zoom', '1024px'),
                ('default', 0, 'orbitvu/layout/img_height', '300px'),
                ('default', 0, 'orbitvu/layout/img_width', '583px'),
                ('default', 0, 'orbitvu/layout/border_color', '#ccc'),
                ('default', 0, 'orbitvu/layout/height', '361px'),
                ('default', 0, 'orbitvu/layout/width', '100%'),
                ('default', 0, 'orbitvu/advanced/language', 'en'),
                ('default', 0, 'orbitvu/synchro/auto_sync_sku', 'false'),
                ('default', 0, 'orbitvu/items/sync_orbittour', '1'),
                ('default', 0, 'orbitvu/items/sync_360', '1'),
                ('default', 0, 'orbitvu/items/sync_2d', '1'),
                ('default', 0, 'orbitvu/synchro/auto_sync', '0');
       
            CREATE TABLE IF NOT EXISTS `".$prefix."orbitvu_configuration` (
                `id` int(3) NOT NULL auto_increment,
                  `priority` int(2) NOT NULL,
                  `var` varchar(20) NOT NULL,
                  `value` text NOT NULL,
                  `type` varchar(20) NOT NULL,
                  `info` varchar(200) NOT NULL,
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `var` (`var`)
                ) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

            INSERT IGNORE INTO `".$prefix."orbitvu_configuration` 
                (`var`, `value`, `type`, `info`) 
            VALUES
                ('access_token', '1567f2b4a02a8bfc5d8aacf0f44b16157e149d29', 'api', 'Your Orbitvu SUN Access Token'),
                ('viewers_path', 'viewers/src/', 'main', 'Your viewers upload folder (chmod 777)'),
                ('temp_path', 'tmp/', 'main', 'Your server''s temporary path (chmod 777)'),
                ('using_sun', 'true', 'main', 'Are you using Orbitvu SUN Cloud?'),
                ('last_updated', '2014-07-16 10:29:46', 'main', 'Last Orbitvu SUN Cloud synchronization date'),
                ('last_refreshed', '2014-07-16 10:29:46', 'main', 'Last Orbitvu SUN Cloud refresh date'),
                ('auto_sync', 'false', 'synchro', 'Synchronize presentations automatically?'),
                ('sync_2d', 'true', 'items', 'If 2D photos exists - synchronize them?'),
                ('sync_360', 'true', 'items', 'If 360 exists - synchronize it?'),
                ('sync_orbittour', 'true', 'items', 'If orbittour exists - synchronize it?'),
                ('auto_sync_sku', 'false', 'synchro', 'Automatically sync SKU'),
                ('hover_mode', 'true', 'mode', ''),
                ('width', '100%', 'layout', ''),
                ('height', '361px', 'layout', ''),
                ('border_color', '#ccc', 'layout', ''),
                ('img_width', '583px', 'layout', ''),
                ('img_height', '300px', 'layout', ''),
                ('img_width_zoom', '1024px', 'layout', ''),
                ('img_height_zoom', '768px', 'layout', ''),
                ('scroll', 'yes', 'layout', ''),
                ('img_width_tn', '75px', 'layout', ''),
                ('img_height_tn', '50px', 'layout', ''),
                ('img_tn_margin', '3px', 'layout', ''),
                ('img_tn_padding', '2px', 'layout', ''),
                ('button_width', '30px', 'layout', ''),
                ('button_height', '53px', 'layout', ''),
                ('button_opacity', '1', 'layout', ''),
                ('teaser', 'autorotate', 'mode', ''),
                ('hover_delay', '2', 'mode', ''),
                ('html5', 'yes', 'mode', ''),
                ('orbittour_thumbnails', 'right_views', 'mode', ''),
                ('first_time', 'true', 'main', '');
                
            CREATE TABLE IF NOT EXISTS `".$prefix."orbitvu_log` (
                `id` int(11) NOT NULL auto_increment,
                  `_item_id` int(11) NOT NULL,
                  `_item_table` enum('_presentations','_presentations_items','_viewers') NOT NULL,
                  `action` enum('add','delete','info','skip','update') NOT NULL DEFAULT 'info',
                  `comment` varchar(150) NOT NULL,
                  `date` datetime NOT NULL,
                  `ip` varchar(20) NOT NULL,
                  PRIMARY KEY (`id`), 
                  KEY `_item_id` (`_item_id`)
                ) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

            CREATE TABLE IF NOT EXISTS `".$prefix."orbitvu_products_presentations` (
                `id` int(11) NOT NULL auto_increment,
                  `product_id` int(11) NOT NULL,
                  `orbitvu_id` int(11) NOT NULL,
                  `name` varchar(150) NOT NULL,
                  `config` text NOT NULL,
                  `viewer` int(2) NOT NULL,
                  `type` enum('sun','local') NOT NULL,
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `product_id` (`product_id`), 
                  KEY `orbitvu_id` (`orbitvu_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

            CREATE TABLE IF NOT EXISTS `".$prefix."orbitvu_products_presentations_history` (
                `id` int(11) NOT NULL auto_increment,
                  `product_id` int(11) NOT NULL,
                  `orbitvu_id` int(11) NOT NULL,
                  `unlink_date` datetime NOT NULL,
                  PRIMARY KEY (`id`), 
                  KEY `product_id` (`product_id`,`orbitvu_id`), 
                  KEY `orbitvu_id` (`orbitvu_id`)
                ) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

            CREATE TABLE IF NOT EXISTS `".$prefix."orbitvu_products_presentations_items` (
                `id` int(11) NOT NULL auto_increment,
                  `_presentations_id` int(11) NOT NULL,
                  `orbitvu_id` int(11) NOT NULL,
                  `priority` int(3) NOT NULL,
                  `name` varchar(150) NOT NULL,
                  `type` tinyint(1) NOT NULL,
                  `thumbnail` varchar(200) NOT NULL,
                  `path` varchar(200) NOT NULL,
                  `config` text NOT NULL,
                  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
                  PRIMARY KEY (`id`), 
                  KEY `presentation_id` (`_presentations_id`,`orbitvu_id`)
                ) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

            CREATE TABLE IF NOT EXISTS `".$prefix."orbitvu_products_thumbnails` (
                `product_id` int(11) NOT NULL,
                `thumbnail` varchar(200) NOT NULL,
                PRIMARY KEY (`product_id`)
              ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ";
        
        $dump = explode(';', $dump);
        
        foreach ($dump as $query) {
            try {
                $this->Query($query);
            }
            catch (Exception $e) {}
        }
        //---------------------------------------------------------------------------------------------------
        return true;
        //---------------------------------------------------------------------------------------------------
    }
    
    /**
     * Make boolean from integers
     * @param integer $value
     * @return boolean|string
     */
    private function make_boolean($value) {
        //---------------------------------------------------------------------------------------------------
        return str_replace(array('1', '0'), array('true', 'false'), $value);
        //---------------------------------------------------------------------------------------------------
    }
    
}
?>