<?php

namespace SV\ReportImprovements\XF\Repository;

/**
 * Class ThreadReplyBan
 * @extends \XF\Repository\ThreadReplyBan
 *
 * @package SV\ReportImprovements\XF\Repository
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
		$this->db()->delete('xf_thread_reply_ban', 'expiry_date > 0 AND expiry_date < ?', $cutOff);
	}
 */
}