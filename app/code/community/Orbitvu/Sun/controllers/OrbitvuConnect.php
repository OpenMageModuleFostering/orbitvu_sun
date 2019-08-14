<?php
/**
 * Orbitvu PHP  Orbitvu eCommerce SUN connector
 * @copyright Orbitvu Sp. z o.o. is the owner of full rights to this code
 * @license Commercial
 */

final class Orbitvu {

    /**
     * License Key
     * @var string
     */
    private $access_token;
    
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
     * Curl version
     * @var string
     */
    private $curl_version;
    
    /**
     * New available version of plugin
     * @var string
     */
    private $plugin_available_version;
    
    /**
     * License end date
     * @var date Y-m-d
     */
    private $plugin_support;
    
    /**
     * Default DEMO access token
     * Available to change (if updated)
     * @var string
     */
    private $demo_access_token = '1567f2b4a02a8bfc5d8aacf0f44b16157e149d29';
    
    /**
     * Local presentations count
     * @var integer
     */
    private $presentations_count = 0;

    /**
     * Viewers path
     * @fixme delete if not used
     * @var string
     */
    public $ViewerPath = 'viewers/src/';
    
    /**
     * Temporary path for uploads
     * @var string
     */
    public $DownloadPath = 'tmp/';
    
    /**
     * Temporary path for uploads
     * @var string
     */
    public $PresentationsPath = 'orbitvu_presentations/';

    /**
     * Let's do the magic
     * @param string $access_token License Key
     * @param boolean $debug Debug mode
     * @param boolean $test Unit tests mode
     * @param boolean $testinstance Unit tests instance.
     */
    public function __construct($access_token, $debug = false, $test = false, $testinstance = false) {
        //---------------------------------------------------------------------------------------------------
        $this->access_token = $access_token;
        $this->debug = $debug;
        $this->test = $test;
        //---------------------------------------------------------------------------------------------------

        /*
         * Test instance
         */
        //---------------------------------------------------------------------------------------------------
        if ($this->test) {
            $this->testclass = $testinstance;

            /*
             * Run test bot!
             */
            $this->run_tests();
        }
        //---------------------------------------------------------------------------------------------------

        //----------------------------------------------------------
        /**/	if ($this->debug || $this->test) {
        /**/		error_reporting(E_ALL & ~E_NOTICE);
        /**/		ini_set('display_errors', '1');
        /**/	}
        //----------------------------------------------------------
    }
    
    /**
     * Set the CURL version
     * @param string $v CURL version
     */
    public function IntroduceYourself($v) {
        //-------------------------------------------------------------------------------------------------------
        $this->curl_version = $v;
        //-------------------------------------------------------------------------------------------------------
    }
    
    /**
     * Get the demo access token
     * @return string
     */
    public function GetDemoAccessToken() {
        //-------------------------------------------------------------------------------------------------------
        return $this->demo_access_token;
        //-------------------------------------------------------------------------------------------------------
    }

    /**
     * Check if current license is demo
     * @return boolean
     */
    public function IsDemo() {
        //-------------------------------------------------------------------------------------------------------
        if ($this->access_token == $this->GetDemoAccessToken() && $this->IsConnected()) {
            //-------------------------------------------------------------------------------------------------------
            return true;
            //-------------------------------------------------------------------------------------------------------
        }
        //-------------------------------------------------------------------------------------------------------
        return false;
        //-------------------------------------------------------------------------------------------------------
    }

    /**
     * Want to change access_token for current session? No problem
     * @param string $access_token New access token
     */
    public function SetAccessToken($access_token) {
        //---------------------------------------------------------------------------------------------------
        $this->access_token = $access_token;
        //---------------------------------------------------------------------------------------------------
    }
    
    /**
     * Get current License Key
     * @return string
     */
    public function GetAccessToken() {
        //---------------------------------------------------------------------------------------------------
        return $this->access_token;
        //---------------------------------------------------------------------------------------------------
    }

