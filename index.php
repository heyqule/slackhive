<?php
/**
 * Created by PhpStorm.
 * User: heyqule
 * Date: 09/01/16
 * Time: 10:32 PM
 */

include "slack/SlackUserCollection.php";

$api = new Slackbot\Api();

$data = array(
    'token' => Slackbot\SETTING::API_AUTH_TOKEN,
    'channel' => Slackbot\SETTING::THE_B_CHANNEL,
);

$pinList = $api->getPins($data);

foreach($pinList->items as $pin)
{
    echo '<div style="margin:1em; border:solid 1px green;">';
    if($pin->type == 'message')
    {
        $message = $pin->message;

        echo $message->text.'<br />';
        if (!empty($message->attachments))
        {
            if (property_exists($message->attachments[0], 'image_url'))
            {
                echo '<img src="' . $message->attachments[0]->image_url . '" />';
            }

            if (property_exists($message->attachments[0], 'thumb_url'))
            {
                echo '<img src="' . $message->attachments[0]->thumb_url . '" />';
            }
        }
    }

    if($pin->type == 'file')
    {
        echo $pin->file->permalink;
    }
    echo '</div>';
}

