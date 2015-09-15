<?php
defined('MOODLE_INTERNAL') || die();

/**
 * A user who authenticates using oauth
 * Class oauth_moodle_user
 */
class oauth_moodle_user {


    /**
     * Defined statically as Moodle needs access to it several times during the execution of the login process
     * @var stdClass
     */
    private static $_userdata;

    /**
     * Constructor
     */
    public function __construct() {

    }

    /**
     * Get the userdata
     * @return stdClass
     */
    public function get_userdata() {
        return self::$_userdata;
    }

    /**
     * Set the data for the user that we trying to log-in
     * @param stdClass $userdata
     */
    public function set_userdata($userdata) {
        self::$_userdata = $userdata;
    }


    /**
     * Maps the returned userdata to Moodle fields, based on the supplied mappings config
     * @param array $mappings
     * @return array
     */
    public function get_mapped_userdata(array $mappings) {
        $result = array();
        foreach ($mappings as $key => $value) {
            $result[$key] = isset(self::$_userdata->{$value}) ? self::$_userdata->{$value} : "";
        }
        return $result;
    }


    /**
     * Logs a user in based on their username and returns a user object, with Moodle properties set
     * @param moodle_functions $m
     * @param $now
     * @return mixed
     * @throws moodle_exception
     */
    public function login_user(moodle_functions $m, $now, $username_field) {
        global $CFG;
        $username = self::$_userdata->{$username_field};

        if (!$user = $m->authenticate_user_login($username, $now)) {
            throw new moodle_exception('Unable to authenticate OAUTH user '.$username);
        }
        if (!$user = $m->get_complete_user_data('id', $user->id)) {
            throw new moodle_exception('Unable to retrieve user data following OAUTH authentication for user '.$username);
        }
        if (!$user = $m->complete_user_login($user)) {
            throw new moodle_exception('Unable to complete user login after retrieving OAUTH data for user '.$username);
        }
        $user->loggedin = true;
        $user->site = $CFG->wwwroot;
        return $user;
    }

    /**
     * Sets the oauth tokens on the session, with an expiry time
     * @param stdClass $tokens
     * @param int $expire_from
     */
    public function set_session_tokens($tokens, $expire_from) {
        global $SESSION;
        $tokens->expiry_time = $expire_from + $tokens->expires_in;
        $SESSION->oauth_tokens = $tokens;
    }

    /**
     * Retrieves the oauth tokens from the session
     * @return stdClass
     */
    public function get_session_tokens() {
        global $SESSION;
        return $SESSION->oauth_tokens;
    }
}