    /**
     * Unit tests results
     * @return array
     */
    public function GetTestsResults() {
        //---------------------------------------------------------------------------------------------------
        return $this->testclass->GetResults();
        //---------------------------------------------------------------------------------------------------
    }

    /**
     * Orbitvu SUN API calling function
     * @param string $call API path to call
     * @param array|string $parameters Additional parameters to call
     * @param boolean $method_post Use POST method
     * @param boolean $get_headers Get HTTP headers
     * @param boolean $not_change Disable automatic call formatting
     * @param boolean $no_query_string Enable if sending sensitive data via POST
     * @return json|strong
     */
    public function CallAPI($call, $parameters = '', $method_post = false, $get_headers = false, $not_change = false, $no_query_string = false) {
        //---------------------------------------------------------------------------------------------------
        /*
         * Add URL parameters from array to URL
         */
        // presentation url auto status
        if (stristr($call, 'presentations') && !stristr($call, 'status=')) {
            if (!is_array($parameters)) {
                $parameters = array('status' => '1');
            }
            else if (!isset($parameters['status'])) {
                $parameters['status'] = '1';
            }
        }
        
        /*
         * POST
         */
        if ($method_post && !$not_change) {
            $parameters['_method'] = 'PATCH';
        }
        
        /*
         * Add URL parameters from array to URL
         */
        if (is_array($parameters)) {
            $parameters_array = $parameters;
            //---------------------------------------------------------------------------------------------------
            $keys = array_keys($parameters);
            $vals = array_values($parameters);

            $parameters = '';
            //---------------------------------------------------------------------------------------------------
            for ($i = 0, $n = count($keys); $i < $n; $i++) {
                if ($i == 0) $parameters .= '?';
                else $parameters .= '&';

                $parameters .= $keys[$i].'='.$vals[$i];
            }
            //---------------------------------------------------------------------------------------------------
        }

        //----------------------------------------------------------
        if ($this->test) {
            $this->testclass->AppendTest('api_call['.$call.']', array('type' => 'function_replace'), array('file' => __FILE__, 'line' => __LINE__, 'function' => __FUNCTION__));
            return $this->testclass->CallAPI($call, $parameters);
        }
        //----------------------------------------------------------

        /*
         * Make both ways calling option
         * but return json as possible
         */
        if (stristr($call, '//')) {
            $url = $this->clean_url($call);
        }
        else {
            $url = 'https://orbitvu.co/api/ecommerce/'.trim(str_replace(array('.html', '.json'), '', $call), '/').($method_post && !$not_change ? '' : '/').'.json';
        }
        
        /**
         * If query string is on, attach parameters to query also,
         * even if POST or PUT method
         */
        if (!$no_query_string) {
            $url .= $parameters;
        }

        /*
         * Set the access token
         */
        $header[] = "Authorization: Token ".$this->access_token;
        $header[] = "Accept-User-Agent: ".OrbitvuDatabaseDriver::DRIVER_VERSION;
        $header[] = "Accept-User-Agent-Version: ".$this->curl_version;

        //----------------------------------------------------------
        /**/		$this->return_debug(array(
        /**/			'function'      => __FUNCTION__,
        /**/			'url'           => $url,
        /**/			'headers'       => $header,
        /**/                    'parameters'    => $parameters_array,
        /**/                    'post'          => ($method_post ? 'true' : 'false')
        /**/		));
        //----------------------------------------------------------

        /*
         * Call SUN with CURL
         * Method: GET
         * + headers
         */
        $c = curl_init();
        curl_setopt($c, CURLOPT_URL, $url);
        curl_setopt($c, CURLOPT_HTTPHEADER, $header);
        curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
        if ($method_post) {
            curl_setopt($c, CURLOPT_POST, count($parameters_array));
            curl_setopt($c, CURLOPT_POSTFIELDS, $parameters_array);
        }
        if ($get_headers) {
            curl_setopt($c, CURLOPT_VERBOSE, 1);
            curl_setopt($c, CURLOPT_HEADER, 1);
        }
        $page = curl_exec($c);
        
        
        if ($get_headers) {
            //---------------------------------------------------------------------------------------------------
            $header_size = curl_getinfo($c, CURLINFO_HEADER_SIZE);
            $header = substr($page, 0, $header_size);
            $body = substr($page, $header_size);
            //---------------------------------------------------------------------------------------------------
            if (!stristr($header, '200 OK')) {
                $body = json_decode($body);
                $error = explode("\n", $header);
                $error = explode(' ', $error[0]);
                $error = $error[1];
                $body->error = $error;
                $body->header = $header;
                $body = json_encode($body);
            }
            //---------------------------------------------------------------------------------------------------
            $page = $body;
            //---------------------------------------------------------------------------------------------------
        }

        /*
         * Return SUN original response
         */
        //---------------------------------------------------------------------------------------------------
        curl_close($c);
        
        return $page;
        //---------------------------------------------------------------------------------------------------
    }

