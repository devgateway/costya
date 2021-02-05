#!/usr/bin/env php
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

  public function getFirstDay() {
    return $this->first_day->format($this->date_format);
  }

  public function getDayAfterLast() {
    return $this->day_after_last->format($this->date_format);
  }

  public function __toString() {
    return $this->first_day->format('Y-m');
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

class BillingCodes {
  protected $default_code, $codes = [];

  public function __construct($settings_file) {
    $handle = fopen($settings_file, 'r');
    if ($handle === FALSE) {
      die("Unable to open for reading: $settings_file");
    }
    $first_row = TRUE;
    while ($items = fgetcsv($handle) !== FALSE) {
      if ($first_row) {
        if (strrchr($items[1], ':') === FALSE) {
          error_log('First line in CSV is a header, skipping');
          continue;
        } else {
          $this->default_code = str_replace("\r", '', $items[1]);
          $first_row = FALSE;
        }
      }

      $this->codes[$items[0]] = str_replace("\r", '', $items[1]);
    }
    fclose($handle);
  }

  public function getDefaultCode() {
    return $this->default_code;
  }

  public function toArray() {
    return $this->codes;
  }
}

class ExpensifyCsv {
  protected $billed_costs, $billing_codes;

  public function __construct($billing_codes) {
    $this->billing_codes = $billing_codes->toArray();
  }

  public function setCosts($costs, $invoice_cents) {
    $default_billing = $this->getDefaultCode();

    $total = 0;
    foreach ($costs->get() as $project => $usd) {
      $cents = (int) round($usd * 100);
      $total += $cents;
      if (isset($this->billing_codes[$project])) {
        $billing_code = $this->billing_codes[$project];
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
      printf('"%s","%s",%.2f,"%s","%s"' . "\n", $merchant, $formatted_date, $cents / 100.0, $category, $code);
    }
  }
}

$args = getopt('d:b:t:');
$invoice_date = $args['d'];
$settings_file = $args['b'];
$invoice_cents = (int) ($args['t'] * 100);

$month = new Month($invoice_date);
$costs = new ProjectCosts($month);
$codes = new BillingCodes($settings_file);
$csv = new ExpensifyCsv($codes);
$csv->setCosts($costs, $invoice_cents);
$csv->display($invoice_date);
