<?php


namespace App\Http\Modules;


use App\Http\Modules\Counter\UnreadMessageCounter;
use App\Http\Repositories\MessageRepository;

class WebSocketPusher
{
    public function pushUnreadMessage($slug, $server = null, $fd = null)
    {
        try
        {
            if ($fd)
            {
                $targetFd = $fd;
            }
            else
            {
                $targetFd = app('swoole')
                    ->wsTable
                    ->get('uid:' . $slug);

                if (false === $targetFd)
                {
                    return;
                }
                $targetFd = $targetFd['value'];
            }

            $pusher = $server ?: app('swoole');
            $UnreadMessageCounter = new UnreadMessageCounter();

            $pusher->push($targetFd, json_encode([
                'channel' => 'unread_total',
                'unread_message_total' => $UnreadMessageCounter->get($slug),
                'unread_notice_total' => 0
            ]));
        }
        catch (\Exception $e) {}
    }

    public function pushChatMessage($slug, $message)
    {
        try
        {
            $targetFd = app('swoole')
                ->wsTable
                ->get('uid:' . $slug);

            if (false === $targetFd)
            {
                return;
            }
            app('swoole')->push($targetFd['value'], json_encode($message));
        }
        catch (\Exception $e)
        {

        }
    }

    public function pushUserMessageList($slug)
    {
        try
        {
            $targetFd = app('swoole')
                ->wsTable
                ->get('uid:' . $slug);

            if (false === $targetFd)
            {
                return;
            }
            $messageRepository = new MessageRepository();
            $menu = $messageRepository->menu($slug);

            app('swoole')->push($targetFd['value'], json_encode([
                'channel' => 'message-menu',
                'data' => $menu
            ]));
        }
        catch (\Exception $e)
        {

        }
    }
}
