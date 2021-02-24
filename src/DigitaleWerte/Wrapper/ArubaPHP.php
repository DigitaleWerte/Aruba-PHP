<?php
namespace DigitaleWerte\Wrapper;

/**
 * Class ArubaPHP
 */
class ArubaPHP
{
    private $unsafeConnection = false;

    /**
     * possible Values: API, USER
     * @var string
     */

    private $username = "";

    private $password = "";

    private $reqProtocol = "https";

    private $devAddress;

    private $devPort = 443;

    private $authcookie = "";

    

    /**
     * FgtPHP constructor.
     * @param string $address Adress of the device you want to connecto to
     * @param int $port
     * @param string $method http or https (standard)
     */
    function __construct($address, $port = 443, $protocol = "https", $username, $password)
    {
        $this->devAddress = $address;

        if ($port >= 1 AND $port <= 65535) {
            $this->devPort = $port;
        } else {
            throw new Exception("Error: Port must be between 1 and 65535");

        }

        if ($protocol == "https" OR $protocol == "http") {
            $this->reqProtocol = $protocol;
        } else {
            throw new Exception("Error. Protocol must be http or https");


        }

        $this->username = $username;

        $this->password = $password;

    }

    /**
     * We need a destructor, because we have to destroy the session at the end of our work.
     * The session count is limited to 5 on Aruba devices.
     */
    function __destruct()
    {
        $this->destroySession();
    }

    /**
     * With this function you can disable the certificate check for https connections
     * @param boolean $unsafe
     */
    function setUnsafeConnection($unsafe) {

        $this->unsafeConnection = $unsafe;
    }

    function getSystemTime() {


        $request['method'] = 'GET';
        $request['uri'] = "/rest/v1/system/time";

        return $this->doRequest($request);


    }

    function getVlans() {
        $request['method'] = 'GET';
        $request['uri'] = "/rest/v1/vlans";


        return $this->doRequest($request);
    }

    /**
     * @return string|bool Returns the systems hostname. If an error occurs it returns false
     *
     */
    function getSystemName() {
        $request['method'] = 'GET';
        $request['uri'] = "/rest/v1/system/status";


        $response = $this->doRequest($request);


        $obj = json_decode($response);

        if ($obj->{'name'}) {
            return $obj->{'name'};
        } else {
            return false;
        }
    }


    function getRunningBootImage() {
        $json = '{"cmd":"show version"}';

        $request['method'] = 'POST';
        $request['uri'] = "/rest/v4/cli";
        $request['postdata'] = $json;


        $response = base64_decode($this->doRequest($request));

        if (strpos($response, 'Primary')) {
            return "Primary";
        } elseif (strpos($response, 'Secondary')) {
            return "Secondary";
        } else {
            return false;
        }
    }

    /**
     * This method returns a array conaining the saved Software Versions.
     * @return array|bool   Array containing the Image Versions in the keys "Primary" and "Secondary"
     */
    function getInstalledImages() {
        $json = '{"cmd":"show flash"}';

        $request['method'] = 'POST';
        $request['uri'] = "/rest/v4/cli";
        $request['postdata'] = $json;


        $response = $this->doRequest($request);

        $obj = json_decode($response);

        if ($obj->{'result_base64_encoded'}) {
                $returnarray = array();

                $lines=explode("\n", base64_decode($obj->{'result_base64_encoded'}));

                foreach ($lines as $line) {
                    if (substr( $line, 0, 13 ) === "Primary Image") {
                        $words = explode(" ", $line);
                        foreach ($words as $word) {
                            if (preg_match('/^[A-Z]{1,2}.\d\d.\d\d.\d\d\d\d/', $word)) {
                                $returnarray['Primary'] = $word;
                            }
                        }



                    }
                }

                foreach ($lines as $line) {
                    if (substr( $line, 0, 15 ) === "Secondary Image") {
                        $words = explode(" ", $line);
                        foreach ($words as $word) {
                            if (preg_match('/^[A-Z]{1,2}.\d\d.\d\d.\d\d\d\d/', $word)) {
                                $returnarray['Secondary'] = $word;
                            }
                        }

                    }
                }

                return $returnarray;
        } else {
            return false;
        }

    }

