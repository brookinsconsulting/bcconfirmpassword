<?php
/**
 * File containing the confirmpassword/confirm module view based heavily on the default user/login module view customized to only confirm password not login.
 *
 * @copyright Copyright (C) 1999 - 2016 Brookins Consulting. All rights reserved.
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2.0
 * @version 0.1.0
 * @package bcconfirmpassword
 */

/**
 * Default module parameters
 */
//$Module->setExitStatus( EZ_MODULE_STATUS_SHOW_LOGIN_PAGE );
$Module = $Params["Module"];

/**
* Default class instances
*/
$http = eZHTTPTool::instance();

// Init template behaviors
$tpl = eZTemplate::factory();

// Access ini variables
$ini = eZINI::instance();
$iniConfirmPassword = eZINI::instance( 'bcconfirmpassword.ini' );

$userLogin = '';
$userPassword = '';
$userRedirectURI = '';

$loginWarning = false;

$siteAccessAllowed = true;
$siteAccessName = false;

if ( isset( $Params['SiteAccessAllowed'] ) )
    $siteAccessAllowed = $Params['SiteAccessAllowed'];
if ( isset( $Params['SiteAccessName'] ) )
    $siteAccessName = $Params['SiteAccessName'];

$postData = ''; // Will contain post data from previous page.
if ( $http->hasSessionVariable( '$_POST_BeforeLogin', false ) )
{
    $postData = $http->sessionVariable( '$_POST_BeforeLogin' );
    $http->removeSessionVariable( '$_POST_BeforeLogin' );
}


