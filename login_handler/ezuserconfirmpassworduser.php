<?php
/**
 * File containing the eZUserConfirmPasswordUser class.
 *
 * @copyright Copyright (C) 1999 - 2006 eZ systems AS. All rights reserved.
 * @copyright Copyright (C) 1999 - 2016 Brookins Consulting. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2.0
 * @version 0.1.0
 * @package bcconfirmpassword
 */

class eZUserConfirmPasswordUser extends eZUser
{
    function eZUserConfirmPasswordUser( $row = array() )
    {
        $this->eZPersistentObject( $row );
        $this->OriginalPassword = false;
        $this->OriginalPasswordConfirm = false;
    }

    /**
     * Confirmes a user login and password is valid.
     *
     * This method does not do any house keeping work anymore (writing audits, etc).
     *
     * @param string $login
     * @param string $password
     * @param bool $authenticationMatch
     * @return mixed eZUser object on log in success, int userID if the username
     *         exists but log in failed, or false if the username doesn't exists.
     */
    public static function confirmUserPassword( $login, $password, $authenticationMatch = false )
    {
        $http = eZHTTPTool::instance();
        $db = eZDB::instance();
        if ( $authenticationMatch === false )
            $authenticationMatch = eZUserConfirmPasswordUser::authenticationMatch();
        $login = self::trimAuthString( $login );
        $password = self::trimAuthString( $password );
        $loginEscaped = $db->escapeString( $login );
        $passwordEscaped = $db->escapeString( $password );
        $loginArray = array();
        if ( $authenticationMatch & self::AUTHENTICATE_LOGIN )
            $loginArray[] = "login='$loginEscaped'";
        if ( $authenticationMatch & self::AUTHENTICATE_EMAIL )
        {
            if ( eZMail::validate( $login ) )
            {
                $loginArray[] = "email='$loginEscaped'";
            }
        }
        if ( empty( $loginArray ) )
            $loginArray[] = "login='$loginEscaped'";
        $loginText = implode( ' OR ', $loginArray );
        $contentObjectStatus = eZContentObject::STATUS_PUBLISHED;
        $ini = eZINI::instance();
        $databaseName = $db->databaseName();
        // if mysql
        if ( $databaseName === 'mysql' )
        {
            $query = "SELECT contentobject_id, password_hash, password_hash_type, email, login
                      FROM ezuser, ezcontentobject
                      WHERE ( $loginText ) AND
                        ezcontentobject.status='$contentObjectStatus' AND
                        ezcontentobject.id=contentobject_id AND
                        ( ( password_hash_type!=4 ) OR
                          ( password_hash_type=4 AND
                              ( $loginText ) AND
                          password_hash=PASSWORD('$passwordEscaped') ) )";
        }
        else
        {
            $query = "SELECT contentobject_id, password_hash,
                             password_hash_type, email, login
                      FROM   ezuser, ezcontentobject
                      WHERE  ( $loginText )
                      AND    ezcontentobject.status='$contentObjectStatus'
                      AND    ezcontentobject.id=contentobject_id";
        }
        $users = $db->arrayQuery( $query );
        $exists = false;
        if ( $users !== false && isset( $users[0] ) )
        {
            $ini = eZINI::instance();
            foreach ( $users as $userRow )
            {
                $userID = $userRow['contentobject_id'];
                $hashType = $userRow['password_hash_type'];
                $hash = $userRow['password_hash'];
                $exists = eZUserConfirmPasswordUser::authenticateHash( $userRow['login'], $password, eZUserConfirmPasswordUser::site(),
                                                    $hashType,
                                                    $hash );
                // If hash type is MySql
                if ( $hashType == self::PASSWORD_HASH_MYSQL and $databaseName === 'mysql' )
                {
                    $queryMysqlUser = "SELECT contentobject_id, password_hash, password_hash_type, email, login
                              FROM ezuser, ezcontentobject
                              WHERE ezcontentobject.status='$contentObjectStatus' AND
                                    password_hash_type=4 AND ( $loginText ) AND password_hash=PASSWORD('$passwordEscaped') ";
                    $mysqlUsers = $db->arrayQuery( $queryMysqlUser );
                    if ( isset( $mysqlUsers[0] ) )
                        $exists = true;
                }
                eZDebugSetting::writeDebug( 'kernel-user', eZUserConfirmPasswordUser::createHash( $userRow['login'], $password, eZUserConfirmPasswordUser::site(),
                                                                               $hashType, $hash ), "check hash" );
                eZDebugSetting::writeDebug( 'kernel-user', $hash, "stored hash" );
                 // If current user has been disabled after a few failed login attempts.
                $canLogin = eZUserConfirmPasswordUser::isEnabledAfterFailedLogin( $userID );
                if ( $exists )
                {
                    // We should store userID for warning message.
                    $GLOBALS['eZFailedLoginAttemptUserID'] = $userID;
                    $userSetting = eZUserSetting::fetch( $userID );
                    $isEnabled = $userSetting->attribute( "is_enabled" );
                    break;
                }
            }
        }
        if ( $exists and $isEnabled and $canLogin )
        {
            return new eZUserConfirmPasswordUser( $userRow );
        }
        else
        {
            return isset( $userID ) ? $userID : false;
        }
    }

