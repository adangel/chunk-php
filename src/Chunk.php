<?php

namespace com\github\adangel\chunkphp;

use ZipArchive;
use PharData;
use finfo;

const VERSION = '1.0.1-SNAPSHOT';

class Chunk {
    private $method;
    private $user;
    private $current_uuid;
    private $verbose;

    function __construct($method) {
        $this->init();
        $this->method = $method;
        $this->verbose = isset($_GET['chunk_php_verbose']);
    }

    private function init() {
        if (!file_exists(Config::getDataDir())) {
            mkdir(Config::getDataDir());
        }
        $data_htacess_file = Config::getDataDir() . '/.htaccess';
        if (!file_exists($data_htacess_file)) {
            $htaccess = <<<EOT
Deny from all
EOT;
            file_put_contents($data_htacess_file, $htaccess);
        }

        if ($this->verbose) {
            header('Content-Type: text/html');
        }
    }

    public function init_for_tests($user, $current_uuid) {
        $this->user = $user;
        $this->current_uuid = $current_uuid;
    }

    private function print($message) {
        if ($this->verbose) {
            print "<p>$message</p>" . PHP_EOL;
        }
    }

    private static function end($code) {
        http_response_code($code);
        exit;
    }

    private function checkAuthorization() {
        $this->print("check authorization");

        $user = '';
        $pw = '';

        if (array_key_exists('PHP_AUTH_USER', $_SERVER) && array_key_exists('PHP_AUTH_PW', $_SERVER)) {
            $user = $_SERVER['PHP_AUTH_USER'];
            $pw = $_SERVER['PHP_AUTH_PW'];
        } else if (array_key_exists('user', $_POST) && array_key_exists('pw', $_POST)) {
            $user = $_POST['user'];
            $pw = $_POST['pw'];
        } else {
            Chunk::end(401); # Unauthorized
        }

        if (Config::isValidUserAuthentication($user, $pw)) {
            $this->print("authorization passed for $user");
        } else {
            $this->print("authorization failed");
            sleep(10);
            Chunk::end(403); # Forbidden
        }

        $this->user = $user;
    }

    private function determinePathFromRequest() {
        $pathinfo = '/';
        if (isset($_SERVER['PATH_INFO'])) {
            $pathinfo = $_SERVER['PATH_INFO'];
        }
        if ($_SERVER['REQUEST_URI'] === $_SERVER['PHP_SELF']) {
            $pathinfo = $_SERVER['REQUEST_URI'];
        }

        $this->print("REQUEST_URI: " . $_SERVER['REQUEST_URI']);
        $this->print("PHP_SELF: " . (isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : 'n/a'));
        $this->print("PATH_INFO: " . (isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : 'n/a'));
        $this->print("QUERY_STRING: " . (isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : 'n/a'));
        $this->print("Using pathinfo: $pathinfo");
        return $pathinfo;
    }

    function handleRequest() {
        $this->print("Handling " . $this->method . " request");

        switch ($this->method) {
            case 'POST':
                $this->handlePost();
                break;
            case 'PUT':
                $this->handlePut();
                break;
            case 'GET':
            case 'HEAD':
                $this->handleGet();
                break;
            default:
                Chunk::end(405); # Method Not Allowed
        }
    }

