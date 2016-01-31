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

    protected $rawPinList;

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
        $this->rawPinList = $this->api->getPins($request);

        $pinIds = array();
        //Fetch all saved pins based on ID
        foreach($this->rawPinList->items as $pin)
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
            try
            {
                if ($pin->type == 'message')
                {
                    $message = $pin->message;
                    $htmlObj = $this->_processBeforeMsg($providerCid);

                    $htmlObj[] = $this->_processMessage($message, 'is-pin');

                    $afterMsgHtmlObj = $this->_processAfterMsg($providerCid);
                    foreach($afterMsgHtmlObj as $obj)
                    {
                        $htmlObj[] = $obj;
                    }

                    $postData['title'] = str_replace(array('<', '>'), '', $message->text);
                    $postData['content'] = json_encode($htmlObj);
                    $postData['timestamp'] = date(DT_FORMAT, $message->ts);
                }

                if ($pin->type == 'file')
                {
                    $file = $pin->file;
                    $htmlObj = array($this->_processFile($file,'is-pin'));

                    $postData['title'] = str_replace(array('<', '>'), '', $file->title);
                    $postData['content'] = json_encode($htmlObj);
                    $postData['timestamp'] = date(DT_FORMAT, $file->timestamp);
                }

                $post = new Post();

                $post->loadByProdviderId($this->provider_id,$providerCid);

                if($post->id)
                {
                    continue;
                }

                $post->id = null;
                $post->title = $postData['title'];
                $post->provider_id = $this->provider_id;
                $post->provider_cid = $providerCid;
                $post->contents = $postData['content'];
                $post->create_time = $postData['timestamp'];
                $post->update_time = $postData['timestamp'];
                $post->save();

                $this->numberChanged++;
            }
            catch(Exception $e)
            {
                Event::write($this->provider_id,Event::E_ERROR,$e->getMessage());
            }
        }

        $postCollection = new Post_Collection();
        $postCollection->reindexAll();

        //Remove saved pin from slack (TO BE DISCUSS) when success
        $this->_removePins();
    }

    protected function _processBeforeMsg($key)
    {
        $beforeMessages = array();
        $htmlObjects = array();
        if(!empty($this->pinBeforeMsgs[$key]))
        {
            $beforeMessages = $this->pinBeforeMsgs[$key];
        }

        foreach($beforeMessages as $msg)
        {
            if($msg->type == 'message')
            {
                $htmlObjects[] = $this->_processMessage($msg, 'before-pin');
            }
            elseif($msg->type == 'file')
            {
                $htmlObjects[] = $this->_processFile($msg,'before-pin');
            }
        }

        return $htmlObjects;
    }

    protected function _processAfterMsg($key)
    {
        $afterMessages = array();
        $htmlObjects = array();
        if(!empty($this->pinAfterMsgs[$key]))
        {
            $afterMessages = $this->pinAfterMsgs[$key];
        }

        foreach($afterMessages as $msg)
        {
            if($msg->type == 'message')
            {
                $htmlObjects[] = $this->_processMessage($msg, 'after-pin');
            }
            elseif($msg->type == 'file')
            {
                $htmlObjects[] = $this->_processFile($msg,'after-pin');
            }
        }

        return $htmlObjects;
    }

    protected function _processMessage($message,$additionalClass = '')
    {
        $htmlObj = new \stdClass();
        $htmlObj->additionalClass = $additionalClass;

        $htmlObj->name = $this->_getUser($message,true);
        $htmlObj->date = date(DT_FORMAT, $message->ts);

        if(property_exists($message, 'subtype') && $message->subtype == 'pinned_item')
        {
            $attachment = $message->attachments[0];
            $attachment->text = $this->_processText($attachment->text);
            if(property_exists($attachment,'author_icon'))
            {
                $htmlObj->text = "pinned <img src=\"{$attachment->author_icon}\"/> {$attachment->author_name}: {$attachment->text}";
            }
            elseif(property_exists($attachment,'author_subname'))
            {
                $htmlObj->text = "pinned {$attachment->author_subname}: {$attachment->text}";
            }
        }
        else
        {
            $htmlObj->text = $this->_processText($message->text);

            if (!empty($message->attachments))
            {
                $attachment = $message->attachments[0];
                if (property_exists($attachment, 'video_html'))
                {
                    $videoUrl = str_replace(array('autoplay=1'), '', $attachment->video_html);
                    $videoUrl = str_replace(array('autoplay'), '', $videoUrl);
                    $htmlObj->attachment = $videoUrl;
                } else if (property_exists($attachment, 'audio_html'))
                {
                    $htmlObj->attachment = $attachment->audio_html;
                } else if (property_exists($attachment, 'image_url'))
                {
                    $htmlObj->attachment = "<img src=\"{$attachment->image_url}\" />";
                } else if (property_exists($attachment, 'thumb_url'))
                {
                    $htmlObj->attachment = "<img src=\"{$attachment->thumb_url}\" />";
                }
                else if (property_exists($attachment, 'service_name')
                    && $attachment->service_name == 'twitter')
                {
                    $htmlObj->attachment = "<a href=\"{$attachment->author_link}\" ><img src=\"{$attachment->author_icon}\">@";
                    $htmlObj->attachment .= "{$attachment->author_name} </a>: {$attachment->text}";
                }
            }
        }

        return $htmlObj;
    }

    protected function _processFile($file,$additionalClass = '')
    {
        $htmlObj = new \stdClass();
        $htmlObj->additionalClass = $additionalClass;
        $htmlObj->name = $this->_getUser($file,true);
        $htmlObj->date = date(DT_FORMAT, $file->timestamp);


        $htmlObj->text = "<a href=\"{$file->permalink}\">A Slack {$file->pretty_type} File</a>";
        /* Should I link the pic?
        if($file->mimetype == 'image/jpeg' ||
           $file->mimetype == 'image/gif'  ||
           $file->mimetype == 'image/webp' ||
           $file->mimetype == 'image/png'
        )
        {
            $htmlObj->attachment .= <img src="'.$file->thumb_480.'"/>;
        }
        */
        return $htmlObj;
    }

    protected function _removePins()
    {
        $removeThreadhold = 50;
        $dataSize = sizeof($this->data);
        if($dataSize <= $removeThreadhold)
        {
            return;
        }

        $this->data = array_reverse($this->data);
        $recToRemove = $dataSize - $removeThreadhold;
        $i = 0;
        foreach($this->data as $pin)
        {
            if($i == $recToRemove)
            {
                break;
            }
            $i++;
            
            $request = array(
                'token' => Slackbot\Setting::API_AUTH_TOKEN,
                'channel' => Slackbot\Setting::THE_B_CHANNEL,
            );

            $removeId = '';
            if($pin->type == 'message')
            {
                $request['timestamp'] = $pin->message->ts;
                $removeId = $request['timestamp'];
            }
            elseif($pin->type == 'file')
            {
                $request['file'] = $pin->file->id;
                $removeId = $request['file'];
            }

            if(Slackbot\Setting::TEST)
            {
                Event::write($this->provider_id,Event::E_INFO,"Test Mode: Remove pin {$removeId}");
            }
            else
            {
                Event::write($this->provider_id,Event::E_INFO,"Remove pin {$removeId}");
                $this->api->unPin($request);
            }
        }

        Event::write($this->provider_id,Event::E_INFO,"Removed {$i} pins");
    }

    /**
     * pull user from msg or file object
     * @param $message
     * @param bool $withPic
     * @return string
     */
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

    protected function _processText($text)
    {
        $text = $this->_processLinks($text);
        $text = $this->_processUserText($text);
        return $text;
    }

    /**
     * <http link> => <a> link
     * @param $text
     * @return string
     */
    protected function _processLinks($text)
    {
        if(strpos($text,'<http') === false)
        {
            return $text;
        }

        $tokens = explode(' ',$text);
        foreach($tokens as $key => $token)
        {
            if(strpos($token,'<http') !== false)
            {
                $token = str_replace(array('<', '>'), '', $token);
                $token = "<a href=\"{$token}\">{$token}</a>";
            }

            $tokens[$key] = $token;
        }
        $text = implode(' ',$tokens);
        return $text;
    }

    /**
     * Process <@U0CKTLCKA|something> => <img> name
     * @param $text
     * @return string
     */
    protected function _processUserText($text)
    {
        if(strpos($text,'<@') === false)
        {
            return $text;
        }

        $tokens = explode(' ',$text);
        foreach($tokens as $key => $token)
        {

            if(!empty($token) && strpos($token,'<@') !== false)
            {
                $userString = substr($token,0,strpos($token,'>')+1);
                $endPart = substr($token,strpos($token,'>')+1);
                $user = str_replace(array('<@','>'),'',$userString);
                $userToken = array();

                if(strpos('|',$userString) !== false)
                {
                    $userToken = explode('|',$userString);
                    $user = $userToken[0];
                }

                $member = $this->slackUser->getMemberById($user);
                $name = $this->slackUser->getName($member);

                //pick stored user id
                if(empty($name) && sizeof($userToken))
                {
                    $name = $userToken[1];
                }

                if(empty($name))
                {
                    $name = 'A rage quited loser';
                }

                $token = '';
                if(property_exists($member,'profile') && property_exists($member->profile,'image_72'))
                {
                    $token .= "<img src=\"{$member->profile->image_72}\" /> ";
                }

                $token .= $name.$endPart;
            }

            $tokens[$key] = $token;
        }
        $text = implode(' ',$tokens);
        return $text;
    }

}