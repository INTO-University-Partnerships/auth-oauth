<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Makes requests to an oauth provider - either browser redirects or server requests
 * Class oauth
 */
class oauth_request {

    public function __construct() {

    }

    /**
     * Redirect to request an authorization code from the provider
     * @param stdClass $provider
     * @param moodle_functions $m
     * @param string $state
     */
    public function request_authorization_code($provider, moodle_functions $m, $state) {
        $params = array(
            'client_id' => $provider->client_id,
            'response_type' => 'code',
            'approval_prompt' => 'skip',
            'state' => $state
        );
        $url = $provider->authorize_url . "?" . http_build_query($params);
        $m->redirect($url);
    }

    /**
     * Redirect to request logout from the provider
     * @param stdClass $provider
     * @param moodle_functions $m
     */
    public function request_provider_logout($provider, moodle_functions $m) {
        $url = $provider->logout_url;
        $m->redirect($url);
    }

    /**
     * Make an access token request
     * @param \GuzzleHttp\Client $client
     * @param stdClass $provider
     * @param string $code
     * @param string $redirect_uri
     * @return mixed
     */
    public function access_token_request(\GuzzleHttp\Client $client, $provider, $code, $redirect_uri) {

        $params = array(
            'client_id' => $provider->client_id,
            'client_secret' => $provider->client_secret,
            'code' => $code,
            'redirect_uri' => $redirect_uri,
            'grant_type' => 'authorization_code'
        );
        $request = $client->createRequest(
            'POST',
            $provider->token_url
        );
        $request->setQuery($params);
        $response = $client->send($request);
        return (object) $response->json();
    }

    /**
     * Uses a refresh token, to retrieve a new access token
     * @param \GuzzleHttp\Client $client
     * @param stdClass $provider
     * @param $refresh_token
     * @return object
     */
    public function refresh_access_token_request(\GuzzleHttp\Client $client, $provider, $refresh_token) {
        $params = array(
            'client_id' => $provider->client_id,
            'client_secret' => $provider->client_secret,
            'refresh_token' => $refresh_token,
            'grant_type' => 'refresh_token'
        );
        $request = $client->createRequest(
            'POST',
            $provider->token_url
        );
        $request->setQuery($params);
        $response = $client->send($request);
        return (object) $response->json();
    }

    /**
     * Makes a bearer api request, when given an endpoint and an access token
     * @param \GuzzleHttp\Client $client
     * @param string $endpoint
     * @param string $access_token
     * @return stdClass
     */
    public function bearer_api_request(\GuzzleHttp\Client $client, $endpoint, $access_token) {
        $request = $client->createRequest(
            'GET',
            $endpoint
        );
        $request->setHeader('Authorization', "Bearer " . $access_token);
        $response = $client->send($request);
        return (object) $response->json();
    }

    /**
     * @param \GuzzleHttp\Client $client
     * @param stdClass $provider
     * @param oauth_moodle_user $user
     * @param int $now
     */
    public function refresh_session_tokens_if_expired(\GuzzleHttp\Client $client, stdClass $provider, oauth_moodle_user $user, $now) {
        global $SESSION;
        $tokens = $SESSION->oauth_tokens;
        if ($tokens->expiry_time <= $now) {
            $tokens = $this->refresh_access_token_request($client, $provider, $tokens->refresh_token);
            $user->set_session_tokens($tokens, $now);
        }
    }
}
