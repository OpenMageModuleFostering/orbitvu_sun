<?php
/**
 * Orbitvu PHP  Orbitvu eCommerce SUN connection
 * @Copyright: Orbitvu Sp. z o.o. is the owner of full rights to this code
 */

class Orbitvu {

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

    /*
     * The constructor. Set access_token to this instance
     */
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
     * Check if we are connected
     * Also, get updates info if available
     * @return boolean
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
        if ($this->access_token == $this->GetDemoAccessToken()) {
            //-------------------------------------------------------------------------------------------------------
            return true;
            //-------------------------------------------------------------------------------------------------------
        }
        //-------------------------------------------------------------------------------------------------------
        return false;
        //-------------------------------------------------------------------------------------------------------
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
     * Want to change access_token for current session? No problem
     * @param string $access_token New access token
     */
    public function SetAccessToken($access_token) {
        //---------------------------------------------------------------------------------------------------
        $this->access_token = $access_token;
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
     * @return json|strong
     */
    public function CallAPI($call, $parameters = '', $method_post = false, $get_headers = false, $not_change = false) {
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
        
        $url .= $parameters;

        /*
         * Set the access token
         */
        $header[] = "Authorization: Token ".$this->access_token;
        $header[] = "Accept-User-Agent: 1.0.1";
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
     * @return stdClass
     */
    public function CallSUN($call, $parameters = '', $method_post = false, $get_headers = false, $not_change = false) {
        //---------------------------------------------------------------------------------------------------
        $page = json_decode($this->CallAPI($call, $parameters, $method_post, $get_headers, $not_change));

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
                        '//orbitvu.com'			=>	'https://orbitvu.com',
                        'orbitvu.com'			=> 	'orbitvu.com',
                        'orbitvu//com'			=> 	'orbitvu//com'
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