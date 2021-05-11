# Costya

A tool to show how much AWS cost ya. Displays billing itemization for a given month by Expensify code. Responses from
AWS Cost Explorer are cached to avoid extra charges.

The standard output is formatted as a CSV table, suitable for import into Expensify. The output fields are:

    Merchant,Date,Amount,Category,Tag

AWS bills will be merged into a single multi-page PDF receipt; attach it to each imported expense.

The script will parse invoice dates, and find the latest one. It will query AWS expenses for the calendar month that
precedes it. This date will also be output in the resulting CSV.

A CSV file that matches AWS `Project` tags with Expensify billing codes should be called `codes.csv`. If the first
line doesn't contain a colon (`:`) character in the second column, it's considered a header and skipped.

The billing code in the first data line is the default code. Billing to this code shows a warning. It is used for:

1. Billing of the tags that don't have a matching code.
2. Adjusting for rounding errors.

The amount actually charged by Amazon is calculated as a sum of invoice totals.

Amazon charges fractional cents per Project tag, so subtotals by billing code don't add up precisely when rounded to
whole cents. The script adjusts the default billing code's subtotal to accomodate for that. A warning is displayed in
this case.

## Installation

Requires [Composer](https://getcomposer.org/), PHP CLI, jq, pdftk, and pdftotext. To install Composer dependencies, run:

    make install

## Usage Example

Download Amazon invoice PDFs to the project directory, and run:

    make
