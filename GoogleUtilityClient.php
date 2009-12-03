<?php
/*
 * GoogleUtilityClient
 *
 * Copyright(c) 2009 Adam Ballai <aballai@gmail.com>
 */
    
class GoogleUtilityClientException extends Exception {}

class GoogleUtilityClient {
    public $domain;
    
    private static $curl = null;

    private $authenticated = false;
    private $auth_token;
    private $api_key;
    private $api_secret;
    private $account_type;
    
    private $memcache;
    
    public function __construct($api_key,
                                $api_secret,
                                $account_type = 'HOSTED',
                                $cache_config = NULL)
    {

        $this->api_key = $api_key;
        $this->api_secret = $api_secret;
        $this->account_type = $account_type;
        $keys = explode('@', $this->api_key);
		if(count($keys) == 2) {
			$this->domain = $keys[1];
		}
		
		if(empty($this->domain)) {
			throw new GoogleUtilityClientException('Invalid api_key');
		}
		
        if(!empty($cache_config)) {
            $this->setup_memcache($cache_config['memcache_servers'],
                                  $cache_config['memcache_port'],
                                  $cache_config['key_prefix']);
        }

    }

    public function setup_memcache($memcache_servers, $memcache_port, $key_prefix) {
        $this->memcache = new Memcache();
        foreach ($memcache_servers as $memcache_server) {
            $this->memcache->addServer($memcache_server, $memcache_port);
        }
        $this->key_prefix = $key_prefix;
    }


    public function build_key($url, $req_per_hour=1) {
        $stamp = intval(time() * ($req_per_hour / 3600));
        return $this->key_prefix . ':' . $stamp . ':' . $url;
    }

    function fetch($url, $method, $args, $req_per_hour=1) {
        if(!$this->memcache) {
            return $this->perform_request($url, $method, $args);
        }
        
        $key = $this->build_key($url, $req_per_hour);
        $value = $this->memcache->get($key);
        if (!$value) {
            $value = $this->perform_request($url, $method, $args);
            $value = json_encode($value);
            $this->memcache->set($key, $value);
        }
        if (!$value) return null;
        return json_decode($value, true);
    }

    public function perform_request($url, $method, $args) {
        $method = strtoupper($method);
        switch($method)
        {
            case 'GET':
                break;
            case 'UPDATE':
            case 'DELETE':
            case 'PUT':
                curl_setopt(self::$curl, CURLOPT_CUSTOMREQUEST, $method);
                break;
            case 'POST':
                curl_setopt(self::$curl, CURLOPT_POSTFIELDS, http_build_query($args));
                curl_setopt(self::$curl, CURLOPT_POST, true);
                break;
        }
        
        // Send the HTTP request.
        curl_setopt(self::$curl, CURLOPT_URL, $url);
        curl_setopt(self::$curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt(self::$curl, CURLOPT_HTTPHEADER, array('Content-Type: application/atom+xml',
                                                           'Authorization: GoogleLogin auth='.$this->auth_token));

        
        $response = curl_exec(self::$curl);
        // Throw an exception on connection failure.
        if (!$response) throw new GoogleAuthenticationClientError('Connection failed');
        
        // Deserialize the response string and store the result.
        $result = self::response_decode($response);
        
        return $result;
    }

    public function authenticate()
    {
        $auth_url = "https://www.google.com/accounts/ClientLogin";
        if (is_null(self::$curl)) {
            self::$curl = curl_init();
        }
        
        $args = array(
                      'Email' => $this->api_key,
                      'Passwd' => $this->api_secret,
                      'accountType' => $this->account_type,
                      'service' => 'apps',
                      );

        curl_setopt(self::$curl, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
        curl_setopt(self::$curl, CURLOPT_POST, true); 
        curl_setopt(self::$curl, CURLOPT_POSTFIELDS, http_build_query($args));
        curl_setopt(self::$curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt(self::$curl, CURLOPT_URL, $auth_url);
        $response = curl_exec(self::$curl);
        if(preg_match('/Auth=(.+)$/', $response, $tokens) > 0) {
            if(!empty($tokens[1])) {
                $this->auth_token = $tokens[1];
                $this->authenticated = true;
                
                return $this->authenticated;
            }
        }
            
        throw new GoogleUtilityClientException('Failed to authenticate');
    }


    public function __call($method, $args) {
        
        static $api_cumulative_time = 0;
        $time = microtime(true);
        
        // Initialize CURL 
        self::$curl = curl_init();

        if(isset($args[0])) {
            $name = $args[0];
        }

        if(isset($args[1])) {
            $args = $args[1];
        }


        curl_setopt(self::$curl, CURLOPT_HTTPHEADER, array('Content-type: application/atom+xml',
                                                           'Authorization: GoogleLogin auth='.$this->auth_token));

        if(preg_match('/^(http)/', $name, $matches) > 0)
        {
            $url = $name;
        }
        else
        {
            $url = 'https://apps-apis.google.com/a/feeds'
                . '/'
                . $name;
        }
            
        $response = $this->fetch($url, $method, $args);
        
        // If the response is a hash containing a key called 'error', assume
        // that an error occurred on the other end and throw an exception.
        if (isset($response['error'])) {
            throw new GoogleAuthenticationClientError($response['error'], $response['code']);
        } else {
            return $response['result'];
        }
    }

    function response_decode($xml) {
        $error = NULL;
        $result = NULL;
        $doc = @DOMDocument::loadXML($xml);
        if(!$doc) {
            throw new GoogleUtilityClientError('Server Unavailable', 500);
        }
        
        $result["list"] = $doc;
    
        return array("result" => $result);
    }

}

class GoogleUtilityClientNSWrapper {
    private $object;
    private $ns;
    
    function __construct($obj, $ns) {
        $this->object = $obj;
        $this->ns = $ns;
    }
    
    function __call($method, $args) {
        $args = array_merge(array($this->ns), $args);
        return call_user_func_array(array($this->object, $method), $args);
    }
}

?>
