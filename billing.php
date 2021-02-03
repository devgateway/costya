<?php
namespace Costya;

require 'vendor/autoload.php';

use Aws\CostExplorer;

define('APP_NAME',  'costya');
define('REGION',    'us-east-1');

class CostException extends \Exception {}

class Month {
  protected $first_day, $day_after_last, $date_format;

  public function __construct($invoice_date, $date_format = 'Y-m-d') {
    $date = new \DateTimeImmutable($invoice_date);
    $this->day_after_last = new \DateTimeImmutable($date->format('Y-m-01'));
    $this->first_day = $this->day_after_last->sub(new \DateInterval('P1M'));
    $this->date_format = $date_format;
  }

  protected function format($dt, $format = 'Y-m-d') {
    return $dt->format($format);
  }

  public function getFirstDay() {
    return $this->first_day->format($this->date_format);
  }

  public function getDayAfterLast() {
    return $this->day_after_last->format($this->date_format);
  }

  public function __toString() {
    return $this->format($this->first_day, 'Y-m');
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

class ProjectCosts {
  protected $data;

  public function __construct($month) {
    $cache = new FileCache($month);
    try {
      $this->data = $cache->load();
    } catch (CostException $err) {
      error_log($err->getMessage());
      $this->data = $this->getData($month);
      try {
        $cache->save($this->data);
      } catch (CostException $err) {
        error_log($err->getMessage());
      }
    }
  }

  protected function getData($month) {
    $client = new \Aws\CostExplorer\CostExplorerClient([
      'profile' => APP_NAME,
      'region' => REGION,
      'version' => 'latest'
    ]);

    $result = $client->getCostAndUsage([
      'Metrics' => ['BlendedCost'],
      'TimePeriod' => [
        'Start' => $month->getFirstDay(),
        'End' => $month->getDayAfterLast()
      ],
      'Granularity' => 'MONTHLY',
      'GroupBy' => [
        [
          'Type' => 'TAG',
          'Key' => 'Project'
        ]
      ]
    ]);

    return $result->toArray();
  }

  public function get() {
    $result = [];
    foreach ($this->data['ResultsByTime'][0]['Groups'] as $group) {
      $project = explode('$', $group['Keys'][0])[1];
      $result[$project] = $group['Metrics']['BlendedCost']['Amount'];
    }
    return $result;
  }
}

class ExpensifyCsv {
  protected $billed_costs, $project_billing;

  public function __construct($settings_file) {
    $this->project_billing = json_decode(file_get_contents($settings_file), true);
  }

  public function setCosts($costs, $invoice_cents) {
    $default_billing = $this->project_billing[''];

    $total = 0;
    foreach ($costs->get() as $project => $usd) {
      $cents = (int) ($usd * 100);
      $total += $cents;
      if (isset($this->project_billing[$project])) {
        $billing_code = $this->project_billing[$project];
      } else {
        error_log("No billing code for '$project', using '$default_billing'");
        $billing_code = $default_billing;
      }
      if (!isset($this->billed_costs[$billing_code])) {
        $this->billed_costs[$billing_code] = 0;
      }
      $this->billed_costs[$billing_code] += $cents;
    }

    $diff = $invoice_cents - $total;
    if ($diff != 0) {
      error_log(sprintf('Adjusting %s by %+d cents', $default_billing, $diff));
      $this->billed_costs[$default_billing] += $diff;
    }
  }

  public function display($invoice_date) {
    $merchant = 'Amazon Web Services';
    $category = '65000-IT Services, Softwares, Hosting & Subscriptions';
    $formatted_date = (new \DateTimeImmutable($invoice_date))->format('Y-m-d H:i:s');

    foreach ($this->billed_costs as $code => $cents) {
      printf('"%s",%s,%.2f,"%s","%s"' . "\n", $merchant, $formatted_date, $cents / 100.0, $category, $code);
    }
  }
}

$args = getopt('d:b:t:');
$invoice_date = $args['d'];
$settings_file = $args['b'];
$invoice_cents = (int) ($args['t'] * 100);

$month = new Month($invoice_date);
$costs = new ProjectCosts($month);
$csv = new ExpensifyCsv($settings_file);
$csv->setCosts($costs, $invoice_cents);
$csv->display($invoice_date);