    /**
     * Orbitvu SUN API calling alias to CallAPI function.
     * Returns decoded json.
     * @param string $call API path to call
     * @param array|string $parameters Additional parameters to call
     * @param boolean $method_post Use POST method
     * @param boolean $get_headers Get HTTP headers
     * @param boolean $not_change Disable automatic call formatting
     * @param boolean $no_query_string Enable if sending sensitive data via POST
     * @return stdClass
     */
    public function CallSUN($call, $parameters = '', $method_post = false, $get_headers = false, $not_change = false, $no_query_string = false) {
        //---------------------------------------------------------------------------------------------------
        $page = json_decode($this->CallAPI($call, $parameters, $method_post, $get_headers, $not_change, $no_query_string));

        //----------------------------------------------------------
        /**/		$this->return_debug(array(
        /**/			'function' => __FUNCTION__,
        /**/			'response' => $page
        /**/		));			
        //----------------------------------------------------------

        //---------------------------------------------------------------------------------------------------
        return $page;
        //---------------------------------------------------------------------------------------------------
    }

    /**
     * Check if we are connected
     * Also, get updates info if available
     * @return boolean
     * @fixme new licenses with Maciej
     */
    public function IsConnected() {
        //---------------------------------------------------------------------------------------------------
        $get = $this->CallSUN('plugins/versions/latest', '', false, true);
        //-------------------------------------------------------------------------------------------------------
        $this->plugin_available_version = $get->version;
        $this->plugin_support = $get->support;

        if ($get->error) {
            return false;            
        }
        //-------------------------------------------------------------------------------------------------------
        return true;
        //---------------------------------------------------------------------------------------------------
    }
    
    /**
     * Check for available updates
     * @return stdClass|boolean
     */
    public function CheckForUpdates() {
        //-------------------------------------------------------------------------------------------------------
        if ($this->curl_version != $this->plugin_available_version) {
            //-------------------------------------------------------------------------------------------------------
            $ret = new stdClass();
            $ret->version = $this->curl_version;
            $ret->new_version = $this->plugin_available_version;
            //-------------------------------------------------------------------------------------------------------
            return $ret;
            //-------------------------------------------------------------------------------------------------------
        }
        //-------------------------------------------------------------------------------------------------------
        return false;
        //-------------------------------------------------------------------------------------------------------
    }
    
    /**
     * License days counter
     * @return integer|string
     */
    public function GetSupportDays() {
        //-------------------------------------------------------------------------------------------------------
        if (!is_null($this->plugin_support)) {
            //-------------------------------------------------------------------------------------------------------
            $ret = new stdClass();
            $ret->days = $this->count_days($this->plugin_support, date('Y-m-d'));
            $ret->date = $this->plugin_support;
            //-------------------------------------------------------------------------------------------------------
        }
        //-------------------------------------------------------------------------------------------------------
        return 'n/a';
        //-------------------------------------------------------------------------------------------------------
    }
    
