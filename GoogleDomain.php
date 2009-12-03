<?php
class GoogleDomainException extends Exception {}

class GoogleDomain
{
    public static function get_users($api_key, $api_secret)
    {
        $api = new GoogleUtilityClient($api_key,
                                       $api_secret,
                                       'HOSTED');

        try
        {
            $api->authenticate();
            $user_response = $api->get($api->domain . '/user/2.0');
            
            $titles = $user_response['list']
                 ->getElementsByTagName('title');
            $users = array();
            foreach($titles as $title)
            {
                if($title->parentNode->nodeName == 'entry') {
                    $users[] = $title->textContent . '@'. $api->domain;
                }
            }
            
        }
        catch(GoogleClientUtilityException $e)
        {
            throw new GoogleDomainException($e->getMessage());
        }
        
        return $users;
    }

    public static function get_groups($api_key, $api_secret)
    {
        $api = new GoogleUtilityClient($api_key,
                                       $api_secret,
                                       'HOSTED');
        try
        {
            $api->authenticate();
            
            $groups_response = $api->get('group/2.0/' . $api->domain);
            $entries = $groups_response['list']->getElementsByTagName('property');
            $groups = array();
            foreach($entries as $entry)
            {
                if($entry->getAttribute('name') == 'groupName') {
                    $groups[] = $entry->getAttribute('value');
                }
            }
        }
        catch(GoogleClientUtilityException $e)
        {
            throw new GoogleDomainException($e->getMessage());
        }
        
        return $groups;
    }

}
