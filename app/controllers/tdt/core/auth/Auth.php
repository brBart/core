<?php

namespace tdt\core\auth;
/**
 * Auth Controller
 * @copyright (C) 2011,2013 by OKFN Belgium vzw/asbl
 * @license AGPLv3
 * @author Michiel Vancoillie <michiel@okfn.be>
 */
class Auth extends \Controller {

    private static $user;
    private static $password;

    /**
     * Check if user meets permissions required to do the request, otherwise prompt login
     */
    public static function requirePermissions($permissions = null){

        // Make sure permissions is an array
        if(!is_array($permissions)){
            $permissions = array($permissions);
        }

        // First check the permissions of the group 'everyone
        try{

            // Get the group
            $group = \Sentry::findGroupByName('everyone');

            // Get the group permissions
            $groupPermissions = $group->getPermissions();

            foreach($permissions as $permission){
                if(!empty($groupPermissions[$permission]) && $groupPermissions[$permission] == 1){
                    // Everyone has access
                    return true;
                }else{
                    break;
                }
            }

        }catch(\Cartalyst\Sentry\Groups\GroupNotFoundException $e){
            // Do nothing, proceed other checks
        }

        // Authenticate
        self::logIn();

        if(\Sentry::check()){

            // Get current user
            $user = \Sentry::getUser();

            // Check permissions
            if($user->hasAccess($permissions)){

                // Share user in views
                \View::share('user', $user);
                return true;
            }else{
                \App::abort(403, "The authenticated user hasn't got the permissions for this action.");
            }

        }else{
            \App::abort(401, 'Authentication is required.');
        }
    }

    /**
     * Log's user in
     */
    protected static function logIn(){

        // Basic auth, TODO: remove check
        if(\App::environment() != 'testing'){
            header('WWW-Authenticate: Basic');
            header('HTTP/1.0 401 Unauthorized');
        }

        // Fix basic auth on some servers;
        self::basicAuth();

        if(isset(self::$user)){
            try{
                // Set login credentials
                $credentials = array(
                    'email'    => self::$user,
                    'password' => self::$password,
                );

                // Try to authenticate the user
                $user = \Sentry::authenticate($credentials, false);

            }catch (\Cartalyst\Sentry\Users\LoginRequiredException $e){
                \App::abort(401, 'Authentication is required.');
            }catch (\Cartalyst\Sentry\Users\PasswordRequiredException $e){
                \App::abort(401, 'Authentication is required.');
            }catch (\Cartalyst\Sentry\Users\WrongPasswordException $e){
                \App::abort(401, 'Authentication is required, username and password mismatch.');
            }catch (\Cartalyst\Sentry\Users\UserNotFoundException $e){
                \App::abort(401, 'Authentication is required, username and password mismatch.');
            }catch (\Cartalyst\Sentry\Users\UserNotActivatedException $e){
                \App::abort(403, 'Authentication is required, user is not activated.');
            }

            // The following is only required if throttle is enabled
            catch (\Cartalyst\Sentry\Throttling\UserSuspendedException $e){
                \App::abort(403, 'Authentication is required, user is suspended.');
            }catch (\Cartalyst\Sentry\Throttling\UserBannedException $e){
                \App::abort(403, 'Authentication is required, user is banned.');
            }
        }else{
            \App::abort(401, 'Authentication is required.');
        }
    }

    /**
     *  Fix for empty PHP_AUTH_USER
     */
    protected static function basicAuth(){

        self::$user = \Request::header('PHP_AUTH_USER');
        self::$password = \Request::header('PHP_AUTH_PW');
        $auth_header = \Request::header('Authorization');

        if(!empty($auth_header)){
            list(self::$user, self::$password) = explode(':' , base64_decode(substr(\Request::header('Authorization'), 6)));
        }
    }


}