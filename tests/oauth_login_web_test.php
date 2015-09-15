<?php
use Mockery as m;
use Symfony\Component\HttpKernel\Client;

require_once __DIR__ . '/../oauth_request.php';

defined('MOODLE_INTERNAL') || die();

class oauth_login_web_test extends advanced_testcase {

    protected $_app;


    //Data for a new user
    protected $_usernew = array(
        'username' => 'bismarck',
        'firstname' => 'otto',
        'lastname' => 'von',
        'email' => 'otto.von@example.com',
        'access_token' => 'OTTO_ACCESS_TOKEN',
        'refresh_token' => 'OTTO_REFRESH_TOKEN',
        'expires_in' => 3600,
        'auth' => 'oauth'
    );
    //Data for an existing user
    protected $_userold = array(
        'username' => 'margo',
        'firstname' => 'margery',
        'lastname' => 'kempe',
        'email' => 'margery.kempe@example.com',
        'access_token' => 'MARGO_ACCESS_TOKEN',
        'refresh_token' => 'MARGO_REFRESH_TOKEN',
        'expires_in' => 1800,
        'auth' => 'oauth'
    );



    public function setUp() {
        global $SESSION;

        // Create Silex app
        $this->createApplication();

        // Enable the oauth module on the DB
        set_config('auth', 'oauth');
        set_config('username', 'username', 'auth/oauth');

        // Mock the user field mappings
        $this->mock_mappings();

        // Mock the provider configuration
        $this->mock_provider();

        // Mock the complete_user_login function, as this results in a "headers already sent" error that I couldn't resolve when using Moodle's PHPUnit bootstrap script
        $this->mock_complete_user_login();

        // Set the state
        $SESSION->oauth_state = 'authorising_test';

        // Mock now
        $now = time();
        $this->_app['now'] = $this->_app->protect(
            function () use ($now) {
                return $now;
            }
        );

        $this->resetAfterTest();
    }

    public function tearDown() {
        m::close();
    }

    /**
     * Get the Silex App
     */
    protected function createApplication() {
        if (!defined('SLUG')) {
            define('SLUG', '');
        }
        if (!defined('SILEX_WEB_TEST')) {
            define('SILEX_WEB_TEST', true);
        }
        $this->_app = require __DIR__ . '/../app.php';
        $this->_app['debug'] = true;
        $this->_app['exception_handler']->disable();
    }


    /**
     * @expectedException        Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     * @expectedExceptionMessage No route found for "GET /not_exist
     */
    public function test_oauth_login() {
        $client = new Client($this->_app);
        $client->request('GET', '/not_exist');
    }

    /**
     * Create and log-in a new user
     */
    public function test_login_new_user() {
        global $CFG;

        $user = $this->_usernew;
        $this->mock_requests($user);

        $client = new Client($this->_app);
        $client->request('GET', '/login/', array('code'=>'request_code', 'state'=>'authorising_test'));

        $this->assertTrue($client->getResponse()->isRedirect($CFG->wwwroot));

        $this->is_logged_in($user);

        $this->db_updated($user);
    }

    /**
     * Log-in an existing user
     */
    public function test_login_existing_user() {
        global $USER, $CFG;
        $user = $this->_userold;
        $userdb = $this->getDataGenerator()->create_user($user);
        $this->mock_requests($user);

        $client = new Client($this->_app);
        $client->request('GET', '/login/', array('code'=>'request_code', 'state'=>'authorising_test'));
        //Same user so same id
        $this->assertTrue($client->getResponse()->isRedirect($CFG->wwwroot));

        $this->assertEquals($userdb->id, $USER->id);

        $this->is_logged_in($user);

        $this->db_updated($user);
    }

    /**
     * Change some profile details for an existing user, and log them in
     */
    public function test_login_existing_user_profile_change() {
        global $USER, $CFG;
        $user = $this->_userold;
        $userdb = $this->getDataGenerator()->create_user($user);

        $user_modified = $user;
        $user_modified['email'] = 'modified_email@example.com';
        $user_modified['firstname'] = 'Modi';
        $user_modified['lastname'] = 'Fied';

        $this->mock_requests($user_modified);

        $client = new Client($this->_app);
        $client->request('GET', '/login/', array('code'=>'request_code', 'state'=>'authorising_test'));

        $this->assertTrue($client->getResponse()->isRedirect($CFG->wwwroot));

        $this->assertEquals($userdb->id, $USER->id);

        $this->is_logged_in($user_modified);

        $this->db_updated($user_modified);
    }