    /**
     * Register an account
     * @param string $email Email to register
     * @return stdClass
     */
    public function CreateAccount($email) {
        //---------------------------------------------------------------------------------------------------
        $reg_token = $this->CallSUN('regtoken', '', true, true, true);
        //---------------------------------------------------------------------------------------------------
        if (is_null($reg_token->token)) {
            //---------------------------------------------------------------------------------------------------
            throw new Exception('Orbitvu: cannot get a token');
            //---------------------------------------------------------------------------------------------------
        }
        //---------------------------------------------------------------------------------------------------
        $reg = $this->CallSUN('reguser', array(
            'token' => $reg_token->token,
            'email' => $email
        ), true, true, true);
        //---------------------------------------------------------------------------------------------------
        if ($reg->email[0]) {
            //---------------------------------------------------------------------------------------------------
            throw new Exception($reg->email[0]);
            //---------------------------------------------------------------------------------------------------
        }
        //---------------------------------------------------------------------------------------------------
        $reg->email = $email;
        
        //----------------------------------------------------------
        /**/		$this->return_debug(array(
        /**/			'function'      => __FUNCTION__,
        /**/			'response'      => $reg
        /**/		));
        //----------------------------------------------------------
       
        //---------------------------------------------------------------------------------------------------
        return $reg;
    }
    
    /**
     * Gets a client License Key (Access Token) from Orbitvu SUN account after log in
     * @param type $user_name SUN user name
     * @param type $password SUN user password
     * @return boolean|string License Key or FALSE
     */
    public function LogInSun($user_name, $password) {
        //---------------------------------------------------------------------------------------------------
        $request = $this->CallSUN('get_api_key', array(
            'username'  => $user_name,
            'password'  => $password
        ), true, false, true, true);
        
        if (!is_null($request->key) && $request->key != '') {
            return $request->key;
        }
        
        return false;
        //---------------------------------------------------------------------------------------------------
    }
    
    /**
     * Checks if access token from SUN @see LogInSun
     * is the same as local access token
     * @param type $sun_access_token SUN access token
     * @return boolean
     */
    public function CheckAccessToken($sun_access_token) {
        //---------------------------------------------------------------------------------------------------
        if ($sun_access_token == $this->GetAccessToken() && $this->IsConnected()) {
            return true;
        }
        
        return false;
        //---------------------------------------------------------------------------------------------------
    }
    
    /*
     * Viewer downloader
     */
    public function DownloadViewer($viewer_url) {

        $viewer_url = str_ireplace('.html', '', $viewer_url);

        //----------------------------------------------------------
        if ($this->test) {
                $this->testclass->AppendTest('download_viewer_start['.$viewer_url.']', array('type' => 'function_check'), array('file' => __FILE__, 'line' => __LINE__, 'function' => __FUNCTION__));
        }
        //----------------------------------------------------------

        //----------------------------------------------------------
        /**/		$this->return_debug(array(
        /**/				'function' => __FUNCTION__,
        /**/				'viewer_url' => $viewer_url
        /**/		));
        //----------------------------------------------------------

        /*
         * Download the viewer
         */
        $file = $this->CallAPI($viewer_url);

        //----------------------------------------------------------
        /**/		$this->return_debug(array(
        /**/				'function' => __FUNCTION__,
        /**/				'file' => (strlen($file) > 100 ? '[ZIP_FILE]' : '')
        /**/		));
        //----------------------------------------------------------

        /*
         * Try to put the content to the zip file
         */
        $temp_dir = __DIR__.'/'.$this->DownloadPath;
        $temp_file = __DIR__.'/'.$this->DownloadPath.'temp.zip';

        if (file_put_contents($temp_file, $file)) {
            /*
             * Check if upload was successfull
             */
            if (file_exists($temp_file)) {
                //----------------------------------------------------------
                if ($this->test) {
                        $this->testclass->UpdateTest('download_viewer_start['.$viewer_url.']', array('file' => __FILE__, 'line' => __LINE__, 'function' => __FUNCTION__), 'ok');
                }
                //----------------------------------------------------------

                /*
                 * Return file path
                 */
                return $temp_file;
            }
            else {
                //----------------------------------------------------------
                if ($this->test) {
                        $this->testclass->UpdateTest('download_viewer_start['.$viewer_url.']', array('file' => __FILE__, 'line' => __LINE__, 'function' => __FUNCTION__), 'failed');
                }
                //----------------------------------------------------------

                //---------------------------------------------------------------------------------------------------
                throw new Exception('Orbitvu: cannot download viewer file to '.$temp_dir);
                //---------------------------------------------------------------------------------------------------
            }
        }
        else {
            //----------------------------------------------------------
            if ($this->test) {
                    $this->testclass->UpdateTest('download_viewer_start['.$viewer_url.']', array('file' => __FILE__, 'line' => __LINE__, 'function' => __FUNCTION__), 'failed');
            }
            //----------------------------------------------------------

            //---------------------------------------------------------------------------------------------------
            throw new Exception('Orbitvu: cannot download viewer file. Set "write" permissions to '.$temp_dir);
            //---------------------------------------------------------------------------------------------------
        }
    }
    
