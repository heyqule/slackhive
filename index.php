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


$postCollection = new \Saywut\Post_Collection();

$limit = 50;
$offset = 0;
$anchorUrl = '?';
$query = '';
if(!empty($_GET['query']))
{
    $postCollection->addFullText($_GET['query']);
    $anchorUrl = '?query='.urlencode($_GET['query']);
    $query = htmlentities($_GET['query'],ENT_HTML5);
}

$page = 0;
if(isset($_GET['page']))
{
    $page = $_GET['page'];
    $offset = $page * $limit;
}


$posts = $postCollection->loadByQuery($offset,$limit);

$size = $postCollection->getSize();


?>

<!DOCTYPE html>
<html lang="en">

<head profile="http://www.w3.org/1999/xhtml/vocab">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="//maxcdn.bootstrapcdn.com/font-awesome/4.3.0/css/font-awesome.min.css" rel="stylesheet">
    <link href="main.css" rel="stylesheet">
    <title>ʕつ ͡◔ ᴥ ͡◔ʔつ Bindom</title>
    <meta name="Description" content="wut deescrippteion da ryu wanta?">
</head>
<body>

<h1>ʕつ ͡◔ ᴥ ͡◔ʔつ Bindom</h1>

<section class="controller">
    <form id="search" method="get" action="/">
        Searchere: <input type="text" name="query" value="<?php echo $query ?>" /> <input type="submit" />
    </form>
    <menu>
        Page:
        <?php
        for($i = 0; $i*$limit < $size; $i++) {
            if($i == $page)
                echo ($i+1).' | ';
            else
                echo '<a href="' . $anchorUrl . '&page='.$i.'">'.($i+1).'</a> | ';
        }
        ?>
    </menu>
</section>
<section class="container">
<?php

foreach($posts as $post)
{
    echo "<article class=\"pin-block\">
            {$post->contents}
            <div class=\"msg-close\">◟ʕ´∀`ʔ◞ CLOSE  ◟ʕ´∀`ʔ ◞</div>
          </article>";
}
?>
</section>
<section class="controller">
    <form id="search" method="get" action="/">
        Searchere: <input type="text" name="query" value="<?php echo $query ?>" /> <input type="submit" />
    </form>
    <menu>
        Page:
        <?php
        for($i = 0; $i*$limit < $size; $i++) {
            if($i == $page)
                echo ($i+1).' | ';
            else
                echo '<a href="' . $anchorUrl . '&page='.$i.'">'.($i+1).'</a> | ';
        }
        ?>
    </menu>
</section>


<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.4/jquery.min.js" type="text/javascript"></script>
<script src="main.js" type="text/javascript"></script>
</body>

