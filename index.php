<?php
/**
 * Created by PhpStorm.
 * User: heyqule
 * Date: 09/01/16
 * Time: 10:32 PM
 */

ini_set('display_error',1);
include 'saywut/config.php';
include 'saywut/bots/Slack_Bot.php';

$bot = new \Saywut\Slack_Bot(\Saywut\Core::getBotKey($GLOBALS['BOT_CONFIG'][1]),$GLOBALS['BOT_CONFIG'][1]);
if(!empty($_GET['run']))
{
    $bot->run();
}
?>

<!DOCTYPE html>
<html lang="en">

<head profile="http://www.w3.org/1999/xhtml/vocab">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="//maxcdn.bootstrapcdn.com/font-awesome/4.3.0/css/font-awesome.min.css" rel="stylesheet">
    <link href="main.css" rel="stylesheet">
</head>
<body>

<h1>Da B Pindom</h1>

<section class="controller">

</section>
<section class="container">
<?php

$postCollection = new \Saywut\Post_Collection();

$posts = $postCollection->loadByQuery(0,100);

foreach($posts as $post)
{
    echo "<article class=\"pin-block\">
            {$post->contents}
            <div class=\"msg-close\">xxxx CLOSE xxxx</div>
          </article>";
}
?>


<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.4/jquery.min.js" type="text/javascript"></script>
<script src="main.js" type="text/javascript"></script>
</body>