    /**
     * Install presentation from file
     * 
     * Check if file is .ovus (Orbitvu Sequence) or .zip package.
     * If so, unpack archive and refresh page
     * 
     * Console like installator, needs to be working with any e-commerce platform/CMS),
     * that's why:
     * - I didn't used templates,
     * - I used exit() to stop rendering page by any platform we use,
     * - I used JavaScript redirect as this will be run in a browser, but one archive at the time 
     * (a way to trick every server limits)
     * 
     * @param string $file Presentation archive file (*.ovus, *.zip)
     * @param boolean $print_output Print console-like output messages
     * @return string
     */
    public function InstallPresentation($file, $print_output = true) {
        
        $dir_or_file = $file;
        $dir_name = explode('/', $dir_or_file);
        $dir_name = $dir_name[count($dir_name)-1];
        
        $is_sun = false;
        //-------------------------------------------------------------------------------------------------------
        if ($print_output) {
            echo '<div style="margin: 20px 10% 20px 10%; font: 14px Arial;"><strong>Orbitvu - Presentations Installer</strong><br />
                You will be redirected to your store automatically after installation.<br /><br /><pre style="font-size: 12px;">';

            echo '['.date('Y-m-d H:i:s').'] ';
        }
        //-------------------------------------------------------------------------------------------------------

        /**
         * Installing
         */
        //-------------------------------------------------------------------------------------------------------
        if (stristr($dir_name, '.ovus')) {
            $ext = '.ovus';
        }
        else {
            $ext = '.zip';
        }
        //-------------------------------------------------------------------------------------------------------
        
        if ($print_output) {
            echo 'Installing '.$ext.' presentation "'.$dir_name.'"...';
        }

        /**
         * Try to unpack the archive
         */
        try {
            $new_dir = $this->PresentationsPath.str_ireplace($ext, '', $dir_name);

            $this->Unzip($dir_or_file, $new_dir);

            /**
             * Is the archive from Orbitvu SUN?
             */
            if (file_exists($new_dir.'/orbitvu12')) {
                $is_sun = true;

                /**
                 * Move presentations to parent folder
                 */
                $tree = $this->GetDirectoryTree($new_dir);
                foreach ($tree as $current_inode) {

                    /**
                     * Make sure we didn't move the viewer from the package and any .html presentations
                     * We will not use these
                     */
                    if (!stristr($current_inode, 'orbitvu12') && !stristr($current_inode, '.html')) {

                        $current_name = explode('/', $current_inode);
                        $current_name = $current_name[count($current_name)-1];

                        rename($current_inode, $this->PresentationsPath.$current_name);
                    }
                }

                /**
                 * Delete unpacked folder 
                 */
                $this->rrmdir($new_dir);
            }

            if ($print_output) {
                echo ' OK<script type="text/javascript">window.location.reload();</script>'."\n";
            }
            else {
                return true;
            }
        }
        catch (Exception $e) {
            if ($print_output) {
                echo '<span style="color: red;font-weight: bold;"> FAILED</span><script type="text/javascript">alert(\'Installation failed!\');</script>'."\n";
                echo $e->getMessage();
            }
            else {
                return false;
            }
            
        }

        if ($print_output) {
            echo '</pre></div>';

            exit();
        }
        
    }
    
