<?php
/**
 * @category    Christopher Queen Consulting
 * @package     AutoUpdate
 * @copyright   Copyright (c) 2020 Christopher Queen Consulting LLC (http://www.ChristopherQueen.com/)
 * @author      christopherqueen <chris@christopherqueenconsulting.com>
 */

// Report simple running errors
error_reporting(E_ERROR | E_WARNING | E_PARSE);

require_once(__DIR__ . '/../vendor/autoload.php');

class AutoUpdate
{

    const DEBUG = true;
    const CONSUMER_KEY = 'xxxxxx'; // Consumer Key from MaxCDN
    const CONSUMER_SECRET = 'XXXX'; // Consumer Secret from MaxCDN;
    const ALIAS = 'XXXXXX'; // Alias from MaxCDN
    //const lineBreak = "\r\n";
    const lineBreak = "<br>";
    const LOG_FILE = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'log.txt';
    var $api;
    var $homeDir;


    /**
     * ChrisQueen_SiteGround_MaxCDN_LetsEncrypt_AutoUpdate constructor.
     * Initiate the api to make calls to MaxCDN
     */
    function __construct()
    {
        $user = posix_getpwuid(posix_getuid());
        $this->homeDir = $user['dir'];
        $this->displayOutput('Home Dir: ' . $this->homeDir);

        // Get Query from url
        if (isset($_REQUEST["log"]) && boolval($_REQUEST["log"])) {
            if (file_exists(self::LOG_FILE)) {
                $this->displayOutput(file_get_contents(self::LOG_FILE), null, true);
            } else {
                $this->displayOutput("No log file exists", null, true);
            }
        } else {
            $this->api = new MaxCDN(self::ALIAS, self::CONSUMER_KEY, self::CONSUMER_SECRET);
            // If no log param in url update the certs
            $this->updateCerts();
        }
    }

    /**
     * Pulls list of certs from Max CDN
     * Compares those certs with ones stored on SiteGround
     * Updates Certs on MaxCDN that dont match SiteGround
     */
    private function updateCerts()
    {
        try {
            // Get List of certs
            $certResponse = json_decode($this->api->get('/ssl.json'), true);
            switch ($certResponse['code']) {
                case 200:
                    $certificates = $certResponse['data']['certificates'];
                    foreach ($certificates as $cert) {
                        $domainName = $cert['domain'];
                        $certID = $cert['id'];
                        $sslCert = $cert['ssl_crt'];
                        $sslCABundle = $cert['ssl_cabundle'];
                        $certInfoCurrent = $this->isCertCurrent($domainName, $sslCert, $sslCABundle);
                        if ($certInfoCurrent !== true) {
                            $this->displayOutput($domainName . " ssl cert is not current. Updating now");
                            $this->updateMaxCDNCert($domainName, $certID, $certInfoCurrent);
                        } else {
                            $this->displayOutput($domainName . " ssl cert is already current.");
                        }
                    }
                    break;
                default:
                    if ($certResponse['error']) {
                        $this->displayOutput('Error: ' . $certResponse['error']['message']);
                    }
                    break;

            }
        } catch (Exception $e) {
            $this->displayOutput('Error while getting list of certificates.', $e->getMessage());

        }
    }

    /**
     * Returns true of false depending on if the cert info passed in the arguments is current with whats on the siteground server
     * @param $domainName | fully qualified domain name. (sub.example.com)
     * @param $sslCert
     * @param $sslCABundle
     * @return bool|array
     */
    public function isCertCurrent($domainName, $sslCert, $sslCABundle)
    {
        $isCurrent = false;
        $currentCertInfo = $this->getCertInfoFromSiteGround($domainName);
        if (is_null($currentCertInfo)) {
            $this->displayOutput("Site Ground has no cert to compare with " . $domainName);
            $isCurrent = true;
        } else if ($this->multiLineCompare($sslCert, $currentCertInfo['ssl_crt']) && $this->multiLineCompare($sslCABundle, $currentCertInfo['ssl_cabundle'])) {
            $isCurrent = true;
        } else {
            $this->displayOutput($sslCert . self::lineBreak . 'Doest Not Equal' . self::lineBreak . $currentCertInfo['ssl_crt']);
            $this->displayOutput('OR' . self::lineBreak . $sslCABundle . self::lineBreak . 'Doest Not Equal' . self::lineBreak . $currentCertInfo['ssl_cabundle']);
            $isCurrent = $currentCertInfo;
        }

        return $isCurrent;
    }