    function handleGet() {
        $sendContent = $this->method === 'GET';
        $uri = $this->determinePathFromRequest();
        $baseurl = $this->determineBaseUrlFromRequest();

        if ($uri === '/') {
            print "<h1>chunk-php " . VERSION . "</h1>" . PHP_EOL;
            print <<<HERE
<h2>Upload files from your terminal</h2>
<pre>
$ curl -u user:pass -T path/to/file $baseurl
{$baseurl}user/00d971ff-f70a-4e3e-a706-58c606cab27c
</pre>

<h2>Upload files with your browser</h2>
<style type="text/css">
h1, h2 {
    font-family: sans-serif;
}
form {
    max-width: 600px;
}
label {
    float: left;
    text-align: right;
    width: 30%;
}
input, button {
    display: block;
    margin-left: 32%;
    margin-bottom: 5px;
}
</style>
<form method="post" enctype="multipart/form-data">
    <label for="file">File:</label>
    <input type="file" name="file" id="file">
    <label for="user">Username:</label>
    <input type="text" name="user" id="user">
    <label for="pw">Password:</label>
    <input type="password" name="pw" id="pw">
    <button type="submit">Upload!</button>
</form>
<hr>
<a href="https://github.com/adangel/chunk-php">github.com/adangel/chunk-php</a>
HERE;

        } else if (Chunk::str_startsWith($uri, '/')) {
            $parts = explode('/', substr($uri, 1), 3);
            if (count($parts) < 2) {
                Chunk::end(400); # Bad Request
            }

            $this->user = $parts[0];
            $this->current_uuid = $parts[1];

            $this->check_user($parts[0]);
            $this->check_uuid_name($parts[1]);

            $this->print("user: $this->user");
            $this->print("uuid: $this->current_uuid");

            $filepath = Config::getDataDir() . '/' . $this->user . '/' . $this->current_uuid;
            $this->print("Determined local base file: $filepath");

            # the file might have an extension...
            $filepaths = glob($filepath . "*");
            if ($filepaths !== FALSE && count($filepaths) > 0) {
                $filepath = $filepaths[0];
            } else {
                $this->print("file $filepath not found");
                Chunk::end(404); # Not Found
            }
            $this->print("Resolved local file: $filepath");

            $finfo = new finfo(FILEINFO_MIME);
            $mimetype = $finfo->file($filepath);
            $this->print("mimetype: $mimetype");

            $this->print("Parsed URI parts: " . count($parts));
            if (count($parts) > 2 && Chunk::str_startsWith($mimetype, "application/zip")) {
                $this->print('ZIP file detected, looking into it for name=' . $parts[2]);
                $zip = new ZipArchive;
                $res = $zip->open($filepath);
                if ($res === FALSE) {
                    $this->print("Couldn't open ZIP file");
                    Chunk::end(500); # Internal Server Error
                }
                $fp = $zip->getStream($parts[2]);
                if ($fp === FALSE) {
                    // try again with with index.html
                    header('Location: ' . $_SERVER['REQUEST_URI'] . '/index.html');
                    Chunk::end(302); # Found
                }

                if ($fp === FALSE) {
                    $this->print("Name=" . $parts[2] . " inside ZIP file not found");
                    Chunk::end(404); # Not Found
                }

                $contents = fread($fp, 8192);
                $mimetype = $finfo->buffer($contents);
                if (Chunk::str_endsWith($parts[2], '.css')) {
                    $mimetype = 'text/css';
                } else if (Chunk::str_endsWith($parts[2], '.js')) {
                    $mimetype = 'application/javascript';
                }
                $this->print("Determined mimetype: $mimetype");

                $this->sendContentType($mimetype);

                if ($this->verbose) {
                    print('<hr><pre>');
                }
                if ($sendContent) {
                    print($contents);
                    while (!feof($fp)) {
                        $contents = fread($fp, 8192);
                        print($contents);
                    }
                }
                if ($this->verbose) {
                    print('</pre>');
                }
                fclose($fp);
                $zip->close();
            } else if (count($parts) > 2 && Chunk::str_startsWith($mimetype, "application/x-tar")) {
                $this->print('TAR archive deteted, looking into it for name=' . $parts[2]);
                $phar = new PharData($filepath);
                $file = $phar[$parts[2]];
                if (!$file) {
                    $this->print('Couldn\'t open TAR file entry ' . $parts[2]);
                    Chunk::end(404); # Not Found
                }

                $contents = $file->getContent();
                $mimetype = $finfo->buffer($contents);
                if (Chunk::str_endsWith($parts[2], '.css')) {
                    $mimetype = 'text/css';
                } else if (Chunk::str_endsWith($parts[2], '.js')) {
                    $mimetype = 'application/javascript';
                }
                $this->print("Determined mimetype: $mimetype");

                $this->sendContentType($mimetype);

                if ($this->verbose) {
                    print('<hr><pre>');
                }
                if ($sendContent) {
                    print($contents);
                }
                if ($this->verbose) {
                    print('</pre>');
                }
            } else {
                $this->sendContentType($mimetype);

                if ($this->verbose) {
                    print('<hr><pre>');
                }
                if ($sendContent) {
                    $handle = fopen($filepath, 'r');
                    if (FALSE === $handle) {
                        $this->print("Couldn't open file $filepath");
                        Chunk::end(500); # Internal Server Error
                    }
                    while (!feof($handle)) {
                        $contents = fread($handle, 8192);
                        print($contents);
                    }
                    fclose($handle);
                }
                if ($this->verbose) {
                    print('</pre>');
                }
            }
        } else {
            Chunk::end(400); # Bad Request
        }
    }

    private function cleanupOldFiles() {
        $path = Config::getDataDir();
        $this->print("Cleaning up old files in $path");

        $now = time();
        $threshold = Config::getCleanupThreshold();
        $this->print("Threshold is: " . ($threshold / (3600 * 24)) . " days");

        $files = glob("{$path}/*/*");
        foreach ($files as $f) {
            $mtime = filemtime($f);
            $days = ($now - $mtime) / (3600 * 24);
            if (($now - $mtime) > $threshold) {
                $this->print("Deleting file $f - it's $days old");
                if (!unlink($f)) {
                    $this->print("Error while deleting file $f");
                }
            }
        }
    }

    private function sendContentType($type) {
        if (!$this->verbose) {
            header('Content-Type: ' . $type);
        }
    }