    /**
     * Install Viewer if $viewer_type is available
     * @param string $viewer_type Viewer type name
     * @return boolean
     * @throws Exception
     */
    public function InstallViewer($viewer_type = 'BASIC') {
        
        $viewers = $this->CallSun('viewers/licenses');
            
        foreach ($viewers as $viewer) {
            
            if ($viewer->type == $viewer_type) {
                
                /**
                 * Temporary file name
                 */
                $file_name = 'viewer.zip';
                
                /**
                 * Download Viewer
                 */
                $viewer = $this->CallAPI($this->clean_url($viewer->download), '', false, false, true);

                if (file_put_contents($this->ViewerPath.$file_name, $viewer)) {
                    
                    /**
                     * Unzip the package
                     */
                    
                    $this->Unzip($this->ViewerPath.$file_name, $this->ViewerPath);
                    
                    /**
                     * Delete temporary file
                     */
                    unlink($this->ViewerPath.$file_name);
                    
                    return true;
                }
                else {
                    throw new Exception('Orbitvu: cannot download Orbitvu Viewer. Set "write" permissions to directory '.$this->ViewerPath.'. If permissions are correct, contact Orbitvu dev@orbitvu.com, as it\'s can be Orbitvu Sun connection issue.');
                }
            }
        }
            
    }

    /*
     * Unzip
     * Require ZipArchive (PHP standard from 5.2.0)
     */
    public function Unzip($file, $path = null) {

        if ($path == null) {
            $path = $this->ViewerPath;
        }
        
        //----------------------------------------------------------
        if ($this->test) {
            $this->testclass->AppendTest('unzip['.$file.']', array('type' => 'function_check'), array('file' => __FILE__, 'line' => __LINE__, 'function' => __FUNCTION__));
        }
        //----------------------------------------------------------
        /*
         * Check if user have ZipArchive class installed
         */
        if (!class_exists('ZipArchive')) {
            //----------------------------------------------------------
            if ($this->test) {
                $this->testclass->UpdateTest('unzip['.$file.']', array('file' => __FILE__, 'line' => __LINE__, 'function' => __FUNCTION__), 'failed');
            }
            //----------------------------------------------------------

            //---------------------------------------------------------------------------------------------------
            throw new Exception('Orbitvu: PHP extension ZipArchive doesn\'t exist. Upgrade your PHP version to >= 5.2.0 or install ZipArchive: php.net/ZipArchive or unzip file manually');
            //---------------------------------------------------------------------------------------------------
            return false;
        }

        /*
         * Unpacking the archive
         */
        $zip = new ZipArchive();
        //---------------------------------------------------------------------------------------------------
        if ($res = $zip->open($file)) {

            if ($res === true) {
                //---------------------------------------------------------------------------------------------------
                /*
                 * Extract
                 */
                $zip->extractTo($path);
                $zip->close();

                /*
                 * Delete the temp .zip file
                 */
                unlink($file);
                
                if (file_exists($file)) {
                    throw new Exception('Orbitvu: cannot extract the file. Set "write" permissions to dir '.$path.' and to the parent directory.');
                }

                //----------------------------------------------------------
                if ($this->test) {
                    $this->testclass->UpdateTest('unzip['.$file.']', array('file' => __FILE__, 'line' => __LINE__, 'function' => __FUNCTION__), 'ok');
                }
                //----------------------------------------------------------

                //---------------------------------------------------------------------------------------------------
                return true;
                //---------------------------------------------------------------------------------------------------
            }
            else {
                //----------------------------------------------------------
                if ($this->test) {
                    $this->testclass->UpdateTest('unzip['.$file.']', array('file' => __FILE__, 'line' => __LINE__, 'function' => __FUNCTION__), 'failed');
                }
                //----------------------------------------------------------

                //---------------------------------------------------------------------------------------------------
                throw new Exception('Orbitvu: cannot extract the file. Not valid file format or directory '.$path.' not found. Create this directory and set "write" permissions');
                //---------------------------------------------------------------------------------------------------
            }
        }
        else {
            //----------------------------------------------------------
            if ($this->test) {
                    $this->testclass->UpdateTest('unzip['.$file.']', array('file' => __FILE__, 'line' => __LINE__, 'function' => __FUNCTION__), 'failed');
            }
            //----------------------------------------------------------

            //---------------------------------------------------------------------------------------------------
            throw new Exception('Orbitvu: cannot extract the file. Set "write" permissions to '.$path);
            //---------------------------------------------------------------------------------------------------
        }
    }
    
