<?php
namespace devgateway\costya;

define('APP_NAME',  'costya');

class FileCache {
  protected $path;

  public function __construct($month) {
    $env = getenv();
    if (isset($env['XDG_CACHE_HOME'])) {
      $path = array($env['XDG_CACHE_HOME']);
    } elseif (isset($env['HOME'])) {
      $path = array($env['HOME'], '.cache');
    } else {
      throw new CostException('Can\'t determine cache directory: neither XDG_CACHE_HOME nor HOME are set');
    }
    array_push($path, APP_NAME, (string) $month);
    $this->path = join(DIRECTORY_SEPARATOR, $path);
  }

  public function load() {
    if (false === $data = @file_get_contents($this->path)) {
      throw new CostException("Can't read cached data from {$this->path}");
    }
    return unserialize($data);
  }

  public function save($data) {
    $dir = dirname($this->path);
    if (!file_exists($dir)) {
      mkdir($dir);
    }

    if (false === file_put_contents($this->path, serialize($data))) {
      throw new CostException('Can\'t cache data');
    }
  }
}
