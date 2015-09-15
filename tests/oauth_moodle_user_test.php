<?php
use Mockery as m;

defined('MOODLE_INTERNAL') || die();

require_once __DIR__ . '/../oauth_moodle_user.php';

class oauth_moodle_user_test extends advanced_testcase {

    /**
     * @var oauth_moodle_user
     */
    protected $_cut;
    /**
     * Time to represent now
     */
    protected $_now;


    /**
     * setUp
     */
    protected function setUp() {
        $this->_now = mktime(1, 30, 0, 4, 22, 2014);
        $this->_cut = new oauth_moodle_user();
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
        $this->assertInstanceOf('oauth_moodle_user', $this->_cut);
    }

    /**
     * @expectedException        moodle_exception
     * @expectedExceptionMessage Unable to authenticate OAUTH user robologo
     */
    public function test_login_user_authenticate_fail() {
        $moodle = m::mock('moodle_functions');
        $moodle->shouldReceive('authenticate_user_login')
            ->once()
            ->with(
                'robologo',
                $this->_now
            )
            ->andReturn(false);
        $this->_cut->set_userdata((object) array(
            'username' => 'robologo'
        ));
        $this->_cut->login_user($moodle, $this->_now, 'username');
    }

    /**
     * @expectedException        moodle_exception
     * @expectedExceptionMessage Unable to retrieve user data following OAUTH authentication for user robologo
     */
    public function test_login_user_get_data_fail() {
        $moodle = m::mock('moodle_functions');

        $user = new stdClass();
        $user->id = 3;
        $user->username = 'robologo';

        $moodle->shouldReceive('authenticate_user_login')
            ->once()
            ->with(
                $user->username,
                $this->_now
            )
            ->andReturn((object) array(
                'id' => $user->id
            ));
        $moodle->shouldReceive('get_complete_user_data')
            ->once()
            ->with('id', $user->id)
            ->andReturn(false);

        $this->_cut->set_userdata((object) array(
            'username' => $user->username
        ));
        $this->_cut->login_user($moodle, $this->_now, 'username');
    }

    /**
     * @expectedException        moodle_exception
     * @expectedExceptionMessage Unable to complete user login after retrieving OAUTH data for user robologo
     */
    public function test_login_user_complete_login_fail() {
        $moodle = m::mock('moodle_functions');

        $user = new stdClass();
        $user->id = 3;
        $user->username = 'robologo';

        $moodle->shouldReceive('authenticate_user_login')
            ->once()
            ->with(
                $user->username,
                $this->_now
            )
            ->andReturn((object) array(
                'id' => $user->id
            ));

        $moodle->shouldReceive('get_complete_user_data')
            ->once()
            ->with('id', $user->id)
            ->andReturn($user);

        $moodle->shouldReceive('complete_user_login')
            ->once()
            ->with($user)
            ->andReturn(false);

        $this->_cut->set_userdata((object) array(
            'username' => 'robologo'
        ));
        $this->_cut->login_user($moodle, $this->_now, 'username');
    }

    /**
     * Successful return of user object following login
     */
    public function test_login_user_success() {
        global $CFG;

        $moodle = m::mock('moodle_functions');

        $user = new stdClass();
        $user->id = 3;
        $user->username = 'robologo';

        $moodle->shouldReceive('authenticate_user_login')
            ->once()
            ->with(
                $user->username,
                $this->_now
            )
            ->andReturn((object) array(
                'id' => $user->id
            ));

        $moodle->shouldReceive('get_complete_user_data')
            ->once()
            ->with('id', $user->id)
            ->andReturn($user);

        $user_complete = clone($user);
        $user_complete->complete = true;

        $moodle->shouldReceive('complete_user_login')
            ->once()
            ->with($user)
            ->andReturn($user_complete);

        $this->_cut->set_userdata((object) array(
            'username' => 'robologo'
        ));
        $user_loggedin = $this->_cut->login_user($moodle, $this->_now, 'username');

        $user_complete->loggedin = true;
        $user_complete->wwwroot = $CFG->wwwroot;

        $this->assertEquals($user_complete, $user_loggedin);
    }

    /**
     * Test set oauth tokens
     */
    public function test_set_oauth_tokens() {
        global $SESSION;
        $tokens = array(
            'expires_in' => 60 * 60, // 1 hour
            'refresh_token' => 'TEST_REFRESH_TOKEN',
            'access_token' => 'TEST_ACCESS_TOKEN',
        );
        $now = time();
        $this->_cut->set_session_tokens((object) $tokens, $now);
        $this->assertObjectHasAttribute('oauth_tokens', $SESSION);

        $tokens['expiry_time'] = $now + 60 * 60;
        $this->assertEquals((object) $tokens, $SESSION->oauth_tokens);
    }

    /**
     * Test get oauth token
     */
    public function test_get_oauth_tokens() {
        global $SESSION;
        $SESSION->oauth_tokens = (object) array(
            'expires_in' => 60 * 60, // 1 hour
            'refresh_token' => 'TEST_REFRESH_TOKEN',
            'access_token' => 'TEST_ACCESS_TOKEN',
            'expiry_time' => time()
        );
        $tokens = $this->_cut->get_session_tokens();
        $this->assertEquals($tokens, $SESSION->oauth_tokens);
    }

    /**
     * User data is set statically, so that Moodle can access it from different instances of the same class
     */
    public function test_userdata() {
        $user = new stdClass();
        $user->username = 'robologo';
        $user->firstname = 'Rosa';
        $user->lastname = 'Luxemburg';
        $this->_cut->set_userdata($user);
        $this->assertEquals($user, $this->_cut->get_userdata());
        unset($this->_cut);
        $this->_cut = new oauth_moodle_user();
        $this->assertEquals($user, $this->_cut->get_userdata());
    }
}