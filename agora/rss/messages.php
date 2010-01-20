<?php
/**
 * Copyright 2007-2010 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author Duck <duck@obala.net>
 */

require_once dirname(__FILE__) . '/lib/Application.php';
Horde_Registry::appInit('agora', array('authentication' => 'none'));

// Show a specific scope?
list($forum_id, $message_id, $scope) = Agora::getAgoraId();
$cache_key = 'agora_rss_' . $scope . '_' . $forum_id . '_' . $message_id;

/* Initialize the Cache object. */
$cache = &Horde_Cache::singleton($GLOBALS['conf']['cache']['driver'],
                                    Horde::getDriverConfig('cache', $GLOBALS['conf']['cache']['driver']));

$rss = $cache->get($cache_key, $conf['cache']['default_lifetime']);

if (!$rss) {

    $messages = Agora_Messages::singleton($scope, $forum_id);
    $message = $messages->getMessage($message_id);
    if ($message instanceof PEAR_Error) {
        exit;
    }

    $threads_list = $messages->getThreads($message['message_thread'], true, 'message_timestamp', 1, 1, '', null, 0, 10);
    if ($threads_list instanceof PEAR_Error) {
        exit;
    }

    $rss = '<?xml version="1.0" encoding="' . Horde_Nls::getCharset() . '" ?>
    <rss version="2.0">
        <channel>
        <title>' . htmlspecialchars($message['message_subject']) . '</title>
        <language>' . str_replace('_', '-', strtolower(Horde_Nls::select())) . '</language>
        <lastBuildDate>' . date('r') . '</lastBuildDate>
        <description>' . htmlspecialchars($message['message_subject']) . '</description>
        <link>' . Horde::applicationUrl('index.php', true, -1) . '</link>
        <generator>' . htmlspecialchars($registry->get('name')) . '</generator>';

    foreach ($threads_list as $thread_id => $thread) {
        $url = Horde::applicationUrl('messages/index.php', true, -1);
        $url = Agora::setAgoraId($forum_id, $thread_id, $url, $scope, true);
        $rss .= '
        <item>
            <title>' . htmlspecialchars($thread['message_subject']) . ' </title>
            <description>' . htmlspecialchars(trim($thread['body'])) . ' </description>
            <link>' . $url . '</link>
        </item>';
    }

    $rss .= '
    </channel>
    </rss>';

    $cache->set($cache_key, $rss);
}

header('Content-type: text/xml; charset=' . Horde_Nls::getCharset());
echo $rss;
