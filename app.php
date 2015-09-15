<?php
/**
 * Silex app for handling logging in and out using oauth
 */
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

require_once __DIR__ . '/../../config.php';
require_once(__DIR__ . '/../../vendor/autoload.php');

$app = new Silex\Application();
$app['debug'] = debugging('', DEBUG_MINIMAL);

// Services
$app['oauth_request'] = $app->share(function ($app) {
    require_once(__DIR__ . '/oauth_request.php');
    return new oauth_request();
});
$app['oauth_user'] = $app->share(function ($app) {
    require_once(__DIR__ . '/oauth_moodle_user.php');
    return new oauth_moodle_user();
});
$app['oauth_guzzler'] = $app->share(function ($app) {
    $client = new \GuzzleHttp\Client();
    return $client;
});
$app['moodle_functions'] = $app->share(function ($app) {
    require_once(__DIR__ . '/moodle_functions.php');
    return new moodle_functions();
});
$app['oauth_provider'] = $app->share(function ($app) {
    $config = get_config('auth/oauth');
    $provider = json_decode($config->oauth_provider);
    return $provider;
});
$app['now'] = $app->protect(function () {
    return time();
});

// Login to Moodle
$app->get('/login/', function (Request $request) use ($app) {
    global $CFG, $SESSION;
    $now = $app['now']();
    // Params
    $code = $request->get('code');
    $state = $request->get('state');

    // XSRF Check
    if (!isset($SESSION->oauth_state) || $state !== $SESSION->oauth_state) {
        throw new Exception('Invalid state');
    }
    // Get an access token and a refresh token
    $redirect_uri = $CFG->wwwroot . SLUG . '/login/';
    $tokens = $app['oauth_request']->access_token_request($app['oauth_guzzler'], $app['oauth_provider'], $code, $redirect_uri);

    // Get the user data from the provider, using the access token
    $endpoint = $app['oauth_provider']->api_user_get;
    $userdata = $app['oauth_request']->bearer_api_request($app['oauth_guzzler'], $endpoint, $tokens->access_token);

    // Log the user in - largely Moodle stuff, except the oauth refresh token is stored on the session for any subsequent API requests
    $app['oauth_user']->set_userdata($userdata);
    $app['oauth_user']->login_user($app['moodle_functions'], $now, get_config('auth/oauth', 'username'));
    $app['oauth_user']->set_session_tokens($tokens, $now);
    $SESSION->oauth_state = 'authorised';

    // Redirect
    $url = (empty($SESSION->wantsurl)) ? $CFG->wwwroot : $SESSION->wantsurl;
    return $app->redirect($url);
});

// Logout locally and redirect to the provider's logout url with a client id
$app->get('/logout/', function (Request $request) use ($app) {
    global $SESSION;
    require_logout();
    unset($SESSION->oauth_state);
    unset($SESSION->refresh_token);
    $url = $app['oauth_provider']->logout_url . "?client_id=" . urlencode($app['oauth_provider']->client_id);
    return $app->redirect($url);
});

$app->error(function (Exception $e, $code) {
    global $PAGE;
    $PAGE->set_context(context_system::instance());
    $PAGE->set_url('/auth/oauth/login/');
    if ($e instanceof moodle_exception) {
        print_error('unable_to_authenticate', 'auth_oauth', '', NULL, $e->getMessage());
    }
});

return $app;
