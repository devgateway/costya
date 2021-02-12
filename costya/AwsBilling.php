<?php
namespace devgateway\costya;

use Aws\CostExplorer\CostExplorerClient;

class AwsBilling {
  protected $data;

  public function __construct($month) {
    $cache = new FileCache($month);
    try {
      $this->data = $cache->load();
    } catch (CacheMiss $err) {
      error_log($err->getMessage());
      $this->data = $this->fetchData($month);
      $cache->save($this->data);
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