    public function handlePost() {
        $this->checkAuthorization();
        $this->prepareDestinationUploadDirectory();
        $this->cleanupOldFiles();
        $this->current_uuid = Chunk::guidv4();
        $this->print("Posting new file for user $this->user with uuid: $this->current_uuid");

        if (array_key_exists('file', $_FILES) && $_FILES['file']['name'] !== '') {
            $original_filename = basename($_FILES['file']['name']);
            $destination = $this->determineDestinationFilename($original_filename);
            if (move_uploaded_file($_FILES['file']['tmp_name'], $destination)) {

                if (filesize($destination) > Config::getMaxFileSize()) {
                    $this->print("File is too big, max allowed: " . Config::getMaxFileSize());
                    Chunk::end(400); # Bad Request
                }

                $this->sendContentType('text/html');
                print("<p>Your file has been successfully uploaded.</p>" . PHP_EOL);
                $url = $this->getTargetUrl();
                print("<p>It is available as <a href=\"$url\">$url</a></p>");
            } else {
                $this->print("Move uploaded file failed, maybe file upload size limit? see php.ini");
                Chunk::end(400); # Bad Request
            }
        } else {
            $this->print("No file in post request found");
            Chunk::end(400); # Bad Request
        }
    }

    function handlePut() {
        $this->checkAuthorization();
        $this->prepareDestinationUploadDirectory();
        $this->cleanupOldFiles();
        $this->current_uuid = Chunk::guidv4();
        $uri = $this->determinePathFromRequest();

        $this->print("Putting new file for user $this->user with uuid: $this->current_uuid");
        $this->print("uri: $uri");

        $original_filename = basename($uri);
        $destination = $this->determineDestinationFilename($original_filename);

        $putdata = fopen("php://input", "r");
        $fp = fopen($destination, "w");
        while ($data = fread($putdata, 8192)) {
            fwrite($fp, $data);
        }
        fclose($fp);
        fclose($putdata);

        if (filesize($destination) > Config::getMaxFileSize()) {
            $this->print("File is too big, max allowed: " . Config::getMaxFileSize());
            Chunk::end(400); # Bad Request
        }

        $this->sendContentType('text/uri-list');
        print($this->getTargetUrl());
        print(PHP_EOL);
    }

    private function determineDestinationFilename($original_filename) {
        $this->print("Original Filename: $original_filename");
        $path_parts = pathinfo($original_filename);
        $ext = '';
        if (array_key_exists('extension', $path_parts)) {
            $ext = '.' . $path_parts['extension'];
        }
        $this->print("Extension: $ext");
        $destination = Config::getDataDir() . '/' . $this->user . '/' . $this->current_uuid . $ext;
        $this->print("Destination: " . $destination);
        return $destination;
    }

    private function prepareDestinationUploadDirectory() {
        $dir = Config::getDataDir() . '/' . $this->user;
        if (!file_exists($dir)) {
            if (mkdir($dir)) {
                $this->print("Successfully created $dir");
            } else {
                $this->print("Couldn't create $dir");
                Chunk::end(500); # Internal Server Error
            }
        } else {
            $this->print("Directory $dir already exists");
        }
    }

    private function determineBaseUrlFromRequest() {
        $result = 'http://';
        if (array_key_exists('HTTPS', $_SERVER)) {
            $result = 'https://';
        }

        $result .= $_SERVER['HTTP_HOST'];
        if ($this->method === 'PUT') {
            $uriparts = explode('/', $_SERVER['REQUEST_URI']);
            unset($uriparts[count($uriparts) - 1]);
            $result .= implode('/', $uriparts);
        } else {
            $result .= $_SERVER['REQUEST_URI'];
        }
        $query = isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '';
        if (Chunk::str_endsWith($result, "?$query")) {
            $result = substr($result, 0, -strlen("?$query"));
        }
        if (!Chunk::str_endsWith($result, '/')) {
            $result .= '/';
        }
        $this->print("Determined base URL: $result");
        return $result;
    }

    public function getTargetUrl() {
        $result = $this->determineBaseUrlFromRequest();
        $result .= $this->user . '/' . $this->current_uuid;
        if ($this->verbose) {
            $result .= '?chunk_php_verbose';
        }
        $this->print("Determined target URL: $result");
        return $result;
    }

    function check_user() {
        if (!Config::isValidUser($this->user)) {
            $this->print("Invalid user: $this->user");
            Chunk::end(404); # Not Found
        }
    }

    function check_uuid_name() {
        if (preg_match("/^[a-f0-9-]{36}(\.[a-zA-Z0-9]{1,5})?$/", $this->current_uuid) !== 1) {
            $this->print("Invalid UUID Name: $this->current_uuid");
            Chunk::end(400); # Bad Request
        }
    }


    # https://stackoverflow.com/a/15875555/1169968
    public static function guidv4()
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    # https://stackoverflow.com/a/834355/1169968
    private static function str_startsWith($str, $pattern) {
        $len = strlen($pattern);
        return substr($str, 0, $len) === $pattern;
    }
    private static function str_endsWith($str, $pattern) {
        $len = strlen($pattern);
        return substr($str, -$len, $len) === $pattern;
    }

}