if ( $Module->isCurrentAction( 'Cancel' ) )
{
        $userRedirectURI = trim( $http->sessionVariable( 'LastAccessesURI', '/' ) );

        if ( empty( $userRedirectURI ) )
        {
            $userRedirectURI = '/';
        }

        if ( $userRedirectURI == '' )
        {
            $userRedirectURI = $ini->variable( 'SiteSettings', 'DefaultPage' );
        }

        return $Module->redirectTo( $userRedirectURI );
}
else if ( $Module->isCurrentAction( 'Confirm' ) and
     $Module->hasActionParameter( 'UserConfirmPassword' ) and
     !$http->hasPostVariable( "RegisterButton" ) and 
     !$http->hasPostVariable( "Cancel" )
     )
{
    $userLogin = eZUserConfirmPasswordUser::currentUser()->Login;
    $userPassword = $Module->actionParameter( 'UserConfirmPassword' );
    $userRedirectURI = $Module->actionParameter( 'UserConfirmPasswordRedirectURI' );

    if ( trim( $userRedirectURI ) == "" )
    {
        // Only use redirection if RequireUserLogin is disabled
        $requireUserLogin = ( $ini->variable( "SiteAccessSettings", "RequireUserLogin" ) == "true" );
        if ( !$requireUserLogin )
        {
            $userRedirectURI = trim( $http->postVariable( 'RedirectURI', '' ) );
            if ( empty( $userRedirectURI ) )
            {
                $userRedirectURI = $http->sessionVariable( 'LastAccessesURI', '/' );
            }
        }

        if ( $http->hasSessionVariable( "RedirectAfterLogin", false ) )
        {
            $userRedirectURI = $http->sessionVariable( "RedirectAfterLogin" );
        }
    }
    // Save array of previous post variables in session variable
    $post = $http->attribute( 'post' );
    $lastPostVars = array();
    foreach ( array_keys( $post ) as $postKey )
    {
        if ( substr( $postKey, 0, 5 ) == 'Last_' )
            $lastPostVars[ substr( $postKey, 5, strlen( $postKey ) )] = $post[ $postKey ];
    }
    if ( count( $lastPostVars ) > 0 )
    {
        $postData = $lastPostVars;
        $http->setSessionVariable( 'LastPostVars', $lastPostVars );
    }

    $user = false;
    if ( $userLogin != '' )
    {
        if ( $http->hasSessionVariable( "RedirectAfterLogin", false ) )
        {
            $http->removeSessionVariable( 'RedirectAfterLogin' );
        }

        if ( $ini->hasVariable( 'UserSettings', 'LoginHandler' ) )
        {
            $loginHandlers = $ini->variable( 'UserSettings', 'LoginHandler' );
        }
        else
        {
            $loginHandlers = array( 'standard' );
        }
        $hasAccessToSite = true;

        if ( $http->hasPostVariable( 'Cookie' )
            && $ini->hasVariable( 'Session', 'RememberMeTimeout' )
            && ( $rememberMeTimeout = $ini->variable( 'Session', 'RememberMeTimeout' ) )
        )
        {
            eZSession::setCookieLifetime( $rememberMeTimeout );
        }

        $loginHandlers = array( 'userconfirmpassword' );

        foreach ( array_keys ( $loginHandlers ) as $key )
        {
            $loginHandler = $loginHandlers[$key];
            $userClass = eZUserLoginHandler::instance( $loginHandler );

            if ( !is_object( $userClass ) )
            {
                continue;
            }

            // $user = $userClass->loginUser( $userLogin, $userPassword );
            $user = $userClass->confirmUserPassword( $userLogin, $userPassword );

            if ( $user instanceof eZUser || $user instanceof eZUserConfirmPasswordUser )
            {
                $hasAccessToSite = $user->canLoginToSiteAccess( $GLOBALS['eZCurrentAccess'] );
                $http->setSessionVariable( "BCConfirmPasswordUserConfirmed", time() );

                if ( !$hasAccessToSite )
                {
                    $user->logoutCurrent();
                    $user = null;
                    $siteAccessName = $GLOBALS['eZCurrentAccess']['name'];
                    $siteAccessAllowed = false;
                }
                break;
            }
        }
        if ( !( $user instanceof eZUser || $user instanceof eZUserConfirmPasswordUser ) and $hasAccessToSite )
            $loginWarning = true;
    }
    else
    {
        $loginWarning = true;
    }

    $redirectionURI = $userRedirectURI;

    // Determine if we already know redirection URI.
    $haveRedirectionURI = ( $redirectionURI != '' && $redirectionURI != '/' );

    if ( !$haveRedirectionURI )
        $redirectionURI = $ini->variable( 'SiteSettings', 'DefaultPage' );

    /* If the user has successfully passed authorization
     * and we don't know redirection URI yet.
     */
    if ( is_object( $user ) && !$haveRedirectionURI )
    {
        /*
         * Choose where to redirect the user to after successful login.
         * The checks are done in the following order:
         * 1. Per-user.
         * 2. Per-group.
         *    If the user object is published under several groups, main node is chosen
         *    (it its URI non-empty; otherwise first non-empty URI is chosen from the group list -- if any).
         *
         * See doc/features/3.8/advanced_redirection_after_user_login.txt for more information.
         */

        // First, let's determine which attributes we should search redirection URI in.
        $userUriAttrName  = '';
        $groupUriAttrName = '';
        if ( $ini->hasVariable( 'UserSettings', 'LoginRedirectionUriAttribute' ) )
        {
            $uriAttrNames = $ini->variable( 'UserSettings', 'LoginRedirectionUriAttribute' );
            if ( is_array( $uriAttrNames ) )
            {
                if ( isset( $uriAttrNames['user'] ) )
                    $userUriAttrName = $uriAttrNames['user'];

                if ( isset( $uriAttrNames['group'] ) )
                    $groupUriAttrName = $uriAttrNames['group'];
            }
        }

        $userObject = $user->attribute( 'contentobject' );

        // 1. Check if redirection URI is specified for the user
        $userUriSpecified = false;
        if ( $userUriAttrName )
        {
            $userDataMap = $userObject->attribute( 'data_map' );
            if ( !isset( $userDataMap[$userUriAttrName] ) )
            {
                eZDebug::writeWarning( "Cannot find redirection URI: there is no attribute '$userUriAttrName' in object '" .
                                       $userObject->attribute( 'name' ) .
                                       "' of class '" .
                                       $userObject->attribute( 'class_name' ) . "'." );
            }
            elseif ( ( $uriAttribute = $userDataMap[$userUriAttrName] ) &&
                     ( $uri = $uriAttribute->attribute( 'content' ) ) )
            {
                $redirectionURI = $uri;
                $userUriSpecified = true;
            }
        }

        // 2.Check if redirection URI is specified for at least one of the user's groups (preferring main parent group).
        if ( !$userUriSpecified && $groupUriAttrName && $user->hasAttribute( 'groups' ) )
        {
            $groups = $user->attribute( 'groups' );

            if ( isset( $groups ) && is_array( $groups ) )
            {
                $chosenGroupURI = '';
                foreach ( $groups as $groupID )
                {
                    $group = eZContentObject::fetch( $groupID );
                    $groupDataMap = $group->attribute( 'data_map' );
                    $isMainParent = ( $group->attribute( 'main_node_id' ) == $userObject->attribute( 'main_parent_node_id' ) );

                    if ( !isset( $groupDataMap[$groupUriAttrName] ) )
                    {
                        eZDebug::writeWarning( "Cannot find redirection URI: there is no attribute '$groupUriAttrName' in object '" .
                                               $group->attribute( 'name' ) .
                                               "' of class '" .
                                               $group->attribute( 'class_name' ) . "'." );
                        continue;
                    }
                    $uri = $groupDataMap[$groupUriAttrName]->attribute( 'content' );
                    if ( $uri )
                    {
                        if ( $isMainParent )
                        {
                            $chosenGroupURI = $uri;
                            break;
                        }
                        elseif ( !$chosenGroupURI )
                            $chosenGroupURI = $uri;
                    }
                }

                if ( $chosenGroupURI ) // if we've chose an URI from one of the user's groups.
                    $redirectionURI = $chosenGroupURI;
            }
        }
    }

    $userID = 0;
    if ( $user instanceof eZUser || $user instanceof eZUserConfirmPasswordUser )
        $userID = $user->id();
    if ( $userID > 0 )
    {
        $http->removeSessionVariable( 'eZUserLoggedInID' );
        $http->setSessionVariable( 'eZUserLoggedInID', $userID );

        // Remove all temporary drafts
        eZContentObject::cleanupAllInternalDrafts( $userID );
        return $Module->redirectTo( $redirectionURI );
    }
}
else
{
    // called from outside of a template (?)
    $requestedURI = $GLOBALS['eZRequestedURI'];

    if ( $requestedURI instanceof eZURI )
    {
        $requestedModule = $requestedURI->element( 0, false );
        $requestedView = $requestedURI->element( 1, false );
        if ( $requestedModule != 'confirmpassword' or
             $requestedView != 'confirm' )
        {
            $userRedirectURI = $requestedURI->originalURIString( false );
        }
        else
        {
            if ( trim( $userRedirectURI ) == "" )
            {
                // Only use redirection if RequireUserLogin is disabled
                $requireUserLogin = ( $ini->variable( "SiteAccessSettings", "RequireUserLogin" ) == "true" );
                if ( !$requireUserLogin )
                {
                    $userRedirectURI = trim( $http->postVariable( 'RedirectURI', '' ) );
            if ( empty( $userRedirectURI ) )
            {
                $userRedirectURI = $http->sessionVariable( 'LastAccessesURI', '/' );
            }
        }

        if ( $http->hasSessionVariable( "RedirectAfterLogin", false ) )
        {
            $userRedirectURI = $http->sessionVariable( "RedirectAfterLogin" );
        }
    }

        }
    }
}

