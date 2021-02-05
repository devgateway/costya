# Costya

A tool to show how much AWS cost ya. Displays billing itemization for a given month by Expensify code. Responses from
AWS Cost Explorer are cached to avoid extra charges.

The standard output is formatted as a CSV table, suitable for import into Expensify. The output fields are:

    Merchant,Date,Amount,Category,Tag

Merge AWS bills into a single multi-page PDF receipt, and attach it to each imported expense.

## Usage Example

    make DATE=2021-01-21 TOTAL=509+12.72+11.65

The Makefile will call:

    php -f costya.php -- -d 2021-01-21 -b codes.csv -t 533.37

### `-d DATE`

AWS invoice date, in any format that [PHP can parse](https://www.php.net/manual/en/datetime.formats.php). The script
will query the calendar month before this date.

### `-b BILLING_CSV`

A CSV file that matches AWS `Project` tags with Expensify billing codes. If the first line doesn't contain a colon
(`:`) character in the second column, it's considered a header and skipped.

The billing code in the first data line is the default code. Billing to this code shows a warning. It is used for:

1. Billing of the tags that don't have a matching code.
2. Adjusting for rounding errors, see `-t` argument description.

### `-t TOTAL_PAID`

The amount in USD actually charged by Amazon. At this time, it's not possible to retrieve it with AWS API.

Amazon charges fractional cents per Project tag, so subtotals by billing code don't add up precisely when rounded to
whole cents. The script adjusts the default billing code's subtotal to accomodate for that. A warning is displayed in
this case.

## Installation

Requires [Composer](https://getcomposer.org/) and PHP CLI. Run:

    make install
