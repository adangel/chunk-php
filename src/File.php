<?php
namespace com\github\adangel\chunkphp;

use Exception;
use PharData;
use ZipArchive;

class File {
    private $realPath;
    private $user;
    private $uuid;
    private $url;

    function __construct($realPath, $baseurl, $user, $uuid) {
        $this->realPath = $realPath;
        $this->user = $user;
        $this->uuid = $uuid;
        $this->url = $baseurl . '/' . $user . '/' . $uuid;

        $dir = dirname($this->realPath);
        if (!file_exists($dir)) {
            mkdir($dir);
        }
    }

    function __toString() : string {
        return $this->realPath;
    }

    function getRealPath() : string {
        return $this->realPath;
    }

    function getName() : string {
    }

    function getUser() : string {
        return $this->user;
    }

    function getUuid() : string {
        return $this->uuid;
    }

    function getType($pathInArchive = NULL) : string {
        if (!isset($pathInArchive)) {
            return MimeTypes::determineReal($this->realPath);
        }

        return MimeTypes::determineVirtual($pathInArchive);
    }

    function getSize($pathInArchive = NULL) : int {
        if (!isset($pathInArchive)) {
            return filesize($this->realPath);
        }

        if ($this->isZip()) {
            $entry = $this->getZipEntry($pathInArchive);
        } else if ($this->isTar()) {
            $entry = $this->getTarEntry($pathInArchive);
        }
        if ($entry !== FALSE) {
            return $entry['size'];
        }

        return FALSE;
    }

    function getUrl() : string {
        return $this->url;
    }

    function hasContent($pathInArchive = NULL) : bool {
        if (!file_exists($this->realPath)) {
            return FALSE;
        }

        if (!isset($pathInArchive)) {
            return TRUE;
        }

        $entry = FALSE;
        if ($this->isZip()) {
            $entry = $this->getZipEntry($pathInArchive);
        } else if ($this->isTar()) {
            $entry = $this->getTarEntry($pathInArchive);
        }
        if ($entry !== FALSE) {
            return TRUE;
        }
        return FALSE;
    }

    private function isZip() : bool {
        return strpos($this->getType(), 'application/zip') === 0;
    }

    /** @return array|false */
    private function getZipEntry($pathInArchive) {
        $path = File::sanitizeArchivePath($pathInArchive);
        if ($path === FALSE) {
            return FALSE;
        }

        $zip = new ZipArchive;
        $res = $zip->open($this->realPath);
        if ($res === FALSE) {
            throw new Exception("Couldn't open zip file {$this->realPath}: $res");
        }
        $entry = $zip->statName($path);
        $zip->close();
        return $entry;
    }

    // https://stackoverflow.com/questions/10064499/php-normalize-path-of-not-existing-directories-to-prevent-directory-traversals
    public static function sanitizeArchivePath($path) {
        if ($path === NULL) {
            return FALSE;
        }

        $result = str_replace('\\', '/', $path); // change any backslashes to forward slashes
        $segments = explode('/', $result);
        $parts = array();
        foreach ($segments as $segment) {
            if ($segment === '.' || $segment === '') {
                continue;
            }
            if ($segment === '..') {
                if (count($parts) === 0) {
                    return FALSE;
                }
                array_pop($parts);
            } else {
                array_push($parts, $segment);
            }
        }

        if (count($parts) === 0) {
            return FALSE;
        }
        return implode('/', $parts);
    }

    private function sendZipEntry($stream, $pathInArchive) : void {
        $path = File::sanitizeArchivePath($pathInArchive);
        if ($path === FALSE) {
            throw new Exception("Invalid entry $pathInArchive");
        }

        $zip = new ZipArchive;
        $res = $zip->open($this->realPath);
        if ($res === FALSE) {
            throw new Exception("Couldn't open zip file {$this->realPath}: $res");
        }
        $fp = $zip->getStream($path);
        if ($fp === FALSE) {
            throw new Exception("Entry $path in file {$this->realPath} not found");
        }
        while (!feof($fp)) {
            $contents = fread($fp, 8192);
            fwrite($stream, $contents);
        }
        fclose($fp);
        $zip->close();
    }

    private function isTar() : bool {
        return strpos($this->getType(), 'application/x-tar') === 0
            || strpos($this->getType(), 'application/x-gtar') === 0;
    }

    /** @return array|false */
    private function getTarEntry($pathInArchive) {
        $path = File::sanitizeArchivePath($pathInArchive);
        if ($path === FALSE) {
            return FALSE;
        }

        $phar = new PharData($this->realPath);
        if ($phar->offsetExists($path)) {
            $file = $phar[$path];
            if ($file && $file->isFile()) {
                return array('size' => $file->getSize(), 'name' => $path);
            }
        }
        return FALSE;
    }

    private function sendTarEntry($stream, $pathInArchive) : void {
        $path = File::sanitizeArchivePath($pathInArchive);
        if ($path === FALSE) {
            throw new Exception("Invalid entry $pathInArchive");
        }

        $phar = new PharData($this->realPath);
        $file = $phar[$path];
        if ($file) {
            $fileObject = $file->openFile();
            while (!$fileObject->eof()) {
                $contents = $fileObject->fread(8192);
                fwrite($stream, $contents);
            }
        }
    }

    /**
     * Send the content of the referenced file to the given stream.
     * If no stream is given, uses php://output by default.
     */
    function send($stream = NULL) : void {
        if ($stream === NULL) {
            $stream = fopen('php://output', 'w');
        }
        $handle = fopen($this->realPath, 'r');
        while (!feof($handle)) {
            $contents = fread($handle, 8192);
            fwrite($stream, $contents);
        }
        fclose($handle);
    }

    function sendArchivePath($pathInArchive, $stream = NULL) : void {
        if ($stream === NULL) {
            $stream = fopen('php://output', 'w');
        }
        if ($this->isZip()) {
            $this->sendZipEntry($stream, $pathInArchive);
            return;
        } else if ($this->isTar()) {
            $this->sendTarEntry($stream, $pathInArchive);
            return;
        }
        throw new Exception("File {$this->realPath} is not an archive");
    }

    /**
     * Reads from the given stream and stores the content into the
     * file. If no stream is given, uses php://input by default.
     */
    function store($stream = NULL) : void {
        if ($stream == NULL) {
            $stream = fopen('php://input', 'r');
        }
        $handle = fopen($this->realPath, "w");
        while ($data = fread($stream, 8192)) {
            fwrite($handle, $data);
        }
        fclose($handle);
    }
}
