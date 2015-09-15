<?php
use Mockery as m;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Stream\Stream;
use GuzzleHttp\Subscriber;

defined('MOODLE_INTERNAL') || die();

require_once __DIR__ . '/../oauth_request.php';
require_once __DIR__ . '/../oauth_moodle_user.php';

class oauth_request_test extends advanced_testcase {

    /**
     * @var oauth_request
     */
    protected $_cut;

    /**
     * setUp
     */
    protected function setUp() {
        $this->_cut = new oauth_request();
        $this->resetAfterTest();
    }

    /**
     * tearDown
     */
    protected function tearDown() {
        m::close();
    }

    /**
     * tests instantiation
     */
    public function test_instantiation() {
        $this->assertInstanceOf('oauth_request', $this->_cut);
    }

    /**
     * Tests the redirect when an authorization request is made
     * Nothing returned, so just mocks the redirect
     */
    public function test_request_authorization_code() {
        $provider = array(
            'client_id' => 'random_client_id',
            'authorize_url' => 'http://authorize_url.com'
        );
        $redirect = m::mock('moodle_functions');

        $url = "http://authorize_url.com?" . http_build_query(
              array(
                  'client_id' => 'random_client_id',
                  'response_type' => 'code',
                  'approval_prompt' => 'skip',
                  'state' => 'state'
              )
            );
        $redirect->shouldReceive('redirect')->once()->with(
            $url
        );
        $this->_cut->request_authorization_code((object) $provider, $redirect, 'state');
    }

    /**
     * Tests the redirect when a logout request is made
     * Nothing returned, so just mocks the redirect
     */
    public function test_request_provider_logout() {
        $provider = array(
            'client_id' => 'random_client_id',
            'logout_url' => 'http://logout_url.com'
        );
        $redirect = m::mock('moodle_functions');

        $url = "http://logout_url.com";

        $redirect->shouldReceive('redirect')->once()->with(
            $url
        );
        $this->_cut->request_provider_logout((object) $provider, $redirect);
    }

    /**
     * Tests requesting an access token
     */
    public function test_access_token_request_success() {
        $provider = array(
            'client_id' => 'random_client_id',
            'client_secret' => 'random_client_secret',
            'token_url' => 'http://token_url.com'
        );

        $return = array(
            'access_token' => 'RANDOM_ACCESS_TOKEN',
            'request_token' => 'RANDOM_REQUEST_TOKEN');

        $client = new \GuzzleHttp\Client();
        $mock = new Subscriber\Mock([new Response(200, array(), Stream::factory(json_encode($return)))]);
        $client->getEmitter()->attach($mock);

        // Test the response
        $response = $this->_cut->access_token_request($client, (object) $provider, 'TEST_AUTHORISATION_CODE', 'http://redirect_url.com');
        $this->assertEquals((object) $return, $response);
    }

    /**
     * Tests requesting an access token, when an exception is thrown
     * @expectedException GuzzleHttp\Exception\ClientException
     */
    public function test_access_token_request_failure() {
        $provider = array(
            'client_id' => 'random_client_id',
            'client_secret' => 'random_client_secret',
            'token_url' => 'http://token_url.com'
        );

        $client = new GuzzleHttp\Client();
        $mock = new Subscriber\Mock([new Response(400)]);
        $client->getEmitter()->attach($mock);
        $this->_cut->access_token_request($client, (object) $provider, 'TEST_AUTHORISATION_CODE', 'http://redirect_url.com');
    }

    /**
     * Tests requesting to refresh the access token
     */
    public function test_refresh_access_token_request_success() {
        $provider = array(
            'client_id' => 'random_client_id',
            'client_secret' => 'random_client_secret',
            'token_url' => 'http://token_url.com'
        );

        $return = array(
            'access_token' => 'RANDOM_ACCESS_TOKEN',
            'request_token' => 'RANDOM_REQUEST_TOKEN'
        );

        $client = new \GuzzleHttp\Client();
        $mock = new Subscriber\Mock([new Response(200, array(), Stream::factory(json_encode($return)))]);
        $client->getEmitter()->attach($mock);

        // Test the response
        $response = $this->_cut->refresh_access_token_request($client, (object) $provider, 'TEST_REFRESH_TOKEN');
        $this->assertEquals((object) $return, $response);
    }