    /**
     * Test invalid state
     * @expectedException        Exception
     */
    public function test_invalid_state() {
        $client = new Client($this->_app);
        $client->request('GET', '/login/', array('code'=>'request_code', 'state'=>'invalid_state'));
    }

    /**
     * Assert that the user is logged in
     * @param $user
     */
    protected function is_logged_in($user) {
        global $USER, $SESSION, $CFG;

        $this->assertEquals(
            array($user['username'], $user['firstname'], $user['lastname'], $user['email'], true, $CFG->wwwroot),
            array($USER->username, $USER->firstname, $USER->lastname, $USER->email, $USER->loggedin, $USER->site)
        );
        $this->assertEquals('authorised', $SESSION->oauth_state);
        $this->assertTrue(!empty($USER->sesskey));
        $this->assertObjectHasAttribute('oauth_tokens', $SESSION);
        $this->assertEquals(
            (object) array(
                'refresh_token' => $user['refresh_token'],
                'access_token' => $user['access_token'],
                'expires_in' => $user['expires_in'],
                'expiry_time' => $this->_app['now']() + $user['expires_in'],
            ), $SESSION->oauth_tokens
        );
    }

    /**
     * Assert that the DB matches the user record
     * @param $user
     */
    protected function db_updated($user) {
        global $DB;
        $this->assertTrue($DB->record_exists('user', array(
                'username' => $user['username'],
                'firstname' => $user['lastname'],
                'email' => $user['email'],
                'firstname' => $user['firstname'],
                'auth' => 'oauth'
            )
        ));
    }

    /**
     * Mock the provider (as much as is needed)
     */
    public function mock_provider() {
        $this->_app['oauth_provider'] = $this->_app->share(function () {
            $data = (object) array(
                       'api_user_get' => 'http://user_get'
                    );
            return $data;
        });
    }

    /**
     * Mock mappings - written to DB
     */
    public function mock_mappings() {
        $map_config = array(
            'field_map_firstname' => 'first_name_from_provider',
            'field_updatelocal_firstname' => 'onlogin',
            'field_lock_firstname' => 'locked',

            'field_map_lastname' => 'last_name_from_provider',
            'field_updatelocal_lastname' => 'onlogin',
            'field_lock_lastname' => 'locked',

            'field_map_email' => 'email_from_provider',
            'field_updatelocal_email' => 'onlogin',
            'field_lock_email' => 'locked',
        );
        foreach ($map_config as $name => $value) {
            set_config($name, $value, 'auth/oauth');
        }
    }


    /**
     * Mock OAUTH request object
     * @param $user
     */
    protected function mock_requests($user) {
        global $CFG;
        //Provider configuration
        $mock = m::mock('oauth_request');
        $mock->shouldReceive('access_token_request')
            ->once()
            ->with(
                $this->_app['oauth_guzzler'],
                $this->_app['oauth_provider'],
                'request_code',
                $CFG->wwwroot . SLUG . '/login/'
            )
            ->andReturn((object) array(
                    'access_token' => $user['access_token'],
                    'refresh_token' => $user['refresh_token'],
                    'expires_in' => $user['expires_in']
                )
            );

        $mock->shouldReceive('bearer_api_request')
            ->once()
            ->with(
                $this->_app['oauth_guzzler'],
                'http://user_get',
                $user['access_token']
            )
            ->andReturn((object) array(
                    'username' => $user['username'],
                    'first_name_from_provider' => $user['firstname'],
                    'last_name_from_provider' => $user['lastname'],
                    'email_from_provider' => $user['email']
                )
            );

        $this->_app['oauth_request'] = $this->_app->share(function() use ($mock){
            return $mock;
        });
    }

    /**
     * Mock Moodle's complete_user_login function, to prevent session_regenerate error - headers already sent
     */
    protected function mock_complete_user_login() {
        $this->_app['moodle_functions']->attach(
            'complete_user_login', function($user) {
                \core\session\manager::set_user($user);
                return $user;
            });
    }
}