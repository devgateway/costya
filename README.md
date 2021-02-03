# Costya

A tool to show how much AWS cost ya. Displays billing itemization for a given month by Expensify code. Responses from AWS Cost Explorer are cached to avoid extra charges.

## Usage Example

    php -f billing.php -- -d 2021-01-21 -b bill.json -t 533.37

### `-d DATE`

Query the period starting at DATE (in any format that [PHP can parse](https://www.php.net/manual/en/datetime.formats.php)) and up to the end of that month. If this argument is omitted, query last month.

## Installation

Requires [Composer](https://getcomposer.org/) and PHP CLI. Run:

    composer install --no-dev
