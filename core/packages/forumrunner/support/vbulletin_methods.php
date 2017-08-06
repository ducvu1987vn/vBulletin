<?php
/*
 * Forum Runner
 *
 * Copyright (c) 2010-2011 to End of Time Studios, LLC
 *
 * This file may not be redistributed in whole or significant part.
 *
 * http://www.forumrunner.com
 */

$methods += array(
    /*
     * like
     *
     * input:
     *
     * postid
     *
     * output:
     *
     * success
     */
    'like' => array(
        'include' => 'misc.php',
        'function' => 'do_like',
    ),
    'get_conversations' => array(
        'include' => 'conversations.php',
        'function' => 'do_get_conversations',
    ),
    'get_conversation' => array(
        'include' => 'conversations.php',
        'function' => 'do_get_conversation',
    ),
    'leave_conversation' => array(
        'include' => 'conversations.php',
        'function' => 'do_leave_conversation',
    ),
    'start_conversation' => array(
        'include' => 'conversations.php',
        'function' => 'do_start_conversation',
    ),
    'reply_conversation' => array(
        'include' => 'conversations.php',
        'function' => 'do_reply_conversation',
    ),
);
