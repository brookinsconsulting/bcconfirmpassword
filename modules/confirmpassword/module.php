<?php
/**
 * File containing the confirmpassword module configuration file, module.php
 *
 * @copyright Copyright (C) 1999 - 2016 Brookins Consulting. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2 (or later)
 * @version 0.1.0
 * @package bcconfirmpassword
*/

// Define module name
$Module = array('name' => 'BC Confirm Password');

// Define module view and parameters
$ViewList = array();

// Define 'confirm' module view parameters
$ViewList['confirm'] = array( 'script' => 'confirm.php',
                              'ui_context' => 'authentication',
                              'functions' => array( 'confirm' ),
                              'default_navigation_part' => 'bcconfirmpasswordnavigationpart',
                              'post_actions' => array(),
//                              'default_action' => array( array( 'name' => 'Confirm',
//                                                                'type' => 'post',
//                                                                'parameters' => array( 'ConfirmPassword' ) ) ),
                              'single_post_actions' => array( 'ConfirmButton' => 'Confirm',
                                                              'CancelButton' => 'Cancel' ),
                              'post_action_parameters' => array( 'Confirm' => array( 'UserConfirmPassword' => 'ConfirmPassword',
                                                                                     'UserConfirmPasswordRedirectURI' => 'RedirectURI' ),
                                                                 'Cancel' => array( 
                                                                                    'UserConfirmPasswordRedirectURI' => 'RedirectURI' ) ),
/*
                              'default_action' => array( array( 'name' => 'Confirm',
                                                                'type' => 'post',
                                                                'parameters' => array( 'ConfirmPassword', 'Cancel' ) ),
//                                                         array( 'name' => 'Cancel',
//                                                                'type' => 'post',
//                                                                'parameters' => array( 'Cancel' ) )
 ),
                              'single_post_actions' => array( 'ConfirmPasswordButton' => 'Confirm',
                                                              'CancelButton' => 'Cancel' ),
                              'post_action_parameters' => array( 'Confirm' => array( 'UserConfirmPassword' => 'ConfirmPassword',
                                                                                     'UserConfirmPasswordRedirectURI' => 'RedirectURI' ),
                                                                 'Cancel' => array( 'UserConfirmPassword' => 'ConfirmPassword',
                                                                                    'UserConfirmPasswordRedirectURI' => 'RedirectURI' ) ),
*/
                              'params' => array( 'ObjectID', 'EditVersion', 'EditLanguage', 'FromLanguage' ) );

// Define function parameters
$FunctionList = array();

// Define function 'confirm' parameters
$FunctionList['confirm'] = array( 'SiteAccess' => $SiteAccess );

?>