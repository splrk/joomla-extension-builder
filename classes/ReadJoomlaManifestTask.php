<?php

require_once "phing/Task.php";
require_once "phing/util/FileUtils.php";

define(
    'JOOMLA_INDEX_FILE',
    '<html><head><title></title></head><body bgcolor="#ffffff">&nbsp;</body></html>'
);

class ReadJoomlaManifestTask extends Task
{
    protected $excludes;
    protected $tempManifest;
    protected $manifest;
    protected $includesArray = array();
    protected $indexFiles = array();
    protected $rewriteFolders;
    protected $prefix;
    protected $defaultExcludes = true;
    protected $createIndexFiles = true;
    protected $deleteOldIndexFiles = true;

    public function setManifest(PhingFile $manifest)
    {
        if ($manifest->exists()) {
            try {
                $this->manifest = simplexml_load_file($manifest->getAbsolutePath());
            }
            catch (Exception $e) {
                throw new BuildException(
                    'Could Not Load Manifest File: ' . $e->getMessage()
                );
            }
        }
        else {
            throw new BuildException(
                'Manifest File Does not exists: ' . $manifest->getAbsolutePath()
            );
        }
    }

    public function setTempManifest(PhingFile $tempManifest)
    {
        $this->tempManifest = $tempManifest;
        $this->rewriteFolders = true;
    }
    
    public function setPropertiesPrefix($propertiesprefix)
    {
        $this->prefix = $propertiesprefix;
    }

    public function setExcludes($excludes)
    {
        $this->excludes = explode(',', $excludes);
    }

    public function setDefaultExcludes($defaultExcludes)
    {
        $this->defaultExcludes = $defaultExcludes;
    }

    public function setCreateIndexFiles($createIndexFiles)
    {
        $this->createIndexFiles = $create_indexFiles;
    }

    public function setForceNewIndexFiles($force_new_indexFiles)
    {
        $this->deleteOldIndexFiles = $force_new_indexFiles;
    }

    public function getDefaultExcludes()
    {
        return array(
            '#*#',
            '#*',
            '%*%',
            'CVS',
            'CVS/**',
            '.cvsignore',
            'SCSS',
            'SCSS/**',
            'vssver.scc',
            '.svn',
            '.svn/**',
            '._*',
            '.DS_Store',
            '.darcs',
            '.darcs/**',
            '.git',
            '.git/**'
        );
    }


    public function init()
    {
        $this->project = $this->getOwningTarget()->getProject(); 
        $this->includesArray = array();
        $this->index_dir_includes = array();
        $this->defaultExcludes = $this->defaultExcludes  
            ? $this->getDefaultExcludes()
            : array();
        $this->excludes = $this->excludes ?: array();
        $this->prefix = $this->prefix ?: 'joomla_extension';
        $this->createIndexFiles = $this->create_indexFiles || $this->deleteOldIndexFiles;
    }

    private function convertFnmatchToRegex($fnmatch)
    {
        return '~^' . strtr(preg_quote($fnmatch, '~'), array(
                    '\*' => '.*?',
                    '\?' => '.',
                    '\*\*' => '([^/]+/)*[^/]+')
        ) . '$~i';
    }
    
    public function main()
    {
        $this->checkManifestFile();
        $this->saveManifestFiles();
        $this->saveManifestProperties();
        $this->saveTempManifest();
    }

    private function checkManifestFile()
    {
        if (! isset($this->manifest) ) {
            throw new BuildException('The manifest property must be set');
        } else if (! is_a($this->manifest, 'SimpleXMLElement')) {
            throw new BuildException('The manifest provided could not be parsed ax XML');
        } else if ( !preg_match('/extension|install/i', $this->manifest->getName())) {
             throw new BuildException('A Joomla extension\'s manifest\'s root element should be "extension"');
        }
    }

    private function saveManifestFiles()
    {
        $this->excludes = array_map(
            array($this, 'convertFnmatchToRegex'),
            array_merge(
                $this->excludes, array('**/.', '**/..', '**index.html'),
                $this->defaultExcludes));

        $this->parseFilesList('files');
        $this->parseFilesList('media');
        $this->parseFilesList('administration/files');
        $this->parseFilesList('languages', 'language', '', false);
        $this->parseFilesList('administration/languages', 'language', '', false);
        $this->parseFilesList('.', 'scriptfile', 'schemapath', false);

        $this->_setProperty('includes', implode(',',$this->includesArray));
    }

    private function parseFilesList($xpath, $filename_element = 'filename',
        $folder_element = 'folder', $rewriteFolders = true
    ) {
        foreach ($this->manifest->xpath($xpath) as $element) {
            $index_dirs = array();
            $base_dir = (string) $element['folder'];

            foreach ($element->$filename_element as $filename) {
                $this->includesArray[] = ($base_dir ? $base_dir . '/' : '') . (string) $filename;
                $this->addNewDirs($base_dir, $filename, $index_dirs);
            }


            $folders = $element->$folder_element;
            $index = 0;
            while(($folder = $folders[$index])) {

                if($rewriteFolders && $this->rewriteFolders) {
                    $index_dirs = array_merge(
                        $index_dirs,
                        $this->iterateDirectories($base_dir, $folder, $element, $filename_element));
                }
                else {
                     $index_dirs = array_merge(
                        $index_dirs,
                        $this->iterateDirectories($base_dir, $folder));
                    $index++;
                }
            }

            if($rewriteFolders && $this->rewriteFolders) {
                $this->addIndexFiles($index_dirs, $element, $filename_element);
                $this->addFolderElements($index_dirs, $element, $folder_element);
            } else {
                $this->addIndexFiles($index_dirs);
            }
        }
    }

