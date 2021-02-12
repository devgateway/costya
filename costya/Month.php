<?php
namespace devgateway\costya;

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