    /**
     *     * @return bool|string Return the Primary boot Image Name
     */
    function getDefaultBootImage() {
        $json = '{"cmd":"show flash"}';

        $request['method'] = 'POST';
        $request['uri'] = "/rest/v4/cli";
        $request['postdata'] = $json;


        $response = $this->doRequest($request);

        $obj = json_decode($response);

        if ($obj->{'result_base64_encoded'}) {


            $lines=explode("\n", base64_decode($obj->{'result_base64_encoded'}));

            foreach ($lines as $line) {
                if (substr( $line, 0, 12 ) === "Default Boot") {
                    $words = explode(" ", $line);
                    foreach ($words as $word) {
                        if ($word == "Primary" or $word == "Secondary") {
                            return $word;
                        }
                    }
                }
            }

        }

        return false;

    }



    /**
     * @return string|bool This method return the HPE/Aruba product number
     */
    function getProductNumber () {
        $request['method'] = 'GET';
        $request['uri'] = "/rest/v1/system/status";


        $response = $this->doRequest($request);


        $obj = json_decode($response);

        if ($obj->{'hardware_revision'}) {
            return $obj->{'hardware_revision'};
        } else {
            return false;
        }
    }

    function getSystemStorageStatus () {


        //$json = '{ "file_type":"FTT_FIRMWARE", "url":"'.$url.'", "action":"FTA_DOWNLOAD", "boot_image":"'.$partition.'"}';

        $request['method'] = 'GET';
        $request['uri'] = "/rest/v1/banner";
        //$request['postdata'] = $json;

        $response = $this->doRequest($request);
        echo "response; " . $response;
    }

    /**
     * @param string $image The image that the system has to boot. By default we start the primary firmware.
     * @return bool
     *
     *
     *
     */
    function reboot($bootimage = "auto") {


        if ($bootimage == "auto") {
            $bootimage == $this->getRunningBootImage();
        }

        if ($bootimage == "Primary") {
            $imageid = "BI_PRIMARY_IMAGE";
        } elseif ($bootimage == "Secondary") {
            $imageid = "BI_SECONDARY_IMAGE";
        } else {
            return false;
        }


        $json = '{ "boot_image": "'.$imageid.'"  }';

        $request['method'] = 'POST';
        $request['uri'] = "/rest/v1/system/reboot";
        $request['postdata'] = $json;


        $response = $this->doRequest($request);

        $obj = json_decode($response);

        if (isset($obj->{'message'}) && $obj->{'message'} == "Device is rebooting") {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Get the configuration from the switch. You can choose between the running configuration and the startup configuration.
     * @param string $config The configuration we want to get (valid values: running|startup)
     * @param bool $base64 We can get the configuration in base64 or plaintext
     * @return string|boolean The configuration in the choosen format or false if a error occured
     */
    function getConfiguration($config = "running", $base64 = true)
    {
        $json = '{"cmd":"show startup"}';

        if ($config == "running") {
            $json = '{"cmd":"show run"}';
        }


        $request['method'] = 'POST';
//        $request['uri'] = "/rest/v7.0/system/config/cfg_backup_files/config";
        $request['uri'] = "/rest/v4/cli";
        $request['postdata'] = $json;

        $response = $this->doRequest($request);
        //echo "response; " . $response;

        $obj = json_decode($response);

        if ($obj->{'result_base64_encoded'}) {
            if ($base64) {
                return $obj->{'result_base64_encoded'};
            } else {
                return base64_decode($obj->{'result_base64_encoded'});
            }

        } else {
            return false;
        }

    }

    /**
     * The method returns the running firmware version
     * @return string|boolean The firmware version as a string
     */
    function getSystemFirmware() {

        $request['method'] = 'GET';
        $request['uri'] = "/rest/v1/system/status";


        $response = $this->doRequest($request);

        $obj = json_decode($response);

        if ($obj->{'firmware_version'}) {
            return $obj->{'firmware_version'};
        } else {
            return false;
        }


    }

    /**
     * This method is initiating a firmware download to the switch and is checking that the switch has written the firmware into the selected flash partition.
     * @param string $url Source of the Installation File. Valid values are 'upload' and 'fortiguard'
     * @param string $partition Primary|secondary       The Name of the partition where the firmware will be written to
     * @return boolean true for successful operation.
     */
    function updateSystemFirmware($url, $partition = "Primary" ) {


        if ($partition == 'Secondary') {
            $partition = "BI_SECONDARY_IMAGE";
        } else {
            $partition = "BI_PRIMARY_IMAGE";
        }

        $json = '{ "file_type":"FTT_FIRMWARE", "url":"'.$url.'", "action":"FTA_DOWNLOAD", "boot_image":"'.$partition.'"}';

        $request['method'] = 'POST';
        $request['uri'] = "/rest/v3/file-transfer";
        $request['postdata'] = $json;

        $response = $this->doRequest($request);
        echo "response; " . $response;
        $obj = json_decode($response);

        if ($obj->{'message'} == "File transfer initiated") {
            return true;

            // Prüfung



        } else {
            return false;
        }

    }


    private function destroySession() {


        $ch = curl_init();

        curl_setopt($ch, CURLOPT_COOKIE, $this->authcookie);

        // Our Array for the headers we have to send
        $headers = array();

        $curlopturl = $this->reqProtocol . "://" . $this->devAddress . ":" . $this->devPort . "/rest/v1/login-sessions";

        curl_setopt($ch, CURLOPT_URL, $curlopturl);

        // $headers[] = 'Content-Type: application/json';
        //$headers[] = 'Accept: application/json';


        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");

        if ($this->unsafeConnection) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        // Der dafür da damit nicht 1 zurückgegeben wird.
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $server_output = curl_exec ($ch);

        curl_close ($ch);


    }

     private function authenticate() {
        if (!isset($this->username) && !isset($this->password)) {
            exit -1;
        }

        $ch = curl_init();

        // Our Array for the headers we have to send
        $headers = array();

        $curlopturl = $this->reqProtocol . "://" . $this->devAddress . ":" . $this->devPort . "/rest/v1/login-sessions";

        curl_setopt($ch, CURLOPT_URL, $curlopturl);

       // $headers[] = 'Content-Type: application/json';
        //$headers[] = 'Accept: application/json';


        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");

        if ($this->unsafeConnection) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        }

        $postdata = '{"userName":"'.$this->username.'", "password":"'.$this->password.'"}';


        curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);


        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        // Der dafür da damit nicht 1 zurückgegeben wird.
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $server_output = curl_exec ($ch);

        curl_close ($ch);



        $obj = json_decode($server_output);


        $this->authcookie = $obj->{'cookie'};


    }

