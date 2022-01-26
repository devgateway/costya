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

Requires [Composer](https://getcomposer.org/), PHP CLI, jq, pdftk, and pdftotext (poppler-utils package).

Requires AWS credentials in the environment under profile [costya]

To install Composer dependencies, run:

    make install

## Usage Example

To verify billing codes, you need to grab `policy.json` from Expensify. Log in to your account, enable Development
Console, filter by XHR requests, and open a report. The required JSON will be returned for a POST request to:

    https://www.expensify.com/api?command=Policy_Get

Download Amazon invoice PDFs to the project directory, and run:

    make


## Error messages

    Code RG:4339-CRP-ASDB-4 from codes.csv not found in active.txt
This usually means that there's a code coming from policy.json that may have been deactivated in the period of time being reported. Check with Finance if that's so and update the code in codes.csv

    PHP Fatal error:  Uncaught Aws\Exception\CredentialsException: 'costya' not found in credentials file in /mnt/c/Users/fferr/IdeaProjects/costya/vendor/aws/aws-sdk-php/src/Credentials/CredentialProvider.php:861

Make sure that you have the credentials in any of the places mentioned [here.](https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/guide_credentials_profiles.html). Recommended to put in credentials file in .aws for the profile [costya] 

