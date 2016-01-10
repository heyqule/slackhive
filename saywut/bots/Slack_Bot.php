<?php
/**
 * Created by PhpStorm.
 * User: heyqule
 * Date: 09/01/16
 * Time: 10:30 PM
 *
 * Bot Config:
 * $GLOBALS['BOT_CONFIG'][1] = array(
 *   'class'=>'Slack_Bot',
 *   'name' => 'SlackHive',
 *   'interval'=>60,
 *   );
 */
namespace Saywut;

use Slackbot;

require_once SAYWUT_ROOT_PATH.DS.'includes'.DS.'Bot.php';
require_once SAYWUT_SERVER_ROOT.DS.'slack/SlackUserCollection.php';

class Slack_Bot extends Bot
{
    protected $pinList;
    public function __construct($id,$config)
    {
        parent::__construct($id);
        $this->interval = $config['interval'];
    }
    protected function fetch() {
        $api = new Slackbot\Api();

        $data = array(
            'token' => Slackbot\Setting::API_AUTH_TOKEN,
            'channel' => Slackbot\Setting::THE_B_CHANNEL,
        );

        //Fetch All Pins
        $this->pinList = $api->getPins($data);

        $pinIds = array();
        //Fetch all saved pins based on ID
        foreach($this->pinList->items as $pin)
        {
            switch ($pin->type)
            {
                case 'file':
                    $pinIds[] = 'file-'.$pin->file->id;
                    break;
                case 'message':
                    $pinIds[] = 'msg-'.$pin->message->ts;
                    break;
                default:
                    break;
            }
        }


        //Remove match pins in saywut

        //Fetch B20 A5 Messages for each new pin

        //Only use the them if pins are within half hours.

        //Remove saved pin from slack (TO BE DISCUSS)
    }
    /*
     * Manipulating and storing data
     */
    protected function store() {
        //Build Message
        //Giphy handler
        //File handler
        //Regular website handler
    }
}