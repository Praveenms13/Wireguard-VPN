<?php

require_once("REST.api.php");
require_once("libs/Database.class.php");
require_once("libs/Signup.class.php");
require_once("libs/User.class.php");
require_once("libs/Auth.class.php");
require_once("libs/OAuth.class.php");
require_once("libs/wireguard.class.php");
require_once("libs/IPNetwork.class.php");
class API extends REST
{
    private $current_call;
    public $data = "";
    public $auth = null;

    public function __construct()
    {
        parent::__construct();
        $this->dbConnect();
    }

    private function dbConnect()
    {
        return Database::getConnection();
    }

    public function auth()
    {
        if (isset(getallheaders()['Authorization'])) {
            $token = explode(" ", getallheaders()['Authorization'])[1];
            if (explode(" ", getallheaders()['Authorization'])[0] != "Bearer") {
                $this->auth = new Auth($token);
            }
        }
    }

    public function isAuthenticated()
    {
        if (isset($_SESSION['username']) and isset($_SESSION['access_token']) and $this->auth->oauth_token->authenticate()) {
            return true;
        }
        return false;
    }

    public function getUsername()
    {
        return $_SESSION['username'];
    }

    public function die($e)
    {
        $data = [
            "Error" => $e->getMessage(),
        ];
        $response_code = 400;
        if ($e->getMessage() == ("Notes Not Found" or
            "Folder Not Found" or
            "Created at Not Found" or
            "Updated at Not Found" or
            "Owner Not Found" or
            "Folder Not Found, Please check the the thing once again..." or
            "Database Error"
        )) {
            $response_code = 404;
        }
        if ($e->getMessage() == "Access token expired, Login Again") {
            $response_code = 403;
        }
        $this->response($this->json($data), $response_code);
    }
    public function processApi()
    {
        //ALERT!!...   to prevent the sql injection here
        $func = strtolower(trim(str_replace("", "", $_REQUEST['method']))); // TODO: If api doesnt works remove / from the line
        if ((int)method_exists($this, $func) > 0) {
            $this->$func();
        } else {
            if (isset($_GET['namespace'])) {
                $dir = $_SERVER['DOCUMENT_ROOT'] . '/api/api_xtensions/' . $_GET['namespace'];
                $file = $dir . '/' . $func . '.php';
                if (file_exists($file)) {
                    include $file;
                    $this->current_call = Closure::bind(${$func}, $this, get_class());
                    $this->$func();
                } else {
                    $this->response($this->json(['error' => 'method_not_found...']), 404);
                }
            }
        }
    }


    public function __call($method, $args)
    {
        if (is_callable($this->current_call)) {
            $this->current_call;
            return call_user_func_array($this->current_call, $args);
        } else {
            echo "Method called not if : $method is not presesnt as external function.......\n";
            $data = [
                "Error" => "Method not Callable"
            ];
            $this->response($this->json($data), 405);
        }
    }

    /*************API SPACE START*******************/

    private function about()
    {
        if ($this->get_request_method() != "POST") {
            $error = array('status' => 'WRONG_CALL', "msg" => "The type of call cannot be accepted by our servers, by File Name : Advanced API");
            $error = $this->json($error);
            $this->response($error, 406);
        }
        $data = array('version' => $this->_request['version'], 'desc' => 'This API is created by Praveen, by File Name : Advanced API');
        $data = $this->json($data);
        $this->response($data, 200);
    }

    private function verify()
    {
        include "verify.php";
    }


    /*************API SPACE END*********************/

    /*
                Encode array into JSON
            */
    private function json($data)
    {
        if (is_array($data)) {
            return json_encode($data, JSON_PRETTY_PRINT);
        } else {
            return "{}";
        }
    }
}

// Initiiate Library
function stringStart($string, $startString)
{
    $len = strlen($startString);
    return (substr($string, 0, $len) === $startString);
}
$api = new API();
try {
    $api->auth();
    //$api->isAuthenticated();
    $api->processApi();
} catch (Exception $e) {
    $api->die($e);
}
