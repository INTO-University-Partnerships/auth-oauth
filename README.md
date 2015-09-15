# Auth OAuth

A Moodle auth plugin that allows authentication with an [OAuth provider](https://github.com/INTO-University-Partnerships/django-into-oauth).

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

## Installation

Install [Composer](https://getcomposer.org/download/) if it isn't already.

    ./composer.phar self-update
    ./composer.phar update
    cd auth
    git clone https://github.com/INTO-University-Partnerships/auth-oauth oauth
    cd ..
    php admin/cli/upgrade.php

### Apache rewrite rule

Add the following Apache rewrite rule:

    RewriteRule ^(/auth/oauth) /auth/oauth/login.php?slug=$1 [QSA,L]

## Configuration

Login as an admin.

In the *Administration* block, navigate to *Site administration* `->` *Plugins* `->` *Authentication* `->` *Manage authentication*.

Enable the `Oauth` plugin, and in its settings, configure the following fields:

    Client ID:         issued by the IdP
    Client secret:     issued by the IdP
    Authorization URL: http://localhost:8000/o/authorize/
    Token URL:         http://localhost:8000/o/token/
    Get User API URL:  http://localhost:8000/o/user/
    Logout URL:        http://localhost:8000/o/logout/

Tick *Redirect to obtain authorisation*.

### Data mapping

Configure the first four fields to be updated *On every login* and to be *Locked*:

    Username:      username
    First name:    first_name
    Surname:       last_name
    Email address: email

## Back door

To login as admin, request `/login/?backdoor=1` relative to the `wwwroot`.

## Tests

Comment-out line `173` of `lib/phpunit/bootstrap.php`, then:

    php admin/tool/phpunit/cli/util.php --buildcomponentconfigs
    vendor/bin/phpunit -c auth/oauth
