<?php

namespace com\github\adangel\chunkphp;

use Exception;
use ZipArchive;
use PharData;
use finfo;

const VERSION = '1.0.0';

class Chunk {
    private $method;
    private $user;
    private $verbose;
    private $storage;

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

        $this->storage = new Storage($this->determineBaseUrlFromRequest(), Config::getDataDir());

        if ($this->verbose) {
            header('Content-Type: text/html');
        }
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

        $valid = FALSE;
        try {
            $valid = Config::isValidUserAuthentication($user, $pw);
        } catch (Exception $e) {
            $this->print("Invalid user: {$e->getMessage()}");
        }

        if ($valid === TRUE) {
            $this->user = $user;
            $this->print("authorization passed for $user");
        } else {
            $this->print("authorization failed");
            sleep(10);
            Chunk::end(403); # Forbidden
        }
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
$ curl -u user:pass -T path/to/file $baseurl/
{$baseurl}/user/00d971ff-f70a-4e3e-a706-58c606cab27c
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

            try {
                $file = $this->storage->findFile($parts[0], $parts[1]);
            } catch (Exception $e) {
                $this->print("Invalid user or uuid: {$e->getMessage()}");
                Chunk::end(400); # Bad Request
            }

            if ($file !== NULL && $file->hasContent()) {
                $this->print("user: {$file->getUser()}");
                $this->print("uuid: {$file->getUuid()}");

                if (count($parts) === 2) {
                    $this->sendContentType($file->getType());

                    if ($this->verbose) {
                        print('<hr><pre>');
                    }
                    if ($sendContent) {
                        $file->send();
                    }
                    if ($this->verbose) {
                        print('</pre>');
                    }
                } else if (count($parts) > 2) {
                    if ($file->hasContent($parts[2])) {
                        $this->sendContentType($file->getType($parts[2]));

                        if ($this->verbose) {
                            print('<hr><pre>');
                        }
                        if ($sendContent) {
                            $file->sendArchivePath($parts[2]);
                        }
                        if ($this->verbose) {
                            print('</pre>');
                        }
                    } else {
                        // try again with with index.html
                        header('Location: ' . $_SERVER['REQUEST_URI'] . '/index.html');
                        Chunk::end(302); # Found
                    }
                }
            } else {
                $this->print("file not found: $uri");
                Chunk::end(404); # Not Found
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
        $this->print("Posting new file for user $this->user");

        if (array_key_exists('file', $_FILES) && $_FILES['file']['name'] !== '') {
            $original_filename = basename($_FILES['file']['name']);

            $file = $this->storage->createFile($this->user, $original_filename);
            $destination = $file->getRealPath();
            if (move_uploaded_file($_FILES['file']['tmp_name'], $destination)) {

                if (filesize($destination) > Config::getMaxFileSize()) {
                    $this->print("File is too big, max allowed: " . Config::getMaxFileSize());
                    if (!unlink($destination)) {
                        $this->print("Error while deleting file $destination");
                    }
                    Chunk::end(400); # Bad Request
                }

                $this->sendContentType('text/html');
                print("<p>Your file has been successfully uploaded.</p>" . PHP_EOL);
                $url = $file->getUrl();
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
        $uri = $this->determinePathFromRequest();

        $this->print("Putting new file for user $this->user");
        $this->print("uri: $uri");

        $original_filename = basename($uri);
        $file = $this->storage->createFile($this->user, $original_filename);
        $destination = $file->getRealPath();
        $file->store();

        if (filesize($destination) > Config::getMaxFileSize()) {
            $this->print("File is too big, max allowed: " . Config::getMaxFileSize());
            if (!unlink($destination)) {
                $this->print("Error while deleting file $destination");
            }
            Chunk::end(400); # Bad Request
        }

        $this->sendContentType('text/uri-list');
        print($file->getUrl());
        print(PHP_EOL);
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

    public function determineBaseUrlFromRequest() {
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
        $result = trim($result, '/');
        $this->print("Determined base URL: $result");
        return $result;
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
