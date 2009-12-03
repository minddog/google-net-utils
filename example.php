<?php

require_once 'GoogleUtilityClient.php';
require_once 'GoogleDomain.php';

$api_key = 'myemail@gmail.com';
$api_secret = 'asdfadsfasdf';

$users = array();
$groups = array();
try
{
    $users = GoogleDomain::get_users($api_key, $api_secret);
    $groups = GoogleDomain::get_groups($api_key, $api_secret);
}
catch (GoogleDomainException $e)
{
    echo $e->getMessage();
}
var_dump($users);
var_dump($groups);