    /**
     * Logs in an user if applied login and password is valid.
     *
     * This method does not do any house keeping work anymore (writing audits, etc).
     * When you call this method make sure to call loginSucceeded() or loginFailed()
     * depending on the success of the login.
     *
     * @param string $login
     * @param string $password
     * @param bool $authenticationMatch
     * @return mixed eZUserConfirmPasswordUser object on log in success, int userID if the username
     *         exists but log in failed, or false if the username doesn't exists.
     */
    protected static function _loginUser( $login, $password, $authenticationMatch = false )
    {
        $http = eZHTTPTool::instance();
        $db = eZDB::instance();
        if ( $authenticationMatch === false )
            $authenticationMatch = eZUserConfirmPasswordUser::authenticationMatch();
        $login = self::trimAuthString( $login );
        $password = self::trimAuthString( $password );
        $loginEscaped = $db->escapeString( $login );
        $passwordEscaped = $db->escapeString( $password );
        $loginArray = array();
        if ( $authenticationMatch & self::AUTHENTICATE_LOGIN )
            $loginArray[] = "login='$loginEscaped'";
        if ( $authenticationMatch & self::AUTHENTICATE_EMAIL )
        {
            if ( eZMail::validate( $login ) )
            {
                $loginArray[] = "email='$loginEscaped'";
            }
        }
        if ( empty( $loginArray ) )
            $loginArray[] = "login='$loginEscaped'";
        $loginText = implode( ' OR ', $loginArray );
        $contentObjectStatus = eZContentObject::STATUS_PUBLISHED;
        $ini = eZINI::instance();
        $databaseName = $db->databaseName();
        // if mysql
        if ( $databaseName === 'mysql' )
        {
            $query = "SELECT contentobject_id, password_hash, password_hash_type, email, login
                      FROM ezuser, ezcontentobject
                      WHERE ( $loginText ) AND
                        ezcontentobject.status='$contentObjectStatus' AND
                        ezcontentobject.id=contentobject_id AND
                        ( ( password_hash_type!=4 ) OR
                          ( password_hash_type=4 AND
                              ( $loginText ) AND
                          password_hash=PASSWORD('$passwordEscaped') ) )";
        }
        else
        {
            $query = "SELECT contentobject_id, password_hash,
                             password_hash_type, email, login
                      FROM   ezuser, ezcontentobject
                      WHERE  ( $loginText )
                      AND    ezcontentobject.status='$contentObjectStatus'
                      AND    ezcontentobject.id=contentobject_id";
        }
        $users = $db->arrayQuery( $query );
        $exists = false;
        if ( $users !== false && isset( $users[0] ) )
        {
            $ini = eZINI::instance();
            foreach ( $users as $userRow )
            {
                $userID = $userRow['contentobject_id'];
                $hashType = $userRow['password_hash_type'];
                $hash = $userRow['password_hash'];
                $exists = eZUserConfirmPasswordUser::authenticateHash( $userRow['login'], $password, eZUserConfirmPasswordUser::site(),
                                                    $hashType,
                                                    $hash );
                // If hash type is MySql
                if ( $hashType == self::PASSWORD_HASH_MYSQL and $databaseName === 'mysql' )
                {
                    $queryMysqlUser = "SELECT contentobject_id, password_hash, password_hash_type, email, login
                              FROM ezuser, ezcontentobject
                              WHERE ezcontentobject.status='$contentObjectStatus' AND
                                    password_hash_type=4 AND ( $loginText ) AND password_hash=PASSWORD('$passwordEscaped') ";
                    $mysqlUsers = $db->arrayQuery( $queryMysqlUser );
                    if ( isset( $mysqlUsers[0] ) )
                        $exists = true;
                }
                eZDebugSetting::writeDebug( 'kernel-user', eZUserConfirmPasswordUser::createHash( $userRow['login'], $password, eZUserConfirmPasswordUser::site(),
                                                                               $hashType, $hash ), "check hash" );
                eZDebugSetting::writeDebug( 'kernel-user', $hash, "stored hash" );
                 // If current user has been disabled after a few failed login attempts.
                $canLogin = eZUserConfirmPasswordUser::isEnabledAfterFailedLogin( $userID );
                if ( $exists )
                {
                    // We should store userID for warning message.
                    $GLOBALS['eZFailedLoginAttemptUserID'] = $userID;
                    $userSetting = eZUserSetting::fetch( $userID );
                    $isEnabled = $userSetting->attribute( "is_enabled" );
                    if ( $hashType != eZUserConfirmPasswordUser::hashType() and
                         strtolower( $ini->variable( 'UserSettings', 'UpdateHash' ) ) == 'true' )
                    {
                        $hashType = eZUserConfirmPasswordUser::hashType();
                        $hash = eZUserConfirmPasswordUser::createHash( $userRow['login'], $password, eZUserConfirmPasswordUser::site(),
                                                    $hashType );
                        $db->query( "UPDATE ezuser SET password_hash='$hash', password_hash_type='$hashType' WHERE contentobject_id='$userID'" );
                    }
                    break;
                }
            }
        }
        if ( $exists and $isEnabled and $canLogin )
        {
            return new eZUserConfirmPasswordUser( $userRow );
        }
        else
        {
            return isset( $userID ) ? $userID : false;
        }
    }

    /**
     * If needed, trims $string using AUTH_STRING_MAX_LENGTH to
     * avoid DDOS attack when the password is hashed.
     *
     * @param $string
     *
     * @return string valid password
     */
    private static function trimAuthString( $string )
    {
        if ( strlen( $string ) <= self::AUTH_STRING_MAX_LENGTH )
        {
            return $string;
        }
        else
        {
            return substr( $string, 0, self::AUTH_STRING_MAX_LENGTH );
        }
    }
}

?>
