<?php
namespace devgateway\costya;

class Invoice {
  public $cents, $date;

  public function __construct($handle) {
    $dates = [];
    $line_num = 1;
    $this->cents = 0;

    while (($line = fgets($handle, 8192)) !== FALSE) {

      if (strpos($line, 'Total for this invoice') !== FALSE) {
        if (preg_match('/\$(\S+)\s*$/', $line, $matches) === 1) {
          $this->cents += (int) ($matches[1] * 100);
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

    $usd = $this->cents / 100.0;
    $date = $this->date->format('F d, Y');
    error_log("Invoice date $date, amount \$$usd");
  }
}
