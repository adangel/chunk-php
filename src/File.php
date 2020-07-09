<?php
namespace com\github\adangel\chunkphp;

use finfo;

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

    function getRealPath() : string {
        return $this->realPath;
    }

    function getName() : string {
    }

    function getUuid() : string {
        return $this->uuid;
    }

    function getType() : string {
        $finfo = new finfo(FILEINFO_MIME);
        $mimetype = $finfo->file($this->realPath);
        return $mimetype;
    }

    function getSize() : int {
        return filesize($this->realPath);
    }

    function getUrl() : string {
        return $this->url;
    }

    function hasContent() : bool {
        return file_exists($this->realPath);
    }

    /**
     * Send the content of the referenced file to the given stream.
     * If no stream is given, uses php://output by default.
     */
    function send($stream = NULL) : void {
        if ($stream == NULL) {
            $stream = fopen('php://output', 'w');
        }
        $handle = fopen($this->realPath, 'r');
        while (!feof($handle)) {
            $contents = fread($handle, 8192);
            fwrite($stream, $contents);
        }
        fclose($handle);
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
