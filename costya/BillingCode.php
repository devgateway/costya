<?php
namespace devgateway\costya;

class BillingCode {
  public $project, $pillar;

  public function __construct($project, $pillar) {
    $this->project = $project;
    $this->pillar = $pillar;
  }

  public function format() {
    $escaped = array_map(function ($tag) {
      return str_replace(':', '\:', $tag);
    }, [$this->project, $this->pillar]);

    return implode(':', $escaped);
  }
}