    private function addFolderElements(array $dirs, SimpleXMLElement $element, $folder_tag)
    {
        foreach($dirs as $directory) {
            if (is_dir($directory)) {
                $element->addChild(
                    $folder_tag,
                    preg_replace('~^' . (string) $element['folder'] . '/~', '', $directory)
                );
            }
        }
    }
            
    private function addNewDirs($base_dir, $filename, &$added_dirs)
    {
        if (!is_dir($base_dir . '/' . $filename) ) {
            $filename = dirname($filename);
        }

        $new_dirname = $base_dir . '/';
        foreach(explode('/', $filename) as $dir_name) {
            $new_dirname = $new_dirname . $dir_name . '/';
            if (!in_array($new_dirname, $added_dirs)) {
                $added_dirs[] = $new_dirname;
            }
        }
    }

    private function iterateDirectories($base_dir, $folder_element,
        $parent_element = null, $rewrite_to_filename_tag = ''
    ) {
        $rewriteXML = $parent_element && $rewrite_to_filename_tag;
        $directories = array();
        $this->addNewDirs($base_dir, $folder_element, $directories);

        $base_directory_path = $base_dir ? $base_dir . '/' : './';
        $folder_path = $base_directory_path . (string) $folder_element;

        $directory_iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($folder_path)
        );
        foreach ($directory_iterator as $filename => $file) {
            if (!preg_match('~/\.\.$~', $filename)) {
                $file_path = preg_replace('~/\.$~', '', $filename);
                if ($file->isDir()) {
                    $this->addNewDirs(
                        dirname($file_path),
                        basename($file_path),
                        $directories
                    );
                } else if ($rewriteXML && ! $this->isExcluded($filename) ) {
                    $this->includesArray[] = $filename;
                    $parent_element->addChild(
                        $rewrite_to_filename_tag,
                        preg_replace('~^' . $base_directory_path . '~', '', $filename)
                    );
                }
            }
        }

        if ($rewrite_to_filename_tag) {
            unset($folder_element[0]);
        }

        return $directories;
    }

    private function isExcluded($filepath)
    {
        $excluded = false;
        $index = 0;
        while(!$excluded && count($this->excludes) > $index) {
            $excluded = (bool) preg_match($this->excludes[$index], $filepath);
            $index++;
        }

        return $excluded;
    }

    protected function addIndexFiles($dirs, $parent_element = null, $rewrite_to_filename_tag = 'filename')
    {
        if (!$this->createIndexFiles) {
            return;
        }

        foreach($dirs as $folder_name) {
            $index_file = $folder_name . '/index.html';

            if (file_exists($folder_name)) {
                if (!file_exists($index_file) || $this->deleteOldIndexFiles) {
                    file_put_contents($index_file, JOOMLA_INDEX_FILE);
                }

                if (!in_array($index_file, $this->includesArray)) {
                    $this->includesArray[] = $index_file;
                }

                if ($parent_element && $filename_element_tag) {
                    $parent_element->addChild($rewrite_to_filename_tag, $index_file);
                }
            }
        }
    }

    protected function saveTempManifest()
    {
        if (is_a($this->tempManifest, 'PhingFile')) {
            $dom = new DOMDocument('1.0');
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = true;
            $dom->loadXML($this->manifest->asXML());
            file_put_contents($this->tempManifest->getAbsolutePath(), $dom->saveXML());
        }
    }

    private function saveManifestProperties()
    {
        $this->saveTypeProperties();
        $prefix = $this->project->getUserProperty($this->prefix . '.type_prefix');
        $name = preg_replace(
            '/^' . preg_quote($prefix, '/') . '_/',
            '',
            strtolower($this->manifest->name)
        );

        $this->_setProperty('site_folder', (string) $this->manifest->files['folder']);
        $this->_setProperty('admin_folder', (string) $this->manifest->administration->files['folder']);
        $this->_setProperty('name', $name);
        $this->_setProperty('jver', (string) $this->manifest['version']);
    }

    protected function saveTypeProperties()
    {
        $type = strtolower($this->manifest['type']);
        $this->_setProperty('type', $type);
        switch($type)
        {
        case 'component':
        case 'module':
        case 'library':
            $this->_setProperty('type_prefix', substr($type, 0, 3));
            $this->_setProperty('subdir', preg_replace('/y$/', 'ie', $type) . 's');
            break;
        case 'plugin':
            $this->_setProperty('type_prefix', 'plg');
            $this->_setProperty('subdir', $type . 'plugins');
            break;
        case 'template':
            $this->_setProperty('type_prefix', 'tpl');
            $this->_setProperty('subdir', $type . 'templates');
            break;
        case 'package':
            $this->_setProperty('type_prefix', 'pkg');
            $this->_setProperty('subdir', $type . 'packages');
            break;
        default:
            throw new BuildException(
                $this->maifest['type'] . ' is not a valid Joomla Extension Type'
            );
        }
    }

    private function _setProperty($name, $value)
    {
        $this->project->setUserProperty($this->prefix . '.' . $name, $value);
    }
}
