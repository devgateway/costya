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

  public function __construct($date, $date_format = 'Y-m-d') {
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

class AwsBilling {
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
      $tag = explode('$', $group['Keys'][0])[1];
      $result[$tag] = $group['Metrics']['BlendedCost']['Amount'];
    }
    return $result;
  }
}

class ExpensifyBilling {
  protected $default_code, $codes = [], $costs;

  public function __construct($codes_csv, $aws_billing, $invoice_cents) {
    ini_set('auto_detect_line_endings', TRUE);
    $handle = fopen($codes_csv, 'r');
    if ($handle === FALSE) {
      die("Unable to open for reading: $codes_csv\n");
    }

    $first_row = TRUE;
    while (($items = fgetcsv($handle)) !== FALSE) {
      if ($first_row) {
        if (strrchr($items[1], ':') === FALSE) {
          error_log('First line in CSV is a header, skipping');
          continue;
        } else {
          $this->default_code = $items[1];
          $first_row = FALSE;
        }
      }

      $this->codes[$items[0]] = $items[1];
    }

    fclose($handle);

    $this->costs = $this->getCosts($aws_billing, $invoice_cents);
  }

  protected function getCosts($aws_billing, $invoice_cents) {
    $costs = [];
    $total = 0;
    foreach ($aws_billing->get() as $tag => $usd) {
      $cents = (int) round($usd * 100);
      $total += $cents;
      if (isset($this->codes[$tag])) {
        $billing_code = $this->codes[$tag];
      } else {
        error_log("No billing code for '$tag', using '" . $this->default_code . "'");
        $billing_code = $this->default_code;
      }
      if (!isset($this->costs[$billing_code])) {
        $costs[$billing_code] = 0;
      }
      $costs[$billing_code] += $cents;
    }

    $diff = $invoice_cents - $total;
    if ($diff != 0) {
      error_log(sprintf('Adjusting %s by %+d cents', $this->default_code, $diff));
      $costs[$this->default_code] += $diff;
    }

    return $costs;
  }

  public function toCsv($handle, $invoice_date) {
    $formatted_date = $invoice_date->format('Y-m-d H:i:s');

    foreach ($this->costs as $code => $cents) {
      fputcsv($handle, [
        'Amazon Web Services',
        $formatted_date,
        sprintf('%.2f', $cents / 100.0),
        '65000-IT Services, Softwares, Hosting & Subscriptions',
        $code
      ]);
    }
  }
}

class Invoice {
  public $total, $date;

  public function __construct($handle) {
    $dates = [];
    $line_num = 1;
    $this->total = 0;

    while (($line = fgets($handle, 8192)) !== FALSE) {

      if (strpos($line, 'Total for this invoice') !== FALSE) {
        if (preg_match('/\$(\S+)\s*$/', $line, $matches) === 1) {
          $this->total += $matches[1];
        } else {
          die("Can't parse line $line_num for invoice total\n");
        }
      } elseif (strpos($line, 'Invoice Date:') !== FALSE) {
        if (preg_match('/[[:upper:]][^[:upper:]]+$/', $line, $matches) === 1) {
          $dates[] = new \DateTimeImmutable($matches[0]);
        } else {
          die("Can't parse line $line_num for invoice date\n");
        }
      }

      $line_num++;
    }

    $this->date = max($dates);

    error_log("Using invoice total of \${$this->total} on {$this->date->format('F d, Y')}");
  }
}

$invoice = new Invoice(STDIN);
$aws_billing = new AwsBilling(new Month($invoice->date));
$expensify_billing = new ExpensifyBilling($argv[1], $aws_billing, (int) ($invoice->total * 100));
$expensify_billing->toCsv(STDOUT, $invoice->date);
