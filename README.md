# Costya

A tool to show how much AWS cost ya. Displays billing itemization for a given month by Expensify code. Responses from
AWS Cost Explorer are cached to avoid extra charges.

The standard output is formatted as a CSV table, suitable for import into Expensify. The CSV fields are:

    Merchant,Date,Amount,Category,Tag

Merge AWS bills into a single multi-page PDF receipt, and attach it to each imported expense.

## Usage Example

    ./costya.php -d 2021-01-21 -b billing-codes.json -t 533.37
    php -f costya.php -- -d 2021-01-21 -b billing-codes.json -t 533.37

### `-d DATE`

AWS invoice date, in any format that [PHP can parse](https://www.php.net/manual/en/datetime.formats.php). The script
will query the calendar month before this date.

### `-b BILLING_JSON`

A file that matches AWS `Project` tags with Expensify billing codes. Format:

    {
      "Project tag": "Billing code",
      "": "Default billing code"
    }

A mandatory empty string project tag is a default billing code. Billing to this code shows a warning. It is used for:

1. Billing of the tags that don't have a matching code.
2. Adjusting for rounding errors, see `-t` argument description.

### `-t TOTAL_PAID`

The amount in USD actually charged by Amazon. At this time, it's not possible to retrieve it with AWS API.

Amazon charges fractional cents per Project tag, so subtotals by billing code don't add up precisely when rounded to
whole cents. The script adjusts the default billing code's subtotal to accomodate for that. A warning is displayed in
this case.

## Installation

Requires [Composer](https://getcomposer.org/) and PHP CLI. Run:

    composer install --no-dev
