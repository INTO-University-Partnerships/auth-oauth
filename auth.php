<?php
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.'); //  It must be included from a Moodle page
}

require_once(__DIR__ . '/../../lib/authlib.php');
require_once(__DIR__ . '/../../lib/validateurlsyntax.php');
require_once(__DIR__ . '/oauth_request.php');
require_once(__DIR__ . '/oauth_moodle_user.php');
require_once(__DIR__ . '/moodle_functions.php');

/**
 * Oauth authentication plugin.
 */
class auth_plugin_oauth extends auth_plugin_base {

    /**
     * A backdoor request
     * @var bool
     */
    protected $_backdoor;

    /**
     * Constructor.
     */
    function auth_plugin_oauth() {
        $this->authtype = 'oauth';
        $this->config = get_config('auth/oauth');
    }

    /**
     * Set as a backdoor request
     */
    public function set_backdoor() {
        $this->_backdoor = true;
    }

    /**
     * Whether to use the "backdoor"
     * @return bool
     */
    protected function is_backdoor_request() {
        global $SESSION;
        if ($this->_backdoor) {
            return true;
        }
        if (optional_param('backdoor', false, PARAM_BOOL) || data_submitted() || !empty($SESSION->loginerrormsg)) {
            return true;
        }
        return false;
    }

    /**
     * Returns true if the username is set on the oauth_moodle_user class
     * and matches the username parameter
     * Password is not used
     * @param string $username
     * @param string $password
     * @return bool Authentication success or failure.
     */
    function user_login($username, $password) {
        $user = new oauth_moodle_user();
        $data = $user->get_userdata();
        $username_field = get_config('auth/oauth', 'username');
        if (!isset($data->{$username_field})) {
            return false;
        }
        return $username == $data->{$username_field};
    }

    /**
     * Returns the user information for 'external' users.
     * @param string $username
     * @return array|mixed
     */
    function get_userinfo($username) {
        $user = new oauth_moodle_user();
        $mappings = $this->get_mappings();
        return $user->get_mapped_userdata($mappings);
    }

    /**
     * Returns an array containing attribute mappings between Moodle and the Identity Provider.
     * @return array
     */
    function get_mappings() {
        $configarray = (array) $this->config;

        if (isset($configarray->userfields)) {
            $fields = $configarray->userfields;
        } else {
            $fields = array("firstname", "lastname", "email", "phone1", "phone2",
                "department", "address", "city", "country", "description",
                "idnumber", "lang", "guid");
        }

        $moodleattributes = array();
        foreach ($fields as $field) {
            if (isset($configarray["field_map_$field"])) {
                $moodleattributes[$field] = $configarray["field_map_$field"];
            }
        }

        return $moodleattributes;
    }

    /**
     * Returns true if this authentication plugin is 'internal'.
     *
     * @return bool
     */
    function is_internal() {
        return false;
    }

    /**
     * Returns true if this authentication plugin can change the user's
     * password.
     *
     * @return bool
     */
    function can_change_password() {
        return false;
    }

    /**
     * Redirect if user is not attempting to use the backdoor and the oauth_redirect configuration option is true
     */
    function loginpage_hook($request = null) {
        global $SESSION;
        if ($this->is_backdoor_request()) {
            return;
        }
        $config = $this->config;
        if (!isset($config->oauth_redirect) || !$config->oauth_redirect) {
            return;
        }
        $SESSION->oauth_state = $state = 'authorizing_'.md5(rand());
        $provider = json_decode($config->oauth_provider);
        $oauth = ($request !== null) ?  $request : new oauth_request();
        $oauth->request_authorization_code($provider, new moodle_functions(), $state);
    }

    /**
     * Redirect to the provider for logout
     */
    function logoutpage_hook($request = null) {
        global $USER;
        $config = $this->config;
        if (!isset($config->oauth_redirect) || !$config->oauth_redirect || $USER->auth !== 'oauth') {
            return;
        }
        $provider = json_decode($config->oauth_provider);
        $oauth = ($request !== null) ?  $request : new oauth_request();
        $oauth->request_provider_logout($provider, new moodle_functions());

    }

    /**
     * Configuration form
     * @param object $config
     * @param object $err
     * @param array $user_fields
     */
    function config_form($config, $err, $user_fields) {
        $fields = array(
            'client_id',
            'client_secret',
            'authorize_url',
            'token_url',
            'api_user_get',
            'logout_url'
        );
        if (property_exists($config, 'oauth_provider')) {
            $values = json_decode($config->oauth_provider);
        } else {
            $values = $config;
        }

        array_unshift($user_fields, 'username');

        $redirect = (isset($config->oauth_redirect) && $config->oauth_redirect);
        include ('config.html.php');
    }

    /**
     * Validate form data
     * @param $form
     * @param array $err
     */
    function validate_form($form, &$err) {
        $urls = array('token_url', 'authorize_url', 'api_user_get', 'logout_url');
        foreach ($urls as $url) {
            if (!validateUrlSyntax($form->{$url})) {
                $err[$url] = "Invalid url syntax";
            }
        }
    }

    /**
     * Save config
     * @param $config
     * @return bool
     */
    function process_config($config) {
        $json = array(
            'client_id' => clean_param($config->client_id, PARAM_TEXT),
            'client_secret' => clean_param($config->client_secret, PARAM_TEXT),
            'authorize_url' => clean_param($config->authorize_url, PARAM_URL),
            'token_url' => clean_param($config->token_url, PARAM_URL),
            'api_user_get' => clean_param($config->api_user_get, PARAM_URL),
            'logout_url' => clean_param($config->logout_url, PARAM_URL),
        );
        set_config('oauth_redirect', isset($config->oauth_redirect) && $config->oauth_redirect, 'auth/oauth');
        set_config('oauth_provider', json_encode($json), 'auth/oauth');
        set_config('username', $config->lockconfig_field_map_username, 'auth/oauth');
        return parent::process_config($config);
    }

}
