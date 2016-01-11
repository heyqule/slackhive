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
    protected $pinBeforeMsgs = array();

    protected $pinAfterMsgs = array();

    protected $hasTop = false;

    protected $slackUser;

    protected $api;

    public function __construct($id,$config)
    {
        parent::__construct($id);
        $this->interval = $config['interval'];
        $this->slackUser = new Slackbot\SlackUserCollection();
        $this->api = new Slackbot\Api();
    }

    protected function fetch() {
        $this->slackUser->update();

        $request = array(
            'token' => Slackbot\Setting::API_AUTH_TOKEN,
            'channel' => Slackbot\Setting::THE_B_CHANNEL,
        );

        //Fetch All Pins
        $pinList = $this->api->getPins($request);

        $pinIds = array();
        //Fetch all saved pins based on ID
        foreach($pinList->items as $pin)
        {
            switch ($pin->type)
            {
                case 'file':
                    $pid = 'file-'.$pin->file->id;
                    break;
                case 'message':
                    $pid = 'msg-'.$pin->message->ts;
                    break;
                default:
                    break;
            }
            $pinIds[] = $pid;
            $this->data[$pid] = $pin;
        }

        unset($pin,$pid);

        //Remove it if there are match pins in saywut
        $postCollection = new Post_Collection();
        $postCollection->addWhere('provider_pid','in',$pinIds);

        foreach($postCollection as $post)
        {
            unset($this->data[$post->getProviderCid()]);
        }

        //Fetch B20 A5 Messages for each new pin
        foreach($this->data as $providerId => $pin)
        {
            if($pin->type == 'message')
            {
                $this->_getMessageBefore($providerId,$pin,$request);
                $this->_getMessageAfter($providerId,$pin,$request);
            }
        }
    }

    protected function _getMessageBefore($providerId,$pin,$request)
    {
        $this->hasTop = false;
        $msgRequest = $request;
        $msgRequest['latest'] = $pin->message->ts;
        $msgRequest['count'] = 20;
        $oldMessages = $this->api->getChannelMessages($msgRequest);

        if($oldMessages->ok && sizeof($oldMessages->messages))
        {
            $this->hasTop = true;
            $oldMessages->messages = array_reverse($oldMessages->messages);
            $this->pinBeforeMsgs[$providerId] = $oldMessages->messages;
        }
    }

    protected function _getMessageAfter($providerId,$pin,$request)
    {
        if($this->hasTop)
        {
            $msgRequest = $request;
            $msgRequest['oldest'] = $pin->message->ts;
            $msgRequest['count'] = 5;
            $oldMessages = $this->api->getChannelMessages($msgRequest);

            if($oldMessages->ok && sizeof($oldMessages->messages))
            {
                $oldMessages->messages = array_reverse($oldMessages->messages);
                $this->pinAfterMsgs[$providerId] = $oldMessages->messages;
            }
        }
    }
    /*
     * Manipulating and storing data
     */
    protected function store()
    {
        foreach($this->data as $providerCid => $pin)
        {
            $html = '';
            if($pin->type == 'message')
            {
                $message = $pin->message;
                $html .= $this->_processBeforeMsg($providerCid, $pin);

                $html .= $this->_processMessage($message,'is-pin');

                $html .= $this->_processAfterMsg($providerCid, $pin);

                $postData['title'] = str_replace(array('<','>'),'',$message->text);
                $postData['content'] = $html;
                $postData['timestamp'] = date(DT_FORMAT, $message->ts);
            }

            if($pin->type == 'file')
            {
                $file = $pin->file;
                $html .= $this->_processFile($file);

                $postData['title'] = str_replace(array('<','>'),'',$file->title);
                $postData['content'] = $html;
                $postData['timestamp'] = date(DT_FORMAT, $file->timestamp);
            }

            $post = new Post();
            $post->id = null;
            $post->title = $postData['title'];
            $post->provider_id = $this->provider_id;
            $post->provider_cid = $providerCid;
            $post->contents = $postData['content'];
            $post->create_time = $postData['timestamp'];
            $post->update_time = $postData['timestamp'];
            $post->save();
        }

        //Remove saved pin from slack (TO BE DISCUSS) when success
        $this->_removePins();
    }

    protected function _processBeforeMsg($key,$pin)
    {
        $beforeMessages = array();
        $html = '';
        if(!empty($this->pinBeforeMsgs[$key]))
        {
            $beforeMessages = $this->pinBeforeMsgs[$key];
        }

        foreach($beforeMessages as $msg)
        {
            $html .= $this->_processMessage($msg,'before-pin');
        }

        return $html;
    }

    protected function _processAfterMsg($key,$pin)
    {
        $afterMessages = array();
        $html = '';
        if(!empty($this->pinAfterMsgs[$key]))
        {
            $afterMessages = $this->pinAfterMsgs[$key];
        }

        foreach($afterMessages as $msg)
        {
            $html .= $this->_processMessage($msg,'after-pin');
        }

        return $html;
    }

    protected function _processMessage($message,$additionalClass = '')
    {
        $html = "<div class=\"msg-block {$additionalClass}\">";

        $html .= "<div class=\"msg-meta\">";
        $html .= "<span class=\"name\">{$this->_getUser($message,true)}</span><span class=\"date\">".date(DT_FORMAT, $message->ts)."</span>";
        $html .= "</div>";

        $html .= "<div class=\"msg-content\">";
        if(property_exists($message, 'subtype') && $message->subtype == 'pinned_item')
        {
            $attachment = $message->attachments[0];
            if(property_exists($attachment,'author_icon'))
            {
                $html .= "pinned <img src=\"{$attachment->author_icon}\"/>{$attachment->author_name}: {$attachment->text}";
            }
            elseif(property_exists($attachment,'author_surname'))
            {
                $html .= "pinned {$attachment->author_surname}: {$attachment->text}";
            }
        }
        else
        {
            $html .= htmlentities($message->text,ENT_HTML5);

            if (!empty($message->attachments))
            {
                $html .= "<div class=\"attachement\">";
                $attachment = $message->attachments[0];
                if (property_exists($attachment, 'video_html'))
                {
                    $videoUrl = str_replace(array('autoplay=1'), '', $attachment->video_html);
                    $html .= $videoUrl;
                } else if (property_exists($attachment, 'audio_html'))
                {
                    $html .= $attachment->audio_html;
                } else if (property_exists($attachment, 'image_url'))
                {
                    $html .= "<img src=\"{$attachment->image_url}\" />";
                } else if (property_exists($attachment, 'thumb_url'))
                {
                    $html .= "<img src=\"{$attachment->thumb_url}\" />";
                }

                if (property_exists($attachment, 'service_name') && $attachment->service_name == 'twitter')
                {
                    $html .= "<a href=\"{$attachment->author_link}\" ><img src=\"{$attachment->author_icon}\">@";
                    $html .= "{$attachment->author_name} </a>: {$attachment->text}";
                }
                $html .= '</div>';
            }
        }

        $html .= '</div></div>';

        return $html;
    }

    protected function _processFile($file)
    {
        $html = "<div class=\"msg-block is-pin\">";

        $html .= "<div class=\"msg-meta\">";
        $html .= "<span class=\"name\">{$this->_getUser($file,true)}</span><span class=\"date\">".date(DT_FORMAT, $file->timestamp)."</span>";
        $html .= "</div>";

        $html .= "<div class=\"msg-content\">";
        $html .= "<a href=\"{$file->permalink}\">A Slack {$file->pretty_type} File</a>";
        /* Should I link the pic?
        if($file->mimetype == 'image/jpeg' ||
           $file->mimetype == 'image/gif'  ||
           $file->mimetype == 'image/webp' ||
           $file->mimetype == 'image/png'
        )
        {
            $html .= '<div class="attachment"><img src="'.$file->thumb_480.'"/></div>';
        }
        */
        $html .= "</div></div>";
        return $html;
    }

    protected function _removePins()
    {

    }


    protected function _getUser($message,$withPic = false)
    {
        $rcName = '';

        if(property_exists($message,'user'))
        {
            $member = $this->slackUser->getMemberById($message->user);
            if($withPic && property_exists($member,'profile') && property_exists($member->profile,'image_72'))
            {
                $rcName .= "<img src=\"{$member->profile->image_72}\" /><br />";
            }
            $rcName .= $this->slackUser->getName($member);
        }
        elseif(property_exists($message,'username'))
        {
            $rcName = $message->username;
        }

        return $rcName;
    }

}