if ( $http->hasPostVariable( "RegisterButton" ) )
{
    $Module->redirectToView( 'register' );
}

$userIsNotAllowedToLogin = false;
$failedLoginAttempts = false;
$maxNumOfFailedLogin = !eZUserConfirmPasswordUser::isTrusted() ? eZUserConfirmPasswordUser::maxNumberOfFailedLogin() : false;

// Should we show message about failed login attempt and max number of failed login
if ( $loginWarning and isset( $GLOBALS['eZFailedLoginAttemptUserID'] ) )
{
    $showMessageIfExceeded = $ini->hasVariable( 'UserSettings', 'ShowMessageIfExceeded' ) ? $ini->variable( 'UserSettings', 'ShowMessageIfExceeded' ) == 'true' : false;

    $failedUserID = $GLOBALS['eZFailedLoginAttemptUserID'];
    $failedLoginAttempts = eZUserConfirmPasswordUser::failedLoginAttemptsByUserID( $failedUserID );

    $canLogin = eZUserConfirmPasswordUser::isEnabledAfterFailedLogin( $failedUserID );
    if ( $showMessageIfExceeded and !$canLogin )
        $userIsNotAllowedToLogin = true;
}

$tpl = eZTemplate::factory();

$tpl->setVariable( 'login', $userLogin, 'User' );
$tpl->setVariable( 'post_data', $postData, 'User' );
$tpl->setVariable( 'password', $userPassword, 'User' );
$tpl->setVariable( 'redirect_uri', $userRedirectURI . eZSys::queryString(), 'User' );
$tpl->setVariable( 'warning', array( 'bad_login' => $loginWarning ), 'User' );

$tpl->setVariable( 'site_access', array( 'allowed' => $siteAccessAllowed,
                                         'name' => $siteAccessName ) );
$tpl->setVariable( 'user_is_not_allowed_to_login', $userIsNotAllowedToLogin, 'User' );
$tpl->setVariable( 'failed_login_attempts', $failedLoginAttempts, 'User' );
$tpl->setVariable( 'max_num_of_failed_login', $maxNumOfFailedLogin, 'User' );


$Result = array();
$Result['content'] = $tpl->fetch( 'design:confirmpassword/confirm.tpl' );
$Result['path'] = array( array( 'text' => ezpI18n::tr( 'kernel/user', 'ConfirmPassword' ),
                                'url' => false ),
                         array( 'text' => ezpI18n::tr( 'kernel/user', 'Confirm' ),
                                'url' => false ) );
if ( $ini->variable( 'SiteSettings', 'LoginPage' ) == 'custom' )
    $Result['pagelayout'] = 'loginpagelayout.tpl';

?>