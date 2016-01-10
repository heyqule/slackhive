<?php
/**
 * Created by PhpStorm.
 * User: heyqule
 * Date: 12/12/15
 * Time: 8:38 PM
 */
namespace Slackbot;

require_once "Setting.php";
require_once "Api.php";

class SlackUserCollection
{
    static protected $dataCache;

    public function update()
    {
        $finalPath = __DIR__.'/'.Setting::MEMBER_CACHE_FILE;
        if(!file_exists($finalPath))
        {
            touch($finalPath);
            $this->updateMemberData($finalPath);
            return;
        }

        if(time() > filemtime($finalPath) + 7 * 24 * 3600)
        {
            $this->updateMemberData($finalPath);
        }
    }

    public function updateMemberData($finalPath)
    {
        $fileHandler = fopen($finalPath,'w');
        $api = new Api();
        $users = $api->getUserList();
        if($users->ok && $users->members)
        {
            $sortedMember = array();
            foreach($users->members as $obj)
            {
                $sortedMember[$obj->id] = $obj;
            }
            $cacheString = json_encode($sortedMember);
            fputs($fileHandler,$cacheString);
        }
        fclose($fileHandler);
    }

    public function getMemberById($id)
    {
        $this->readMemberData();

        if(empty(self::$dataCache) || empty(self::$dataCache[$id]))
        {
            return $this->getNewb();
        }
        else
        {
            return self::$dataCache[$id];
        }

    }

    public function readMemberData()
    {
        if(empty(self::$dataCache))
        {
            $fileHandler = fopen(__DIR__.'/'.Setting::MEMBER_CACHE_FILE,'r');
            $rawObj = json_decode(fgets($fileHandler));
            $formattedArray = array();
            foreach(get_object_vars($rawObj) as $key => $value)
            {
                $formattedArray[$key] = $value;
            }

            self::$dataCache = $formattedArray;
            fclose($fileHandler);
        }
    }

    public function getNewb()
    {
        $newb = new \stdClass();
        $newb->id = "UNEWB";
        $newb->name = "newb";
        $newb->real_name = "newb";
        return $newb;
    }

    public function getMemberRandomly()
    {
        $this->readMemberData();

        if(is_array(self::$dataCache) && $keyCount = count(self::$dataCache))
        {
            $keys = array_keys(self::$dataCache);
            $selectedKey = $keys[rand(0,$keyCount - 1)];
            return self::$dataCache[$selectedKey];
        }
        else
        {
            return $this->getNewb();
        }
    }

    public function getName($member)
    {
        if(isset($member->real_name))
        {
            return $member->real_name;
        }
        else
        {
            return $member->name;
        }
    }
}