    /**
     * Breaks $str1 and $str2 into multiple lines and compares each. Returning true if every line is equal in both parameters.
     * @param $str1
     * @param $str2
     * @return bool
     */
    public static function multiLineCompare($str1, $str2)
    {
        $equal = true;
        $array1 = preg_split("/\r\n|\n|\r/", trim($str1));
        $array2 = preg_split("/\r\n|\n|\r/", trim($str2));
        if (is_array($array1) && is_array($array2) && count($array1) == count($array2)) {
            do {
                $line1 = array_shift($array1);
                $line2 = array_shift($array2);
                if (strcmp($line1, $line2) !== 0) {
                    $equal = false;
                    break;
                }
            } while (count($array1) > 0);
        } else {
            $equal = false;
        }

        return $equal;
    }

    /**
     * Returns the Valid Time To info for the passed $cert
     * @param $cert
     * @return int
     */
    public static function getCertValidTo($cert)
    {
        $cert = (is_file($cert)) ? file_get_contents($cert) : $cert;
        $certInfo = openssl_x509_parse($cert);
        //self::log("Cert Info:", $certInfo);
        //$valid_to = date(DATE_RFC2822,$certInfo['validTo_time_t']);
        //self::log($certInfo['name']. 'is Valid To: '.$valid_to);
        return ($certInfo['validTo_time_t']) ? $certInfo['validTo_time_t'] : 0;
    }

    /**
     * Returns the ssl info (id, ssl_crt, ssl_key, ssl_cabundle) of the given $domainName from siteground (local server)
     * @param $domainName
     * @return array
     */
    public function getCertInfoFromSiteGround($domainName)
    {


        // Break the $domainName into parts passed
        list($subdomain, $domain, $topLevelDomain) = explode('.', $domainName);

        $certInfo = array(
            'name' => $domainName,
            'ssl_crt' => '',
            'ssl_key' => '',
            'ssl_cabundle' => trim(file_get_contents(__DIR__ . '/siteground_cabundle.txt')));

        // Look for current cert
        if (!is_null($topLevelDomain)) {
            $this->displayOutput("\r\nGetting cert info for " . $domainName);
            $filePrefix = ($subdomain == '*') ? '_wildcard_' : $subdomain;
            $search = $filePrefix . '_' . $domain . '_' . $topLevelDomain . '_';

            $sitegroundCertDir = $this->homeDir . '/ssl/certs/';
            $certPattern = '/(' . $search . '.*\.crt)$/';
            $this->displayOutput('Cert Pattern: ' . $certPattern);

            $certFiles = $this->myglob($sitegroundCertDir, $certPattern);
            if (is_array($certFiles) && count($certFiles) > 0) {
                usort($certFiles, function ($a, $b) {
                    return $this->getCertValidTo($a) - $this->getCertValidTo($b);
                });
                $useCertFile = array_pop($certFiles); // Get file at the end
                $this->displayOutput('Found SSL Cert: ' . $useCertFile);
                $certInfo['ssl_crt'] = trim(file_get_contents($useCertFile));

                // Parse the found cert file to find the file name of the keys file
                $useCertFileNameSearch = str_replace($search, '', basename($useCertFile));
                list($keyFilePart1, $keyFilePart2) = explode('_', $useCertFileNameSearch);
                $sitegroundKeysDir = $this->homeDir . '/ssl/keys/';
                $keysPattern = '/(' . $keyFilePart1 . '_' . $keyFilePart2 . '.*\.key)$/';
                $this->displayOutput('Keys Pattern: ' . $keysPattern);
                $keyFiles = $this->myglob($sitegroundKeysDir, $keysPattern);
                if (is_array($keyFiles) && count($keyFiles) > 0) {
                    usort($keyFiles, function ($a, $b) {
                        return filemtime($a) - filemtime($b);
                    });
                    $useKeyFile = array_pop($keyFiles); // Get File at the end
                    $this->displayOutput('Found SSL Key: ' . $useKeyFile);
                    $certInfo['ssl_key'] = trim(file_get_contents($useKeyFile));
                } else {
                    $this->displayOutput('No Key files found matching pattern: ' . $keysPattern);
                }
            } else {
                $this->displayOutput('No Cert files found matching pattern: ' . $certPattern);
            }
        } else {
            $this->displayOutput('No Top Level Domain for ' . $domainName);
            $certInfo = null;
        }

        if (strlen($certInfo['ssl_crt']) == 0 || strlen($certInfo['ssl_key']) == 0) {
            $certInfo = null;
        }

        return $certInfo;
    }