    /**
     * @param $reqParam array with many fields. the required fields are "method" (GET,POST,UPDATE,DELTE), "uri" and optional 'paramater'
     */
    private function doRequest($reqParam) {

        if ($this->authcookie == "") {
            $this->authenticate();
        }



        $ch = curl_init();

        curl_setopt($ch, CURLOPT_COOKIE, $this->authcookie);
        /**
         * The params we want to add to the URL
         */
        $getParams = '';

        // Our Array for the headers we have to send
        $headers = array();

        //the parameters we get from the caller...
        if (isset($reqParam['parameter'])) {
            foreach($reqParam['parameter'] as $key=>$value) {
                $getParams .= $key.'='.$value.'&';
            }
            $getParams = trim($getParams, '&');
        }

        $curlopturl = $this->reqProtocol . "://" . $this->devAddress . ":" . $this->devPort . $reqParam['uri'] . "?" . $getParams;



        curl_setopt($ch, CURLOPT_URL, $curlopturl);

        /**
         * Set the Request Type
         */
        switch ($reqParam['method']) {
            case "GET":
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
                break;
            case "POST":
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
                if(isset($reqParam['postdata'])) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $reqParam['postdata']);
                    $headers[] = 'Content-Type: application/json';
                    //$headers[] = 'Content-Length:' . strlen($reqParam['postdata']);
                }

                break;
            case "PUT":
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($reqParam['data']));
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
                break;
            case "DELETE":
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
                //curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($reqParam['data']));
                break;
            default:
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        }

        /**
         * Set unsafe Options...
         */
        if ($this->unsafeConnection) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        }


        /**
         * Filling Headers
         */



        $headers[] = 'User-Agent: DW Services FortGate API Wrapper';
        $headers[] = 'Cache-Control: no-cache';

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        // Der dafür da damit nicht 1 zurückgegeben wird.
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);



        $server_output = curl_exec ($ch);

        curl_close ($ch);



        return  $server_output;


    }

}