    /**
     * Tests requesting to refresh the accesst oken, when an exception is thrown
     * @expectedException GuzzleHttp\Exception\ClientException
     */
    public function test_refresh_access_token_request_failure() {
        $provider = array(
            'client_id' => 'random_client_id',
            'client_secret' => 'random_client_secret',
            'token_url' => 'http://token_url.com'
        );

        $client = new GuzzleHttp\Client();
        $mock = new Subscriber\Mock([new Response(400)]);
        $client->getEmitter()->attach($mock);
        $this->_cut->refresh_access_token_request($client, (object) $provider, 'TEST_REFRESH_TOKEN');
    }

    /**
     * Tests the bearer api method with a successful response
     */
    public function test_bearer_api_request_success() {
        $client = new GuzzleHttp\Client();
        $user = array(
            'firstname' => 'john',
            'lastname' => 'mills',
            'email' => 'john.mills@example.com'
        );

        $mock = new Subscriber\Mock([new Response(200, array(), Stream::factory(json_encode($user)))]);
        $client->getEmitter()->attach($mock);
        $response = $this->_cut->bearer_api_request($client, 'http://bearer_request.com', 'TEST_ACCESS_TOKEN');
        $this->assertEquals((object) $user, $response);
    }

    /**
     * Tests the bearer api method with an unsuccessful response
     * @expectedException        GuzzleHttp\Exception\ClientException
     */
    public function test_bearer_api_request_failure() {
        $client = new GuzzleHttp\Client();
        $mock = new Subscriber\Mock([new Response(400)]);
        $client->getEmitter()->attach($mock);
        $this->_cut->bearer_api_request($client, 'http://bearer_request.com', 'TEST_ACCESS_TOKEN');
    }

    /**
     * Tests that tokens are refreshed if expired
     */
    public function test_refresh_session_tokens_if_expired() {
        global $SESSION;
        $now = time();
        $SESSION->oauth_tokens = (object) array(
            'expires_in' => 3600,
            'access_token' => 'RANDOM_ACCESS_TOKEN',
            'refresh_token' => 'RANDOM_REQUEST_TOKEN',
            'expiry_time' => $now
        );

        $client = new GuzzleHttp\Client();
        $provider = array(
            'client_id' => 'random_client_id',
            'client_secret' => 'random_client_secret',
            'token_url' => 'http://token_url.com'
        );
        $return = array(
            'expires_in' => 1800,
            'access_token' => 'NEW_RANDOM_ACCESS_TOKEN',
            'refresh_token' => 'NEW_RANDOM_REQUEST_TOKEN'
        );
        $mock = new Subscriber\Mock([new Response(200, array(), Stream::factory(json_encode($return)))]);
        $client->getEmitter()->attach($mock);
        $this->_cut->refresh_session_tokens_if_expired($client, (object) $provider, new oauth_moodle_user(), $now);
        $return['expiry_time'] = $now + 1800;
        $this->assertEquals((object) $return, $SESSION->oauth_tokens);
    }

    /**
     * Tests that tokens are not refreshed if not yet expired
     */
    public function test_refresh_session_tokens_not_yet_expired() {
        global $SESSION;
        $now = time();
        $tokens = (object) array(
            'expires_in' => 3600,
            'access_token' => 'RANDOM_ACCESS_TOKEN',
            'refresh_token' => 'RANDOM_REQUEST_TOKEN',
            'expiry_time' => $now+1
        );
        $SESSION->oauth_tokens = $tokens;
        $client = new GuzzleHttp\Client();
        $this->_cut->refresh_session_tokens_if_expired($client, new \stdClass(), new oauth_moodle_user(), $now);
        $this->assertEquals($tokens, $SESSION->oauth_tokens);
    }
}