    /**
     * Upload new LetEncrypt Cert ($certInfo) to Max CDN for the given $domainName
     * @param $domainName
     * @param $certID
     * @param $certInfo
     */
    public function updateMaxCDNCert($domainName, $certID, $certInfo)
    {
        $updated = false;

        //$this->log('Updating ' . $domainName . " with ", $certInfo);
        $updateResponse = json_decode($this->api->put('/ssl.json/' . $certID, $certInfo), true);
        //$this->log(' Update Response : ', $updateResponse);
        if (isset($updateResponse['error'])) {
            $this->displayOutput($domainName . ' Error:', $updateResponse['error']['message']);
        } else if (isset($updateResponse['code']) && $updateResponse['code'] == 200) {
            $this->displayOutput($domainName . ' Updated Successfully.');
            $updated = true;
        } else {
            $this->displayOutput('Something went wrong trying to update ' . $domainName);
        }


        // Log Update to log file
        if (!file_exists(self::LOG_FILE)) {
            $this->displayOutput('File Doesnt exists. Making file');
            if (!file_exists(dirname(self::LOG_FILE))) {
                mkdir(dirname(self::LOG_FILE));
            }
            touch(self::LOG_FILE);
        }
        $postFix = 'Error during update';
        if ($updated) {
            $postFix = 'Updated Successfully';
        }
        $this->recordToLogFile("Domain: " . $domainName . ' | ' . $postFix . ' at ' . date('l jS \of F Y h:i:s A') . "\r\n");


    }

    /**
     * Records the passed in string to the log file
     * @param $str
     */
    public static function recordToLogFile($str)
    {
        file_put_contents(self::LOG_FILE, $str, FILE_APPEND);
    }

    /**
     * Prints the $message and exports passed $variable if DEBUG constant is set to true
     * @param $message
     * @param null $variable
     * @param false $debugOverride
     */
    public static function displayOutput($message, $variable = null, $debugOverride = false)
    {
        if (self::DEBUG || $debugOverride) {
            $message .= self::lineBreak;
            if (!is_null($variable)) {
                $message .= var_export($variable, true) . self::lineBreak;
            }
            if (PHP_SAPI === 'cli') {
                $message = self::br2nl($message);
            } else {
                $message = nl2br($message);
            }
            echo($message);
        }
    }

    /**
     * Changes <br> to new line characters
     * @param $str
     * @return string|string[]|null
     */
    public static function br2nl($str)
    {
        $str = preg_replace("/(\r\n|\n|\r)/", "", $str);
        return preg_replace("=&lt;br */?&gt;=i", "\n", $str);
    }

    /**
     * Returns files that match the $pattern inside the $file_path directory
     * @param $file_path
     * @param $pattern
     * @return array
     */
    public static function myglob($file_path, $pattern)
    {
        $files = array();
        // open the directory
        if ($handle = opendir($file_path)) {
            // iterate over the directory entries
            while (false !== ($entry = readdir($handle))) {
                // match on .php extension
                if (preg_match($pattern, $entry)) {
                    $files[] = $file_path . $entry;
                }
            }
            // close the directory
            closedir($handle);
        }
        return $files;
    }

}

?>