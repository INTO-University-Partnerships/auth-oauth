# Auth OAuth

A Moodle auth plugin that allows authentication with an OAuth provider.

Commands are relative to the directory in which Moodle is installed.

## Dependencies

Moodle 2.9

The following packages must be added to `composer.json`:

    "require": {
        "silex/silex": "1.3.*",
        "symfony/browser-kit": "2.5.*",
        "guzzlehttp/guzzle": "4.2.*"
    },
    "require-dev": {
        "mockery/mockery": "dev-master"
    }

# Installation

Install [Composer](https://getcomposer.org/download/) if it isn't already.

    ./composer.phar self-update
    ./composer.phar update
    cd auth
    git clone https://github.com/INTO-University-Partnerships/auth-oauth oauth
    cd ..
    php admin/cli/upgrade.php

## Apache rewrite rule

Add the following Apache rewrite rule:

    RewriteRule ^(/auth/oauth) /auth/oauth/login.php?slug=$1 [QSA,L]

## Tests

Comment-out line `173` of `lib/phpunit/bootstrap.php`, then:

    php admin/tool/phpunit/cli/util.php --buildcomponentconfigs
    vendor/bin/phpunit -c auth/oauth
