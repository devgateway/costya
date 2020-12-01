<?php
namespace Costya;

require 'vendor/autoload.php';

use Aws\CostExplorer;

define('APP_NAME', 'costya');

class CostException extends \Exception {}

class FileCache {
  protected $path;

  public function __construct() {
    $env = getenv();
    if (isset($env['XDG_CACHE_HOME'])) {
      $path = array($env['XDG_CACHE_HOME']);
    } elseif (isset($env['HOME'])) {
      $path = array($env['HOME'], '.cache');
    } else {
      throw new CostException('Can\'t determine cache directory: neither XDG_CACHE_HOME nor HOME are set');
    }
    array_push($path, APP_NAME, 'data');
    $this->path = join(DIRECTORY_SEPARATOR, $path);
  }

  public function load() {
    if (false === $data = file_get_contents($this->path)) {
      throw new CostException("Can't read cached data from {$this->path}");
    }
    return unserialize($data);
  }

  public function save($data) {
    mkdir(dirname($this->path));
    if (false === file_put_contents(serialize($data))) {
      throw new CostException('Can\'t cache data');
    }
  }
}

class Costs {
  protected $cost_data;

  public function __construct() {
    $cache = new FileCache();
    try {
      $this->cost_data = $cache->load();
    } catch (CostException $err) {
      error_log($err->getMessage());
    }
  }
}

$costs = new Costs();
