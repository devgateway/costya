<?php
namespace devgateway\costya;

class ExpensifyBilling {
  protected $default_code, $codes = [];

  public function __construct($codes_csv) {
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
          $this->default_code = new BillingCode($items[1], $items[2]);
          $first_row = FALSE;
        }
      }

      $tag = strtoupper($items[0]);
      $this->codes[$tag] = new BillingCode($items[1], $items[2]);
    }

    fclose($handle);
  }

  public function toCsv($handle, $aws_billing, $invoice) {
    $expenses = [];
    $charges = $aws_billing->getCharges();
    $pretax_total = array_sum($charges);
    $tax_total = $aws_billing->getTax();
    $total = 0;
    $default_code = $this->default_code->format();

    foreach ($charges as $tag => $amount) {
      if (isset($this->codes[$tag])) {
        $billing_code = $this->codes[$tag]->format();
      } else {
        if ($tag) {
          error_log("No billing code for '$tag', using '$default_code'");
        }
        $billing_code = $default_code;
      }

      if (!isset($expenses[$billing_code])) {
        $expenses[$billing_code] = 0;
      }

      $tax = $amount / $pretax_total * $tax_total;
      $subtotal = self::toCents($amount + $tax);
      $total += $subtotal;
      $expenses[$billing_code] += $subtotal;
    }

    $diff = $invoice->cents - $total;
    if ($diff != 0) {
      error_log(sprintf('Adjusting %s by %+d cents', $this->default_code->format(), $diff));
      $expenses[$this->default_code->format()] += $diff;
    }

    $formatted_date = $invoice->date->format('Y-m-d');

    foreach ($expenses as $code => $cents) {
      fputcsv($handle, [
        'Amazon Web Services',
        $formatted_date,
        sprintf('%.2f', $cents / 100.0),
        '65000-IT Services, Softwares, Hosting & Subscriptions',
        $code
      ]);
    }
  }

  protected static function toCents($usd) {
    return (int) round($usd * 100);
  }

  protected static function toUsd($cents) {
    return sprintf('%.2f', $cents / 100.0);
  }
}
