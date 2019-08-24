<?php namespace EugeneErg\SQLPreprocessor;

abstract class Translater {

    protected $vars = [];
    private $path;
    private $blocks = [];

    abstract protected function getQuery(Raw $query, array $fields = null);

    final private static function pathinfo($path) {
        $r = new \StdClass();
        if (false === $r->filename = mb_strrchr($path, '/')) {
            $r->filename = $path;
            $r->dirname = '';
        }
        else {
            $r->filename = mb_substr($r->filename, 1);
            $r->dirname = mb_substr($path, 0, - mb_strlen($r->filename));
        }
        if (false === $r->extension = mb_strrchr($r->filename, '.')) {
            $r->extension = '';
            $r->basename = $r->filename;
        }
        else {
            $r->basename = mb_substr($r->filename, 0, - mb_strlen($r->extension));
        }
        return $r;
    }
    final protected function getPath($path = '', $ext = '', $folder = null, $prefix = '') {
        if (mb_strlen($path) && $path[0] == '/') {
            return $path;
        }
        $pathInfo = Self::pathinfo($path);
        return $this->path .
            '/' . $pathInfo->dirname .
            (is_null($folder) ? '' : $folder . '/') .
            $prefix . $pathInfo->basename .
            ($pathInfo->extension === '' ? $ext : $pathInfo->extension);
    }
    final protected function makePartial($path, array $vars = []) {
        extract($this->vars);
        extract($vars);
        ob_start();
        require($this->getPath($path, '.php', null));
        return ob_get_clean();
    }
    private function __construct() {
        $rClass = new \ReflectionClass($this);
        $classPath = str_replace('\\', '/', $rClass->getFileName());
        $info = Self::pathinfo($classPath);
        $this->path = $info->dirname . mb_strtolower($info->basename);
    }
    final public static function instance() {
        static $instance = [];
        if (!isset($instance[$class = get_called_class()])) {
            $instance[$class] = new $class();
        }
        return $instance[$class];
    }
    final public function translate(Raw $query) {
        return $this->getQuery($query);
    }
}