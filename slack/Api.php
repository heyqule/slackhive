<?php
/**
 * Created by PhpStorm.
 * User: heyqule
 * Date: 12/12/15
 * Time: 6:27 PM
 */
namespace Slackbot;

class Api
{
    const API_URL = "https://slack.com/api/";

    public function getChannelMessages($data)
    {
        return $this->_request("channels.history",$data);
    }

    public function getUserList()
    {
        return $this->_request("users.list",array());
    }

    public function postMessage($data)
    {
        $response = $this->_request('chat.postMessage',$data);
        sleep(1);
        return $response;
    }

    public function getPins($data)
    {
        return $this->_request('pins.list',$data);
    }

    /**
     *
     * @param $method
     * @param $postData
     */
    private function _request($method,$postData = array())
    {
        if(empty($postData['token']))
        {
            $postData['token'] = Setting::API_AUTH_TOKEN;
        }

        $data_string = '';
        foreach($postData as $key=>$value)
        {
            $data_string .= $key.'='.urlencode($value).'&';
        }
        rtrim($data_string, '&');

        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL, self::API_URL.$method);
        curl_setopt($ch,CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        curl_close($ch);

        if(Setting::DEBUG_API)
        {
            echo $result."\n";
        }

        return json_decode($result);
    }

    /**
     * @param $message
     * @param $channel
     */
    public function slackBotSendMessage($message,$channel)
    {
        $postUrl = Setting::SLACKBOT_POST_URL."&channel=%23".urlencode($channel);
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_URL, $postUrl);
        curl_setopt($ch,CURLOPT_POSTFIELDS, $message);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        curl_close($ch);
        //1s per message limit.
        sleep(1);
    }

    /**
     * @param $messagePhaseFilter
     * @param $messageSenderFilter
     */
    public function filterMessages($messages, $messagePhaseFilter = array(),$messageSenderFilter = array())
    {
        $filteredMessages = array();
        foreach($messages as $message)
        {
            //Skip Bot message
            if(isset($message->subtype) && $message->subtype == 'bot_message') {
                continue;
            }

            //Skip API message
            if(!isset($message->user))
            {
                continue;
            }

            if(in_array($message->user,$messageSenderFilter))
            {
                continue;
            }

            foreach($messagePhaseFilter as $filter)
            {
                if(preg_match('/\b('.$filter.')\b/', $message->text) == true)
                {
                    $filteredMessages[] = $message;
                    break;
                }
            }
        }
        return $filteredMessages;
    }
}