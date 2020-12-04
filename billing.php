<?php
namespace Costya;

require 'vendor/autoload.php';

use Aws\CostExplorer;

define('APP_NAME', 'costya');

class CostException extends \Exception {}

class Month {
  protected $start;

  public function __construct($m) {
    if ($m) {
      $this->start = new \DateTimeImmutable($m);
    } else {
      $this_month = new \DateTimeImmutable(date('Y-m-01'));
      $this->start = $this_month->sub(new \DateInterval('P1M'));
    }
  }

  protected function format($dt, $format = 'Y-m-d') {
    return $dt->format($format);
  }

  public function getStart() {
    return $this->format($this->start);
  }

  public function getNext() {
    return $this->format(
      $this->start->add(new \DateInterval('P1M'))
    );
  }

  public function __toString() {
    return $this->format($this->start, 'Y-m');
  }
}

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

  public function __construct($month) {
    $cache = new FileCache($month);
    try {
      $this->cost_data = $cache->load();
    } catch (CostException $err) {
      error_log($err->getMessage());
    }
  }
}

$args = getopt('d:');
$month = new Month(isset($args['d']) ? $args['d'] : false);
$costs = new Costs($month);