    /**
     * Lists all directories
     * @param type $dir
     * @param type $no_basefiles
     * @return array
     */
    public static function GetDirectoryTree($dir, $no_basefiles = true, $per_page = 0, $page = 0) {
        //--------------------------------------------------------------------------------------------------------------------  
        if ($page > 0) {
            $page--;
        }   
        //-------------------------------------------------------------------------------------------------------
        $files = array();
        
        $i = 0;
        $j = 0;
        foreach (new DirectoryIterator($dir) as $current_file) {
            //-------------------------------------------------------------------------------------------------------
            if ($current_file->isDot() && $no_basefiles) {
                continue;
            }
            //-------------------------------------------------------------------------------------------------------
            if ($page > 0) {
                $j++;
                
                if ($j <= $per_page * $page) {
                    $i = 0;
                }
            }
            //-------------------------------------------------------------------------------------------------------
            if ($i == $per_page && $per_page > 0) {
                break;
            }
            //-------------------------------------------------------------------------------------------------------
            if ($page == 0 || ($page > 0 && $j > $per_page * $page)) {
                $files[] = $current_file->getPathname();
            }
            //-------------------------------------------------------------------------------------------------------
            $i++;
        }
        
        return $files;
        //--------------------------------------------------------------------------------------------------------------------  
    }
    
    /**
     * Counts presentations
     * @param string $dir
     * @return integer
     */
    public function GetPresentationsCount($dir) {
        //--------------------------------------------------------------------------------------------------------------------
        if ($this->presentations_count == 0) {
            $pres = self::GetDirectoryTree($dir);
            
            $this->presentations_count = count($pres);
        }   
        
        return intval($this->presentations_count); 
        //--------------------------------------------------------------------------------------------------------------------
    }
    
    /**
     * Converts XML content to multi-dimensional array
     * @param type $xml_content
     * @return array
     * @throws Exception
     */
    public static function XMLtoArray($xml_content) {
        //-------------------------------------------------------------------------------------------------------
        /*
         * Check if user have ZipArchive class installed
         */
        if (!class_exists('SimpleXMLElement')) {
            //---------------------------------------------------------------------------------------------------
            throw new Exception('Orbitvu: PHP extension SimpleXMLElement doesn\'t exist. Upgrade your PHP version to >= 5.0.1');
            //---------------------------------------------------------------------------------------------------
        }
        
        $xml = new SimpleXMLElement($xml_content);
        
        $xml_array = unserialize(serialize(json_decode(json_encode((array) $xml), 1)));
        
        return $xml_array;
        //-------------------------------------------------------------------------------------------------------
    }
    
    /**
     * Remove directory recursively
     * @param string $dirPath Directory to delete (could be non empty)
     */
    private function rrmdir($dirPath) {
        if (!is_dir($dirPath)) {
            throw new InvalidArgumentException($dirPath.' must be a directory');
        }
        
        if (substr($dirPath, strlen($dirPath) - 1, 1) != '/') {
            $dirPath .= '/';
        }
        
        $files = glob($dirPath . '*', GLOB_MARK);
        foreach ($files as $file) {
            if (is_dir($file)) {
                $this->rrmdir($file);
            } else {
                unlink($file);
            }
        }
        
        rmdir($dirPath);
    }
    
