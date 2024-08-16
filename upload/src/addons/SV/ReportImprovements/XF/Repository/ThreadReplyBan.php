<?php

namespace SV\ReportImprovements\XF\Repository;

/**
 * @extends \XF\Repository\ThreadReplyBan
 */
class ThreadReplyBan extends XFCP_ThreadReplyBan
{
    // todo: shim to call entity->delete();
/*
	public function cleanUpExpiredBans($cutOff = null)
	{
		if ($cutOff === null)
		{
			$cutOff = time();
		}
		\XF::db()->delete('xf_thread_reply_ban', 'expiry_date > 0 AND expiry_date < ?', $cutOff);
	}
 */
}