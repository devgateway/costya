#!/usr/bin/env php
<?php
namespace devgateway\costya;

require 'vendor/autoload.php';

$invoice = new Invoice(STDIN);
$aws_billing = new AwsBilling(new Month($invoice->date));
$expensify_billing = new ExpensifyBilling($argv[1]);
$expensify_billing->toCsv(STDOUT, $aws_billing, $invoice);
