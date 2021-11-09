<?php
namespace devgateway\costya;

use Aws\CostExplorer\CostExplorerClient;

class AwsBilling {
  protected $charges = [], $tax = 0;

  public function getCharges() {
    return $this->charges;
  }

  public function getTax() {
    return $this->tax;
  }

  public function __construct($month) {
    $cache = new FileCache($month);
    try {
      $response = $cache->load();
    } catch (CacheMiss $err) {
      error_log($err->getMessage());
      $response = $this->fetchData($month);
      $cache->save($response);
    }

    foreach ($response['ResultsByTime'][0]['Groups'] as $group) {
      $tag = strtoupper(explode('$', $group['Keys'][0])[1]);
      $service = $group['Keys'][1];
      $amount = $group['Metrics']['BlendedCost']['Amount'];

      if (!isset($this->charges[$tag])) {
        $this->charges[$tag] = 0;
      }

      if ($service == 'Tax') {
        $this->tax += $amount;
      } else {
        $this->charges[$tag] += $amount;
      }
    }
  }

  protected function fetchData($month) {
    $client = new CostExplorerClient([
      'profile' => APP_NAME,
      'region' => 'us-east-1',
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
        ],
        [
          'Type' => 'DIMENSION',
          'Key' => 'SERVICE'
        ]
      ]
    ]);

    return $result->toArray();
  }
}