    /**
     * Cleans API URL with http / https dependences
     * @param string $url Url to clean
     * @return string
     */
    private function clean_url($url) {	
        //----------------------------------------------------------
        if ($this->test) {
            $this->testclass->AppendTest('clean_url['.$url.']', array('type' => 'function_check'), array('file' => __FILE__, 'line' => __LINE__, 'function' => __FUNCTION__));
        }
        //---------------------------------------------------------------------------------------------------
        if (stristr($url, 'https://')) {
            return $url;
        }
        else if (stristr($url, 'http://')) {
            return str_ireplace('http://', 'https://', $url);
        }
        else if (stristr($url, '//') && stristr($url, '.')) {
            return str_ireplace('//', 'https://', $url);
        }
        //---------------------------------------------------------------------------------------------------
        return $url;
        //---------------------------------------------------------------------------------------------------
    }
    
    /**
     * Count days between two dates
     * @param date $date1 Date
     * @param date $date2 Date
     * @return integer
     */
    private function count_days($date1, $date2) {
        //-------------------------------------------------------------------------------------------------------
        $y1 = date('Y', strtotime($date1));
        $m1 = date('m', strtotime($date1));
        $d1 = date('d', strtotime($date1));
        
        $y2 = date('Y', strtotime($date2));
        $m2 = date('m', strtotime($date2));
        $d2 = date('d', strtotime($date2));

        $date1_set = mktime(0, 0, 0, $m1, $d1, $y1);
        $date2_set = mktime(0, 0, 0, $m2, $d2, $y2);
        //-------------------------------------------------------------------------------------------------------
        return (round(($date2_set - $date1_set) / (60 * 60 * 24)));
        //-------------------------------------------------------------------------------------------------------
    }

    /**
     * Run unit tests
     * @todo Add tests for new functions
     */
    private function run_tests() {
        //-----------------------------------------------------------------------------------------------------
        /*
         * Basic functions
         */
        //-----------------------------------------------------------------------------------------------------
        $tests_urls = array(
                        'http://orbitvu.com'	=>	'https://orbitvu.com',
                        'https://orbitvu.com'	=>	'https://orbitvu.com',
                        '//orbitvu.com'		=>	'https://orbitvu.com',
                        'orbitvu.com'		=> 	'orbitvu.com',
                        'orbitvu//com'		=> 	'orbitvu//com'
        );
        $keys = array_keys($tests_urls);
        $vals = array_values($tests_urls);
        //-----------------------------------------------------------------------------------------------------
        for ($i = 0, $n = count($tests_urls); $i < $n; $i++) {

            $clean = $this->clean_url($keys[$i]);

            $tst = array(
            /**/			'function' => 'clean_url('.$keys[$i].')',
            /**/			'given' => $clean,
            /**/			'expected' => $vals[$i]
            );

            if ($clean == $vals[$i]) {
                    $result = 'ok';
            }
            else {
                    $result = 'fail';
            }

            $this->testclass->UpdateTest('clean_url['.$keys[$i].']', array('file' => __FILE__, 'line' => __LINE__, 'function' => __FUNCTION__), $result, $tst);
        }

        //-----------------------------------------------------------------------------------------------------
        /*
         * Presentations display
         */
        //-----------------------------------------------------------------------------------------------------
        $call = 'presentations';

        $response = $this->CallSUN($call);

        $status = 'ok';
        $status = 'fail';
        //---------------------------------------------------------------------------------------------------
        $this->UpdateTest('api_call['.$call.']', array('file' => __FILE__, 'line' => __LINE__, 'function' => __FUNCTION__), 'ok');
        //---------------------------------------------------------------------------------------------------
    }

    /**
     * Debugger. Returns debug output if debug mode is enabled
     * @param array $params
     * @return string
     */
    private function return_debug($params) {
        //---------------------------------------------------------------------------------------------------
        if ($this->debug) {
            return OrbitvuDebugger::Debug($params);
        }
        //---------------------------------------------------------------------------------------------------
    }	
    
}

?>