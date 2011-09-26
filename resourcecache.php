<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Defines the qtype_opaque_resource_cache class.
 *
 * @package    qtype
 * @subpackage opaque
 * @copyright  2006 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();


/**
 * This class caches the resources belonging a particular question.
 *
 * There are synchronisation issues if two students are doing the same question
 * at the same time.
 *
 * @copyright  2010 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_opaque_resource_cache {
    /** Prefix used for CSS files. */
    const CSS_PREFIX = '__styles_';

    protected $folder; // Path to the folder where resources for this question are cached.
    protected $metadatafolder; // Path to the folder where mime types are stored.
    protected $baseurl; // initial part of the URL to link to a file in the cache.

    /**
     * Create a new qtype_opaque_resource_cache for a particular remote question.
     * @param int $engineid the id of the question engine.
     * @param string $remoteid remote question id, as per Opaque spec.
     * @param string $remoteversion remote question version, as per Opaque spec.
     */
    public function __construct($engineid, $remoteid, $remoteversion) {
        global $CFG;
        $folderstart = $CFG->dataroot . '/opaqueresources/' . $engineid . '/' .
        $remoteid . '/' . $remoteversion;
        $this->folder = $folderstart . '/files';
        if (!is_dir($this->folder)) {
            $this->mkdir_recursive($this->folder);
        }
        $this->metadatafolder = $folderstart . '/meta';
        if (!is_dir($this->metadatafolder)) {
            $this->mkdir_recursive($this->metadatafolder);
        }
        $this->baseurl = "/question/type/opaque/file.php/{$engineid}/{$remoteid}/{$remoteversion}/";
    }

    /**
     * @param string $filename the file name.
     * @return the full path of a file with the given name.
     */
    public function file_path($filename) {
        return $this->folder . '/' . $filename;
    }

    /**
     * @param string $filename the file name.
     * @return the full path of a file with the given name.
     */
    public function file_meta_path($filename) {
        return $this->metadatafolder . '/' . $filename;
    }

    /**
     * @param string $filename the file name.
     * @return the URL to access this file.
     */
    public function file_url($filename) {
        return new moodle_url($this->baseurl . $filename);
    }

    /**
     * File name used to store the CSS of the question, question session id is appended.
     */
    function stylesheet_filename($questionsessionid) {
        return self::CSS_PREFIX . $questionsessionid . '.css';
    }

    /**
     * @param string $filename the file name.
     * @return the URL to access this file.
     */
    public function file_mime_type($filename) {
        $metapath = $this->file_meta_path($filename);
        if (file_exists($metapath)) {
            return file_get_contents($metapath);
        }
        return mimeinfo('type', $filename);
    }

    /**
     * @param string $filename the name of the file to look for.
     * @return true if this named file is in the cache, otherwise false.
     */
    public function file_in_cache($filename) {
        return file_exists($this->file_path($filename));
    }

    /**
     * Serve a file from the cache.
     * @param string $filename the file name.
     */
    public function serve_file($filename) {
        if (!$this->file_in_cache($filename)) {
            header('HTTP/1.0 404 Not Found');
            header('Content-Type: text/plain;charset=UTF-8');
            echo 'File not found';
            exit;
        }
        $mimetype = $this->file_mime_type($filename);

        // Handle If-Modified-Since
        $file = $this->file_path($filename);
        $filedate = filemtime($file);
        $ifmodifiedsince = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ?
        $_SERVER['HTTP_IF_MODIFIED_SINCE'] : false;
        if ($ifmodifiedsince && strtotime($ifmodifiedsince) >= $filedate) {
            header('HTTP/1.0 304 Not Modified');
            exit;
        }
        header('Last-Modified: '.gmdate('D, d M Y H:i:s', $filedate).' GMT');

        // Type
        header('Content-Type: ' . $mimetype);
        header('Content-Length: ' . filesize($file));

        // Output file
        session_write_close(); // unlock session during fileserving
        readfile($file);
    }

    /**
     * Store a file in the cache.
     *
     * @param string $filename the name of the file to cache.
     * @param string $mimetype the type of the file to cache.
     * @param string $content the contents to write to the file.
     */
    public function cache_file($filename, $mimetype, $content) {
        file_put_contents($this->file_path($filename), $content);
        file_put_contents($this->file_meta_path($filename), $mimetype);
    }

    /**
     * Add the resources from a particular response to the cache.
     * @param array $resources as returned from start or process Opaque methods.
     */
    public function cache_resources($resources) {
        if (empty($resources)) {
            return;
        }

        foreach ($resources as $resource) {
            $mimetype = $resource->mimeType;
            if (strpos($resource->mimeType, 'text/') === 0 && !empty($resource->encoding)) {
                $mimetype .= ';charset=' . $resource->encoding;
            }
            $this->cache_file($resource->filename, $mimetype, $resource->content);
        }
    }

    /**
     * List the resources cached for this question.
     * @return array list of resource names.
     */
    public function list_cached_resources() {
        $filepaths = glob($this->folder . '/*');
        if (!is_array($filepaths)) {
            // If an error occurrs, say that we have no files cached.
            $filepaths = array();
        }
        $pathlen = strlen($this->folder . '/');
        $files = array();
        foreach ($filepaths as &$filepath) {
            $file = substr($filepath, $pathlen);
            if (strpos($file, self::CSS_PREFIX) !== 0) {
                $files[] = $file;
            }
        }
        return $files;
    }

    /**
     * This function exists because mkdir(folder,mode,TRUE) doesn't work on our server.
     * Safe to call even if folder already exists (checks)
     * @param string $folder Folder to create
     * @param int $mode Mode for creation (default 0755)
     * @return bool True if folder (now) exists, false if there was a failure
     */
    protected function mkdir_recursive($folder, $mode='') {
        if (is_dir($folder)) {
            return true;
        }
        if ($mode == '') {
            global $CFG;
            $mode = $CFG->directorypermissions;
        }
        if (!$this->mkdir_recursive(dirname($folder), $mode)) {
            return false;
        }
        return mkdir($folder, $mode);
    }
}
