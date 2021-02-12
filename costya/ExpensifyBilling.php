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

      $this->codes[$items[0]] = new BillingCode($items[1], $items[2]);
    }

    fclose($handle);
  }

  public function toCsv($handle, $aws_billing, $invoice) {
    $costs = [];
    $total = 0;

    foreach ($aws_billing->get() as $tag => $usd) {
      $cents = (int) round($usd * 100);
      $total += $cents;
      if (isset($this->codes[$tag])) {
        $billing_code = $this->codes[$tag]->format();
      } else {
        error_log("No billing code for '$tag', using '{$this->default_code->format()}'");
        $billing_code = $this->default_code->format();
      }
      if (!isset($costs[$billing_code])) {
        $costs[$billing_code] = 0;
      }
      $costs[$billing_code] += $cents;
    }

    $diff = $invoice->cents - $total;
    if ($diff != 0) {
      error_log(sprintf('Adjusting %s by %+d cents', $this->default_code->format(), $diff));
      $costs[$this->default_code->format()] += $diff;
    }

    $formatted_date = $invoice->date->format('Y-m-d');

    foreach ($costs as $code => $cents) {
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
